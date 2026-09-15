<?php
/**
 * Global Admin Header Include with Dark / Light Mode Support
 * Child Vaccination Management System (VMS)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

// Enforce admin login
requireAdmin();

$currentAdmin = getCurrentAdmin();
$pageTitle = $pageTitle ?? 'Admin Panel';
$activePage = $activePage ?? '';

// Pending parent booking requests and pending hospital registration requests
$pendingRequestsCount = 0;
$pendingHospitalsCount = 0;
$recentPendingHospitals = [];
if ($pdo) {
    try {
        $pendingRequestsCount = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'Pending'")->fetchColumn();
        $pendingHospitalsCount = (int)$pdo->query("SELECT COUNT(*) FROM hospitals WHERE status = 'Pending'")->fetchColumn();
        if ($pendingHospitalsCount > 0) {
            $recentPendingHospitals = $pdo->query("SELECT hospital_id, hospital_name, location, email, phone FROM hospitals WHERE status = 'Pending' ORDER BY hospital_id DESC LIMIT 4")->fetchAll();
        }
    } catch (Exception $e) {
        $pendingRequestsCount = 0;
        $pendingHospitalsCount = 0;
        $recentPendingHospitals = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title><?= htmlspecialchars($pageTitle) ?> | <?= APP_NAME ?></title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <link rel="icon" href="<?= BASE_URL ?>assets/img/kaiadmin/favicon.ico" type="image/x-icon" />

    <!-- Theme Preference Loader (Prevents White Flash on Dark Mode) -->
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
        /* Base Styling */
        .sidebar[data-background-color="dark"] {
            background: #1a2035;
        }
        .main-header .navbar-header[data-background-color="dark"] {
            background: #1a2035;
        }
        .logo-header[data-background-color="dark"] {
            background: #151a2e;
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
        .avatar-circle {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #1572e8;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .btn-xs {
            padding: 0.2rem 0.4rem;
            font-size: 0.75rem;
        }

        /* ========================================================= */
        /* DARK THEME STYLING & OVERRIDES                            */
        /* ========================================================= */
        [data-theme="dark"] {
            color-scheme: dark;
        }
        [data-theme="dark"] body,
        body[data-background-color="dark"] {
            background-color: #0b1329 !important;
            color: #cbd5e1 !important;
        }
        [data-theme="dark"] .wrapper,
        body[data-background-color="dark"] .wrapper {
            background-color: #0b1329 !important;
        }
        [data-theme="dark"] .main-panel,
        body[data-background-color="dark"] .main-panel {
            background-color: #0b1329 !important;
        }
        [data-theme="dark"] .main-panel > .container,
        body[data-background-color="dark"] .main-panel > .container {
            background-color: #0b1329 !important;
        }
        [data-theme="dark"] .page-inner,
        body[data-background-color="dark"] .page-inner {
            background-color: #0b1329 !important;
        }

        /* High-Contrast Headings & Typography */
        [data-theme="dark"] h1, [data-theme="dark"] .h1,
        [data-theme="dark"] h2, [data-theme="dark"] .h2,
        [data-theme="dark"] h3, [data-theme="dark"] .h3,
        [data-theme="dark"] h4, [data-theme="dark"] .h4,
        [data-theme="dark"] h5, [data-theme="dark"] .h5,
        [data-theme="dark"] h6, [data-theme="dark"] .h6,
        body[data-background-color="dark"] h1, body[data-background-color="dark"] .h1,
        body[data-background-color="dark"] h2, body[data-background-color="dark"] .h2,
        body[data-background-color="dark"] h3, body[data-background-color="dark"] .h3,
        body[data-background-color="dark"] h4, body[data-background-color="dark"] .h4,
        body[data-background-color="dark"] h5, body[data-background-color="dark"] .h5,
        body[data-background-color="dark"] h6, body[data-background-color="dark"] .h6 {
            color: #f8fafc !important;
        }

        [data-theme="dark"] .text-dark,
        [data-theme="dark"] .text-dark *,
        body[data-background-color="dark"] .text-dark,
        body[data-background-color="dark"] .text-dark * {
            color: #f8fafc !important;
        }

        [data-theme="dark"] .text-muted,
        [data-theme="dark"] .small.text-muted,
        [data-theme="dark"] small.text-muted,
        body[data-background-color="dark"] .text-muted {
            color: #94a3b8 !important;
        }

        [data-theme="dark"] .op-7,
        body[data-background-color="dark"] .op-7 {
            opacity: 0.95 !important;
            color: #94a3b8 !important;
        }

        [data-theme="dark"] p,
        body[data-background-color="dark"] p {
            color: #cbd5e1;
        }

        [data-theme="dark"] strong,
        [data-theme="dark"] b,
        [data-theme="dark"] .fw-bold,
        body[data-background-color="dark"] strong,
        body[data-background-color="dark"] b,
        body[data-background-color="dark"] .fw-bold {
            color: #f8fafc !important;
        }

        /* Metric Cards & Stat Titles */
        [data-theme="dark"] .card-stats,
        body[data-background-color="dark"] .card-stats {
            background-color: #111c3a !important;
            border: 1px solid #1e293b !important;
        }
        [data-theme="dark"] .card-stats .card-title,
        [data-theme="dark"] .card-stats .numbers .card-title,
        body[data-background-color="dark"] .card-stats .card-title,
        body[data-background-color="dark"] .card-stats .numbers .card-title {
            color: #ffffff !important;
            font-weight: 700 !important;
        }
        [data-theme="dark"] .card-stats .card-category,
        [data-theme="dark"] .card-stats .numbers .card-category,
        body[data-background-color="dark"] .card-stats .card-category,
        body[data-background-color="dark"] .card-stats .numbers .card-category {
            color: #94a3b8 !important;
            font-weight: 600 !important;
        }

        /* Quick Navigation Shortcut Cards */
        [data-theme="dark"] .dashboard-shortcut-card,
        body[data-background-color="dark"] .dashboard-shortcut-card {
            background-color: #111c3a !important;
            border-color: #1e293b !important;
        }
        [data-theme="dark"] .dashboard-shortcut-card h6,
        body[data-background-color="dark"] .dashboard-shortcut-card h6 {
            color: #f8fafc !important;
        }
        [data-theme="dark"] .dashboard-shortcut-card p,
        body[data-background-color="dark"] .dashboard-shortcut-card p {
            color: #94a3b8 !important;
        }
        [data-theme="dark"] .dashboard-shortcut-card .btn-shortcut,
        body[data-background-color="dark"] .dashboard-shortcut-card .btn-shortcut {
            background-color: #1e293b !important;
            border-color: #334155 !important;
            color: #e2e8f0 !important;
        }
        [data-theme="dark"] .dashboard-shortcut-card:hover .btn-shortcut,
        body[data-background-color="dark"] .dashboard-shortcut-card:hover .btn-shortcut {
            background-color: #2563eb !important;
            border-color: #2563eb !important;
            color: #ffffff !important;
        }

        /* Cards & Card Headers */
        [data-theme="dark"] .card,
        body[data-background-color="dark"] .card {
            background-color: #111c3a !important;
            border-color: #1e293b !important;
            color: #e2e8f0 !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.35) !important;
        }
        [data-theme="dark"] .card-header,
        [data-theme="dark"] .card-header.bg-light,
        body[data-background-color="dark"] .card-header,
        body[data-background-color="dark"] .card-header.bg-light {
            background-color: #162447 !important;
            border-bottom-color: #1e293b !important;
            color: #f8fafc !important;
        }
        [data-theme="dark"] .card-header .card-title,
        [data-theme="dark"] .card-title,
        body[data-background-color="dark"] .card-header .card-title,
        body[data-background-color="dark"] .card-title {
            color: #f8fafc !important;
        }
        [data-theme="dark"] .card-category,
        body[data-background-color="dark"] .card-category {
            color: #94a3b8 !important;
        }

        /* Tables & List Groups */
        [data-theme="dark"] .table,
        body[data-background-color="dark"] .table {
            color: #cbd5e1 !important;
            border-color: #1e293b !important;
        }
        [data-theme="dark"] .table thead.bg-light,
        [data-theme="dark"] .table thead th,
        [data-theme="dark"] .table th,
        body[data-background-color="dark"] .table thead th,
        body[data-background-color="dark"] .table th {
            background-color: #162447 !important;
            color: #f8fafc !important;
            border-color: #1e293b !important;
        }
        [data-theme="dark"] .table td,
        body[data-background-color="dark"] .table td {
            border-color: #1e293b !important;
            color: #cbd5e1 !important;
        }
        [data-theme="dark"] .table td strong,
        body[data-background-color="dark"] .table td strong {
            color: #ffffff !important;
        }
        [data-theme="dark"] .table td .text-primary,
        body[data-background-color="dark"] .table td .text-primary {
            color: #60a5fa !important;
        }
        [data-theme="dark"] .table-hover tbody tr:hover,
        body[data-background-color="dark"] .table-hover tbody tr:hover {
            background-color: rgba(59, 130, 246, 0.08) !important;
            color: #f8fafc !important;
        }
        [data-theme="dark"] .list-group-item,
        body[data-background-color="dark"] .list-group-item {
            background-color: transparent !important;
            border-color: #1e293b !important;
            color: #cbd5e1 !important;
        }
        [data-theme="dark"] .btn-outline-primary,
        body[data-background-color="dark"] .btn-outline-primary {
            color: #60a5fa !important;
            border-color: #3b82f6 !important;
        }
        [data-theme="dark"] .btn-outline-primary:hover,
        body[data-background-color="dark"] .btn-outline-primary:hover {
            background-color: #2563eb !important;
            border-color: #2563eb !important;
            color: #ffffff !important;
        }
        [data-theme="dark"] .btn-outline-secondary,
        body[data-background-color="dark"] .btn-outline-secondary {
            color: #cbd5e1 !important;
            border-color: #475569 !important;
        }
        [data-theme="dark"] .btn-outline-secondary:hover,
        body[data-background-color="dark"] .btn-outline-secondary:hover {
            background-color: #334155 !important;
            color: #ffffff !important;
            border-color: #64748b !important;
        }

        /* Top Navbar & Header */
        [data-theme="dark"] .navbar-header,
        body[data-background-color="dark"] .navbar-header {
            background-color: #111c3a !important;
            border-bottom-color: #1e293b !important;
        }
        [data-theme="dark"] .profile-username,
        [data-theme="dark"] .profile-username span,
        [data-theme="dark"] .profile-username .fw-bold,
        body[data-background-color="dark"] .profile-username,
        body[data-background-color="dark"] .profile-username .fw-bold {
            color: #f8fafc !important;
        }
        [data-theme="dark"] .navbar-header .text-muted,
        body[data-background-color="dark"] .navbar-header .text-muted {
            color: #94a3b8 !important;
        }
        [data-theme="dark"] .navbar-header .btn-outline-warning,
        body[data-background-color="dark"] .navbar-header .btn-outline-warning {
            color: #fbbf24 !important;
            border-color: #fbbf24 !important;
        }
        [data-theme="dark"] .user-box .u-text h5,
        body[data-background-color="dark"] .user-box .u-text h5 {
            color: #f8fafc !important;
        }
        [data-theme="dark"] .user-box .u-text p,
        body[data-background-color="dark"] .user-box .u-text p {
            color: #94a3b8 !important;
        }

        /* Form Controls */
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select,
        body[data-background-color="dark"] .form-control,
        body[data-background-color="dark"] .form-select {
            background-color: #0b1329 !important;
            border-color: #334155 !important;
            color: #f1f5f9 !important;
        }
        [data-theme="dark"] .form-control:focus,
        [data-theme="dark"] .form-select:focus,
        body[data-background-color="dark"] .form-control:focus,
        body[data-background-color="dark"] .form-select:focus {
            border-color: #3b82f6 !important;
            color: #fff !important;
        }
        [data-theme="dark"] .input-group-text,
        body[data-background-color="dark"] .input-group-text {
            background-color: #162447 !important;
            border-color: #334155 !important;
            color: #94a3b8 !important;
        }

        /* Modals */
        [data-theme="dark"] .modal-content,
        body[data-background-color="dark"] .modal-content {
            background-color: #111c3a !important;
            color: #f1f5f9 !important;
            border-color: #334155 !important;
        }
        [data-theme="dark"] .modal-header,
        body[data-background-color="dark"] .modal-header {
            border-bottom-color: #334155 !important;
        }
        [data-theme="dark"] .modal-footer,
        body[data-background-color="dark"] .modal-footer {
            border-top-color: #334155 !important;
        }

        /* Badges & Accents */
        [data-theme="dark"] .bg-light,
        body[data-background-color="dark"] .bg-light {
            background-color: #162447 !important;
        }
        [data-theme="dark"] .badge.bg-light,
        body[data-background-color="dark"] .badge.bg-light {
            background-color: #1e293b !important;
            color: #cbd5e1 !important;
            border-color: #334155 !important;
        }
        [data-theme="dark"] .badge.bg-secondary,
        body[data-background-color="dark"] .badge.bg-secondary {
            background-color: #334155 !important;
            color: #f1f5f9 !important;
        }

        /* Footer & Dropdown */
        [data-theme="dark"] .footer,
        body[data-background-color="dark"] .footer {
            background-color: #0b1329 !important;
            border-top-color: #1e293b !important;
            color: #94a3b8 !important;
        }
        [data-theme="dark"] .dropdown-menu,
        body[data-background-color="dark"] .dropdown-menu {
            background-color: #111c3a !important;
            border-color: #334155 !important;
            color: #cbd5e1 !important;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5) !important;
        }
        [data-theme="dark"] .dropdown-item,
        body[data-background-color="dark"] .dropdown-item {
            color: #cbd5e1 !important;
        }
        [data-theme="dark"] .dropdown-item:hover,
        body[data-background-color="dark"] .dropdown-item:hover {
            background-color: #1e293b !important;
            color: #f8fafc !important;
        }
        [data-theme="dark"] .dropdown-divider,
        body[data-background-color="dark"] .dropdown-divider {
            border-top-color: #1e293b !important;
        }
        [data-theme="dark"] .btn-light,
        body[data-background-color="dark"] .btn-light {
            background-color: #1e293b !important;
            border-color: #334155 !important;
            color: #cbd5e1 !important;
        }
        [data-theme="dark"] .btn-light:hover,
        body[data-background-color="dark"] .btn-light:hover {
            background-color: #334155 !important;
            color: #f8fafc !important;
        }
        [data-theme="dark"] .progress,
        body[data-background-color="dark"] .progress {
            background-color: #1e293b !important;
        }
        [data-theme="dark"] .dataTables_wrapper .dataTables_length select,
        [data-theme="dark"] .dataTables_wrapper .dataTables_filter input,
        body[data-background-color="dark"] .dataTables_wrapper .dataTables_length select,
        body[data-background-color="dark"] .dataTables_wrapper .dataTables_filter input {
            background-color: #0b1329 !important;
            border-color: #334155 !important;
            color: #cbd5e1 !important;
        }
        [data-theme="dark"] .dataTables_wrapper .dataTables_info,
        [data-theme="dark"] .dataTables_wrapper .dataTables_paginate,
        body[data-background-color="dark"] .dataTables_wrapper .dataTables_info,
        body[data-background-color="dark"] .dataTables_wrapper .dataTables_paginate {
            color: #94a3b8 !important;
        }

        /* Theme Toggle Button Style */
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
        .theme-toggle-btn:hover {
            transform: scale(1.08);
            background: rgba(255,255,255,0.18);
            color: #fbbf24;
        }
        [data-theme="light"] .theme-toggle-btn {
            border-color: #cbd5e1;
            background: #f1f5f9;
            color: #475569;
        }
        [data-theme="light"] .theme-toggle-btn:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        /* ========================================================= */
        /* HOVER EFFECTS & ANIMATIONS                                */
        /* ========================================================= */

        /* Sidebar Navigation Hover Effects */
        .sidebar[data-background-color="dark"] .nav > .nav-item > a {
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
            border-radius: 8px !important;
            margin: 2px 10px !important;
        }
        .sidebar[data-background-color="dark"] .nav > .nav-item > a:hover {
            background: rgba(255, 255, 255, 0.1) !important;
            transform: translateX(4px);
        }
        .sidebar[data-background-color="dark"] .nav > .nav-item > a:hover i {
            color: #38bdf8 !important;
            transform: scale(1.15);
            transition: transform 0.25s ease, color 0.25s ease;
        }
        .sidebar[data-background-color="dark"] .nav > .nav-item > a:hover p {
            color: #ffffff !important;
            font-weight: 600;
        }
        .sidebar[data-background-color="dark"] .nav > .nav-item.active > a {
            background: #1572e8 !important;
            box-shadow: 0 4px 14px rgba(21, 114, 232, 0.4) !important;
        }
        .sidebar[data-background-color="dark"] .nav > .nav-item.active > a:hover {
            background: #1266cf !important;
            transform: translateX(4px);
            box-shadow: 0 6px 18px rgba(21, 114, 232, 0.55) !important;
        }

        /* Admin Dashboard Metric Stat Cards Hover */
        .dashboard-stat-card {
            transition: transform 0.28s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.28s ease, border-color 0.28s ease !important;
            cursor: pointer;
            border: 1px solid transparent !important;
        }
        .dashboard-stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12) !important;
            border-color: rgba(21, 114, 232, 0.3) !important;
        }
        .dashboard-stat-card .icon-big {
            transition: transform 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .dashboard-stat-card:hover .icon-big {
            transform: scale(1.14) rotate(4deg);
        }
        .dashboard-stat-card .card-title {
            transition: color 0.2s ease;
        }
        .dashboard-stat-card:hover .card-title {
            color: #1572e8 !important;
        }
        [data-theme="dark"] .dashboard-stat-card {
            border-color: #1e293b !important;
        }
        [data-theme="dark"] .dashboard-stat-card:hover {
            box-shadow: 0 14px 28px rgba(0, 0, 0, 0.55) !important;
            border-color: rgba(59, 130, 246, 0.4) !important;
        }
        [data-theme="dark"] .dashboard-stat-card:hover .card-title {
            color: #60a5fa !important;
        }

        /* Admin Dashboard Quick Navigation Shortcut Cards Hover */
        .dashboard-shortcut-card {
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease, border-color 0.3s ease !important;
            cursor: pointer;
            border: 1px solid rgba(0, 0, 0, 0.08) !important;
        }
        .dashboard-shortcut-card:hover {
            transform: translateY(-7px);
            box-shadow: 0 14px 28px rgba(0, 0, 0, 0.14) !important;
            border-color: #1572e8 !important;
        }
        .dashboard-shortcut-card .shortcut-icon {
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .dashboard-shortcut-card:hover .shortcut-icon {
            transform: scale(1.22);
        }
        .dashboard-shortcut-card .btn-shortcut {
            transition: all 0.25s ease !important;
        }
        .dashboard-shortcut-card:hover .btn-shortcut {
            background-color: #1572e8 !important;
            color: #ffffff !important;
            border-color: #1572e8 !important;
            box-shadow: 0 4px 12px rgba(21, 114, 232, 0.35);
        }
        [data-theme="dark"] .dashboard-shortcut-card {
            border-color: #1e293b !important;
        }
        [data-theme="dark"] .dashboard-shortcut-card:hover {
            border-color: #3b82f6 !important;
            box-shadow: 0 14px 28px rgba(0, 0, 0, 0.65) !important;
        }

        /* Top Action Buttons Hover */
        .btn-header-action {
            transition: all 0.25s ease !important;
        }
        .btn-header-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2) !important;
        }

        /* Recent Children List Item Hover */
        .recent-child-item {
            transition: all 0.2s ease !important;
            border-left: 3px solid transparent !important;
            padding-left: 10px !important;
            padding-right: 10px !important;
            border-radius: 6px;
        }
        .recent-child-item:hover {
            background-color: rgba(21, 114, 232, 0.08) !important;
            border-left-color: #1572e8 !important;
            transform: translateX(4px);
        }
        [data-theme="dark"] .recent-child-item:hover {
            background-color: rgba(59, 130, 246, 0.14) !important;
            border-left-color: #3b82f6 !important;
        }

        /* Table Row and Button Hover */
        .table-hover tbody tr {
            transition: background-color 0.2s ease;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(21, 114, 232, 0.05) !important;
        }
        .table-hover tbody tr:hover .btn {
            transform: scale(1.05);
            transition: transform 0.2s ease;
        }
    </style>
</head>
<body>
    <div class="wrapper">
