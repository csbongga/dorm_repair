<?php
require_once 'includes/auth_check.php';
require_once '../connect.php';

$page_title   = 'ตั้งค่ามิเตอร์ไฟฟ้าเริ่มต้น';
$current_page = 'meter_init_elec';

// ====================================================================
// AJAX POST: บันทึก elec_meter_init
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax'])) {
    header('Content-Type: application/json');
    $room_id    = (int)($_POST['room_id']    ?? 0);
    $elec_init  = trim($_POST['elec_init']  ?? '');

    if ($room_id > 0 && is_numeric($elec_init) && (float)$elec_init >= 0) {
        try {
            $pdo->prepare("UPDATE rooms SET elec_meter_init = ? WHERE id = ?")
                ->execute([(float)$elec_init, $room_id]);
            echo json_encode(['ok' => true]);
        } catch (PDOException $e) {
            error_log('meter_init_elec: ' . $e->getMessage());
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
$filter_dorm  = (int)($_GET['dorm_id'] ?? 0);
$filter_floor = (int)($_GET['floor']   ?? 0);

$allDorms = $pdo->query("SELECT id, name FROM dorms ORDER BY name ASC")->fetchAll();

try {
    $stmt = $pdo->prepare("
        SELECT r.id AS room_id, r.room_number, r.elec_meter_init, r.floor,
               d.name AS dorm_name
        FROM rooms r
        JOIN dorms d ON d.id = r.dorm_id
        WHERE (:fdorm_c = 0 OR r.dorm_id = :fdorm_v)
          AND (:ffloor_c = 0 OR r.floor = :ffloor_v)
        ORDER BY r.dorm_id ASC, r.floor ASC, r.room_number ASC
    ");
    $stmt->execute([
        'fdorm_c'  => $filter_dorm,  'fdorm_v'  => $filter_dorm,
        'ffloor_c' => $filter_floor, 'ffloor_v' => $filter_floor,
    ]);
    $rooms = $stmt->fetchAll();
} catch (PDOException $e) {
    // elec_meter_init ยังไม่มีในตาราง — fallback ไม่แสดงคอลัมน์นั้น
    $stmt = $pdo->prepare("
        SELECT r.id AS room_id, r.room_number, NULL AS elec_meter_init, r.floor,
               d.name AS dorm_name
        FROM rooms r
        JOIN dorms d ON d.id = r.dorm_id
        WHERE (:fdorm_c = 0 OR r.dorm_id = :fdorm_v)
          AND (:ffloor_c = 0 OR r.floor = :ffloor_v)
        ORDER BY r.dorm_id ASC, r.floor ASC, r.room_number ASC
    ");
    $stmt->execute([
        'fdorm_c'  => $filter_dorm,  'fdorm_v'  => $filter_dorm,
        'ffloor_c' => $filter_floor, 'ffloor_v' => $filter_floor,
    ]);
    $rooms = $stmt->fetchAll();
}

$totalRooms = count($rooms);
$hasInit    = count(array_filter($rooms, fn($r) => $r['elec_meter_init'] !== null));
$noInit     = $totalRooms - $hasInit;

$extra_head = <<<'CSS'
<style>
.stat-strip { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.stat-chip {
    background: white; border-radius: 12px; padding: 12px 18px;
    border: 1px solid #e2e8f0; box-shadow: 0 1px 4px rgba(0,0,0,.04);
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

.init-table { width: 100%; border-collapse: collapse; }
.init-table th {
    font-size: 0.78rem; font-weight: 600; color: #64748b;
    text-transform: uppercase; letter-spacing: .03em;
    background: #f8fafc; padding: 10px 16px;
    border-bottom: 1px solid #e2e8f0; text-align: left;
}
.init-table td {
    padding: 10px 16px; border-bottom: 1px solid #f1f5f9;
    vertical-align: middle; font-size: 0.88rem; color: #334155;
}
.init-table tr:last-child td { border-bottom: none; }
.init-table tr:hover td { background: #fafafa; }

.room-pill {
    background: #fffbeb; color: #d97706;
    border-radius: 6px; padding: 2px 8px; font-size: 0.82rem; font-weight: 700;
}
.has-init {
    background: #f0fdf4; color: #059669;
    border-radius: 6px; padding: 2px 8px; font-size: 0.82rem; font-weight: 600;
}
.no-init { color: #94a3b8; font-size: 0.8rem; }
.curr-input {
    border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 7px 12px;
    font-size: 0.9rem; font-family: 'Kanit', sans-serif; color: #1e293b;
    width: 140px; background: #f8fafc; outline: none; transition: border-color .2s, box-shadow .2s;
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
</style>
CSS;

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-lightning-charge-fill me-2" style="color:#d97706;"></i>ตั้งค่ามิเตอร์ไฟฟ้าเริ่มต้น</h2>
        <p class="page-desc">กรอกเลขมิเตอร์ก่อนเริ่มใช้ระบบ — ใช้สำหรับคำนวณหน่วยไฟเดือนแรก</p>
    </div>
</div>

<!-- Stat strip -->
<div class="stat-strip">
    <div class="stat-chip">
        <div class="sc-icon" style="background:#fffbeb;color:#d97706;"><i class="bi bi-house-fill"></i></div>
        <div><div class="sc-num"><?= $totalRooms ?></div><div class="sc-lbl">ห้องทั้งหมด</div></div>
    </div>
    <div class="stat-chip">
        <div class="sc-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-check-circle-fill"></i></div>
        <div><div class="sc-num"><?= $hasInit ?></div><div class="sc-lbl">ตั้งค่าแล้ว</div></div>
    </div>
    <div class="stat-chip">
        <div class="sc-icon" style="background:#fef3c7;color:#d97706;"><i class="bi bi-exclamation-circle-fill"></i></div>
        <div><div class="sc-num"><?= $noInit ?></div><div class="sc-lbl">ยังไม่มีข้อมูล</div></div>
    </div>
</div>

<!-- Filter bar -->
<form method="GET" action="meter_init_elec.php" class="filter-bar">
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
    <a href="meter_init_elec.php" class="btn-filter-clear"><i class="bi bi-x-circle"></i> ล้างตัวกรอง</a>
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

<!-- Table -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-list-ul"></i> รายการห้องพัก</span>
        <span style="font-size:.8rem;color:#94a3b8;">กรอกเลขมิเตอร์เริ่มต้นแล้วกด "บันทึก"</span>
    </div>
    <div class="table-responsive">
        <table class="init-table">
            <thead>
                <tr>
                    <th>ห้อง</th>
                    <th>เลขมิเตอร์เริ่มต้น</th>
                    <th>ตั้งค่า / อัปเดต</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rooms as $r): ?>
                <tr id="initrow-<?= $r['room_id'] ?>">
                    <td><span class="room-pill"><?= htmlspecialchars($r['room_number']) ?></span></td>
                    <td id="initval-<?= $r['room_id'] ?>">
                        <?php if ($r['elec_meter_init'] !== null): ?>
                            <span class="has-init"><?= number_format((float)$r['elec_meter_init'], 2) ?></span>
                        <?php else: ?>
                            <span class="no-init">ยังไม่ได้ตั้งค่า</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form onsubmit="saveInit(this, event)" style="display:flex;gap:8px;align-items:center;">
                            <input type="hidden" name="room_id" value="<?= $r['room_id'] ?>">
                            <input type="hidden" name="ajax"    value="1">
                            <input type="number" name="elec_init" class="curr-input"
                                   value="<?= $r['elec_meter_init'] !== null ? (float)$r['elec_meter_init'] : '' ?>"
                                   placeholder="0" min="0" step="1" inputmode="numeric" required>
                            <button type="submit" class="btn-save">
                                <i class="bi bi-check2"></i> บันทึก
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rooms)): ?>
                <tr><td colspan="3" style="text-align:center;padding:40px;color:#94a3b8;">ไม่พบข้อมูลห้องพัก</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$extra_scripts = <<<'JS'
<script>
async function saveInit(form, event) {
    event.preventDefault();
    const btn  = form.querySelector('button[type=submit]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin .6s linear infinite;"></span>';

    try {
        const res  = await fetch('meter_init_elec.php', { method: 'POST', body: new FormData(form) });
        const data = await res.json();
        if (data.ok) {
            const roomId  = form.querySelector('[name=room_id]').value;
            const newVal  = parseFloat(form.querySelector('[name=elec_init]').value);
            const valCell = document.getElementById('initval-' + roomId);
            if (valCell) valCell.innerHTML = `<span class="has-init">${newVal.toFixed(0)}</span>`;

            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> บันทึกแล้ว';
            btn.style.background = '#059669';
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = orig;
                btn.style.background = '';
                const nextInput = form.closest('tr').nextElementSibling?.querySelector('input[name="elec_init"]');
                if (nextInput) { nextInput.focus(); nextInput.select(); }
            }, 900);
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
