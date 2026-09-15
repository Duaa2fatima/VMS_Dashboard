<?php
/**
 * My Bookings - Parent Appointments Tracking & Management
 * Child Vaccination Management System (VMS)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
requireParent();

$currentParent = getCurrentParent();
$parentId = $currentParent['id'];
$statusFilter = $_GET['status'] ?? 'All';

// Handle Cancel Booking (Only if status is Pending)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $action = $_POST['action'] ?? '';
    if ($action === 'cancel_booking') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        try {
            // Verify booking belongs to this parent's child and is Pending
            $stmtChk = $pdo->prepare("
                SELECT b.booking_id, b.status, c.child_name, v.vaccine_name
                FROM bookings b
                JOIN children c ON b.child_id = c.child_id
                JOIN vaccines v ON b.vaccine_id = v.vaccine_id
                WHERE b.booking_id = ? AND c.parent_id = ?
            ");
            $stmtChk->execute([$bookingId, $parentId]);
            $bkg = $stmtChk->fetch();

            if (!$bkg) {
                setFlash('error', 'Booking request not found or unauthorized.');
            } elseif ($bkg['status'] !== 'Pending') {
                setFlash('warning', 'Only pending booking requests can be cancelled. Approved appointments are already scheduled with the healthcare center.');
            } else {
                $stmtDel = $pdo->prepare("DELETE FROM bookings WHERE booking_id = ?");
                $stmtDel->execute([$bookingId]);
                setFlash('info', "Booking request #BKG-" . str_pad($bookingId, 3, '0', STR_PAD_LEFT) . " for '{$bkg['child_name']}' has been cancelled.");
                header('Location: bookings.php');
                exit;
            }
        } catch (Exception $e) {
            setFlash('error', 'Error cancelling booking: ' . $e->getMessage());
        }
    }
}

// Fetch Bookings
$bookings = [];
$counts = ['All' => 0, 'Pending' => 0, 'Approved' => 0, 'Completed' => 0, 'Rejected' => 0];

if ($pdo) {
    try {
        // Status counts
        $stmtCounts = $pdo->prepare("
            SELECT b.status, COUNT(*) as cnt 
            FROM bookings b 
            JOIN children c ON b.child_id = c.child_id 
            WHERE c.parent_id = ? 
            GROUP BY b.status
        ");
        $stmtCounts->execute([$parentId]);
        $rows = $stmtCounts->fetchAll();
        foreach ($rows as $r) {
            if (isset($counts[$r['status']])) {
                $counts[$r['status']] = (int)$r['cnt'];
            }
            $counts['All'] += (int)$r['cnt'];
        }

        // Fetch Bookings list with status filter
        $query = "
            SELECT b.*, c.child_name, c.date_of_birth, c.gender,
                   v.vaccine_name, v.age_group,
                   h.hospital_name, h.location, h.phone as hospital_phone, h.address as hospital_address,
                   vr.status as vax_status, vr.vaccination_date, vr.remarks as clinical_remarks
            FROM bookings b
            JOIN children c ON b.child_id = c.child_id
            JOIN vaccines v ON b.vaccine_id = v.vaccine_id
            JOIN hospitals h ON b.hospital_id = h.hospital_id
            LEFT JOIN vaccination_records vr ON b.booking_id = vr.booking_id
            WHERE c.parent_id = ?
        ";
        $params = [$parentId];

        if ($statusFilter !== 'All' && in_array($statusFilter, ['Pending', 'Approved', 'Completed', 'Rejected'])) {
            $query .= " AND b.status = ?";
            $params[] = $statusFilter;
        }

        $query .= " ORDER BY b.appointment_date DESC, b.booking_id DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $bookings = $stmt->fetchAll();

    } catch (Exception $e) {
        setFlash('error', 'Database error: ' . $e->getMessage());
    }
}

$pageTitle = 'My Bookings';
$activePage = 'parent_bookings';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-panel">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>
    <div class="container">
        <div class="page-inner">

            <!-- Page Header -->
            <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                <div>
                    <h3 class="fw-bold mb-1">My Bookings</h3>
                    <h6 class="op-7 mb-0 text-muted">Track status of appointment requests, scheduled hospital dates, and clinical completion</h6>
                </div>
                <div class="ms-md-auto py-2 py-md-0">
                    <a href="book-appointment.php" class="btn btn-primary btn-round shadow-sm">
                        <i class="fas fa-plus me-1"></i> Book New Appointment
                    </a>
                </div>
            </div>

            <?= displayFlash() ?>

            <!-- Status Filter Tabs -->
            <div class="card card-round shadow-sm mb-4">
                <div class="card-body p-2">
                    <ul class="nav nav-pills nav-secondary">
                        <li class="nav-item">
                            <a class="nav-link <?= ($statusFilter === 'All') ? 'active' : '' ?>" href="bookings.php?status=All">
                                All <span class="badge bg-light text-dark ms-1"><?= $counts['All'] ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= ($statusFilter === 'Pending') ? 'active' : '' ?>" href="bookings.php?status=Pending">
                                Pending Approval <span class="badge bg-warning text-dark ms-1"><?= $counts['Pending'] ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= ($statusFilter === 'Approved') ? 'active' : '' ?>" href="bookings.php?status=Approved">
                                Approved / Scheduled <span class="badge bg-info text-white ms-1"><?= $counts['Approved'] ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= ($statusFilter === 'Completed') ? 'active' : '' ?>" href="bookings.php?status=Completed">
                                Completed <span class="badge bg-success ms-1"><?= $counts['Completed'] ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= ($statusFilter === 'Rejected') ? 'active' : '' ?>" href="bookings.php?status=Rejected">
                                Rejected <span class="badge bg-danger ms-1"><?= $counts['Rejected'] ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Bookings List Table -->
            <div class="card card-round shadow-sm">
                <div class="card-body">
                    <?php if (empty($bookings)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-calendar-times fa-3x mb-3 text-muted"></i>
                            <h5 class="fw-bold">No Bookings Found</h5>
                            <p class="small text-muted mb-3">No appointment records match the selected filter.</p>
                            <a href="book-appointment.php" class="btn btn-primary btn-sm rounded-pill">
                                <i class="fas fa-calendar-plus me-1"></i> Book a Hospital Visit
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle datatable-custom">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Ref ID</th>
                                        <th>Child</th>
                                        <th>Vaccine</th>
                                        <th>Hospital Facility</th>
                                        <th>Appointment Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $b): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-dark">#BKG-<?= str_pad($b['booking_id'], 3, '0', STR_PAD_LEFT) ?></span>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($b['child_name']) ?></strong>
                                                <div class="small text-muted"><?= calculateAge($b['date_of_birth']) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-primary"><?= htmlspecialchars($b['vaccine_name']) ?></div>
                                                <small class="badge bg-secondary"><?= htmlspecialchars($b['age_group']) ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($b['hospital_name']) ?></div>
                                                <div class="small text-muted"><i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($b['location'] ?? '-') ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= formatDate($b['appointment_date'], 'd M Y') ?></div>
                                                <?php if (!empty($b['appointment_time'])): ?>
                                                    <div class="badge bg-light text-primary border border-primary-subtle mt-1">
                                                        <i class="far fa-clock me-1"></i> <?= formatTime($b['appointment_time']) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="small text-muted">Booked: <?= formatDate($b['booking_date'], 'd M') ?></div>
                                            </td>
                                            <td>
                                                <?= getStatusBadge($b['status']) ?>
                                                <?php if (!empty($b['vax_status'])): ?>
                                                    <div class="mt-1">
                                                        <?= getStatusBadge($b['vax_status']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" title="View Booking Details" onclick='showBookingModal(<?= json_encode($b) ?>)'>
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($b['status'] === 'Pending'): ?>
                                                        <button type="button" class="btn btn-outline-danger" title="Cancel Request" onclick="confirmCancel(<?= $b['booking_id'] ?>)">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php elseif ($b['status'] === 'Completed'): ?>
                                                        <a href="reports.php?child_id=<?= $b['child_id'] ?>" class="btn btn-outline-success" title="View Report">
                                                            <i class="fas fa-certificate"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
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

    <!-- Booking Details Modal -->
    <div class="modal fade" id="bookingDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="m_ref_title">Booking Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="list-group list-group-flush mb-3">
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted small">Child Name:</span>
                            <span class="fw-bold" id="m_child"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted small">Vaccine:</span>
                            <span class="fw-bold text-primary" id="m_vaccine"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted small">Hospital Facility:</span>
                            <span class="fw-bold" id="m_hospital"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted small">Facility Address:</span>
                            <span class="small text-end" id="m_address"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted small">Scheduled Date:</span>
                            <span class="fw-bold text-success" id="m_date"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted small">Confirmed Time:</span>
                            <span class="fw-bold text-primary" id="m_time"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted small">Booking Status:</span>
                            <span id="m_status"></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2" id="m_approval_row">
                            <span class="text-muted small">Hospital Confirmation Date:</span>
                            <span class="small" id="m_approval_date"></span>
                        </li>
                        <li class="list-group-item d-flex flex-column px-0 py-2" id="m_notes_row">
                            <span class="text-muted small mb-1">Hospital Instructions / Notes:</span>
                            <span class="small bg-light p-2 rounded border" id="m_notes"></span>
                        </li>
                    </ul>

                    <div id="m_vax_section" class="p-3 bg-light rounded-3 d-none">
                        <h6 class="fw-bold text-success mb-1"><i class="fas fa-syringe me-1"></i> Clinical Vaccination Result:</h6>
                        <div class="small mb-1">Status: <strong id="m_vax_status"></strong> on <span id="m_vax_date"></span></div>
                        <div class="small text-muted">Remarks: <span id="m_vax_remarks" class="fst-italic"></span></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Form for Cancelling Booking -->
    <form id="cancelBookingForm" method="POST" action="bookings.php" style="display: none;">
        <input type="hidden" name="action" value="cancel_booking">
        <input type="hidden" name="booking_id" id="cancel_bkg_id">
    </form>

    <script>
        function showBookingModal(bkg) {
            document.getElementById('m_ref_title').innerText = "Booking Details #BKG-" + String(bkg.booking_id).padStart(3, '0');
            document.getElementById('m_child').innerText = bkg.child_name;
            document.getElementById('m_vaccine').innerText = bkg.vaccine_name + " (" + bkg.age_group + ")";
            document.getElementById('m_hospital').innerText = bkg.hospital_name;
            document.getElementById('m_address').innerText = (bkg.hospital_address || '') + " (" + (bkg.location || '') + ")";
            document.getElementById('m_date').innerText = bkg.appointment_date;
            document.getElementById('m_time').innerText = bkg.appointment_time ? bkg.appointment_time : 'Awaiting hospital assignment';
            document.getElementById('m_status').innerHTML = '<span class="badge bg-primary">' + bkg.status + '</span>';

            if (bkg.approval_date) {
                document.getElementById('m_approval_row').style.display = 'flex';
                document.getElementById('m_approval_date').innerText = bkg.approval_date;
            } else {
                document.getElementById('m_approval_row').style.display = 'none';
            }

            if (bkg.hospital_notes) {
                document.getElementById('m_notes_row').style.display = 'flex';
                document.getElementById('m_notes').innerText = bkg.hospital_notes;
            } else {
                document.getElementById('m_notes_row').style.display = 'none';
            }

            const vaxSec = document.getElementById('m_vax_section');
            if (bkg.vax_status) {
                vaxSec.classList.remove('d-none');
                document.getElementById('m_vax_status').innerText = bkg.vax_status;
                document.getElementById('m_vax_date').innerText = bkg.vaccination_date || '-';
                document.getElementById('m_vax_remarks').innerText = bkg.clinical_remarks || 'None';
            } else {
                vaxSec.classList.add('d-none');
            }

            var modal = new bootstrap.Modal(document.getElementById('bookingDetailModal'));
            modal.show();
        }

        function confirmCancel(bkgId) {
            if (confirm("Are you sure you want to cancel this pending booking request?")) {
                document.getElementById('cancel_bkg_id').value = bkgId;
                document.getElementById('cancelBookingForm').submit();
            }
        }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
