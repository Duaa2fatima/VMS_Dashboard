<?php
/**
 * Global Utility & Helper Functions
 * Child Vaccination Management System (VMS)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!ob_get_level()) {
    ob_start();
}

/**
 * Set a Flash Message
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type'    => $type, // 'success', 'danger', 'error', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Display and Clear Flash Message
 */
function displayFlash() {
    if (empty($_SESSION['flash'])) {
        return '';
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $type = $flash['type'] === 'error' ? 'danger' : $flash['type'];
    $icon = match ($type) {
        'success' => 'fa-check-circle',
        'danger'  => 'fa-exclamation-circle',
        'warning' => 'fa-exclamation-triangle',
        default   => 'fa-info-circle',
    };

    $html = '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show d-flex align-items-center mb-4" role="alert">';
    $html .= '<i class="fas ' . $icon . ' me-2 fs-5"></i>';
    $html .= '<div>' . htmlspecialchars($flash['message']) . '</div>';
    $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    $html .= '</div>';

    return $html;
}

/**
 * Format Date helper
 */
function formatDate($dateStr, $format = 'd M Y') {
    if (empty($dateStr) || $dateStr === '0000-00-00') {
        return '-';
    }
    try {
        $date = new DateTime($dateStr);
        return $date->format($format);
    } catch (Exception $e) {
        return htmlspecialchars($dateStr);
    }
}

/**
 * Format Time helper (e.g. '14:30:00' -> '2:30 PM')
 */
function formatTime($timeStr, $format = 'g:i A') {
    if (empty($timeStr) || $timeStr === '00:00:00') {
        return '-';
    }
    try {
        $time = new DateTime($timeStr);
        return $time->format($format);
    } catch (Exception $e) {
        return htmlspecialchars($timeStr);
    }
}

/**
 * Calculate Child Age from Date of Birth
 */
function calculateAge($dob) {
    if (empty($dob) || $dob === '0000-00-00') {
        return 'N/A';
    }
    try {
        $birthDate = new DateTime($dob);
        $today     = new DateTime('today');
        $diff      = $birthDate->diff($today);

        if ($diff->y > 0) {
            return $diff->y . ' yr' . ($diff->y > 1 ? 's' : '') . ($diff->m > 0 ? ' ' . $diff->m . ' mo' : '');
        } elseif ($diff->m > 0) {
            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ($diff->d > 0 ? ' ' . $diff->d . ' d' : '');
        } else {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
        }
    } catch (Exception $e) {
        return 'N/A';
    }
}

/**
 * Calculate Approximate Due Date based on child DOB and Age Group string
 */
function calculateDueDate($dob, $ageGroup) {
    if (empty($dob)) {
        return date('Y-m-d');
    }
    $dobDate = new DateTime($dob);

    // Approximate offsets based on standard WHO terms in age_group
    $ag = strtolower((string)$ageGroup);
    if (str_contains($ag, 'birth')) {
        return $dobDate->format('Y-m-d');
    } elseif (str_contains($ag, '6 week')) {
        $dobDate->modify('+6 weeks');
    } elseif (str_contains($ag, '10 week')) {
        $dobDate->modify('+10 weeks');
    } elseif (str_contains($ag, '14 week')) {
        $dobDate->modify('+14 weeks');
    } elseif (str_contains($ag, '6 month')) {
        $dobDate->modify('+6 months');
    } elseif (str_contains($ag, '9 month')) {
        $dobDate->modify('+9 months');
    } elseif (str_contains($ag, '12 month') || str_contains($ag, '1 year')) {
        $dobDate->modify('+12 months');
    } elseif (str_contains($ag, '16-24 month') || str_contains($ag, '18 month')) {
        $dobDate->modify('+18 months');
    } elseif (str_contains($ag, '5 year') || str_contains($ag, '5-6 year')) {
        $dobDate->modify('+5 years');
    } else {
        // Fallback default 4 weeks
        $dobDate->modify('+4 weeks');
    }

    return $dobDate->format('Y-m-d');
}

/**
 * Status badge generator for Bookings and Vaccinations
 */
function getStatusBadge($status) {
    return match ($status) {
        'Approved'   => '<span class="badge bg-info text-white"><i class="fas fa-check-circle me-1"></i> Approved</span>',
        'Completed'  => '<span class="badge bg-success"><i class="fas fa-check-double me-1"></i> Completed</span>',
        'Vaccinated' => '<span class="badge bg-success"><i class="fas fa-syringe me-1"></i> Vaccinated</span>',
        'Not Vaccinated' => '<span class="badge bg-danger"><i class="fas fa-times me-1"></i> Not Vaccinated</span>',
        'Pending'    => '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> Pending</span>',
        'Rejected'   => '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i> Rejected</span>',
        'Active'     => '<span class="badge bg-success">Active</span>',
        'Inactive'   => '<span class="badge bg-secondary">Inactive</span>',
        default      => '<span class="badge bg-secondary">' . htmlspecialchars((string)$status) . '</span>',
    };
}

/**
 * Vaccine Stock status badge
 */
function getStockBadge($status) {
    if ($status === 'Available') {
        return '<span class="badge bg-success rounded-pill px-3"><i class="fas fa-check-circle me-1"></i> Available</span>';
    } else {
        return '<span class="badge bg-danger rounded-pill px-3"><i class="fas fa-times-circle me-1"></i> Unavailable</span>';
    }
}

/**
 * Clean & Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}
