<?php
require_once 'includes/auth_check.php';
require_once '../connect.php';

$page_title   = 'จัดการนักศึกษา / ห้องพัก';
$current_page = 'students';

// ===== Handle POST (Edit Student) =====
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'edit_student') {
        $student_id = trim($_POST['student_id'] ?? '');
        $name       = trim($_POST['name'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $room_id    = (int)($_POST['room_id'] ?? 0);
        $role       = in_array($_POST['role'] ?? '', ['student', 'monitor']) ? $_POST['role'] : 'student';

        if ($student_id && $name && $room_id) {
            $pdo->prepare("UPDATE students SET name = ?, phone = ?, room_id = ?, role = ? WHERE student_id = ?")
                ->execute([$name, $phone, $room_id, $role, $student_id]);
            $msg     = "อัปเดตข้อมูลนักศึกษา {$student_id} เรียบร้อยแล้ว";
            $msgType = 'success';
        } else {
            $msg     = 'ข้อมูลไม่ครบถ้วน';
            $msgType = 'danger';
        }
    }

    if ($action === 'delete_student') {
        $student_id = trim($_POST['student_id'] ?? '');
        if ($student_id) {
            $pdo->prepare("DELETE FROM students WHERE student_id = ?")
                ->execute([$student_id]);
            $msg     = "ลบนักศึกษา {$student_id} ออกจากระบบแล้ว";
            $msgType = 'warning';
        }
    }
}

// ===== Filter =====
$filter_dorm   = $_GET['dorm_id'] ?? '';
$filter_search = trim($_GET['q'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 20;

$where  = [];
$params = [];

if ($filter_dorm) {
    $where[]  = 'd.id = ?';
    $params[] = (int)$filter_dorm;
}
if ($filter_search) {
    $where[]  = '(s.student_id LIKE ? OR s.name LIKE ? OR r.room_number LIKE ?)';
    $params[] = "%$filter_search%";
    $params[] = "%$filter_search%";
    $params[] = "%$filter_search%";
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)$pdo->prepare("
    SELECT COUNT(*) FROM students s
    JOIN rooms r ON s.room_id = r.id
    JOIN dorms d ON r.dorm_id = d.id
    $whereSQL
")->execute($params) ? $pdo->prepare("SELECT COUNT(*) FROM students s JOIN rooms r ON s.room_id = r.id JOIN dorms d ON r.dorm_id = d.id $whereSQL")->execute($params) || 0 : 0;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM students s JOIN rooms r ON s.room_id = r.id JOIN dorms d ON r.dorm_id = d.id $whereSQL");
$countStmt->execute($params);
$total      = (int)$countStmt->fetchColumn();
$total_pages = ceil($total / $per_page);
$offset      = ($page - 1) * $per_page;

$studentsStmt = $pdo->prepare("
    SELECT s.student_id, s.name, s.phone, s.role, s.line_uid, s.line_profile_img, s.updated_at,
           r.id AS room_id, r.room_number, d.id AS dorm_id, d.name AS dorm_name,
           (SELECT COUNT(*) FROM repair_requests rr WHERE rr.student_id = s.student_id) AS repair_count
    FROM students s
    JOIN rooms r ON s.room_id = r.id
    JOIN dorms d ON r.dorm_id = d.id
    $whereSQL
    ORDER BY s.updated_at DESC
    LIMIT $per_page OFFSET $offset
");
$studentsStmt->execute($params);
$students = $studentsStmt->fetchAll();

$dorms    = $pdo->query("SELECT id, name FROM dorms ORDER BY name")->fetchAll();
$allRooms = $pdo->query("SELECT r.id, r.room_number, d.id AS dorm_id, d.name AS dorm_name FROM rooms r JOIN dorms d ON r.dorm_id = d.id ORDER BY d.name, r.room_number")->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-people-fill me-2" style="color:#06C755;"></i>นักศึกษา / ห้องพัก</h2>
        <p class="page-desc">ทั้งหมด <?= number_format($total) ?> รายการ</p>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> rounded-3 d-flex align-items-center gap-2 mb-4" style="font-size:0.9rem;">
    <i class="bi bi-info-circle-fill"></i> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Filter -->
<div class="panel mb-4">
    <div class="panel-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label mb-1" style="font-size:0.82rem;font-weight:500;color:#64748b;">ค้นหา</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0"
                           placeholder="รหัสนักศึกษา, ชื่อ, เลขห้อง..."
                           value="<?= htmlspecialchars($filter_search) ?>">
                </div>
            </div>
            <div class="col-7 col-md-4">
                <label class="form-label mb-1" style="font-size:0.82rem;font-weight:500;color:#64748b;">หอพัก</label>
                <select name="dorm_id" class="form-select form-select-sm">
                    <option value="">ทุกหอพัก</option>
                    <?php foreach ($dorms as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $filter_dorm == $d['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-5 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-success flex-fill" style="background:#06C755;border:none;">
                    <i class="bi bi-funnel-fill me-1"></i>กรอง
                </button>
                <a href="students.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="panel">
    <div class="table-responsive">
        <table class="table table-clean mb-0">
            <thead>
                <tr>
                    <th style="padding-left:20px;">#</th>
                    <th>รหัสนักศึกษา</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>ห้อง / หอพัก</th>
                    <th>เบอร์โทร</th>
                    <th>สถานะ</th>
                    <th>LINE</th>
                    <th>แจ้งซ่อม</th>
                    <th style="padding-right:20px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="bi bi-person-x" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                        ไม่พบข้อมูลนักศึกษา
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($students as $idx => $s): ?>
                <tr>
                    <td style="padding-left:20px;color:#94a3b8;font-size:0.82rem;"><?= $offset + $idx + 1 ?></td>
                    <td>
                        <span style="font-weight:600;font-size:0.88rem;font-family:monospace;color:#1e293b;">
                            <?= htmlspecialchars($s['student_id']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($s['line_profile_img']): ?>
                            <img src="<?= htmlspecialchars($s['line_profile_img']) ?>"
                                 style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:1.5px solid #e2e8f0;"
                                 onerror="this.style.display='none'">
                            <?php else: ?>
                            <div style="width:32px;height:32px;border-radius:50%;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:0.8rem;color:#64748b;flex-shrink:0;">
                                <?= mb_substr($s['name'], 0, 1) ?>
                            </div>
                            <?php endif; ?>
                            <span style="font-size:0.9rem;font-weight:500;"><?= htmlspecialchars($s['name']) ?></span>
                        </div>
                    </td>
                    <td>
                        <div style="font-size:0.88rem;">ห้อง <?= htmlspecialchars($s['room_number']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($s['dorm_name']) ?></small>
                    </td>
                    <td style="font-size:0.88rem;">
                        <?= $s['phone'] ? htmlspecialchars($s['phone']) : '<span class="text-muted">-</span>' ?>
                    </td>
                    <td>
                        <?php if (($s['role'] ?? 'student') === 'monitor'): ?>
                        <span class="badge" style="background:#ede9fe;color:#7c3aed;font-size:0.75rem;">
                            <i class="bi bi-shield-fill me-1"></i>ดูแลหอพัก
                        </span>
                        <?php else: ?>
                        <span class="badge bg-light text-muted" style="font-size:0.75rem;">นักศึกษา</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($s['line_uid']): ?>
                        <span class="badge" style="background:#d1fae5;color:#065f46;font-size:0.75rem;">
                            <i class="bi bi-check-circle-fill me-1"></i>เชื่อมต่อแล้ว
                        </span>
                        <?php else: ?>
                        <span class="badge bg-light text-muted" style="font-size:0.75rem;">ยังไม่เชื่อมต่อ</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($s['repair_count'] > 0): ?>
                        <a href="repairs.php?q=<?= urlencode($s['student_id']) ?>"
                           class="badge text-decoration-none" style="background:#dbeafe;color:#1d4ed8;font-size:0.78rem;">
                            <?= $s['repair_count'] ?> ใบ
                        </a>
                        <?php else: ?>
                        <span class="text-muted" style="font-size:0.82rem;">-</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding-right:20px;">
                        <button class="btn btn-sm btn-outline-secondary rounded-pill"
                                style="font-size:0.78rem;"
                                onclick="openEditModal(<?= htmlspecialchars(json_encode($s)) ?>)">
                            <i class="bi bi-pencil me-1"></i>แก้ไข
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top" style="border-color:#f1f5f9 !important;">
        <small class="text-muted">
            แสดง <?= $offset + 1 ?>–<?= min($offset + $per_page, $total) ?> จากทั้งหมด <?= $total ?>
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0 gap-1">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link rounded" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php endif; ?>
                <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link rounded" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link rounded" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Edit Student Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">แก้ไขข้อมูลนักศึกษา</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit_student">
                <input type="hidden" name="student_id" id="edit_student_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;font-weight:500;">รหัสนักศึกษา</label>
                        <input type="text" id="edit_student_id_display" class="form-control form-control-sm" readonly style="background:#f8fafc;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;font-weight:500;">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;font-weight:500;">เบอร์โทรศัพท์</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control form-control-sm">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;font-weight:500;">ห้องพัก <span class="text-danger">*</span></label>
                        <select name="room_id" id="edit_room_id" class="form-select form-select-sm" required>
                            <?php foreach ($allRooms as $room): ?>
                            <option value="<?= $room['id'] ?>" data-dorm="<?= $room['dorm_id'] ?>">
                                <?= htmlspecialchars($room['dorm_name']) ?> — ห้อง <?= htmlspecialchars($room['room_number']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;font-weight:500;">สถานะในระบบ</label>
                        <div class="d-flex gap-2">
                            <label class="flex-fill text-center py-2 rounded-3"
                                   style="cursor:pointer;border:2px solid #e2e8f0;font-size:0.85rem;font-weight:500;transition:all 0.15s;"
                                   id="roleLabel_student">
                                <input type="radio" name="role" value="student" class="d-none role-radio" onchange="highlightRole('student')">
                                👤 นักศึกษาทั่วไป
                            </label>
                            <label class="flex-fill text-center py-2 rounded-3"
                                   style="cursor:pointer;border:2px solid #e2e8f0;font-size:0.85rem;font-weight:500;transition:all 0.15s;"
                                   id="roleLabel_monitor">
                                <input type="radio" name="role" value="monitor" class="d-none role-radio" onchange="highlightRole('monitor')">
                                🛡️ ดูแลหอพัก
                            </label>
                        </div>
                        <div style="font-size:0.75rem;color:#94a3b8;margin-top:5px;">
                            คนดูแลหอพักสามารถเลือกหอ+ห้องได้เสรีเมื่อแจ้งซ่อม
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-outline-danger me-auto"
                            onclick="deleteStudent()">
                        <i class="bi bi-trash me-1"></i>ลบออกจากระบบ
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-sm" style="background:#06C755;color:white;border:none;">
                        <i class="bi bi-save me-1"></i>บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete form (hidden) -->
<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="action" value="delete_student">
    <input type="hidden" name="student_id" id="delete_student_id">
</form>

<?php
$extra_scripts = <<<'JS'
<script>
function highlightRole(role) {
    ['student', 'monitor'].forEach(r => {
        const lbl = document.getElementById('roleLabel_' + r);
        if (r === role) {
            lbl.style.borderColor  = r === 'monitor' ? '#7c3aed' : '#06C755';
            lbl.style.background   = r === 'monitor' ? '#ede9fe' : '#f0fdf4';
            lbl.style.color        = r === 'monitor' ? '#7c3aed' : '#065f46';
            lbl.style.fontWeight   = '700';
        } else {
            lbl.style.borderColor  = '#e2e8f0';
            lbl.style.background   = 'white';
            lbl.style.color        = '#334155';
            lbl.style.fontWeight   = '500';
        }
    });
}

function openEditModal(student) {
    document.getElementById('edit_student_id').value         = student.student_id;
    document.getElementById('edit_student_id_display').value = student.student_id;
    document.getElementById('edit_name').value               = student.name;
    document.getElementById('edit_phone').value              = student.phone || '';
    document.getElementById('edit_room_id').value            = student.room_id;

    // Set role radio
    const role = student.role || 'student';
    const radioEl = document.querySelector(`.role-radio[value="${role}"]`);
    if (radioEl) radioEl.checked = true;
    highlightRole(role);

    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function deleteStudent() {
    const sid = document.getElementById('edit_student_id').value;
    Swal.fire({
        title: 'ลบนักศึกษาออกจากระบบ?',
        html: `รหัส <strong>${sid}</strong> จะถูกลบออกจากระบบ<br><small class='text-muted'>ประวัติการแจ้งซ่อมยังคงอยู่</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ยืนยันการลบ',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b'
    }).then(result => {
        if (result.isConfirmed) {
            document.getElementById('delete_student_id').value = sid;
            document.getElementById('deleteForm').submit();
        }
    });
}
</script>
JS;

include 'includes/footer.php';
?>
