<?php
$isSuper = isSuperAdmin();
$active = fn($pages) => in_array($currentPage, (array)$pages) ? 'active' : '';
$initials = strtoupper(substr($_SESSION['employee_name'], 0, 1));
?>
<aside class="admin-sidebar" id="adminSidebar">
    <button class="sidebar-toggle" id="sidebarToggle" type="button" title="Collapse sidebar" aria-label="Collapse sidebar" aria-controls="adminSidebar" aria-expanded="true"><i class="fa-solid fa-chevron-left"></i></button>

    <div class="sidebar-brand">
        <div class="brand-mark">S&amp;S</div>
        <div class="brand-text">
            <span><?= sanitize(setting('site_name', 'Stitch & Souls')) ?></span>
            <span class="brand-sub">Admin Console</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-title">Main</div>
        <a href="dashboard.php" title="Dashboard" class="<?= $active('dashboard.php') ?>"><i class="fa-solid fa-gauge-high"></i><span class="nav-label">Dashboard</span></a>

        <div class="nav-section-title">Catalog</div>
        <?php if ($isSuper): ?>
        <a href="categories.php" title="Categories" class="<?= $active('categories.php') ?>"><i class="fa-solid fa-tags"></i><span class="nav-label">Categories</span></a>
        <?php endif; ?>
        <a href="products.php" title="Products" class="<?= $active(['products.php','product-form.php']) ?>"><i class="fa-solid fa-shirt"></i><span class="nav-label">Products</span></a>
        <a href="inventory.php" title="Inventory" class="<?= $active('inventory.php') ?>"><i class="fa-solid fa-warehouse"></i><span class="nav-label">Inventory</span></a>

        <div class="nav-section-title">Sales</div>
        <a href="orders.php" title="Orders" class="<?= $active(['orders.php','order-view.php']) ?>"><i class="fa-solid fa-cart-shopping"></i><span class="nav-label">Orders</span></a>
        <?php if ($isSuper): ?>
        <a href="customers.php" title="Customers" class="<?= $active('customers.php') ?>"><i class="fa-solid fa-users"></i><span class="nav-label">Customers</span></a>
        <?php endif; ?>

        <div class="nav-section-title">Content</div>
        <a href="hero_slides.php" title="Hero Slider" class="<?= $active('hero_slides.php') ?>"><i class="fa-solid fa-images"></i><span class="nav-label">Hero Slider</span></a>
        <a href="reviews.php" title="Reviews" class="<?= $active('reviews.php') ?>"><i class="fa-solid fa-star-half-stroke"></i><span class="nav-label">Reviews</span></a>

        <div class="nav-section-title">Shop By Video</div>
        <a href="video-categories.php" title="Video Categories" class="<?= $active('video-categories.php') ?>"><i class="fa-solid fa-icons"></i><span class="nav-label">Video Categories</span></a>
        <a href="shop-videos.php" title="Shop Videos" class="<?= $active('shop-videos.php') ?>"><i class="fa-solid fa-clapperboard"></i><span class="nav-label">Shop Videos</span></a>

        <?php if ($isSuper): ?>
        <div class="nav-section-title">Insights</div>
        <a href="reports.php" title="Reports" class="<?= $active('reports.php') ?>"><i class="fa-solid fa-chart-line"></i><span class="nav-label">Reports</span></a>
        <?php endif; ?>

        <?php if ($isSuper): ?>
        <div class="nav-section-title">Administration</div>
        <a href="employees.php" title="Employees" class="<?= $active('employees.php') ?>"><i class="fa-solid fa-user-tie"></i><span class="nav-label">Employees</span></a>
        <a href="settings.php" title="Settings" class="<?= $active('settings.php') ?>"><i class="fa-solid fa-gear"></i><span class="nav-label">Settings</span></a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-avatar"><?= $initials ?></div>
        <div class="user-meta">
            <div class="name"><?= sanitize($_SESSION['employee_name']) ?></div>
            <div class="role"><?= $_SESSION['employee_role'] === 'super_admin' ? 'Super Admin' : 'Staff' ?></div>
        </div>
        <a href="logout.php" class="logout-link" title="Logout"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
</aside>
