<?php
require_once __DIR__ . '/admin_bootstrap.php';

// Fetch notifications for the bell dropdown
$notifStmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 8");
$notifications = $notifStmt->fetchAll();
$unreadCount = $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn();

function notifIcon($type) {
    return match ($type) {
        'order' => 'fa-cart-shopping',
        'low_stock' => 'fa-triangle-exclamation',
        'customer' => 'fa-user-plus',
        default => 'fa-bell',
    };
}
function notifTimeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? sanitize($pageTitle) . ' - ' : '' ?>Admin Console</title>
<link rel="icon" type="image/png" href="../assets/img/logo.png">
<script>
// Apply saved theme before first paint to avoid a light/dark flash
(function () {
    var saved = localStorage.getItem('adminTheme');
    if (saved === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
})();
</script>
<style>
/* Prevent a flash of the expanded sidebar on desktop reload when it was left collapsed */
@media (min-width: 993px) {
    html[data-sidebar-collapsed="1"] .admin-sidebar { width: 76px; }
    html[data-sidebar-collapsed="1"] .admin-main { margin-left: 76px; }
}
</style>
<script>
(function () {
    if (window.innerWidth > 992 && localStorage.getItem('adminSidebarCollapsed') === '1') {
        document.documentElement.setAttribute('data-sidebar-collapsed', '1');
    }
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="admin-main" id="adminMain">
<script>
/* Apply the saved collapsed state to the real elements the instant they exist in the
   DOM (instead of waiting for admin.js at the bottom of the page / DOMContentLoaded).
   This is what actually removes the "half-collapsed, broken layout" flash you get when
   you collapse the sidebar, click a nav link, and the next page briefly renders with
   the narrow width from the <head> style but full-size labels/text still showing. */
(function () {
    if (window.innerWidth > 992 && localStorage.getItem('adminSidebarCollapsed') === '1') {
        var sb = document.getElementById('adminSidebar');
        var mn = document.getElementById('adminMain');
        if (sb) sb.classList.add('collapsed');
        if (mn) mn.classList.add('collapsed');
    }
})();
</script>
    <div class="admin-topbar">
        <div style="display:flex;align-items:center;gap:16px;">
            <button class="topbar-toggle-mobile" id="topbarMobileToggle" type="button" title="Open menu" aria-label="Open menu" aria-controls="adminSidebar" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
            <div class="page-heading">
                <div class="crumb">Admin Console</div>
                <h1><?= sanitize($pageTitle ?? 'Dashboard') ?></h1>
            </div>
        </div>
        <div class="topbar-right">
            <a href="../index.php" target="_blank" class="topbar-icon-btn" title="View Store">
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
            </a>
            <button class="theme-toggle" id="themeToggle" title="Toggle dark mode">
                <i class="fa-solid fa-moon icon-moon"></i>
                <i class="fa-solid fa-sun icon-sun"></i>
            </button>
            <div class="notif-wrap">
                <button class="topbar-icon-btn" id="notifBtn" title="Notifications">
                    <i class="fa-regular fa-bell"></i>
                    <?php if ($unreadCount > 0): ?><span class="dot" id="notifDot"></span><?php endif; ?>
                </button>
                <div class="notif-panel" id="notifPanel">
                    <div class="notif-panel-header">
                        <h4>Notifications <?php if ($unreadCount > 0): ?><span id="notifCount">(<?= (int)$unreadCount ?> new)</span><?php endif; ?></h4>
                        <?php if ($unreadCount > 0): ?><button id="notifMarkAll">Mark all read</button><?php endif; ?>
                    </div>
                    <?php if (empty($notifications)): ?>
                        <div class="notif-empty"><i class="fa-regular fa-bell-slash"></i>No notifications yet</div>
                    <?php else: ?>
                        <?php foreach ($notifications as $n): ?>
                        <a href="<?= sanitize($n['link'] ?: '#') ?>" class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>" data-notif-id="<?= (int)$n['id'] ?>">
                            <div class="notif-icon type-<?= sanitize($n['type']) ?>"><i class="fa-solid <?= notifIcon($n['type']) ?>"></i></div>
                            <div class="notif-body">
                                <div class="notif-title"><?= sanitize($n['title']) ?></div>
                                <div class="notif-msg"><?= sanitize($n['message']) ?></div>
                                <div class="notif-time"><?= notifTimeAgo($n['created_at']) ?></div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="topbar-divider"></div>
            <div class="admin-user-wrap">
                <button type="button" class="admin-user" id="adminUserBtn">
                    <div class="avatar"><?= strtoupper(substr($_SESSION['employee_name'], 0, 1)) ?></div>
                    <div class="admin-user-meta">
                        <div class="u-name"><?= sanitize($_SESSION['employee_name']) ?></div>
                        <span class="role-badge"><?= $_SESSION['employee_role'] === 'super_admin' ? 'Super Admin' : 'Staff' ?></span>
                    </div>
                    <i class="fa-solid fa-chevron-down admin-user-caret"></i>
                </button>
                <div class="admin-user-panel" id="adminUserPanel">
                    <div class="admin-user-panel-head">
                        <div class="avatar"><?= strtoupper(substr($_SESSION['employee_name'], 0, 1)) ?></div>
                        <div>
                            <div class="name"><?= sanitize($_SESSION['employee_name']) ?></div>
                            <div class="email"><?= sanitize($_SESSION['employee_email'] ?? '') ?></div>
                        </div>
                    </div>
                    <a href="profile.php" class="admin-user-panel-item"><i class="fa-solid fa-user-gear"></i> Profile Settings</a>
                    <a href="logout.php" class="admin-user-panel-item danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>
    <div class="admin-content">
