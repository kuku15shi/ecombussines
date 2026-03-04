<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/google-config.php';

$params = [
    'response_type' => 'code',
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URL, // Use the clean base URL
    'scope' => 'openid email profile',
    'state' => 'admin_' . bin2hex(random_bytes(16)), // Prefix state to identify admin
    'prompt' => 'select_account'
];

$_SESSION['oauth2state'] = $params['state'];

$authUrl = GOOGLE_AUTH_URL . '?' . http_build_query($params);
header('Location: ' . $authUrl);
exit;
