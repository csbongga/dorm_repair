<?php
require_once 'includes/auth_check.php';
require_once '../connect.php';

$page_title   = 'จัดการเจ้าหน้าที่ / ช่าง';
$current_page = 'staff';

$msg     = '';
$msgType = '';

// ===== Handle POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_staff') {
        $username  = trim($_POST['username'] ?? '');
        $password  = $_POST['password'] ?? '';
        $name      = trim($_POST['name'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $role      = $_POST['role'] ?? 'technician';
        $specialty = $_POST['specialty'] ?? 'ทั้งหมด';

        if ($username && $password && $name) {
            // เช็คว่า username ซ้ำไหม
            $check = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetchColumn() > 0) {
                $msg     = "Username '{$username}' มีอยู่ในระบบแล้ว";
                $msgType = 'danger';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO staff (username, password, name, phone, role, specialty, status) VALUES (?,?,?,?,?,?,'active')")
                    ->execute([$username, $hashed, $name, $phone, $role, $specialty]);
                $msg     = "เพิ่มเจ้าหน้าที่ '{$name}' เรียบร้อยแล้ว";
                $msgType = 'success';
            }
        } else {
            $msg     = 'กรุณากรอก Username, รหัสผ่าน และชื่อให้ครบถ้วน';
            $msgType = 'danger';
        }
    }

    if ($action === 'edit_staff') {
        $id        = (int)$_POST['staff_id'];
        $name      = trim($_POST['name'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $role      = $_POST['role'] ?? 'technician';
        $specialty = $_POST['specialty'] ?? 'ทั้งหมด';
        $status    = $_POST['status'] ?? 'active';
        $newPass   = $_POST['new_password'] ?? '';

        if ($id && $name) {
            if ($newPass) {
                $hashed = password_hash($newPass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE staff SET name=?, phone=?, role=?, specialty=?, status=?, password=? WHERE id=?")
                    ->execute([$name, $phone, $role, $specialty, $status, $hashed, $id]);
            } else {
                $pdo->prepare("UPDATE staff SET name=?, phone=?, role=?, specialty=?, status=? WHERE id=?")
                    ->execute([$name, $phone, $role, $specialty, $status, $id]);
            }
            $msg     = "อัปเดตข้อมูล '{$name}' เรียบร้อยแล้ว";
            $msgType = 'success';
        }
    }

    if ($action === 'toggle_status') {
        $id         = (int)$_POST['staff_id'];
        $newStatus  = $_POST['new_status'] ?? 'inactive';
        if ($id && $id !== (int)$_SESSION['admin_id']) {
            $pdo->prepare("UPDATE staff SET status=? WHERE id=?")->execute([$newStatus, $id]);
            $msg     = 'อัปเดตสถานะเรียบร้อยแล้ว';
            $msgType = 'success';
        } else {
            $msg     = 'ไม่สามารถเปลี่ยนสถานะบัญชีของตนเองได้';
            $msgType = 'warning';
        }
    }
}

// ===== ดึงรายชื่อ Staff =====
$staffList = $pdo->query("SELECT * FROM staff ORDER BY status DESC, role ASC, name ASC")->fetchAll();

$roles      = ['admin' => 'ผู้ดูแลระบบ', 'technician' => 'ช่างซ่อม'];
$specialties = ['ประปา', 'ไฟฟ้า', 'ซ่อมสร้าง', 'ทั้งหมด'];

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-person-badge-fill me-2" style="color:#06C755;"></i>เจ้าหน้าที่ / ช่าง</h2>
        <p class="page-desc">ทั้งหมด <?= count($staffList) ?> คน</p>
    </div>
    <button class="btn btn-sm" style="background:#06C755;color:white;border:none;border-radius:10px;padding:8px 16px;"
            onclick="openAddModal()">
        <i class="bi bi-person-plus-fill me-1"></i>เพิ่มเจ้าหน้าที่
    </button>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> rounded-3 d-flex align-items-center gap-2 mb-4" style="font-size:0.9rem;">
    <i class="bi bi-info-circle-fill"></i> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Staff Grid -->
<div class="row g-3">
    <?php foreach ($staffList as $staff): ?>
    <?php $isActive = $staff['status'] === 'active'; ?>
    <div class="col-12 col-md-6 col-lg-4">
        <div class="panel mb-0" style="opacity:<?= $isActive ? 1 : 0.65 ?>;">
            <div class="panel-body">
                <div class="d-flex align-items-start gap-3">
                    <div style="width:50px;height:50px;border-radius:14px;background:<?= $isActive ? 'linear-gradient(135deg,#06C755,#05a044)' : '#e2e8f0' ?>;display:flex;align-items:center;justify-content:center;color:<?= $isActive ? 'white' : '#94a3b8' ?>;font-size:1.1rem;font-weight:700;flex-shrink:0;">
                        <?= mb_substr($staff['name'], 0, 1) ?>
                    </div>
                    <div class="flex-fill min-width-0">
                        <div style="font-weight:700;font-size:0.95rem;color:#1e293b;">
                            <?= htmlspecialchars($staff['name']) ?>
                        </div>
                        <div style="font-size:0.8rem;color:#64748b;">@<?= htmlspecialchars($staff['username'] ?? '-') ?></div>
                        <div class="d-flex flex-wrap gap-1 mt-2">
                            <span class="badge" style="background:#ede9fe;color:#7c3aed;font-size:0.72rem;">
                                <?= $roles[$staff['role']] ?? $staff['role'] ?>
                            </span>
                            <?php if ($staff['specialty']): ?>
                            <span class="badge" style="background:#dbeafe;color:#1d4ed8;font-size:0.72rem;">
                                <?= htmlspecialchars($staff['specialty']) ?>
                            </span>
                            <?php endif; ?>
                            <span class="badge" style="background:<?= $isActive ? '#d1fae5' : '#f1f5f9' ?>;color:<?= $isActive ? '#065f46' : '#64748b' ?>;font-size:0.72rem;">
                                <?= $isActive ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                    </div>
                </div>

                <?php if ($staff['phone']): ?>
                <div style="font-size:0.82rem;color:#64748b;margin-top:12px;">
                    <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($staff['phone']) ?>
                </div>
                <?php endif; ?>

                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-outline-secondary flex-fill"
                            style="font-size:0.78rem;"
                            onclick="openEditModal(<?= htmlspecialchars(json_encode($staff)) ?>)">
                        <i class="bi bi-pencil me-1"></i>แก้ไข
                    </button>
                    <?php if ($staff['id'] !== (int)$_SESSION['admin_id']): ?>
                    <form method="POST" style="flex:1;">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                        <input type="hidden" name="new_status" value="<?= $isActive ? 'inactive' : 'active' ?>">
                        <button type="submit" class="btn btn-sm w-100"
                                style="font-size:0.78rem;background:<?= $isActive ? '#fee2e2' : '#d1fae5' ?>;color:<?= $isActive ? '#dc2626' : '#059669' ?>;border:none;"
                                onclick="return confirm('<?= $isActive ? 'ระงับการใช้งาน' : 'เปิดใช้งาน' ?>บัญชีนี้?')">
                            <i class="bi bi-<?= $isActive ? 'pause-circle' : 'play-circle' ?> me-1"></i>
                            <?= $isActive ? 'ระงับ' : 'เปิดใช้' ?>
                        </button>
                    </form>
                    <?php else: ?>
                    <button class="btn btn-sm flex-fill" disabled style="font-size:0.78rem;background:#f1f5f9;color:#94a3b8;border:none;">
                        (บัญชีของคุณ)
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($staffList)): ?>
    <div class="col-12">
        <div class="panel">
            <div class="panel-body text-center py-5 text-muted">
                <i class="bi bi-person-x" style="font-size:2.5rem;display:block;margin-bottom:10px;"></i>
                ยังไม่มีเจ้าหน้าที่ในระบบ
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2" style="color:#06C755;"></i>เพิ่มเจ้าหน้าที่ใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_staff">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label" style="font-size:0.85rem;font-weight:500;">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control form-control-sm" required autocomplete="off">
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-size:0.85rem;font-weight:500;">รหัสผ่าน <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control form-control-sm" required autocomplete="new-password">
                        </div>
                        <div class="col-12">
                            <label class="form-label" style="font-size:0.85rem;font-weight:500;">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" style="font-size:0.85rem;font-weight:500;">เบอร์โทรศัพท์</label>
                            <input type="text" name="phone" class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-size:0.85rem;font-weight:500;">บทบาท</label>
                            <select name="role" class="form-select form-select-sm">
                                <option value="technician">ช่างซ่อม</option>
                                <option value="admin">ผู้ดูแลระบบ</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-size:0.85rem;font-weight:500;">ความเชี่ยวชาญ</label>
                            <select name="specialty" class="form-select form-select-sm">
                                <?php foreach ($specialties as $sp): ?>
                                <option value="<?= $sp ?>"><?= $sp ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-sm" style="background:#06C755;color:white;border:none;">
                        <i class="bi bi-plus-lg me-1"></i>เพิ่มเจ้าหน้าที่
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Staff Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">แก้ไขข้อมูลเจ้าหน้าที่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_staff">
                <input type="hidden" name="staff_id" id="edit_staff_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" style="font-size:0.85rem;font-weight:500;">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="edit_name" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" style="font-size:0.85rem;font-weight:500;">เบอร์โทรศัพท์</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-size:0.85rem;font-weight:500;">บทบาท</label>
                            <select name="role" id="edit_role" class="form-select form-select-sm">
                                <option value="technician">ช่างซ่อม</option>
                                <option value="admin">ผู้ดูแลระบบ</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-size:0.85rem;font-weight:500;">ความเชี่ยวชาญ</label>
                            <select name="specialty" id="edit_specialty" class="form-select form-select-sm">
                                <?php foreach ($specialties as $sp): ?>
                                <option value="<?= $sp ?>"><?= $sp ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-size:0.85rem;font-weight:500;">สถานะ</label>
                            <select name="status" id="edit_status" class="form-select form-select-sm">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" style="font-size:0.85rem;font-weight:500;">
                                เปลี่ยนรหัสผ่าน <span class="text-muted" style="font-weight:400;">(เว้นว่างถ้าไม่ต้องการเปลี่ยน)</span>
                            </label>
                            <input type="password" name="new_password" class="form-control form-control-sm" autocomplete="new-password" placeholder="รหัสผ่านใหม่...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-sm" style="background:#06C755;color:white;border:none;">
                        <i class="bi bi-save me-1"></i>บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extra_scripts = "
<script>
function openAddModal() {
    new bootstrap.Modal(document.getElementById('addModal')).show();
}

function openEditModal(staff) {
    document.getElementById('edit_staff_id').value = staff.id;
    document.getElementById('edit_name').value = staff.name;
    document.getElementById('edit_phone').value = staff.phone || '';
    document.getElementById('edit_role').value = staff.role;
    document.getElementById('edit_specialty').value = staff.specialty || 'ทั้งหมด';
    document.getElementById('edit_status').value = staff.status;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>";

include 'includes/footer.php';
?>
