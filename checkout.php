<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Checkout';

$cart = getCart();
if (empty($cart)) {
    redirect('cart.php');
}

$errors = [];

// Pre-fill for logged in customers
$prefill = ['name' => '', 'email' => '', 'phone' => '', 'address' => ''];
if (isCustomerLoggedIn()) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$_SESSION['customer_id']]);
    if ($c = $stmt->fetch()) {
        $prefill = ['name' => $c['name'], 'email' => $c['email'], 'phone' => $c['phone'], 'address' => $c['address']];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $shippingZone = ($_POST['shipping_zone'] ?? 'inside_dhaka') === 'outside_dhaka' ? 'outside_dhaka' : 'inside_dhaka';
    $paymentMethod = in_array($_POST['payment_method'] ?? '', ['cod', 'card', 'mobile_banking']) ? $_POST['payment_method'] : 'cod';
    $stitchRequest = isset($_POST['stitch_request']) ? 1 : 0;
    $notes = sanitize($_POST['notes'] ?? '');

    if ($name === '') $errors[] = 'Full name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
    if ($phone === '') $errors[] = 'Phone number is required.';
    if ($address === '') $errors[] = 'Shipping address is required.';

    if (empty($errors)) {
        $subtotal = cartTotal();
        $shippingFee = $shippingZone === 'inside_dhaka'
            ? (float)setting('shipping_fee_inside_dhaka', 70)
            : (float)setting('shipping_fee_outside_dhaka', 130);
        $total = $subtotal + $shippingFee;
        $orderNumber = generateOrderNumber();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO orders
                (order_number, customer_id, guest_name, guest_email, guest_phone, shipping_address, city, shipping_zone, subtotal, shipping_fee, total_amount, payment_method, notes, stitch_request)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $orderNumber,
                isCustomerLoggedIn() ? $_SESSION['customer_id'] : null,
                $name, $email, $phone, $address, $city, $shippingZone,
                $subtotal, $shippingFee, $total, $paymentMethod, $notes, $stitchRequest
            ]);
            $orderId = $pdo->lastInsertId();

            $itemsForEmail = [];
            $lowStockAlerts = [];
            foreach ($cart as $item) {
                $lineTotal = $item['price'] * $item['quantity'];
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_image, size, color, price, quantity, line_total) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$orderId, $item['product_id'], $item['name'], $item['image'], $item['size'], $item['color'], $item['price'], $item['quantity'], $lineTotal]);

                // Reduce stock + log inventory change
                $upd = $pdo->prepare("UPDATE products SET stock = GREATEST(stock - ?, 0) WHERE id = ?");
                $upd->execute([$item['quantity'], $item['product_id']]);
                $log = $pdo->prepare("INSERT INTO inventory_logs (product_id, change_qty, reason) VALUES (?, ?, ?)");
                $log->execute([$item['product_id'], -$item['quantity'], "Order #$orderNumber"]);

                // Flag anything that just dropped to/below the low-stock threshold
                $stockCheck = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
                $stockCheck->execute([$item['product_id']]);
                $remaining = (int)$stockCheck->fetchColumn();
                if ($remaining <= 10) {
                    $lowStockAlerts[] = ['name' => $item['name'], 'stock' => $remaining];
                }

                $itemsForEmail[] = [
                    'product_name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'line_total' => $lineTotal,
                ];
            }

            $pdo->commit();

            // Notify admins: new order + any low-stock products
            createNotification('order', 'New order received', "$orderNumber — " . formatPrice($total) . " from $name", "order-view.php?id=$orderId");
            foreach ($lowStockAlerts as $alert) {
                createNotification('low_stock', 'Low stock alert', "{$alert['name']} has only {$alert['stock']} units left", 'inventory.php');
            }

            $orderForEmail = [
                'order_number' => $orderNumber,
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'total_amount' => $total,
                'payment_method' => $paymentMethod,
                'shipping_address' => $address . ($city ? ', ' . $city : ''),
            ];
            $emailSent = sendOrderConfirmationEmail($email, $name, $orderForEmail, $itemsForEmail);

            clearCart();
            $_SESSION['last_order_number'] = $orderNumber;
            $_SESSION['last_order_email'] = $email;
            $_SESSION['email_sent'] = $emailSent;
            redirect('order-success.php');

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Something went wrong while placing your order. Please try again.';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-banner">
    <div class="container"><h1>Checkout</h1><p>No account needed — just enter your details below.</p></div>
</div>

<section class="section">
    <div class="container">
        <?php if ($errors): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $e): ?><div><?= sanitize($e) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!isCustomerLoggedIn()): ?>
        <div class="alert alert-info">
            <i class="fa-solid fa-circle-info"></i> Checking out as a guest. Just provide your name, email and phone — no registration required.
            Already have an account? <a href="login.php" style="font-weight:600;">Login here</a> for faster checkout next time.
        </div>
        <?php endif; ?>

        <form method="post" action="checkout.php">
            <div class="checkout-grid">
                <div class="checkout-box">
                    <h3><i class="fa-solid fa-user"></i> Contact & Shipping Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="name" required value="<?= sanitize($_POST['name'] ?? $prefill['name']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Email Address *</label>
                            <input type="email" name="email" required value="<?= sanitize($_POST['email'] ?? $prefill['email']) ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone Number *</label>
                            <input type="text" name="phone" required value="<?= sanitize($_POST['phone'] ?? $prefill['phone']) ?>">
                        </div>
                        <div class="form-group">
                            <label>City / Area</label>
                            <input type="text" name="city" placeholder="e.g. Dhanmondi" value="<?= sanitize($_POST['city'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Shipping Location *</label>
                        <div class="payment-options" id="shippingZoneOptions">
                            <label>
                                <input type="radio" name="shipping_zone" value="inside_dhaka" data-fee="<?= (float)setting('shipping_fee_inside_dhaka', 70) ?>" checked>
                                <span><i class="fa-solid fa-city"></i> Inside Dhaka — <?= formatPrice(setting('shipping_fee_inside_dhaka', 70)) ?></span>
                            </label>
                            <label>
                                <input type="radio" name="shipping_zone" value="outside_dhaka" data-fee="<?= (float)setting('shipping_fee_outside_dhaka', 130) ?>">
                                <span><i class="fa-solid fa-truck-fast"></i> Outside Dhaka — <?= formatPrice(setting('shipping_fee_outside_dhaka', 130)) ?></span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Full Shipping Address *</label>
                        <textarea name="address" rows="3" required><?= sanitize($_POST['address'] ?? $prefill['address']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="stitch-checkbox">
                            <input type="checkbox" name="stitch_request" value="1" <?= !empty($_POST['stitch_request']) ? 'checked' : '' ?>>
                            <span><i class="fa-solid fa-scissors"></i> Please stitch my dress for me (custom tailoring)</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Order Notes (optional)</label>
                        <textarea name="notes" rows="2" placeholder="Any special instructions..."><?= sanitize($_POST['notes'] ?? '') ?></textarea>
                    </div>

                    <h3 style="margin-top:26px;"><i class="fa-solid fa-credit-card"></i> Payment Method</h3>
                    <div class="payment-options">
                        <label><input type="radio" name="payment_method" value="cod" checked> <span><i class="fa-solid fa-money-bill-wave"></i> Cash on Delivery</span></label>
                        <label><input type="radio" name="payment_method" value="mobile_banking"> <span><i class="fa-solid fa-mobile-screen"></i> Mobile Banking (bKash/Nagad)</span></label>
                        <label><input type="radio" name="payment_method" value="card"> <span><i class="fa-solid fa-credit-card"></i> Credit / Debit Card</span></label>
                    </div>
                </div>

                <div class="checkout-box">
                    <h3>Order Summary</h3>
                    <?php foreach ($cart as $item): ?>
                    <div class="order-summary-item">
                        <span><?= sanitize($item['name']) ?> <?= $item['size'] ? '(' . sanitize($item['size']) . ')' : '' ?> &times; <?= (int)$item['quantity'] ?></span>
                        <span><?= formatPrice($item['price'] * $item['quantity']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div class="cart-summary" style="box-shadow:none;padding:16px 0;max-width:none;margin:0;">
                        <div class="row"><span>Subtotal</span><span id="checkoutSubtotal" data-value="<?= cartTotal() ?>"><?= formatPrice(cartTotal()) ?></span></div>
                        <div class="row"><span>Shipping</span><span id="checkoutShipping"><?= formatPrice(setting('shipping_fee_inside_dhaka', 70)) ?></span></div>
                        <div class="row total"><span>Total</span><span id="checkoutTotal"><?= formatPrice(cartTotal() + (float)setting('shipping_fee_inside_dhaka', 70)) ?></span></div>
                    </div>
                    <button type="submit" class="btn btn-block" style="margin-top:10px;"><i class="fa-solid fa-lock"></i> Place Order</button>
                </div>
            </div>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var radios = document.querySelectorAll('#shippingZoneOptions input[name="shipping_zone"]');
    var subtotalEl = document.getElementById('checkoutSubtotal');
    var shippingEl = document.getElementById('checkoutShipping');
    var totalEl = document.getElementById('checkoutTotal');
    var currencySymbol = '<?= sanitize(setting("currency_symbol", "৳")) ?>';
    var subtotal = parseFloat(subtotalEl.dataset.value);

    function formatMoney(n) {
        return currencySymbol + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            var fee = parseFloat(this.dataset.fee);
            shippingEl.textContent = formatMoney(fee);
            totalEl.textContent = formatMoney(subtotal + fee);
        });
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
