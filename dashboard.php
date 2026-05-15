<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

// Logged in user id comes from session after login.
$userId = (int) $_SESSION['user_id'];
$currentMonth = date('m');
$currentYear = date('Y');
$currentMonthName = date('F');
$today = date('Y-m-d');
$monthStartDate = date('Y-m-01');

// Get user details.
$userSql = "SELECT full_name FROM users WHERE id = $userId";
$userResult = mysqli_query($conn, $userSql);
$user = mysqli_fetch_assoc($userResult);

// Make sure every user has one default budget called No Budget.
$checkNoBudgetSql = "SELECT id FROM budgets WHERE user_id = $userId AND title = 'No Budget'";
$checkNoBudgetResult = mysqli_query($conn, $checkNoBudgetSql);
$noBudgetRow = mysqli_fetch_assoc($checkNoBudgetResult);

if (!$noBudgetRow) {
    // Create one default budget if user does not have it.
    $addNoBudgetSql = "INSERT INTO budgets (user_id, title, monthly_limit) VALUES ($userId, 'No Budget', 999999.00)";
    mysqli_query($conn, $addNoBudgetSql);
}

// Get total income and total expense for current month only.
$summarySql = "SELECT
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS total_income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS total_expense
     FROM transactions";
$summarySql .= " WHERE user_id = $userId
                 AND MONTH(transaction_date) = $currentMonth
                 AND YEAR(transaction_date) = $currentYear";
$summaryResult = mysqli_query($conn, $summarySql);
$summary = mysqli_fetch_assoc($summaryResult);

// Carry forward the old balance from all previous months.
$openingSql = "SELECT
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS old_income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS old_expense
     FROM transactions
     WHERE user_id = $userId
     AND transaction_date < '$monthStartDate'";
$openingResult = mysqli_query($conn, $openingSql);
$openingRow = mysqli_fetch_assoc($openingResult);

$openingBalance = (float) ($openingRow['old_income'] ?? 0) - (float) ($openingRow['old_expense'] ?? 0);
$totalIncome = (float) ($summary['total_income'] ?? 0);
$totalExpense = (float) ($summary['total_expense'] ?? 0);
$monthBalance = $totalIncome - $totalExpense;
$closingBalance = $openingBalance + $monthBalance;

// Count active weekly budgets for today.
$activeWeeklySql = "SELECT COUNT(*) AS total_weekly FROM weekly_budgets
                    WHERE user_id = $userId
                    AND '$today' BETWEEN week_start AND week_end";
$activeWeeklyResult = mysqli_query($conn, $activeWeeklySql);
$activeWeeklyRow = mysqli_fetch_assoc($activeWeeklyResult);
$activeWeeklyCount = (int) $activeWeeklyRow['total_weekly'];

// Get budget usage for current month only.
$budgets = [];
$budgetSql = "SELECT
        budgets.id,
        budgets.title,
        budgets.monthly_limit,
        SUM(CASE WHEN transactions.type = 'expense' THEN transactions.amount ELSE 0 END) AS spent
     FROM budgets
     LEFT JOIN transactions
        ON transactions.budget_id = budgets.id
        AND MONTH(transactions.transaction_date) = $currentMonth
        AND YEAR(transactions.transaction_date) = $currentYear
     WHERE budgets.user_id = $userId
     GROUP BY budgets.id, budgets.title, budgets.monthly_limit
     ORDER BY budgets.id ASC";
$budgetResult = mysqli_query($conn, $budgetSql);
while ($row = mysqli_fetch_assoc($budgetResult)) {
    $budgets[] = $row;
}

// Get active weekly budget usage.
// Important: Only transactions selected with this weekly budget are counted here.
$weeklyBudgets = [];
$weeklySql = "SELECT
        weekly_budgets.title,
        weekly_budgets.week_start,
        weekly_budgets.week_end,
        weekly_budgets.weekly_limit,
        SUM(CASE WHEN transactions.type = 'expense' THEN transactions.amount ELSE 0 END) AS spent
     FROM weekly_budgets
     LEFT JOIN transactions
        ON transactions.weekly_budget_id = weekly_budgets.id
        AND transactions.user_id = weekly_budgets.user_id
        AND transactions.transaction_date BETWEEN weekly_budgets.week_start AND weekly_budgets.week_end
     WHERE weekly_budgets.user_id = $userId
     AND '$today' BETWEEN weekly_budgets.week_start AND weekly_budgets.week_end
     GROUP BY weekly_budgets.id, weekly_budgets.title, weekly_budgets.week_start, weekly_budgets.week_end, weekly_budgets.weekly_limit
     ORDER BY weekly_budgets.week_start ASC";
$weeklyResult = mysqli_query($conn, $weeklySql);
while ($row = mysqli_fetch_assoc($weeklyResult)) {
    $weeklyBudgets[] = $row;
}

// Get top 3 categories for current month.
$categories = [];
$topCategorySql = "SELECT
        categories.name,
        SUM(CASE WHEN transactions.type = 'expense' THEN transactions.amount ELSE 0 END) AS total_spent
     FROM categories
     LEFT JOIN transactions
        ON transactions.category_id = categories.id
        AND transactions.user_id = categories.user_id
        AND MONTH(transactions.transaction_date) = $currentMonth
        AND YEAR(transactions.transaction_date) = $currentYear
     WHERE categories.user_id = $userId
     GROUP BY categories.id, categories.name
     ORDER BY total_spent DESC, categories.name ASC
     LIMIT 3";
$topCategoryResult = mysqli_query($conn, $topCategorySql);
while ($row = mysqli_fetch_assoc($topCategoryResult)) {
    $categories[] = $row;
}

// Get latest transactions for current month.
$transactions = [];
$transactionSql = "SELECT
        transactions.title,
        categories.name AS category_name,
        transactions.transaction_date,
        transactions.amount,
        transactions.type
     FROM transactions
     LEFT JOIN categories ON categories.id = transactions.category_id
     WHERE transactions.user_id = $userId
     AND MONTH(transactions.transaction_date) = $currentMonth
     AND YEAR(transactions.transaction_date) = $currentYear
     ORDER BY transactions.transaction_date DESC, transactions.id DESC
     LIMIT 8";
$transactionResult = mysqli_query($conn, $transactionSql);
while ($row = mysqli_fetch_assoc($transactionResult)) {
    $transactions[] = $row;
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
    <title>Monthly Budget Planner Dashboard</title>
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
                    <h1>Dashboard</h1>
                </div>
            </div>

            <nav class="nav-links">
                <a class="active" href="dashboard.php">Dashboard</a>
                <a href="add_transaction.php">Add Transaction</a>
                <a href="budgets.php">Monthly Budgets</a>
                <a href="weekly_budgets.php">Weekly Budgets</a>
                <a href="report.php">Monthly Report</a>
                <a href="history.php">Budget Report</a>
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
                    <p class="eyebrow"><?= htmlspecialchars($currentMonthName . ' ' . $currentYear) ?> Overview</p>
                    <h2>Monitor monthly budgets and weekly spending plans for <?= htmlspecialchars($currentMonthName . ' ' . $currentYear) ?>.</h2>
                    <!-- <p class="hero-copy">This dashboard shows overview data only. Use separate pages to add transactions, monthly budgets, and weekly budgets.</p> -->
                </div>
                <div class="hero-metrics">
                    <div>
                        <span>Opening Balance</span>
                        <strong><?= htmlspecialchars(formatRupees($openingBalance)) ?></strong>
                    </div>
                    <div>
                        <span>Active Weekly Budgets</span>
                        <strong><?= $activeWeeklyCount ?></strong>
                    </div>
                </div>
            </header>

            <section class="summary-grid">
                <article class="summary-card blue">
                    <p>Opening Balance</p>
                    <h3><?= htmlspecialchars(formatRupees($openingBalance)) ?></h3>
                    <!-- <span>Saved amount carried from old months</span> -->
                </article>

                <article class="summary-card sky">
                    <p>Total Income</p>
                    <h3><?= htmlspecialchars(formatRupees($totalIncome)) ?></h3>
                    <!-- <span>Money added to your account</span> -->
                </article>

                <article class="summary-card blue">
                    <p>Total Expense</p>
                    <h3><?= htmlspecialchars(formatRupees($totalExpense)) ?></h3>
                    <!-- <span>Money spent so far</span> -->
                </article>

                <article class="summary-card navy">
                    <p>Closing Balance</p>
                    <h3><?= htmlspecialchars(formatRupees($closingBalance)) ?></h3>
                    <!-- <span>Opening balance plus this month result</span> -->
                </article>
            </section>

            <section class="content-grid">
                <article class="panel panel-large" id="budgets">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Budget Status</p>
                            <h3>Monthly budgets</h3>
                        </div>
                    </div>

                    <div class="budget-list">
                        <?php foreach ($budgets as $budget): ?>
                            <?php
                            $limit = (float) $budget['monthly_limit'];
                            $spent = (float) $budget['spent'];
                            $percent = $limit > 0 ? min(100, (int) round(($spent / $limit) * 100)) : 0;
                            ?>
                            <div class="budget-row">
                                <div class="budget-meta">
                                    <strong><?= htmlspecialchars($budget['title']) ?></strong>
                                    <span><?= htmlspecialchars(formatRupees($spent)) ?> of <?= htmlspecialchars(formatRupees($limit)) ?></span>
                                </div>
                                <div class="budget-progress-block">
                                    <div class="progress-track">
                                        <span style="width: <?= $percent ?>%"></span>
                                    </div>
                                    <?php if ($spent > $limit): ?>
                                        <small class="budget-warning">Budget limit exceeded</small>
                                    <?php endif; ?>
                                </div>
                                <em><?= $percent ?>%</em>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="panel insights-panel" id="categories">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Categories</p>
                            <h3>Top spending categories</h3>
                        </div>
                    </div>

                    <div class="category-stack">
                        <?php foreach ($categories as $category): ?>
                            <div class="category-item">
                                <div>
                                    <strong><?= htmlspecialchars($category['name']) ?></strong>
                                    <span>Total expense in this category</span>
                                </div>
                                <b><?= htmlspecialchars(formatRupees($category['total_spent'])) ?></b>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- <div class="mini-stat">
                        <p>Connected to database</p>
                        <strong>Login and dashboard are working together</strong>
                    </div> -->
                </article>

                <article class="panel panel-wide" id="weekly-budgets">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Weekly Budget Status</p>
                            <h3>Active weekly budgets</h3>
                        </div>
                    </div>

                    <div class="budget-list">
                        <?php if (count($weeklyBudgets) === 0): ?>
                            <p class="simple-note">No active weekly budget found for today.</p>
                        <?php endif; ?>

                        <?php foreach ($weeklyBudgets as $weeklyBudget): ?>
                            <?php
                            $weeklyLimit = (float) $weeklyBudget['weekly_limit'];
                            $weeklySpent = (float) $weeklyBudget['spent'];
                            $weeklyPercent = $weeklyLimit > 0 ? min(100, (int) round(($weeklySpent / $weeklyLimit) * 100)) : 0;
                            ?>
                            <div class="budget-row">
                                <div class="budget-meta">
                                    <strong><?= htmlspecialchars($weeklyBudget['title']) ?></strong>
                                    <span><?= htmlspecialchars(date('d M Y', strtotime($weeklyBudget['week_start']))) ?> to <?= htmlspecialchars(date('d M Y', strtotime($weeklyBudget['week_end']))) ?></span>
                                </div>
                                <div class="budget-progress-block">
                                    <div class="progress-track">
                                        <span style="width: <?= $weeklyPercent ?>%"></span>
                                    </div>
                                    <span><?= htmlspecialchars(formatRupees($weeklySpent)) ?> of <?= htmlspecialchars(formatRupees($weeklyLimit)) ?></span>
                                    <?php if ($weeklySpent > $weeklyLimit): ?>
                                        <small class="budget-warning">Weekly budget exceeded</small>
                                    <?php else: ?>
                                        <small class="status-within">On Track</small>
                                    <?php endif; ?>
                                </div>
                                <em><?= $weeklyPercent ?>%</em>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="panel panel-wide" id="transactions">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Recent Activity</p>
                            <h3>Recent transactions</h3>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($transaction['title']) ?></td>
                                        <td><?= htmlspecialchars($transaction['category_name'] ?? 'No Category') ?></td>
                                        <td><?= htmlspecialchars(date('d M Y', strtotime($transaction['transaction_date']))) ?></td>
                                        <td class="<?= $transaction['type'] === 'income' ? 'positive' : 'negative' ?>">
                                            <?= htmlspecialchars(($transaction['type'] === 'income' ? '+ ' : '- ') . formatRupees($transaction['amount'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
        </main>
    </div>
</body>
</html>
