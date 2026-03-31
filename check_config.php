<?php
try {
    require_once 'config/config.php';
    $pdo = new PDO("mysql:host=" . DB_HOST_VAL . ";dbname=" . DB_NAME_VAL, DB_USER_VAL, DB_PASSWORD_VAL);
    $stmt = $pdo->query("SELECT * FROM whatsapp_config");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "--- Config From DB ---\n";
    print_r($configs);
    echo "\n--- Config From Constants ---\n";
    echo "WA_ACCESS_TOKEN: " . substr(WA_ACCESS_TOKEN_VAL, 0, 10) . "...\n";
    echo "WA_PHONE_NUMBER_ID: 983614081506207\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
