<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'My Orders';
requireCustomerLogin();

$stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['customer_id']]);
$orders = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="page-banner"><div class="container"><h1>My Orders</h1></div></div>

<section class="section">
    <div class="container">
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-box"></i>
                <h3>No orders yet</h3>
                <p>Your placed orders will show up here.</p>
                <a href="products.php" class="btn" style="margin-top:20px;">Start Shopping</a>
            </div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Order #</th><th>Date</th><th>Total</th><th>Payment</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><?= sanitize($o['order_number']) ?><?= !empty($o['stitch_request']) ? ' <i class="fa-solid fa-scissors" style="color:var(--coral);" title="Stitching requested"></i>' : '' ?></td>
                        <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                        <td><?= formatPrice($o['total_amount']) ?></td>
                        <td><span class="status-badge status-<?= sanitize($o['payment_status']) ?>"><?= sanitize($o['payment_status']) ?></span></td>
                        <td><span class="status-badge status-<?= sanitize($o['order_status']) ?>"><?= sanitize($o['order_status']) ?></span></td>
                        <td>
                            <a href="order-details.php?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
