<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Order Confirmed';

if (empty($_SESSION['last_order_number'])) {
    redirect('index.php');
}
$orderNumber = $_SESSION['last_order_number'];
$email = $_SESSION['last_order_email'];
$emailSent = $_SESSION['email_sent'] ?? false;

unset($_SESSION['last_order_number'], $_SESSION['last_order_email'], $_SESSION['email_sent']);

include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="order-success-box">
            <i class="fa-solid fa-circle-check"></i>
            <h2>Order Placed Successfully!</h2>
            <p>Thank you for shopping with us. Your order has been received and is now being processed.</p>
            <div class="order-number-box"><?= sanitize($orderNumber) ?></div>

            <?php if ($emailSent): ?>
                <p style="color:#1e8e3e;"><i class="fa-solid fa-envelope-circle-check"></i> A confirmation email has been sent to <strong><?= sanitize($email) ?></strong>.</p>
            <?php else: ?>
                <p style="color:var(--text-light);">We could not send a confirmation email right now, but your order is confirmed. Please save your order number above.</p>
            <?php endif; ?>

            <p style="margin-top:16px;color:var(--text-light);">You can track your order anytime using your order number and email address.</p>

            <div style="display:flex;gap:14px;justify-content:center;margin-top:26px;flex-wrap:wrap;">
                <a href="track-order.php" class="btn btn-outline">Track My Order</a>
                <a href="products.php" class="btn">Continue Shopping</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
