<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
$pageTitle = 'Inventory Management';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'adjust') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $changeQty = (int)($_POST['change_qty'] ?? 0);
    $reason = sanitize($_POST['reason'] ?? 'Manual adjustment');

    if ($productId > 0 && $changeQty != 0) {
        $stmt = $pdo->prepare("UPDATE products SET stock = GREATEST(stock + ?, 0) WHERE id = ?");
        $stmt->execute([$changeQty, $productId]);
        $log = $pdo->prepare("INSERT INTO inventory_logs (product_id, change_qty, reason, employee_id) VALUES (?,?,?,?)");
        $log->execute([$productId, $changeQty, $reason, $_SESSION['employee_id']]);
        flash('success', 'Inventory updated successfully.');
    } else {
        $errors[] = 'Please select a product and enter a valid quantity change.';
    }
    if (empty($errors)) redirect('inventory.php');
}

$search = sanitize($_GET['search'] ?? '');
$where = "p.status = 'active'";
$params = [];
if ($search !== '') {
    $where .= " AND p.name LIKE ?";
    $params[] = "%$search%";
}
$stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE $where ORDER BY p.stock ASC");
$stmt->execute($params);
$products = $stmt->fetchAll();

$allProducts = $pdo->query("SELECT id, name, stock FROM products ORDER BY name ASC")->fetchAll();

$recentLogs = $pdo->query("SELECT l.*, p.name AS product_name, e.name AS employee_name
    FROM inventory_logs l JOIN products p ON p.id = l.product_id
    LEFT JOIN employees e ON e.id = l.employee_id
    ORDER BY l.created_at DESC LIMIT 10")->fetchAll();

require_once __DIR__ . '/includes/admin_header.php';
?>

<?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $e) echo sanitize($e) . '<br>'; ?></div><?php endif; ?>

<div class="grid-2">
    <div class="panel">
        <div class="panel-header">
            <h3>Stock Levels</h3>
            <form method="get" style="display:flex;gap:8px;">
                <input type="text" name="search" placeholder="Search product..." value="<?= sanitize($search) ?>" style="padding:9px 14px;border:1px solid var(--a-border);border-radius:8px;">
                <button type="submit" class="btn btn-outline btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Product</th><th>Category</th><th>Stock</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><?= sanitize($p['name']) ?></td>
                        <td><?= sanitize($p['category_name']) ?></td>
                        <td style="font-weight:700;color:<?= $p['stock'] <= 10 ? '#c0392b' : ($p['stock'] <= 30 ? '#e67e22' : '#1e8e3e') ?>;"><?= (int)$p['stock'] ?></td>
                        <td>
                            <?php if ($p['stock'] <= 0): ?>
                                <span class="status-badge status-inactive">Out of Stock</span>
                            <?php elseif ($p['stock'] <= 10): ?>
                                <span class="status-badge status-pending">Low Stock</span>
                            <?php else: ?>
                                <span class="status-badge status-active">In Stock</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <div class="panel">
            <div class="panel-header"><h3>Adjust Stock</h3></div>
            <form method="post" action="inventory.php">
                <input type="hidden" name="form_action" value="adjust">
                <div class="form-group">
                    <label>Product</label>
                    <select name="product_id" required>
                        <option value="">Select Product</option>
                        <?php foreach ($allProducts as $p): ?>
                            <option value="<?= (int)$p['id'] ?>"><?= sanitize($p['name']) ?> (Current: <?= (int)$p['stock'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity Change <small style="color:var(--a-text-light);">(use negative to reduce, e.g. -5)</small></label>
                    <input type="number" name="change_qty" required placeholder="e.g. 20 or -5">
                </div>
                <div class="form-group">
                    <label>Reason</label>
                    <input type="text" name="reason" placeholder="e.g. New stock arrival, damaged goods">
                </div>
                <button type="submit" class="btn btn-block">Apply Adjustment</button>
            </form>
        </div>

        <div class="panel">
            <div class="panel-header"><h3>Recent Activity</h3></div>
            <?php foreach ($recentLogs as $log): ?>
            <div style="display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--a-border);font-size:13.5px;">
                <div>
                    <?= sanitize($log['product_name']) ?><br>
                    <small style="color:var(--a-text-light);"><?= sanitize($log['reason']) ?> <?= $log['employee_name'] ? '· ' . sanitize($log['employee_name']) : '' ?></small>
                </div>
                <span style="font-weight:700;color:<?= $log['change_qty'] > 0 ? '#1e8e3e' : '#c0392b' ?>;"><?= $log['change_qty'] > 0 ? '+' : '' ?><?= (int)$log['change_qty'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recentLogs)): ?><p style="color:var(--a-text-light);font-size:14px;">No inventory activity yet.</p><?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
