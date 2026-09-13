<?php
$cats = $pdo->query("SELECT id, name FROM categories WHERE status='active' ORDER BY name ASC")->fetchAll();
?>
<header class="main-navbar" id="mainNavbar">
    <div class="container navbar-inner">
        <a href="index.php" class="brand">
            <img src="assets/img/logo.png" alt="<?= sanitize(setting('site_name', 'Stitch & Souls')) ?>">
            <span class="brand-name">Stitch &amp; Souls</span>
        </a>

        <nav class="nav-links" id="navLinks">
            <a href="index.php">Home</a>
            <div class="dropdown">
                <a href="products.php">Shop <i class="fa-solid fa-chevron-down"></i></a>
                <div class="dropdown-menu">
                    <?php foreach ($cats as $c): ?>
                        <a href="products.php?category=<?= (int)$c['id'] ?>"><?= sanitize($c['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <a href="products.php?filter=flash_sale">Flash Sale</a>
            <a href="track-order.php">Track Order</a>
            <?php if (isCustomerLoggedIn()): ?>
                <a href="order-history.php">My Orders</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </nav>

        <div class="navbar-actions">
            <form class="search-box" action="products.php" method="get">
                <input type="text" name="search" placeholder="Search handmade goods..." value="<?= sanitize($_GET['search'] ?? '') ?>">
                <button type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
            <a href="cart.php" class="cart-icon" id="cartDrawerToggle">
                <i class="fa-solid fa-basket-shopping"></i>
                <span class="cart-badge" id="cartBadge"><?= cartCount() ?></span>
            </a>
            <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle menu">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>
    </div>
</header>
