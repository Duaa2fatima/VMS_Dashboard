<?php
/**
 * Parent Panel Header Include
 * Child Vaccination Management System (VMS)
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/helpers.php';

// Enforce parent login
requireParent();

$currentParent = getCurrentParent();
$pageTitle = $pageTitle ?? 'Parent Portal';
$activePage = $activePage ?? '';

// Upcoming notification count for this parent's children (Approved appointments + due milestones)
$parentUpcomingCount = 0;
$parentChildren = [];
$parentPendingCount = 0;

if ($pdo && !empty($currentParent['id'])) {
    try {
        $parentId = $currentParent['id'];
        
        // Children for this parent
        $stmtCh = $pdo->prepare("SELECT child_id, child_name, date_of_birth, gender FROM children WHERE parent_id = ? ORDER BY child_id ASC");
        $stmtCh->execute([$parentId]);
        $parentChildren = $stmtCh->fetchAll();

        // Count pending bookings
        $stmtPending = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN children c ON b.child_id = c.child_id WHERE c.parent_id = ? AND b.status = 'Pending'");
        $stmtPending->execute([$parentId]);
        $parentPendingCount = (int)$stmtPending->fetchColumn();

        // Count approved upcoming appointments from today onwards
        $stmtUp = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN children c ON b.child_id = c.child_id WHERE c.parent_id = ? AND b.status = 'Approved' AND b.appointment_date >= CURRENT_DATE");
        $stmtUp->execute([$parentId]);
        $parentUpcomingCount = (int)$stmtUp->fetchColumn();

    } catch (Exception $e) {
        $parentUpcomingCount = 0;
        $parentPendingCount = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title><?= htmlspecialchars($pageTitle) ?> | Parent Portal &bull; <?= APP_NAME ?></title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <link rel="icon" href="<?= BASE_URL ?>assets/img/kaiadmin/favicon.ico" type="image/x-icon" />

    <!-- Theme Preference Loader -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('vaxcare_theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            document.addEventListener('DOMContentLoaded', function() {
                if (document.body) {
                    document.body.setAttribute('data-theme', savedTheme);
                    document.body.setAttribute('data-background-color', savedTheme === 'dark' ? 'dark' : 'white');
                }
            });
        })();
    </script>

    <!-- Fonts and icons -->
    <script src="<?= BASE_URL ?>assets/js/plugin/webfont/webfont.min.js"></script>
    <script>
        WebFont.load({
            google: { families: ["Public Sans:300,400,500,600,700"] },
            custom: {
                families: [
                    "Font Awesome 5 Solid",
                    "Font Awesome 5 Regular",
                    "Font Awesome 5 Brands",
                    "simple-line-icons",
                ],
                urls: ["<?= BASE_URL ?>assets/css/fonts.min.css"],
            },
            active: function () {
                sessionStorage.fonts = true;
            },
        });
    </script>

    <!-- CSS Files -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/plugins.min.css" />
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/kaiadmin.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    
    <style>
        .sidebar[data-background-color="dark"] {
            background: #111e38;
        }
        .logo-header[data-background-color="dark"] {
            background: #0d172e;
        }
        .app-brand {
            font-size: 18px;
            font-weight: 700;
            color: #fff !important;
            letter-spacing: 0.5px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .table th {
            font-weight: 600;
            color: #495057;
            background-color: #f8f9fa;
        }
        .card-stats .icon-big {
            font-size: 2.2rem;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-xs {
            padding: 0.2rem 0.5rem;
            font-size: 0.75rem;
        }
        .badge-role {
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 4px;
            background-color: #10b981;
            color: #fff;
        }

        /* Dark Theme Support */
        [data-theme="dark"] { color-scheme: dark; }
        [data-theme="dark"] body, body[data-background-color="dark"] {
            background-color: #0b1329 !important;
            color: #cbd5e1 !important;
        }
        [data-theme="dark"] .wrapper, body[data-background-color="dark"] .wrapper { background-color: #0b1329 !important; }
        [data-theme="dark"] .main-panel, body[data-background-color="dark"] .main-panel { background-color: #0b1329 !important; }
        [data-theme="dark"] .main-panel > .container { background-color: #0b1329 !important; }
        [data-theme="dark"] .page-inner { background-color: #0b1329 !important; }
        [data-theme="dark"] .navbar-header { background-color: #111c3a !important; border-bottom-color: #1e293b !important; }
        [data-theme="dark"] .card { background-color: #111c3a !important; border-color: #1e293b !important; color: #e2e8f0 !important; }
        [data-theme="dark"] .card-header { background-color: #162447 !important; border-bottom-color: #1e293b !important; color: #f8fafc !important; }
        [data-theme="dark"] .card-title, [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] h4, [data-theme="dark"] h5, [data-theme="dark"] h6 { color: #f8fafc !important; }
        [data-theme="dark"] .table { color: #cbd5e1 !important; border-color: #1e293b !important; }
        [data-theme="dark"] .table th { background-color: #162447 !important; color: #f8fafc !important; border-color: #1e293b !important; }
        [data-theme="dark"] .table td { border-color: #1e293b !important; color: #cbd5e1 !important; }
        [data-theme="dark"] .form-control, [data-theme="dark"] .form-select { background-color: #0b1329 !important; border-color: #334155 !important; color: #f1f5f9 !important; }
        [data-theme="dark"] .modal-content { background-color: #111c3a !important; color: #f1f5f9 !important; border-color: #334155 !important; }
        [data-theme="dark"] .modal-header { border-bottom-color: #334155 !important; }
        [data-theme="dark"] .modal-footer { border-top-color: #334155 !important; }
        [data-theme="dark"] .footer { background-color: #0b1329 !important; border-top-color: #1e293b !important; color: #94a3b8 !important; }
        [data-theme="dark"] .dropdown-menu { background-color: #111c3a !important; border-color: #334155 !important; color: #cbd5e1 !important; }
        [data-theme="dark"] .dropdown-item { color: #cbd5e1 !important; }
        [data-theme="dark"] .dropdown-item:hover { background-color: #1e293b !important; color: #fff !important; }

        /* Card Hover */
        .dashboard-stat-card {
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            cursor: pointer;
        }
        .dashboard-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 22px rgba(0,0,0,0.15) !important;
        }
        .theme-toggle-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.08);
            color: #f59e0b;
            cursor: pointer;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="wrapper">
