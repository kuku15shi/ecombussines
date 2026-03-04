<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/google-config.php';
require_once __DIR__ . '/config/auth.php';

if (!isset($_GET['code'])) {
    header('Location: login.php?error=auth_failed');
    exit;
}

// Exchange code for access token
$state = $_GET['state'] ?? '';
$isAdminFlow = (strpos($state, 'admin_') === 0);
$currentRedirectUri = GOOGLE_REDIRECT_URL; // Always the base URL now

$postData = [
    'code' => $_GET['code'],
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => $currentRedirectUri,
    'grant_type' => 'authorization_code',
];

$ch = curl_init(GOOGLE_TOKEN_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
$response = curl_exec($ch);
$tokenData = json_decode($response, true);
curl_close($ch);

if (isset($tokenData['access_token'])) {
    // Get user info
    $ch = curl_init(GOOGLE_USERINFO_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $tokenData['access_token']
    ]);
    $userResponse = curl_exec($ch);
    $userInfo = json_decode($userResponse, true);
    curl_close($ch);

    if (isset($userInfo['sub'])) {
        $google_id = $userInfo['sub'];
        $email = $conn->real_escape_string($userInfo['email']);
        $name = $conn->real_escape_string($userInfo['name']);
        $avatar = $userInfo['picture'] ?? 'default_user_avatar.png';

        if ($isAdminFlow) {
            // ... (keep admin flow as is)
            $res = $conn->query("SELECT * FROM admin WHERE email = '$email'");
            if ($res && $res->num_rows > 0) {
                $admin = $res->fetch_assoc();
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                header('Location: admin/index.php');
                exit;
            } else {
                header('Location: admin/login.php?error=not_admin');
                exit;
            }
        }

        if (strpos($state, 'affiliate_') === 0) {
            // Affiliate Flow
            $res = $conn->query("SELECT * FROM affiliates WHERE google_id = '$google_id' OR email = '$email'");
            if ($res && $res->num_rows > 0) {
                $affiliate = $res->fetch_assoc();
                if (empty($affiliate['google_id'])) {
                    $conn->query("UPDATE affiliates SET google_id = '$google_id', avatar = '$avatar' WHERE id = " . $affiliate['id']);
                }
                
                if ($affiliate['status'] === 'approved') {
                    $_SESSION['affiliate_id'] = $affiliate['id'];
                    $_SESSION['affiliate_name'] = $affiliate['name'];
                    header('Location: affiliate/dashboard.php');
                } else {
                    header('Location: affiliate/login.php?error=pending_approval');
                }
                exit;
            } else {
                // Register new affiliate via Google
                require_once __DIR__ . '/config/AffiliateClass.php';
                require_once __DIR__ . '/config/pdo_config.php';
                $affSystem = new AffiliateSystem($pdo);
                $refCode = $affSystem->register([
                    'name' => $name,
                    'email' => $email,
                    'password' => bin2hex(random_bytes(8)), // Placeholder
                    'phone' => ''
                ]);
                
                // Update with google_id
                $conn->query("UPDATE affiliates SET google_id = '$google_id', avatar = '$avatar' WHERE email = '$email'");
                
                header('Location: affiliate/login.php?success=google_registered');
                exit;
            }
        }

        // Check if user exists (Standard User)
        $res = $conn->query("SELECT * FROM users WHERE google_id = '$google_id' OR email = '$email'");
        
        if ($res && $res->num_rows > 0) {
            $user = $res->fetch_assoc();
            // Update google_id if it was just an email match before
            if (empty($user['google_id'])) {
                $conn->query("UPDATE users SET google_id = '$google_id', avatar = '$avatar' WHERE id = " . $user['id']);
            }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
        } else {
            // Register new user
            $conn->query("INSERT INTO users (name, email, google_id, avatar, password) VALUES ('$name', '$email', '$google_id', '$avatar', 'GOOGLE_OAUTH')");
            $_SESSION['user_id'] = $conn->insert_id;
            $_SESSION['user_name'] = $name;
        }

        header('Location: index.php');
        exit;
    }
}

header('Location: login.php?error=google_auth_failed');
exit;
