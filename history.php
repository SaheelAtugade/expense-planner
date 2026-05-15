<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

$userId = (int) $_SESSION['user_id'];
$selectedBudgetId = $_GET['budget_id'] ?? '';
$selectedWeeklyBudgetName = trim($_GET['weekly_budget_name'] ?? '');
$selectedCategoryId = $_GET['category_id'] ?? '';
$selectedType = $_GET['type'] ?? '';
$searchTitle = trim($_GET['search_title'] ?? '');

if (!ctype_digit((string) $selectedBudgetId)) {
    $selectedBudgetId = '';
}

if (!ctype_digit((string) $selectedCategoryId)) {
    $selectedCategoryId = '';
}

if ($selectedType !== 'income' && $selectedType !== 'expense') {
    $selectedType = '';
}

$userSql = "SELECT full_name FROM users WHERE id = $userId";
$userResult = mysqli_query($conn, $userSql);
$user = mysqli_fetch_assoc($userResult);

$budgetList = [];
$budgetListResult = mysqli_query($conn, "SELECT id, title FROM budgets WHERE user_id = $userId ORDER BY title ASC");
while ($row = mysqli_fetch_assoc($budgetListResult)) {
    $budgetList[] = $row;
}

$weeklyBudgetList = [];
$weeklyBudgetListResult = mysqli_query($conn, "SELECT id, title FROM weekly_budgets WHERE user_id = $userId ORDER BY week_start DESC");
while ($row = mysqli_fetch_assoc($weeklyBudgetListResult)) {
    $cleanName = cleanWeeklyBudgetTitle($row['title']);
    if (!in_array($cleanName, $weeklyBudgetList, true)) {
        $weeklyBudgetList[] = $cleanName;
    }
}

$categoryList = [];
$categoryListResult = mysqli_query($conn, "SELECT id, name FROM categories WHERE user_id = $userId ORDER BY name ASC");
while ($row = mysqli_fetch_assoc($categoryListResult)) {
    $categoryList[] = $row;
}

$safeSelectedType = mysqli_real_escape_string($conn, $selectedType);
$safeSearchTitle = mysqli_real_escape_string($conn, $searchTitle);
$safeSelectedWeeklyBudgetName = mysqli_real_escape_string($conn, $selectedWeeklyBudgetName);
$hasSpecificFilter = $selectedBudgetId !== '' || $selectedWeeklyBudgetName !== '' || $selectedCategoryId !== '' || $selectedType !== '' || $searchTitle !== '';
$weeklyBudgetMatchSql = '';
if ($selectedWeeklyBudgetName !== '') {
    $weeklyBudgetMatchSql = "transactions.weekly_budget_id IN (
        SELECT wb.id
        FROM weekly_budgets AS wb
        WHERE wb.user_id = $userId
        AND " . cleanWeeklyBudgetTitleSql("wb.title") . " = '$safeSelectedWeeklyBudgetName'
    )";
}

$transactionFilterSql = "transactions.user_id = $userId";
if ($selectedBudgetId !== '') {
    $transactionFilterSql .= " AND transactions.budget_id = " . (int) $selectedBudgetId;
}
if ($selectedWeeklyBudgetName !== '') {
    $transactionFilterSql .= " AND $weeklyBudgetMatchSql";
}
if ($selectedCategoryId !== '') {
    $transactionFilterSql .= " AND transactions.category_id = " . (int) $selectedCategoryId;
}
if ($selectedType !== '') {
    $transactionFilterSql .= " AND transactions.type = '$safeSelectedType'";
}
if ($searchTitle !== '') {
    $transactionFilterSql .= " AND transactions.title LIKE '%$safeSearchTitle%'";
}

$summaryRows = [];
$summarySql = "SELECT
        DATE_FORMAT(transactions.transaction_date, '%Y-%m') AS month_value,
        DATE_FORMAT(transactions.transaction_date, '%b %Y') AS month_label,
        SUM(CASE WHEN transactions.type = 'income' THEN transactions.amount ELSE 0 END) AS total_income,
        SUM(CASE WHEN transactions.type = 'expense' THEN transactions.amount ELSE 0 END) AS total_expense,
        COUNT(*) AS total_entries,
        COUNT(DISTINCT transactions.budget_id) AS monthly_budget_count,
        COUNT(DISTINCT transactions.weekly_budget_id) AS weekly_budget_count
    FROM transactions
    WHERE transactions.user_id = $userId
    GROUP BY YEAR(transactions.transaction_date), MONTH(transactions.transaction_date)
    ORDER BY YEAR(transactions.transaction_date) DESC, MONTH(transactions.transaction_date) DESC";
$summaryResult = mysqli_query($conn, $summarySql);
while ($row = mysqli_fetch_assoc($summaryResult)) {
    $summaryRows[] = $row;
}

$monthlyBudgetRows = [];
$monthlyBudgetSql = "SELECT
        DATE_FORMAT(transactions.transaction_date, '%Y-%m') AS month_value,
        DATE_FORMAT(transactions.transaction_date, '%b %Y') AS month_label,
        budgets.title AS budget_name,
        budgets.monthly_limit AS budget_limit,
        SUM(CASE WHEN transactions.type = 'expense' THEN transactions.amount ELSE 0 END) AS used_amount,
        GROUP_CONCAT(DISTINCT categories.name ORDER BY categories.name SEPARATOR ', ') AS used_categories,
        GROUP_CONCAT(DISTINCT transactions.title ORDER BY transactions.transaction_date SEPARATOR ', ') AS used_titles
    FROM transactions
    INNER JOIN budgets ON budgets.id = transactions.budget_id
    LEFT JOIN categories ON categories.id = transactions.category_id
    WHERE $transactionFilterSql
    GROUP BY YEAR(transactions.transaction_date), MONTH(transactions.transaction_date), budgets.id, budgets.title, budgets.monthly_limit
    ORDER BY YEAR(transactions.transaction_date) DESC, MONTH(transactions.transaction_date) DESC, budgets.title ASC";
$monthlyBudgetResult = mysqli_query($conn, $monthlyBudgetSql);
while ($row = mysqli_fetch_assoc($monthlyBudgetResult)) {
    $monthlyBudgetRows[] = $row;
}

$weeklyBudgetRows = [];
$weeklyBudgetSql = "SELECT
        DATE_FORMAT(weekly_budgets.week_start, '%Y-%m') AS month_value,
        DATE_FORMAT(weekly_budgets.week_start, '%b %Y') AS month_label,
        weekly_budgets.title AS budget_name,
        weekly_budgets.week_start,
        weekly_budgets.week_end,
        weekly_budgets.weekly_limit AS budget_limit,
        SUM(CASE WHEN transactions.type = 'expense' THEN transactions.amount ELSE 0 END) AS used_amount,
        GROUP_CONCAT(DISTINCT categories.name ORDER BY categories.name SEPARATOR ', ') AS used_categories,
        GROUP_CONCAT(DISTINCT transactions.title ORDER BY transactions.transaction_date SEPARATOR ', ') AS used_titles
    FROM weekly_budgets
    LEFT JOIN transactions
        ON transactions.weekly_budget_id = weekly_budgets.id
        AND $transactionFilterSql
        AND transactions.transaction_date BETWEEN weekly_budgets.week_start AND weekly_budgets.week_end
    LEFT JOIN categories ON categories.id = transactions.category_id
    WHERE weekly_budgets.user_id = $userId";
if ($selectedWeeklyBudgetName !== '') {
    $weeklyBudgetSql .= " AND " . cleanWeeklyBudgetTitleSql("weekly_budgets.title") . " = '$safeSelectedWeeklyBudgetName'";
}
$weeklyBudgetSql .= "
    GROUP BY weekly_budgets.id, weekly_budgets.title, weekly_budgets.week_start, weekly_budgets.week_end, weekly_budgets.weekly_limit
    ORDER BY weekly_budgets.week_start DESC";
$weeklyBudgetResult = mysqli_query($conn, $weeklyBudgetSql);
while ($row = mysqli_fetch_assoc($weeklyBudgetResult)) {
    $weeklyBudgetRows[] = $row;
}

if ($hasSpecificFilter) {
    $monthlyBudgetRows = array_values(array_filter($monthlyBudgetRows, function ($row) {
        return (float) $row['used_amount'] > 0;
    }));

    $weeklyBudgetRows = array_values(array_filter($weeklyBudgetRows, function ($row) {
        return (float) $row['used_amount'] > 0;
    }));
}

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
    WHERE $transactionFilterSql
    ORDER BY transactions.transaction_date DESC, transactions.id DESC";
$transactionResult = mysqli_query($conn, $transactionSql);
while ($row = mysqli_fetch_assoc($transactionResult)) {
    $transactionRows[] = $row;
}

function formatRupees($amount)
{
    return 'Rs ' . number_format((float) $amount, 2);
}

function cleanWeeklyBudgetTitle($title)
{
    return preg_replace('/^(January|February|March|April|May|June|July|August|September|October|November|December)\s+/i', '', (string) $title);
}

function cleanWeeklyBudgetTitleSql($columnName)
{
    return "TRIM(
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
            $columnName,
            'January ', ''),
            'February ', ''),
            'March ', ''),
            'April ', ''),
            'May ', ''),
            'June ', ''),
            'July ', ''),
            'August ', ''),
            'September ', ''),
            'October ', ''),
            'November ', ''),
            'December ', '')
    )";
}

$showMonthlySection = $hasSpecificFilter && $selectedWeeklyBudgetName === '';
$showWeeklySection = $hasSpecificFilter && $selectedBudgetId === '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Report</title>
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
                    <h1>Budget Report</h1>
                </div>
            </div>

            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="add_transaction.php">Add Transaction</a>
                <a href="budgets.php">Monthly Budgets</a>
                <a href="weekly_budgets.php">Weekly Budgets</a>
                <a href="report.php">Monthly Report</a>
                <a class="active" href="history.php">Budget Report</a>
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
                        <p class="eyebrow">Budget Report</p>
                        <h3>Filter Budget Details Across All Months</h3>
                    </div>
                </div>

                <form method="get" class="report-filter-form">
                    <label>
                        <span>Select Monthly Budget</span>
                        <select name="budget_id">
                            <option value="">All Monthly Budgets</option>
                            <?php foreach ($budgetList as $budget): ?>
                                <option value="<?= (int) $budget['id'] ?>" <?= (string) $selectedBudgetId === (string) $budget['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($budget['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Select Weekly Budget</span>
                        <select name="weekly_budget_name">
                            <option value="">All Weekly Budgets</option>
                            <?php foreach ($weeklyBudgetList as $weeklyBudget): ?>
                                <option value="<?= htmlspecialchars($weeklyBudget) ?>" <?= $selectedWeeklyBudgetName === $weeklyBudget ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($weeklyBudget) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Select Category</span>
                        <select name="category_id">
                            <option value="">All Categories</option>
                            <?php foreach ($categoryList as $category): ?>
                                <option value="<?= (int) $category['id'] ?>" <?= (string) $selectedCategoryId === (string) $category['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Select Type</span>
                        <select name="type">
                            <option value="">All</option>
                            <option value="income" <?= $selectedType === 'income' ? 'selected' : '' ?>>Income</option>
                            <option value="expense" <?= $selectedType === 'expense' ? 'selected' : '' ?>>Expense</option>
                        </select>
                    </label>

                    <label>
                        <span>Search Title</span>
                        <input type="text" name="search_title" value="<?= htmlspecialchars($searchTitle) ?>" placeholder="Example: rent or printer">
                    </label>

                    <div class="report-filter-actions">
                        <button type="submit">Apply Filter</button>
                        <a class="secondary-link" href="history.php">Reset</a>
                    </div>
                </form>
            </section>

            <?php if (!$hasSpecificFilter || $showMonthlySection): ?>
                <section class="panel panel-wide">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow"><?= $hasSpecificFilter ? 'Monthly Budgets' : 'Budget Report' ?></p>
                            <h3><?= $hasSpecificFilter ? 'Month wise monthly budget usage' : 'Month wise summary' ?></h3>
                        </div>
                    </div>

                    <?php if (!$hasSpecificFilter): ?>
                        <?php if (count($summaryRows) === 0): ?>
                            <p class="simple-note">No transaction data found yet.</p>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Total Income</th>
                                            <th>Total Expense</th>
                                            <th>Entries</th>
                                            <th>Monthly Budgets Used</th>
                                            <th>Weekly Budgets Used</th>
                                            <th>View Monthly Report</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($summaryRows as $row): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['month_label']) ?></td>
                                                <td class="positive"><?= htmlspecialchars(formatRupees($row['total_income'])) ?></td>
                                                <td class="negative"><?= htmlspecialchars(formatRupees($row['total_expense'])) ?></td>
                                                <td><?= (int) $row['total_entries'] ?></td>
                                                <td><?= (int) $row['monthly_budget_count'] ?></td>
                                                <td><?= (int) $row['weekly_budget_count'] ?></td>
                                                <td><a class="table-link" href="report.php?report_month=<?= htmlspecialchars($row['month_value']) ?>">View Report</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if (count($monthlyBudgetRows) === 0): ?>
                            <p class="simple-note">No monthly budget usage found for the selected filters.</p>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Monthly Budget</th>
                                            <th>Limit</th>
                                            <th>Used</th>
                                            <th>Remaining</th>
                                            <th>Status</th>
                                            <th>Used In Categories</th>
                                            <th>Expense Titles</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($monthlyBudgetRows as $row): ?>
                                            <?php
                                            $limit = (float) $row['budget_limit'];
                                            $used = (float) $row['used_amount'];
                                            $remaining = $limit - $used;
                                            $status = $used > $limit ? 'Exceeded' : 'Within Limit';
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['month_label']) ?></td>
                                                <td><?= htmlspecialchars($row['budget_name']) ?></td>
                                                <td><?= htmlspecialchars(formatRupees($limit)) ?></td>
                                                <td><?= htmlspecialchars(formatRupees($used)) ?></td>
                                                <td><?= htmlspecialchars(formatRupees($remaining)) ?></td>
                                                <td class="<?= $used > $limit ? 'negative' : 'positive' ?>"><?= htmlspecialchars($status) ?></td>
                                                <td><?= htmlspecialchars($row['used_categories'] ?: 'No expense used') ?></td>
                                                <td><?= htmlspecialchars($row['used_titles'] ?: 'No expense used') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($showWeeklySection && count($weeklyBudgetRows) > 0): ?>
                <section class="panel panel-wide">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Weekly Budgets</p>
                            <h3>Month wise weekly budget usage</h3>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Month</th>
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
                                <?php foreach ($weeklyBudgetRows as $row): ?>
                                    <?php
                                    $limit = (float) $row['budget_limit'];
                                    $used = (float) $row['used_amount'];
                                    $remaining = $limit - $used;
                                    $status = $used > $limit ? 'Exceeded' : 'Within Limit';
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['month_label']) ?></td>
                                        <td><?= htmlspecialchars(cleanWeeklyBudgetTitle($row['budget_name'])) ?></td>
                                        <td><?= htmlspecialchars(date('d M Y', strtotime($row['week_start']))) ?> to <?= htmlspecialchars(date('d M Y', strtotime($row['week_end']))) ?></td>
                                        <td><?= htmlspecialchars(formatRupees($limit)) ?></td>
                                        <td><?= htmlspecialchars(formatRupees($used)) ?></td>
                                        <td><?= htmlspecialchars(formatRupees($remaining)) ?></td>
                                        <td class="<?= $used > $limit ? 'negative' : 'positive' ?>"><?= htmlspecialchars($status) ?></td>
                                        <td><?= htmlspecialchars($row['used_categories'] ?: 'No expense used') ?></td>
                                        <td><?= htmlspecialchars($row['used_titles'] ?: 'No expense used') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($hasSpecificFilter): ?>
                <section class="panel panel-wide">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Transaction Details</p>
                            <h3>Related entries</h3>
                        </div>
                    </div>

                    <?php if (count($transactionRows) === 0): ?>
                        <p class="simple-note">No related transaction found for the selected filters.</p>
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
                                            <td><?= htmlspecialchars($transaction['weekly_budget_name'] ? cleanWeeklyBudgetTitle($transaction['weekly_budget_name']) : '-') ?></td>
                                            <td class="<?= $transaction['type'] === 'income' ? 'positive' : 'negative' ?>"><?= htmlspecialchars(formatRupees($transaction['amount'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
