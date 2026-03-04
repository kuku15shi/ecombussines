<?php
require_once __DIR__ . '/db.php';

function isAffiliateLoggedIn() {
    return isset($_SESSION['affiliate_id']) && !empty($_SESSION['affiliate_id']);
}

function requireAffiliateLogin() {
    if (!isAffiliateLoggedIn()) {
        header('Location: ' . SITE_URL . '/affiliate/login.php');
        exit;
    }
}

function getCurrentAffiliate($conn) {
    if (!isAffiliateLoggedIn()) return null;
    $id = (int)$_SESSION['affiliate_id'];
    $res = $conn->query("SELECT * FROM affiliates WHERE id = $id");
    return $res ? $res->fetch_assoc() : null;
}

function generateAffiliateReferralCode($conn, $name) {
    $base = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $name));
    $base = substr($base, 0, 5);
    $code = $base . rand(100, 999);
    
    // Ensure uniqueness
    $check = $conn->query("SELECT id FROM affiliates WHERE referral_code = '$code'");
    while ($check && $check->num_rows > 0) {
        $code = $base . rand(1000, 9999);
        $check = $conn->query("SELECT id FROM affiliates WHERE referral_code = '$code'");
    }
    return $code;
}
