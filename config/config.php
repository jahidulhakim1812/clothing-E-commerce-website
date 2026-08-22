<?php
/**
 * Global Configuration
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('BASE_URL', ''); // set to sub-folder path if needed e.g. '/stitchsouls'
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/products/');
define('UPLOAD_URL', BASE_URL . 'assets/uploads/products/');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Load site settings into a global array
$siteSettings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    foreach ($stmt->fetchAll() as $row) {
        $siteSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // settings table may not exist yet on first run
}

function setting($key, $default = '') {
    global $siteSettings;
    return $siteSettings[$key] ?? $default;
}

// ---- Mailer config ----
// NOTE: for production, move these into environment variables (e.g. via
// getenv() + a .env file that is NOT committed to git) instead of hard-coding
// them here. Because this Gmail App Password has already been shared in
// plain text, it's strongly recommended you regenerate a new App Password
// in your Google Account > Security > App Passwords and swap it in below.
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'sumaiyaislam25101@gmail.com');
define('MAIL_PASSWORD', 'prer qxla selj wvqi');
define('MAIL_FROM', 'sumaiyaislam25101@gmail.com');
define('MAIL_FROM_NAME', 'Stitch & Souls');
define('MAIL_USE_SMTP', true); // true = send via Gmail SMTP (PHPMailer); false = falls back to PHP mail()
