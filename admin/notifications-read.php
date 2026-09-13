<?php
/**
 * AJAX endpoint: mark one or all notifications as read.
 * Returns JSON. Requires an active employee session.
 */
require_once __DIR__ . '/includes/admin_bootstrap.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if ($action === 'mark_all') {
    $pdo->exec("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'mark_one') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->execute([$id]);
    }
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
