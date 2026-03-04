<?php
// Enhanced Head section for Affiliate Dashboard
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
<style>
    :root {
        --primary: #6366f1;
        --primary-dark: #4f46e5;
        --accent: #f59e0b;
        --sidebar-bg: #ffffff;
        --main-bg: #f8fafc;
        --card-bg: #ffffff;
        --text-main: #1e293b;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
    }

    body { 
        font-family: 'Outfit', sans-serif; 
        background-color: var(--main-bg);
        color: var(--text-main);
        overflow-x: hidden;
    }

    .glass-card { 
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .sidebar { 
        position: fixed; 
        top: 0; 
        left: 0; 
        bottom: 0; 
        width: 280px; 
        background: var(--sidebar-bg);
        border-right: 1px solid var(--border-color);
        padding: 2rem; 
        z-index: 1000; 
        transition: all 0.3s ease;
    }

    .main-content { 
        margin-left: 280px; 
        padding: 3rem; 
        min-height: 100vh;
    }

    .nav-link-custom { 
        display: flex; 
        align-items: center; 
        gap: 0.85rem; 
        padding: 0.85rem 1.25rem; 
        border-radius: 12px; 
        color: var(--text-muted); 
        text-decoration: none !important; 
        margin-bottom: 0.5rem; 
        transition: all 0.2s ease; 
        font-weight: 500;
    }

    .nav-link-custom:hover {
        background: #f1f5f9;
        color: var(--primary);
    }

    .nav-link-custom.active { 
        background: var(--primary); 
        color: #fff !important; 
        box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.2);
    }

    .stat-card {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: 1.5rem;
        height: 100%;
        transition: transform 0.2s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .icon-box {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        font-size: 1.25rem;
    }

    .btn-primary-luxury {
        background: var(--primary);
        color: white;
        border: none;
        padding: 0.8rem 2rem;
        border-radius: 12px;
        font-weight: 700;
        transition: all 0.2s ease;
    }

    .btn-primary-luxury:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
    }

    .method-card {
        display: block;
        border: 2.5px solid #f1f5f9;
        padding: 1.5rem 1rem;
        border-radius: 16px;
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: center;
    }

    .method-card i {
        display: block;
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }

    .method-check:checked + .method-card {
        border-color: var(--primary);
        background: rgba(99, 102, 241, 0.05);
        color: var(--primary);
    }

    @media (max-width: 991px) {
        .sidebar { transform: translateX(-100%); }
        .main-content { margin-left: 0; padding: 1.5rem; }
        .sidebar.active { transform: translateX(0); width: 100%; max-width: 300px; }
    }
</style>
