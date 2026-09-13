<?php
$pageTitle = 'Order Management';
require_once __DIR__ . '/includes/admin_header.php';

$statusFilter = sanitize($_GET['status'] ?? '');
$where = '1=1';
$params = [];
if ($statusFilter !== '') {
    $where .= " AND order_status = ?";
    $params[] = $statusFilter;
}
if (!empty($_GET['search'])) {
    $where .= " AND (order_number LIKE ? OR guest_name LIKE ? OR guest_email LIKE ?)";
    $s = "%{$_GET['search']}%";
    $params[] = $s; $params[] = $s; $params[] = $s;
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE $where ORDER BY created_at DESC");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$statusCounts = $pdo->query("SELECT order_status, COUNT(*) as cnt FROM orders GROUP BY order_status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <a href="orders.php" class="btn btn-sm <?= $statusFilter === '' ? '' : 'btn-outline' ?>">All (<?= array_sum($statusCounts) ?>)</a>
    <?php foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $s): ?>
        <a href="orders.php?status=<?= $s ?>" class="btn btn-sm <?= $statusFilter === $s ? '' : 'btn-outline' ?>"><?= ucfirst($s) ?> (<?= (int)($statusCounts[$s] ?? 0) ?>)</a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <div class="panel-header">
        <h3>Orders (<?= count($orders) ?>)</h3>
        <form method="get" style="display:flex;gap:8px;">
            <?php if ($statusFilter): ?><input type="hidden" name="status" value="<?= sanitize($statusFilter) ?>"><?php endif; ?>
            <input type="text" name="search" placeholder="Search order #, name, email..." value="<?= sanitize($_GET['search'] ?? '') ?>" style="padding:9px 14px;border:1px solid var(--a-border);border-radius:8px;">
            <button type="submit" class="btn btn-outline btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
    </div>
    <div class="table-wrap">
        <table class="admin-table">
            <thead><tr><th>Order #</th><th>Customer</th><th>Contact</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="8" style="text-align:center;color:var(--a-text-light);padding:30px;">No orders found.</td></tr>
            <?php endif; ?>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><?= sanitize($o['order_number']) ?></td>
                    <td><?= sanitize($o['guest_name']) ?><?= $o['customer_id'] ? ' <span style="color:var(--a-primary);" title="Registered Customer"><i class=\'fa-solid fa-user-check\'></i></span>' : '' ?><?= !empty($o['stitch_request']) ? ' <span style="color:#DC2626;" title="Stitching requested"><i class=\'fa-solid fa-scissors\'></i></span>' : '' ?></td>
                    <td><?= sanitize($o['guest_email']) ?><br><small><?= sanitize($o['guest_phone']) ?></small></td>
                    <td><?= formatPrice($o['total_amount']) ?></td>
                    <td><span class="status-badge status-<?= sanitize($o['payment_status']) ?>"><?= sanitize($o['payment_status']) ?></span></td>
                    <td><span class="status-badge status-<?= sanitize($o['order_status']) ?>"><?= sanitize($o['order_status']) ?></span></td>
                    <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                    <td><a href="order-view.php?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline"><i class="fa-solid fa-eye"></i> View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
