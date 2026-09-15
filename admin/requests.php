<?php
/**
 * Parent Appointment Requests - Admin View & Audit Log
 * Child Vaccination Management System (VMS)
 * Note: Approval & scheduling is managed by the assigned healthcare hospital.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$pageTitle = 'Request from Parents (Hospital Approval Queue)';
$activePage = 'admin_requests';

// Filter Tab (Pending vs All)
$viewMode = $_GET['view'] ?? 'pending';
$requests = [];

if ($pdo) {
    try {
        $statusSql = ($viewMode === 'pending') ? "WHERE b.status = 'Pending'" : "WHERE 1=1";
        $stmt = $pdo->query("SELECT b.*, c.child_name, c.date_of_birth, c.gender, c.notes as child_notes,
                p.name as parent_name, p.phone as parent_phone, p.email as parent_email,
                v.vaccine_name, v.age_group,
                h.hospital_name, h.location as hospital_location, h.phone as hospital_phone,
                adm.name as approved_by_admin
            FROM bookings b
            JOIN children c ON b.child_id = c.child_id
            JOIN parents p ON c.parent_id = p.parent_id
            JOIN vaccines v ON b.vaccine_id = v.vaccine_id
            JOIN hospitals h ON b.hospital_id = h.hospital_id
            LEFT JOIN admins adm ON b.admin_id = adm.admin_id
            $statusSql
            ORDER BY b.booking_date DESC, b.booking_id DESC");
        $requests = $stmt->fetchAll();
    } catch (Exception $e) {
        setFlash('error', 'Error loading requests: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php'; 
?>

<div class="main-panel">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-inner">
            
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1"><i class="fas fa-envelope-open-text text-primary me-2"></i> Request from Parents</h3>
                    <ul class="breadcrumbs mb-0 ps-0 list-unstyled d-flex gap-2 text-muted small">
                        <li><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                        <li>/</li>
                        <li class="active">Hospital Booking & Schedule Queue (View-Only)</li>
                    </ul>
                </div>
                <div class="btn-group" role="group">
                    <a href="requests.php?view=pending" class="btn btn-sm <?= $viewMode === 'pending' ? 'btn-warning text-dark fw-bold' : 'btn-outline-warning' ?>">
                        <i class="fas fa-clock me-1"></i> Pending Queue (<?= $pendingRequestsCount ?>)
                    </a>
                    <a href="requests.php?view=all" class="btn btn-sm <?= $viewMode === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-list me-1"></i> All Request History
                    </a>
                </div>
            </div>

            <?= displayFlash() ?>

            <!-- Information Callout -->
            <div class="alert alert-info d-flex align-items-center mb-4 py-2 px-3 rounded-3 shadow-sm">
                <i class="fas fa-info-circle fa-lg me-2 text-info"></i>
                <div class="small">
                    <strong>Admin Audit Note:</strong> Parent appointment requests are approved and scheduled directly by the assigned hospital facility. Admins have complete visibility to monitor queues and schedules across all centers.
                </div>
            </div>

            <!-- Requests Table Card -->
            <div class="card card-round shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div class="card-title text-dark">
                        <?= $viewMode === 'pending' ? 'Pending Parent Appointment Requests (Awaiting Hospital Approval & Time Assignment)' : 'Master Booking & Request Log' ?>
                    </div>
                    <span class="badge bg-secondary"><?= count($requests) ?> Requests</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover datatable-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Booking Ref</th>
                                    <th>Child Profile</th>
                                    <th>Requested Vaccine</th>
                                    <th>Target Hospital</th>
                                    <th>Appointment Date & Time</th>
                                    <th>Hospital Status</th>
                                    <th class="text-center">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($requests)): ?>
                                    <?php foreach ($requests as $req): ?>
                                        <tr>
                                            <td class="fw-bold text-primary font-monospace">
                                                #BK-<?= str_pad($req['booking_id'], 4, '0', STR_PAD_LEFT) ?>
                                                <div class="small text-muted font-sans-serif">Booked: <?= formatDate($req['booking_date']) ?></div>
                                            </td>
                                            <td>
                                                <a href="child-details.php?id=<?= $req['child_id'] ?>" class="fw-bold text-dark text-decoration-none">
                                                    <?= htmlspecialchars($req['child_name']) ?>
                                                </a>
                                                <div class="small text-muted">
                                                    Parent: <?= htmlspecialchars($req['parent_name']) ?> (<?= htmlspecialchars($req['parent_phone'] ?: 'N/A') ?>)
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($req['vaccine_name']) ?></span>
                                                <div class="small text-muted"><?= htmlspecialchars($req['age_group']) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><i class="fas fa-hospital me-1 text-muted"></i> <?= htmlspecialchars($req['hospital_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($req['hospital_location'] ?: '') ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= formatDate($req['appointment_date']) ?></div>
                                                <?php if (!empty($req['appointment_time'])): ?>
                                                    <div class="small text-primary fw-bold"><i class="far fa-clock me-1"></i><?= formatTime($req['appointment_time']) ?></div>
                                                <?php else: ?>
                                                    <small class="text-muted">Time pending hospital schedule</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($req['status'] === 'Pending'): ?>
                                                    <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> Awaiting Hospital</span>
                                                <?php elseif ($req['status'] === 'Approved'): ?>
                                                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Approved by Hospital</span>
                                                <?php elseif ($req['status'] === 'Rejected'): ?>
                                                    <span class="badge bg-danger"><i class="fas fa-times me-1"></i> Declined by Hospital</span>
                                                <?php else: ?>
                                                    <?= getStatusBadge($req['status']) ?>
                                                <?php endif; ?>
                                                <?php if (!empty($req['approval_date'])): ?>
                                                    <div class="small text-muted mt-1">
                                                        Decided: <?= formatDate($req['approval_date']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm" onclick='openViewModal(<?= json_encode($req) ?>)' title="View Full Details">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- View Booking Details Modal (Read-Only for Admin) -->
    <div class="modal fade" id="viewRequestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-primary">
                        <i class="fas fa-info-circle me-2"></i> Booking Request Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Booking Ref:</span>
                            <span id="modal_bkg_no" class="font-monospace fw-bold text-primary"></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Child Patient:</span>
                            <strong id="modal_child"></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Parent Contact:</span>
                            <span id="modal_parent"></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Vaccine:</span>
                            <strong class="text-primary" id="modal_vaccine"></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Assigned Hospital:</span>
                            <strong id="modal_hospital"></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Appointment Date:</span>
                            <span id="modal_datetime" class="fw-bold"></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Scheduled Time:</span>
                            <span id="modal_time" class="fw-bold text-primary"></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Status:</span>
                            <span id="modal_status_badge"></span>
                        </div>
                    </div>

                    <div id="modal_notes_box" class="alert alert-secondary p-2 small mb-0 d-none">
                        <strong><i class="fas fa-notes-medical me-1"></i> Hospital Notes / Instructions:</strong>
                        <div id="modal_notes" class="mt-1"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function openViewModal(req) {
        document.getElementById('modal_bkg_no').textContent = '#BK-' + String(req.booking_id).padStart(4, '0');
        document.getElementById('modal_child').textContent = req.child_name;
        document.getElementById('modal_parent').textContent = req.parent_name + ' (' + (req.parent_phone || 'N/A') + ')';
        document.getElementById('modal_vaccine').textContent = req.vaccine_name + ' (' + req.age_group + ')';
        document.getElementById('modal_hospital').textContent = req.hospital_name + (req.hospital_location ? ' - ' + req.hospital_location : '');
        document.getElementById('modal_datetime').textContent = req.appointment_date;
        document.getElementById('modal_time').textContent = req.appointment_time ? req.appointment_time : 'Awaiting hospital time slot';
        
        let statusBadge = '<span class="badge bg-secondary">' + req.status + '</span>';
        if (req.status === 'Pending') statusBadge = '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> Pending Hospital Approval</span>';
        else if (req.status === 'Approved') statusBadge = '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Approved by Hospital</span>';
        else if (req.status === 'Rejected') statusBadge = '<span class="badge bg-danger"><i class="fas fa-times me-1"></i> Declined by Hospital</span>';
        else if (req.status === 'Completed') statusBadge = '<span class="badge bg-primary"><i class="fas fa-check-double me-1"></i> Completed</span>';
        document.getElementById('modal_status_badge').innerHTML = statusBadge;

        const notesBox = document.getElementById('modal_notes_box');
        if (req.hospital_notes && req.hospital_notes.trim() !== '') {
            notesBox.classList.remove('d-none');
            document.getElementById('modal_notes').textContent = req.hospital_notes;
        } else {
            notesBox.classList.add('d-none');
        }

        var modal = new bootstrap.Modal(document.getElementById('viewRequestModal'));
        modal.show();
    }
    </script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
