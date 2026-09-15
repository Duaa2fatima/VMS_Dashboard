<?php
/**
 * Vaccination Dates & Upcoming Schedule
 * Child Vaccination Management System (VMS)
 */
$pageTitle = 'Vaccination Dates';
$activePage = 'parent_schedule';

require_once __DIR__ . '/includes/header.php';

$parentId = $currentParent['id'];

// Filter inputs
$selectedChildId = (int)($_GET['child_id'] ?? 0);
$timeframe = $_GET['timeframe'] ?? '30'; // '7', '30', '90', 'all'

// Fetch parent's children
$children = [];
$upcomingBookings = [];
$milestonesDue = [];

if ($pdo) {
    try {
        $stmtCh = $pdo->prepare("SELECT * FROM children WHERE parent_id = ? ORDER BY child_name ASC");
        $stmtCh->execute([$parentId]);
        $children = $stmtCh->fetchAll();

        // Build upcoming bookings query
        $bkQuery = "
            SELECT b.*, c.child_name, c.date_of_birth, c.gender, v.vaccine_name, v.age_group, h.hospital_name, h.location, h.phone, h.address as hospital_address
            FROM bookings b
            JOIN children c ON b.child_id = c.child_id
            JOIN vaccines v ON b.vaccine_id = v.vaccine_id
            JOIN hospitals h ON b.hospital_id = h.hospital_id
            WHERE c.parent_id = ? AND b.status IN ('Approved', 'Pending') AND b.appointment_date >= CURRENT_DATE
        ";
        $params = [$parentId];

        if ($selectedChildId > 0) {
            $bkQuery .= " AND c.child_id = ?";
            $params[] = $selectedChildId;
        }

        if ($timeframe === '7') {
            $bkQuery .= " AND b.appointment_date <= DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)";
        } elseif ($timeframe === '30') {
            $bkQuery .= " AND b.appointment_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)";
        } elseif ($timeframe === '90') {
            $bkQuery .= " AND b.appointment_date <= DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY)";
        }

        $bkQuery .= " ORDER BY b.appointment_date ASC";

        $stmtBk = $pdo->prepare($bkQuery);
        $stmtBk->execute($params);
        $upcomingBookings = $stmtBk->fetchAll();

        // Milestone due dates calculations
        $allVaccines = $pdo->query("SELECT * FROM vaccines WHERE stock_status = 'Available' ORDER BY vaccine_id ASC")->fetchAll();
        $targetChildren = $selectedChildId ? array_filter($children, fn($c) => $c['child_id'] == $selectedChildId) : $children;

        foreach ($targetChildren as $child) {
            // Check completed/booked vaccines
            $stmtTaken = $pdo->prepare("SELECT vaccine_id FROM bookings WHERE child_id = ? AND status IN ('Approved', 'Completed', 'Pending')");
            $stmtTaken->execute([$child['child_id']]);
            $takenIds = $stmtTaken->fetchAll(PDO::FETCH_COLUMN);

            foreach ($allVaccines as $v) {
                if (!in_array($v['vaccine_id'], $takenIds)) {
                    $due = calculateDueDate($child['date_of_birth'], $v['age_group']);
                    $today = date('Y-m-d');
                    $diffDays = (int)round((strtotime($due) - strtotime($today)) / 86400);

                    // Apply timeframe filter
                    $include = false;
                    if ($timeframe === '7' && $diffDays <= 7) $include = true;
                    elseif ($timeframe === '30' && $diffDays <= 30) $include = true;
                    elseif ($timeframe === '90' && $diffDays <= 90) $include = true;
                    elseif ($timeframe === 'all') $include = true;

                    if ($include) {
                        $milestonesDue[] = [
                            'child_id'     => $child['child_id'],
                            'child_name'   => $child['child_name'],
                            'gender'       => $child['gender'],
                            'vaccine_id'   => $v['vaccine_id'],
                            'vaccine_name' => $v['vaccine_name'],
                            'age_group'    => $v['age_group'],
                            'due_date'     => $due,
                            'days_diff'    => $diffDays,
                            'is_overdue'   => $diffDays < 0
                        ];
                    }
                }
            }
        }

        usort($milestonesDue, fn($a, $b) => strcmp($a['due_date'], $b['due_date']));

    } catch (Exception $e) {
        setFlash('error', 'Error loading vaccination schedule: ' . $e->getMessage());
    }
}
?>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-panel">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>
    <div class="container">
        <div class="page-inner">

            <!-- Page Header -->
            <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                <div>
                    <h3 class="fw-bold mb-1">Vaccination Dates & Upcoming Schedule</h3>
                    <h6 class="op-7 mb-0 text-muted">Upcoming appointment schedules and calculated WHO immunization due dates</h6>
                </div>
                <div class="ms-md-auto py-2 py-md-0 d-flex gap-2">
                    <button class="btn btn-outline-secondary btn-round" onclick="window.print()">
                        <i class="fas fa-print me-1"></i> Print Schedule
                    </button>
                    <a href="book-appointment.php" class="btn btn-primary btn-round shadow-sm">
                        <i class="fas fa-calendar-plus me-1"></i> Book Hospital Visit
                    </a>
                </div>
            </div>

            <?= displayFlash() ?>

            <!-- Filter Controls -->
            <div class="card card-round shadow-sm mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="schedule.php" class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted mb-1">Filter by Child:</label>
                            <select name="child_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="0">All Children</option>
                                <?php foreach ($children as $c): ?>
                                    <option value="<?= $c['child_id'] ?>" <?= ($selectedChildId == $c['child_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['child_name']) ?> (<?= calculateAge($c['date_of_birth']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-muted mb-1">Time Horizon:</label>
                            <div class="btn-group w-100 btn-group-sm" role="group">
                                <a href="schedule.php?child_id=<?= $selectedChildId ?>&timeframe=7" class="btn <?= ($timeframe === '7') ? 'btn-primary' : 'btn-outline-primary' ?>">Next 7 Days</a>
                                <a href="schedule.php?child_id=<?= $selectedChildId ?>&timeframe=30" class="btn <?= ($timeframe === '30') ? 'btn-primary' : 'btn-outline-primary' ?>">Next 30 Days</a>
                                <a href="schedule.php?child_id=<?= $selectedChildId ?>&timeframe=90" class="btn <?= ($timeframe === '90') ? 'btn-primary' : 'btn-outline-primary' ?>">Next 90 Days</a>
                                <a href="schedule.php?child_id=<?= $selectedChildId ?>&timeframe=all" class="btn <?= ($timeframe === 'all') ? 'btn-primary' : 'btn-outline-primary' ?>">All Future</a>
                            </div>
                        </div>
                        <div class="col-md-3 text-end pt-3">
                            <a href="schedule.php" class="btn btn-sm btn-link text-muted">Reset Filters</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Confirmed / Scheduled Appointments Section -->
            <div class="card card-round shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div class="card-title">
                        <i class="fas fa-calendar-check text-primary me-2"></i> Scheduled Hospital Appointments (Approved & Pending)
                    </div>
                    <span class="badge bg-primary"><?= count($upcomingBookings) ?> Active Bookings</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($upcomingBookings)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-calendar-day fa-3x mb-2 text-muted"></i>
                            <h6>No scheduled hospital bookings found for this timeframe</h6>
                            <p class="small mb-2">Book an appointment at an accredited hospital facility for upcoming immunization.</p>
                            <a href="book-appointment.php" class="btn btn-sm btn-primary rounded-pill">
                                <i class="fas fa-plus me-1"></i> Book Appointment Now
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Date & Countdown</th>
                                        <th>Child Details</th>
                                        <th>Vaccine</th>
                                        <th>Hospital Facility</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcomingBookings as $bk): ?>
                                        <?php
                                            $today = new DateTime('today');
                                            $appDate = new DateTime($bk['appointment_date']);
                                            $diff = $today->diff($appDate);
                                            $daysRemaining = (int)$diff->format("%r%a");
                                            $badgeClass = ($daysRemaining === 0) ? 'bg-danger' : (($daysRemaining <= 3) ? 'bg-warning text-dark' : 'bg-success');
                                            $timeLabel = ($daysRemaining === 0) ? 'TODAY!' : (($daysRemaining === 1) ? 'Tomorrow' : 'In ' . $daysRemaining . ' days');
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold fs-6 text-primary"><?= formatDate($bk['appointment_date'], 'd M Y') ?></div>
                                                <?php if (!empty($bk['appointment_time'])): ?>
                                                    <div class="badge bg-light text-primary border border-primary-subtle my-1">
                                                        <i class="far fa-clock me-1"></i> <?= formatTime($bk['appointment_time']) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <span class="badge <?= $badgeClass ?>" style="font-size: 0.72rem;">
                                                        <i class="fas fa-calendar-day me-1"></i> <?= $timeLabel ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <strong class="text-dark"><?= htmlspecialchars($bk['child_name']) ?></strong>
                                                <div class="small text-muted"><?= calculateAge($bk['date_of_birth']) ?> &bull; <?= htmlspecialchars($bk['gender']) ?></div>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($bk['vaccine_name']) ?></strong>
                                                <div class="small text-muted"><?= htmlspecialchars($bk['age_group']) ?></div>
                                            </td>
                                            <td>
                                                <div><i class="fas fa-hospital text-muted me-1"></i> <strong><?= htmlspecialchars($bk['hospital_name']) ?></strong></div>
                                                <div class="small text-muted"><i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($bk['location'] ?? '-') ?></div>
                                                <?php if (!empty($bk['phone'])): ?>
                                                    <div class="small text-muted"><i class="fas fa-phone me-1"></i> <?= htmlspecialchars($bk['phone']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= getStatusBadge($bk['status']) ?>
                                            </td>
                                            <td>
                                                <a href="bookings.php" class="btn btn-xs btn-outline-primary">
                                                    <i class="fas fa-info-circle me-1"></i> Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Calculated Immunization Milestone Due Dates Section -->
            <div class="card card-round shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div class="card-title">
                        <i class="fas fa-bell text-warning me-2"></i> Calculated Immunization Milestone Reminders (WHO Schedule)
                    </div>
                    <span class="badge bg-warning text-dark"><?= count($milestonesDue) ?> Milestones</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($milestonesDue)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-check-double fa-3x mb-2 text-success"></i>
                            <h6>All milestone vaccines are scheduled or completed for this timeframe!</h6>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Child</th>
                                        <th>Vaccine</th>
                                        <th>Recommended Age</th>
                                        <th>Estimated Due Date</th>
                                        <th>Urgency Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($milestonesDue as $m): ?>
                                        <tr class="<?= $m['is_overdue'] ? 'table-danger bg-opacity-10' : '' ?>">
                                            <td>
                                                <strong class="text-dark"><?= htmlspecialchars($m['child_name']) ?></strong>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-primary"><?= htmlspecialchars($m['vaccine_name']) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($m['age_group']) ?></span>
                                            </td>
                                            <td class="fw-bold">
                                                <?= formatDate($m['due_date'], 'd M Y') ?>
                                            </td>
                                            <td>
                                                <?php if ($m['is_overdue']): ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-exclamation-triangle me-1"></i> Overdue by <?= abs($m['days_diff']) ?> days
                                                    </span>
                                                <?php elseif ($m['days_diff'] === 0): ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="fas fa-clock me-1"></i> Due Today!
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-info text-white">
                                                        <i class="fas fa-calendar-day me-1"></i> Due in <?= $m['days_diff'] ?> days
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="book-appointment.php?child_id=<?= $m['child_id'] ?>&vaccine_id=<?= $m['vaccine_id'] ?>" class="btn btn-xs btn-primary text-white">
                                                    <i class="fas fa-calendar-plus me-1"></i> Book Schedule
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
