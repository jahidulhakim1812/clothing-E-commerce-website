<?php
/**
 * Renders the cart drawer item rows.
 * Expects $cart (array from getCart()) in scope.
 */
if (empty($cart)):
?>
<div class="drawer-empty">
    <i class="fa-solid fa-basket-shopping"></i>
    <p>Your cart is empty</p>
    <a href="products.php" class="btn btn-sm" id="drawerContinueShopping">Start Shopping</a>
</div>
<?php else: ?>
<?php foreach ($cart as $key => $item): ?>
<div class="drawer-item" data-key="<?= sanitize($key) ?>">
    <img src="assets/uploads/products/<?= sanitize($item['image']) ?>"
         onerror="this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?w=200&q=80'" alt="">
    <div class="drawer-item-info">
        <strong><?= sanitize($item['name']) ?></strong>
        <?php if ($item['size'] || $item['color']): ?>
        <small><?= $item['size'] ? 'Size: ' . sanitize($item['size']) . ' ' : '' ?><?= $item['color'] ? 'Color: ' . sanitize($item['color']) : '' ?></small>
        <?php endif; ?>
        <div class="drawer-item-row">
            <div class="drawer-qty" data-key="<?= sanitize($key) ?>">
                <button type="button" class="drawer-qty-minus">-</button>
                <span><?= (int)$item['quantity'] ?></span>
                <button type="button" class="drawer-qty-plus">+</button>
            </div>
            <span class="drawer-item-price"><?= formatPrice($item['price'] * $item['quantity']) ?></span>
        </div>
    </div>
    <button type="button" class="drawer-remove" data-key="<?= sanitize($key) ?>" title="Remove"><i class="fa-solid fa-xmark"></i></button>
</div>
<?php endforeach; ?>
<?php endif; ?>
