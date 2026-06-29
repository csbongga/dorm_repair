<?php
require_once 'includes/auth_check.php';
require_once '../connect.php';

$page_title = 'ตรวจสอบค่าน้ำ';
$current_page  = 'meter_verify';

// ====================================================================
// POST: verify / reject
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action']   ?? '';
    $meter_id = (int)($_POST['meter_id'] ?? 0);

    try {
        $rateRow = $pdo->query("SELECT value FROM bill_settings WHERE setting_key = 'rate_water'")->fetch();
        $water_rate = $rateRow ? (float)$rateRow['value'] : 0;

        if ($action === 'verify' && $meter_id > 0) {
            $corrected = trim($_POST['water_curr'] ?? '');
            if ($corrected !== '' && is_numeric($corrected)) {
                $pdo->prepare("
                    UPDATE bill_meters
                    SET water_curr = ?, 
                        water_rate = ?,
                        water_amt = IF(water_prev IS NOT NULL, (? - water_prev) * ?, NULL),
                        water_status = 'verified', 
                        water_verified_at = NOW()
                    WHERE id = ? AND water_status = 'review'
                ")->execute([$corrected, $water_rate, $corrected, $water_rate, $meter_id]);
            } else {
                $pdo->prepare("
                    UPDATE bill_meters
                    SET water_rate = ?,
                        water_amt = IF(water_curr IS NOT NULL AND water_prev IS NOT NULL, (water_curr - water_prev) * ?, NULL),
                        water_status = 'verified', 
                        water_verified_at = NOW()
                    WHERE id = ? AND water_status = 'review'
                ")->execute([$water_rate, $water_rate, $meter_id]);
            }

        } elseif ($action === 'reject' && $meter_id > 0) {
            $reason = trim($_POST['reject_reason'] ?? '');
            $pdo->prepare("
                UPDATE bill_meters
                SET water_status = 'reject', water_reject_reason = ?
                WHERE id = ? AND water_status = 'review'
            ")->execute([$reason ?: null, $meter_id]);

        } elseif ($action === 'admin_enter') {
            $room_id_p  = (int)($_POST['room_id']   ?? 0);
            $water_curr = trim($_POST['water_curr'] ?? '');
            $cycle_id_p = trim($_POST['cycle_id']   ?? '');

            if ($room_id_p > 0 && is_numeric($water_curr) && $cycle_id_p !== '') {
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
                    $prevStmt->execute(['rid' => $room_id_p, 'cid' => $cycle_id_p, 'rid2' => $room_id_p]);
                    $water_prev = $prevStmt->fetchColumn() ?: null;
                } catch (PDOException $e2) {
                    $prevStmt = $pdo->prepare("
                        SELECT water_curr FROM bill_meters
                        WHERE room_id = ? AND water_status = 'verified' AND cycle_id != ?
                        ORDER BY cycle_id DESC LIMIT 1
                    ");
                    $prevStmt->execute([$room_id_p, $cycle_id_p]);
                    $water_prev = $prevStmt->fetchColumn() ?: null;
                }

                $ex = $pdo->prepare("SELECT id FROM bill_meters WHERE cycle_id = ? AND room_id = ? LIMIT 1");
                $ex->execute([$cycle_id_p, $room_id_p]);
                $existing = $ex->fetchColumn();

                $water_amt = null;
                if ($water_prev !== null && is_numeric($water_curr)) {
                    $water_amt = ($water_curr - $water_prev) * $water_rate;
                }

                if ($existing) {
                    $pdo->prepare("
                        UPDATE bill_meters
                        SET water_prev = ?, water_curr = ?,
                            water_rate = ?, water_amt = ?,
                            water_status = 'verified', water_verified_at = NOW(),
                            water_photo = NULL, water_submitted_at = NOW()
                        WHERE id = ?
                    ")->execute([$water_prev, $water_curr, $water_rate, $water_amt, $existing]);
                } else {
                    $pdo->prepare("
                        INSERT INTO bill_meters
                            (cycle_id, room_id, water_prev, water_curr, water_rate, water_amt, water_status, water_submitted_at, water_verified_at)
                        VALUES (?, ?, ?, ?, ?, ?, 'verified', NOW(), NOW())
                    ")->execute([$cycle_id_p, $room_id_p, $water_prev, $water_curr, $water_rate, $water_amt]);
                }

                if (!empty($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => true]);
                    exit;
                }
            }
        }
    } catch (PDOException $e) {
        error_log('meter_verify: ' . $e->getMessage());
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'msg' => 'เกิดข้อผิดพลาดในฐานข้อมูล']);
            exit;
        }
    }

    $keep_dorm  = (int)($_POST['keep_dorm']  ?? 0);
    $keep_floor = (int)($_POST['keep_floor'] ?? 0);
    $qs = http_build_query(array_filter(['dorm_id' => $keep_dorm, 'floor' => $keep_floor]));
    header('Location: meter_verify.php' . ($qs ? '?' . $qs : ''));
    exit;
}

// ====================================================================
// ดึงข้อมูล
// ====================================================================
$cycleRow = $pdo->query("SELECT id, label FROM bill_cycles WHERE is_current = 1 LIMIT 1")->fetch();
$cycle_id    = $cycleRow['id']    ?? null;
$cycle_label = $cycleRow['label'] ?? '–';

$rateRow   = $pdo->query("SELECT value FROM bill_settings WHERE setting_key = 'rate_water' LIMIT 1")->fetch();
$rateWater = $rateRow ? (float)$rateRow['value'] : 0;

// Filter params (GET)
$filter_dorm  = (int)($_GET['dorm_id'] ?? 0);
$filter_floor = (int)($_GET['floor']   ?? 0);

$allDorms = $pdo->query("SELECT id, name FROM dorms ORDER BY name ASC")->fetchAll();

$pendingMeters = [];
$verifiedCount = 0;
$totalRooms    = 0;

if ($cycle_id) {
    // รายการที่รออนุมัติ (พร้อม filter)
    $stmt = $pdo->prepare("
        SELECT bm.id,
               COALESCE(bm.water_prev, r.water_meter_init) AS water_prev,
               bm.water_curr, bm.water_photo,
               bm.water_submitted_at, bm.water_fine,
               r.room_number, r.id AS room_id, r.dorm_id,
               d.name AS dorm_name,
               GROUP_CONCAT(CONCAT(s.name, IFNULL(CONCAT(' (', NULLIF(s.phone, ''), ')'), '')) SEPARATOR ', ') AS student_name
        FROM bill_meters bm
        JOIN rooms r ON r.id = bm.room_id
        JOIN dorms d ON d.id = r.dorm_id
        LEFT JOIN students s ON s.room_id = r.id
        WHERE bm.cycle_id = :cid AND bm.water_status = 'review'
          AND (:fdorm_c = 0 OR r.dorm_id = :fdorm_v)
          AND (:ffloor_c = 0 OR r.floor = :ffloor_v)
        GROUP BY bm.id
        ORDER BY r.dorm_id ASC, r.room_number ASC
    ");
    $stmt->execute([
        'cid'     => $cycle_id,
        'fdorm_c' => $filter_dorm,
        'fdorm_v' => $filter_dorm,
        'ffloor_c' => $filter_floor ?: '0',
        'ffloor_v' => $filter_floor ?: '0',
    ]);
    $pendingMeters = $stmt->fetchAll();

    $vc = $pdo->prepare("SELECT COUNT(*) FROM bill_meters WHERE cycle_id = ? AND water_status = 'verified'");
    $vc->execute([$cycle_id]);
    $verifiedCount = (int)$vc->fetchColumn();

    $tc = $pdo->prepare("SELECT COUNT(*) FROM bill_meters WHERE cycle_id = ?");
    $tc->execute([$cycle_id]);
    $totalRooms = (int)$tc->fetchColumn();

    // ห้องที่ยังไม่มีมิเตอร์รอบนี้ (พร้อม filter)
    $noMeterRooms = [];
    try {
        $nmStmt = $pdo->prepare("
            SELECT r.id AS room_id, r.room_number,
                   GROUP_CONCAT(CONCAT(s.name, IFNULL(CONCAT(' (', NULLIF(s.phone, ''), ')'), '')) SEPARATOR ', ') AS student_name,
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
                SELECT room_id FROM bill_meters WHERE cycle_id = :cid2 AND (water_status = 'review' OR water_status = 'verified')
            )
              AND (:fdorm_c = 0 OR r.dorm_id = :fdorm_v)
              AND (:ffloor_c = 0 OR r.floor = :ffloor_v)
            GROUP BY r.id
            ORDER BY r.room_number ASC
        ");
        $nmStmt->execute([
            'cid'     => $cycle_id,
            'cid2'   => $cycle_id,
            'fdorm_c' => $filter_dorm,
            'fdorm_v' => $filter_dorm,
            'ffloor_c' => $filter_floor ?: '0',
            'ffloor_v' => $filter_floor ?: '0',
        ]);
        $noMeterRooms = $nmStmt->fetchAll();
    } catch (PDOException $e) {
        $nmStmt = $pdo->prepare("
            SELECT r.id AS room_id, r.room_number,
                   GROUP_CONCAT(CONCAT(s.name, IFNULL(CONCAT(' (', NULLIF(s.phone, ''), ')'), '')) SEPARATOR ', ') AS student_name,
                   (SELECT bm2.water_curr FROM bill_meters bm2
                    WHERE bm2.room_id = r.id AND bm2.water_status = 'verified'
                      AND bm2.cycle_id != ?
                    ORDER BY bm2.cycle_id DESC LIMIT 1) AS water_prev
            FROM rooms r
            LEFT JOIN students s ON s.room_id = r.id
            WHERE r.id NOT IN (SELECT room_id FROM bill_meters WHERE cycle_id = ? AND (water_status = 'review' OR water_status = 'verified'))
            GROUP BY r.id
            ORDER BY r.room_number ASC
        ");
        $nmStmt->execute([$cycle_id, $cycle_id]);
        $noMeterRooms = $nmStmt->fetchAll();
    }

}

// helper: แปลงวันที่เป็น พ.ศ. ย่อ
function shortDate(?string $dt): string {
    if (!$dt) return '–';
    $ts = strtotime($dt);
    $thMonth = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
                'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $d = (int)date('j', $ts);
    $m = (int)date('n', $ts);
    $y = (int)date('Y', $ts) + 543 - 2500;
    $t = date('H:i', $ts);
    return "{$d} {$thMonth[$m]} {$y} {$t}";
}

// helper: render digital meter display
function meterDigits(string $num): string {
    $clean = preg_replace('/[^0-9]/', '', explode('.', $num)[0]);
    $clean = str_pad($clean, 5, '0', STR_PAD_LEFT);
    $len   = strlen($clean);
    $out   = '';
    for ($i = 0; $i < $len; $i++) {
        $isLast = ($i === $len - 1);
        $cls    = $isLast ? 'md-digit last' : 'md-digit';
        $out   .= "<span class=\"{$cls}\">{$clean[$i]}</span>";
    }
    return $out;
}

$extra_head = <<<'CSS'
<style>
/* ── Meter verify page ── */
.cycle-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: #f0fdf4; color: #16a34a;
    border: 1px solid #bbf7d0;
    border-radius: 20px; padding: 5px 14px;
    font-size: 0.85rem; font-weight: 600;
}

.info-banner {
    background: #eff6ff; border: 1px solid #bfdbfe;
    border-radius: 12px; padding: 12px 16px;
    font-size: 0.85rem; color: #1d4ed8;
    display: flex; align-items: flex-start; gap: 10px;
    margin-bottom: 24px;
}
.info-banner i { flex-shrink: 0; margin-top: 2px; }

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
.stat-chip .sc-num  { font-size: 1.25rem; font-weight: 700; color: #1e293b; }
.stat-chip .sc-lbl  { font-size: 0.75rem; color: #94a3b8; }

/* ── Meter card ── */
.meter-card {
    background: white; border-radius: 18px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
    overflow: hidden;
    transition: box-shadow .2s;
}
.meter-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }

.mc-head {
    padding: 14px 18px;
    display: flex; align-items: center; gap: 12px;
    border-bottom: 1px solid #f1f5f9;
}
.room-tag {
    background: #ccfbf1; color: #0d9488;
    border-radius: 8px; padding: 4px 10px;
    font-size: 0.82rem; font-weight: 700;
    flex-shrink: 0;
}
.mc-name  { font-weight: 600; font-size: 0.95rem; color: #1e293b; }
.mc-time  { font-size: 0.75rem; color: #94a3b8; margin-top: 1px; }

.mc-body  { padding: 16px 18px; display: flex; gap: 16px; align-items: flex-start; }

/* photo box */
.photo-box {
    width: 160px; flex-shrink: 0;
    border-radius: 12px; overflow: hidden;
    background: #f8fafc; position: relative;
}
.photo-box img {
    width: 100%; aspect-ratio: 1/1;
    object-fit: cover; display: block;
}
.photo-box .photo-label {
    text-align: center; padding: 6px 0;
    font-size: 0.72rem; color: #94a3b8;
    background: white;
}

/* digital meter overlay */
.meter-overlay {
    position: absolute; bottom: 28px; left: 50%;
    transform: translateX(-50%);
    background: #111; border-radius: 6px;
    padding: 4px 6px;
    display: flex; align-items: center; gap: 2px;
    box-shadow: 0 2px 8px rgba(0,0,0,.5);
}
.md-digit {
    display: inline-flex; align-items: center; justify-content: center;
    width: 22px; height: 28px;
    background: #1a1a1a; color: #f5f5f5;
    border-radius: 3px; font-size: 0.95rem;
    font-weight: 700; font-family: 'Courier New', monospace;
    border: 1px solid #333;
}
.md-digit.last {
    background: #dc2626; color: white;
    border-color: #dc2626;
}
.meter-unit {
    font-size: 0.65rem; color: #9ca3af;
    margin-left: 3px; align-self: flex-end; padding-bottom: 3px;
}

/* no-image placeholder */
.no-photo {
    width: 100%; aspect-ratio: 1/1;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    background: #f1f5f9; color: #94a3b8; gap: 6px;
    font-size: 0.78rem;
}
.no-photo i { font-size: 1.8rem; }

/* reading table */
.reading-table { flex: 1; min-width: 0; }
.reading-table table { width: 100%; border-collapse: collapse; }
.reading-table td {
    padding: 7px 0; border-bottom: 1px solid #f1f5f9;
    font-size: 0.88rem; vertical-align: middle;
}
.reading-table td:first-child { color: #64748b; width: 55%; }
.reading-table td:last-child   { text-align: right; font-weight: 600; color: #1e293b; }
.reading-table tr:last-child td { border-bottom: none; }
.reading-table .td-units { color: #0d9488 !important; font-size: 0.98rem; }
.reading-table .td-amount { color: #1e293b !important; }
.reading-table .td-na { color: #94a3b8 !important; font-weight: 400; font-size: 0.82rem; }

/* action buttons */
.mc-footer {
    padding: 14px 18px;
    border-top: 1px solid #f1f5f9;
    display: flex; gap: 10px;
}
.btn-reject {
    flex: 1; border: 1.5px solid #e2e8f0;
    background: white; color: #ef4444;
    border-radius: 10px; padding: 10px;
    font-size: 0.88rem; font-weight: 600;
    font-family: 'Kanit', sans-serif;
    cursor: pointer; transition: all .2s;
    display: flex; align-items: center; justify-content: center; gap: 6px;
}
.btn-reject:hover { background: #fff1f2; border-color: #fecaca; }
.btn-verify {
    flex: 2; border: none;
    background: linear-gradient(135deg, #0d9488, #0891b2);
    color: white; border-radius: 10px; padding: 10px;
    font-size: 0.88rem; font-weight: 600;
    font-family: 'Kanit', sans-serif;
    cursor: pointer; transition: opacity .2s;
    display: flex; align-items: center; justify-content: center; gap: 6px;
    box-shadow: 0 2px 8px rgba(13,148,136,.25);
}
.btn-verify:hover { opacity: .9; }

/* empty state */
.empty-state {
    text-align: center; padding: 60px 20px;
    color: #94a3b8;
}
.empty-state i { font-size: 3rem; margin-bottom: 12px; display: block; }
.empty-state h5 { font-weight: 600; color: #475569; margin-bottom: 6px; }

/* filter bar */
.filter-bar {
    background: white; border-radius: 14px;
    border: 1px solid #e2e8f0;
    padding: 14px 18px; margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.filter-bar label { font-size: 0.82rem; color: #64748b; font-weight: 600; white-space: nowrap; }
.filter-select {
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    padding: 7px 12px; font-size: 0.88rem;
    font-family: 'Kanit', sans-serif; color: #334155;
    background: #f8fafc; outline: none;
    transition: border-color .2s;
    cursor: pointer;
}
.filter-select:focus { border-color: #0d9488; background: white; }
.btn-filter {
    border: none; background: #0d9488; color: white;
    border-radius: 8px; padding: 8px 18px;
    font-size: 0.85rem; font-weight: 600;
    font-family: 'Kanit', sans-serif; cursor: pointer;
    display: inline-flex; align-items: center; gap: 5px;
    transition: opacity .2s;
}
.btn-filter:hover { opacity: .88; }
.btn-filter-clear {
    font-size: 0.82rem; color: #94a3b8;
    text-decoration: none; padding: 8px 10px;
    border-radius: 8px; transition: color .2s;
    display: inline-flex; align-items: center; gap: 4px;
}
.btn-filter-clear:hover { color: #ef4444; }
.filter-active-tag {
    background: #f0fdfa; color: #0d9488;
    border: 1px solid #99f6e4; border-radius: 20px;
    padding: 3px 10px; font-size: 0.78rem; font-weight: 600;
    display: inline-flex; align-items: center; gap: 5px;
}

/* section divider */
.section-divider {
    display: flex; align-items: center; gap: 10px;
    font-size: 0.95rem; font-weight: 600; color: #334155;
    margin: 28px 0 16px 0;
}
.section-divider::after {
    content: ''; flex: 1; height: 1px; background: #e2e8f0;
}

/* admin enter table */
.admin-enter-table { width: 100%; border-collapse: collapse; }
.admin-enter-table th {
    font-size: 0.78rem; font-weight: 600; color: #64748b;
    text-transform: uppercase; letter-spacing: .03em;
    background: #f8fafc; padding: 10px 16px;
    border-bottom: 1px solid #e2e8f0; text-align: left;
}
.admin-enter-table td {
    padding: 10px 16px; border-bottom: 1px solid #f1f5f9;
    vertical-align: middle; font-size: 0.88rem; color: #334155;
}
.admin-enter-table tr:last-child td { border-bottom: none; }
.admin-enter-table tr:hover td { background: #f8fafc; }

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
</style>
CSS;

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-droplet-fill me-2" style="color:#0d9488;"></i>ตรวจสอบค่าน้ำ</h2>
        <p class="page-desc">เทียบรูปถ่ายมิเตอร์กับตัวเลขที่ผู้เช่าส่ง</p>
    </div>
    <span class="cycle-badge">
        <i class="bi bi-calendar3"></i> <?= htmlspecialchars($cycle_label) ?>
    </span>
</div>

<!-- Filter bar -->
<form method="GET" action="meter_verify.php" class="filter-bar">
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

    <button type="submit" class="btn-filter">
        <i class="bi bi-funnel-fill"></i> กรอง
    </button>

    <?php if ($filter_dorm || $filter_floor): ?>
    <a href="meter_verify.php" class="btn-filter-clear">
        <i class="bi bi-x-circle"></i> ล้างตัวกรอง
    </a>
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

<!-- Info banner -->
<div class="info-banner">
    <i class="bi bi-info-circle-fill"></i>
    <span>เทียบรูปถ่ายหน้าปัดที่ผู้เช่าส่งมา กับตัวเลขที่พิมพ์ หากไม่ตรงหรือรูปไม่ชัด กด <strong>"ตีกลับ"</strong> เพื่อให้ส่งใหม่</span>
</div>

<!-- Stat strip -->
<div class="stat-strip">
    <div class="stat-chip">
        <div class="sc-icon" style="background:#fef3c7;color:#d97706;"><i class="bi bi-hourglass-split"></i></div>
        <div>
            <div class="sc-num"><?= count($pendingMeters) ?></div>
            <div class="sc-lbl">รออนุมัติ</div>
        </div>
    </div>
    <div class="stat-chip">
        <div class="sc-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-check-circle-fill"></i></div>
        <div>
            <div class="sc-num"><?= $verifiedCount ?></div>
            <div class="sc-lbl">ยืนยันแล้ว</div>
        </div>
    </div>
    <div class="stat-chip">
        <div class="sc-icon" style="background:#f0f9ff;color:#0ea5e9;"><i class="bi bi-houses-fill"></i></div>
        <div>
            <div class="sc-num"><?= $totalRooms ?></div>
            <div class="sc-lbl">ส่งมาทั้งหมด</div>
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

<?php elseif (empty($pendingMeters)): ?>
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <i class="bi bi-check2-all" style="color:#0d9488;"></i>
            <h5 style="color:#0d9488;">ไม่มีรายการรออนุมัติ</h5>
            <p style="font-size:.88rem;">ทุกห้องได้รับการตรวจสอบเรียบร้อยแล้ว หรือยังไม่มีการส่งค่ามิเตอร์</p>
        </div>
    </div>
</div>

<?php else: ?>

<div class="row g-3">
<?php foreach ($pendingMeters as $m):
    $curr   = $m['water_curr']  !== null ? (float)$m['water_curr']  : null;
    $prev   = $m['water_prev']  !== null ? (float)$m['water_prev']  : null;
    $units  = ($curr !== null && $prev !== null) ? $curr - $prev : null;
    $amount = ($units !== null && $rateWater > 0) ? $units * $rateWater : null;
    $fine   = $m['water_fine'] !== null ? (float)$m['water_fine'] : 0;
    $photoUrl = $m['water_photo'] ? '../' . htmlspecialchars($m['water_photo']) : null;
?>
<div class="col-12 col-xl-6">
    <div class="meter-card">

        <!-- head -->
        <div class="mc-head">
            <span class="room-tag"><?= htmlspecialchars($m['room_number']) ?></span>
            <div>
                <div class="mc-name"><?= htmlspecialchars($m['student_name'] ?? 'ไม่ระบุชื่อ') ?></div>
                <div class="mc-time">ส่งเมื่อ <?= shortDate($m['water_submitted_at']) ?></div>
            </div>
        </div>

        <!-- body -->
        <div class="mc-body">

            <!-- photo + meter overlay -->
            <div class="photo-box">
                <?php if ($photoUrl): ?>
                    <img src="<?= $photoUrl ?>" alt="รูปมิเตอร์"
                         style="cursor:zoom-in;"
                         onclick="openPhoto('<?= $photoUrl ?>')">
                <?php else: ?>
                    <div class="no-photo">
                        <i class="bi bi-image-fill"></i>
                        ไม่มีรูปภาพ
                    </div>
                <?php endif; ?>
                <div class="photo-label">รูปจากผู้เช่า</div>
            </div>

            <!-- reading detail -->
            <div class="reading-table">
                <table>
                    <tr>
                        <td>เลขที่พิมพ์</td>
                        <td><?= $curr !== null ? number_format($curr, 0) : '<span class="td-na">–</span>' ?></td>
                    </tr>
                    <tr>
                        <td>ครั้งก่อน</td>
                        <td><?= $prev !== null ? number_format($prev, 0) : '<span class="td-na">ไม่มีข้อมูล</span>' ?></td>
                    </tr>
                    <tr>
                        <td>ใช้ไป</td>
                        <td class="td-units">
                            <?php if ($units !== null): ?>
                                <?= number_format($units, 0) ?> <span style="font-size:.8rem;font-weight:400;">หน่วย</span>
                            <?php else: ?>
                                <span class="td-na">คำนวณไม่ได้</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>เป็นเงิน</td>
                        <td class="td-amount">
                            <?php if ($amount !== null): ?>
                                ฿<?= number_format($amount, 0) ?>
                            <?php else: ?>
                                <span class="td-na">–</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($fine > 0): ?>
                    <tr>
                        <td style="color:#ef4444;">ค่าปรับล่าช้า</td>
                        <td class="td-amount" style="color:#ef4444;">
                            ฿<?= number_format($fine, 0) ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- footer buttons -->
        <div class="mc-footer">
            <button class="btn-reject"
                    onclick="openReject(<?= $m['id'] ?>, '<?= htmlspecialchars($m['room_number']) ?>')">
                <i class="bi bi-x-circle"></i> ตีกลับ
            </button>
            <button class="btn-verify"
                    onclick="confirmVerify(<?= $m['id'] ?>, '<?= htmlspecialchars($m['room_number']) ?>', <?= $curr ?? 0 ?>, '<?= $photoUrl ? addslashes($photoUrl) : '' ?>')">
                <i class="bi bi-check-circle"></i> ยืนยันค่าน้ำ
            </button>
        </div>

    </div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

<!-- ====================================================== -->
<!-- ผู้ดูแลกรอกมิเตอร์แทน -->
<!-- ====================================================== -->
<?php if ($cycle_id && !empty($noMeterRooms)): ?>

<div class="section-divider">
    <i class="bi bi-pencil-square" style="color:#0d9488;"></i>
    กรอกมิเตอร์โดยผู้ดูแล (<?= count($noMeterRooms) ?> ห้องที่ยังไม่มีข้อมูล)
</div>

<div class="panel">
    <div class="panel-header">
        <span class="panel-title">
            <i class="bi bi-pencil-square"></i> ห้องที่ยังไม่ได้ส่งเลขมิเตอร์
        </span>
        <span style="font-size:.8rem;color:#94a3b8;">กรอกเลขแล้วกด "บันทึก" — ระบบยืนยันให้อัตโนมัติ ไม่ต้องแนบรูป</span>
    </div>
    <div class="table-responsive">
        <table class="admin-enter-table">
            <thead>
                <tr>
                    <th>ห้อง</th>
                    <th>เลขครั้งก่อน</th>
                    <th colspan="2">เลขปัจจุบัน</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($noMeterRooms as $nr): ?>
                <tr id="nmrow-<?= $nr['room_id'] ?>">
                    <td><span class="room-pill"><?= htmlspecialchars($nr['room_number']) ?></span></td>
                    <td>
                        <?php if ($nr['water_prev'] !== null): ?>
                            <span class="prev-chip"><?= number_format((float)$nr['water_prev'], 0) ?></span>
                        <?php else: ?>
                            <span style="color:#94a3b8;font-size:.8rem;">ไม่มีข้อมูล</span>
                        <?php endif; ?>
                    </td>
                    <td colspan="2">
                        <form onsubmit="saveAdminMeter(this, event)" style="display:flex;gap:8px;align-items:center;">
                            <input type="hidden" name="action"   value="admin_enter">
                            <input type="hidden" name="ajax"     value="1">
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

<!-- Hidden forms -->
<form id="verifyForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="verify">
    <input type="hidden" name="meter_id"   id="verifyMeterId">
    <input type="hidden" name="water_curr" id="verifyCurr">
    <input type="hidden" name="keep_dorm"  value="<?= $filter_dorm ?>">
    <input type="hidden" name="keep_floor" value="<?= $filter_floor ?>">
</form>

<form id="rejectForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="reject">
    <input type="hidden" name="meter_id" id="rejectMeterId">
    <input type="hidden" name="reject_reason" id="rejectReason">
    <input type="hidden" name="keep_dorm"  value="<?= $filter_dorm ?>">
    <input type="hidden" name="keep_floor" value="<?= $filter_floor ?>">
</form>

<!-- Photo modal -->
<div id="photoModal" onclick="closePhoto()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;
            align-items:center;justify-content:center;cursor:zoom-out;">
    <img id="photoModalImg" src="" alt=""
         style="max-width:90vw;max-height:90vh;border-radius:12px;box-shadow:0 8px 40px rgba(0,0,0,.5);">
</div>

<?php
$extra_scripts = <<<'JS'
<script>
function confirmVerify(id, room, currVal, photoUrl) {
    const photoHtml = photoUrl
        ? `<div style="margin-bottom:14px;">
               <img src="${photoUrl}" alt="รูปมิเตอร์"
                    style="width:100%;max-height:220px;object-fit:contain;
                           border-radius:10px;border:1px solid #e2e8f0;cursor:zoom-in;"
                    onclick="openPhoto('${photoUrl}')">
               <div style="font-size:0.72rem;color:#94a3b8;margin-top:4px;">รูปจากผู้เช่า — คลิกขยาย</div>
           </div>`
        : `<div style="margin-bottom:14px;padding:20px;background:#f1f5f9;border-radius:10px;
                       color:#94a3b8;font-size:.85rem;text-align:center;">
               <i class="bi bi-image" style="font-size:1.5rem;display:block;margin-bottom:4px;"></i>
               ไม่มีรูปภาพ
           </div>`;

    Swal.fire({
        title: 'ยืนยันค่าน้ำ',
        html: `<div style="text-align:left;">
                   <div style="font-size:.82rem;color:#64748b;margin-bottom:10px;">ห้อง <strong style="color:#1e293b;">${room}</strong></div>
                   ${photoHtml}
                   <label style="font-size:.82rem;color:#64748b;font-family:Kanit,sans-serif;">
                       เลขมิเตอร์ (แก้ไขได้หากผู้เช่าพิมผิด)
                   </label>
                   <input id="swalCurrInput" type="number" value="${currVal}"
                          step="1" inputmode="numeric" min="0"
                          class="swal2-input" style="font-family:Kanit,sans-serif;margin-top:6px;">
               </div>`,
        width: 420,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-check-circle me-1"></i>ยืนยัน',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#0d9488',
        cancelButtonColor: '#94a3b8',
        preConfirm: () => {
            const val = document.getElementById('swalCurrInput').value.trim();
            if (!val || isNaN(val)) {
                Swal.showValidationMessage('กรุณากรอกเลขมิเตอร์ให้ถูกต้อง');
                return false;
            }
            return val;
        },
        didOpen: () => {
            const inp = document.getElementById('swalCurrInput');
            inp.focus(); inp.select();
        }
    }).then(r => {
        if (r.isConfirmed) {
            document.getElementById('verifyMeterId').value = id;
            document.getElementById('verifyCurr').value    = r.value;
            document.getElementById('verifyForm').submit();
        }
    });
}

function openReject(id, room) {
    Swal.fire({
        title: 'ตีกลับค่ามิเตอร์',
        html: `ห้อง <strong>${room}</strong><br>
               <div style="margin-top:10px;">
                   <textarea id="rejectReasonInput" class="swal2-textarea"
                             placeholder="ระบุเหตุผล เช่น รูปไม่ชัด, ตัวเลขไม่ตรงกับรูป..."
                             style="margin:0;height:100px;font-family:Kanit,sans-serif;"></textarea>
               </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-arrow-counterclockwise me-1"></i>ตีกลับ',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        preConfirm: () => {
            return document.getElementById('rejectReasonInput').value.trim();
        }
    }).then(r => {
        if (r.isConfirmed) {
            document.getElementById('rejectMeterId').value = id;
            document.getElementById('rejectReason').value  = r.value || '';
            document.getElementById('rejectForm').submit();
        }
    });
}

function openPhoto(url) {
    const modal = document.getElementById('photoModal');
    document.getElementById('photoModalImg').src = url;
    modal.style.display = 'flex';
}
function closePhoto() {
    document.getElementById('photoModal').style.display = 'none';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closePhoto(); });

async function saveAdminMeter(form, event) {
    event.preventDefault();
    const btn  = form.querySelector('button[type=submit]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin .6s linear infinite;"></span>';

    try {
        const res  = await fetch('meter_verify.php', { method: 'POST', body: new FormData(form) });
        const data = await res.json();
        if (data.ok) {
            const row = form.closest('tr');
            const nextInput = row.nextElementSibling?.querySelector('input[name="water_curr"]');
            row.style.transition = 'opacity 0.5s';
            row.style.background = '#f0fdf4';
            row.cells[2].innerHTML = '<span style="color:#0d9488;font-weight:600;font-size:.88rem;"><i class="bi bi-check-circle-fill me-1"></i>บันทึกแล้ว</span>';
            setTimeout(() => { row.style.opacity = '0'; }, 700);
            setTimeout(() => { row.remove(); }, 1200);
            if (nextInput) setTimeout(() => { nextInput.focus(); nextInput.select(); }, 100);
        } else {
            btn.disabled = false;
            btn.innerHTML = orig;
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: data.msg || 'กรุณาลองใหม่', confirmButtonColor: '#0d9488' });
        }
    } catch(e) {
        btn.disabled = false;
        btn.innerHTML = orig;
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'กรุณาลองใหม่', confirmButtonColor: '#0d9488' });
    }
}
</script>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
JS;

include 'includes/footer.php';
?>
