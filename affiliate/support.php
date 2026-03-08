<?php
require_once __DIR__ . '/../config/pdo_config.php';
require_once __DIR__ . '/../config/affiliate_auth.php';

requireAffiliateLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Support Center - <?= SITE_NAME ?></title>
    <?php include 'includes/head.php'; ?>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h1 style="font-weight: 800; font-size: 2.25rem; letter-spacing: -1px; color: #1e293b;">Support Center</h1>
                <p class="text-secondary mb-0 fw-500">How can we help you succeed today?</p>
            </div>
            <button class="btn d-lg-none" onclick="toggleSidebar()"><i class="bi bi-list fs-2"></i></button>
        </header>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="glass-card mb-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="icon-box mb-0" style="background: rgba(99, 102, 241, 0.1); color: #6366f1; width: 42px; height: 42px; font-size: 1.25rem;">
                            <i class="bi bi-question-circle-fill"></i>
                        </div>
                        <h4 class="fw-800 mb-0">Helpful FAQ</h4>
                    </div>
                    
                    <div class="accordion accordion-flush" id="faqAffiliate">
                        <div class="accordion-item bg-transparent border-bottom">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-transparent fw-700 px-0 py-3 text-main shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#q1">
                                    When do I get my commissions?
                                </button>
                            </h2>
                            <div id="q1" class="accordion-collapse collapse" data-bs-parent="#faqAffiliate">
                                <div class="accordion-body px-0 text-secondary fw-500 small">
                                    Commissions are verified after the order return period (usually 7-14 days). Once verified, you can request a withdrawal if your balance meets the minimum threshold of <?= formatPrice(AFFILIATE_MIN_WITHDRAWAL) ?>.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item bg-transparent border-bottom">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-transparent fw-700 px-0 py-3 text-main shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#q2">
                                    How is the referral tracked?
                                </button>
                            </h2>
                            <div id="q2" class="accordion-collapse collapse" data-bs-parent="#faqAffiliate">
                                <div class="accordion-body px-0 text-secondary fw-500 small">
                                    We use a secure cookie-based tracking system. When a user clicks your link, a cookie is stored for 30 days. Any purchase they making during that time will be credited to your account.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item bg-transparent">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-transparent fw-700 px-0 py-3 text-main shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#q3">
                                    Can I use my own referral link?
                                </button>
                            </h2>
                            <div id="q3" class="accordion-collapse collapse" data-bs-parent="#faqAffiliate">
                                <div class="accordion-body px-0 text-secondary fw-500 small">
                                    No, self-referrals are strictly prohibited. Our system automatically filters those out and repeated attempts can lead to account suspension.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="glass-card">
                    <h5 class="fw-800 mb-4">Quick Resources</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-4 border rounded-4 d-flex align-items-center gap-3 bg-light bg-opacity-50 transition-all hover-shadow">
                                <div class="rounded-3 bg-danger bg-opacity-10 p-2">
                                    <i class="bi bi-file-earmark-pdf-fill fs-3 text-danger"></i>
                                </div>
                                <div>
                                    <div class="fw-800 small text-main">Marketing Kit.pdf</div>
                                    <div class="x-small text-secondary fw-500">Brand shadows & icons</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-4 border rounded-4 d-flex align-items-center gap-3 bg-light bg-opacity-50 transition-all hover-shadow">
                                <div class="rounded-3 bg-primary bg-opacity-10 p-2">
                                    <i class="bi bi-play-circle-fill fs-3 text-primary"></i>
                                </div>
                                <div>
                                    <div class="fw-800 small text-main">Strategy Guide</div>
                                    <div class="x-small text-secondary fw-500">How to maximize earnings</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="glass-card text-center py-5 shadow-lg border-0" style="background: linear-gradient(135deg, #6366f1, #4f46e5); color: white;">
                    <div class="icon-box mx-auto mb-4 shadow-lg" style="background: rgba(255, 255, 255, 0.2); color: white; width: 80px; height: 80px; font-size: 2.5rem;">
                        <i class="bi bi-headset"></i>
                    </div>
                    <h4 class="fw-800 mb-2">Partner Manager</h4>
                    <p class="small text-white-50 mb-4 px-4 fw-500">Dedicated support for our verified partners. We prioritize your success.</p>
                    <a href="mailto:support@luxestore.com" class="btn btn-light rounded-pill px-5 py-3 fw-800 text-primary shadow-lg transition-transform" style="font-size: 0.9rem;">
                        Contact Now <i class="bi bi-arrow-right-short ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS for Accordion -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
