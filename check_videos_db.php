<?php
require_once __DIR__ . '/config/db.php';
$stmt = $pdo->query("SELECT * FROM videos");
echo json_encode($stmt->fetchAll());
?>
