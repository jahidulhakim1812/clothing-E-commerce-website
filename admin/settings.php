<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
requireSuperAdmin();
$pageTitle = 'Site Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'site_name', 'site_email', 'site_phone', 'site_address',
        'currency_symbol', 'shipping_fee_inside_dhaka', 'shipping_fee_outside_dhaka',
        'facebook_link', 'instagram_link'
    ];
    foreach ($fields as $field) {
        $value = sanitize($_POST[$field] ?? '');
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$field, $value, $value]);
    }
    flash('success', 'Settings updated successfully.');
    redirect('settings.php');
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>

<div class="panel" style="max-width:700px;">
    <div class="panel-header"><h3>General Settings</h3></div>
    <form method="post" action="settings.php">
        <div class="form-group">
            <label>Site Name</label>
            <input type="text" name="site_name" value="<?= sanitize(setting('site_name')) ?>">
        </div>
        <div class="form-row-2">
            <div class="form-group">
                <label>Site Email</label>
                <input type="email" name="site_email" value="<?= sanitize(setting('site_email')) ?>">
            </div>
            <div class="form-group">
                <label>Site Phone</label>
                <input type="text" name="site_phone" value="<?= sanitize(setting('site_phone')) ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Site Address</label>
            <input type="text" name="site_address" value="<?= sanitize(setting('site_address')) ?>">
        </div>
        <div class="form-row-2">
            <div class="form-group">
                <label>Currency Symbol</label>
                <input type="text" name="currency_symbol" value="<?= sanitize(setting('currency_symbol')) ?>">
            </div>
        </div>
        <div class="form-row-2">
            <div class="form-group">
                <label>Shipping Fee (Inside Dhaka)</label>
                <input type="number" step="0.01" name="shipping_fee_inside_dhaka" value="<?= sanitize(setting('shipping_fee_inside_dhaka')) ?>">
            </div>
            <div class="form-group">
                <label>Shipping Fee (Outside Dhaka)</label>
                <input type="number" step="0.01" name="shipping_fee_outside_dhaka" value="<?= sanitize(setting('shipping_fee_outside_dhaka')) ?>">
            </div>
        </div>
        <div class="form-row-2">
            <div class="form-group">
                <label>Facebook Link</label>
                <input type="text" name="facebook_link" value="<?= sanitize(setting('facebook_link')) ?>">
            </div>
            <div class="form-group">
                <label>Instagram Link</label>
                <input type="text" name="instagram_link" value="<?= sanitize(setting('instagram_link')) ?>">
            </div>
        </div>
        <button type="submit" class="btn">Save Settings</button>
    </form>
</div>

<div class="panel" style="max-width:700px;">
    <div class="panel-header"><h3>Email (SMTP) Configuration</h3></div>
    <p style="font-size:14px;color:var(--a-text-light);">
        To enable real order-confirmation emails via SMTP (e.g. Gmail), edit
        <code>config/config.php</code> and set <code>MAIL_USE_SMTP</code> to <code>true</code>,
        then fill in <code>MAIL_HOST</code>, <code>MAIL_USERNAME</code>, and <code>MAIL_PASSWORD</code>
        (use a Gmail App Password, not your normal password). Until then, the system will attempt
        to use PHP's built-in <code>mail()</code> function.
    </p>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
