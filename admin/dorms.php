<?php
require_once 'includes/auth_check.php';
require_once '../connect.php';

$page_title   = 'จัดการหอพัก / ห้องพัก';
$current_page = 'dorms';

$msg     = '';
$msgType = '';

// ====================================================================
// POST Handlers
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- เพิ่มหอพัก ---
    if ($action === 'add_dorm') {
        $name      = trim($_POST['name'] ?? '');
        $dorm_type = $_POST['dorm_type'] ?? 'หอพักรวม';
        if ($name) {
            $pdo->prepare("INSERT INTO dorms (name, dorm_type) VALUES (?, ?)")
                ->execute([$name, $dorm_type]);
            $msg     = "เพิ่มหอพัก '{$name}' เรียบร้อยแล้ว";
            $msgType = 'success';
        } else {
            $msg = 'กรุณากรอกชื่อหอพัก'; $msgType = 'danger';
        }
    }

    // --- แก้ไขหอพัก ---
    if ($action === 'edit_dorm') {
        $id        = (int)$_POST['dorm_id'];
        $name      = trim($_POST['name'] ?? '');
        $dorm_type = $_POST['dorm_type'] ?? 'หอพักรวม';
        if ($id && $name) {
            $pdo->prepare("UPDATE dorms SET name=?, dorm_type=? WHERE id=?")
                ->execute([$name, $dorm_type, $id]);
            $msg     = "อัปเดตหอพัก '{$name}' เรียบร้อยแล้ว";
            $msgType = 'success';
        }
    }

    // --- ลบหอพัก ---
    if ($action === 'delete_dorm') {
        $id = (int)$_POST['dorm_id'];
        if ($id) {
            // เช็คว่ามีห้องหรือไม่
            $roomCount = (int)$pdo->prepare("SELECT COUNT(*) FROM rooms WHERE dorm_id=?")->execute([$id]) ?
                $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE dorm_id=?")->execute([$id]) || 0 : 0;
            $rc = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE dorm_id=?");
            $rc->execute([$id]);
            $roomCount = (int)$rc->fetchColumn();
            if ($roomCount > 0) {
                $msg     = "ไม่สามารถลบหอพักได้ เนื่องจากยังมีห้องพักอยู่ {$roomCount} ห้อง";
                $msgType = 'danger';
            } else {
                $pdo->prepare("DELETE FROM dorms WHERE id=?")->execute([$id]);
                $msg     = 'ลบหอพักเรียบร้อยแล้ว';
                $msgType = 'warning';
            }
        }
    }

    // --- เพิ่มห้องพัก (เดี่ยว) ---
    if ($action === 'add_room') {
        $dorm_id     = (int)$_POST['dorm_id'];
        $room_number = trim($_POST['room_number'] ?? '');
        if ($dorm_id && $room_number) {
            // เช็คห้องซ้ำในหอเดียวกัน
            $dup = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE dorm_id=? AND room_number=?");
            $dup->execute([$dorm_id, $room_number]);
            if ($dup->fetchColumn() > 0) {
                $msg     = "ห้อง '{$room_number}' มีอยู่ในหอพักนี้แล้ว";
                $msgType = 'danger';
            } else {
                $pdo->prepare("INSERT INTO rooms (dorm_id, room_number, status) VALUES (?,?,?)")
                    ->execute([$dorm_id, $room_number, 'พร้อมใช้งาน']);
                $msg     = "เพิ่มห้อง {$room_number} เรียบร้อยแล้ว";
                $msgType = 'success';
            }
        } else {
            $msg = 'กรุณากรอกหมายเลขห้องและเลือกหอพัก'; $msgType = 'danger';
        }
    }

    // --- เพิ่มห้องพักหลายห้อง (batch) ---
    if ($action === 'add_rooms_batch') {
        $dorm_id  = (int)$_POST['dorm_id'];
        $prefix   = trim($_POST['prefix'] ?? '');
        $from_num = (int)$_POST['from_num'];
        $to_num   = (int)$_POST['to_num'];

        if ($dorm_id && $prefix && $from_num > 0 && $to_num >= $from_num) {
            $added = 0; $skipped = 0;
            $insStmt = $pdo->prepare("INSERT IGNORE INTO rooms (dorm_id, room_number, status) VALUES (?,?,?)");
            $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE dorm_id=? AND room_number=?");
            for ($n = $from_num; $n <= $to_num; $n++) {
                $roomNo = $prefix . $n;
                $dupStmt->execute([$dorm_id, $roomNo]);
                if ($dupStmt->fetchColumn() > 0) {
                    $skipped++;
                } else {
                    $insStmt->execute([$dorm_id, $roomNo, 'พร้อมใช้งาน']);
                    $added++;
                }
            }
            $msg     = "เพิ่มห้องพักสำเร็จ {$added} ห้อง" . ($skipped > 0 ? " (ข้ามที่ซ้ำ {$skipped} ห้อง)" : '');
            $msgType = 'success';
        } else {
            $msg = 'ข้อมูลไม่ครบถ้วน กรุณาตรวจสอบ prefix และช่วงหมายเลขห้อง'; $msgType = 'danger';
        }
    }

    // --- แก้ไขห้องพัก ---
    if ($action === 'edit_room') {
        $id          = (int)$_POST['room_id'];
        $room_number = trim($_POST['room_number'] ?? '');
        $status      = $_POST['status'] ?? 'พร้อมใช้งาน';
        $init_raw    = trim($_POST['water_meter_init'] ?? '');
        $water_init  = ($init_raw !== '' && is_numeric($init_raw)) ? (float)$init_raw : null;
        if ($id && $room_number) {
            $pdo->prepare("UPDATE rooms SET room_number=?, status=?, water_meter_init=? WHERE id=?")
                ->execute([$room_number, $status, $water_init, $id]);
            $msg     = "อัปเดตห้อง {$room_number} เรียบร้อยแล้ว";
            $msgType = 'success';
        }
    }

    // --- ลบห้องพัก ---
    if ($action === 'delete_room') {
        $id = (int)$_POST['room_id'];
        if ($id) {
            // เช็คนักศึกษาและใบงาน
            $sCheck = $pdo->prepare("SELECT COUNT(*) FROM students WHERE room_id=?"); $sCheck->execute([$id]);
            $rCheck = $pdo->prepare("SELECT COUNT(*) FROM repair_requests WHERE room_id=?"); $rCheck->execute([$id]);
            if ($sCheck->fetchColumn() > 0) {
                $msg = 'ไม่สามารถลบได้ เนื่องจากมีนักศึกษาลงทะเบียนในห้องนี้อยู่'; $msgType = 'danger';
            } elseif ($rCheck->fetchColumn() > 0) {
                $msg = 'ไม่สามารถลบได้ เนื่องจากมีประวัติใบงานแจ้งซ่อมของห้องนี้'; $msgType = 'danger';
            } else {
                $pdo->prepare("DELETE FROM rooms WHERE id=?")->execute([$id]);
                $msg = 'ลบห้องพักเรียบร้อยแล้ว'; $msgType = 'warning';
            }
        }
    }

    // redirect หลัง POST เพื่อป้องกัน resubmit
    $redirectDorm = isset($_POST['selected_dorm']) ? '&dorm_id=' . (int)$_POST['selected_dorm'] : '';
    $tab = $_POST['tab'] ?? 'rooms';
    header("Location: dorms.php?tab={$tab}{$redirectDorm}&msg=" . urlencode($msg) . "&msg_type=" . urlencode($msgType));
    exit;
}

// แสดง msg จาก redirect
if (empty($msg) && isset($_GET['msg'])) {
    $msg     = $_GET['msg'];
    $msgType = $_GET['msg_type'] ?? 'info';
}

$activeTab    = $_GET['tab'] ?? 'dorms';
$selectedDorm = (int)($_GET['dorm_id'] ?? 0);

// ====================================================================
// ดึงข้อมูล
// ====================================================================
$dorms = $pdo->query("
    SELECT d.*, d.dorm_type,
           COUNT(r.id) AS room_count,
           SUM(CASE WHEN s.student_id IS NOT NULL THEN 1 ELSE 0 END) AS occupied_count
    FROM dorms d
    LEFT JOIN rooms r ON d.id = r.dorm_id
    LEFT JOIN students s ON r.id = s.room_id
    GROUP BY d.id
    ORDER BY d.id ASC
")->fetchAll();

// ห้องพักของหอที่เลือก (หรือทั้งหมดถ้าไม่ได้เลือก)
$roomsWhere  = $selectedDorm ? 'WHERE r.dorm_id = ' . $selectedDorm : '';
$rooms = $pdo->query("
    SELECT r.id, r.room_number, r.status, r.dorm_id, r.water_meter_init,
           d.name AS dorm_name,
           (SELECT COUNT(*) FROM students s WHERE s.room_id = r.id) AS student_count,
           (SELECT COUNT(*) FROM repair_requests rr WHERE rr.room_id = r.id) AS repair_count,
           (SELECT COUNT(*) FROM repair_requests rr WHERE rr.room_id = r.id AND rr.status = 'รอดำเนินการ') AS pending_count
    FROM rooms r
    JOIN dorms d ON r.dorm_id = d.id
    $roomsWhere
    ORDER BY d.id ASC, r.room_number ASC
")->fetchAll();

$roomStatuses = ['พร้อมใช้งาน', 'ไม่พร้อมใช้งาน', 'กำลังซ่อมแซม'];
$dormTypes    = ['หอพักชาย', 'หอพักหญิง', 'หอพักรวม'];

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-building-fill me-2" style="color:#06C755;"></i>หอพัก / ห้องพัก</h2>
        <p class="page-desc">
            <?= count($dorms) ?> หอพัก &nbsp;·&nbsp;
            <?= array_sum(array_column($dorms, 'room_count')) ?> ห้องพักทั้งหมด
        </p>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msgType) ?> rounded-3 d-flex align-items-center gap-2 mb-4" style="font-size:0.9rem;">
    <i class="bi bi-<?= $msgType === 'success' ? 'check-circle-fill' : ($msgType === 'danger' ? 'x-circle-fill' : 'info-circle-fill') ?>"></i>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-pills mb-4 gap-1" style="border-bottom:none;">
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'dorms' ? 'active' : '' ?>"
           href="?tab=dorms"
           style="<?= $activeTab === 'dorms' ? 'background:#06C755;' : 'background:white;color:#475569;border:1.5px solid #e2e8f0;' ?> border-radius:10px;font-size:0.9rem;font-weight:500;">
            <i class="bi bi-building me-1"></i>จัดการหอพัก
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'rooms' ? 'active' : '' ?>"
           href="?tab=rooms<?= $selectedDorm ? '&dorm_id=' . $selectedDorm : '' ?>"
           style="<?= $activeTab === 'rooms' ? 'background:#06C755;' : 'background:white;color:#475569;border:1.5px solid #e2e8f0;' ?> border-radius:10px;font-size:0.9rem;font-weight:500;">
            <i class="bi bi-door-closed me-1"></i>จัดการห้องพัก
        </a>
    </li>
</ul>

<!-- ============================================================ -->
<!-- TAB 1: หอพัก -->
<!-- ============================================================ -->
<?php if ($activeTab === 'dorms'): ?>

<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-sm" style="background:#06C755;color:white;border:none;border-radius:10px;padding:8px 18px;"
            onclick="openAddDormModal()">
        <i class="bi bi-plus-lg me-1"></i>เพิ่มหอพักใหม่
    </button>
</div>

<div class="row g-3">
    <?php foreach ($dorms as $d): ?>
    <?php
    $typeIcon  = ['หอพักชาย' => 'bi-gender-male', 'หอพักหญิง' => 'bi-gender-female', 'หอพักรวม' => 'bi-gender-ambiguous'][$d['dorm_type']] ?? 'bi-building';
    $typeColor = ['หอพักชาย' => '#3b82f6', 'หอพักหญิง' => '#ec4899', 'หอพักรวม' => '#8b5cf6'][$d['dorm_type']] ?? '#64748b';
    $occupancy = $d['room_count'] > 0 ? round($d['occupied_count'] / $d['room_count'] * 100) : 0;
    ?>
    <div class="col-12 col-md-6 col-lg-4">
        <div class="panel mb-0">
            <div class="panel-body">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <div style="width:48px;height:48px;border-radius:14px;background:<?= $typeColor ?>18;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:<?= $typeColor ?>;flex-shrink:0;">
                        <i class="bi <?= $typeIcon ?>"></i>
                    </div>
                    <div class="flex-fill">
                        <div style="font-weight:700;font-size:1rem;color:#1e293b;"><?= htmlspecialchars($d['name']) ?></div>
                        <span class="badge" style="background:<?= $typeColor ?>18;color:<?= $typeColor ?>;font-size:0.72rem;border-radius:6px;">
                            <?= htmlspecialchars($d['dorm_type']) ?>
                        </span>
                    </div>
                </div>

                <div class="row g-2 mb-3 text-center">
                    <div class="col-4">
                        <div style="font-size:1.3rem;font-weight:700;color:#1e293b;"><?= $d['room_count'] ?></div>
                        <div style="font-size:0.72rem;color:#94a3b8;">ห้องทั้งหมด</div>
                    </div>
                    <div class="col-4">
                        <div style="font-size:1.3rem;font-weight:700;color:#06C755;"><?= $d['occupied_count'] ?></div>
                        <div style="font-size:0.72rem;color:#94a3b8;">มีนักศึกษา</div>
                    </div>
                    <div class="col-4">
                        <div style="font-size:1.3rem;font-weight:700;color:#f59e0b;"><?= $d['room_count'] - $d['occupied_count'] ?></div>
                        <div style="font-size:0.72rem;color:#94a3b8;">ว่าง</div>
                    </div>
                </div>

                <!-- Occupancy bar -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span style="font-size:0.75rem;color:#64748b;">อัตราการเข้าพัก</span>
                        <span style="font-size:0.75rem;font-weight:600;color:#06C755;"><?= $occupancy ?>%</span>
                    </div>
                    <div style="height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden;">
                        <div style="height:100%;width:<?= $occupancy ?>%;background:linear-gradient(90deg,#06C755,#05a044);border-radius:3px;"></div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <a href="?tab=rooms&dorm_id=<?= $d['id'] ?>"
                       class="btn btn-sm flex-fill" style="background:#f1f5f9;color:#475569;border:none;font-size:0.8rem;border-radius:8px;">
                        <i class="bi bi-door-closed me-1"></i>ดูห้อง
                    </a>
                    <button class="btn btn-sm" style="background:#dbeafe;color:#1d4ed8;border:none;font-size:0.8rem;border-radius:8px;"
                            onclick="openEditDormModal(<?= htmlspecialchars(json_encode($d)) ?>)">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" style="margin:0;" onsubmit="return confirmDeleteDorm(event, '<?= htmlspecialchars($d['name']) ?>', <?= $d['room_count'] ?>)">
                        <input type="hidden" name="action" value="delete_dorm">
                        <input type="hidden" name="dorm_id" value="<?= $d['id'] ?>">
                        <input type="hidden" name="tab" value="dorms">
                        <button type="submit" class="btn btn-sm" style="background:#fee2e2;color:#dc2626;border:none;font-size:0.8rem;border-radius:8px;">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($dorms)): ?>
    <div class="col-12">
        <div class="panel">
            <div class="panel-body text-center py-5 text-muted">
                <i class="bi bi-building-x" style="font-size:2.5rem;display:block;margin-bottom:10px;"></i>
                ยังไม่มีข้อมูลหอพักในระบบ
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<!-- ============================================================ -->
<!-- TAB 2: ห้องพัก -->
<!-- ============================================================ -->
<?php if ($activeTab === 'rooms'): ?>

<div class="row g-3 mb-4">
    <!-- Filter + Add -->
    <div class="col-12">
        <div class="panel">
            <div class="panel-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label mb-1" style="font-size:0.82rem;font-weight:500;color:#64748b;">กรองตามหอพัก</label>
                        <select id="dormFilter" class="form-select form-select-sm"
                                onchange="window.location.href='?tab=rooms&dorm_id='+this.value">
                            <option value="0" <?= !$selectedDorm ? 'selected' : '' ?>>ทุกหอพัก</option>
                            <?php foreach ($dorms as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $selectedDorm == $d['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['name']) ?> (<?= $d['room_count'] ?> ห้อง)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-auto ms-auto">
                        <button class="btn btn-sm w-100" style="background:#06C755;color:white;border:none;border-radius:10px;padding:8px 16px;"
                                onclick="openAddRoomModal()">
                            <i class="bi bi-plus-lg me-1"></i>เพิ่มห้องเดี่ยว
                        </button>
                    </div>
                    <div class="col-6 col-md-auto">
                        <button class="btn btn-sm btn-outline-secondary w-100" style="border-radius:10px;padding:8px 16px;"
                                onclick="openBatchModal()">
                            <i class="bi bi-stack me-1"></i>เพิ่มห้องแบบ Batch
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rooms Table -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">
            <i class="bi bi-door-closed-fill"></i>
            รายการห้องพัก
            <?php if ($selectedDorm): ?>
            — <?= htmlspecialchars(array_column($dorms, 'name', 'id')[$selectedDorm] ?? '') ?>
            <?php endif; ?>
        </span>
        <span style="font-size:0.82rem;color:#94a3b8;"><?= count($rooms) ?> ห้อง</span>
    </div>
    <div class="table-responsive">
        <table class="table table-clean mb-0">
            <thead>
                <tr>
                    <th style="padding-left:20px;">หมายเลขห้อง</th>
                    <?php if (!$selectedDorm): ?>
                    <th>หอพัก</th>
                    <?php endif; ?>
                    <th>สถานะห้อง</th>
                    <th>นักศึกษา</th>
                    <th>ใบงาน</th>
                    <th>รอดำเนินการ</th>
                    <th style="padding-right:20px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rooms)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-door-open" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                        ไม่พบห้องพัก<?= $selectedDorm ? 'ในหอพักนี้' : '' ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($rooms as $room): ?>
                <?php
                $statusColor = [
                    'พร้อมใช้งาน'    => ['#d1fae5', '#059669'],
                    'ไม่พร้อมใช้งาน' => ['#f1f5f9', '#64748b'],
                    'กำลังซ่อมแซม'  => ['#fef3c7', '#d97706'],
                ][$room['status']] ?? ['#f1f5f9', '#64748b'];
                ?>
                <tr>
                    <td style="padding-left:20px;">
                        <span style="font-weight:700;font-size:0.95rem;color:#1e293b;font-family:monospace;">
                            <?= htmlspecialchars($room['room_number']) ?>
                        </span>
                    </td>
                    <?php if (!$selectedDorm): ?>
                    <td>
                        <span style="font-size:0.85rem;color:#475569;"><?= htmlspecialchars($room['dorm_name']) ?></span>
                    </td>
                    <?php endif; ?>
                    <td>
                        <span class="badge" style="background:<?= $statusColor[0] ?>;color:<?= $statusColor[1] ?>;font-size:0.78rem;border-radius:8px;font-weight:500;">
                            <?= htmlspecialchars($room['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($room['student_count'] > 0): ?>
                        <span class="badge" style="background:#dbeafe;color:#1d4ed8;font-size:0.78rem;">
                            <i class="bi bi-person-fill me-1"></i><?= $room['student_count'] ?>
                        </span>
                        <?php else: ?>
                        <span style="font-size:0.82rem;color:#cbd5e1;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($room['repair_count'] > 0): ?>
                        <a href="repairs.php?q=<?= urlencode($room['room_number']) ?>"
                           style="font-size:0.85rem;color:#06C755;text-decoration:none;font-weight:500;">
                            <?= $room['repair_count'] ?> ใบ
                        </a>
                        <?php else: ?>
                        <span style="font-size:0.82rem;color:#cbd5e1;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($room['pending_count'] > 0): ?>
                        <span class="badge" style="background:#fef3c7;color:#d97706;font-size:0.78rem;">
                            <?= $room['pending_count'] ?> รายการ
                        </span>
                        <?php else: ?>
                        <span style="font-size:0.82rem;color:#cbd5e1;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding-right:20px;">
                        <div class="d-flex gap-1 justify-content-end">
                            <button class="btn btn-sm" style="background:#f1f5f9;color:#475569;border:none;font-size:0.78rem;border-radius:8px;"
                                    onclick="openEditRoomModal(<?= htmlspecialchars(json_encode($room)) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ($room['student_count'] == 0 && $room['repair_count'] == 0): ?>
                            <form method="POST" style="margin:0;" onsubmit="return confirm('ลบห้อง <?= htmlspecialchars($room['room_number'], ENT_QUOTES) ?> ?')">
                                <input type="hidden" name="action" value="delete_room">
                                <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
                                <input type="hidden" name="selected_dorm" value="<?= $selectedDorm ?>">
                                <input type="hidden" name="tab" value="rooms">
                                <button type="submit" class="btn btn-sm" style="background:#fee2e2;color:#dc2626;border:none;font-size:0.78rem;border-radius:8px;">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <button class="btn btn-sm" disabled
                                    style="background:#f8fafc;color:#cbd5e1;border:none;font-size:0.78rem;border-radius:8px;"
                                    title="ไม่สามารถลบได้ (มีนักศึกษาหรือใบงาน)">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<!-- ====================================================== -->
<!-- Modals: Dorm -->
<!-- ====================================================== -->

<!-- Add Dorm Modal -->
<div class="modal fade" id="addDormModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold"><i class="bi bi-plus-circle-fill me-2" style="color:#06C755;"></i>เพิ่มหอพักใหม่</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_dorm">
                <input type="hidden" name="tab" value="dorms">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;font-weight:500;">ชื่อหอพัก <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" required placeholder="เช่น หอพักราชพฤกษ์ 10">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;font-weight:500;">ประเภท</label>
                        <select name="dorm_type" class="form-select form-select-sm">
                            <?php foreach ($dormTypes as $t): ?>
                            <option value="<?= $t ?>"><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-sm" style="background:#06C755;color:white;border:none;">
                        <i class="bi bi-plus-lg me-1"></i>เพิ่ม
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Dorm Modal -->
<div class="modal fade" id="editDormModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold">แก้ไขหอพัก</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_dorm">
                <input type="hidden" name="tab" value="dorms">
                <input type="hidden" name="dorm_id" id="editDormId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;font-weight:500;">ชื่อหอพัก <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editDormName" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;font-weight:500;">ประเภท</label>
                        <select name="dorm_type" id="editDormType" class="form-select form-select-sm">
                            <?php foreach ($dormTypes as $t): ?>
                            <option value="<?= $t ?>"><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
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

<!-- ====================================================== -->
<!-- Modals: Room -->
<!-- ====================================================== -->

<!-- Add Single Room Modal -->
<div class="modal fade" id="addRoomModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold"><i class="bi bi-door-closed-fill me-2" style="color:#06C755;"></i>เพิ่มห้องพัก</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_room">
                <input type="hidden" name="tab" value="rooms">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;font-weight:500;">หอพัก <span class="text-danger">*</span></label>
                        <select name="dorm_id" id="addRoomDormId" class="form-select form-select-sm" required>
                            <option value="">เลือกหอพัก</option>
                            <?php foreach ($dorms as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $selectedDorm == $d['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;font-weight:500;">หมายเลขห้อง <span class="text-danger">*</span></label>
                        <input type="text" name="room_number" class="form-control form-control-sm" required placeholder="เช่น 1101">
                    </div>
                    <input type="hidden" name="selected_dorm" value="<?= $selectedDorm ?>">
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-sm" style="background:#06C755;color:white;border:none;">
                        <i class="bi bi-plus-lg me-1"></i>เพิ่ม
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Batch Add Rooms Modal -->
<div class="modal fade" id="batchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold"><i class="bi bi-stack me-2" style="color:#06C755;"></i>เพิ่มห้องพักแบบ Batch</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_rooms_batch">
                <input type="hidden" name="tab" value="rooms">
                <input type="hidden" name="selected_dorm" value="<?= $selectedDorm ?>">
                <div class="modal-body">
                    <div class="alert" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;font-size:0.83rem;color:#065f46;">
                        <i class="bi bi-lightbulb-fill me-1"></i>
                        ระบบจะสร้างห้องพักโดยใช้ <strong>Prefix + ตัวเลขต่อเนื่อง</strong><br>
                        เช่น Prefix = <code>11</code>, จาก <code>01</code> ถึง <code>26</code> → ได้ห้อง 1101, 1102, ... 1126
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;font-weight:500;">หอพัก <span class="text-danger">*</span></label>
                        <select name="dorm_id" id="batchDormId" class="form-select form-select-sm" required>
                            <option value="">เลือกหอพัก</option>
                            <?php foreach ($dorms as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $selectedDorm == $d['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-4">
                            <label class="form-label" style="font-size:0.85rem;font-weight:500;">Prefix <span class="text-danger">*</span></label>
                            <input type="text" name="prefix" id="batchPrefix" class="form-control form-control-sm" required
                                   placeholder="เช่น 11" oninput="updateBatchPreview()">
                        </div>
                        <div class="col-4">
                            <label class="form-label" style="font-size:0.85rem;font-weight:500;">จากเลข <span class="text-danger">*</span></label>
                            <input type="number" name="from_num" id="batchFrom" class="form-control form-control-sm" required
                                   min="1" placeholder="01" oninput="updateBatchPreview()">
                        </div>
                        <div class="col-4">
                            <label class="form-label" style="font-size:0.85rem;font-weight:500;">ถึงเลข <span class="text-danger">*</span></label>
                            <input type="number" name="to_num" id="batchTo" class="form-control form-control-sm" required
                                   min="1" placeholder="26" oninput="updateBatchPreview()">
                        </div>
                    </div>
                    <div id="batchPreview" class="mt-3 p-3 rounded-3" style="background:#f8fafc;border:1.5px dashed #e2e8f0;font-size:0.82rem;color:#64748b;min-height:48px;display:none;">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-sm" style="background:#06C755;color:white;border:none;">
                        <i class="bi bi-plus-lg me-1"></i>สร้างห้องทั้งหมด
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Room Modal -->
<div class="modal fade" id="editRoomModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold">แก้ไขห้องพัก</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_room">
                <input type="hidden" name="tab" value="rooms">
                <input type="hidden" name="room_id" id="editRoomId">
                <input type="hidden" name="selected_dorm" value="<?= $selectedDorm ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;font-weight:500;">หอพัก</label>
                        <input type="text" id="editRoomDorm" class="form-control form-control-sm" readonly style="background:#f8fafc;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;font-weight:500;">หมายเลขห้อง <span class="text-danger">*</span></label>
                        <input type="text" name="room_number" id="editRoomNumber" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;font-weight:500;">สถานะห้อง</label>
                        <select name="status" id="editRoomStatus" class="form-select form-select-sm">
                            <?php foreach ($roomStatuses as $rs): ?>
                            <option value="<?= $rs ?>"><?= $rs ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-1">
                        <label class="form-label" style="font-size:0.85rem;font-weight:500;">
                            <i class="bi bi-droplet-fill me-1" style="color:#0ea5e9;"></i>
                            เลขมิเตอร์น้ำเริ่มต้น (ก่อนใช้ระบบ)
                        </label>
                        <input type="number" name="water_meter_init" id="editRoomWaterInit"
                               class="form-control form-control-sm"
                               placeholder="เว้นว่างถ้าไม่มี" min="0" step="1" inputmode="numeric">
                        <div style="font-size:0.75rem;color:#94a3b8;margin-top:4px;">
                            ใช้แสดง "เลขครั้งก่อน" ในหน้าส่งมิเตอร์สำหรับเดือนแรก
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
$extra_scripts = <<<'JS'
<script>
function openAddDormModal() {
    new bootstrap.Modal(document.getElementById('addDormModal')).show();
}

function openEditDormModal(d) {
    document.getElementById('editDormId').value   = d.id;
    document.getElementById('editDormName').value = d.name;
    document.getElementById('editDormType').value = d.dorm_type;
    new bootstrap.Modal(document.getElementById('editDormModal')).show();
}

function confirmDeleteDorm(e, name, roomCount) {
    e.preventDefault();
    if (roomCount > 0) {
        Swal.fire({ title:'ไม่สามารถลบได้', text:`หอพัก '${name}' ยังมีห้องพักอยู่ ${roomCount} ห้อง`, icon:'error', confirmButtonColor:'#06C755' });
        return false;
    }
    Swal.fire({
        title: 'ลบหอพัก?',
        html: `ต้องการลบ <strong>'${name}'</strong> ออกจากระบบ?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ยืนยันการลบ',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b'
    }).then(r => { if (r.isConfirmed) e.target.submit(); });
    return false;
}

function openAddRoomModal() {
    new bootstrap.Modal(document.getElementById('addRoomModal')).show();
}

function openBatchModal() {
    new bootstrap.Modal(document.getElementById('batchModal')).show();
}

function openEditRoomModal(room) {
    document.getElementById('editRoomId').value        = room.id;
    document.getElementById('editRoomNumber').value    = room.room_number;
    document.getElementById('editRoomStatus').value    = room.status;
    document.getElementById('editRoomDorm').value      = room.dorm_name;
    document.getElementById('editRoomWaterInit').value = room.water_meter_init ?? '';
    new bootstrap.Modal(document.getElementById('editRoomModal')).show();
}

function updateBatchPreview() {
    const prefix  = document.getElementById('batchPrefix').value.trim();
    const fromNum = parseInt(document.getElementById('batchFrom').value);
    const toNum   = parseInt(document.getElementById('batchTo').value);
    const preview = document.getElementById('batchPreview');

    if (!prefix || !fromNum || !toNum || fromNum > toNum) {
        preview.style.display = 'none';
        return;
    }
    const count = toNum - fromNum + 1;
    let samples = [];
    for (let i = fromNum; i <= Math.min(toNum, fromNum + 4); i++) samples.push(prefix + i);
    if (count > 5) samples.push('...' + prefix + toNum);

    preview.style.display = 'block';
    preview.innerHTML = `<strong>จะสร้าง ${count} ห้อง:</strong> ${samples.join(', ')}`;
}
</script>
JS;

include 'includes/footer.php';
?>
