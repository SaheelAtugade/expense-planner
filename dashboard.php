<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

// Logged in user id comes from session after login.
$userId = (int) $_SESSION['user_id'];
$currentMonth = date('m');
$currentYear = date('Y');
$currentMonthName = date('F');

// These variables are used for messages on the page.
$error = '';
$success = '';

// These variables keep form values after submit.
$title = '';
$amount = '';
$type = 'expense';
$categoryId = '';
$newCategory = '';
$budgetId = '';
$newBudget = '';
$newBudgetLimit = '';
$transactionDate = date('Y-m-d');

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

// Get categories for the dropdown.
$categoryList = [];
$categoryListSql = "SELECT id, name FROM categories WHERE user_id = $userId ORDER BY name ASC";
$categoryListResult = mysqli_query($conn, $categoryListSql);
while ($row = mysqli_fetch_assoc($categoryListResult)) {
    $categoryList[] = $row;
}

// Get budgets for the dropdown.
$budgetList = [];
$budgetListSql = "SELECT id, title FROM budgets WHERE user_id = $userId ORDER BY title ASC";
$budgetListResult = mysqli_query($conn, $budgetListSql);
while ($row = mysqli_fetch_assoc($budgetListResult)) {
    $budgetList[] = $row;
}

// If user submits the form, add new transaction.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $type = $_POST['type'] ?? 'expense';
    $categoryId = $_POST['category_id'] ?? '';
    $newCategory = trim($_POST['new_category'] ?? '');
    $budgetId = $_POST['budget_id'] ?? '';
    $newBudget = trim($_POST['new_budget'] ?? '');
    $newBudgetLimit = trim($_POST['new_budget_limit'] ?? '');
    $transactionDate = $_POST['transaction_date'] ?? date('Y-m-d');

    if ($title === '' || $amount === '' || $transactionDate === '') {
        $error = 'Please fill all required fields.';
    } elseif (!is_numeric($amount) || (float) $amount <= 0) {
        $error = 'Amount must be a valid number.';
    } elseif ($type !== 'income' && $type !== 'expense') {
        $error = 'Please select a valid transaction type.';
    } elseif ($type === 'expense' && $budgetId === '' && $newBudget === '') {
        $error = 'Please select a budget or add a new budget for expense.';
    } elseif ($newBudget !== '' && ($newBudgetLimit === '' || !is_numeric($newBudgetLimit) || (float) $newBudgetLimit < 0)) {
        $error = 'Please enter a valid monthly limit for the new budget.';
    } else {
        $safeTitle = mysqli_real_escape_string($conn, $title);
        $safeType = mysqli_real_escape_string($conn, $type);
        $safeDate = mysqli_real_escape_string($conn, $transactionDate);
        $safeNewCategory = mysqli_real_escape_string($conn, $newCategory);
        $safeNewBudget = mysqli_real_escape_string($conn, $newBudget);
        $budgetLimitValue = $newBudgetLimit !== '' ? (float) $newBudgetLimit : 0;
        $categoryValue = null;
        $budgetValue = null;

        // If user writes a new category, save it first.
        if ($newCategory !== '') {
            $checkCategorySql = "SELECT id FROM categories WHERE user_id = $userId AND name = '$safeNewCategory'";
            $checkCategoryResult = mysqli_query($conn, $checkCategorySql);
            $oldCategory = mysqli_fetch_assoc($checkCategoryResult);

            if ($oldCategory) {
                $categoryValue = (int) $oldCategory['id'];
            } else {
                $addCategorySql = "INSERT INTO categories (user_id, name) VALUES ($userId, '$safeNewCategory')";
                mysqli_query($conn, $addCategorySql);
                $categoryValue = (int) mysqli_insert_id($conn);
            }
        } elseif ($categoryId !== '') {
            $categoryValue = (int) $categoryId;
        }

        // If user writes a new budget, save it first.
        if ($newBudget !== '') {
            $checkBudgetSql = "SELECT id FROM budgets WHERE user_id = $userId AND title = '$safeNewBudget'";
            $checkBudgetResult = mysqli_query($conn, $checkBudgetSql);
            $oldBudget = mysqli_fetch_assoc($checkBudgetResult);

            if ($oldBudget) {
                $budgetValue = (int) $oldBudget['id'];
            } else {
                $addBudgetSql = "INSERT INTO budgets (user_id, title, monthly_limit) VALUES ($userId, '$safeNewBudget', $budgetLimitValue)";
                mysqli_query($conn, $addBudgetSql);
                $budgetValue = (int) mysqli_insert_id($conn);
            }
        } elseif ($budgetId !== '') {
            $budgetValue = (int) $budgetId;
        }

        $categorySqlValue = $categoryValue === null ? "NULL" : $categoryValue;
        $budgetSqlValue = $budgetValue === null ? "NULL" : $budgetValue;
        $amountValue = (float) $amount;

        $insertSql = "INSERT INTO transactions (user_id, category_id, budget_id, title, amount, type, transaction_date)
                      VALUES ($userId, $categorySqlValue, $budgetSqlValue, '$safeTitle', $amountValue, '$safeType', '$safeDate')";
        mysqli_query($conn, $insertSql);

        header("Location: dashboard.php?message=added");
        exit;
    }
}

if (isset($_GET['message']) && $_GET['message'] === 'added') {
    $success = 'Transaction added successfully.';
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

$totalIncome = (float) ($summary['total_income'] ?? 0);
$totalExpense = (float) ($summary['total_expense'] ?? 0);
$moneyLeft = $totalIncome - $totalExpense;

// Count how many budgets the user has.
$countBudgetSql = "SELECT COUNT(*) AS total_budget FROM budgets WHERE user_id = $userId";
$countBudgetResult = mysqli_query($conn, $countBudgetSql);
$countBudgetRow = mysqli_fetch_assoc($countBudgetResult);
$budgetCount = (int) $countBudgetRow['total_budget'];

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
    <title>Expense Planner Dashboard</title>
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
                    <h1>Dashboard</h1>
                </div>
            </div>

            <nav class="nav-links">
                <a class="active" href="dashboard.php">Dashboard</a>
                <a href="report.php">Monthly Report</a>
                <a href="#add-transaction">Add Entry</a>
                <a href="#transactions">Transactions</a>
                <a href="#budgets">Budgets</a>
                <a href="#categories">Categories</a>
            </nav>

            <section class="premium-card">
                <p class="eyebrow">Logged In User</p>
                <h2><?= htmlspecialchars($user['full_name'] ?? $_SESSION['user_name']) ?></h2>
                <p><?= htmlspecialchars($_SESSION['user_email']) ?></p>
                <a class="button-link" href="logout.php">Logout</a>
            </section>
        </aside>

        <main class="dashboard">
            <header class="hero-card">
                <div>
                    <p class="eyebrow"><?= htmlspecialchars($currentMonthName . ' ' . $currentYear) ?> Overview</p>
                    <h2>See your budget, spending, and recent activity for <?= htmlspecialchars($currentMonthName . ' ' . $currentYear) ?>.</h2>
                    <p class="hero-copy">This dashboard is showing only the current month data. Old month records are still saved in the database for history.</p>
                </div>
                <div class="hero-metrics">
                    <div>
                        <span>Money Left</span>
                        <strong><?= htmlspecialchars(formatRupees($moneyLeft)) ?></strong>
                    </div>
                    <div>
                        <span>Budgets in Use</span>
                        <strong><?= $budgetCount ?></strong>
                    </div>
                </div>
            </header>

            <section class="summary-grid">
                <article class="summary-card blue">
                    <p>Total Income</p>
                    <h3><?= htmlspecialchars(formatRupees($totalIncome)) ?></h3>
                    <span>Money added to your account</span>
                </article>

                <article class="summary-card sky">
                    <p>Total Expense</p>
                    <h3><?= htmlspecialchars(formatRupees($totalExpense)) ?></h3>
                    <span>Money spent so far</span>
                </article>

                <article class="summary-card navy">
                    <p>Current Balance</p>
                    <h3><?= htmlspecialchars(formatRupees($moneyLeft)) ?></h3>
                    <span>Income minus expenses</span>
                </article>
            </section>

            <section class="panel add-panel" id="add-transaction">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Add New Entry</p>
                        <h3>Add income or expense</h3>
                    </div>
                </div>

                <p class="simple-note">Fill this form to save a new transaction in the database.</p>

                <?php if ($error !== ''): ?>
                    <div class="alert error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success !== ''): ?>
                    <div class="alert success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <form method="post" class="add-form">
                    <label>
                        <span>Title</span>
                        <input type="text" name="title" value="<?= htmlspecialchars($title) ?>" placeholder="Example: Salary or Grocery" required>
                    </label>

                    <label>
                        <span>Amount</span>
                        <input type="number" step="0.01" name="amount" value="<?= htmlspecialchars($amount) ?>" placeholder="Enter amount" required>
                    </label>

                    <label>
                        <span>Type</span>
                        <select name="type" required>
                            <option value="expense" <?= $type === 'expense' ? 'selected' : '' ?>>Expense</option>
                            <option value="income" <?= $type === 'income' ? 'selected' : '' ?>>Income</option>
                        </select>
                    </label>

                    <label>
                        <span>Category</span>
                        <select name="category_id">
                            <option value="">Select category</option>
                            <?php foreach ($categoryList as $categoryOption): ?>
                                <option value="<?= (int) $categoryOption['id'] ?>" <?= (string) $categoryId === (string) $categoryOption['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($categoryOption['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Or add new category</span>
                        <input type="text" name="new_category" value="<?= htmlspecialchars($newCategory) ?>" placeholder="Example: Shopping">
                    </label>

                    <label>
                        <span>Budget</span>
                        <select name="budget_id">
                            <option value="">Select budget</option>
                            <?php foreach ($budgetList as $budgetOption): ?>
                                <option value="<?= (int) $budgetOption['id'] ?>" <?= (string) $budgetId === (string) $budgetOption['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($budgetOption['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Or add new budget</span>
                        <input type="text" name="new_budget" value="<?= htmlspecialchars($newBudget) ?>" placeholder="Example: Food Budget">
                    </label>

                    <label>
                        <span>New Budget Monthly Limit</span>
                        <input type="number" step="0.01" name="new_budget_limit" value="<?= htmlspecialchars($newBudgetLimit) ?>" placeholder="Example: 5000">
                    </label>

                    <label>
                        <span>Date</span>
                        <input type="date" name="transaction_date" value="<?= htmlspecialchars($transactionDate) ?>" required>
                    </label>

                    <button type="submit">Save Transaction</button>
                </form>
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

                    <div class="mini-stat">
                        <p>Connected to database</p>
                        <strong>Login and dashboard are working together</strong>
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
