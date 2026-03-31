<?php
require_once __DIR__ . '/config/db.php';
$stmt = $pdo->query("DESC BANNERS");
echo json_encode($stmt->fetchAll());
?>
