<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
requireSuperAdmin();
$pageTitle = 'Employee Management';

$errors = [];
$editEmployee = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['super_admin', 'employee']) ? $_POST['role'] : 'employee';
    $status = $_POST['status'] ?? 'active';
    $password = $_POST['password'] ?? '';

    if ($name === '') $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';

    if (empty($errors)) {
        $checkStmt = $pdo->prepare("SELECT id FROM employees WHERE email = ? AND id != ?");
        $checkStmt->execute([$email, $id]);
        if ($checkStmt->fetch()) {
            $errors[] = 'This email is already used by another employee.';
        } else {
            if ($id > 0) {
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE employees SET name=?, email=?, role=?, status=?, password=? WHERE id=?");
                    $stmt->execute([$name, $email, $role, $status, $hash, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE employees SET name=?, email=?, role=?, status=? WHERE id=?");
                    $stmt->execute([$name, $email, $role, $status, $id]);
                }
                flash('success', 'Employee updated successfully.');
            } else {
                if (strlen($password) < 6) {
                    $errors[] = 'Password must be at least 6 characters.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO employees (name, email, password, role, status) VALUES (?,?,?,?,?)");
                    $stmt->execute([$name, $email, $hash, $role, $status]);
                    flash('success', 'Employee added successfully.');
                }
            }
            if (empty($errors)) redirect('employees.php');
        }
    }
}

if (isset($_GET['delete'])) {
    if ((int)$_GET['delete'] === (int)$_SESSION['employee_id']) {
        flash('error', 'You cannot delete your own account while logged in.');
    } else {
        $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->execute([(int)$_GET['delete']]);
        flash('success', 'Employee removed.');
    }
    redirect('employees.php');
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editEmployee = $stmt->fetch();
}

$employees = $pdo->query("SELECT * FROM employees ORDER BY created_at DESC")->fetchAll();

// Per-employee activity summary — visible to Super Admin only.
$activityStmt = $pdo->query("
    SELECT e.id, e.name, e.role,
        (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.handled_by = e.id) AS received_income,
        (SELECT COUNT(*) FROM orders o WHERE o.handled_by = e.id) AS orders_handled,
        (SELECT COUNT(*) FROM products p WHERE p.created_by = e.id) AS products_added,
        (SELECT COUNT(*) FROM reviews r WHERE r.reviewed_by = e.id AND r.status = 'approved') AS reviews_approved
    FROM employees e
    WHERE e.role = 'employee'
    ORDER BY received_income DESC
");
$employeeActivity = $activityStmt->fetchAll();

require_once __DIR__ . '/includes/admin_header.php';
?>

<?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
<?php if ($msg = flash('error')): ?><div class="alert alert-error"><?= sanitize($msg) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $e) echo sanitize($e) . '<br>'; ?></div><?php endif; ?>

<div class="panel">
    <div class="panel-header"><h3>Employee Activity Overview</h3></div>
    <div class="table-wrap">
        <table class="admin-table">
            <thead><tr><th>Employee</th><th>Received Income</th><th>Orders Handled</th><th>Products Added</th><th>Reviews Approved</th></tr></thead>
            <tbody>
            <?php if (empty($employeeActivity)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--a-text-light);padding:24px;">No staff activity yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($employeeActivity as $a): ?>
                <tr>
                    <td><?= sanitize($a['name']) ?></td>
                    <td><strong><?= formatPrice($a['received_income']) ?></strong></td>
                    <td><?= (int)$a['orders_handled'] ?></td>
                    <td><?= (int)$a['products_added'] ?></td>
                    <td><?= (int)$a['reviews_approved'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid-2">
    <div class="panel">
        <div class="panel-header"><h3>Staff Accounts</h3></div>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($employees as $e): ?>
                    <tr>
                        <td><?= sanitize($e['name']) ?></td>
                        <td><?= sanitize($e['email']) ?></td>
                        <td><span class="role-badge" style="background:var(--a-primary-light);color:var(--a-primary-dark);"><?= $e['role'] === 'super_admin' ? 'Super Admin' : 'Staff' ?></span></td>
                        <td><span class="status-badge status-<?= $e['status'] === 'active' ? 'active' : 'inactive' ?>"><?= sanitize($e['status']) ?></span></td>
                        <td>
                            <a href="employees.php?edit=<?= (int)$e['id'] ?>" class="btn btn-sm btn-outline"><i class="fa-solid fa-pen"></i></a>
                            <?php if ((int)$e['id'] !== (int)$_SESSION['employee_id']): ?>
                            <a href="employees.php?delete=<?= (int)$e['id'] ?>" class="btn btn-sm confirm-delete"><i class="fa-solid fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h3><?= $editEmployee ? 'Edit Employee' : 'Add New Employee' ?></h3></div>
        <form method="post" action="employees.php">
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="id" value="<?= $editEmployee['id'] ?? '' ?>">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" required value="<?= sanitize($editEmployee['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="email" required value="<?= sanitize($editEmployee['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role">
                    <option value="employee" <?= ($editEmployee['role'] ?? '') === 'employee' ? 'selected' : '' ?>>Staff / Employee</option>
                    <option value="super_admin" <?= ($editEmployee['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="active" <?= ($editEmployee['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($editEmployee['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="form-group">
                <label>Password <?= $editEmployee ? '<small style="color:var(--a-text-light);">(leave blank to keep current)</small>' : '*' ?></label>
                <input type="password" name="password">
            </div>
            <button type="submit" class="btn btn-block"><?= $editEmployee ? 'Update Employee' : 'Add Employee' ?></button>
            <?php if ($editEmployee): ?><a href="employees.php" class="btn btn-outline btn-block" style="margin-top:8px;">Cancel</a><?php endif; ?>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
