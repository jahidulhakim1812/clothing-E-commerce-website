<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Shopping Cart';

// ---------- Handle POST actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'quick_add') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        $size = sanitize($_POST['size'] ?? '');
        $color = sanitize($_POST['color'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status='active'");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if ($product && $product['stock'] > 0) {
            $price = !empty($product['discount_price']) && $product['discount_price'] < $product['price']
                ? $product['discount_price'] : $product['price'];
            addToCart($product['id'], $product['name'], $price, $product['image'], $qty, $size, $color);
            flash('success', 'Added to cart successfully.');

            if (isset($_POST['buy_now'])) {
                redirect('checkout.php');
            }
        } else {
            flash('error', 'Sorry, this product is out of stock.');
        }
        redirect($action === 'quick_add' ? ($_SERVER['HTTP_REFERER'] ?? 'products.php') : 'cart.php');
    }

    if ($action === 'update') {
        foreach ($_POST['qty'] ?? [] as $key => $qty) {
            updateCartQty($key, (int)$qty);
        }
        flash('success', 'Cart updated.');
        redirect('cart.php');
    }

    if ($action === 'remove') {
        removeFromCart($_POST['key'] ?? '');
        flash('success', 'Item removed from cart.');
        redirect('cart.php');
    }
}

$cart = getCart();
include __DIR__ . '/includes/header.php';
?>

<div class="page-banner">
    <div class="container"><h1>Shopping Cart</h1></div>
</div>

<section class="section">
    <div class="container">
        <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
        <?php if ($msg = flash('error')): ?><div class="alert alert-error"><?= sanitize($msg) ?></div><?php endif; ?>

        <?php if (empty($cart)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-cart-shopping"></i>
                <h3>Your cart is empty</h3>
                <p>Looks like you haven't added anything yet.</p>
                <a href="products.php" class="btn" style="margin-top:20px;">Continue Shopping</a>
            </div>
        <?php else: ?>
        <form method="post" action="cart.php">
            <input type="hidden" name="action" value="update">
            <div class="table-wrap">
                <table class="cart-table">
                    <thead>
                        <tr><th>Product</th><th>Price</th><th>Quantity</th><th>Total</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart as $key => $item): ?>
                        <tr>
                            <td>
                                <div class="cart-product">
                                    <img src="assets/uploads/products/<?= sanitize($item['image']) ?>"
                                         onerror="this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?w=200&q=80'" alt="">
                                    <div>
                                        <strong><?= sanitize($item['name']) ?></strong><br>
                                        <small style="color:var(--text-light);">
                                            <?= $item['size'] ? 'Size: ' . sanitize($item['size']) . ' ' : '' ?>
                                            <?= $item['color'] ? 'Color: ' . sanitize($item['color']) : '' ?>
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td><?= formatPrice($item['price']) ?></td>
                            <td>
                                <input type="number" name="qty[<?= sanitize($key) ?>]" value="<?= (int)$item['quantity'] ?>" min="1" style="width:70px;padding:6px;border:1px solid var(--border);border-radius:6px;">
                            </td>
                            <td><?= formatPrice($item['price'] * $item['quantity']) ?></td>
                            <td>
                                <button type="submit" form="removeForm_<?= sanitize($key) ?>" class="remove-btn" title="Remove"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-outline">Update Cart</button>
                <a href="products.php" class="btn btn-outline">Continue Shopping</a>
            </div>
        </form>

        <?php foreach ($cart as $key => $item): ?>
        <form id="removeForm_<?= sanitize($key) ?>" method="post" action="cart.php" style="display:none;">
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="key" value="<?= sanitize($key) ?>">
        </form>
        <?php endforeach; ?>

        <div class="cart-summary" style="margin-top:30px;">
            <div class="row"><span>Subtotal</span><span><?= formatPrice(cartTotal()) ?></span></div>
            <div class="row"><span>Shipping</span><span>Calculated at checkout</span></div>
            <div class="row total"><span>Total</span><span><?= formatPrice(cartTotal()) ?></span></div>
            <a href="checkout.php" class="btn btn-block" style="margin-top:16px;justify-content:center;">Proceed to Checkout <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
