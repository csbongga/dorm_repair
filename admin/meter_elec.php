<?php
require_once 'includes/auth_check.php';
require_once '../connect.php';

$page_title   = 'กรอกมิเตอร์ไฟฟ้า';
$current_page = 'meter_elec';

// ====================================================================
// AJAX POST: บันทึกเลขมิเตอร์ไฟฟ้า
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax'])) {
    header('Content-Type: application/json');
    $room_id_p  = (int)($_POST['room_id']   ?? 0);
    $elec_curr  = trim($_POST['elec_curr']  ?? '');
    $cycle_id_p = trim($_POST['cycle_id']   ?? '');

    if ($room_id_p > 0 && is_numeric($elec_curr) && $cycle_id_p !== '') {
        try {
            // ดึง elec_prev
            try {
                $prevStmt = $pdo->prepare("
                    SELECT COALESCE(
                        (SELECT bm2.elec_curr
                         FROM bill_meters bm2
                         WHERE bm2.room_id = :rid
                           AND bm2.elec_entered = 1
                           AND bm2.cycle_id != :cid
                         ORDER BY bm2.cycle_id DESC LIMIT 1),
                        (SELECT r.elec_meter_init FROM rooms r WHERE r.id = :rid2)
                    ) AS elec_prev
                ");
                $prevStmt->execute(['rid' => $room_id_p, 'cid' => $cycle_id_p, 'rid2' => $room_id_p]);
                $elec_prev = $prevStmt->fetchColumn() ?: null;
            } catch (PDOException $e2) {
                $prevStmt = $pdo->prepare("
                    SELECT elec_curr FROM bill_meters
                    WHERE room_id = ? AND elec_entered = 1 AND cycle_id != ?
                    ORDER BY cycle_id DESC LIMIT 1
                ");
                $prevStmt->execute([$room_id_p, $cycle_id_p]);
                $elec_prev = $prevStmt->fetchColumn() ?: null;
            }

            $ex = $pdo->prepare("SELECT id FROM bill_meters WHERE cycle_id = ? AND room_id = ? LIMIT 1");
            $ex->execute([$cycle_id_p, $room_id_p]);
            $existing = $ex->fetchColumn();

            if ($existing) {
                $pdo->prepare("
                    UPDATE bill_meters
                    SET elec_prev = ?, elec_curr = ?, elec_entered = 1, elec_entered_at = NOW()
                    WHERE id = ?
                ")->execute([$elec_prev, $elec_curr, $existing]);
            } else {
                $pdo->prepare("
                    INSERT INTO bill_meters
                        (cycle_id, room_id, elec_prev, elec_curr, elec_entered, elec_entered_at)
                    VALUES (?, ?, ?, ?, 1, NOW())
                ")->execute([$cycle_id_p, $room_id_p, $elec_prev, $elec_curr]);
            }

            echo json_encode(['ok' => true]);
        } catch (PDOException $e) {
            error_log('meter_elec: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'msg' => 'เกิดข้อผิดพลาดในฐานข้อมูล']);
        }
    } else {
        echo json_encode(['ok' => false, 'msg' => 'ข้อมูลไม่ถูกต้อง']);
    }
    exit;
}

// ====================================================================
// ดึงข้อมูล
// ====================================================================
$cycleRow    = $pdo->query("SELECT id, label FROM bill_cycles WHERE is_current = 1 LIMIT 1")->fetch();
$cycle_id    = $cycleRow['id']    ?? null;
$cycle_label = $cycleRow['label'] ?? '–';

$rateRow  = $pdo->query("SELECT value FROM bill_settings WHERE setting_key = 'rate_elec' LIMIT 1")->fetch();
$rateElec = $rateRow ? (float)$rateRow['value'] : 0;

$filter_dorm  = (int)($_GET['dorm_id'] ?? 0);
$filter_floor = (int)($_GET['floor']   ?? 0);

$allDorms = $pdo->query("SELECT id, name FROM dorms ORDER BY name ASC")->fetchAll();

$noElecRooms  = [];
$enteredCount = 0;
$totalRooms   = 0;

if ($cycle_id) {
    $tc = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
    $totalRooms = (int)$tc;

    $ec = $pdo->prepare("SELECT COUNT(*) FROM bill_meters WHERE cycle_id = ? AND elec_entered = 1");
    $ec->execute([$cycle_id]);
    $enteredCount = (int)$ec->fetchColumn();

    // ห้องที่ยังไม่ได้กรอกเลขไฟ
    try {
        $stmt = $pdo->prepare("
            SELECT r.id AS room_id, r.room_number,
                   COALESCE(
                       (SELECT bm2.elec_curr
                        FROM bill_meters bm2
                        WHERE bm2.room_id = r.id AND bm2.elec_entered = 1
                          AND bm2.cycle_id != :cid
                        ORDER BY bm2.cycle_id DESC LIMIT 1),
                       r.elec_meter_init
                   ) AS elec_prev
            FROM rooms r
            WHERE (:fdorm_c = 0 OR r.dorm_id = :fdorm_v)
              AND (:ffloor_c = 0 OR r.floor = :ffloor_v)
              AND r.id NOT IN (
                  SELECT room_id FROM bill_meters WHERE cycle_id = :cid2 AND elec_entered = 1
              )
            ORDER BY r.room_number ASC
        ");
        $stmt->execute([
            'cid'     => $cycle_id,
            'cid2'    => $cycle_id,
            'fdorm_c' => $filter_dorm,
            'fdorm_v' => $filter_dorm,
            'ffloor_c' => $filter_floor,
            'ffloor_v' => $filter_floor,
        ]);
        $noElecRooms = $stmt->fetchAll();
    } catch (PDOException $e) {
        $stmt = $pdo->prepare("
            SELECT r.id AS room_id, r.room_number,
                   (SELECT bm2.elec_curr FROM bill_meters bm2
                    WHERE bm2.room_id = r.id AND bm2.elec_entered = 1
                      AND bm2.cycle_id != ?
                    ORDER BY bm2.cycle_id DESC LIMIT 1) AS elec_prev
            FROM rooms r
            WHERE r.id NOT IN (
                SELECT room_id FROM bill_meters WHERE cycle_id = ? AND elec_entered = 1
            )
            ORDER BY r.room_number ASC
        ");
        $stmt->execute([$cycle_id, $cycle_id]);
        $noElecRooms = $stmt->fetchAll();
    }
}

$extra_head = <<<'CSS'
<style>
.cycle-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: #fffbeb; color: #d97706;
    border: 1px solid #fde68a;
    border-radius: 20px; padding: 5px 14px;
    font-size: 0.85rem; font-weight: 600;
}
.stat-strip { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
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

.filter-bar {
    background: white; border-radius: 14px; border: 1px solid #e2e8f0;
    padding: 14px 18px; margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.filter-bar label { font-size: 0.82rem; color: #64748b; font-weight: 600; white-space: nowrap; }
.filter-select {
    border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 7px 12px;
    font-size: 0.88rem; font-family: 'Kanit', sans-serif; color: #334155;
    background: #f8fafc; outline: none; cursor: pointer; transition: border-color .2s;
}
.filter-select:focus { border-color: #d97706; background: white; }
.btn-filter {
    border: none; background: #d97706; color: white; border-radius: 8px; padding: 8px 18px;
    font-size: 0.85rem; font-weight: 600; font-family: 'Kanit', sans-serif; cursor: pointer;
    display: inline-flex; align-items: center; gap: 5px; transition: opacity .2s;
}
.btn-filter:hover { opacity: .88; }
.btn-filter-clear {
    font-size: 0.82rem; color: #94a3b8; text-decoration: none; padding: 8px 10px;
    border-radius: 8px; transition: color .2s; display: inline-flex; align-items: center; gap: 4px;
}
.btn-filter-clear:hover { color: #ef4444; }
.filter-active-tag {
    background: #fffbeb; color: #d97706; border: 1px solid #fde68a;
    border-radius: 20px; padding: 3px 10px; font-size: 0.78rem; font-weight: 600;
    display: inline-flex; align-items: center; gap: 5px;
}

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
.enter-table tr:hover td { background: #fafafa; }

.room-pill {
    background: #fffbeb; color: #d97706;
    border-radius: 6px; padding: 2px 8px; font-size: 0.82rem; font-weight: 700;
}
.prev-chip {
    background: #f1f5f9; color: #475569;
    border-radius: 6px; padding: 2px 8px; font-size: 0.8rem;
}
.curr-input {
    border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 7px 12px;
    font-size: 0.9rem; font-family: 'Kanit', sans-serif; color: #1e293b;
    width: 130px; background: #f8fafc; outline: none;
    transition: border-color .2s, box-shadow .2s;
}
.curr-input:focus {
    border-color: #d97706; box-shadow: 0 0 0 3px rgba(217,119,6,.12); background: white;
}
.btn-save {
    border: none; background: #d97706; color: white; border-radius: 8px; padding: 7px 18px;
    font-size: 0.85rem; font-weight: 600; font-family: 'Kanit', sans-serif; cursor: pointer;
    transition: opacity .2s; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;
}
.btn-save:hover { opacity: .88; }
.empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
.empty-state i { font-size: 3rem; margin-bottom: 12px; display: block; }
.empty-state h5 { font-weight: 600; color: #475569; margin-bottom: 6px; }
</style>
CSS;

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-lightning-charge-fill me-2" style="color:#d97706;"></i>กรอกมิเตอร์ไฟฟ้า</h2>
        <p class="page-desc">กรอกเลขมิเตอร์ไฟฟ้าประจำรอบ — ระบบคำนวณหน่วยและค่าไฟให้อัตโนมัติ</p>
    </div>
    <span class="cycle-badge"><i class="bi bi-calendar3"></i> <?= htmlspecialchars($cycle_label) ?></span>
</div>

<!-- Stat strip -->
<div class="stat-strip">
    <div class="stat-chip">
        <div class="sc-icon" style="background:#fef3c7;color:#d97706;"><i class="bi bi-exclamation-circle-fill"></i></div>
        <div><div class="sc-num"><?= count($noElecRooms) ?></div><div class="sc-lbl">ยังไม่ได้กรอก</div></div>
    </div>
    <div class="stat-chip">
        <div class="sc-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-check-circle-fill"></i></div>
        <div><div class="sc-num"><?= $enteredCount ?></div><div class="sc-lbl">กรอกแล้ว</div></div>
    </div>
    <?php if ($rateElec > 0): ?>
    <div class="stat-chip">
        <div class="sc-icon" style="background:#fffbeb;color:#d97706;"><i class="bi bi-lightning-charge-fill"></i></div>
        <div><div class="sc-num">฿<?= number_format($rateElec, 0) ?></div><div class="sc-lbl">บาท/หน่วย</div></div>
    </div>
    <?php endif; ?>
</div>

<!-- Filter bar -->
<form method="GET" action="meter_elec.php" class="filter-bar">
    <label><i class="bi bi-building me-1"></i>หอพัก</label>
    <select name="dorm_id" class="filter-select">
        <option value="0">— ทั้งหมด —</option>
        <?php foreach ($allDorms as $d): ?>
        <option value="<?= $d['id'] ?>" <?= $filter_dorm == $d['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($d['name']) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <label><i class="bi bi-layers me-1"></i>ชั้น</label>
    <select name="floor" class="filter-select">
        <option value="0">— ทุกชั้น —</option>
        <?php for ($f = 1; $f <= 4; $f++): ?>
        <option value="<?= $f ?>" <?= $filter_floor == $f ? 'selected' : '' ?>>ชั้น <?= $f ?></option>
        <?php endfor; ?>
    </select>

    <button type="submit" class="btn-filter"><i class="bi bi-funnel-fill"></i> กรอง</button>

    <?php if ($filter_dorm || $filter_floor): ?>
    <a href="meter_elec.php" class="btn-filter-clear"><i class="bi bi-x-circle"></i> ล้างตัวกรอง</a>
    <?php foreach ($allDorms as $d): ?>
        <?php if ($d['id'] == $filter_dorm): ?>
        <span class="filter-active-tag"><i class="bi bi-building"></i> <?= htmlspecialchars($d['name']) ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($filter_floor): ?>
    <span class="filter-active-tag"><i class="bi bi-layers"></i> ชั้น <?= $filter_floor ?></span>
    <?php endif; ?>
    <?php endif; ?>
</form>

<?php if (!$cycle_id): ?>
<div class="panel"><div class="panel-body">
    <div class="empty-state">
        <i class="bi bi-calendar-x"></i>
        <h5>ไม่พบรอบบิลที่ใช้งานอยู่</h5>
        <p style="font-size:.88rem;">กรุณาตั้งค่ารอบบิลปัจจุบันก่อน</p>
    </div>
</div></div>

<?php elseif (empty($noElecRooms)): ?>
<div class="panel"><div class="panel-body">
    <div class="empty-state">
        <i class="bi bi-check2-all" style="color:#d97706;"></i>
        <h5 style="color:#d97706;">กรอกมิเตอร์ไฟฟ้าครบทุกห้องแล้ว</h5>
        <p style="font-size:.88rem;">ไม่มีห้องที่ต้องกรอกเพิ่มเติมในรอบนี้</p>
    </div>
</div></div>

<?php else: ?>
<div class="panel">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-list-ul"></i> ห้องที่ยังไม่ได้กรอกเลขมิเตอร์</span>
        <span style="font-size:.8rem;color:#94a3b8;">กรอกเลขปัจจุบันแล้วกด "บันทึก"</span>
    </div>
    <div class="table-responsive">
        <table class="enter-table">
            <thead>
                <tr>
                    <th>ห้อง</th>
                    <th>เลขครั้งก่อน</th>
                    <th>เลขปัจจุบัน & บันทึก</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($noElecRooms as $nr): ?>
                <tr id="elecrow-<?= $nr['room_id'] ?>">
                    <td><span class="room-pill"><?= htmlspecialchars($nr['room_number']) ?></span></td>
                    <td>
                        <?php if ($nr['elec_prev'] !== null): ?>
                            <span class="prev-chip"><?= number_format((float)$nr['elec_prev'], 2) ?></span>
                        <?php else: ?>
                            <span style="color:#94a3b8;font-size:.8rem;">ไม่มีข้อมูล</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form onsubmit="saveElec(this, event)" style="display:flex;gap:8px;align-items:center;">
                            <input type="hidden" name="room_id"  value="<?= $nr['room_id'] ?>">
                            <input type="hidden" name="cycle_id" value="<?= htmlspecialchars($cycle_id) ?>">
                            <input type="hidden" name="ajax"     value="1">
                            <input type="number" name="elec_curr" class="curr-input"
                                   placeholder="0.00" min="0" step="0.01" required>
                            <button type="submit" class="btn-save">
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
$extra_scripts = <<<'JS'
<script>
async function saveElec(form, event) {
    event.preventDefault();
    const btn  = form.querySelector('button[type=submit]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin .6s linear infinite;"></span>';

    try {
        const res  = await fetch('meter_elec.php', { method: 'POST', body: new FormData(form) });
        const data = await res.json();
        if (data.ok) {
            const row = form.closest('tr');
            const nextInput = row.nextElementSibling?.querySelector('input[name="elec_curr"]');
            row.style.transition = 'opacity 0.5s';
            row.style.background = '#fffbeb';
            row.cells[2].innerHTML = '<span style="color:#d97706;font-weight:600;font-size:.88rem;"><i class="bi bi-check-circle-fill me-1"></i>บันทึกแล้ว</span>';
            setTimeout(() => { row.style.opacity = '0'; }, 700);
            setTimeout(() => { row.remove(); }, 1200);
            if (nextInput) setTimeout(() => { nextInput.focus(); nextInput.select(); }, 100);
        } else {
            btn.disabled = false;
            btn.innerHTML = orig;
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: data.msg || 'กรุณาลองใหม่', confirmButtonColor: '#d97706' });
        }
    } catch(e) {
        btn.disabled = false;
        btn.innerHTML = orig;
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'กรุณาลองใหม่', confirmButtonColor: '#d97706' });
    }
}
</script>
<style>@keyframes spin { to { transform: rotate(360deg); } }</style>
JS;

include 'includes/footer.php';
?>
