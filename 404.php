<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        .error-page {
            height: 80vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .error-code {
            font-size: 8rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary), var(--accent2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container">
        <div class="error-page">
            <h1 class="error-code">404</h1>
            <h2 style="font-weight: 800; margin-bottom: 1rem;">Oops! Page Not Found</h2>
            <p style="color: var(--text-muted); margin-bottom: 2rem; max-width: 500px;">
                The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.
            </p>
            <a href="<?= SITE_URL ?>/index" class="btn-primary-luxury">Back to Home</a>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/bottom_nav.php'; ?>
</body>
</html>
