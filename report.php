<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

$userId = (int) $_SESSION['user_id'];
$selectedMonthValue = $_GET['report_month'] ?? date('Y-m');

// If month format is wrong, use current month.
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonthValue)) {
    $selectedMonthValue = date('Y-m');
}

// Split selected value into year and month.
$selectedYear = (int) substr($selectedMonthValue, 0, 4);
$selectedMonth = (int) substr($selectedMonthValue, 5, 2);
$reportMonthName = date('F', strtotime($selectedMonthValue . '-01'));
$generatedDate = date('d M Y');
$reportStartDate = $selectedMonthValue . '-01';
$reportEndDate = date('Y-m-t', strtotime($reportStartDate));

$userSql = "SELECT full_name FROM users WHERE id = $userId";
$userResult = mysqli_query($conn, $userSql);
$user = mysqli_fetch_assoc($userResult);

// Carry forward old balance from months before selected month.
$openingSql = "SELECT
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS old_income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS old_expense
    FROM transactions
    WHERE user_id = $userId
    AND transaction_date < '$reportStartDate'";
$openingResult = mysqli_query($conn, $openingSql);
$openingRow = mysqli_fetch_assoc($openingResult);
$openingBalance = (float) ($openingRow['old_income'] ?? 0) - (float) ($openingRow['old_expense'] ?? 0);

// Monthly total for selected month.
$summarySql = "SELECT
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS total_income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS total_expense,
        COUNT(*) AS total_entries
    FROM transactions
    WHERE user_id = $userId
    AND MONTH(transaction_date) = $selectedMonth
    AND YEAR(transaction_date) = $selectedYear";
$summaryResult = mysqli_query($conn, $summarySql);
$summary = mysqli_fetch_assoc($summaryResult);

$totalIncome = (float) ($summary['total_income'] ?? 0);
$totalExpense = (float) ($summary['total_expense'] ?? 0);
$monthBalance = $totalIncome - $totalExpense;
$closingBalance = $openingBalance + $monthBalance;
$totalEntries = (int) ($summary['total_entries'] ?? 0);

// Budget summary for selected month.
$budgetRows = [];
$budgetSql = "SELECT
        budgets.id,
        budgets.title,
        budgets.monthly_limit,
        SUM(CASE WHEN transactions.type = 'expense' THEN transactions.amount ELSE 0 END) AS spent,
        GROUP_CONCAT(DISTINCT categories.name ORDER BY categories.name SEPARATOR ', ') AS used_categories,
        GROUP_CONCAT(DISTINCT CASE WHEN transactions.title IS NOT NULL AND transactions.title <> '' THEN transactions.title END ORDER BY transactions.transaction_date SEPARATOR ', ') AS used_titles
    FROM budgets
    LEFT JOIN transactions
        ON transactions.budget_id = budgets.id
        AND MONTH(transactions.transaction_date) = $selectedMonth
        AND YEAR(transactions.transaction_date) = $selectedYear
    LEFT JOIN categories
        ON categories.id = transactions.category_id
    WHERE budgets.user_id = $userId
    GROUP BY budgets.id, budgets.title, budgets.monthly_limit
    ORDER BY budgets.title ASC";
$budgetResult = mysqli_query($conn, $budgetSql);
while ($row = mysqli_fetch_assoc($budgetResult)) {
    $budgetRows[] = $row;
}

// Category summary for selected month.
$categoryRows = [];
$categorySql = "SELECT
        categories.name,
        SUM(CASE WHEN transactions.type = 'expense' THEN transactions.amount ELSE 0 END) AS total_spent
    FROM categories
    LEFT JOIN transactions
        ON transactions.category_id = categories.id
        AND transactions.user_id = categories.user_id
        AND MONTH(transactions.transaction_date) = $selectedMonth
        AND YEAR(transactions.transaction_date) = $selectedYear
    WHERE categories.user_id = $userId
    GROUP BY categories.id, categories.name
    ORDER BY total_spent DESC, categories.name ASC";
$categoryResult = mysqli_query($conn, $categorySql);
while ($row = mysqli_fetch_assoc($categoryResult)) {
    if ((float) $row['total_spent'] > 0) {
        $categoryRows[] = $row;
    }
}

// Weekly budgets that start in selected month.
// Only expenses selected with the weekly budget are counted in its spent amount.
$weeklyRows = [];
$weeklySql = "SELECT
        weekly_budgets.id,
        weekly_budgets.title,
        weekly_budgets.week_start,
        weekly_budgets.week_end,
        weekly_budgets.weekly_limit,
        SUM(CASE WHEN transactions.type = 'expense' THEN transactions.amount ELSE 0 END) AS spent,
        GROUP_CONCAT(DISTINCT categories.name ORDER BY categories.name SEPARATOR ', ') AS used_categories,
        GROUP_CONCAT(DISTINCT CASE WHEN transactions.title IS NOT NULL AND transactions.title <> '' THEN transactions.title END ORDER BY transactions.transaction_date SEPARATOR ', ') AS used_titles
    FROM weekly_budgets
    LEFT JOIN transactions
        ON transactions.weekly_budget_id = weekly_budgets.id
        AND transactions.user_id = weekly_budgets.user_id
        AND transactions.transaction_date BETWEEN weekly_budgets.week_start AND weekly_budgets.week_end
    LEFT JOIN categories
        ON categories.id = transactions.category_id
    WHERE weekly_budgets.user_id = $userId
    AND MONTH(weekly_budgets.week_start) = $selectedMonth
    AND YEAR(weekly_budgets.week_start) = $selectedYear
    GROUP BY weekly_budgets.id, weekly_budgets.title, weekly_budgets.week_start, weekly_budgets.week_end, weekly_budgets.weekly_limit
    ORDER BY weekly_budgets.week_start ASC";
$weeklyResult = mysqli_query($conn, $weeklySql);
while ($row = mysqli_fetch_assoc($weeklyResult)) {
    $weeklyRows[] = $row;
}

// Transaction details for selected month.
$transactionRows = [];
$transactionSql = "SELECT
        transactions.transaction_date,
        transactions.title,
        transactions.type,
        transactions.amount,
        categories.name AS category_name,
        budgets.title AS monthly_budget_name,
        weekly_budgets.title AS weekly_budget_name
    FROM transactions
    LEFT JOIN categories ON categories.id = transactions.category_id
    LEFT JOIN budgets ON budgets.id = transactions.budget_id
    LEFT JOIN weekly_budgets ON weekly_budgets.id = transactions.weekly_budget_id
    WHERE transactions.user_id = $userId
    AND transactions.transaction_date BETWEEN '$reportStartDate' AND '$reportEndDate'
    ORDER BY transactions.transaction_date DESC, transactions.id DESC";
$transactionResult = mysqli_query($conn, $transactionSql);
while ($row = mysqli_fetch_assoc($transactionResult)) {
    $transactionRows[] = $row;
}

function formatRupees($amount)
{
    return 'Rs ' . number_format((float) $amount, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Report</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="page-shell">
        <aside class="sidebar">
            <div class="brand-block">
                <div class="brand-mark">MB</div>
                <div>
                    <p class="eyebrow">Monthly Budget Planner</p>
                    <h1>Reports</h1>
                </div>
            </div>

            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="add_transaction.php">Add Transaction</a>
                <a href="budgets.php">Monthly Budgets</a>
                <a href="weekly_budgets.php">Weekly Budgets</a>
                <a class="active" href="report.php">Monthly Report</a>
                <a href="history.php">Financial History</a>
            </nav>

            <section class="premium-card">
                <p class="eyebrow">Organization Account</p>
                <h2><?= htmlspecialchars($user['full_name'] ?? $_SESSION['user_name']) ?></h2>
                <p><?= htmlspecialchars($_SESSION['user_email']) ?></p>
                <a class="button-link" href="logout.php">Logout</a>
            </section>
        </aside>

        <main class="dashboard">
            <section class="panel report-filter-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Monthly Report</p>
                        <h3>Select Month</h3>
                    </div>
                </div>

                <form method="get" class="report-filter-form">
                    <label>
                        <span>Choose Month</span>
                        <input type="month" name="report_month" value="<?= htmlspecialchars($selectedMonthValue) ?>">
                    </label>
                    <button type="submit">View Report</button>
                </form>
            </section>

            <section class="report-receipt">
                <div class="receipt-top">
                    <div>
                        <p class="eyebrow">Monthly Budget Planner</p>
                        <h2>Monthly Report Receipt</h2>
                    </div>
                    <div class="receipt-top-meta">
                        <p><strong>Organization:</strong> <?= htmlspecialchars($user['full_name'] ?? $_SESSION['user_name']) ?></p>
                        <p><strong>Month:</strong> <?= htmlspecialchars($reportMonthName . ' ' . $selectedYear) ?></p>
                        <p><strong>Generated:</strong> <?= htmlspecialchars($generatedDate) ?></p>
                    </div>
                </div>

                <div class="receipt-summary-grid">
                    <div class="receipt-box">
                        <span>Opening Balance</span>
                        <strong><?= htmlspecialchars(formatRupees($openingBalance)) ?></strong>
                    </div>
                    <div class="receipt-box">
                        <span>Total Income</span>
                        <strong><?= htmlspecialchars(formatRupees($totalIncome)) ?></strong>
                    </div>
                    <div class="receipt-box">
                        <span>Total Expense</span>
                        <strong><?= htmlspecialchars(formatRupees($totalExpense)) ?></strong>
                    </div>
                    <div class="receipt-box">
                        <span>Closing Balance</span>
                        <strong><?= htmlspecialchars(formatRupees($closingBalance)) ?></strong>
                    </div>
                </div>

                <div class="receipt-summary-grid report-extra-grid">
                    <div class="receipt-box">
                        <span>Month Result</span>
                        <strong><?= htmlspecialchars(formatRupees($monthBalance)) ?></strong>
                    </div>
                    <div class="receipt-box">
                        <span>Total Entries</span>
                        <strong><?= $totalEntries ?></strong>
                    </div>
                </div>

                <div class="receipt-section">
                    <div class="receipt-section-header">
                        <h3>Budget Summary</h3>
                    </div>

                    <?php if (count($budgetRows) === 0): ?>
                        <p class="receipt-empty">No budget data found for this month.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Budget</th>
                                        <th>Limit</th>
                                        <th>Used</th>
                                        <th>Remaining</th>
                                        <th>Status</th>
                                        <th>Used In Categories</th>
                                        <th>Expense Titles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($budgetRows as $budget): ?>
                                        <?php
                                        $limit = (float) $budget['monthly_limit'];
                                        $spent = (float) $budget['spent'];
                                        $remaining = $limit - $spent;
                                        $status = $spent > $limit ? 'Exceeded' : 'Within Limit';
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($budget['title']) ?></td>
                                            <td><?= htmlspecialchars(formatRupees($limit)) ?></td>
                                            <td><?= htmlspecialchars(formatRupees($spent)) ?></td>
                                            <td><?= htmlspecialchars(formatRupees($remaining)) ?></td>
                                            <td class="<?= $spent > $limit ? 'negative' : 'positive' ?>"><?= htmlspecialchars($status) ?></td>
                                            <td><?= htmlspecialchars($budget['used_categories'] ?: 'No expense used') ?></td>
                                            <td><?= htmlspecialchars($budget['used_titles'] ?: 'No expense used') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="receipt-section">
                    <div class="receipt-section-header">
                        <h3>Category Summary</h3>
                    </div>

                    <?php if (count($categoryRows) === 0): ?>
                        <p class="receipt-empty">No category expense found for this month.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Total Expense</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categoryRows as $category): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($category['name']) ?></td>
                                            <td><?= htmlspecialchars(formatRupees($category['total_spent'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="receipt-section">
                    <div class="receipt-section-header">
                        <h3>Weekly Budget Summary</h3>
                    </div>

                    <?php if (count($weeklyRows) === 0): ?>
                        <p class="receipt-empty">No weekly budget found for this month.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Weekly Budget</th>
                                        <th>Date Range</th>
                                        <th>Limit</th>
                                        <th>Used</th>
                                        <th>Remaining</th>
                                        <th>Status</th>
                                        <th>Used In Categories</th>
                                        <th>Expense Titles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($weeklyRows as $weekly): ?>
                                        <?php
                                        $weeklyLimit = (float) $weekly['weekly_limit'];
                                        $weeklySpent = (float) $weekly['spent'];
                                        $weeklyLeft = $weeklyLimit - $weeklySpent;
                                        $weeklyStatus = $weeklySpent > $weeklyLimit ? 'Exceeded' : 'Within Limit';
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($weekly['title']) ?></td>
                                            <td><?= htmlspecialchars(date('d M Y', strtotime($weekly['week_start']))) ?> to <?= htmlspecialchars(date('d M Y', strtotime($weekly['week_end']))) ?></td>
                                            <td><?= htmlspecialchars(formatRupees($weeklyLimit)) ?></td>
                                            <td><?= htmlspecialchars(formatRupees($weeklySpent)) ?></td>
                                            <td><?= htmlspecialchars(formatRupees($weeklyLeft)) ?></td>
                                            <td class="<?= $weeklySpent > $weeklyLimit ? 'negative' : 'positive' ?>"><?= htmlspecialchars($weeklyStatus) ?></td>
                                            <td><?= htmlspecialchars($weekly['used_categories'] ?: 'No expense used') ?></td>
                                            <td><?= htmlspecialchars($weekly['used_titles'] ?: 'No expense used') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="receipt-section">
                    <div class="receipt-section-header">
                        <h3>Transaction Details</h3>
                    </div>

                    <?php if (count($transactionRows) === 0): ?>
                        <p class="receipt-empty">No transaction found for this month.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Category</th>
                                        <th>Monthly Budget</th>
                                        <th>Weekly Budget</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactionRows as $transaction): ?>
                                        <tr>
                                            <td><?= htmlspecialchars(date('d M Y', strtotime($transaction['transaction_date']))) ?></td>
                                            <td><?= htmlspecialchars($transaction['title']) ?></td>
                                            <td><?= htmlspecialchars(ucfirst($transaction['type'])) ?></td>
                                            <td><?= htmlspecialchars($transaction['category_name'] ?: 'No Category') ?></td>
                                            <td><?= htmlspecialchars($transaction['monthly_budget_name'] ?: '-') ?></td>
                                            <td><?= htmlspecialchars($transaction['weekly_budget_name'] ?: '-') ?></td>
                                            <td class="<?= $transaction['type'] === 'income' ? 'positive' : 'negative' ?>">
                                                <?= htmlspecialchars(formatRupees($transaction['amount'])) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="receipt-footer">
                    <p>This report shows summary and transaction data for <?= htmlspecialchars($reportMonthName . ' ' . $selectedYear) ?>.</p>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
