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
        --text-main: #1e293b !important; /* Force dark text for affiliate portal */
        --text-muted: #64748b;
        --border-color: #e2e8f0;
    }

    /* Override any global dark theme defaults from style.css */
    html {
        background-color: var(--main-bg) !important;
    }

    body { 
        font-family: 'Outfit', sans-serif; 
        background-color: var(--main-bg) !important;
        color: var(--text-main) !important;
        overflow-x: hidden;
    }

    .glass-card { 
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
    }
    
    .glass-card:hover {
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
    }

    .sidebar { 
        position: fixed; 
        top: 0; 
        left: 0; 
        bottom: 0; 
        width: 280px; 
        background: var(--sidebar-bg);
        border-right: 1px solid var(--border-color);
        padding: 2.5rem 1.5rem; 
        z-index: 1000; 
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .main-content { 
        margin-left: 280px; 
        padding: 3rem; 
        min-height: 100vh;
        transition: all 0.3s ease;
    }

    .nav-link-custom { 
        display: flex; 
        align-items: center; 
        gap: 0.85rem; 
        padding: 0.85rem 1.25rem; 
        border-radius: 12px; 
        color: var(--text-muted); 
        text-decoration: none !important; 
        margin-bottom: 0.4rem; 
        transition: all 0.2s ease; 
        font-weight: 600;
        font-size: 0.95rem;
    }

    .nav-link-custom:hover {
        background: #f1f5f9;
        color: var(--primary);
        transform: translateX(4px);
    }

    .nav-link-custom.active { 
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: #fff !important; 
        box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);
    }

    .stat-card {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 24px;
        padding: 1.75rem;
        height: 100%;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
    }

    .stat-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);
        border-color: var(--primary);
    }

    .icon-box {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.25rem;
        font-size: 1.5rem;
        transition: all 0.3s;
    }
    
    .stat-card:hover .icon-box {
        transform: scale(1.1) rotate(5deg);
    }

    .btn-primary-luxury {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white !important;
        border: none;
        padding: 0.8rem 2rem;
        border-radius: 12px;
        font-weight: 700;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        transition: all 0.3s ease;
    }

    .btn-primary-luxury:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(99, 102, 241, 0.3);
        filter: brightness(1.1);
    }

    .badge {
        padding: 0.5rem 1rem !important;
        font-weight: 600 !important;
    }

    .table thead th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 700;
        color: var(--text-muted);
        border: none;
        padding: 1.5rem 1rem;
    }

    @media (max-width: 991px) {
        .sidebar { transform: translateX(-100%); width: 280px; }
        .main-content { margin-left: 0; padding: 1.5rem; }
        .sidebar.active { transform: translateX(0); }
        .overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        .sidebar.active + .overlay { display: block; }
    }
</style>
<div class="overlay" onclick="toggleSidebar()"></div>

