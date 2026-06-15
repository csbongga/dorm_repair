<?php
require_once 'includes/auth_check.php';
require_once '../connect.php';

$ticket = trim($_GET['ticket'] ?? '');
if (!$ticket) {
    header('Location: repairs.php');
    exit;
}

// ===== ดึงข้อมูลใบงาน =====
$stmt = $pdo->prepare("
    SELECT rr.*, d.name AS dorm_name, r.room_number,
           s.name AS student_name_db, s.phone AS student_phone_db
    FROM repair_requests rr
    JOIN rooms r ON rr.room_id = r.id
    JOIN dorms d ON r.dorm_id = d.id
    LEFT JOIN students s ON rr.student_id = s.student_id
    WHERE rr.ticket_id = ?
");
$stmt->execute([$ticket]);
$repair = $stmt->fetch();

if (!$repair) {
    header('Location: repairs.php');
    exit;
}

// รายการอุปกรณ์
$itemsStmt = $pdo->prepare("
    SELECT ri.id, ri.quantity, ri.status,
           rim.item_name, rim.category
    FROM repair_items ri
    JOIN repair_items_master rim ON ri.item_master_id = rim.id
    WHERE ri.request_id = ?
    ORDER BY rim.category, rim.item_name
");
$itemsStmt->execute([$repair['id']]);
$items = $itemsStmt->fetchAll();

// รูปภาพ
$imagesStmt = $pdo->prepare("SELECT id, image_url, uploaded_at FROM repair_images WHERE request_id = ? ORDER BY uploaded_at ASC");
$imagesStmt->execute([$repair['id']]);
$images = $imagesStmt->fetchAll();

// ช่างทั้งหมด (active)
$techStmt = $pdo->query("SELECT id, name, specialty FROM staff WHERE status = 'active' ORDER BY name");
$technicians = $techStmt->fetchAll();

// ===== อัปเดตสถานะ (POST) =====
$msg     = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $isAjax  = ($_POST['json'] ?? '') === '1';

    if ($action === 'update_status') {
        $newStatus = $_POST['status'] ?? '';
        $allowed   = ['รอดำเนินการ', 'กำลังดำเนินการ', 'เสร็จสิ้น', 'ยกเลิก'];
        if (in_array($newStatus, $allowed) && $repair['status'] !== $newStatus) {
            $oldStatus = $repair['status'];

            $pdo->prepare("INSERT INTO repair_request_logs (request_id, old_status, new_status, staff_id, staff_name) VALUES (?,?,?,?,?)")
                ->execute([$repair['id'], $oldStatus, $newStatus, $_SESSION['admin_id'], $_SESSION['admin_name']]);
            $pdo->prepare("UPDATE repair_requests SET status = ? WHERE ticket_id = ?")->execute([$newStatus, $ticket]);

            $logItemStmt = $pdo->prepare("INSERT INTO repair_item_logs (item_id, request_id, item_name, old_status, new_status, staff_id, staff_name) VALUES (?,?,?,?,?,?,?)");
            if ($newStatus === 'เสร็จสิ้น') {
                $affectedItems = $pdo->prepare("SELECT ri.id, ri.status, rim.item_name FROM repair_items ri JOIN repair_items_master rim ON ri.item_master_id = rim.id WHERE ri.request_id = ? AND ri.status NOT IN ('ยกเลิก','เสร็จสิ้น')");
                $affectedItems->execute([$repair['id']]);
                foreach ($affectedItems->fetchAll() as $ai) {
                    $logItemStmt->execute([$ai['id'], $repair['id'], $ai['item_name'], $ai['status'], 'เสร็จสิ้น', $_SESSION['admin_id'], $_SESSION['admin_name']]);
                }
                $pdo->prepare("UPDATE repair_items SET status = 'เสร็จสิ้น' WHERE request_id = ? AND status != 'ยกเลิก'")->execute([$repair['id']]);
            } elseif ($newStatus === 'กำลังดำเนินการ') {
                $affectedItems = $pdo->prepare("SELECT ri.id, rim.item_name FROM repair_items ri JOIN repair_items_master rim ON ri.item_master_id = rim.id WHERE ri.request_id = ? AND ri.status = 'รอดำเนินการ'");
                $affectedItems->execute([$repair['id']]);
                foreach ($affectedItems->fetchAll() as $ai) {
                    $logItemStmt->execute([$ai['id'], $repair['id'], $ai['item_name'], 'รอดำเนินการ', 'กำลังดำเนินการ', $_SESSION['admin_id'], $_SESSION['admin_name']]);
                }
                $pdo->prepare("UPDATE repair_items SET status = 'กำลังดำเนินการ' WHERE request_id = ? AND status = 'รอดำเนินการ'")->execute([$repair['id']]);
            }

            $repair['status'] = $newStatus;
            $msg     = 'อัปเดตสถานะใบงานเรียบร้อยแล้ว';
            $msgType = 'success';
        }
        if ($isAjax) {
            // ดึงสถานะ item ทั้งหมดหลังอัปเดต เพื่อให้ JS ซิงค์ได้โดยไม่ต้องรีเฟรช
            $updatedItems = $pdo->prepare("SELECT id, status FROM repair_items WHERE request_id = ?");
            $updatedItems->execute([$repair['id']]);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'status'  => $repair['status'],
                'items'   => $updatedItems->fetchAll(PDO::FETCH_KEY_PAIR),
            ]);
            exit;
        }
    }

    if ($action === 'update_item_status') {
        $itemId     = (int)$_POST['item_id'];
        $itemStatus = $_POST['item_status'] ?? '';
        $allowed    = ['รอดำเนินการ', 'กำลังดำเนินการ', 'เสร็จสิ้น', 'ยกเลิก'];
        if ($itemId && in_array($itemStatus, $allowed)) {
            $oldStmt = $pdo->prepare("SELECT ri.status, rim.item_name FROM repair_items ri JOIN repair_items_master rim ON ri.item_master_id = rim.id WHERE ri.id = ? AND ri.request_id = ?");
            $oldStmt->execute([$itemId, $repair['id']]);
            $oldItem = $oldStmt->fetch();
            if ($oldItem && $oldItem['status'] !== $itemStatus) {
                $pdo->prepare("UPDATE repair_items SET status = ? WHERE id = ? AND request_id = ?")->execute([$itemStatus, $itemId, $repair['id']]);
                $pdo->prepare("INSERT INTO repair_item_logs (item_id, request_id, item_name, old_status, new_status, staff_id, staff_name) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$itemId, $repair['id'], $oldItem['item_name'], $oldItem['status'], $itemStatus, $_SESSION['admin_id'], $_SESSION['admin_name']]);
            }
            $msg     = 'อัปเดตสถานะรายการเรียบร้อยแล้ว';
            $msgType = 'success';
        }
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true]);
            exit;
        }
        $itemsStmt->execute([$repair['id']]);
        $items = $itemsStmt->fetchAll();
    }

    if ($action === 'add_note') {
        $note = trim($_POST['note'] ?? '');
        if ($note) {
            $pdo->prepare("UPDATE repair_requests SET additional_details = ? WHERE ticket_id = ?")->execute([$note, $ticket]);
            $repair['additional_details'] = $note;
            $msg     = 'บันทึกหมายเหตุเรียบร้อยแล้ว';
            $msgType = 'success';
        }
    }
}

// ดึง logs ทั้งหมดของใบงานนี้ (รวม item + request level)
$logsStmt = $pdo->prepare("
    SELECT 'item' AS log_type, item_name AS subject, old_status, new_status, staff_name, changed_at
    FROM repair_item_logs
    WHERE request_id = ?
    UNION ALL
    SELECT 'request' AS log_type, 'สถานะใบงาน' AS subject, old_status, new_status, staff_name, changed_at
    FROM repair_request_logs
    WHERE request_id = ?
    ORDER BY changed_at DESC
");
$logsStmt->execute([$repair['id'], $repair['id']]);
$logs = $logsStmt->fetchAll();

$page_title   = 'ใบงาน ' . htmlspecialchars($ticket);
$current_page = 'repairs';

$statuses = ['รอดำเนินการ', 'กำลังดำเนินการ', 'เสร็จสิ้น', 'ยกเลิก'];

function statusBadge($s) {
    $map = [
        'รอดำเนินการ'    => ['badge-pending',   'bi-hourglass-split'],
        'กำลังดำเนินการ' => ['badge-progress',  'bi-gear-fill'],
        'เสร็จสิ้น'      => ['badge-completed', 'bi-check-circle-fill'],
        'ยกเลิก'         => ['badge-cancelled', 'bi-x-circle-fill'],
    ];
    [$cls, $icon] = $map[$s] ?? ['badge-pending', 'bi-circle'];
    return "<span class='badge-status {$cls}'><i class='bi {$icon} me-1'></i>{$s}</span>";
}

function catEmoji($c) {
    return ['ประปา' => '💧', 'ไฟฟ้า' => '⚡', 'ซ่อมสร้าง' => '🔨'][$c] ?? '🛠️';
}

include 'includes/header.php';
?>

<!-- Breadcrumb -->
<nav style="font-size:0.85rem;color:#94a3b8;margin-bottom:16px;">
    <a href="repairs.php" style="color:#06C755;text-decoration:none;">ใบงานทั้งหมด</a>
    <span class="mx-2">/</span>
    <span><?= htmlspecialchars($ticket) ?></span>
</nav>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'success' ? 'success' : 'danger' ?> rounded-3 d-flex align-items-center gap-2 mb-4" style="font-size:0.9rem;">
    <i class="bi bi-<?= $msgType === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill' ?>"></i>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="row g-4">

    <!-- Left: Main Details -->
    <div class="col-lg-8">

        <!-- Ticket Header Card -->
        <div class="panel mb-4">
            <div class="panel-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <h4 style="font-size:1.25rem;font-weight:700;color:#1e293b;margin-bottom:4px;">
                            <i class="bi bi-receipt me-2" style="color:#06C755;"></i><?= htmlspecialchars($repair['ticket_id']) ?>
                        </h4>
                        <div style="font-size:0.82rem;color:#94a3b8;">
                            แจ้งเมื่อ <?= date('d/m/Y H:i', strtotime($repair['created_at'])) ?> น.
                        </div>
                    </div>
                    <span id="ticketStatusBadge"><?= statusBadge($repair['status']) ?></span>
                </div>

                <hr style="border-color:#f1f5f9;margin:16px 0;">

                <div class="row g-3">
                    <div class="col-sm-6">
                        <div style="font-size:0.78rem;color:#94a3b8;margin-bottom:3px;">ผู้แจ้ง</div>
                        <div style="font-weight:600;color:#1e293b;"><?= htmlspecialchars($repair['reporter_name']) ?></div>
                        <?php if ($repair['reporter_phone']): ?>
                        <div style="font-size:0.85rem;color:#64748b;">
                            <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($repair['reporter_phone']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-6">
                        <div style="font-size:0.78rem;color:#94a3b8;margin-bottom:3px;">ที่พัก</div>
                        <div style="font-weight:600;color:#1e293b;"><?= htmlspecialchars($repair['dorm_name']) ?></div>
                        <div style="font-size:0.85rem;color:#64748b;">ห้อง <?= htmlspecialchars($repair['room_number']) ?></div>
                    </div>
                    <?php if ($repair['student_id']): ?>
                    <div class="col-sm-6">
                        <div style="font-size:0.78rem;color:#94a3b8;margin-bottom:3px;">รหัสนักศึกษา</div>
                        <div style="font-weight:600;color:#1e293b;"><?= htmlspecialchars($repair['student_id']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($repair['additional_details']): ?>
                <div style="margin-top:16px;padding:14px;background:#f8fafc;border-radius:12px;border-left:3px solid #06C755;">
                    <div style="font-size:0.78rem;color:#94a3b8;margin-bottom:4px;"><i class="bi bi-chat-left-dots me-1"></i>รายละเอียดปัญหา</div>
                    <div style="font-size:0.9rem;color:#334155;line-height:1.6;"><?= nl2br(htmlspecialchars($repair['additional_details'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Repair Items -->
        <div class="panel mb-4">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-card-checklist"></i> รายการอุปกรณ์แจ้งซ่อม</span>
            </div>
            <div class="table-responsive">
                <table class="table table-clean mb-0">
                    <thead>
                        <tr>
                            <th style="padding-left:20px;">อุปกรณ์</th>
                            <th>หมวดหมู่</th>
                            <th>จำนวน</th>
                            <th>สถานะ</th>
                            <th style="padding-right:20px;">อัปเดต</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td style="padding-left:20px;font-weight:500;">
                                <?= catEmoji($item['category']) ?> <?= htmlspecialchars($item['item_name']) ?>
                            </td>
                            <td><small class="text-muted"><?= htmlspecialchars($item['category']) ?></small></td>
                            <td><?= $item['quantity'] ?></td>
                            <td id="item-badge-<?= $item['id'] ?>"><?= statusBadge($item['status']) ?></td>
                            <td style="padding-right:20px;">
                                <select class="form-select form-select-sm item-status-select" data-item-id="<?= $item['id'] ?>" style="font-size:0.8rem;width:auto;">
                                    <?php foreach ($statuses as $s): ?>
                                    <option value="<?= $s ?>" <?= $item['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Images -->
        <?php if (!empty($images)): ?>
        <div class="panel mb-4">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-images"></i> รูปภาพประกอบ (<?= count($images) ?> รูป)</span>
            </div>
            <div class="panel-body">
                <div class="row g-2">
                    <?php foreach ($images as $img): ?>
                    <div class="col-4 col-sm-3 col-md-2">
                        <div style="aspect-ratio:1;border-radius:10px;overflow:hidden;border:1.5px solid #e2e8f0;cursor:pointer;"
                             onclick="previewImg('<?= htmlspecialchars('../' . $img['image_url']) ?>')">
                            <img src="../<?= htmlspecialchars($img['image_url']) ?>"
                                 style="width:100%;height:100%;object-fit:cover;"
                                 onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><rect fill=%22%23f1f5f9%22 width=%22100%22 height=%22100%22/><text y=%2250%22 x=%2250%22 text-anchor=%22middle%22 font-size=%2218%22>📷</text></svg>'"
                                 alt="รูปภาพซ่อม">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Add Note -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-pencil-square"></i> แก้ไขรายละเอียด / หมายเหตุ</span>
            </div>
            <div class="panel-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_note">
                    <textarea name="note" class="form-control mb-3" rows="3"
                              placeholder="เพิ่มหมายเหตุหรือรายละเอียดเพิ่มเติม..."
                              style="border-radius:10px;font-family:'Kanit',sans-serif;"><?= htmlspecialchars($repair['additional_details'] ?? '') ?></textarea>
                    <button type="submit" class="btn btn-sm" style="background:#06C755;color:white;border:none;border-radius:8px;padding:8px 20px;">
                        <i class="bi bi-save me-1"></i>บันทึกหมายเหตุ
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right: Actions -->
    <div class="col-lg-4">

        <!-- Update Status -->
        <div class="panel mb-4">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-arrow-repeat"></i> อัปเดตสถานะใบงาน</span>
            </div>
            <div class="panel-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <div class="mb-3">
                        <?php foreach ($statuses as $s): ?>
                        <?php
                        $colors = [
                            'รอดำเนินการ'    => ['#fef3c7','#d97706','#f59e0b'],
                            'กำลังดำเนินการ' => ['#dbeafe','#1d4ed8','#3b82f6'],
                            'เสร็จสิ้น'      => ['#d1fae5','#065f46','#059669'],
                            'ยกเลิก'         => ['#fee2e2','#991b1b','#dc2626'],
                        ];
                        [$bg, $text, $border] = $colors[$s];
                        $checked = $repair['status'] === $s;
                        ?>
                        <label class="d-flex align-items-center gap-3 p-3 mb-2 rounded-3"
                               style="cursor:pointer;border:2px solid <?= $checked ? $border : '#e2e8f0' ?>;background:<?= $checked ? $bg : '#fff' ?>;transition:all 0.2s;">
                            <input type="radio" name="status" value="<?= $s ?>" <?= $checked ? 'checked' : '' ?> class="form-check-input mt-0" style="border-color:<?= $border ?>;">
                            <span style="font-size:0.9rem;font-weight:<?= $checked ? '600' : '400' ?>;color:<?= $checked ? $text : '#334155' ?>;">
                                <?= $s ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Info Summary -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-info-circle"></i> สรุปข้อมูลใบงาน</span>
            </div>
            <div class="panel-body">
                <div class="d-flex flex-column gap-3">
                    <div>
                        <div style="font-size:0.75rem;color:#94a3b8;">ประเภทผู้แจ้ง</div>
                        <div style="font-size:0.9rem;font-weight:500;">
                            <?= $repair['reporter_type'] === 'student' ? '🎓 นักศึกษา' : ($repair['reporter_type'] === 'admin' ? '👤 เจ้าหน้าที่' : '🙍 บุคคลทั่วไป') ?>
                        </div>
                    </div>
                    <div>
                        <div style="font-size:0.75rem;color:#94a3b8;">จำนวนอุปกรณ์</div>
                        <div style="font-size:0.9rem;font-weight:500;"><?= count($items) ?> รายการ</div>
                    </div>
                    <div>
                        <div style="font-size:0.75rem;color:#94a3b8;">รูปภาพประกอบ</div>
                        <div style="font-size:0.9rem;font-weight:500;"><?= count($images) ?> รูป</div>
                    </div>
                    <?php
                    $completedItems = array_filter($items, fn($i) => $i['status'] === 'เสร็จสิ้น');
                    $itemPct = count($items) > 0 ? round(count($completedItems) / count($items) * 100) : 0;
                    ?>
                    <div>
                        <div class="d-flex justify-content-between mb-1">
                            <span style="font-size:0.75rem;color:#94a3b8;">ความคืบหน้า</span>
                            <span style="font-size:0.75rem;color:#06C755;font-weight:600;"><?= $itemPct ?>%</span>
                        </div>
                        <div style="height:8px;background:#f1f5f9;border-radius:4px;overflow:hidden;">
                            <div style="height:100%;width:<?= $itemPct ?>%;background:linear-gradient(90deg,#06C755,#05a044);border-radius:4px;transition:width 0.5s;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ประวัติการเปลี่ยนสถานะ (full width) -->
<div class="panel mt-2">
    <div class="panel-header">
        <span class="panel-title">
            <i class="bi bi-clock-history"></i> ประวัติการเปลี่ยนสถานะ
        </span>
        <span style="font-size:0.82rem;color:#94a3b8;"><?= count($logs) ?> รายการ</span>
    </div>
    <?php if (empty($logs)): ?>
    <div class="panel-body text-center text-muted py-4" style="font-size:0.88rem;">
        <i class="bi bi-clock" style="font-size:1.5rem;display:block;margin-bottom:6px;color:#cbd5e1;"></i>
        ยังไม่มีประวัติการเปลี่ยนสถานะ
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-clean mb-0">
            <thead>
                <tr>
                    <th style="padding-left:20px;">รายการ</th>
                    <th>จากสถานะ</th>
                    <th>เปลี่ยนเป็น</th>
                    <th>โดย</th>
                    <th style="padding-right:20px;">วันเวลา</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <?php
                $isRequest = $log['log_type'] === 'request';
                ?>
                <tr>
                    <td style="padding-left:20px;">
                        <?php if ($isRequest): ?>
                        <span style="font-size:0.75rem;background:#ede9fe;color:#7c3aed;padding:2px 7px;border-radius:5px;font-weight:500;margin-right:6px;">ใบงาน</span>
                        <?php else: ?>
                        <span style="font-size:0.75rem;background:#f0fdf4;color:#059669;padding:2px 7px;border-radius:5px;font-weight:500;margin-right:6px;">อุปกรณ์</span>
                        <?php endif; ?>
                        <span style="font-size:0.88rem;font-weight:500;color:#1e293b;"><?= htmlspecialchars($log['subject']) ?></span>
                    </td>
                    <td>
                        <?php if ($log['old_status']): ?>
                        <?= statusBadge($log['old_status']) ?>
                        <?php else: ?>
                        <span style="color:#cbd5e1;font-size:0.82rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= statusBadge($log['new_status']) ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:7px;">
                            <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#06C755,#05a044);display:flex;align-items:center;justify-content:center;color:white;font-size:0.72rem;font-weight:700;flex-shrink:0;">
                                <?= mb_substr($log['staff_name'], 0, 1) ?>
                            </div>
                            <span style="font-size:0.88rem;color:#334155;"><?= htmlspecialchars($log['staff_name']) ?></span>
                        </div>
                    </td>
                    <td style="padding-right:20px;font-size:0.82rem;color:#94a3b8;white-space:nowrap;">
                        <?= date('d/m/Y', strtotime($log['changed_at'])) ?><br>
                        <span style="font-size:0.78rem;"><?= date('H:i:s', strtotime($log['changed_at'])) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
$extra_scripts = <<<'JS'
<script>
// ── badge HTML helper ─────────────────────────────────────────────────────
const STATUS_MAP = {
    'รอดำเนินการ':    ['badge-pending',   'bi-hourglass-split'],
    'กำลังดำเนินการ': ['badge-progress',  'bi-gear-fill'],
    'เสร็จสิ้น':      ['badge-completed', 'bi-check-circle-fill'],
    'ยกเลิก':         ['badge-cancelled', 'bi-x-circle-fill'],
};
const STATUS_COLORS = {
    'รอดำเนินการ':    ['#fef3c7','#d97706','#f59e0b'],
    'กำลังดำเนินการ': ['#dbeafe','#1d4ed8','#3b82f6'],
    'เสร็จสิ้น':      ['#d1fae5','#065f46','#059669'],
    'ยกเลิก':         ['#fee2e2','#991b1b','#dc2626'],
};
function makeBadge(s) {
    const [cls, icon] = STATUS_MAP[s] ?? ['badge-pending','bi-circle'];
    return `<span class="badge-status ${cls}"><i class="bi ${icon} me-1"></i>${s}</span>`;
}

// ── preview image ─────────────────────────────────────────────────────────
function previewImg(url) {
    Swal.fire({
        imageUrl: url,
        imageAlt: 'รูปภาพประกอบใบงาน',
        confirmButtonText: 'ปิด',
        confirmButtonColor: '#06C755',
        background: '#fff'
    });
}

// ── toast helper ─────────────────────────────────────────────────────────
function showToast(msg, icon = 'success') {
    Swal.fire({
        toast: true, position: 'top-end', showConfirmButton: false,
        timer: 2200, timerProgressBar: true,
        icon, title: msg,
    });
}

// ── update_status (AJAX on radio change) ─────────────────────────────────
async function saveStatus(newStatus) {
    const fd = new FormData();
    fd.set('action', 'update_status');
    fd.set('status', newStatus);
    fd.set('json',   '1');
    try {
        const res  = await fetch(location.href, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            // อัปเดต badge หัวใบงาน
            document.getElementById('ticketStatusBadge').innerHTML = makeBadge(data.status);
            // อัปเดตไฮไลต์ radio
            document.querySelectorAll('input[name="status"]').forEach(r => {
                const lbl = r.closest('label');
                const [bg, text, border] = STATUS_COLORS[r.value] ?? ['#fff','#334155','#e2e8f0'];
                const active = r.value === data.status;
                lbl.style.borderColor = active ? border : '#e2e8f0';
                lbl.style.background  = active ? bg     : '#fff';
                lbl.querySelector('span').style.color      = active ? text  : '#334155';
                lbl.querySelector('span').style.fontWeight = active ? '600' : '400';
            });
            // อัปเดต badge + select ของ item ทุกรายการ
            if (data.items) {
                Object.entries(data.items).forEach(([id, status]) => {
                    const badge = document.getElementById('item-badge-' + id);
                    const sel   = document.querySelector(`.item-status-select[data-item-id="${id}"]`);
                    if (badge) badge.innerHTML = makeBadge(status);
                    if (sel)   sel.value = status;
                });
            }
            showToast('อัปเดตสถานะใบงานเรียบร้อยแล้ว');
        }
    } catch(err) {
        showToast('เกิดข้อผิดพลาด กรุณาลองใหม่', 'error');
    }
}
document.querySelectorAll('input[name="status"]').forEach(r => {
    r.addEventListener('change', () => saveStatus(r.value));
});

// ── update_item_status (AJAX on select change) ───────────────────────────
document.querySelectorAll('.item-status-select').forEach(sel => {
    sel.addEventListener('change', async function() {
        const itemId    = this.dataset.itemId;
        const newStatus = this.value;
        this.disabled   = true;
        const fd = new FormData();
        fd.set('action',      'update_item_status');
        fd.set('item_id',     itemId);
        fd.set('item_status', newStatus);
        fd.set('json',        '1');
        try {
            const res  = await fetch(location.href, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                document.getElementById('item-badge-' + itemId).innerHTML = makeBadge(newStatus);
                showToast('อัปเดตสถานะรายการเรียบร้อยแล้ว');
            }
        } catch(err) {
            showToast('เกิดข้อผิดพลาด กรุณาลองใหม่', 'error');
        }
        this.disabled = false;
    });
});
</script>
JS;

include 'includes/footer.php';
?>
