<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Order Details';
requireCustomerLogin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ?");
$stmt->execute([$id, $_SESSION['customer_id']]);
$order = $stmt->fetch();

if (!$order) {
    redirect('order-history.php');
}

$itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemStmt->execute([$order['id']]);
$items = $itemStmt->fetchAll();

$statusSteps = ['pending', 'processing', 'shipped', 'delivered'];
include __DIR__ . '/includes/header.php';
?>
<div class="page-banner"><div class="container"><h1>Order Details</h1></div></div>

<section class="section">
    <div class="container" style="max-width:700px;">
        <div class="checkout-box">
            <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px;">
                <div>
                    <strong>Order Number:</strong> <?= sanitize($order['order_number']) ?><br>
                    <strong>Order Date:</strong> <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?>
                </div>
                <span class="status-badge status-<?= sanitize($order['order_status']) ?>"><?= sanitize($order['order_status']) ?></span>
            </div>

            <?php if ($order['order_status'] !== 'cancelled'):
                $currentIndex = array_search($order['order_status'], $statusSteps); ?>
            <div style="display:flex;justify-content:space-between;margin:30px 0;position:relative;">
                <div style="position:absolute;top:14px;left:0;right:0;height:3px;background:var(--border);z-index:0;"></div>
                <?php foreach ($statusSteps as $i => $step): $done = $i <= $currentIndex; ?>
                <div style="position:relative;z-index:1;text-align:center;flex:1;">
                    <div style="width:30px;height:30px;border-radius:50%;background:<?= $done ? 'var(--wine)' : '#fff' ?>;border:3px solid <?= $done ? 'var(--wine)' : 'var(--border)' ?>;margin:0 auto 8px;display:flex;align-items:center;justify-content:center;color:#fff;">
                        <?php if ($done): ?><i class="fa-solid fa-check" style="font-size:12px;"></i><?php endif; ?>
                    </div>
                    <small style="text-transform:capitalize;color:<?= $done ? 'var(--wine)' : 'var(--text-light)' ?>;font-weight:600;"><?= $step ?></small>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <div class="alert alert-error">This order has been cancelled.</div>
            <?php endif; ?>

            <h4 style="margin:20px 0 10px;">Items</h4>
            <?php foreach ($items as $item): ?>
            <div class="order-summary-item">
                <span><?= sanitize($item['product_name']) ?> &times; <?= (int)$item['quantity'] ?></span>
                <span><?= formatPrice($item['line_total']) ?></span>
            </div>
            <?php endforeach; ?>

            <div class="cart-summary" style="box-shadow:none;padding:16px 0;max-width:none;margin:10px 0 0;">
                <div class="row"><span>Subtotal</span><span><?= formatPrice($order['subtotal']) ?></span></div>
                <div class="row"><span>Shipping</span><span><?= formatPrice($order['shipping_fee']) ?></span></div>
                <div class="row total"><span>Total</span><span><?= formatPrice($order['total_amount']) ?></span></div>
            </div>
            <p><strong>Payment:</strong> <?= strtoupper(sanitize($order['payment_method'])) ?> — <span class="status-badge status-<?= sanitize($order['payment_status']) ?>"><?= sanitize($order['payment_status']) ?></span></p>
            <p style="margin-top:8px;"><strong>Shipping Address:</strong><br><?= sanitize($order['shipping_address']) ?><?= $order['city'] ? ', ' . sanitize($order['city']) : '' ?></p>
            <?php if (!empty($order['stitch_request'])): ?>
            <p style="margin-top:12px;padding:10px 14px;border-radius:8px;background:rgba(240,98,61,.08);border:1px dashed var(--coral);color:var(--coral);font-weight:600;">
                <i class="fa-solid fa-scissors"></i> Custom stitching requested for this order
            </p>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
