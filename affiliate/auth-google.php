<?php
require_once __DIR__ . '/../config/google-config.php';
require_once __DIR__ . '/../config/pdo_config.php';

$google_client_id = GOOGLE_CLIENT_ID;
$google_redirect_url = GOOGLE_REDIRECT_URL;

$params = [
    'response_type' => 'code',
    'client_id' => $google_client_id,
    'redirect_uri' => $google_redirect_url,
    'scope' => 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email',
    'state' => 'affiliate_' . bin2hex(random_bytes(16)), // Identify affiliate flow
    'access_type' => 'offline',
    'prompt' => 'select_account'
];

$auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
header('Location: ' . $auth_url);
exit;
