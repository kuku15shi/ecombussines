<?php
try {
    require_once 'config/config.php';
    $pdo = new PDO("mysql:host=" . DB_HOST_VAL . ";dbname=" . DB_NAME_VAL, DB_USER_VAL, DB_PASSWORD_VAL);
    $stmt = $pdo->query("SELECT DISTINCT phone FROM whatsapp_messages ORDER BY id DESC LIMIT 5");
    $phones = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Last 5 Phones: " . implode(', ', $phones) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
