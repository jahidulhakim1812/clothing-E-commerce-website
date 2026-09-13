<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
$pageTitle = 'Profile Settings';

$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([(int)$_SESSION['employee_id']]);
$me = $stmt->fetch();

if (!$me) {
    redirect('logout.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'update_info') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');

    if ($name === '') $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';

    if (empty($errors)) {
        $checkStmt = $pdo->prepare("SELECT id FROM employees WHERE email = ? AND id != ?");
        $checkStmt->execute([$email, $me['id']]);
        if ($checkStmt->fetch()) {
            $errors[] = 'This email is already used by another account.';
        } else {
            $stmt = $pdo->prepare("UPDATE employees SET name = ?, email = ? WHERE id = ?");
            $stmt->execute([$name, $email, $me['id']]);
            $_SESSION['employee_name'] = $name;
            $_SESSION['employee_email'] = $email;
            flash('success', 'Your profile has been updated.');
            redirect('profile.php');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'update_password') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $me['password'])) {
        $errors[] = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $errors[] = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $errors[] = 'New password and confirmation do not match.';
    } else {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE employees SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $me['id']]);
        flash('success', 'Your password has been changed.');
        redirect('profile.php');
    }
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $e) echo sanitize($e) . '<br>'; ?></div><?php endif; ?>

<div class="grid-2">
    <div class="panel">
        <div class="panel-header"><h3>Account Information</h3></div>
        <form method="post" action="profile.php">
            <input type="hidden" name="form_action" value="update_info">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" required value="<?= sanitize($me['name']) ?>">
            </div>
            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="email" required value="<?= sanitize($me['email']) ?>">
            </div>
            <div class="form-group">
                <label>Role</label>
                <input type="text" value="<?= $me['role'] === 'super_admin' ? 'Super Admin' : 'Staff' ?>" disabled style="background:var(--a-bg);color:var(--a-text-light);">
            </div>
            <button type="submit" class="btn btn-block">Save Changes</button>
        </form>
    </div>

    <div class="panel">
        <div class="panel-header"><h3>Change Password</h3></div>
        <form method="post" action="profile.php">
            <input type="hidden" name="form_action" value="update_password">
            <div class="form-group">
                <label>Current Password *</label>
                <input type="password" name="current_password" required>
            </div>
            <div class="form-group">
                <label>New Password *</label>
                <input type="password" name="new_password" required minlength="6">
            </div>
            <div class="form-group">
                <label>Confirm New Password *</label>
                <input type="password" name="confirm_password" required minlength="6">
            </div>
            <button type="submit" class="btn btn-block">Update Password</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
