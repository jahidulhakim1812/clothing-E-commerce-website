<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
requireSuperAdmin();
$pageTitle = 'Site Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'site_name', 'site_email', 'site_phone', 'site_address',
        'currency_symbol', 'shipping_fee_inside_dhaka', 'shipping_fee_outside_dhaka',
        'facebook_link', 'instagram_link', 'whatsapp_number', 'whatsapp_message'
    ];
    foreach ($fields as $field) {
        $value = trim((string)($_POST[$field] ?? ''));
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$field, $value, $value]);
    }
    // Checkbox: only present in POST when checked
    $showWa = isset($_POST['show_whatsapp_button']) ? '1' : '0';
    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('show_whatsapp_button', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$showWa, $showWa]);

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
        <div class="form-row-2">
            <div class="form-group">
                <label>WhatsApp Number <span style="font-weight:400;color:var(--a-text-light);">(with country code, no + or spaces — e.g. 8801XXXXXXXXX)</span></label>
                <input type="text" name="whatsapp_number" placeholder="8801XXXXXXXXX" value="<?= sanitize(setting('whatsapp_number')) ?>">
            </div>
            <div class="form-group">
                <label>WhatsApp Default Message <span style="font-weight:400;color:var(--a-text-light);">(optional)</span></label>
                <input type="text" name="whatsapp_message" placeholder="Hi, I'd like to know more about..." value="<?= sanitize(setting('whatsapp_message')) ?>">
            </div>
        </div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="show_whatsapp_button" value="1" style="width:auto;" <?= setting('show_whatsapp_button') === '1' ? 'checked' : '' ?>>
                Show floating WhatsApp chat button on the storefront
            </label>
        </div>
        <button type="submit" class="btn">Save Settings</button>
    </form>
</div>



<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
