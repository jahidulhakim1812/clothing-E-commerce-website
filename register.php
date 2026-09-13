<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Register';

if (isCustomerLoggedIn()) redirect('index.php');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '') $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists. Please login instead.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO customers (name, email, phone, password) VALUES (?,?,?,?)");
            $stmt->execute([$name, $email, $phone, $hash]);
            createNotification('customer', 'New customer registered', "$name created an account", 'customers.php');
            flash('success', 'Account created successfully! Please login.');
            redirect('login.php');
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="form-wrapper">
        <h2>Create Account</h2>
        <p class="sub">Join us for faster checkout and order tracking</p>

        <?php if ($errors): ?>
            <div class="alert alert-error"><?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?></div>
        <?php endif; ?>

        <form method="post" action="register.php">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" required value="<?= sanitize($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required value="<?= sanitize($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" value="<?= sanitize($_POST['phone'] ?? '') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-block">Create Account</button>
        </form>
        <div class="form-footer-link">Already have an account? <a href="login.php">Login here</a></div>
        <div class="form-footer-link" style="margin-top:6px;">Just want to shop? <a href="checkout.php">Checkout as guest</a></div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
