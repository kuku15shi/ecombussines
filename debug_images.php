<?php
require_once __DIR__ . '/config/db.php';
$stmt = $pdo->query('SELECT name, images FROM products LIMIT 10');
while($r = $stmt->fetch()) {
    echo "Product: " . $r['name'] . "\nImages: " . $r['images'] . "\n---\n";
}
