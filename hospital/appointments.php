<?php
/**
 * Patient Appointments & Update Vaccine Status
 * Child Vaccination Management System (VMS)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
requireHospital();

$currentHospital = getCurrentHospital();
$hospitalId = $currentHospital['id'];
$filter = $_GET['filter'] ?? 'Pending'; // 'Pending', 'Approved', 'Today', 'Completed', 'All'
$openStatusId = (int)($_GET['open_status'] ?? 0);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $action = $_POST['action'] ?? '';

    // Action 1: Hospital Approves Appointment & Assigns Date and Time
    if ($action === 'approve_appointment') {
        $bookingId       = (int)($_POST['booking_id'] ?? 0);
        $appointmentDate = trim($_POST['appointment_date'] ?? '');
        $appointmentTime = trim($_POST['appointment_time'] ?? '');
        $hospitalNotes   = trim($_POST['hospital_notes'] ?? '');

        if (!$bookingId || empty($appointmentDate) || empty($appointmentTime)) {
            setFlash('error', 'Please provide both a confirmed appointment date and appointment time.');
        } else {
            try {
                $stmtVerify = $pdo->prepare("
                    SELECT b.*, c.child_name, v.vaccine_name, p.name as parent_name
                    FROM bookings b
                    JOIN children c ON b.child_id = c.child_id
                    JOIN vaccines v ON b.vaccine_id = v.vaccine_id
                    JOIN parents p ON c.parent_id = p.parent_id
                    WHERE b.booking_id = ? AND b.hospital_id = ?
                ");
                $stmtVerify->execute([$bookingId, $hospitalId]);
                $bkg = $stmtVerify->fetch();

                if (!$bkg) {
                    setFlash('error', 'Appointment request not found or unauthorized.');
                } else {
                    $stmtApprove = $pdo->prepare("
                        UPDATE bookings 
                        SET status = 'Approved',
                            appointment_date = ?,
                            appointment_time = ?,
                            hospital_notes = ?,
                            approval_date = CURRENT_DATE
                        WHERE booking_id = ? AND hospital_id = ?
                    ");
                    $stmtApprove->execute([$appointmentDate, $appointmentTime, $hospitalNotes, $bookingId, $hospitalId]);

                    $formattedTime = formatTime($appointmentTime);
                    setFlash('success', "Appointment #BKG-" . str_pad($bookingId, 3, '0', STR_PAD_LEFT) . " for '{$bkg['child_name']}' ({$bkg['vaccine_name']}) has been APPROVED and scheduled for " . formatDate($appointmentDate, 'd M Y') . " at {$formattedTime}.");
                    header('Location: appointments.php?filter=Approved');
                    exit;
                }
            } catch (Exception $e) {
                setFlash('error', 'Error approving appointment: ' . $e->getMessage());
            }
        }
        header('Location: appointments.php?filter=Pending');
        exit;
    }

    // Action 2: Hospital Declines / Rejects Request
    elseif ($action === 'reject_appointment') {
        $bookingId     = (int)($_POST['booking_id'] ?? 0);
        $hospitalNotes = trim($_POST['hospital_notes'] ?? '');

        if (!$bookingId) {
            setFlash('error', 'Invalid booking reference.');
        } else {
            try {
                $stmtVerify = $pdo->prepare("SELECT child_id FROM bookings WHERE booking_id = ? AND hospital_id = ?");
                $stmtVerify->execute([$bookingId, $hospitalId]);
                if ($stmtVerify->fetch()) {
                    $stmtRej = $pdo->prepare("
                        UPDATE bookings 
                        SET status = 'Rejected', 
                            hospital_notes = ?, 
                            approval_date = CURRENT_DATE 
                        WHERE booking_id = ? AND hospital_id = ?
                    ");
                    $stmtRej->execute([$hospitalNotes, $bookingId, $hospitalId]);
                    setFlash('info', "Booking request #BKG-" . str_pad($bookingId, 3, '0', STR_PAD_LEFT) . " has been declined.");
                }
            } catch (Exception $e) {
                setFlash('error', 'Error declining request: ' . $e->getMessage());
            }
        }
        header('Location: appointments.php?filter=Pending');
        exit;
    }

    // Action 3: Handle Update Vaccine Status Form Submission
    elseif ($action === 'update_vaccine_status' || empty($action)) {
    $bookingId       = (int)($_POST['booking_id'] ?? 0);
    $childId         = (int)($_POST['child_id'] ?? 0);
    $vaccineStatus   = in_array($_POST['vaccine_status'] ?? '', ['Vaccinated', 'Not Vaccinated']) ? $_POST['vaccine_status'] : 'Vaccinated';
    $vaccinationDate = trim($_POST['vaccination_date'] ?? date('Y-m-d'));
    $remarks         = trim($_POST['remarks'] ?? '');

    if (!$bookingId || !$childId) {
        setFlash('error', 'Invalid appointment reference.');
    } else {
        try {
            // Verify appointment belongs to this hospital
            $stmtChk = $pdo->prepare("
                SELECT b.*, c.child_name, v.vaccine_name 
                FROM bookings b
                JOIN children c ON b.child_id = c.child_id
                JOIN vaccines v ON b.vaccine_id = v.vaccine_id
                WHERE b.booking_id = ? AND b.hospital_id = ?
            ");
            $stmtChk->execute([$bookingId, $hospitalId]);
            $bkg = $stmtChk->fetch();

            if (!$bkg) {
                setFlash('error', 'Appointment not found or unauthorized for your healthcare facility.');
            } else {
                $pdo->beginTransaction();

                // 1. Insert or Update vaccination_records table
                $stmtRecChk = $pdo->prepare("SELECT record_id FROM vaccination_records WHERE booking_id = ? LIMIT 1");
                $stmtRecChk->execute([$bookingId]);
                $existingRecord = $stmtRecChk->fetch();

                if ($existingRecord) {
                    $stmtRecUp = $pdo->prepare("
                        UPDATE vaccination_records 
                        SET vaccination_date = ?, status = ?, remarks = ? 
                        WHERE booking_id = ?
                    ");
                    $stmtRecUp->execute([$vaccinationDate, $vaccineStatus, $remarks, $bookingId]);
                } else {
                    $stmtRecIns = $pdo->prepare("
                        INSERT INTO vaccination_records (booking_id, child_id, vaccination_date, status, remarks) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmtRecIns->execute([$bookingId, $childId, $vaccinationDate, $vaccineStatus, $remarks]);
                }

                // 2. Update booking status to 'Completed'
                $stmtBkUp = $pdo->prepare("UPDATE bookings SET status = 'Completed' WHERE booking_id = ?");
                $stmtBkUp->execute([$bookingId]);

                $pdo->commit();

                $badgeIcon = ($vaccineStatus === 'Vaccinated') ? '✅' : '⚠️';
                setFlash('success', "{$badgeIcon} Vaccination status updated to '{$vaccineStatus}' for {$bkg['child_name']} ({$bkg['vaccine_name']}) on " . formatDate($vaccinationDate, 'd M Y') . ".");
                header('Location: appointments.php?filter=' . urlencode($filter));
                exit;
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('error', 'Error updating vaccination status: ' . $e->getMessage());
        }
    }
}
}

// Fetch Appointments for this Hospital
$appointments = [];
$counts = ['Pending' => 0, 'Approved' => 0, 'Today' => 0, 'Completed' => 0, 'All' => 0];

if ($pdo) {
    try {
        // Compute Counts
        $counts['Pending']   = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE hospital_id = $hospitalId AND status = 'Pending'")->fetchColumn();
        $counts['Approved']  = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE hospital_id = $hospitalId AND status = 'Approved'")->fetchColumn();
        $counts['Today']     = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE hospital_id = $hospitalId AND appointment_date = CURRENT_DATE AND status IN ('Approved', 'Completed')")->fetchColumn();
        $counts['Completed'] = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE hospital_id = $hospitalId AND status = 'Completed'")->fetchColumn();
        $counts['All']       = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE hospital_id = $hospitalId")->fetchColumn();

        // If no explicit filter in URL and there are no pending requests, default to Approved
        if (!isset($_GET['filter']) && $counts['Pending'] === 0 && $counts['Approved'] > 0) {
            $filter = 'Approved';
        }

        // Build main query
        $query = "
            SELECT b.*, c.child_name, c.gender, c.date_of_birth, c.address as child_address, c.notes as child_notes,
                   v.vaccine_name, v.age_group, v.description as vaccine_desc,
                   p.name as parent_name, p.phone as parent_phone, p.email as parent_email,
                   vr.record_id, vr.vaccination_date, vr.status as vax_status, vr.remarks as clinical_remarks
            FROM bookings b
            JOIN children c ON b.child_id = c.child_id
            JOIN vaccines v ON b.vaccine_id = v.vaccine_id
            JOIN parents p ON c.parent_id = p.parent_id
            LEFT JOIN vaccination_records vr ON b.booking_id = vr.booking_id
            WHERE b.hospital_id = ?
        ";
        $params = [$hospitalId];

        if ($filter === 'Pending') {
            $query .= " AND b.status = 'Pending'";
            $query .= " ORDER BY b.booking_date DESC, b.booking_id DESC";
        } elseif ($filter === 'Approved') {
            $query .= " AND b.status = 'Approved'";
            $query .= " ORDER BY b.appointment_date ASC, b.booking_id DESC";
        } elseif ($filter === 'Today') {
            $query .= " AND b.appointment_date = CURRENT_DATE";
            $query .= " ORDER BY b.appointment_time ASC, b.booking_id DESC";
        } elseif ($filter === 'Completed') {
            $query .= " AND b.status = 'Completed'";
            $query .= " ORDER BY b.appointment_date DESC, b.booking_id DESC";
        } else {
            $query .= " ORDER BY b.booking_id DESC";
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $appointments = $stmt->fetchAll();

    } catch (Exception $e) {
        setFlash('error', 'Error loading appointments: ' . $e->getMessage());
    }
}

$pageTitle = 'Patient Appointments';
$activePage = 'hospital_appointments';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-panel">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>
    <div class="container">
        <div class="page-inner">

            <!-- Page Header -->
            <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                <div>
                    <h3 class="fw-bold mb-1">Patient Appointments & Vaccine Status</h3>
                    <h6 class="op-7 mb-0 text-muted">Appointments booked by parents and approved by admin &bull; Update status to Vaccinated or Not Vaccinated</h6>
                </div>
                <div class="ms-md-auto py-2 py-md-0 d-flex gap-2">
                    <a href="records.php" class="btn btn-outline-success btn-round">
                        <i class="fas fa-clipboard-check me-1"></i> View Completed Records Log
                    </a>
                </div>
            </div>

            <?= displayFlash() ?>

            <!-- Filter Tabs (Kaiadmin Style) -->
            <div class="card card-round shadow-sm mb-4">
                <div class="card-body p-2">
                    <ul class="nav nav-pills nav-secondary">
                        <li class="nav-item">
                            <a class="nav-link <?= ($filter === 'Pending') ? 'active' : '' ?>" href="appointments.php?filter=Pending">
                                <i class="fas fa-clock me-1"></i> Pending Requests
                                <span class="badge <?= ($counts['Pending'] > 0) ? 'bg-warning text-dark' : 'bg-light text-muted' ?> ms-1"><?= $counts['Pending'] ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= ($filter === 'Approved') ? 'active' : '' ?>" href="appointments.php?filter=Approved">
                                <i class="fas fa-check-circle me-1"></i> Approved / Scheduled
                                <span class="badge bg-info text-white ms-1"><?= $counts['Approved'] ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= ($filter === 'Today') ? 'active' : '' ?>" href="appointments.php?filter=Today">
                                <i class="fas fa-calendar-day me-1"></i> Today's Schedule
                                <span class="badge bg-danger ms-1"><?= $counts['Today'] ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= ($filter === 'Completed') ? 'active' : '' ?>" href="appointments.php?filter=Completed">
                                <i class="fas fa-check-double me-1"></i> Completed
                                <span class="badge bg-success ms-1"><?= $counts['Completed'] ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= ($filter === 'All') ? 'active' : '' ?>" href="appointments.php?filter=All">
                                <i class="fas fa-list me-1"></i> All Appointments
                                <span class="badge bg-secondary ms-1"><?= $counts['All'] ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Appointments Data Table -->
            <div class="card card-round shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0">
                            <?php if ($filter === 'Pending'): ?>
                                <i class="fas fa-clock text-warning me-2"></i> Pending Parent Booking Requests Awaiting Schedule & Approval
                            <?php elseif ($filter === 'Approved'): ?>
                                <i class="fas fa-bell text-primary me-2"></i> Confirmed Appointments Awaiting Clinical Update
                            <?php elseif ($filter === 'Today'): ?>
                                <i class="fas fa-calendar-day text-danger me-2"></i> Patients Arriving Today
                            <?php elseif ($filter === 'Completed'): ?>
                                <i class="fas fa-check-double text-success me-2"></i> Completed Vaccination Visits
                            <?php else: ?>
                                <i class="fas fa-hospital-user text-primary me-2"></i> Master Patient Appointments List
                            <?php endif; ?>
                        </h5>
                        <small class="text-muted">
                            <?php if ($filter === 'Pending'): ?>
                                Review incoming booking requests from parents, assign confirmed appointment date & time, and send to parents
                            <?php else: ?>
                                Once vaccination is completed, click "Update Vaccine Status" to record clinical status and batch details
                            <?php endif; ?>
                        </small>
                    </div>
                    <span class="badge bg-primary"><?= count($appointments) ?> Appointments</span>
                </div>
                <div class="card-body">
                    <?php if (empty($appointments)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-clipboard fa-3x mb-3 text-muted"></i>
                            <h5 class="fw-bold">No Appointments Found</h5>
                            <p class="small text-muted mb-0">No patient bookings match the selected filter category.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle datatable-custom">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Ref ID</th>
                                        <th>Child Patient</th>
                                        <th>Parent Contact</th>
                                        <th>Vaccine Required</th>
                                        <th><?= ($filter === 'Pending') ? 'Requested Date' : 'Scheduled Date & Time' ?></th>
                                        <th>Clinical Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $app): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-dark">#BKG-<?= str_pad($app['booking_id'], 3, '0', STR_PAD_LEFT) ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="avatar-xs">
                                                        <span class="avatar-title rounded-circle bg-<?= ($app['gender'] === 'Female') ? 'danger' : 'primary' ?> text-white font-weight-bold">
                                                            <?= strtoupper(substr($app['child_name'], 0, 1)) ?>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <strong class="text-primary"><?= htmlspecialchars($app['child_name']) ?></strong>
                                                        <div class="small text-muted">
                                                            <?= htmlspecialchars($app['gender']) ?> &bull; <?= calculateAge($app['date_of_birth']) ?>
                                                        </div>
                                                        <?php if (!empty($app['child_notes'])): ?>
                                                            <div class="badge bg-warning text-dark" style="font-size: 0.65rem;" title="<?= htmlspecialchars($app['child_notes']) ?>">
                                                                <i class="fas fa-exclamation-triangle me-1"></i> Medical Notes
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($app['parent_name']) ?></div>
                                                <div class="small text-muted"><i class="fas fa-phone me-1"></i> <?= htmlspecialchars($app['parent_phone'] ?? '-') ?></div>
                                                <?php if (!empty($app['parent_email'])): ?>
                                                    <div class="small text-muted"><i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($app['parent_email']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($app['vaccine_name']) ?></div>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($app['age_group']) ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                    $isToday = ($app['appointment_date'] === date('Y-m-d'));
                                                ?>
                                                <div class="fw-bold <?= $isToday && $app['status'] !== 'Pending' ? 'text-danger' : 'text-dark' ?>">
                                                    <i class="far fa-calendar-alt me-1 text-muted"></i> <?= formatDate($app['appointment_date'], 'd M Y') ?>
                                                </div>
                                                <?php if (!empty($app['appointment_time'])): ?>
                                                    <div class="small text-primary fw-bold">
                                                        <i class="far fa-clock me-1"></i> <?= formatTime($app['appointment_time']) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($isToday && $app['status'] !== 'Pending'): ?>
                                                    <span class="badge bg-danger" style="font-size: 0.65rem;">TODAY</span>
                                                <?php elseif (!empty($app['approval_date'])): ?>
                                                    <small class="text-muted d-block">Approved: <?= formatDate($app['approval_date'], 'd M') ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($app['status'] === 'Pending'): ?>
                                                    <span class="badge bg-warning text-dark"><i class="fas fa-hourglass-half me-1"></i> Awaiting Your Approval</span>
                                                <?php elseif ($app['status'] === 'Rejected'): ?>
                                                    <span class="badge bg-danger"><i class="fas fa-times me-1"></i> Declined</span>
                                                    <?php if (!empty($app['hospital_notes'])): ?>
                                                        <div class="small text-muted mt-1" title="<?= htmlspecialchars($app['hospital_notes']) ?>"><?= htmlspecialchars(substr($app['hospital_notes'], 0, 25)) ?>...</div>
                                                    <?php endif; ?>
                                                <?php elseif (!empty($app['vax_status'])): ?>
                                                    <?= getStatusBadge($app['vax_status']) ?>
                                                    <div class="small text-muted mt-1">
                                                        <i class="fas fa-calendar-check me-1"></i> <?= formatDate($app['vaccination_date'], 'd M Y') ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge bg-info text-white"><i class="fas fa-calendar-check me-1"></i> Scheduled</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($app['status'] === 'Pending'): ?>
                                                    <div class="d-flex gap-1">
                                                        <button type="button" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm text-nowrap" onclick='openApproveModal(<?= json_encode($app) ?>)'>
                                                            <i class="fas fa-calendar-check me-1"></i> Approve & Schedule
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-2" onclick='openRejectModal(<?= json_encode($app) ?>)' title="Decline Request">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                <?php elseif ($app['status'] === 'Approved' || $app['status'] === 'Completed'): ?>
                                                    <!-- Action: Update Vaccine Status Modal Trigger -->
                                                    <button type="button" class="btn btn-sm <?= !empty($app['vax_status']) ? 'btn-outline-primary' : 'btn-success text-white' ?> rounded-pill px-3 shadow-sm" onclick='openUpdateModal(<?= json_encode($app) ?>)'>
                                                        <i class="fas fa-syringe me-1"></i> <?= !empty($app['vax_status']) ? 'Edit Status' : 'Update Status' ?>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted small">N/A</span>
                                                <?php endif; ?>
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

    <!-- ========================================================================= -->
    <!-- UPDATE VACCINE STATUS MODAL (Prompt Requirement)                          -->
    <!-- "If vaccination is completed they will update the status to Vaccinated   -->
    <!-- or not."                                                                  -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="appointments.php?filter=<?= urlencode($filter) ?>">
                    <input type="hidden" name="booking_id" id="modal_booking_id">
                    <input type="hidden" name="child_id" id="modal_child_id">

                    <div class="modal-header bg-light">
                        <h5 class="modal-title fw-bold text-primary" id="updateStatusModalLabel">
                            <i class="fas fa-syringe me-2"></i> Update Vaccine Status
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <!-- Patient & Vaccine Information Summary Box -->
                        <div class="p-3 bg-light rounded-3 mb-3 border">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Child Patient:</span>
                                <strong class="text-dark" id="modal_child_name"></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Parent Contact:</span>
                                <span id="modal_parent_contact" class="small"></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Vaccine to Administer:</span>
                                <strong class="text-primary" id="modal_vaccine_name"></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted small">Scheduled Appointment:</span>
                                <span class="fw-bold" id="modal_app_date"></span>
                            </div>
                        </div>

                        <!-- Medical Allergy Notice if present -->
                        <div id="modal_notes_box" class="alert alert-warning p-2 small mb-3 d-none">
                            <strong><i class="fas fa-exclamation-triangle me-1"></i> Patient Health Notes:</strong>
                            <span id="modal_child_notes"></span>
                        </div>

                        <!-- Status Selection (Vaccinated / Not Vaccinated) -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Vaccination Status <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="vaccine_status" id="status_vaccinated" value="Vaccinated" checked onchange="toggleStatusNotice('Vaccinated')">
                                    <label class="btn btn-outline-success w-100 py-2 fw-bold" for="status_vaccinated">
                                        <i class="fas fa-check-circle me-1"></i> Vaccinated
                                    </label>
                                </div>
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="vaccine_status" id="status_not_vaccinated" value="Not Vaccinated" onchange="toggleStatusNotice('Not Vaccinated')">
                                    <label class="btn btn-outline-danger w-100 py-2 fw-bold" for="status_not_vaccinated">
                                        <i class="fas fa-times-circle me-1"></i> Not Vaccinated
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Date Administered / Recorded -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Date of Administration / Record <span class="text-danger">*</span></label>
                            <input type="date" name="vaccination_date" id="modal_vax_date" class="form-control" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <!-- Clinical Remarks / Batch Number -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold" id="remarks_label">
                                Clinical Remarks & Batch Number
                            </label>
                            <textarea name="remarks" id="modal_remarks" class="form-control" rows="3" placeholder="e.g. Batch #BCG-2026-X administered 0.05ml left upper arm. Child observed with no adverse event."></textarea>
                            <div class="form-text small" id="remarks_hint">
                                If 'Not Vaccinated' is selected, please specify reason (e.g. child presented with fever/illness, missed appointment, parent deferred).
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success text-white px-4">
                            <i class="fas fa-save me-1"></i> Confirm & Save Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- APPROVE & SCHEDULE APPOINTMENT MODAL                                      -->
    <!-- Hospital confirms appointment date and assigns time to parents            -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="approveAppointmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="appointments.php?filter=<?= urlencode($filter) ?>">
                    <input type="hidden" name="action" value="approve_appointment">
                    <input type="hidden" name="booking_id" id="appr_modal_booking_id">

                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title fw-bold text-white">
                            <i class="fas fa-calendar-check me-2"></i> Approve & Schedule Appointment
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <!-- Summary -->
                        <div class="p-3 bg-light rounded-3 mb-3 border">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Booking Reference:</span>
                                <span class="font-monospace fw-bold text-primary" id="appr_modal_ref"></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Child Patient:</span>
                                <strong class="text-dark" id="appr_modal_child"></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Parent Contact:</span>
                                <span id="appr_modal_parent" class="small"></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Vaccine Requested:</span>
                                <strong class="text-primary" id="appr_modal_vaccine"></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted small">Parent Requested Date:</span>
                                <span class="badge bg-secondary" id="appr_modal_req_date"></span>
                            </div>
                        </div>

                        <!-- Date Selection -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Confirmed Appointment Date <span class="text-danger">*</span></label>
                            <input type="date" name="appointment_date" id="appr_modal_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                            <div class="form-text small">Hospital can keep the parent's requested date or adjust to available slots.</div>
                        </div>

                        <!-- Time Selection -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Confirmed Appointment Time <span class="text-danger">*</span></label>
                            <input type="time" name="appointment_time" id="appr_modal_time" class="form-control" required value="10:00">
                            <div class="form-text small">Specify the arrival time slot (e.g. 09:30 AM, 11:00 AM, 02:30 PM).</div>
                        </div>

                        <!-- Hospital Instructions / Remarks -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Hospital Instructions / Notes for Parent (Optional)</label>
                            <textarea name="hospital_notes" id="appr_modal_notes" class="form-control" rows="2" placeholder="e.g. Please bring previous vaccination card. Arrive 10 minutes before schedule at Pediatric Room 3."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success text-white px-4">
                            <i class="fas fa-check-circle me-1"></i> Confirm & Send Schedule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- DECLINE / REJECT APPOINTMENT MODAL                                        -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="rejectAppointmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="appointments.php?filter=<?= urlencode($filter) ?>">
                    <input type="hidden" name="action" value="reject_appointment">
                    <input type="hidden" name="booking_id" id="rej_modal_booking_id">

                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title fw-bold text-white">
                            <i class="fas fa-times-circle me-2"></i> Decline Appointment Request
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <p class="text-muted">Are you sure you want to decline this appointment request for <strong id="rej_modal_child"></strong> (<span id="rej_modal_vaccine"></span>)?</p>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Reason for Declining (Optional)</label>
                            <textarea name="hospital_notes" class="form-control" rows="2" placeholder="e.g. Schedule fully booked for this date or doctor unavailable."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger px-4">
                            <i class="fas fa-times me-1"></i> Decline Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openApproveModal(app) {
            document.getElementById('appr_modal_booking_id').value = app.booking_id;
            document.getElementById('appr_modal_ref').innerText = "#BKG-" + String(app.booking_id).padStart(3, '0');
            document.getElementById('appr_modal_child').innerText = app.child_name;
            document.getElementById('appr_modal_parent').innerText = app.parent_name + " (" + (app.parent_phone || '-') + ")";
            document.getElementById('appr_modal_vaccine').innerText = app.vaccine_name + " (" + app.age_group + ")";
            document.getElementById('appr_modal_req_date').innerText = app.appointment_date;
            document.getElementById('appr_modal_date').value = app.appointment_date || '<?= date('Y-m-d') ?>';
            document.getElementById('appr_modal_time').value = app.appointment_time ? app.appointment_time.substring(0, 5) : '10:00';
            document.getElementById('appr_modal_notes').value = app.hospital_notes || '';

            var modal = new bootstrap.Modal(document.getElementById('approveAppointmentModal'));
            modal.show();
        }

        function openRejectModal(app) {
            document.getElementById('rej_modal_booking_id').value = app.booking_id;
            document.getElementById('rej_modal_child').innerText = app.child_name;
            document.getElementById('rej_modal_vaccine').innerText = app.vaccine_name;

            var modal = new bootstrap.Modal(document.getElementById('rejectAppointmentModal'));
            modal.show();
        }

        function openUpdateModal(app) {
            document.getElementById('modal_booking_id').value = app.booking_id;
            document.getElementById('modal_child_id').value = app.child_id;
            document.getElementById('modal_child_name').innerText = app.child_name;
            document.getElementById('modal_parent_contact').innerText = app.parent_name + " (" + (app.parent_phone || '-') + ")";
            document.getElementById('modal_vaccine_name').innerText = app.vaccine_name + " (" + app.age_group + ")";
            document.getElementById('modal_app_date').innerText = app.appointment_date + (app.appointment_time ? " at " + app.appointment_time : "");

            // Notes
            const notesBox = document.getElementById('modal_notes_box');
            if (app.child_notes && app.child_notes.trim() !== '') {
                notesBox.classList.remove('d-none');
                document.getElementById('modal_child_notes').innerText = app.child_notes;
            } else {
                notesBox.classList.add('d-none');
            }

            // Existing status / remarks if already recorded
            if (app.vax_status === 'Not Vaccinated') {
                document.getElementById('status_not_vaccinated').checked = true;
                toggleStatusNotice('Not Vaccinated');
            } else {
                document.getElementById('status_vaccinated').checked = true;
                toggleStatusNotice('Vaccinated');
            }

            document.getElementById('modal_vax_date').value = app.vaccination_date || '<?= date('Y-m-d') ?>';
            document.getElementById('modal_remarks').value = app.clinical_remarks || '';

            var modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
            modal.show();
        }

        function toggleStatusNotice(status) {
            const hint = document.getElementById('remarks_hint');
            const remarks = document.getElementById('modal_remarks');
            if (status === 'Not Vaccinated') {
                hint.innerText = "Please specify the clinical reason for non-vaccination (e.g., fever, contraindication, guardian deferred).";
                if (!remarks.value) {
                    remarks.placeholder = "e.g. Infant presented with temperature 38.5°C. Doctor advised rescheduling next week.";
                }
            } else {
                hint.innerText = "Include dosage, site of injection, and manufacturer batch number for record verification.";
                if (!remarks.value) {
                    remarks.placeholder = "e.g. Batch #BCG-2026-X administered 0.05ml left deltoid. No adverse reaction.";
                }
            }
        }

        // Auto-open modal if open_status parameter is in URL
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($openStatusId > 0): ?>
                <?php
                    $targetApp = null;
                    foreach ($appointments as $a) {
                        if ($a['booking_id'] == $openStatusId) {
                            $targetApp = $a;
                            break;
                        }
                    }
                ?>
                <?php if ($targetApp): ?>
                    openUpdateModal(<?= json_encode($targetApp) ?>);
                <?php endif; ?>
            <?php endif; ?>
        });
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
