<?php
/**
 * AJAX Cart Endpoint — powers the slide-out cart drawer.
 * Returns JSON: { success, count, subtotal_formatted, items_html }
 */
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            $productId = (int)($_POST['product_id'] ?? 0);
            $qty = max(1, (int)($_POST['quantity'] ?? 1));
            $size = sanitize($_POST['size'] ?? '');
            $color = sanitize($_POST['color'] ?? '');

            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status='active'");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Product not found.']);
                exit;
            }
            if ($product['stock'] <= 0) {
                echo json_encode(['success' => false, 'message' => 'Sorry, this product is out of stock.']);
                exit;
            }
            $price = (!empty($product['discount_price']) && $product['discount_price'] < $product['price'])
                ? $product['discount_price'] : $product['price'];
            addToCart($product['id'], $product['name'], $price, $product['image'], $qty, $size, $color);
            break;

        case 'update':
            $key = $_POST['key'] ?? '';
            $qty = (int)($_POST['quantity'] ?? 1);
            updateCartQty($key, $qty);
            break;

        case 'remove':
            $key = $_POST['key'] ?? '';
            removeFromCart($key);
            break;

        case 'get':
            // no-op, just returns current state below
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
            exit;
    }

    $cart = getCart();
    ob_start();
    include __DIR__ . '/../includes/cart-drawer-items.php';
    $itemsHtml = ob_get_clean();

    echo json_encode([
        'success'            => true,
        'count'              => cartCount(),
        'subtotal_formatted' => formatPrice(cartTotal()),
        'items_html'         => $itemsHtml,
        'empty'              => empty($cart),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}
