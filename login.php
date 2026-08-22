<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Login';

if (isCustomerLoggedIn()) redirect('index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM customers WHERE email = ?");
    $stmt->execute([$email]);
    $customer = $stmt->fetch();

    if ($customer && password_verify($password, $customer['password'])) {
        if ($customer['status'] === 'blocked') {
            $error = 'Your account has been blocked. Please contact support.';
        } else {
            $_SESSION['customer_id'] = $customer['id'];
            $_SESSION['customer_name'] = $customer['name'];
            redirect('index.php');
        }
    } else {
        $error = 'Invalid email or password.';
    }
}

include __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="form-wrapper">
        <h2>Welcome Back</h2>
        <p class="sub">Login to your account</p>

        <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>

        <form method="post" action="login.php">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required value="<?= sanitize($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label style="display:flex;justify-content:space-between;align-items:center;">
                    <span>Password</span>
                    <a href="forgot-password.php" style="font-weight:600;font-size:12.5px;color:var(--pink);">Forgot password?</a>
                </label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-block">Login</button>
        </form>
        <div class="form-footer-link">Don't have an account? <a href="register.php">Register here</a></div>
        <div class="form-footer-link" style="margin-top:6px;">Just want to shop? <a href="checkout.php">Checkout as guest</a></div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
