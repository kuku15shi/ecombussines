<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
if (!$email) { echo json_encode(['success'=>false,'message'=>'Invalid email address']); exit; }

$stmt = $pdo->prepare("SELECT id FROM newsletter WHERE email=?");
$stmt->execute([$email]);
if ($stmt->fetch()) { echo json_encode(['success'=>false,'message'=>'You are already subscribed!']); exit; }

$pdo->prepare("INSERT INTO newsletter (email) VALUES (?)")->execute([$email]);
echo json_encode(['success'=>true,'message'=>'Thank you for subscribing! 🎉']);
