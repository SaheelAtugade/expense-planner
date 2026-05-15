<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

$userId = (int) $_SESSION['user_id'];
$error = '';
$success = '';

$title = '';
$amount = '';
$type = 'expense';
$categoryId = '';
$newCategory = '';
$budgetId = '';
$weeklyBudgetId = '';
$transactionDate = date('Y-m-d');

$userSql = "SELECT full_name FROM users WHERE id = $userId";
$userResult = mysqli_query($conn, $userSql);
$user = mysqli_fetch_assoc($userResult);

$categoryList = [];
$categorySql = "SELECT id, name FROM categories WHERE user_id = $userId ORDER BY name ASC";
$categoryResult = mysqli_query($conn, $categorySql);
while ($row = mysqli_fetch_assoc($categoryResult)) {
    $categoryList[] = $row;
}

$budgetList = [];
$budgetSql = "SELECT id, title FROM budgets WHERE user_id = $userId ORDER BY title ASC";
$budgetResult = mysqli_query($conn, $budgetSql);
while ($row = mysqli_fetch_assoc($budgetResult)) {
    $budgetList[] = $row;
}

$weeklyBudgetList = [];
$weeklyBudgetSql = "SELECT id, title, week_start, week_end FROM weekly_budgets WHERE user_id = $userId ORDER BY week_start DESC";
$weeklyBudgetResult = mysqli_query($conn, $weeklyBudgetSql);
while ($row = mysqli_fetch_assoc($weeklyBudgetResult)) {
    $weeklyBudgetList[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $type = $_POST['type'] ?? 'expense';
    $categoryId = $_POST['category_id'] ?? '';
    $newCategory = trim($_POST['new_category'] ?? '');
    $budgetId = $_POST['budget_id'] ?? '';
    $weeklyBudgetId = $_POST['weekly_budget_id'] ?? '';
    $transactionDate = $_POST['transaction_date'] ?? date('Y-m-d');

    if ($title === '' || $amount === '' || $transactionDate === '') {
        $error = 'Please fill all required fields.';
    } elseif (!is_numeric($amount) || (float) $amount <= 0) {
        $error = 'Amount must be a valid number.';
    } elseif ($type !== 'income' && $type !== 'expense') {
        $error = 'Please select a valid type.';
    } elseif ($type === 'expense' && $budgetId === '' && $weeklyBudgetId === '') {
        $error = 'Please select a monthly budget or weekly budget for expense.';
    } else {
        $safeTitle = mysqli_real_escape_string($conn, $title);
        $safeType = mysqli_real_escape_string($conn, $type);
        $safeDate = mysqli_real_escape_string($conn, $transactionDate);
        $safeNewCategory = mysqli_real_escape_string($conn, $newCategory);

        // If user does not choose category or budget, NULL is saved in database.
        // For expense, at least one budget is already checked above.
        $categoryValue = "NULL";
        $budgetValue = $budgetId !== '' ? (int) $budgetId : "NULL";
        $weeklyBudgetValue = $weeklyBudgetId !== '' ? (int) $weeklyBudgetId : "NULL";

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

        $amountValue = (float) $amount;
        $insertSql = "INSERT INTO transactions (user_id, category_id, budget_id, weekly_budget_id, title, amount, type, transaction_date)
                      VALUES ($userId, $categoryValue, $budgetValue, $weeklyBudgetValue, '$safeTitle', $amountValue, '$safeType', '$safeDate')";
        mysqli_query($conn, $insertSql);

        header('Location: add_transaction.php?message=added');
        exit;
    }
}

if (isset($_GET['message']) && $_GET['message'] === 'added') {
    $success = 'Transaction added successfully.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Transaction</title>
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
                    <h1>Add Entry</h1>
                </div>
            </div>

            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a class="active" href="add_transaction.php">Add Transaction</a>
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
            <section class="panel add-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Income / Expense</p>
                        <h3>Add transaction</h3>
                    </div>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="alert error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success !== ''): ?>
                    <div class="alert success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <form method="post" class="add-form">
                    <label>
                        <span>Title</span>
                        <input type="text" name="title" value="<?= htmlspecialchars($title) ?>" placeholder="Example: Office supplies" required>
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
                            <?php foreach ($categoryList as $category): ?>
                                <option value="<?= (int) $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Or add new category</span>
                        <input type="text" name="new_category" value="<?= htmlspecialchars($newCategory) ?>" placeholder="Example: Utilities">
                    </label>

                    <label>
                        <span>Monthly Budget</span>
                        <select name="budget_id">
                            <option value="">Select monthly budget</option>
                            <?php foreach ($budgetList as $budget): ?>
                                <option value="<?= (int) $budget['id'] ?>"><?= htmlspecialchars($budget['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Weekly Budget</span>
                        <select name="weekly_budget_id">
                            <option value="">Select weekly budget</option>
                            <?php foreach ($weeklyBudgetList as $weeklyBudget): ?>
                                <option value="<?= (int) $weeklyBudget['id'] ?>">
                                    <?= htmlspecialchars($weeklyBudget['title']) ?> (<?= htmlspecialchars(date('d M', strtotime($weeklyBudget['week_start']))) ?> - <?= htmlspecialchars(date('d M', strtotime($weeklyBudget['week_end']))) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Date</span>
                        <input type="date" name="transaction_date" value="<?= htmlspecialchars($transactionDate) ?>" required>
                    </label>

                    <button type="submit">Save Transaction</button>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
