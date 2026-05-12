<?php
session_start();

require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$fullName = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // In the database, full_name is used to store organization name.
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($fullName === '' || $email === '' || $password === '' || $confirmPassword === '') {
        $error = 'Please fill all fields.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Password and confirm password do not match.';
    } else {
        $safeName = mysqli_real_escape_string($conn, $fullName);
        $safeEmail = mysqli_real_escape_string($conn, $email);

        $checkSql = "SELECT id FROM users WHERE email = '$safeEmail'";
        $checkResult = mysqli_query($conn, $checkSql);
        $oldUser = mysqli_fetch_assoc($checkResult);

        if ($oldUser) {
            $error = 'This email is already registered.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $safePassword = mysqli_real_escape_string($conn, $hashedPassword);

            $insertSql = "INSERT INTO users (full_name, email, password) VALUES ('$safeName', '$safeEmail', '$safePassword')";
            mysqli_query($conn, $insertSql);

            $newUserId = mysqli_insert_id($conn);

            // Every new user gets one default budget.
            $defaultBudgetSql = "INSERT INTO budgets (user_id, title, monthly_limit) VALUES ($newUserId, 'No Budget', 999999.00)";
            mysqli_query($conn, $defaultBudgetSql);

            $_SESSION['user_id'] = $newUserId;
            $_SESSION['user_name'] = $fullName;
            $_SESSION['user_email'] = $email;

            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Organization</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <main class="auth-layout">
        <section class="auth-panel auth-intro">
            <div class="brand-block">
                <div class="brand-mark">MB</div>
                <div>
                    <p class="eyebrow">Monthly Budget Planner</p>
                    <h1>Create a new organization account.</h1>
                </div>
            </div>

            <p class="auth-copy">
                Register a small organization or firm, then start managing monthly budgets,
                weekly budgets, income, and expenses.
            </p>

            <div class="auth-points">
                <div class="info-card">
                    <strong>Easy registration</strong>
                    <span>Add organization name, email, and password to create a new account.</span>
                </div>
                <div class="info-card">
                    <strong>Login after register</strong>
                    <span>After registration, the you will goes directly to the dashboard.</span>
                </div>
            </div>
        </section>

        <section class="auth-panel auth-form-panel">
            <div class="form-card">
                <p class="eyebrow">Register</p>
                <h2>Create account</h2>
                <p class="form-copy">Fill the details below to create a new organization account.</p>

                <?php if ($error !== ''): ?>
                    <div class="alert error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" class="auth-form">
                    <label>
                        <span>Organization Name</span>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($fullName) ?>" placeholder="Enter organization name" required>
                    </label>

                    <label>
                        <span>Email</span>
                        <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="Enter email" required>
                    </label>

                    <label>
                        <span>Password</span>
                        <input type="password" name="password" placeholder="Enter password" required>
                    </label>

                    <label>
                        <span>Confirm Password</span>
                        <input type="password" name="confirm_password" placeholder="Confirm password" required>
                    </label>

                    <button type="submit">Register Organization</button>
                </form>

                <p class="switch-text">
                    Already have an account? <a href="index.php">Login here</a>
                </p>
            </div>
        </section>
    </main>
</body>
</html>
