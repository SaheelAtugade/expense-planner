<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

$userId = (int) $_SESSION['user_id'];
$error = '';
$success = '';
$title = '';
$weekStart = date('Y-m-d');
$weekEnd = date('Y-m-d', strtotime('+6 days'));
$weeklyLimit = '';

$userSql = "SELECT full_name FROM users WHERE id = $userId";
$userResult = mysqli_query($conn, $userSql);
$user = mysqli_fetch_assoc($userResult);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $weekStart = $_POST['week_start'] ?? '';
    $weekEnd = $_POST['week_end'] ?? '';
    $weeklyLimit = trim($_POST['weekly_limit'] ?? '');

    if ($title === '' || $weekStart === '' || $weekEnd === '' || $weeklyLimit === '') {
        $error = 'Please fill all fields.';
    } elseif (!is_numeric($weeklyLimit) || (float) $weeklyLimit < 0) {
        $error = 'Weekly limit must be a valid number.';
    } elseif ($weekEnd < $weekStart) {
        $error = 'Week end date cannot be before week start date.';
    } else {
        $safeTitle = mysqli_real_escape_string($conn, $title);
        $safeStart = mysqli_real_escape_string($conn, $weekStart);
        $safeEnd = mysqli_real_escape_string($conn, $weekEnd);
        $limitValue = (float) $weeklyLimit;

        $insertSql = "INSERT INTO weekly_budgets (user_id, title, week_start, week_end, weekly_limit)
                      VALUES ($userId, '$safeTitle', '$safeStart', '$safeEnd', $limitValue)";
        mysqli_query($conn, $insertSql);
        header('Location: weekly_budgets.php?message=added');
        exit;
    }
}

if (isset($_GET['message']) && $_GET['message'] === 'added') {
    $success = 'Weekly budget added successfully.';
}

$weeklyBudgets = [];
$weeklySql = "SELECT title, week_start, week_end, weekly_limit FROM weekly_budgets WHERE user_id = $userId ORDER BY week_start DESC";
$weeklyResult = mysqli_query($conn, $weeklySql);
while ($row = mysqli_fetch_assoc($weeklyResult)) {
    $weeklyBudgets[] = $row;
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
    <title>Weekly Budgets</title>
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
                    <h1>Weekly</h1>
                </div>
            </div>
            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="add_transaction.php">Add Transaction</a>
                <a href="budgets.php">Monthly Budgets</a>
                <a class="active" href="weekly_budgets.php">Weekly Budgets</a>
                <a href="report.php">Monthly Report</a>
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
                        <p class="eyebrow">Weekly Planning</p>
                        <h3>Add weekly budget</h3>
                    </div>
                </div>
                <?php if ($error !== ''): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($success !== ''): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
                <form method="post" class="add-form">
                    <label>
                        <span>Weekly Budget Name</span>
                        <input type="text" name="title" value="<?= htmlspecialchars($title) ?>" placeholder="Example: Week 1 Office Spending" required>
                    </label>
                    <label>
                        <span>Weekly Limit</span>
                        <input type="number" step="0.01" name="weekly_limit" value="<?= htmlspecialchars($weeklyLimit) ?>" placeholder="Example: 5000" required>
                    </label>
                    <label>
                        <span>Week Start Date</span>
                        <input type="date" name="week_start" value="<?= htmlspecialchars($weekStart) ?>" required>
                    </label>
                    <label>
                        <span>Week End Date</span>
                        <input type="date" name="week_end" value="<?= htmlspecialchars($weekEnd) ?>" required>
                    </label>
                    <button type="submit">Save Weekly Budget</button>
                </form>
            </section>

            <section class="panel panel-wide">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Saved Weekly Budgets</p>
                        <h3>Weekly budget list</h3>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Title</th><th>Start</th><th>End</th><th>Limit</th></tr></thead>
                        <tbody>
                            <?php foreach ($weeklyBudgets as $budget): ?>
                                <tr>
                                    <td><?= htmlspecialchars($budget['title']) ?></td>
                                    <td><?= htmlspecialchars(date('d M Y', strtotime($budget['week_start']))) ?></td>
                                    <td><?= htmlspecialchars(date('d M Y', strtotime($budget['week_end']))) ?></td>
                                    <td><?= htmlspecialchars(formatRupees($budget['weekly_limit'])) ?></td>
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
