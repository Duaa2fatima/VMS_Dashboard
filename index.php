<?php
/**
 * Public Landing Page - Child Vaccination Management System (VaxCare)
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

$isLoggedIn = isAdminLoggedIn();
$currentAdmin = $isLoggedIn ? getCurrentAdmin() : null;
$isParent = isParentLoggedIn();
$currentParent = $isParent ? getCurrentParent() : null;
$isHospital = isHospitalLoggedIn();
$currentHospital = $isHospital ? getCurrentHospital() : null;

// Data for Landing Page
$featuredHospitals = [];

if ($pdo) {
    try {
        // Sample partner hospitals
        $stmtHosp = $pdo->query("SELECT * FROM hospitals WHERE status = 'Active' ORDER BY hospital_id ASC LIMIT 4");
        $featuredHospitals = $stmtHosp->fetchAll();
    } catch (Exception $e) {
        // Continue with default fallbacks if database not yet initialized
    }           
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>VaxCare &bull; Child Vaccination & Immunization Management System</title>
    <link rel="icon" href="<?= BASE_URL ?>assets/img/kaiadmin/favicon.ico" type="image/x-icon" />

    <!-- Google Fonts & Stylesheets -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />

    <style>
        :root,
        [data-theme="dark"] {
            color-scheme: dark;
            --bg-body: #0b1329;
            --bg-card: #111c3a;
            --bg-card-alt: #162447;
            --border-color: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --primary-color: #3b82f6;
            --primary-hover: #2563eb;
            --primary-light: rgba(59, 130, 246, 0.15);
            --nav-bg: rgba(17, 28, 58, 0.92);
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
            --card-hover-shadow: 0 20px 35px -5px rgba(0, 0, 0, 0.65);
        }

        body {
            font-family: 'Public Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Typography */
        h1, h2, h3, h4, h5, h6 {
            color: var(--text-main);
            font-weight: 700;
        }

        .text-muted {
            color: var(--text-muted) !important;
        }

        /* Sticky Glass Navbar */
        .landing-navbar {
            position: sticky;
            top: 0;
            z-index: 1050;
            background: var(--nav-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .brand-logo {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-main);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-logo:hover {
            color: var(--primary-color);
        }

        .nav-link {
            color: var(--text-muted);
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            transition: color 0.2s ease;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary-color);
        }

        /* Hero Section */
        .hero-section {
            padding: 90px 0 70px;
            position: relative;
            background: radial-gradient(circle at 10% 20%, rgba(21, 114, 232, 0.08) 0%, transparent 40%),
                        radial-gradient(circle at 90% 80%, rgba(56, 189, 248, 0.07) 0%, transparent 40%);
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            background: var(--primary-light);
            color: var(--primary-color);
            border: 1px solid rgba(21, 114, 232, 0.2);
            margin-bottom: 1.5rem;
        }

        .hero-title {
            font-size: 3.2rem;
            font-weight: 800;
            line-height: 1.18;
            letter-spacing: -0.5px;
            margin-bottom: 1.5rem;
        }

        .hero-title .text-gradient {
            background: linear-gradient(135deg, #1572e8 0%, #06b6d4 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-desc {
            font-size: 1.15rem;
            color: var(--text-muted);
            margin-bottom: 2.2rem;
            max-width: 680px;
        }

        /* Custom Buttons */
        .btn-round {
            border-radius: 50px;
            padding: 0.6rem 1.6rem;
            font-weight: 600;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-round:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(21, 114, 232, 0.35);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }

        .btn-outline-custom {
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-main);
        }

        .btn-outline-custom:hover {
            background: var(--bg-card-alt);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        /* Hero Preview Card / Mockup */
        .hero-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            padding: 28px;
            position: relative;
            transition: all 0.3s ease;
        }

        .hero-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--card-hover-shadow);
        }

        .floating-badge {
            position: absolute;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
            border-radius: 12px;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            z-index: 2;
        }

        .floating-badge-1 {
            top: -20px;
            right: -15px;
            animation: floatSlow 4s ease-in-out infinite alternate;
        }

        .floating-badge-2 {
            bottom: -20px;
            left: -15px;
            animation: floatSlow 5s ease-in-out infinite alternate-reverse;
        }

        @keyframes floatSlow {
            0% { transform: translateY(0); }
            100% { transform: translateY(-8px); }
        }

        /* Stats Section */
        .stats-section {
            padding: 40px 0;
            background: var(--bg-card);
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .stat-box {
            text-align: center;
            padding: 20px;
        }

        .stat-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 12px;
            background: var(--primary-light);
            color: var(--primary-color);
            transition: transform 0.3s ease;
        }

        .stat-box:hover .stat-icon {
            transform: scale(1.15) rotate(5deg);
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 4px;
            letter-spacing: -0.5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Feature Cards */
        .feature-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 30px;
            height: 100%;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: var(--card-shadow);
        }

        .feature-card:hover {
            transform: translateY(-8px);
            border-color: var(--primary-color);
            box-shadow: var(--card-hover-shadow);
        }

        .feature-icon-box {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 22px;
            background: var(--primary-light);
            color: var(--primary-color);
        }

        /* Vaccine & Hospital Cards */
        .item-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            height: 100%;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }

        .item-card:hover {
            transform: translateY(-6px);
            border-color: var(--primary-color);
            box-shadow: var(--card-hover-shadow);
        }

        .badge-available {
            background-color: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
            font-weight: 600;
        }

        .badge-unavailable {
            background-color: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            font-weight: 600;
        }

        /* Step Process */
        .step-number {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--primary-color);
            color: #ffffff;
            font-weight: 700;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 18px;
        }

        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, #1572e8 0%, #1e293b 100%);
            color: #ffffff;
            border-radius: 24px;
            padding: 60px 40px;
            box-shadow: 0 20px 40px rgba(21, 114, 232, 0.25);
            margin: 70px 0;
        }

        .cta-section h2 {
            color: #ffffff !important;
        }

        /* Footer */
        .landing-footer {
            background: var(--bg-card);
            border-top: 1px solid var(--border-color);
            padding: 60px 0 30px;
            color: var(--text-muted);
        }

        .footer-link {
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s ease;
            display: block;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .footer-link:hover {
            color: var(--primary-color);
        }

        @media (max-width: 991px) {
            .hero-title {
                font-size: 2.4rem;
            }
            .floating-badge {
                display: none;
            }
            .hero-card {
                margin-top: 40px;
            }
        }
    </style>
</head>
<body data-theme="dark" data-background-color="dark">

    <!-- Logged in Banner (Only shown if currently authenticated) -->
    <?php if ($isLoggedIn): ?>
        <div class="bg-primary text-white py-2 px-3 text-center small fw-semibold d-flex justify-content-center align-items-center gap-2">
            <span><i class="fas fa-user-shield me-1"></i> You are logged in as <strong><?= htmlspecialchars($currentAdmin['name'] ?? 'Administrator') ?></strong></span>
            <span class="mx-1">&bull;</span>
            <a href="<?= BASE_URL ?>admin/index.php" class="text-white text-decoration-underline fw-bold">Return to Admin Dashboard &rarr;</a>
        </div>
    <?php elseif ($isParent): ?>
        <div class="bg-success text-white py-2 px-3 text-center small fw-semibold d-flex justify-content-center align-items-center gap-2">
            <span><i class="fas fa-user-check me-1"></i> Welcome, <strong><?= htmlspecialchars($currentParent['name'] ?? 'Parent') ?></strong> (Registered Parent)</span>
            <span class="mx-1">&bull;</span>
            <a href="<?= BASE_URL ?>parent/index.php" class="text-white text-decoration-underline fw-bold me-2">Parent Dashboard &rarr;</a>
            <span class="mx-1">&bull;</span>
            <a href="<?= BASE_URL ?>auth/logout.php" class="text-white text-decoration-underline fw-bold">Sign Out</a>
        </div>
    <?php elseif ($isHospital): ?>
        <div class="bg-info text-dark py-2 px-3 text-center small fw-semibold d-flex justify-content-center align-items-center gap-2">
            <span><i class="fas fa-hospital me-1"></i> Welcome, <strong><?= htmlspecialchars($currentHospital['hospital_name'] ?? 'Hospital') ?></strong> (Hospital Portal)</span>
            <span class="mx-1">&bull;</span>
            <a href="<?= BASE_URL ?>hospital/index.php" class="text-dark text-decoration-underline fw-bold me-2">Hospital Dashboard &rarr;</a>
            <span class="mx-1">&bull;</span>
            <a href="<?= BASE_URL ?>auth/logout.php" class="text-dark text-decoration-underline fw-bold">Sign Out</a>
        </div>
    <?php endif; ?>

    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg landing-navbar">
        <div class="container">
            <a class="brand-logo" href="<?= BASE_URL ?>">
                <i class="fas fa-shield-virus text-primary" style="font-size: 26px;"></i>
                <span>VaxCare</span>
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fas fa-bars text-primary fs-4"></i>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-lg-2">
                    <li class="nav-item"><a class="nav-link" href="#features">Key Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#hospitals">Partner Hospitals</a></li>
                    <li class="nav-item"><a class="nav-link" href="#how-it-works">How It Works</a></li>
                </ul>

                <div class="d-flex align-items-center gap-3">
                    <!-- Auth Action Button -->
                    <?php if ($isLoggedIn): ?>
                        <a href="<?= BASE_URL ?>admin/index.php" class="btn btn-primary btn-round shadow-sm">
                            <i class="fas fa-tachometer-alt me-1"></i> Admin Dashboard
                        </a>
                        <a href="<?= BASE_URL ?>auth/logout.php" class="btn btn-outline-custom btn-round">
                            <i class="fas fa-sign-out-alt me-1"></i> Log Out
                        </a>
                    <?php elseif ($isParent): ?>
                        <a href="<?= BASE_URL ?>parent/index.php" class="btn btn-success btn-round shadow-sm">
                            <i class="fas fa-baby me-1"></i> Parent Dashboard
                        </a>
                        <a href="<?= BASE_URL ?>auth/logout.php" class="btn btn-outline-custom btn-round">
                            <i class="fas fa-sign-out-alt me-1"></i> Log Out
                        </a>
                    <?php elseif ($isHospital): ?>
                        <a href="<?= BASE_URL ?>hospital/index.php" class="btn btn-info btn-round shadow-sm">
                            <i class="fas fa-hospital me-1"></i> Hospital Dashboard
                        </a>
                        <a href="<?= BASE_URL ?>auth/logout.php" class="btn btn-outline-custom btn-round">
                            <i class="fas fa-sign-out-alt me-1"></i> Log Out
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>auth/register.php" class="btn btn-primary btn-round shadow-sm">
                            <i class="fas fa-user-plus me-1"></i> Register Now
                        </a>
                        <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-outline-custom btn-round shadow-sm">
                            <i class="fas fa-sign-in-alt me-1"></i> Sign In
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10 col-xl-9 text-center">
                    <div class="hero-badge mx-auto">
                        <i class="fas fa-check-circle"></i> Child Health & Immunization Management Portal
                    </div>
                    <h1 class="hero-title">
                        Protecting Every Child's Future with <span class="text-gradient">Timely Vaccination</span>
                    </h1>
                    <p class="hero-desc mx-auto">
                        A unified medical management system tracking recommended childhood immunization timelines from birth, coordinating certified hospital appointments, and securing digital health records.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-3 mb-4">
                        <?php if ($isLoggedIn): ?>
                            <a href="<?= BASE_URL ?>admin/index.php" class="btn btn-primary btn-lg btn-round shadow">
                                <i class="fas fa-tachometer-alt me-2"></i> Open Admin Dashboard
                            </a>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>auth/register.php" class="btn btn-primary btn-lg btn-round shadow">
                                <i class="fas fa-user-plus me-2"></i> Register Now
                            </a>
                            <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-outline-custom btn-lg btn-round">
                                <i class="fas fa-sign-in-alt me-2 text-primary"></i> Sign In
                            </a>
                        <?php endif; ?>
                        <a href="#features" class="btn btn-outline-custom btn-lg btn-round">
                            <i class="fas fa-stream me-2 text-primary"></i> Explore Features
                        </a>
                    </div>
                    <div class="d-flex flex-wrap justify-content-center align-items-center gap-4 text-muted small pt-2">
                        <span><i class="fas fa-hospital-user text-primary me-1"></i> Accredited Hospitals</span>
                        <span><i class="fas fa-shield-alt text-success me-1"></i> WHO & EPI Standard</span>
                        <span><i class="fas fa-clock text-info me-1"></i> Automated Timelines</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Core Features Section -->
    <section class="py-5" id="features">
        <div class="container py-4">
            <div class="text-center max-w-700 mx-auto mb-5">
                <div class="hero-badge"><i class="fas fa-star"></i> Powerful Platform Capabilities</div>
                <h2 class="fw-bold mb-3">Designed for Healthcare Providers, Admins,  Parents & Hospitals</h2>
                <p class="text-muted">A streamlined ecosystem ensuring no child misses crucial life-saving immunization doses.</p>
            </div>

            <div class="row g-4">
                <!-- Feature 1 -->
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon-box">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Automated Age Timelines</h5>
                        <p class="text-muted small mb-0">
                            Automatic immunization calculation from birth date across national schedules (BCG, Polio, Hep-B, MMR, Rota).
                        </p>
                    </div>
                </div>

                <!-- Feature 2 -->
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon-box">
                            <i class="fas fa-hospital-alt"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Hospital Network</h5>
                        <p class="text-muted small mb-0">
                            Centralized directory of partner clinics and authorized healthcare hospitals with active credentials.
                        </p>
                    </div>
                </div>

                <!-- Feature 3 -->
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon-box">
                            <i class="fas fa-envelope-open-text"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Parent Booking Queue</h5>
                        <p class="text-muted small mb-0">
                            Review and approve parent vaccination appointment requests with real-time status and hospital assignment.
                        </p>
                    </div>
                </div>

                <!-- Feature 4 -->
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon-box">
                            <i class="fas fa-file-medical-alt"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Reports & Verification</h5>
                        <p class="text-muted small mb-0">
                            Generate date-wise vaccination reports, filter by hospital or vaccine, and export official CSV records.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Partner Hospitals Section -->
    <section class="py-5" id="hospitals">
        <div class="container py-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
                <div>
                    <div class="hero-badge mb-2"><i class="fas fa-hospital"></i> Healthcare Network</div>
                    <h2 class="fw-bold mb-1">Certified Partner Hospitals</h2>
                    <p class="text-muted mb-0">Authorized vaccination clinics administering verified pediatric immunization.</p>
                </div>
                <!-- <div class="mt-3 mt-md-0">
                    <a href="<?= $isLoggedIn ? BASE_URL . 'admin/hospitals.php' : BASE_URL . 'auth/login.php' ?>" class="btn btn-outline-custom btn-round">
                        View All Hospitals &rarr;
                    </a>
                </div> -->
            </div>

            <div class="row g-4">
                <?php if (!empty($featuredHospitals)): ?>
                    <?php foreach ($featuredHospitals as $h): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="item-card">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <div class="bg-info text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fas fa-hospital"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-bold"><?= htmlspecialchars($h['hospital_name']) ?></h6>
                                        <small class="text-muted"><i class="fas fa-map-marker-alt text-danger me-1"></i> <?= htmlspecialchars($h['location']) ?></small>
                                    </div>
                                </div>
                                <div class="text-muted small mb-3">
                                    <div class="mb-1"><i class="fas fa-phone me-1 text-primary"></i> <?= htmlspecialchars($h['phone'] ?: 'Official Helpline') ?></div>
                                    <div><i class="fas fa-map-pin me-1 text-secondary"></i> <?= htmlspecialchars($h['address'] ?: 'Regional Medical Complex') ?></div>
                                </div>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill w-100 text-center">
                                    <i class="fas fa-check-circle me-1"></i> Active Partner
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="item-card">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <div class="bg-info text-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="fas fa-hospital"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold">City Central Hospital</h6>
                                    <small class="text-muted"><i class="fas fa-map-marker-alt text-danger me-1"></i> Central District</small>
                                </div>
                            </div>
                            <div class="text-muted small mb-3">
                                <div><i class="fas fa-phone me-1 text-primary"></i> +1 (555) 019-2834</div>
                                <div><i class="fas fa-map-pin me-1 text-secondary"></i> Healthcare Ave, Sector 4</div>
                            </div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill w-100 text-center">
                                <i class="fas fa-check-circle me-1"></i> Active Partner
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="py-5" id="how-it-works" style="background: var(--bg-card-alt);">
        <div class="text-center mb-5">
            <h2 class="fw-bold">How VaxCare Works</h2>
            <p class="text-muted">Streamlining child immunization in 4 simple steps</p>
        </div>

        <div class="row g-4 text-center">
            <div class="col-md-3">
                <div class="p-3">
                    <div class="text-primary fs-1 mb-3"><i class="fas fa-baby"></i></div>
                    <h5 class="fw-bold">1. Register Child</h5>
                    <p class="text-muted small">Enter your child's date of birth to automatically generate a complete WHO immunization schedule.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3">
                    <div class="text-primary fs-1 mb-3"><i class="fas fa-calendar-plus"></i></div>
                    <h5 class="fw-bold">2. Book Hospital</h5>
                    <p class="text-muted small">Select your preferred hospital and vaccine date. The booking request is submitted to Admin.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3">
                    <div class="text-primary fs-1 mb-3"><i class="fas fa-check-double"></i></div>
                    <h5 class="fw-bold">3. Admin Approval</h5>
                    <p class="text-muted small">Admin approves the booking request and automatically routes the appointment to the chosen hospital.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3">
                    <div class="text-primary fs-1 mb-3"><i class="fas fa-certificate"></i></div>
                    <h5 class="fw-bold">4. Vaccination & Card</h5>
                    <p class="text-muted small">Hospital administers the dose, updates the record, and parents can instantly download the digital card.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Call-To-Action Banner -->
    <div class="container">
        <section class="cta-section text-center">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h2 class="fw-bold mb-3">Ready to Manage Childhood Immunization?</h2>
                    <p class="lead opacity-90 mb-4">
                        Access the administrative dashboard to monitor vaccination coverage, process parent requests, and oversee partner hospitals.
                    </p>
                    <div class="d-flex justify-content-center gap-3">
                        <?php if ($isLoggedIn): ?>
                            <a href="<?= BASE_URL ?>admin/index.php" class="btn btn-light btn-round btn-lg text-primary fw-bold shadow">
                                <i class="fas fa-tachometer-alt me-2"></i> Return to Admin Dashboard
                            </a>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>auth/register.php" class="btn btn-light btn-round btn-lg text-primary fw-bold shadow">
                                <i class="fas fa-user-plus me-2"></i> Open Registration Form
                            </a>
                            <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-outline-light btn-round btn-lg fw-bold">
                                <i class="fas fa-sign-in-alt me-2"></i> Sign In Portal
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer class="landing-footer">
        <div class="container">
            <div class="row g-4 pb-4">
                <div class="col-lg-4">
                    <a class="brand-logo mb-3" href="<?= BASE_URL ?>">
                        <i class="fas fa-shield-virus text-primary" style="font-size: 24px;"></i>
                        <span>VaxCare</span>
                    </a>
                    <p class="text-muted small mb-3">
                        Comprehensive Child Vaccination Management System ensuring every infant and toddler receives their essential immunization doses on time.
                    </p>
                    <div class="d-flex gap-2">
                        <span class="badge border border-secondary-subtle px-3 py-2" style="background: rgba(255, 255, 255, 0.06); color: #cbd5e1;"><i class="fas fa-shield-alt text-primary me-1"></i> Certified EPI Standards</span>
                    </div>
                </div>

                <div class="col-6 col-lg-2 offset-lg-2">
                    <h6 class="fw-bold mb-3">Quick Links</h6>
                    <a href="#features" class="footer-link">Key Features</a>
                    <a href="#hospitals" class="footer-link">Hospital Network</a>
                    <a href="#how-it-works" class="footer-link">How It Works</a>
                </div>

                <div class="col-6 col-lg-2">
                    <h6 class="fw-bold mb-3">Portal Access</h6>
                    <a href="<?= BASE_URL ?>auth/register.php" class="footer-link"><i class="fas fa-user-plus me-1 text-primary"></i> User Registration</a>
                    <a href="<?= BASE_URL ?>auth/login.php" class="footer-link">Portal Sign In</a>
                    <a href="<?= BASE_URL ?>admin/index.php" class="footer-link">Admin Dashboard</a>
                    <a href="<?= BASE_URL ?>admin/reports.php" class="footer-link">Vaccination Reports</a>
                    <a href="<?= BASE_URL ?>admin/vaccines.php" class="footer-link">Vaccines Catalog</a>
                </div>

                <div class="col-lg-2">
                    <h6 class="fw-bold mb-3">Support & Help</h6>
                    <p class="text-muted small mb-1"><i class="fas fa-envelope me-1"></i> support@vaxcare.org</p>
                    <p class="text-muted small mb-1"><i class="fas fa-phone me-1"></i> Helpline: 111-VAX-CARE</p>
                    <p class="text-muted small"><i class="fas fa-clock me-1"></i> 24/7 Monitoring</p>
                </div>
            </div>

            <div class="border-top pt-4 text-center small text-muted">
                &copy; <?= date('Y') ?> VaxCare &bull; Child Vaccination Management System (VMS). All rights reserved.
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle -->
    <script src="<?= BASE_URL ?>assets/js/core/bootstrap.min.js"></script>
</body>
</html>
