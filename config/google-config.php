<?php
// Google API Configuration
// Load site configuration for SITE_URL
if (!defined('SITE_URL')) {
    require_once __DIR__ . '/db.php';
}

define('GOOGLE_CLIENT_ID', GOOGLE_CLIENT_ID_VAL);
define('GOOGLE_CLIENT_SECRET', GOOGLE_CLIENT_SECRET_VAL);

define('GOOGLE_REDIRECT_URL', SITE_URL . '/google-callback.php');

// Google OAuth Endpoints
define('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL', 'https://www.googleapis.com/oauth2/v3/userinfo');
