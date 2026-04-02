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

$userSql = "SELECT full_name FROM users WHERE id = $userId";
$userResult = mysqli_query($conn, $userSql);
$user = mysqli_fetch_assoc($userResult);

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
$balance = $totalIncome - $totalExpense;
$totalEntries = (int) ($summary['total_entries'] ?? 0);

// Budget summary for selected month.
$budgetRows = [];
$budgetSql = "SELECT
        budgets.title,
        budgets.monthly_limit,
        SUM(CASE WHEN transactions.type = 'expense' THEN transactions.amount ELSE 0 END) AS spent
    FROM budgets
    LEFT JOIN transactions
        ON transactions.budget_id = budgets.id
        AND MONTH(transactions.transaction_date) = $selectedMonth
        AND YEAR(transactions.transaction_date) = $selectedYear
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
                <div class="brand-mark">EP</div>
                <div>
                    <p class="eyebrow">Expense Planner</p>
                    <h1>Reports</h1>
                </div>
            </div>

            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a class="active" href="report.php">Monthly Report</a>
                <a href="dashboard.php#add-transaction">Add Entry</a>
                <a href="dashboard.php#transactions">Transactions</a>
                <a href="dashboard.php#budgets">Budgets</a>
                <a href="dashboard.php#categories">Categories</a>
            </nav>

            <section class="premium-card">
                <p class="eyebrow">Report User</p>
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
                        <p class="eyebrow">Expense Planner</p>
                        <h2>Monthly Report Receipt</h2>
                    </div>
                    <div class="receipt-top-meta">
                        <p><strong>User:</strong> <?= htmlspecialchars($user['full_name'] ?? $_SESSION['user_name']) ?></p>
                        <p><strong>Month:</strong> <?= htmlspecialchars($reportMonthName . ' ' . $selectedYear) ?></p>
                        <p><strong>Generated:</strong> <?= htmlspecialchars($generatedDate) ?></p>
                    </div>
                </div>

                <div class="receipt-summary-grid">
                    <div class="receipt-box">
                        <span>Total Income</span>
                        <strong><?= htmlspecialchars(formatRupees($totalIncome)) ?></strong>
                    </div>
                    <div class="receipt-box">
                        <span>Total Expense</span>
                        <strong><?= htmlspecialchars(formatRupees($totalExpense)) ?></strong>
                    </div>
                    <div class="receipt-box">
                        <span>Balance Left</span>
                        <strong><?= htmlspecialchars(formatRupees($balance)) ?></strong>
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
                        <?php foreach ($budgetRows as $budget): ?>
                            <?php
                            $limit = (float) $budget['monthly_limit'];
                            $spent = (float) $budget['spent'];
                            $remaining = $limit - $spent;
                            $status = $spent > $limit ? 'Exceeded' : 'Within Limit';
                            ?>
                            <div class="receipt-line">
                                <div>
                                    <strong><?= htmlspecialchars($budget['title']) ?></strong>
                                    <span>Limit: <?= htmlspecialchars(formatRupees($limit)) ?></span>
                                </div>
                                <div class="receipt-line-values">
                                    <span>Spent: <?= htmlspecialchars(formatRupees($spent)) ?></span>
                                    <span>Left: <?= htmlspecialchars(formatRupees($remaining)) ?></span>
                                    <span class="<?= $spent > $limit ? 'status-exceeded' : 'status-within' ?>"><?= htmlspecialchars($status) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="receipt-section">
                    <div class="receipt-section-header">
                        <h3>Category Summary</h3>
                    </div>

                    <?php if (count($categoryRows) === 0): ?>
                        <p class="receipt-empty">No category expense found for this month.</p>
                    <?php else: ?>
                        <?php foreach ($categoryRows as $category): ?>
                            <div class="receipt-line">
                                <div>
                                    <strong><?= htmlspecialchars($category['name']) ?></strong>
                                </div>
                                <div class="receipt-line-values">
                                    <span><?= htmlspecialchars(formatRupees($category['total_spent'])) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="receipt-footer">
                    <p>This receipt shows summary data for <?= htmlspecialchars($reportMonthName . ' ' . $selectedYear) ?> only.</p>
                    <p>Old records stay saved in the database and can be viewed by choosing another month.</p>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
