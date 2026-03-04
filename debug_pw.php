<?php
require_once __DIR__ . '/config/db.php';
$stmt = $pdo->query('SELECT password FROM admin LIMIT 1');
echo $stmt->fetchColumn();
