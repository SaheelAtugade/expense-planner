<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

$userId = (int) $_SESSION['user_id'];

$userSql = "SELECT full_name FROM users WHERE id = $userId";
$userResult = mysqli_query($conn, $userSql);
$user = mysqli_fetch_assoc($userResult);

// Get monthly income and expense totals for all saved months.
$monthlyRows = [];
$monthlySql = "SELECT
        DATE_FORMAT(transaction_date, '%Y-%m') AS month_value,
        DATE_FORMAT(transaction_date, '%b %Y') AS month_label,
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS total_income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS total_expense
    FROM transactions
    WHERE user_id = $userId
    GROUP BY YEAR(transaction_date), MONTH(transaction_date)
    ORDER BY YEAR(transaction_date) ASC, MONTH(transaction_date) ASC";
$monthlyResult = mysqli_query($conn, $monthlySql);
while ($row = mysqli_fetch_assoc($monthlyResult)) {
    $monthlyRows[] = $row;
}

// Count exceeded monthly budgets for each month.
$exceededMap = [];
$exceededSql = "SELECT
        DATE_FORMAT(exceeded_budgets.transaction_date, '%Y-%m') AS month_value,
        COUNT(*) AS exceeded_count
    FROM (
        SELECT
            transactions.transaction_date,
            budgets.id,
            budgets.monthly_limit,
            SUM(transactions.amount) AS spent
        FROM transactions
        INNER JOIN budgets ON budgets.id = transactions.budget_id
        WHERE transactions.user_id = $userId
        AND transactions.type = 'expense'
        GROUP BY YEAR(transactions.transaction_date), MONTH(transactions.transaction_date), budgets.id, budgets.monthly_limit
        HAVING spent > budgets.monthly_limit
    ) AS exceeded_budgets
    GROUP BY month_value";
$exceededResult = mysqli_query($conn, $exceededSql);
while ($row = mysqli_fetch_assoc($exceededResult)) {
    $exceededMap[$row['month_value']] = (int) $row['exceeded_count'];
}

// Build opening and closing balance month by month.
$historyRows = [];
$runningBalance = 0;
foreach ($monthlyRows as $row) {
    $income = (float) ($row['total_income'] ?? 0);
    $expense = (float) ($row['total_expense'] ?? 0);
    $openingBalance = $runningBalance;
    $closingBalance = $openingBalance + $income - $expense;

    $historyRows[] = [
        'month_value' => $row['month_value'],
        'month_label' => $row['month_label'],
        'opening_balance' => $openingBalance,
        'income' => $income,
        'expense' => $expense,
        'closing_balance' => $closingBalance,
        'exceeded_count' => $exceededMap[$row['month_value']] ?? 0,
    ];

    $runningBalance = $closingBalance;
}

$historyRows = array_reverse($historyRows);

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
    <title>Financial History</title>
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
                    <h1>History</h1>
                </div>
            </div>

            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="add_transaction.php">Add Transaction</a>
                <a href="budgets.php">Monthly Budgets</a>
                <a href="weekly_budgets.php">Weekly Budgets</a>
                <a href="report.php">Monthly Report</a>
                <a class="active" href="history.php">Financial History</a>
            </nav>

            <section class="premium-card">
                <p class="eyebrow">Organization Account</p>
                <h2><?= htmlspecialchars($user['full_name'] ?? $_SESSION['user_name']) ?></h2>
                <p><?= htmlspecialchars($_SESSION['user_email']) ?></p>
                <a class="button-link" href="logout.php">Logout</a>
            </section>
        </aside>

        <main class="dashboard">
            <header class="hero-card">
                <div>
                    <p class="eyebrow">Month Wise Summary</p>
                    <h2 id="history-heading">Track income, expenses, savings, and budget performance for every month.</h2>
                    <p class="hero-copy">This page keeps all old months data to show your budget performance over time.</p>
                </div>
            </header>

            <section class="panel panel-wide">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Financial History</p>
                        <h3>All month summary</h3>
                    </div>
                </div>

                <?php if (count($historyRows) === 0): ?>
                    <p class="simple-note">No transaction history found yet. Add income or expense to start monthly tracking.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Opening Balance</th>
                                    <th>Income</th>
                                    <th>Expense</th>
                                    <th>Closing Balance</th>
                                    <th>Exceeded Budgets</th>
                                    <th>Report</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historyRows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['month_label']) ?></td>
                                        <td><?= htmlspecialchars(formatRupees($row['opening_balance'])) ?></td>
                                        <td class="positive"><?= htmlspecialchars(formatRupees($row['income'])) ?></td>
                                        <td class="negative"><?= htmlspecialchars(formatRupees($row['expense'])) ?></td>
                                        <td><?= htmlspecialchars(formatRupees($row['closing_balance'])) ?></td>
                                        <td><?= (int) $row['exceeded_count'] ?></td>
                                        <td><a class="table-link" href="report.php?report_month=<?= htmlspecialchars($row['month_value']) ?>">View Report</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
