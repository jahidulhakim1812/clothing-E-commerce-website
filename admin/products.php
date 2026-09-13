<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
$pageTitle = 'Product Management';

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([(int)$_GET['delete']]);
    flash('success', 'Product deleted successfully.');
    redirect('products.php');
}

$search = sanitize($_GET['search'] ?? '');
$where = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$stmt = $pdo->prepare("SELECT p.*, c.name AS category_name, e.name AS added_by_name FROM products p JOIN categories c ON c.id = p.category_id LEFT JOIN employees e ON e.id = p.created_by WHERE $where ORDER BY p.created_at DESC");
$stmt->execute($params);
$products = $stmt->fetchAll();

require_once __DIR__ . '/includes/admin_header.php';
?>

<?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <h3>All Products (<?= count($products) ?>)</h3>
        <div style="display:flex;gap:10px;">
            <form method="get" style="display:flex;gap:8px;">
                <input type="text" name="search" placeholder="Search by name or SKU..." value="<?= sanitize($search) ?>" style="padding:9px 14px;border:1px solid var(--a-border);border-radius:8px;">
                <button type="submit" class="btn btn-outline btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
            <a href="product-form.php" class="btn"><i class="fa-solid fa-plus"></i> Add Product</a>
        </div>
    </div>
    <div class="table-wrap">
        <table class="admin-table">
            <thead><tr><th>Image</th><th>Name</th><th>Category</th><th>Price</th><?php if (isSuperAdmin()): ?><th>Purchase Amt</th><?php endif; ?><th>Stock</th><th>Added By</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="<?= isSuperAdmin() ? 8 : 7 ?>" style="text-align:center;color:var(--a-text-light);padding:30px;">No products found.</td></tr>
            <?php endif; ?>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><img class="table-thumb" src="../assets/uploads/products/<?= sanitize($p['image']) ?>" onerror="this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?w=100&q=80'" alt=""></td>
                    <td><?= sanitize($p['name']) ?><br><small style="color:var(--a-text-light);">SKU: <?= sanitize($p['sku']) ?></small></td>
                    <td><?= sanitize($p['category_name']) ?></td>
                    <td>
                        <?= formatPrice($p['discount_price'] ?: $p['price']) ?>
                        <?php if ($p['discount_price']): ?><br><small style="text-decoration:line-through;color:var(--a-text-light);"><?= formatPrice($p['price']) ?></small><?php endif; ?>
                    </td>
                    <?php if (isSuperAdmin()): ?>
                    <td><?= $p['cost_price'] !== null ? formatPrice($p['cost_price']) : '—' ?></td>
                    <?php endif; ?>
                    <td><span style="color:<?= $p['stock'] <= 10 ? '#c0392b' : 'inherit' ?>;font-weight:600;"><?= (int)$p['stock'] ?></span></td>
                    <td><?= $p['added_by_name'] ? sanitize($p['added_by_name']) : '—' ?></td>
                    <td><span class="status-badge status-<?= sanitize($p['status']) ?>"><?= sanitize($p['status']) ?></span></td>
                    <td>
                        <a href="product-form.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline"><i class="fa-solid fa-pen"></i></a>
                        <a href="products.php?delete=<?= (int)$p['id'] ?>" class="btn btn-sm confirm-delete"><i class="fa-solid fa-trash"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
