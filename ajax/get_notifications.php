<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    // Get the latest notification
    $stmt = $pdo->query("SELECT * FROM site_notifications ORDER BY id DESC LIMIT 1");
    $notif = $stmt->fetch();

    if ($notif) {
        echo json_encode(['success' => true, 'notification' => $notif]);
    } else {
        echo json_encode(['success' => true, 'notification' => null]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
