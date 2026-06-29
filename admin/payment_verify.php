<?php
require_once 'includes/auth_check.php';
require_once '../connect.php';

$page_title   = 'ยืนยันการชำระเงิน';
$current_page = 'payment_verify';

// ====================================================================
// POST: confirm / reject
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action']   ?? '';
    $meter_id = (int)($_POST['meter_id'] ?? 0);

    try {
        if ($action === 'confirm' && $meter_id > 0) {
            try {
                $pdo->prepare("
                    UPDATE bill_meters
                    SET payment_status = 'confirmed', payment_confirmed_at = NOW()
                    WHERE id = ? AND payment_status = 'pending'
                ")->execute([$meter_id]);
            } catch (PDOException $e2) {
                // fallback: ถ้า payment_confirmed_at ยังไม่มี column
                $pdo->prepare("
                    UPDATE bill_meters
                    SET payment_status = 'confirmed'
                    WHERE id = ? AND payment_status = 'pending'
                ")->execute([$meter_id]);
            }

        } elseif ($action === 'reject' && $meter_id > 0) {
            // ลบสลิปเก่าออก แล้ว reset ให้ผู้เช่าส่งใหม่
            $slipStmt = $pdo->prepare("SELECT payment_slip FROM bill_meters WHERE id = ?");
            $slipStmt->execute([$meter_id]);
            $oldSlip = $slipStmt->fetchColumn();
            if ($oldSlip && file_exists(__DIR__ . '/../' . $oldSlip)) {
                @unlink(__DIR__ . '/../' . $oldSlip);
            }
            $pdo->prepare("
                UPDATE bill_meters
                SET payment_status = NULL, payment_slip = NULL, payment_submitted_at = NULL
                WHERE id = ? AND payment_status = 'pending'
            ")->execute([$meter_id]);
        }
    } catch (PDOException $e) {
        error_log('payment_verify: ' . $e->getMessage());
    }

    $keep_dorm  = (int)($_POST['keep_dorm']  ?? 0);
    $keep_floor = (int)($_POST['keep_floor'] ?? 0);
    $qs = http_build_query(array_filter(['dorm_id' => $keep_dorm, 'floor' => $keep_floor]));
    header('Location: payment_verify.php' . ($qs ? '?' . $qs : ''));
    exit;
}

// ====================================================================
// ดึงข้อมูล
// ====================================================================
$cycleRow = $pdo->query("SELECT id, label FROM bill_cycles WHERE is_current = 1 LIMIT 1")->fetch();
$cycle_id    = $cycleRow['id']    ?? null;
$cycle_label = $cycleRow['label'] ?? '–';

$ratesStmt = $pdo->query("SELECT setting_key, value FROM bill_settings WHERE setting_key IN ('rate_water','rate_elec')");
$rates     = $ratesStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$rateWater = (float)($rates['rate_water'] ?? 0);
$rateElec  = (float)($rates['rate_elec']  ?? 0);

$filter_dorm  = (int)($_GET['dorm_id'] ?? 0);
$filter_floor = (int)($_GET['floor']   ?? 0);

$allDorms = $pdo->query("SELECT id, name FROM dorms ORDER BY name ASC")->fetchAll();

$pendingPayments = [];
$confirmedCount  = 0;

// ดึงสลิปรอตรวจสอบจากทุกรอบ (ไม่จำกัดรอบปัจจุบัน)
$stmt = $pdo->prepare("
    SELECT bm.id,
           bm.water_curr, bm.water_prev, bm.water_fine, bm.water_amt,
           bm.elec_curr, bm.elec_prev, bm.elec_amt,
           bm.payment_slip, bm.payment_submitted_at,
           r.room_number, r.id AS room_id, r.dorm_id,
           d.name AS dorm_name,
           GROUP_CONCAT(CONCAT(s.name, IFNULL(CONCAT(' (', NULLIF(s.phone, ''), ')'), '')) SEPARATOR ', ') AS student_name,
           bc.label AS cycle_label
    FROM bill_meters bm
    JOIN rooms r ON r.id = bm.room_id
    JOIN dorms d ON d.id = r.dorm_id
    JOIN bill_cycles bc ON bc.id = bm.cycle_id
    LEFT JOIN students s ON s.room_id = r.id
    WHERE bm.payment_status = 'pending'
      AND (:fdorm_c = 0 OR r.dorm_id = :fdorm_v)
      AND (:ffloor_c = 0 OR r.floor  = :ffloor_v)
    GROUP BY bm.id
    ORDER BY bm.payment_submitted_at DESC, r.dorm_id ASC, r.room_number ASC
");
$stmt->execute([
    'fdorm_c'  => $filter_dorm,
    'fdorm_v'  => $filter_dorm,
    'ffloor_c' => $filter_floor ?: '0',
    'ffloor_v' => $filter_floor ?: '0',
]);
$pendingPayments = $stmt->fetchAll();

if ($cycle_id) {
    $cc = $pdo->prepare("SELECT COUNT(*) FROM bill_meters WHERE cycle_id = ? AND payment_status = 'confirmed'");
    $cc->execute([$cycle_id]);
    $confirmedCount = (int)$cc->fetchColumn();
}

function shortDate(?string $dt): string {
    if (!$dt) return '–';
    $ts = strtotime($dt);
    $thMonth = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.',
                'ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    return date('j', $ts) . ' ' . $thMonth[(int)date('n', $ts)]
        . ' ' . ((int)date('Y', $ts) + 543 - 2500)
        . ' ' . date('H:i', $ts);
}

$extra_head = <<<'CSS'
<style>
/* ── Payment verify page ── */
.cycle-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: #f0fdf4; color: #16a34a;
    border: 1px solid #bbf7d0;
    border-radius: 20px; padding: 5px 14px;
    font-size: 0.85rem; font-weight: 600;
}
.info-banner {
    background: #f0fdf4; border: 1px solid #bbf7d0;
    border-radius: 12px; padding: 12px 16px;
    font-size: 0.85rem; color: #15803d;
    display: flex; align-items: flex-start; gap: 10px;
    margin-bottom: 24px;
}
.info-banner i { flex-shrink: 0; margin-top: 2px; }

.stat-strip { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
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

/* ── Payment card ── */
.pay-card {
    background: white; border-radius: 18px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
    overflow: hidden; transition: box-shadow .2s;
}
.pay-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }

.pc-head {
    padding: 14px 18px;
    display: flex; align-items: center; gap: 12px;
    border-bottom: 1px solid #f1f5f9;
}
.room-tag {
    background: #dcfce7; color: #16a34a;
    border-radius: 8px; padding: 4px 10px;
    font-size: 0.82rem; font-weight: 700; flex-shrink: 0;
}
.pc-name { font-weight: 600; font-size: 0.95rem; color: #1e293b; }
.pc-time { font-size: 0.75rem; color: #94a3b8; margin-top: 1px; }
.pc-dorm { font-size: 0.75rem; color: #64748b; margin-left: auto; white-space: nowrap; }

.pc-body { padding: 16px 18px; display: flex; gap: 16px; align-items: flex-start; }

/* slip photo box */
.slip-box {
    width: 160px; flex-shrink: 0;
    border-radius: 12px; overflow: hidden;
    background: #f8fafc; position: relative;
}
.slip-box img {
    width: 100%; aspect-ratio: 3/4;
    object-fit: cover; display: block; cursor: zoom-in;
}
.slip-box .slip-label {
    text-align: center; padding: 6px 0;
    font-size: 0.72rem; color: #94a3b8; background: white;
}
.no-slip {
    width: 100%; aspect-ratio: 3/4;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    background: #f1f5f9; color: #94a3b8; gap: 6px;
    font-size: 0.78rem;
}
.no-slip i { font-size: 1.8rem; }

/* bill summary */
.bill-table { flex: 1; min-width: 0; }
.bill-table table { width: 100%; border-collapse: collapse; }
.bill-table td {
    padding: 8px 0; border-bottom: 1px solid #f1f5f9;
    font-size: 0.88rem; vertical-align: middle;
}
.bill-table td:first-child { color: #64748b; width: 50%; }
.bill-table td:last-child  { text-align: right; font-weight: 600; color: #1e293b; }
.bill-table tr:last-child td { border-bottom: none; }
.bill-table .td-units  { color: #64748b !important; font-size: 0.8rem; font-weight: 400; }
.bill-table .td-na     { color: #94a3b8 !important; font-weight: 400; font-size: 0.82rem; }
.bill-table .td-total  {
    font-size: 1.05rem !important; color: #16a34a !important;
    background: #f0fdf4; border-radius: 8px; padding: 10px !important;
}
.bill-table .td-total-label { color: #15803d !important; font-weight: 700; }

/* footer */
.pc-footer {
    padding: 14px 18px; border-top: 1px solid #f1f5f9;
    display: flex; gap: 10px;
}
.btn-reject {
    flex: 1; border: 1.5px solid #e2e8f0;
    background: white; color: #ef4444;
    border-radius: 10px; padding: 10px;
    font-size: 0.88rem; font-weight: 600;
    font-family: 'Kanit', sans-serif; cursor: pointer;
    transition: all .2s;
    display: flex; align-items: center; justify-content: center; gap: 6px;
}
.btn-reject:hover { background: #fff1f2; border-color: #fecaca; }
.btn-confirm {
    flex: 2; border: none;
    background: linear-gradient(135deg, #16a34a, #15803d);
    color: white; border-radius: 10px; padding: 10px;
    font-size: 0.88rem; font-weight: 600;
    font-family: 'Kanit', sans-serif; cursor: pointer;
    transition: opacity .2s;
    display: flex; align-items: center; justify-content: center; gap: 6px;
    box-shadow: 0 2px 8px rgba(22,163,74,.25);
}
.btn-confirm:hover { opacity: .9; }

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
    background: #f8fafc; outline: none; cursor: pointer;
    transition: border-color .2s;
}
.filter-select:focus { border-color: #16a34a; background: white; }
.btn-filter {
    border: none; background: #16a34a; color: white;
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
    background: #f0fdf4; color: #16a34a;
    border: 1px solid #bbf7d0; border-radius: 20px;
    padding: 3px 10px; font-size: 0.78rem; font-weight: 600;
    display: inline-flex; align-items: center; gap: 5px;
}

/* empty state */
.empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
.empty-state i { font-size: 3rem; margin-bottom: 12px; display: block; }
.empty-state h5 { font-weight: 600; color: #475569; margin-bottom: 6px; }
</style>
CSS;

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-cash-coin me-2" style="color:#16a34a;"></i>ยืนยันการชำระเงิน</h2>
        <p class="page-desc">ตรวจสอบสลิปโอนเงินจากผู้เช่าก่อนยืนยัน</p>
    </div>
    <span class="cycle-badge">
        <i class="bi bi-calendar3"></i> <?= htmlspecialchars($cycle_label) ?>
    </span>
</div>

<!-- Filter bar -->
<form method="GET" action="payment_verify.php" class="filter-bar">
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
    <a href="payment_verify.php" class="btn-filter-clear">
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
    <span>ตรวจสอบยอดในสลิปให้ตรงกับ <strong>ค่าน้ำ + ค่าไฟรวม</strong> หากไม่ตรงหรือสลิปไม่ชัดเจน กด <strong>"ตีกลับ"</strong> ให้ผู้เช่าส่งสลิปใหม่</span>
</div>

<!-- Stat strip -->
<div class="stat-strip">
    <div class="stat-chip">
        <div class="sc-icon" style="background:#fef3c7;color:#d97706;"><i class="bi bi-hourglass-split"></i></div>
        <div>
            <div class="sc-num"><?= count($pendingPayments) ?></div>
            <div class="sc-lbl">รอตรวจสอบ</div>
        </div>
    </div>
    <div class="stat-chip">
        <div class="sc-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-check-circle-fill"></i></div>
        <div>
            <div class="sc-num"><?= $confirmedCount ?></div>
            <div class="sc-lbl">ยืนยันแล้ว</div>
        </div>
    </div>
    <?php if ($rateWater > 0): ?>
    <div class="stat-chip">
        <div class="sc-icon" style="background:#f0fbff;color:#0ea5e9;"><i class="bi bi-droplet-fill"></i></div>
        <div>
            <div class="sc-num">฿<?= number_format($rateWater, 0) ?></div>
            <div class="sc-lbl">บาท/หน่วยน้ำ</div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($rateElec > 0): ?>
    <div class="stat-chip">
        <div class="sc-icon" style="background:#fffbeb;color:#f59e0b;"><i class="bi bi-lightning-charge-fill"></i></div>
        <div>
            <div class="sc-num">฿<?= number_format($rateElec, 0) ?></div>
            <div class="sc-lbl">บาท/หน่วยไฟ</div>
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

<?php elseif (empty($pendingPayments)): ?>
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <i class="bi bi-check2-all" style="color:#16a34a;"></i>
            <h5 style="color:#16a34a;">ไม่มีสลิปรอตรวจสอบ</h5>
            <p style="font-size:.88rem;">ยังไม่มีผู้เช่าส่งสลิปการชำระเงิน หรือตรวจสอบครบทุกห้องแล้ว</p>
        </div>
    </div>
</div>

<?php else: ?>

<div class="row g-3">
<?php foreach ($pendingPayments as $p):
    $wCurr  = $p['water_curr'] !== null ? (float)$p['water_curr'] : null;
    $wPrev  = $p['water_prev'] !== null ? (float)$p['water_prev'] : null;
    $wUnits = ($wCurr !== null && $wPrev !== null) ? $wCurr - $wPrev : null;
    $wAmt   = $p['water_amt'] !== null ? (float)$p['water_amt'] : (($wUnits !== null && $rateWater > 0) ? $wUnits * $rateWater : null);

    $eCurr  = $p['elec_curr'] !== null ? (float)$p['elec_curr'] : null;
    $ePrev  = $p['elec_prev'] !== null ? (float)$p['elec_prev'] : null;
    $eUnits = ($eCurr !== null && $ePrev !== null) ? $eCurr - $ePrev : null;
    $eAmt   = $p['elec_amt'] !== null ? (float)$p['elec_amt'] : (($eUnits !== null && $rateElec > 0) ? $eUnits * $rateElec : null);
    $fine   = $p['water_fine'] !== null ? (float)$p['water_fine'] : 0;

    $total    = ($wAmt ?? 0) + ($eAmt ?? 0) + $fine;
    $slipUrl  = $p['payment_slip'] ? '../' . htmlspecialchars($p['payment_slip']) : null;
?>
<div class="col-12 col-xl-6">
    <div class="pay-card">

        <!-- head -->
        <div class="pc-head">
            <span class="room-tag"><?= htmlspecialchars($p['room_number']) ?></span>
            <div>
                <div class="pc-name"><?= htmlspecialchars($p['student_name'] ?? 'ไม่ระบุชื่อ') ?></div>
                <div class="pc-time">
                    <?= htmlspecialchars($p['cycle_label'] ?? '') ?>
                    · ส่งสลิปเมื่อ <?= shortDate($p['payment_submitted_at']) ?>
                </div>
            </div>
            <span class="pc-dorm"><?= htmlspecialchars($p['dorm_name']) ?></span>
        </div>

        <!-- body -->
        <div class="pc-body">

            <!-- slip image -->
            <div class="slip-box">
                <?php if ($slipUrl): ?>
                    <img src="<?= $slipUrl ?>" alt="สลิปโอนเงิน"
                         onclick="openPhoto('<?= $slipUrl ?>')">
                <?php else: ?>
                    <div class="no-slip">
                        <i class="bi bi-image-fill"></i>
                        ไม่มีสลิป
                    </div>
                <?php endif; ?>
                <div class="slip-label">สลิปจากผู้เช่า</div>
            </div>

            <!-- bill summary -->
            <div class="bill-table">
                <table>
                    <tr>
                        <td><i class="bi bi-droplet-fill me-1" style="color:#0ea5e9;"></i>ค่าน้ำ</td>
                        <td>
                            <?php if ($wAmt !== null): ?>
                                ฿<?= number_format($wAmt, 0) ?>
                                <div class="td-units"><?= number_format($wUnits, 0) ?> หน่วย</div>
                            <?php else: ?>
                                <span class="td-na">–</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-lightning-charge-fill me-1" style="color:#f59e0b;"></i>ค่าไฟ</td>
                        <td>
                            <?php if ($eAmt !== null): ?>
                                ฿<?= number_format($eAmt, 0) ?>
                                <div class="td-units"><?= number_format($eUnits, 0) ?> หน่วย</div>
                            <?php else: ?>
                                <span class="td-na">–</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($fine > 0): ?>
                    <tr>
                        <td style="color:#ef4444;"><i class="bi bi-exclamation-circle-fill me-1"></i>ค่าปรับล่าช้า</td>
                        <td style="color:#ef4444; font-weight: 600;">฿<?= number_format($fine, 0) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="td-total-label" style="background:#f0fdf4;border-radius:8px 0 0 8px;padding:10px !important;">
                            <i class="bi bi-receipt-cutoff me-1"></i>รวมทั้งหมด
                        </td>
                        <td class="td-total" style="border-radius:0 8px 8px 0;">
                            ฿<?= number_format($total, 0) ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- footer -->
        <div class="pc-footer">
            <button class="btn-reject"
                    onclick="openReject(<?= $p['id'] ?>, '<?= htmlspecialchars($p['room_number']) ?>')">
                <i class="bi bi-x-circle"></i> ตีกลับ
            </button>
            <button class="btn-confirm"
                    onclick="confirmPayment(<?= $p['id'] ?>, '<?= htmlspecialchars($p['room_number']) ?>', <?= (int)round($total) ?>, '<?= $slipUrl ? $slipUrl : '' ?>')">
                <i class="bi bi-check-circle"></i> ยืนยันรับเงิน
            </button>
        </div>

    </div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

<!-- Hidden forms -->
<form id="confirmForm" method="POST" style="display:none;">
    <input type="hidden" name="action"    value="confirm">
    <input type="hidden" name="meter_id"  id="confirmMeterId">
    <input type="hidden" name="keep_dorm"  value="<?= $filter_dorm ?>">
    <input type="hidden" name="keep_floor" value="<?= $filter_floor ?>">
</form>
<form id="rejectForm" method="POST" style="display:none;">
    <input type="hidden" name="action"    value="reject">
    <input type="hidden" name="meter_id"  id="rejectMeterId">
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
function confirmPayment(id, room, total, slipUrl) {
    let imgHtml = '';
    if (slipUrl) {
        imgHtml = `<div style="text-align:center; margin-top: 15px;">
                       <img src="${slipUrl}" style="max-height: 250px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" alt="สลิปโอนเงิน">
                   </div>`;
    }

    Swal.fire({
        title: 'ยืนยันรับเงิน?',
        html: `ห้อง <strong>${room}</strong><br>ยอด <strong style="color:#16a34a;">฿${total.toLocaleString()}</strong> — ยืนยันว่าได้รับเงินแล้ว? ${imgHtml}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-check-circle me-1"></i>ยืนยันรับเงิน',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#16a34a',
        cancelButtonColor: '#94a3b8',
    }).then(r => {
        if (r.isConfirmed) {
            document.getElementById('confirmMeterId').value = id;
            document.getElementById('confirmForm').submit();
        }
    });
}

function openReject(id, room) {
    Swal.fire({
        title: 'ตีกลับสลิป',
        html: `ห้อง <strong>${room}</strong><br>
               <p style="font-size:.88rem;color:#64748b;margin:8px 0;">ผู้เช่าจะต้องอัปโหลดสลิปใหม่อีกครั้ง</p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-arrow-counterclockwise me-1"></i>ตีกลับ',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
    }).then(r => {
        if (r.isConfirmed) {
            document.getElementById('rejectMeterId').value = id;
            document.getElementById('rejectForm').submit();
        }
    });
}

function openPhoto(url) {
    document.getElementById('photoModalImg').src = url;
    document.getElementById('photoModal').style.display = 'flex';
}
function closePhoto() {
    document.getElementById('photoModal').style.display = 'none';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closePhoto(); });
</script>
JS;

include 'includes/footer.php';
?>
