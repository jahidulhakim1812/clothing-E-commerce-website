<?php
require_once __DIR__ . '/../config/config.php';

if (isEmployeeLoggedIn()) redirect('dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ?");
    $stmt->execute([$email]);
    $employee = $stmt->fetch();

    if ($employee && password_verify($password, $employee['password'])) {
        if ($employee['status'] === 'inactive') {
            $error = 'Your account has been deactivated. Please contact the super admin.';
        } else {
            $_SESSION['employee_id'] = $employee['id'];
            $_SESSION['employee_name'] = $employee['name'];
            $_SESSION['employee_email'] = $employee['email'];
            $_SESSION['employee_role'] = $employee['role'];
            redirect('dashboard.php');
        }
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login - <?= sanitize(setting('site_name', 'Stitch & Souls')) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="login-page">
    <div class="login-box" style="position:relative;">
        <a href="employee-register.php" title="Create Admin / Staff Account" style="position:absolute;top:16px;right:16px;width:32px;height:32px;border-radius:50%;background:var(--a-bg);border:1px solid var(--a-border);display:flex;align-items:center;justify-content:center;color:var(--a-primary);text-decoration:none;font-size:13px;">
            <i class="fa-solid fa-user-plus"></i>
        </a>
        <div class="logo"><img src="../assets/img/logo.png" alt="Stitch & Souls" style="height:64px;margin:0 auto;"></div>
        <h2>Employee Login</h2>
        <p class="sub">Sign in to manage your handmade store</p>

        <?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>

        <form method="post" action="login.php">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required value="<?= sanitize($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label style="display:flex;justify-content:space-between;align-items:center;">
                    <span>Password</span>
                    <a href="forgot-password.php" style="font-weight:600;font-size:12px;color:var(--a-primary);">Forgot password?</a>
                </label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-block" style="width:100%;justify-content:center;">Login</button>
        </form>
      
        <p style="text-align:center;margin-top:10px;"><a href="../index.php" style="font-size:13px;color:var(--a-primary);"><i class="fa-solid fa-arrow-left"></i> Back to Store</a></p>
    </div>
</div>
</body>
</html>
