<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json');

if (!isAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Get the latest order ID
    $stmt = $pdo->query("SELECT id, order_number, total, name FROM orders ORDER BY id DESC LIMIT 1");
    $order = $stmt->fetch();

    if ($order) {
        echo json_encode(['success' => true, 'latest_order' => $order]);
    } else {
        echo json_encode(['success' => true, 'latest_order' => null]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
