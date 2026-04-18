<?php
session_start();

require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Check login form fields.
    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        // Find user by email.
        $safeEmail = mysqli_real_escape_string($conn, $email);
        $sql = "SELECT id, full_name, email, password FROM users WHERE email = '$safeEmail' LIMIT 1";
        $result = mysqli_query($conn, $sql);
        $user = mysqli_fetch_assoc($result);

        // Verify password and create login session.
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];

            header('Location: dashboard.php');
            exit;
        }

        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Budget Planner Login</title>
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
                    <h1>Plan organization spending with clarity.</h1>
                </div>
            </div>

            <p class="auth-copy">
                A simple budget planning system for small organizations and firms to manage
                monthly budgets, weekly budgets, income, and expenses in one place.
            </p>

            <div class="auth-points">
                <div class="info-card">
                    <strong>Simple login flow</strong>
                    <span>Organization logs in first, then enters the planning dashboard.</span>
                </div>
                <div class="info-card">
                    <strong>Easy database</strong>
                    <span>Users, categories, budgets, and transactions only.</span>
                </div>
                <div class="info-card">
                    <strong>Ready for demo</strong>
                    <span>Import the SQL file into phpMyAdmin and use the sample accounts.</span>
                </div>
            </div>
        </section>

        <section class="auth-panel auth-form-panel">
            <div class="form-card">
                <p class="eyebrow">Login</p>
                <h2>Welcome back</h2>
                <p class="form-copy">Enter your email and password to open the expense planner dashboard.</p>

                <?php if ($error !== ''): ?>
                    <div class="alert error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" class="auth-form">
                    <label>
                        <span>Email</span>
                        <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="Enter your email" required>
                    </label>

                    <label>
                        <span>Password</span>
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </label>

                    <button type="submit">Login to App</button>
                </form>

                <p class="switch-text">
                    New user? <a href="register.php">Create account</a>
                </p>

                <div class="demo-box">
                    <strong>Sample login</strong>
                    <p>Email: <code>admin@budgetplanner.com</code></p>
                    <p>Password: <code>admin123</code></p>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
