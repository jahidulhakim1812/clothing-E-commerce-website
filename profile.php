<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'My Profile';
requireCustomerLogin();

$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$_SESSION['customer_id']]);
$customer = $stmt->fetch();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';

    if ($name === '') $errors[] = 'Name is required.';

    if (empty($errors)) {
        if (!empty($newPassword)) {
            if (strlen($newPassword) < 6) {
                $errors[] = 'New password must be at least 6 characters.';
            } else {
                $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE customers SET name=?, phone=?, address=?, password=? WHERE id=?");
                $stmt->execute([$name, $phone, $address, $hash, $customer['id']]);
            }
        } else {
            $stmt = $pdo->prepare("UPDATE customers SET name=?, phone=?, address=? WHERE id=?");
            $stmt->execute([$name, $phone, $address, $customer['id']]);
        }

        if (empty($errors)) {
            $_SESSION['customer_name'] = $name;
            $success = true;
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->execute([$customer['id']]);
            $customer = $stmt->fetch();
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="form-wrapper">
        <h2>My Profile</h2>
        <p class="sub">Update your account information</p>

        <?php if ($success): ?><div class="alert alert-success">Profile updated successfully.</div><?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert alert-error"><?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?></div>
        <?php endif; ?>

        <form method="post" action="profile.php">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" required value="<?= sanitize($customer['name']) ?>">
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" value="<?= sanitize($customer['email']) ?>" disabled style="background:var(--cream);">
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" value="<?= sanitize($customer['phone']) ?>">
            </div>
            <div class="form-group">
                <label>Address</label>
                <textarea name="address" rows="3"><?= sanitize($customer['address']) ?></textarea>
            </div>
            <div class="form-group">
                <label>New Password <small style="color:var(--text-light);">(leave blank to keep current)</small></label>
                <input type="password" name="new_password">
            </div>
            <button type="submit" class="btn btn-block">Update Profile</button>
        </form>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
