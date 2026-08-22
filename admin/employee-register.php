<?php
require_once __DIR__ . '/../config/config.php';

if (isEmployeeLoggedIn()) redirect('dashboard.php');

// A logged-in Super Admin who happens to open this page (e.g. from the
// employees panel) is allowed to grant the Super Admin role to the new
// account. Anyone arriving from the public login page — i.e. not already
// authenticated — can only self-register as an Employee; upgrading to
// Super Admin afterwards is done from Employee Management by an existing
// Super Admin. This keeps the public-facing wizard from letting a random
// visitor grant themselves full admin access.
$canGrantSuperAdmin = isSuperAdmin();

if (isset($_GET['restart'])) {
    unset($_SESSION['emp_reg']);
    redirect('employee-register.php');
}

if (!isset($_SESSION['emp_reg'])) {
    $_SESSION['emp_reg'] = ['step' => 'email'];
}
$reg = &$_SESSION['emp_reg'];
$errors = [];
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- Step 1: submit email, send first verification code ----
    if ($action === 'submit_email') {
        $email = sanitize($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $chk = $pdo->prepare("SELECT id FROM employees WHERE email = ?");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $errors[] = 'An account with this email already exists. Please log in instead.';
            } else {
                $code = generateOtpCode();
                $reg['email'] = $email;
                $reg['otp1'] = $code;
                $reg['otp1_expires'] = time() + 600; // 10 minutes
                sendOtpEmail($email, $code, 'Verify Your Email');
                $reg['step'] = 'verify_email';
            }
        }
    }

    // ---- Step 2: verify the first code ----
    elseif ($action === 'verify_email') {
        $entered = trim($_POST['code'] ?? '');
        if (empty($reg['otp1']) || time() > ($reg['otp1_expires'] ?? 0)) {
            $errors[] = 'This code has expired. Please request a new one.';
        } elseif (!hash_equals($reg['otp1'], $entered)) {
            $errors[] = 'Incorrect verification code. Please try again.';
        } else {
            $reg['step'] = 'details';
        }
    }

    elseif ($action === 'resend_email_code') {
        if (!empty($reg['email'])) {
            $code = generateOtpCode();
            $reg['otp1'] = $code;
            $reg['otp1_expires'] = time() + 600;
            sendOtpEmail($reg['email'], $code, 'Verify Your Email');
            $notice = 'A new verification code has been sent to your email.';
        }
    }

    // ---- Step 3: name + role, send second verification code ----
    elseif ($action === 'submit_details') {
        $name = sanitize($_POST['name'] ?? '');
        $role = $_POST['role'] ?? 'employee';
        if (!$canGrantSuperAdmin) $role = 'employee'; // enforced regardless of what was posted
        if (!in_array($role, ['super_admin', 'employee'], true)) $role = 'employee';

        if ($name === '') {
            $errors[] = 'Please enter your full name.';
        } else {
            $reg['name'] = $name;
            $reg['role'] = $role;
            $code = generateOtpCode();
            $reg['otp2'] = $code;
            $reg['otp2_expires'] = time() + 600;
            sendOtpEmail($reg['email'], $code, 'Confirm Your Registration');
            $reg['step'] = 'verify_final';
        }
    }

    // ---- Step 4: verify the second code ----
    elseif ($action === 'verify_final') {
        $entered = trim($_POST['code'] ?? '');
        if (empty($reg['otp2']) || time() > ($reg['otp2_expires'] ?? 0)) {
            $errors[] = 'This code has expired. Please request a new one.';
        } elseif (!hash_equals($reg['otp2'], $entered)) {
            $errors[] = 'Incorrect verification code. Please try again.';
        } else {
            $reg['step'] = 'set_password';
        }
    }

    elseif ($action === 'resend_final_code') {
        if (!empty($reg['email'])) {
            $code = generateOtpCode();
            $reg['otp2'] = $code;
            $reg['otp2_expires'] = time() + 600;
            sendOtpEmail($reg['email'], $code, 'Confirm Your Registration');
            $notice = 'A new verification code has been sent to your email.';
        }
    }

    // ---- Step 5: set password, create the account ----
    elseif ($action === 'set_password') {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($password !== $confirmPassword) $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            // Re-check the email hasn't been taken by someone else mid-flow.
            $chk = $pdo->prepare("SELECT id FROM employees WHERE email = ?");
            $chk->execute([$reg['email']]);
            if ($chk->fetch()) {
                $errors[] = 'An account with this email was just created. Please log in instead.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO employees (name, email, password, role, status) VALUES (?,?,?,?, 'active')");
                $stmt->execute([$reg['name'], $reg['email'], $hash, $reg['role']]);

                sendEmployeeRegistrationCompleteEmail($reg['email'], $reg['name'], $reg['role']);

                $roleLabel = $reg['role'] === 'super_admin' ? 'Super Admin' : 'Employee';
                createNotification('system', 'New ' . $roleLabel . ' account created', $reg['name'] . ' (' . $reg['email'] . ') registered as ' . $roleLabel, 'employees.php');

                unset($_SESSION['emp_reg']);
                flash('success', 'Your account has been created successfully! Please log in.');
                redirect('login.php');
            }
        }
    }
}

$step = $reg['step'] ?? 'email';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account - <?= sanitize(setting('site_name', 'Stitch & Souls')) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="login-page">
    <div class="login-box" style="max-width:420px;">
        <div class="logo"><img src="../assets/img/logo.png" alt="Stitch & Souls" style="height:64px;margin:0 auto;"></div>
        <h2>Create Admin / Staff Account</h2>
        <p class="sub">
            <?php if ($step === 'email'): ?>Enter your email to get started
            <?php elseif ($step === 'verify_email'): ?>Verify your email address
            <?php elseif ($step === 'details'): ?>Tell us who you are
            <?php elseif ($step === 'verify_final'): ?>Confirm your registration
            <?php elseif ($step === 'set_password'): ?>Choose a password
            <?php endif; ?>
        </p>

        <!-- Step progress -->
        <div style="display:flex;gap:5px;margin-bottom:24px;">
            <?php $stepsOrder = ['email', 'verify_email', 'details', 'verify_final', 'set_password'];
            $currentIdx = array_search($step, $stepsOrder); ?>
            <?php foreach ($stepsOrder as $i => $s): ?>
                <div style="flex:1;height:4px;border-radius:3px;background:<?= $i <= $currentIdx ? 'var(--a-primary)' : 'var(--a-border)' ?>;"></div>
            <?php endforeach; ?>
        </div>

        <?php if ($notice): ?><div class="alert alert-success"><?= sanitize($notice) ?></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?></div><?php endif; ?>

        <?php if ($step === 'email'): ?>
            <form method="post" action="employee-register.php">
                <input type="hidden" name="action" value="submit_email">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required autofocus value="<?= sanitize($reg['email'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-block" style="width:100%;justify-content:center;">Send Verification Code</button>
            </form>

        <?php elseif ($step === 'verify_email'): ?>
            <p style="font-size:13px;color:var(--a-text-light);margin-bottom:16px;">We sent a 6-digit code to <strong><?= sanitize($reg['email']) ?></strong>.</p>
            <form method="post" action="employee-register.php">
                <input type="hidden" name="action" value="verify_email">
                <div class="form-group">
                    <label>Verification Code</label>
                    <input type="text" name="code" required autofocus maxlength="6" inputmode="numeric" style="letter-spacing:4px;font-size:18px;text-align:center;">
                </div>
                <button type="submit" class="btn btn-block" style="width:100%;justify-content:center;">Verify Code</button>
            </form>
            <form method="post" action="employee-register.php" style="margin-top:10px;">
                <input type="hidden" name="action" value="resend_email_code">
                <button type="submit" class="btn btn-outline btn-block" style="width:100%;justify-content:center;">Resend Code</button>
            </form>

        <?php elseif ($step === 'details'): ?>
            <form method="post" action="employee-register.php">
                <input type="hidden" name="action" value="submit_details">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" required autofocus value="<?= sanitize($reg['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <?php if ($canGrantSuperAdmin): ?>
                        <select name="role">
                            <option value="employee">Employee</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    <?php else: ?>
                        <input type="text" value="Employee" disabled style="background:var(--a-bg);">
                        <input type="hidden" name="role" value="employee">
                        <small style="color:var(--a-text-light);">Super Admin accounts can only be granted by an existing Super Admin from Employee Management, after this account is created.</small>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-block" style="width:100%;justify-content:center;">Continue</button>
            </form>

        <?php elseif ($step === 'verify_final'): ?>
            <p style="font-size:13px;color:var(--a-text-light);margin-bottom:16px;">One more code was sent to <strong><?= sanitize($reg['email']) ?></strong> to confirm this registration.</p>
            <form method="post" action="employee-register.php">
                <input type="hidden" name="action" value="verify_final">
                <div class="form-group">
                    <label>Verification Code</label>
                    <input type="text" name="code" required autofocus maxlength="6" inputmode="numeric" style="letter-spacing:4px;font-size:18px;text-align:center;">
                </div>
                <button type="submit" class="btn btn-block" style="width:100%;justify-content:center;">Verify Code</button>
            </form>
            <form method="post" action="employee-register.php" style="margin-top:10px;">
                <input type="hidden" name="action" value="resend_final_code">
                <button type="submit" class="btn btn-outline btn-block" style="width:100%;justify-content:center;">Resend Code</button>
            </form>

        <?php elseif ($step === 'set_password'): ?>
            <form method="post" action="employee-register.php">
                <input type="hidden" name="action" value="set_password">
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required autofocus>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-block" style="width:100%;justify-content:center;">Complete Registration</button>
            </form>
        <?php endif; ?>

        <p style="text-align:center;margin-top:20px;">
            <a href="employee-register.php?restart=1" style="font-size:12.5px;color:var(--a-text-light);margin-right:14px;">Start Over</a>
            <a href="login.php" style="font-size:12.5px;color:var(--a-primary);"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
        </p>
    </div>
</div>
</body>
</html>
