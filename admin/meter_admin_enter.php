<?php
require_once 'includes/auth_check.php';
require_once '../connect.php';

$page_title    = 'กรอกมิเตอร์น้ำโดยผู้ดูแล';
$page_subtitle  = 'กรอกเลขมิเตอร์แทนห้องที่ยังไม่มีข้อมูล';
$current_page  = 'meter_admin_enter';

// ====================================================================
// POST: บันทึกเลขมิเตอร์
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_id    = (int)($_POST['room_id']   ?? 0);
    $water_curr = trim($_POST['water_curr'] ?? '');
    $cycle_id_p = trim($_POST['cycle_id']   ?? '');

    if ($room_id > 0 && is_numeric($water_curr) && $cycle_id_p !== '') {
        try {
            // ดึง water_prev
            try {
                $prevStmt = $pdo->prepare("
                    SELECT COALESCE(
                        (SELECT bm2.water_curr
                         FROM bill_meters bm2
                         WHERE bm2.room_id = :rid
                           AND bm2.water_status = 'verified'
                           AND bm2.cycle_id != :cid
                         ORDER BY bm2.cycle_id DESC LIMIT 1),
                        (SELECT r.water_meter_init FROM rooms r WHERE r.id = :rid2)
                    ) AS water_prev
                ");
                $prevStmt->execute(['rid' => $room_id, 'cid' => $cycle_id_p, 'rid2' => $room_id]);
                $water_prev = $prevStmt->fetchColumn() ?: null;
            } catch (PDOException $e) {
                $prevStmt = $pdo->prepare("
                    SELECT water_curr FROM bill_meters
                    WHERE room_id = ? AND water_status = 'verified' AND cycle_id != ?
                    ORDER BY cycle_id DESC LIMIT 1
                ");
                $prevStmt->execute([$room_id, $cycle_id_p]);
                $water_prev = $prevStmt->fetchColumn() ?: null;
            }

            $ex = $pdo->prepare("SELECT id FROM bill_meters WHERE cycle_id = ? AND room_id = ? LIMIT 1");
            $ex->execute([$cycle_id_p, $room_id]);
            $existing = $ex->fetchColumn();

            if ($existing) {
                $pdo->prepare("
                    UPDATE bill_meters
                    SET water_prev = ?, water_curr = ?,
                        water_status = 'verified', water_verified_at = NOW(),
                        water_photo = NULL, water_submitted_at = NOW()
                    WHERE id = ?
                ")->execute([$water_prev, $water_curr, $existing]);
            } else {
                $pdo->prepare("
                    INSERT INTO bill_meters
                        (cycle_id, room_id, water_prev, water_curr, water_status, water_submitted_at, water_verified_at)
                    VALUES (?, ?, ?, ?, 'verified', NOW(), NOW())
                ")->execute([$cycle_id_p, $room_id, $water_prev, $water_curr]);
            }

            $successMsg = 'บันทึกเลขมิเตอร์ห้องเรียบร้อยแล้ว';
        } catch (PDOException $e) {
            error_log('meter_admin_enter: ' . $e->getMessage());
            $errorMsg = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
        }
    }
}

// ====================================================================
// ดึงข้อมูล
// ====================================================================
$cycleRow = $pdo->query("SELECT id, label FROM bill_cycles WHERE is_current = 1 LIMIT 1")->fetch();
$cycle_id    = $cycleRow['id']    ?? null;
$cycle_label = $cycleRow['label'] ?? '–';

$rateRow   = $pdo->query("SELECT value FROM bill_settings WHERE setting_key = 'rate_water' LIMIT 1")->fetch();
$rateWater = $rateRow ? (float)$rateRow['value'] : 0;

$noMeterRooms = [];
$verifiedByAdmin = 0;

if ($cycle_id) {
    // ห้องที่ยังไม่มีมิเตอร์รอบนี้
    try {
        $stmt = $pdo->prepare("
            SELECT r.id AS room_id, r.room_number,
                   s.name AS student_name,
                   COALESCE(
                       (SELECT bm2.water_curr
                        FROM bill_meters bm2
                        WHERE bm2.room_id = r.id AND bm2.water_status = 'verified'
                          AND bm2.cycle_id != :cid
                        ORDER BY bm2.cycle_id DESC LIMIT 1),
                       r.water_meter_init
                   ) AS water_prev
            FROM rooms r
            LEFT JOIN students s ON s.room_id = r.id
            WHERE r.id NOT IN (
                SELECT room_id FROM bill_meters WHERE cycle_id = :cid2
            )
            ORDER BY r.room_number ASC
        ");
        $stmt->execute(['cid' => $cycle_id, 'cid2' => $cycle_id]);
        $noMeterRooms = $stmt->fetchAll();
    } catch (PDOException $e) {
        // fallback ถ้า water_meter_init ยังไม่มี
        $stmt = $pdo->prepare("
            SELECT r.id AS room_id, r.room_number,
                   s.name AS student_name,
                   (SELECT bm2.water_curr FROM bill_meters bm2
                    WHERE bm2.room_id = r.id AND bm2.water_status = 'verified'
                      AND bm2.cycle_id != ?
                    ORDER BY bm2.cycle_id DESC LIMIT 1) AS water_prev
            FROM rooms r
            LEFT JOIN students s ON s.room_id = r.id
            WHERE r.id NOT IN (SELECT room_id FROM bill_meters WHERE cycle_id = ?)
            ORDER BY r.room_number ASC
        ");
        $stmt->execute([$cycle_id, $cycle_id]);
        $noMeterRooms = $stmt->fetchAll();
    }

    $vc = $pdo->prepare("SELECT COUNT(*) FROM bill_meters WHERE cycle_id = ? AND water_status = 'verified'");
    $vc->execute([$cycle_id]);
    $verifiedByAdmin = (int)$vc->fetchColumn();
}

$extra_head = <<<'CSS'
<style>
.cycle-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: #f0fdf4; color: #16a34a;
    border: 1px solid #bbf7d0;
    border-radius: 20px; padding: 5px 14px;
    font-size: 0.85rem; font-weight: 600;
}
.stat-strip {
    display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap;
}
.stat-chip {
    background: white; border-radius: 12px;
    padding: 12px 18px; border: 1px solid #e2e8f0;
    box-shadow: 0 1px 4px rgba(0,0,0,.04);
    display: flex; align-items: center; gap: 10px;
}
.stat-chip .sc-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}
.stat-chip .sc-num { font-size: 1.25rem; font-weight: 700; color: #1e293b; }
.stat-chip .sc-lbl { font-size: 0.75rem; color: #94a3b8; }

.room-pill {
    background: #f0fdf4; color: #0d9488;
    border-radius: 6px; padding: 2px 8px;
    font-size: 0.82rem; font-weight: 700;
}
.prev-chip {
    background: #f1f5f9; color: #475569;
    border-radius: 6px; padding: 2px 8px;
    font-size: 0.8rem;
}
.curr-input {
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    padding: 7px 12px; font-size: 0.9rem;
    font-family: 'Kanit', sans-serif; color: #1e293b;
    width: 130px; background: #f8fafc;
    transition: border-color .2s, box-shadow .2s;
    outline: none;
}
.curr-input:focus {
    border-color: #0d9488;
    box-shadow: 0 0 0 3px rgba(13,148,136,.12);
    background: white;
}
.btn-admin-save {
    border: none; background: #0d9488; color: white;
    border-radius: 8px; padding: 7px 18px;
    font-size: 0.85rem; font-weight: 600;
    font-family: 'Kanit', sans-serif; cursor: pointer;
    transition: opacity .2s;
    display: inline-flex; align-items: center; gap: 5px;
    white-space: nowrap;
}
.btn-admin-save:hover { opacity: .88; }

.enter-table { width: 100%; border-collapse: collapse; }
.enter-table th {
    font-size: 0.78rem; font-weight: 600; color: #64748b;
    text-transform: uppercase; letter-spacing: .03em;
    background: #f8fafc; padding: 10px 16px;
    border-bottom: 1px solid #e2e8f0; text-align: left;
}
.enter-table td {
    padding: 10px 16px; border-bottom: 1px solid #f1f5f9;
    vertical-align: middle; font-size: 0.88rem; color: #334155;
}
.enter-table tr:last-child td { border-bottom: none; }
.enter-table tr:hover td { background: #f8fafc; }

.empty-state {
    text-align: center; padding: 60px 20px; color: #94a3b8;
}
.empty-state i { font-size: 3rem; margin-bottom: 12px; display: block; }
.empty-state h5 { font-weight: 600; color: #475569; margin-bottom: 6px; }
</style>
CSS;

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-pencil-square me-2" style="color:#0d9488;"></i>กรอกมิเตอร์น้ำโดยผู้ดูแล</h2>
        <p class="page-desc">กรอกเลขมิเตอร์แทนสำหรับห้องที่ยังไม่ได้ส่ง — ยืนยันอัตโนมัติ ไม่ต้องแนบรูป</p>
    </div>
    <span class="cycle-badge">
        <i class="bi bi-calendar3"></i> <?= htmlspecialchars($cycle_label) ?>
    </span>
</div>

<?php if (!empty($successMsg)): ?>
<div class="alert alert-success rounded-3 d-flex align-items-center gap-2 mb-4" style="font-size:.9rem;">
    <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($successMsg) ?>
</div>
<?php endif; ?>
<?php if (!empty($errorMsg)): ?>
<div class="alert alert-danger rounded-3 d-flex align-items-center gap-2 mb-4" style="font-size:.9rem;">
    <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errorMsg) ?>
</div>
<?php endif; ?>

<!-- Stat strip -->
<div class="stat-strip">
    <div class="stat-chip">
        <div class="sc-icon" style="background:#fef3c7;color:#d97706;"><i class="bi bi-exclamation-circle-fill"></i></div>
        <div>
            <div class="sc-num"><?= count($noMeterRooms) ?></div>
            <div class="sc-lbl">ยังไม่มีข้อมูล</div>
        </div>
    </div>
    <div class="stat-chip">
        <div class="sc-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-check-circle-fill"></i></div>
        <div>
            <div class="sc-num"><?= $verifiedByAdmin ?></div>
            <div class="sc-lbl">ยืนยันแล้ว</div>
        </div>
    </div>
    <?php if ($rateWater > 0): ?>
    <div class="stat-chip">
        <div class="sc-icon" style="background:#f0fdfa;color:#0d9488;"><i class="bi bi-droplet-fill"></i></div>
        <div>
            <div class="sc-num">฿<?= number_format($rateWater, 0) ?></div>
            <div class="sc-lbl">บาท/หน่วย</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (!$cycle_id): ?>
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            <h5>ไม่พบรอบบิลที่ใช้งานอยู่</h5>
            <p style="font-size:.88rem;">กรุณาตั้งค่ารอบบิลปัจจุบันก่อน</p>
        </div>
    </div>
</div>

<?php elseif (empty($noMeterRooms)): ?>
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <i class="bi bi-check2-all" style="color:#0d9488;"></i>
            <h5 style="color:#0d9488;">ทุกห้องมีข้อมูลมิเตอร์ครบแล้ว</h5>
            <p style="font-size:.88rem;">ไม่มีห้องที่ต้องกรอกมิเตอร์เพิ่มเติมในรอบนี้</p>
        </div>
    </div>
</div>

<?php else: ?>

<div class="panel">
    <div class="panel-header">
        <span class="panel-title">
            <i class="bi bi-list-ul"></i> ห้องที่ยังไม่มีเลขมิเตอร์
        </span>
        <span style="font-size:.8rem;color:#94a3b8;">กรอกเลขปัจจุบันแล้วกด "บันทึก"</span>
    </div>
    <div class="table-responsive">
        <table class="enter-table">
            <thead>
                <tr>
                    <th>ห้อง</th>
                    <th>ผู้เช่า</th>
                    <th>เลขครั้งก่อน</th>
                    <th>เลขปัจจุบัน & บันทึก</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($noMeterRooms as $nr): ?>
                <tr>
                    <td><span class="room-pill"><?= htmlspecialchars($nr['room_number']) ?></span></td>
                    <td><?= htmlspecialchars($nr['student_name'] ?? '–') ?></td>
                    <td>
                        <?php if ($nr['water_prev'] !== null): ?>
                            <span class="prev-chip"><?= number_format((float)$nr['water_prev'], 2) ?></span>
                        <?php else: ?>
                            <span style="color:#94a3b8;font-size:.8rem;">ไม่มีข้อมูล</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" style="display:flex;gap:8px;align-items:center;">
                            <input type="hidden" name="room_id"  value="<?= $nr['room_id'] ?>">
                            <input type="hidden" name="cycle_id" value="<?= htmlspecialchars($cycle_id) ?>">
                            <input type="number" name="water_curr" class="curr-input"
                                   placeholder="0" min="0" step="1" inputmode="numeric" required>
                            <button type="submit" class="btn-admin-save">
                                <i class="bi bi-check2"></i> บันทึก
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php
include 'includes/footer.php';
?>
