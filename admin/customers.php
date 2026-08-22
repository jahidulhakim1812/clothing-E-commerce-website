<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
requireSuperAdmin();
$pageTitle = 'Customer Management';

if (isset($_GET['toggle'])) {
    $stmt = $pdo->prepare("SELECT status FROM customers WHERE id = ?");
    $stmt->execute([(int)$_GET['toggle']]);
    $cur = $stmt->fetchColumn();
    $new = $cur === 'active' ? 'blocked' : 'active';
    $upd = $pdo->prepare("UPDATE customers SET status = ? WHERE id = ?");
    $upd->execute([$new, (int)$_GET['toggle']]);
    flash('success', 'Customer status updated.');
    redirect('customers.php');
}

$search = sanitize($_GET['search'] ?? '');
$where = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND (name LIKE ? OR email LIKE ?)";
    $params = ["%$search%", "%$search%"];
}
$stmt = $pdo->prepare("SELECT c.*,
    (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS order_count,
    (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.customer_id = c.id) AS total_spent
    FROM customers c WHERE $where ORDER BY c.created_at DESC");
$stmt->execute($params);
$customers = $stmt->fetchAll();

require_once __DIR__ . '/includes/admin_header.php';
?>

<?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <h3>Registered Customers (<?= count($customers) ?>)</h3>
        <form method="get" style="display:flex;gap:8px;">
            <input type="text" name="search" placeholder="Search by name or email..." value="<?= sanitize($search) ?>" style="padding:9px 14px;border:1px solid var(--a-border);border-radius:8px;">
            <button type="submit" class="btn btn-outline btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
    </div>
    <div class="table-wrap">
        <table class="admin-table">
            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Orders</th><th>Total Spent</th><th>Joined</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($customers)): ?>
                <tr><td colspan="8" style="text-align:center;color:var(--a-text-light);padding:30px;">No customers found.</td></tr>
            <?php endif; ?>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td><?= sanitize($c['name']) ?></td>
                    <td><?= sanitize($c['email']) ?></td>
                    <td><?= sanitize($c['phone']) ?></td>
                    <td><?= (int)$c['order_count'] ?></td>
                    <td><?= formatPrice($c['total_spent']) ?></td>
                    <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                    <td><span class="status-badge status-<?= $c['status'] === 'active' ? 'active' : 'inactive' ?>"><?= sanitize($c['status']) ?></span></td>
                    <td>
                        <a href="customers.php?toggle=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline confirm-delete">
                            <?= $c['status'] === 'active' ? 'Block' : 'Unblock' ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
