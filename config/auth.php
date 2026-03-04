<?php
require_once __DIR__ . '/db.php';

function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireUserLogin() {
    if (!isUserLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: ' . SITE_URL . '/admin/login.php');
        exit;
    }
}

function getCurrentUser($db = null) {
    if (!$db instanceof PDO) { global $pdo; $db = $pdo; }
    if (!isUserLoggedIn()) return null;
    $id = (int)$_SESSION['user_id'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND is_blocked = 0");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getCartCount($db = null) {
    // Note: $db not strictly needed here as we use session, but kept for interface consistency
    if (!isUserLoggedIn()) return isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
    return isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'qty')) : 0;
}

function getWishlistCount($db = null) {
    if (!$db instanceof PDO) { global $pdo; $db = $pdo; }
    if (!isUserLoggedIn()) return 0;
    $uid = (int)$_SESSION['user_id'];
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM wishlist WHERE user_id = ?");
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    return $row['cnt'] ?? 0;
}

function handleMockGoogleAuth($conn, $type = 'user') {
    if (isset($_GET['auth']) && $_GET['auth'] === 'google_mock') {
        if ($type === 'admin') {
            $res = $conn->query("SELECT * FROM admin LIMIT 1");
            if ($row = $res->fetch_assoc()) {
                $_SESSION['admin_id'] = $row['id'];
                $_SESSION['admin_name'] = $row['name'];
                header('Location: index.php'); exit;
            }
        } else {
            $email = 'user@google.com';
            $res = $conn->query("SELECT * FROM users WHERE email='$email' LIMIT 1");
            if ($row = $res->fetch_assoc()) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_name'] = $row['name'];
            } else {
                $conn->query("INSERT INTO users (name,email,password) VALUES ('Google User', '$email', 'GOOGLE_MOCK')");
                $_SESSION['user_id'] = $conn->insert_id;
                $_SESSION['user_name'] = 'Google User';
            }
            header('Location: index.php'); exit;
        }
    }
}
