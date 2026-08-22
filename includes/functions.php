<?php
/**
 * Shared Helper Functions
 */

function sanitize($str) {
    return htmlspecialchars(trim($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function isCustomerLoggedIn() {
    return isset($_SESSION['customer_id']);
}

function isEmployeeLoggedIn() {
    return isset($_SESSION['employee_id']);
}

function isSuperAdmin() {
    return isEmployeeLoggedIn() && ($_SESSION['employee_role'] ?? '') === 'super_admin';
}

function requireCustomerLogin() {
    if (!isCustomerLoggedIn()) {
        redirect('login.php');
    }
}

function requireEmployeeLogin() {
    if (!isEmployeeLoggedIn()) {
        redirect('login.php');
    }
}

function requireSuperAdmin() {
    if (!isSuperAdmin()) {
        redirect('dashboard.php?error=access_denied');
    }
}

function formatPrice($price) {
    global $siteSettings;
    $symbol = $siteSettings['currency_symbol'] ?? '৳';
    return $symbol . number_format((float)$price, 2);
}

function generateOrderNumber() {
    return 'ORD' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

function uploadImage($file, $targetDir) {
    if (!isset($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        return false;
    }
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    $newName = uniqid('img_', true) . '.' . $ext;
    $destination = rtrim($targetDir, '/') . '/' . $newName;
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $newName;
    }
    return false;
}

/**
 * Upload a short showcase video (mp4/webm/mov) for a product — used by the
 * "Watch & Shop" video section and the floating product video widget.
 */
function uploadVideo($file, $targetDir) {
    if (!isset($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ['mp4', 'webm', 'mov'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        return false;
    }
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    $newName = uniqid('vid_', true) . '.' . $ext;
    $destination = rtrim($targetDir, '/') . '/' . $newName;
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $newName;
    }
    return false;
}

function flash($key, $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return;
    }
    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

/**
 * Cart helpers - session based, works for guests AND logged-in customers
 */
function getCart() {
    return $_SESSION['cart'] ?? [];
}

function cartCount() {
    $count = 0;
    foreach (getCart() as $item) {
        $count += $item['quantity'];
    }
    return $count;
}

function cartTotal() {
    $total = 0;
    foreach (getCart() as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return $total;
}

function addToCart($productId, $name, $price, $image, $qty = 1, $size = '', $color = '') {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $key = $productId . '_' . $size . '_' . $color;
    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['quantity'] += $qty;
    } else {
        $_SESSION['cart'][$key] = [
            'product_id' => $productId,
            'name'       => $name,
            'price'      => $price,
            'image'      => $image,
            'quantity'   => $qty,
            'size'       => $size,
            'color'      => $color,
        ];
    }
}

function removeFromCart($key) {
    unset($_SESSION['cart'][$key]);
}

function updateCartQty($key, $qty) {
    if (isset($_SESSION['cart'][$key])) {
        if ($qty <= 0) {
            removeFromCart($key);
        } else {
            $_SESSION['cart'][$key]['quantity'] = $qty;
        }
    }
}

function clearCart() {
    $_SESSION['cart'] = [];
}

/**
 * Send order confirmation email to the customer (guest or registered).
 * Uses PHPMailer + SMTP if MAIL_USE_SMTP is true, otherwise falls back
 * to PHP's built-in mail() function.
 */
function sendOrderConfirmationEmail($toEmail, $toName, $order, $items) {
    $subject = "Order Confirmation - " . $order['order_number'];
    $body = buildOrderEmailBody($toName, $order, $items);

    if (defined('MAIL_USE_SMTP') && MAIL_USE_SMTP) {
        return sendMailViaPHPMailer($toEmail, $toName, $subject, $body);
    }

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";

    return @mail($toEmail, $subject, $body, $headers);
}

/**
 * Send a "reset your password" email with a tokenized link.
 * Uses PHPMailer + SMTP if MAIL_USE_SMTP is true, otherwise falls back
 * to PHP's built-in mail() function.
 */
function sendPasswordResetEmail($toEmail, $toName, $resetLink) {
    $subject = "Reset Your Password - " . setting('site_name', 'Stitch & Souls');
    $body = buildPasswordResetEmailBody($toName, $resetLink);

    if (defined('MAIL_USE_SMTP') && MAIL_USE_SMTP) {
        return sendMailViaPHPMailer($toEmail, $toName, $subject, $body);
    }

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";

    return @mail($toEmail, $subject, $body, $headers);
}

function buildPasswordResetEmailBody($toName, $resetLink) {
    $siteName = setting('site_name', 'Stitch & Souls');
    $safeName = htmlspecialchars($toName ?: 'there', ENT_QUOTES, 'UTF-8');
    return "
    <div style='font-family:Poppins,Arial,sans-serif;max-width:520px;margin:0 auto;color:#2E2A28;'>
        <h2 style='color:#D96C5F;margin-bottom:4px;'>" . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . "</h2>
        <p>Hi {$safeName},</p>
        <p>We received a request to reset your password. Click the button below to choose a new one. This link will expire in <strong>1 hour</strong>.</p>
        <p style='text-align:center;margin:28px 0;'>
            <a href='{$resetLink}' style='background:#D96C5F;color:#fff;padding:13px 28px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;'>Reset Password</a>
        </p>
        <p style='font-size:13px;color:#8a8480;'>If the button above doesn't work, copy and paste this link into your browser:<br>{$resetLink}</p>
        <p style='font-size:13px;color:#8a8480;'>If you didn't request a password reset, you can safely ignore this email — your password will remain unchanged.</p>
    </div>";
}

/**
 * Generate a 6-digit numeric one-time verification code.
 */
function generateOtpCode() {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Send a one-time verification code email (used by the admin/staff
 * self-registration wizard — once for the email step, once for the
 * final confirmation step before setting a password).
 */
function sendOtpEmail($toEmail, $code, $stepLabel = 'Verify Your Email') {
    $siteName = setting('site_name', 'Stitch & Souls');
    $subject = "$stepLabel - " . $siteName;
    $body = "
    <div style='font-family:Poppins,Arial,sans-serif;max-width:520px;margin:0 auto;color:#2E2A28;'>
        <h2 style='color:#D96C5F;margin-bottom:4px;'>" . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . "</h2>
        <p>" . htmlspecialchars($stepLabel, ENT_QUOTES, 'UTF-8') . "</p>
        <p>Your verification code is:</p>
        <p style='text-align:center;margin:24px 0;'>
            <span style='display:inline-block;background:#faf5ef;border:1px solid #eee;border-radius:8px;padding:14px 28px;font-size:28px;font-weight:700;letter-spacing:6px;'>{$code}</span>
        </p>
        <p style='font-size:13px;color:#8a8480;'>This code expires in 10 minutes. If you didn't request this, you can safely ignore this email.</p>
    </div>";

    if (defined('MAIL_USE_SMTP') && MAIL_USE_SMTP) {
        return sendMailViaPHPMailer($toEmail, '', $subject, $body);
    }
    $headers  = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    return @mail($toEmail, $subject, $body, $headers);
}

/**
 * Send the final "your account is ready" email once an admin/staff
 * self-registration is complete — sent for BOTH Super Admin and
 * Employee roles.
 */
function sendEmployeeRegistrationCompleteEmail($toEmail, $name, $role) {
    $siteName = setting('site_name', 'Stitch & Souls');
    $roleLabel = $role === 'super_admin' ? 'Super Admin' : 'Employee';
    $subject = "Your $roleLabel Account is Ready - " . $siteName;
    $safeName = htmlspecialchars($name ?: 'there', ENT_QUOTES, 'UTF-8');
    $body = "
    <div style='font-family:Poppins,Arial,sans-serif;max-width:520px;margin:0 auto;color:#2E2A28;'>
        <h2 style='color:#D96C5F;margin-bottom:4px;'>" . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . "</h2>
        <p>Hi {$safeName},</p>
        <p>Your <strong>{$roleLabel}</strong> account has been created and verified successfully. You can now log in from the admin panel using your email and the password you just set.</p>
        <p style='font-size:13px;color:#8a8480;'>If you did not create this account, please contact the site owner immediately.</p>
    </div>";

    if (defined('MAIL_USE_SMTP') && MAIL_USE_SMTP) {
        return sendMailViaPHPMailer($toEmail, $name, $subject, $body);
    }
    $headers  = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    return @mail($toEmail, $subject, $body, $headers);
}

function sendMailViaPHPMailer($toEmail, $toName, $subject, $body) {
    require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
    require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}

function buildOrderEmailBody($toName, $order, $items) {
    $rows = '';
    foreach ($items as $item) {
        $rows .= "<tr>
            <td style='padding:8px;border-bottom:1px solid #eee;'>{$item['product_name']}</td>
            <td style='padding:8px;border-bottom:1px solid #eee;text-align:center;'>{$item['quantity']}</td>
            <td style='padding:8px;border-bottom:1px solid #eee;text-align:right;'>" . number_format($item['price'], 2) . "</td>
            <td style='padding:8px;border-bottom:1px solid #eee;text-align:right;'>" . number_format($item['line_total'], 2) . "</td>
        </tr>";
    }

    return "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;'>
        <div style='background:#2E2A28;padding:20px;text-align:center;'>
            <h2 style='color:#fff;margin:0;'>Stitch &amp; Souls</h2>
            <p style='color:#F0B23D;margin:4px 0 0;font-size:12px;letter-spacing:2px;text-transform:uppercase;'>Handmade With Heart</p>
        </div>
        <div style='padding:20px;background:#fff;'>
            <p>Hi " . htmlspecialchars($toName) . ",</p>
            <p>Thank you for your order! We're happy to confirm that we've received it and it's now being processed.</p>
            <p><strong>Order Number:</strong> {$order['order_number']}<br>
               <strong>Order Date:</strong> " . date('d M Y, h:i A') . "<br>
               <strong>Payment Method:</strong> " . strtoupper($order['payment_method']) . "</p>
            <table style='width:100%;border-collapse:collapse;margin-top:15px;'>
                <thead>
                    <tr style='background:#f5f5f5;'>
                        <th style='padding:8px;text-align:left;'>Item</th>
                        <th style='padding:8px;'>Qty</th>
                        <th style='padding:8px;text-align:right;'>Price</th>
                        <th style='padding:8px;text-align:right;'>Total</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
            <p style='text-align:right;margin-top:10px;'>
                Subtotal: " . number_format($order['subtotal'], 2) . "<br>
                Shipping: " . number_format($order['shipping_fee'], 2) . "<br>
                <strong style='font-size:16px;'>Total: " . number_format($order['total_amount'], 2) . "</strong>
            </p>
            <p><strong>Shipping Address:</strong><br>{$order['shipping_address']}</p>
            <p>You can track your order anytime using your Order Number and Email on our Track Order page.</p>
            <p style='margin-top:25px;'>Thank you for shopping with us!<br>— The Stitch &amp; Souls Team</p>
        </div>
    </div>";
}

/**
 * Create an admin notification (shown in the admin panel bell dropdown).
 * $type: 'order' | 'low_stock' | 'customer' | 'system'
 */
function createNotification($type, $title, $message, $link = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (type, title, message, link) VALUES (?,?,?,?)");
        $stmt->execute([$type, $title, $message, $link]);
    } catch (Exception $e) {
        // Non-critical — never let a notification failure break the main flow
        error_log('createNotification failed: ' . $e->getMessage());
    }
}
