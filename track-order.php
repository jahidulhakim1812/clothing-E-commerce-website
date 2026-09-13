<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Track Order';

$order = null;
$items = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderNumber = sanitize($_POST['order_number'] ?? '');
    $email = sanitize($_POST['email'] ?? '');

    $stmt = $pdo->prepare("SELECT o.*, COALESCE(c.name, o.guest_name) AS customer_name, COALESCE(c.email, o.guest_email) AS customer_email
                           FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
                           WHERE o.order_number = ? AND (o.guest_email = ? OR c.email = ?)");
    $stmt->execute([$orderNumber, $email, $email]);
    $order = $stmt->fetch();

    if ($order) {
        $itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $itemStmt->execute([$order['id']]);
        $items = $itemStmt->fetchAll();
    } else {
        $error = 'No order found with that order number and email combination. Please check your details and try again.';
    }
}

include __DIR__ . '/includes/header.php';

$statusSteps = ['pending', 'processing', 'shipped', 'delivered'];
?>

<div class="page-banner">
    <div class="container"><h1>Track Your Order</h1><p>Enter your order number and email to see the latest status.</p></div>
</div>

<section class="section">
    <div class="container" style="max-width:700px;">
        <form method="post" action="track-order.php" class="checkout-box" style="margin-bottom:30px;">
            <div class="form-row">
                <div class="form-group">
                    <label>Order Number *</label>
                    <input type="text" name="order_number" required placeholder="e.g. ORD20260727ABC123" value="<?= sanitize($_POST['order_number'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" required value="<?= sanitize($_POST['email'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-block"><i class="fa-solid fa-magnifying-glass"></i> Track Order</button>
        </form>

        <?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>

        <?php if ($order): ?>
        <div class="checkout-box">
            <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px;">
                <div>
                    <strong>Order Number:</strong> <?= sanitize($order['order_number']) ?><br>
                    <strong>Order Date:</strong> <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?>
                </div>
                <span class="status-badge status-<?= sanitize($order['order_status']) ?>"><?= sanitize($order['order_status']) ?></span>
            </div>

            <?php if ($order['order_status'] !== 'cancelled'): ?>
            <div style="display:flex;justify-content:space-between;margin:30px 0;position:relative;">
                <div style="position:absolute;top:14px;left:0;right:0;height:3px;background:var(--border);z-index:0;"></div>
                <?php
                $currentIndex = array_search($order['order_status'], $statusSteps);
                foreach ($statusSteps as $i => $step):
                    $done = $i <= $currentIndex;
                ?>
                <div style="position:relative;z-index:1;text-align:center;flex:1;">
                    <div style="width:30px;height:30px;border-radius:50%;background:<?= $done ? 'var(--wine)' : '#fff' ?>;border:3px solid <?= $done ? 'var(--wine)' : 'var(--border)' ?>;margin:0 auto 8px;display:flex;align-items:center;justify-content:center;color:#fff;">
                        <?php if ($done): ?><i class="fa-solid fa-check" style="font-size:12px;"></i><?php endif; ?>
                    </div>
                    <small style="text-transform:capitalize;color:<?= $done ? 'var(--wine)' : 'var(--text-light)' ?>;font-weight:600;"><?= $step ?></small>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <div class="alert alert-error" style="margin-top:16px;">This order has been cancelled.</div>
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
        </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
