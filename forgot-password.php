<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Forgot Password';

if (isCustomerLoggedIn()) redirect('index.php');

$error = '';
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        $customer = $stmt->fetch();

        // Only actually create a token + send an email if the account
        // exists, but always show the same success message either way —
        // this avoids leaking which emails are registered.
        if ($customer) {
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $ins = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $ins->execute([$email, $token, $expiresAt]);

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $resetLink = $scheme . '://' . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/reset-password.php?token=' . $token;

            sendPasswordResetEmail($email, $customer['name'], $resetLink);
        }

        $sent = true;
    }
}

include __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="form-wrapper">
        <h2>Forgot Password?</h2>
        <p class="sub">Enter your email and we'll send you a link to reset your password</p>

        <?php if ($sent): ?>
            <div class="alert alert-success">If an account exists for that email address, a password reset link has been sent. Please check your inbox (and spam folder).</div>
            <div class="form-footer-link"><a href="login.php">Back to Login</a></div>
        <?php else: ?>
            <?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>
            <form method="post" action="forgot-password.php">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required value="<?= sanitize($_POST['email'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-block">Send Reset Link</button>
            </form>
            <div class="form-footer-link">Remembered your password? <a href="login.php">Login here</a></div>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
