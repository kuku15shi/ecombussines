<?php
require_once __DIR__ . '/config/db.php';
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
header('Content-Type: application/json');
echo json_encode($tables);
?>
