<?php
/**
 * Details of Child - Add, Update & Maintain
 * Child Vaccination Management System (VMS)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
requireParent();

$currentParent = getCurrentParent();
$parentId = $currentParent['id'];

// Handle POST actions: Add, Update, Delete BEFORE HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_child') {
        $childName = trim($_POST['child_name'] ?? '');
        $gender    = in_array($_POST['gender'] ?? '', ['Male', 'Female', 'Other']) ? $_POST['gender'] : 'Male';
        $dob       = trim($_POST['date_of_birth'] ?? '');
        $address   = trim($_POST['address'] ?? ($currentParent['address'] ?? ''));
        $notes     = trim($_POST['notes'] ?? '');

        if (empty($childName) || empty($dob)) {
            setFlash('error', 'Child name and date of birth are required fields.');
        } elseif (strtotime($dob) > time()) {
            setFlash('error', 'Date of birth cannot be in the future.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO children (parent_id, child_name, gender, date_of_birth, address, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$parentId, $childName, $gender, $dob, $address, $notes]);
                $newChildId = $pdo->lastInsertId();

                setFlash('success', "Child profile for '{$childName}' created successfully! You can now book hospital appointments or review immunization dates.");
                header('Location: children.php');
                exit;
            } catch (Exception $e) {
                setFlash('error', 'Error adding child: ' . $e->getMessage());
            }
        }

    } elseif ($action === 'edit_child') {
        $childId   = (int)($_POST['child_id'] ?? 0);
        $childName = trim($_POST['child_name'] ?? '');
        $gender    = in_array($_POST['gender'] ?? '', ['Male', 'Female', 'Other']) ? $_POST['gender'] : 'Male';
        $dob       = trim($_POST['date_of_birth'] ?? '');
        $address   = trim($_POST['address'] ?? '');
        $notes     = trim($_POST['notes'] ?? '');

        if (empty($childName) || empty($dob)) {
            setFlash('error', 'Child name and date of birth are required fields.');
        } elseif (strtotime($dob) > time()) {
            setFlash('error', 'Date of birth cannot be in the future.');
        } else {
            try {
                // Ensure child belongs to this parent
                $stmtChk = $pdo->prepare("SELECT child_id FROM children WHERE child_id = ? AND parent_id = ?");
                $stmtChk->execute([$childId, $parentId]);
                if (!$stmtChk->fetch()) {
                    setFlash('error', 'Unauthorized action: Child profile not found in your account.');
                } else {
                    $stmtUp = $pdo->prepare("UPDATE children SET child_name = ?, gender = ?, date_of_birth = ?, address = ?, notes = ? WHERE child_id = ? AND parent_id = ?");
                    $stmtUp->execute([$childName, $gender, $dob, $address, $notes, $childId, $parentId]);
                    setFlash('success', "Child details for '{$childName}' have been updated successfully.");
                    header('Location: children.php');
                    exit;
                }
            } catch (Exception $e) {
                setFlash('error', 'Error updating child: ' . $e->getMessage());
            }
        }

    } elseif ($action === 'delete_child') {
        $childId = (int)($_POST['child_id'] ?? 0);
        try {
            $stmtDel = $pdo->prepare("DELETE FROM children WHERE child_id = ? AND parent_id = ?");
            $stmtDel->execute([$childId, $parentId]);
            setFlash('info', 'Child record has been removed.');
            header('Location: children.php');
            exit;
        } catch (Exception $e) {
            setFlash('error', 'Error deleting child: ' . $e->getMessage());
        }
    }
}

// Fetch all children for this parent
$children = [];
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM children WHERE parent_id = ? ORDER BY date_of_birth DESC");
        $stmt->execute([$parentId]);
        $children = $stmt->fetchAll();
    } catch (Exception $e) {
        setFlash('error', 'Database error: ' . $e->getMessage());
    }
}

$pageTitle = 'Details of Child';
$activePage = 'parent_children';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-panel">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>
    <div class="container">
        <div class="page-inner">

            <!-- Page Header -->
            <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                <div>
                    <h3 class="fw-bold mb-1">Details of Child</h3>
                    <h6 class="op-7 mb-0 text-muted">Update and maintain your children's profiles, demographics, and vaccination tracking</h6>
                </div>
                <div class="ms-md-auto py-2 py-md-0">
                    <button type="button" class="btn btn-primary btn-round shadow-sm" data-bs-toggle="modal" data-bs-target="#addChildModal">
                        <i class="fas fa-plus-circle me-1"></i> Add New Child
                    </button>
                </div>
            </div>

            <?= displayFlash() ?>

            <!-- Children Cards / Overview Grid -->
            <?php if (empty($children)): ?>
                <div class="card card-round shadow-sm text-center py-5">
                    <div class="card-body">
                        <div class="avatar-xl mb-3 mx-auto">
                            <span class="avatar-title rounded-circle bg-light text-primary fs-1">
                                <i class="fas fa-baby"></i>
                            </span>
                        </div>
                        <h4 class="fw-bold mb-2">No Children Added Yet</h4>
                        <p class="text-muted small mb-4" style="max-width: 450px; margin: 0 auto;">
                            Register your child details to track upcoming vaccination dates, book schedules with accredited hospitals, and access official vaccination reports.
                        </p>
                        <button type="button" class="btn btn-primary btn-round px-4" data-bs-toggle="modal" data-bs-target="#addChildModal">
                            <i class="fas fa-plus me-1"></i> Register Child Profile
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($children as $c): ?>
                        <?php
                            // Statistics for this child
                            $stmtVax = $pdo->prepare("SELECT COUNT(*) FROM vaccination_records WHERE child_id = ? AND status = 'Vaccinated'");
                            $stmtVax->execute([$c['child_id']]);
                            $vaxCompleted = (int)$stmtVax->fetchColumn();

                            $stmtSched = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE child_id = ? AND status = 'Approved' AND appointment_date >= CURRENT_DATE");
                            $stmtSched->execute([$c['child_id']]);
                            $vaxScheduled = (int)$stmtSched->fetchColumn();

                            $pct = min(100, round(($vaxCompleted / 12) * 100));
                        ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card card-round h-100 shadow-sm border dashboard-stat-card">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-sm">
                                            <span class="avatar-title rounded-circle bg-<?= ($c['gender'] === 'Female') ? 'danger' : 'primary' ?> text-white fw-bold">
                                                <?= strtoupper(substr($c['child_name'], 0, 1)) ?>
                                            </span>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold mb-0 text-truncate" style="max-width: 170px;">
                                                <?= htmlspecialchars($c['child_name']) ?>
                                            </h6>
                                            <span class="badge bg-secondary" style="font-size: 0.65rem;">
                                                <?= htmlspecialchars($c['gender']) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-icon btn-clean btn-sm" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="child-details.php?id=<?= $c['child_id'] ?>">
                                                    <i class="fas fa-id-card me-2 text-primary"></i> Full Profile & Schedule
                                                </a>
                                            </li>
                                            <li>
                                                <button class="dropdown-item" type="button" onclick='openEditModal(<?= json_encode($c) ?>)'>
                                                    <i class="fas fa-edit me-2 text-warning"></i> Update Details
                                                </button>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="book-appointment.php?child_id=<?= $c['child_id'] ?>">
                                                    <i class="fas fa-calendar-plus me-2 text-success"></i> Book Hospital
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button class="dropdown-item text-danger" type="button" onclick="confirmDelete(<?= $c['child_id'] ?>, '<?= htmlspecialchars(addslashes($c['child_name'])) ?>')">
                                                    <i class="fas fa-trash-alt me-2"></i> Delete Child
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <!-- Demographics List -->
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between py-1 border-bottom">
                                            <span class="text-muted small">Date of Birth:</span>
                                            <span class="fw-bold small"><?= formatDate($c['date_of_birth'], 'd M Y') ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between py-1 border-bottom">
                                            <span class="text-muted small">Current Age:</span>
                                            <span class="badge bg-info text-white"><?= calculateAge($c['date_of_birth']) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between py-1 border-bottom">
                                            <span class="text-muted small">Scheduled Visits:</span>
                                            <span class="badge bg-warning text-dark"><?= $vaxScheduled ?> Upcoming</span>
                                        </div>
                                        <?php if (!empty($c['notes'])): ?>
                                            <div class="py-2">
                                                <span class="text-muted small d-block mb-1">Health & Allergies:</span>
                                                <div class="p-2 bg-light rounded text-muted small fst-italic">
                                                    <?= htmlspecialchars($c['notes']) ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Vaccination Progress Bar -->
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between small fw-bold mb-1">
                                            <span>Immunization Progress:</span>
                                            <span class="text-success"><?= $vaxCompleted ?> of 12 Doses (<?= $pct ?>%)</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $pct ?>%"></div>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="d-flex gap-2">
                                        <a href="child-details.php?id=<?= $c['child_id'] ?>" class="btn btn-sm btn-outline-primary flex-fill">
                                            <i class="fas fa-syringe me-1"></i> Vaccine Profile
                                        </a>
                                        <a href="book-appointment.php?child_id=<?= $c['child_id'] ?>" class="btn btn-sm btn-primary flex-fill text-white">
                                            <i class="fas fa-calendar-plus me-1"></i> Book Hospital
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Children Master Table View -->
                <div class="card card-round shadow-sm mt-3">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-list-alt text-primary me-2"></i> All Registered Children Directory
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle datatable-custom">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Child Name</th>
                                        <th>Gender</th>
                                        <th>Date of Birth</th>
                                        <th>Age</th>
                                        <th>Home Address</th>
                                        <th>Vaccines Received</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($children as $c): ?>
                                        <?php
                                            $stmtVax = $pdo->prepare("SELECT COUNT(*) FROM vaccination_records WHERE child_id = ? AND status = 'Vaccinated'");
                                            $stmtVax->execute([$c['child_id']]);
                                            $cnt = (int)$stmtVax->fetchColumn();
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="avatar-xs">
                                                        <span class="avatar-title rounded-circle bg-<?= ($c['gender'] === 'Female') ? 'danger' : 'primary' ?> text-white font-weight-bold">
                                                            <?= strtoupper(substr($c['child_name'], 0, 1)) ?>
                                                        </span>
                                                    </div>
                                                    <strong class="text-primary"><?= htmlspecialchars($c['child_name']) ?></strong>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($c['gender']) ?></td>
                                            <td><?= formatDate($c['date_of_birth'], 'd M Y') ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= calculateAge($c['date_of_birth']) ?></span></td>
                                            <td class="small text-muted text-truncate" style="max-width: 180px;">
                                                <?= htmlspecialchars($c['address'] ?? '-') ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><i class="fas fa-syringe me-1"></i> <?= $cnt ?> Doses</span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="child-details.php?id=<?= $c['child_id'] ?>" class="btn btn-outline-primary" title="View Immunization Timeline">
                                                        <i class="fas fa-id-card"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-warning" title="Edit Child Details" onclick='openEditModal(<?= json_encode($c) ?>)'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="book-appointment.php?child_id=<?= $c['child_id'] ?>" class="btn btn-outline-success" title="Book Vaccine">
                                                        <i class="fas fa-calendar-plus"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- ========================================== -->
    <!-- ADD CHILD MODAL                            -->
    <!-- ========================================== -->
    <div class="modal fade" id="addChildModal" tabindex="-1" aria-labelledby="addChildModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="children.php">
                    <input type="hidden" name="action" value="add_child">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="addChildModalLabel">
                            <i class="fas fa-baby text-primary me-2"></i> Register New Child Profile
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Child Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="child_name" class="form-control" placeholder="e.g. Liam Jenkins" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">Gender <span class="text-danger">*</span></label>
                                <select name="gender" class="form-select" required>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" name="date_of_birth" class="form-control" max="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Residential Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Home address"><?= htmlspecialchars($currentParent['address'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Medical Notes & Known Allergies</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Normal birth weight, mild lactose sensitivity, no penicillin allergies"></textarea>
                            <div class="form-text small">This information is shared with accredited hospital nurses during vaccination.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Child Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- EDIT CHILD MODAL                           -->
    <!-- ========================================== -->
    <div class="modal fade" id="editChildModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="children.php">
                    <input type="hidden" name="action" value="edit_child">
                    <input type="hidden" name="child_id" id="edit_child_id">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">
                            <i class="fas fa-user-edit text-warning me-2"></i> Update Child Details
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Child Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="child_name" id="edit_child_name" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">Gender <span class="text-danger">*</span></label>
                                <select name="gender" id="edit_gender" class="form-select" required>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" name="date_of_birth" id="edit_dob" class="form-control" max="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Residential Address</label>
                            <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Medical Notes & Known Allergies</label>
                            <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning text-dark">
                            <i class="fas fa-check-circle me-1"></i> Update Details
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Form (Hidden) -->
    <form id="deleteChildForm" method="POST" action="children.php" style="display: none;">
        <input type="hidden" name="action" value="delete_child">
        <input type="hidden" name="child_id" id="delete_child_id">
    </form>

    <script>
        function openEditModal(child) {
            document.getElementById('edit_child_id').value = child.child_id;
            document.getElementById('edit_child_name').value = child.child_name;
            document.getElementById('edit_gender').value = child.gender;
            document.getElementById('edit_dob').value = child.date_of_birth;
            document.getElementById('edit_address').value = child.address || '';
            document.getElementById('edit_notes').value = child.notes || '';

            var editModal = new bootstrap.Modal(document.getElementById('editChildModal'));
            editModal.show();
        }

        function confirmDelete(childId, childName) {
            if (confirm("Are you sure you want to delete the profile for '" + childName + "'?\nThis will remove all associated appointments and records.")) {
                document.getElementById('delete_child_id').value = childId;
                document.getElementById('deleteChildForm').submit();
            }
        }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
