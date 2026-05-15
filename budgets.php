<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

$userId = (int) $_SESSION['user_id'];
$error = '';
$success = '';
$title = '';
$monthlyLimit = '';

$userSql = "SELECT full_name FROM users WHERE id = $userId";
$userResult = mysqli_query($conn, $userSql);
$user = mysqli_fetch_assoc($userResult);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $monthlyLimit = trim($_POST['monthly_limit'] ?? '');

    if ($title === '' || $monthlyLimit === '') {
        $error = 'Please fill all fields.';
    } elseif (!is_numeric($monthlyLimit) || (float) $monthlyLimit < 0) {
        $error = 'Monthly limit must be a valid number.';
    } else {
        $safeTitle = mysqli_real_escape_string($conn, $title);
        $limitValue = (float) $monthlyLimit;

        $checkSql = "SELECT id FROM budgets WHERE user_id = $userId AND title = '$safeTitle'";
        $checkResult = mysqli_query($conn, $checkSql);
        $oldBudget = mysqli_fetch_assoc($checkResult);

        if ($oldBudget) {
            $error = 'This budget already exists.';
        } else {
            $insertSql = "INSERT INTO budgets (user_id, title, monthly_limit) VALUES ($userId, '$safeTitle', $limitValue)";
            mysqli_query($conn, $insertSql);
            header('Location: budgets.php?message=added');
            exit;
        }
    }
}

if (isset($_GET['message']) && $_GET['message'] === 'added') {
    $success = 'Monthly budget added successfully.';
}

$budgets = [];
$budgetSql = "SELECT title, monthly_limit FROM budgets WHERE user_id = $userId ORDER BY title ASC";
$budgetResult = mysqli_query($conn, $budgetSql);
while ($row = mysqli_fetch_assoc($budgetResult)) {
    $budgets[] = $row;
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
    <title>Monthly Budgets</title>
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
                    <h1>Budgets</h1>
                </div>
            </div>
            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="add_transaction.php">Add Transaction</a>
                <a class="active" href="budgets.php">Monthly Budgets</a>
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
                        <p class="eyebrow">Monthly Planning</p>
                        <h3>Add monthly budget</h3>
                    </div>
                </div>

                <?php if ($error !== ''): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($success !== ''): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

                <form method="post" class="add-form">
                    <label>
                        <span>Budget Name</span>
                        <input type="text" name="title" value="<?= htmlspecialchars($title) ?>" placeholder="Example: Office Supplies" required>
                    </label>
                    <label>
                        <span>Monthly Limit</span>
                        <input type="number" step="0.01" name="monthly_limit" value="<?= htmlspecialchars($monthlyLimit) ?>" placeholder="Example: 10000" required>
                    </label>
                    <button type="submit">Save Budget</button>
                </form>
            </section>

            <section class="panel panel-wide">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Saved Budgets</p>
                        <h3>Monthly budget list</h3>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Budget</th><th>Monthly Limit</th></tr></thead>
                        <tbody>
                            <?php foreach ($budgets as $budget): ?>
                                <tr>
                                    <td><?= htmlspecialchars($budget['title']) ?></td>
                                    <td><?= htmlspecialchars(formatRupees($budget['monthly_limit'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
