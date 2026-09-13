<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
$pageTitle = 'Order Details';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'Order not found.');
    redirect('orders.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['order_status'] ?? $order['order_status'];
    $newPaymentStatus = $_POST['payment_status'] ?? $order['payment_status'];

    $stmt = $pdo->prepare("UPDATE orders SET order_status = ?, payment_status = ?, handled_by = ? WHERE id = ?");
    $stmt->execute([$newStatus, $newPaymentStatus, $_SESSION['employee_id'], $id]);

    // If cancelled, restock items
    if ($newStatus === 'cancelled' && $order['order_status'] !== 'cancelled') {
        $items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $items->execute([$id]);
        foreach ($items->fetchAll() as $item) {
            if ($item['product_id']) {
                $upd = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                $upd->execute([$item['quantity'], $item['product_id']]);
                $log = $pdo->prepare("INSERT INTO inventory_logs (product_id, change_qty, reason) VALUES (?,?,?)");
                $log->execute([$item['product_id'], $item['quantity'], "Order #{$order['order_number']} cancelled - restocked"]);
            }
        }
    }

    flash('success', 'Order updated successfully.');
    redirect('order-view.php?id=' . $id);
}

$itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemStmt->execute([$id]);
$items = $itemStmt->fetchAll();

$handledByName = null;
if (!empty($order['handled_by'])) {
    $hb = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
    $hb->execute([$order['handled_by']]);
    $handledByName = $hb->fetchColumn();
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>

<div style="margin-bottom:16px;">
    <a href="orders.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to Orders</a>
</div>

<div class="grid-2">
    <div class="panel">
        <div class="panel-header">
            <h3>Order <?= sanitize($order['order_number']) ?></h3>
            <span class="status-badge status-<?= sanitize($order['order_status']) ?>"><?= sanitize($order['order_status']) ?></span>
        </div>
        <p style="font-size:14px;color:var(--a-text-light);margin-bottom:16px;">Placed on <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></p>

        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Product</th><th>Size/Color</th><th>Price</th><th>Qty</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <img class="table-thumb" src="../assets/uploads/products/<?= sanitize($item['product_image']) ?>" onerror="this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?w=100&q=80'" alt="">
                                <?= sanitize($item['product_name']) ?>
                            </div>
                        </td>
                        <td><?= sanitize($item['size']) ?> <?= sanitize($item['color']) ?></td>
                        <td><?= formatPrice($item['price']) ?></td>
                        <td><?= (int)$item['quantity'] ?></td>
                        <td><?= formatPrice($item['line_total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="cart-summary" style="box-shadow:none;padding:16px 0;max-width:none;margin:10px 0 0;">
            <div class="row"><span>Subtotal</span><span><?= formatPrice($order['subtotal']) ?></span></div>
            <div class="row"><span>Shipping</span><span><?= formatPrice($order['shipping_fee']) ?></span></div>
            <div class="row total"><span>Total</span><span><?= formatPrice($order['total_amount']) ?></span></div>
        </div>

        <?php if (!empty($order['stitch_request'])): ?>
        <div style="margin-top:16px;padding:10px 14px;border-radius:8px;background:rgba(220,38,38,.08);border:1px dashed #DC2626;color:#DC2626;font-weight:600;font-size:14px;">
            <i class="fa-solid fa-scissors"></i> Customer requested custom stitching for this order
        </div>
        <?php endif; ?>

        <?php if (!empty($order['notes'])): ?>
        <div style="margin-top:16px;">
            <strong>Customer Notes:</strong>
            <p style="color:var(--a-text-light);font-size:14px;"><?= sanitize($order['notes']) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="panel">
            <div class="panel-header"><h3>Customer Info</h3></div>
            <p style="font-size:14px;margin-bottom:8px;"><i class="fa-solid fa-user"></i> <?= sanitize($order['guest_name']) ?></p>
            <p style="font-size:14px;margin-bottom:8px;"><i class="fa-solid fa-envelope"></i> <?= sanitize($order['guest_email']) ?></p>
            <p style="font-size:14px;margin-bottom:8px;"><i class="fa-solid fa-phone"></i> <?= sanitize($order['guest_phone']) ?></p>
            <p style="font-size:14px;"><i class="fa-solid fa-location-dot"></i> <?= sanitize($order['shipping_address']) ?><?= $order['city'] ? ', ' . sanitize($order['city']) : '' ?></p>
            <?php if ($order['customer_id']): ?>
                <p style="margin-top:10px;"><span class="status-badge status-active">Registered Customer</span></p>
            <?php else: ?>
                <p style="margin-top:10px;"><span class="status-badge status-pending">Guest Checkout</span></p>
            <?php endif; ?>
            <?php if ($handledByName): ?>
                <p style="margin-top:10px;font-size:13.5px;color:var(--a-text-light);"><i class="fa-solid fa-user-tie"></i> Handled by <strong><?= sanitize($handledByName) ?></strong></p>
            <?php endif; ?>
        </div>

        <div class="panel">
            <div class="panel-header"><h3>Update Order</h3></div>
            <form method="post" action="order-view.php?id=<?= $id ?>">
                <div class="form-group">
                    <label>Order Status</label>
                    <select name="order_status">
                        <?php foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $s): ?>
                            <option value="<?= $s ?>" <?= $order['order_status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Payment Status</label>
                    <select name="payment_status">
                        <?php foreach (['unpaid', 'paid', 'failed', 'refunded'] as $s): ?>
                            <option value="<?= $s ?>" <?= $order['payment_status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <input type="text" value="<?= strtoupper(sanitize($order['payment_method'])) ?>" disabled>
                </div>
                <button type="submit" class="btn btn-block">Update Order</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
