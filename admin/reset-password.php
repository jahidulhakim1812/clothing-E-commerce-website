<?php
require_once __DIR__ . '/../config/config.php';

if (isEmployeeLoggedIn()) redirect('dashboard.php');

$token = $_POST['token'] ?? ($_GET['token'] ?? '');
$errors = [];
$success = false;
$validToken = false;
$reset = null;

if ($token !== '') {
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? LIMIT 1");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if ($reset && (int)$reset['used'] === 0 && strtotime($reset['expires_at']) > time()) {
        // Make sure this token actually belongs to an employee account.
        $chk = $pdo->prepare("SELECT id FROM employees WHERE email = ?");
        $chk->execute([$reset['email']]);
        if ($chk->fetch()) $validToken = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    if (!$validToken) {
        $errors[] = 'This password reset link is invalid or has expired. Please request a new one.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($password !== $confirmPassword) $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $upd = $pdo->prepare("UPDATE employees SET password = ? WHERE email = ?");
            $upd->execute([$hash, $reset['email']]);

            $mark = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
            $mark->execute([$reset['id']]);

            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password - <?= sanitize(setting('site_name', 'Stitch & Souls')) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="logo"><img src="../assets/img/logo.png" alt="Stitch & Souls" style="height:64px;margin:0 auto;"></div>
        <h2>Reset Password</h2>
        <p class="sub">Choose a new password for your account</p>

        <?php if ($success): ?>
            <div class="alert alert-success">Your password has been reset successfully.</div>
            <p style="text-align:center;margin-top:10px;"><a href="login.php" style="font-size:13px;color:var(--a-primary);">Go to Login</a></p>
        <?php elseif (!$validToken): ?>
            <div class="alert alert-error">This password reset link is invalid or has expired.</div>
            <p style="text-align:center;margin-top:10px;"><a href="forgot-password.php" style="font-size:13px;color:var(--a-primary);">Request a new reset link</a></p>
        <?php else: ?>
            <?php if ($errors): ?>
                <div class="alert alert-error"><?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?></div>
            <?php endif; ?>
            <form method="post" action="reset-password.php">
                <input type="hidden" name="token" value="<?= sanitize($token) ?>">
                <input type="hidden" name="reset_password" value="1">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-block" style="width:100%;justify-content:center;">Reset Password</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
