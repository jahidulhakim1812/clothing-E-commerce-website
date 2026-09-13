<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
$pageTitle = 'Product Reviews';

// Approve / Reject / Delete actions — available to both Super Admin and Employee
if (isset($_GET['approve'])) {
    $stmt = $pdo->prepare("UPDATE reviews SET status='approved', reviewed_by=? WHERE id=?");
    $stmt->execute([$_SESSION['employee_id'], (int)$_GET['approve']]);
    flash('success', 'Review approved and is now visible on the storefront.');
    redirect('reviews.php');
}
if (isset($_GET['reject'])) {
    $stmt = $pdo->prepare("UPDATE reviews SET status='rejected', reviewed_by=? WHERE id=?");
    $stmt->execute([$_SESSION['employee_id'], (int)$_GET['reject']]);
    flash('success', 'Review rejected.');
    redirect('reviews.php');
}
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
    $stmt->execute([(int)$_GET['delete']]);
    flash('success', 'Review deleted.');
    redirect('reviews.php');
}

$statusFilter = sanitize($_GET['status'] ?? '');
$where = '1=1';
$params = [];
if ($statusFilter !== '') {
    $where .= " AND r.status = ?";
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare("SELECT r.*, p.name AS product_name, p.image AS product_image
    FROM reviews r JOIN products p ON p.id = r.product_id
    WHERE $where ORDER BY r.created_at DESC");
$stmt->execute($params);
$reviews = $stmt->fetchAll();

$statusCounts = $pdo->query("SELECT status, COUNT(*) cnt FROM reviews GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

require_once __DIR__ . '/includes/admin_header.php';
?>

<?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <a href="reviews.php" class="btn btn-sm <?= $statusFilter === '' ? '' : 'btn-outline' ?>">All (<?= array_sum($statusCounts) ?>)</a>
    <?php foreach (['pending', 'approved', 'rejected'] as $s): ?>
        <a href="reviews.php?status=<?= $s ?>" class="btn btn-sm <?= $statusFilter === $s ? '' : 'btn-outline' ?>"><?= ucfirst($s) ?> (<?= (int)($statusCounts[$s] ?? 0) ?>)</a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <div class="panel-header"><h3>Reviews (<?= count($reviews) ?>)</h3></div>
    <div class="table-wrap">
        <table class="admin-table">
            <thead><tr><th>Product</th><th>Customer</th><th>Rating</th><th>Comment</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($reviews)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--a-text-light);padding:30px;">No reviews found.</td></tr>
            <?php endif; ?>
            <?php foreach ($reviews as $r): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <img class="table-thumb" src="../assets/uploads/products/<?= sanitize($r['product_image']) ?>" onerror="this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?w=100&q=80'" alt="">
                            <?= sanitize($r['product_name']) ?>
                        </div>
                    </td>
                    <td><?= sanitize($r['customer_name']) ?><br><small style="color:var(--a-text-light);"><?= sanitize($r['customer_email']) ?></small></td>
                    <td>
                        <?php for ($i = 0; $i < 5; $i++): ?>
                            <i class="fa-solid fa-star" style="color:<?= $i < (int)$r['rating'] ? '#F0B23D' : 'var(--a-border)' ?>;font-size:12px;"></i>
                        <?php endfor; ?>
                    </td>
                    <td style="max-width:260px;"><?= nl2br(sanitize($r['comment'])) ?></td>
                    <td><span class="status-badge status-<?= $r['status'] === 'approved' ? 'active' : ($r['status'] === 'rejected' ? 'cancelled' : 'pending') ?>"><?= ucfirst($r['status']) ?></span></td>
                    <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                    <td style="white-space:nowrap;">
                        <?php if ($r['status'] !== 'approved'): ?>
                            <a href="reviews.php?approve=<?= (int)$r['id'] ?>" class="btn btn-sm" title="Approve"><i class="fa-solid fa-check"></i></a>
                        <?php endif; ?>
                        <?php if ($r['status'] !== 'rejected'): ?>
                            <a href="reviews.php?reject=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline" title="Reject"><i class="fa-solid fa-ban"></i></a>
                        <?php endif; ?>
                        <a href="reviews.php?delete=<?= (int)$r['id'] ?>" class="btn btn-sm confirm-delete" title="Delete"><i class="fa-solid fa-trash"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
