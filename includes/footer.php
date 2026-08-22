<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-col">
            <img src="assets/img/logo.png" alt="<?= sanitize(setting('site_name', 'Stitch & Souls')) ?>" style="height:60px;filter:brightness(0) invert(1);opacity:.92;">
            <p><?= sanitize(setting('site_tagline', 'Handmade With Heart')) ?> — thoughtfully hand-stitched clothing made to be loved.</p>
            <div class="footer-social">
                <a href="<?= sanitize(setting('facebook_link', '#')) ?>"><i class="fa-brands fa-facebook"></i></a>
                <a href="<?= sanitize(setting('instagram_link', '#')) ?>"><i class="fa-brands fa-instagram"></i></a>
                <a href="#"><i class="fa-brands fa-whatsapp"></i></a>
            </div>
        </div>
        <div class="footer-col">
            <h4>Quick Links</h4>
            <a href="index.php">Home</a>
            <a href="products.php">Shop All</a>
            <a href="products.php?filter=flash_sale">Flash Sale</a>
            <a href="track-order.php">Track Order</a>
        </div>
        <div class="footer-col">
            <h4>My Account</h4>
            <a href="register.php">Create Account</a>
            <a href="login.php">Login</a>
            <a href="order-history.php">Order History</a>
            <a href="cart.php">Cart</a>
              <a href="/stitchsouls/admin/login.php">Setup</a>
        </div>
        <div class="footer-col">
            <h4>Contact Us</h4>
            <p><i class="fa-solid fa-location-dot"></i> <?= sanitize(setting('site_address')) ?></p>
            <p><i class="fa-solid fa-phone"></i> <?= sanitize(setting('site_phone')) ?></p>
            <p><i class="fa-solid fa-envelope"></i> <?= sanitize(setting('site_email')) ?></p>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; <?= date('Y') ?> <?= sanitize(setting('site_name', 'Stitch & Souls')) ?>. All Rights Reserved. Handmade With Heart.
    </div>
</footer>

<!-- Slide-Out Cart Drawer -->
<div class="cart-drawer-overlay" id="cartDrawerOverlay"></div>
<aside class="cart-drawer" id="cartDrawer" aria-label="Shopping cart">
    <div class="cart-drawer-header">
        <h3><i class="fa-solid fa-basket-shopping"></i> Your Cart</h3>
        <button type="button" class="cart-drawer-close" id="cartDrawerClose" aria-label="Close cart">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="cart-drawer-body" id="cartDrawerBody">
        <?php $cart = getCart(); include __DIR__ . '/cart-drawer-items.php'; ?>
    </div>
    <div class="cart-drawer-footer" id="cartDrawerFooter" <?= empty($cart) ? 'style="display:none;"' : '' ?>>
        <div class="drawer-subtotal-row">
            <span>Subtotal</span>
            <span id="drawerSubtotal"><?= formatPrice(cartTotal()) ?></span>
        </div>
        <a href="cart.php" class="btn btn-outline">View Full Cart</a>
        <a href="checkout.php" class="btn btn-gradient">Checkout <i class="fa-solid fa-arrow-right"></i></a>
    </div>
</aside>

<script src="assets/js/main.js"></script>
</body>
</html>
