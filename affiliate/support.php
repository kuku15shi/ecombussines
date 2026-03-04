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
                <h1 style="font-weight: 800; font-size: 2rem;">Affiliate Support</h1>
                <p class="text-secondary mb-0">How can we help you succeed today?</p>
            </div>
            <button class="btn d-lg-none" onclick="toggleSidebar()"><i class="bi bi-list fs-2"></i></button>
        </header>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="glass-card mb-4">
                    <h4 class="fw-800 mb-4">Frequently Asked Questions</h4>
                    
                    <div class="accordion accordion-flush" id="faqAffiliate">
                        <div class="accordion-item bg-transparent">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-transparent fw-700 px-0 py-3" type="button" data-bs-toggle="collapse" data-bs-target="#q1">
                                    When do I get my commissions?
                                </button>
                            </h2>
                            <div id="q1" class="accordion-collapse collapse" data-bs-parent="#faqAffiliate">
                                <div class="accordion-body px-0 text-secondary">
                                    Commissions are verified after the order return period (usually 7-14 days). Once verified, you can request a withdrawal if your balance meets the minimum threshold.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item bg-transparent">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-transparent fw-700 px-0 py-3" type="button" data-bs-toggle="collapse" data-bs-target="#q2">
                                    How is the referral tracked?
                                </button>
                            </h2>
                            <div id="q2" class="accordion-collapse collapse" data-bs-parent="#faqAffiliate">
                                <div class="accordion-body px-0 text-secondary">
                                    We use a secure cookie-based tracking system. When a user clicks your link, a cookie is stored for 30 days. Any purchase they making during that time will be credited to you.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item bg-transparent">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-transparent fw-700 px-0 py-3" type="button" data-bs-toggle="collapse" data-bs-target="#q3">
                                    Can I use my own referral link?
                                </button>
                            </h2>
                            <div id="q3" class="accordion-collapse collapse" data-bs-parent="#faqAffiliate">
                                <div class="accordion-body px-0 text-secondary">
                                    No, self-referrals are not allowed and will be automatically filtered out by our system to maintain program integrity.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="glass-card">
                    <h5 class="fw-800 mb-4">Quick Resources</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 border rounded-4 d-flex align-items-center gap-3">
                                <i class="bi bi-file-earmark-pdf fs-3 text-danger"></i>
                                <div>
                                    <div class="fw-700 small">Marketing Kit.pdf</div>
                                    <div class="x-small text-secondary">Brand assets & guidelines</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 border rounded-4 d-flex align-items-center gap-3">
                                <i class="bi bi-play-circle-fill fs-3 text-primary"></i>
                                <div>
                                    <div class="fw-700 small">Video Tutorial</div>
                                    <div class="x-small text-secondary">How to maximize earnings</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="glass-card text-center py-5" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border: none;">
                    <i class="bi bi-headset mb-4 d-block" style="font-size: 3.5rem;"></i>
                    <h4 class="fw-800 mb-3">Priority Support</h4>
                    <p class="small text-white-50 mb-4 px-3">Dedicated support for our approved partners. We usually respond within 24 hours.</p>
                    <a href="mailto:support@luxestore.com" class="btn btn-light rounded-4 px-4 py-2 fw-700 text-primary">Contact Manager</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS for Accordion -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
