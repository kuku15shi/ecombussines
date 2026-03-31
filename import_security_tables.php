<?php
require_once __DIR__ . '/config/db.php';

$sqlFile = __DIR__ . '/security_tables.sql';

if (!file_exists($sqlFile)) {
    die("Error: security_tables.sql not found at " . $sqlFile . "\n");
}

$sql = file_get_contents($sqlFile);

try {
    // Disable prepared statement emulation just for this multi-query
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    
    // Execute the SQL schema block
    $result = $pdo->exec($sql);
    
    if ($result !== false) {
        echo "SUCCESS: The security_tables.sql file has been imported into the ecombusiness database.\n";
        echo "You can now safely use the admin_secure_panel/index.php and secure_login.php endpoints.\n";
    } else {
        echo "WARNING: PDO->exec() returned false, which might mean no rows were affected (this is normal for CREATE TABLE statements if they already exist).\n";
        $errorInfo = $pdo->errorInfo();
        print_r($errorInfo);
    }
} catch (PDOException $e) {
    echo "ERROR: Failed to import SQL file: " . $e->getMessage() . "\n";
}
?>
