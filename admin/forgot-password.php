<?php
require_once __DIR__ . '/../config/config.php';

if (isEmployeeLoggedIn()) redirect('dashboard.php');

$error = '';
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ?");
        $stmt->execute([$email]);
        $employee = $stmt->fetch();

        // Same email either way is shown to avoid leaking which accounts exist.
        if ($employee) {
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $ins = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $ins->execute([$email, $token, $expiresAt]);

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $resetLink = $scheme . '://' . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/reset-password.php?token=' . $token;

            sendPasswordResetEmail($email, $employee['name'], $resetLink);
        }

        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password - <?= sanitize(setting('site_name', 'Stitch & Souls')) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="logo"><img src="../assets/img/logo.png" alt="Stitch & Souls" style="height:64px;margin:0 auto;"></div>
        <h2>Forgot Password?</h2>
        <p class="sub">Enter your email and we'll send you a reset link</p>

        <?php if ($sent): ?>
            <div class="alert alert-success">If an account exists for that email address, a password reset link has been sent.</div>
            <p style="text-align:center;margin-top:10px;"><a href="login.php" style="font-size:13px;color:var(--a-primary);">Back to Login</a></p>
        <?php else: ?>
            <?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>
            <form method="post" action="forgot-password.php">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required value="<?= sanitize($_POST['email'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-block" style="width:100%;justify-content:center;">Send Reset Link</button>
            </form>
            <p style="text-align:center;margin-top:20px;"><a href="login.php" style="font-size:13px;color:var(--a-primary);"><i class="fa-solid fa-arrow-left"></i> Back to Login</a></p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
