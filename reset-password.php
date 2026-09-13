<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Reset Password';

if (isCustomerLoggedIn()) redirect('index.php');

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
        $validToken = true;
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
            $upd = $pdo->prepare("UPDATE customers SET password = ? WHERE email = ?");
            $upd->execute([$hash, $reset['email']]);

            $mark = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
            $mark->execute([$reset['id']]);

            $success = true;
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="form-wrapper">
        <h2>Reset Password</h2>
        <p class="sub">Choose a new password for your account</p>

        <?php if ($success): ?>
            <div class="alert alert-success">Your password has been reset successfully. You can now log in with your new password.</div>
            <div class="form-footer-link"><a href="login.php">Go to Login</a></div>
        <?php elseif (!$validToken): ?>
            <div class="alert alert-error">This password reset link is invalid or has expired.</div>
            <div class="form-footer-link"><a href="forgot-password.php">Request a new reset link</a></div>
        <?php else: ?>
            <?php if ($errors): ?>
                <div class="alert alert-error"><?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?></div>
            <?php endif; ?>
            <form method="post" action="reset-password.php">
                <input type="hidden" name="token" value="<?= sanitize($token) ?>">
                <input type="hidden" name="reset_password" value="1">
                <div class="form-row">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-block">Reset Password</button>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
