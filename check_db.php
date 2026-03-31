<?php
require_once __DIR__ . '/config/db.php';
if (file_exists(__DIR__ . '/config/pdo_config.php')) {
    require_once __DIR__ . '/config/pdo_config.php';
    
    $check_tables = ['products', 'categories', 'tags', 'synonyms', 'search_logs', 'brands'];
    $schema = [];
    foreach ($check_tables as $table) {
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            $schema[$table] = $cols;
        } catch(Exception $e) {
            $schema[$table] = "Table does not exist";
        }
    }
    echo json_encode($schema, JSON_PRETTY_PRINT);
} else {
    echo "NO PDO";
}
