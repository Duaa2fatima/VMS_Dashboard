<?php
/**
 * Administrator Logout Script
 * Child Vaccination Management System (VMS)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

logoutUser();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['flash'] = [
    'type' => 'info',
    'message' => 'You have been successfully signed out.'
];

header('Location: ' . BASE_URL . 'auth/login.php');
exit;
