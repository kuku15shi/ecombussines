<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once 'config/db.php';
    echo "DB Connected\n";
    $stmt = $pdo->query("SELECT * FROM whatsapp_logs ORDER BY created_at DESC LIMIT 5");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($logs as $log) {
        echo "Time: " . $log['created_at'] . "\n";
        echo "Phone: " . $log['phone'] . "\n";
        echo "Status: " . $log['status'] . "\n";
        echo "Error: " . $log['error_message'] . "\n";
        echo "Response: " . $log['api_response'] . "\n";
        echo "-----------------------------------\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    // Try to list tables
    try {
        $conn = new mysqli(DB_HOST_VAL, DB_USER_VAL, DB_PASSWORD_VAL, DB_NAME_VAL);
        echo "MySQLi Connected\n";
        $res = $conn->query("SELECT * FROM whatsapp_logs ORDER BY created_at DESC LIMIT 5");
        while($log = $res->fetch_assoc()) {
            echo "Time: " . $log['created_at'] . "\n";
            echo "Phone: " . $log['phone'] . "\n";
            echo "Status: " . $log['status'] . "\n";
            echo "Error: " . $log['error_message'] . "\n";
            echo "Response: " . $log['api_response'] . "\n";
            echo "-----------------------------------\n";
        }
    } catch (Exception $e2) {
        echo "MySQLi Error: " . $e2->getMessage() . "\n";
    }
}
