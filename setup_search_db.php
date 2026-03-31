<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/pdo_config.php';

try {
    // 1. Create synonyms table
    $pdo->exec("CREATE TABLE IF NOT EXISTS synonyms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        keyword VARCHAR(100) NOT NULL UNIQUE,
        synonyms TEXT NOT NULL
    )");

    // 2. Create search_logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS search_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        keyword VARCHAR(100) NOT NULL,
        results_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. Create brands table
    $pdo->exec("CREATE TABLE IF NOT EXISTS brands (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        slug VARCHAR(150),
        is_active BOOLEAN DEFAULT 1
    )");

    // 4. Alter products table
    $columns = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('brand_id', $columns)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN brand_id INT NULL");
    }
    if (!in_array('tags', $columns)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN tags TEXT NULL");
    }
    if (!in_array('views', $columns)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN views INT DEFAULT 0");
    }
    if (!in_array('sales_count', $columns)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN sales_count INT DEFAULT 0");
    }
    if (!in_array('boost_score', $columns)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN boost_score INT DEFAULT 0");
    }

    // Try adding index if not exists (using try catch as index might exist)
    try {
        $pdo->exec("ALTER TABLE products ADD FULLTEXT INDEX ft_search (name, tags)");
    } catch(Exception $e) {}

    echo "Success";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
