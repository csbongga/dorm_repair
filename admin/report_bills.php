<?php
require_once 'includes/auth_check.php';
require_once '../connect.php';

$page_title   = 'สรุปค่าน้ำค่าไฟ';
$current_page = 'report_bills';

// ดึงรอบบิลทั้งหมด
$cycles = $pdo->query("SELECT * FROM bill_cycles ORDER BY id DESC")->fetchAll();

// รอบบิลที่เลือก (ค่าเริ่มต้นคือรอบปัจจุบัน)
$cycle_id = $_GET['cycle_id'] ?? '';
if (empty($cycle_id)) {
    $currCycle = $pdo->query("SELECT id FROM bill_cycles WHERE is_current = 1 LIMIT 1")->fetch();
    if ($currCycle) {
        $cycle_id = $currCycle['id'];
    } elseif (!empty($cycles)) {
        $cycle_id = $cycles[0]['id'];
    }
}

// 1. ดึงสรุปแต่ละหอพัก
$summaryStmt = $pdo->prepare("
    SELECT d.id AS dorm_id, d.name AS dorm_name,
           COUNT(r.id) AS total_rooms,
           SUM(CASE WHEN bm.id IS NOT NULL THEN 1 ELSE 0 END) AS billed_rooms,
           SUM(CASE WHEN bm.payment_status = 'confirmed' THEN 1 ELSE 0 END) AS paid_rooms,
           SUM(bm.water_amt) AS total_water,
           SUM(bm.elec_amt) AS total_elec,
           SUM(bm.water_fine) AS total_fine
    FROM dorms d
    JOIN rooms r ON d.id = r.dorm_id
    LEFT JOIN bill_meters bm ON r.id = bm.room_id AND bm.cycle_id = :cid
    GROUP BY d.id
    ORDER BY d.name ASC
");
$summaryStmt->execute(['cid' => $cycle_id]);
$summaries = $summaryStmt->fetchAll();

// 2. ดึงรายละเอียดห้องที่ค้างชำระ (หรือยังไม่ออกบิล) ในแต่ละหอพัก
$unpaidStmt = $pdo->prepare("
    SELECT d.id AS dorm_id, r.room_number,
           (SELECT GROUP_CONCAT(name SEPARATOR ', ') FROM students WHERE room_id = r.id) AS student_name,
           (SELECT GROUP_CONCAT(phone SEPARATOR ', ') FROM students WHERE room_id = r.id) AS student_phone,
           bm.water_amt, bm.elec_amt, bm.water_fine, bm.payment_status,
           bm.water_status, bm.elec_entered
    FROM dorms d
    JOIN rooms r ON d.id = r.dorm_id
    JOIN bill_meters bm ON r.id = bm.room_id AND bm.cycle_id = :cid
    WHERE (bm.payment_status IS NULL OR bm.payment_status != 'confirmed')
    ORDER BY d.id ASC, r.floor ASC, r.room_number ASC
");
$unpaidStmt->execute(['cid' => $cycle_id]);
$unpaidRows = $unpaidStmt->fetchAll();

// จัดกลุ่ม unpaid ตาม dorm_id
$unpaidByDorm = [];
foreach ($unpaidRows as $u) {
    $did = $u['dorm_id'];
    if (!isset($unpaidByDorm[$did])) $unpaidByDorm[$did] = [];
    $unpaidByDorm[$did][] = $u;
}

$extra_head = <<<'CSS'
<style>
.summary-card {
    background: #fff; border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    border: 1px solid #e2e8f0;
    margin-bottom: 24px;
    overflow: hidden;
}
.summary-header {
    background: #f8fafc; padding: 16px 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex; justify-content: space-between; align-items: center;
}
.summary-title { font-size: 1.1rem; font-weight: 600; color: #1e293b; }
.progress-bar-bg {
    width: 150px; height: 8px; background: #e2e8f0;
    border-radius: 4px; overflow: hidden; display: inline-block;
    vertical-align: middle; margin-right: 8px;
}
.progress-bar-fill {
    height: 100%; background: #10b981;
}
.table-summary th {
    background: #f8fafc; color: #64748b; font-weight: 600; font-size: 0.85rem;
    padding: 12px 16px; border-bottom: 2px solid #e2e8f0;
}
.table-summary td {
    padding: 14px 16px; vertical-align: middle; border-bottom: 1px solid #e2e8f0;
}
.btn-unpaid {
    background: #fff0f2; color: #e11d48; border: 1px solid #fda4af;
    padding: 6px 12px; border-radius: 6px; font-size: 0.85rem;
    cursor: pointer; transition: all 0.2s;
}
.btn-unpaid:hover { background: #ffe4e6; border-color: #fb7185; }
.btn-unpaid.all-paid {
    background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; cursor: default;
}

/* Modal */
.modal-content { border-radius: 12px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
.modal-header { border-bottom: 1px solid #f1f5f9; padding: 20px 24px; }
.modal-body { padding: 0; }
.unpaid-list { margin: 0; padding: 0; list-style: none; }
.unpaid-item {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px 24px; border-bottom: 1px solid #f1f5f9;
}
.unpaid-item:last-child { border-bottom: none; }
.unpaid-room { font-size: 1.05rem; font-weight: 600; color: #334155; }
.unpaid-name { font-size: 0.85rem; color: #64748b; }
.unpaid-amt { font-size: 1rem; font-weight: 600; color: #e11d48; }
.unpaid-status { font-size: 0.8rem; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; color: #475569; }
</style>
CSS;

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1" style="font-weight: 700; color: #1e293b;">สรุปค่าน้ำค่าไฟ</h1>
        <p class="text-muted mb-0">รายงานยอดค่าน้ำค่าไฟแยกตามหอพักและตรวจสอบห้องที่ค้างชำระ</p>
    </div>
</div>

<div class="summary-card p-4 mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label" style="font-weight:600; color:#475569;">เลือกรอบบิล</label>
            <select name="cycle_id" class="form-select" onchange="this.form.submit()">
                <?php foreach ($cycles as $c): ?>
                    <option value="<?= htmlspecialchars($c['id']) ?>" <?= $cycle_id === $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-8">
            <div class="text-md-end text-muted" style="font-size:0.9rem;">
                ข้อมูล ณ วันที่ <?= date('d/m/Y H:i') ?>
            </div>
        </div>
    </form>
</div>

<?php if (empty($summaries)): ?>
    <div class="alert alert-info bg-white border-info text-info">
        <i class="bi bi-info-circle me-2"></i> ยังไม่มีข้อมูลหอพัก
    </div>
<?php else: ?>

    <div class="summary-card">
        <div class="table-responsive">
            <table class="table table-borderless table-summary mb-0">
                <thead>
                    <tr>
                        <th>หอพัก</th>
                        <th class="text-end">ค่าน้ำรวม</th>
                        <th class="text-end">ค่าไฟรวม</th>
                        <th class="text-end" style="color:#334155;">รวม (ปกติ)</th>
                        <th class="text-end">ค่าปรับ</th>
                        <th class="text-end" style="color:#16a34a;">รวม (สุทธิ)</th>
                        <th>สถานะการชำระเงิน</th>
                        <th class="text-center">ค้างชำระ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $gtWater = 0; $gtElec = 0; $gtFine = 0; $gtNormal = 0; $gtTotal = 0;
                    foreach ($summaries as $s): 
                        $wAmt = (float)$s['total_water'];
                        $eAmt = (float)$s['total_elec'];
                        $fAmt = (float)$s['total_fine'];
                        $totNormal = $wAmt + $eAmt;
                        $tot  = $totNormal + $fAmt;
                        $rooms = (int)$s['billed_rooms']; // อิงจากจำนวนห้องที่มีบิล
                        $paid  = (int)$s['paid_rooms'];
                        $pct   = $rooms > 0 ? ($paid / $rooms) * 100 : 0;
                        
                        $gtWater += $wAmt; $gtElec += $eAmt; $gtFine += $fAmt; 
                        $gtNormal += $totNormal; $gtTotal += $tot;
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600; color:#1e293b;"><?= htmlspecialchars($s['dorm_name']) ?></div>
                            <div style="font-size:0.8rem; color:#64748b;">ออกบิลแล้ว <?= $rooms ?> ห้อง</div>
                        </td>
                        <td class="text-end"><?= number_format($wAmt, 2) ?></td>
                        <td class="text-end"><?= number_format($eAmt, 2) ?></td>
                        <td class="text-end" style="font-weight:600; color:#334155;"><?= number_format($totNormal, 2) ?></td>
                        <td class="text-end text-danger"><?= $fAmt > 0 ? number_format($fAmt, 2) : '-' ?></td>
                        <td class="text-end" style="font-weight:700; color:#16a34a;"><?= number_format($tot, 2) ?></td>
                        <td>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: <?= $pct ?>%;"></div>
                            </div>
                            <span style="font-size:0.85rem; color:#475569;"><?= $paid ?>/<?= $rooms ?> ห้อง</span>
                        </td>
                        <td class="text-center">
                            <?php if ($paid >= $rooms && $rooms > 0): ?>
                                <button class="btn-unpaid all-paid" disabled><i class="bi bi-check-circle-fill"></i> ชำระครบถ้วน</button>
                            <?php elseif ($rooms === 0): ?>
                                <span class="text-muted" style="font-size:0.85rem;">ยังไม่มีบิล</span>
                            <?php else: ?>
                                <button class="btn-unpaid" onclick="showUnpaid(<?= $s['dorm_id'] ?>, '<?= htmlspecialchars(addslashes($s['dorm_name'])) ?>')">
                                    ดูห้องค้าง (<?= $rooms - $paid ?>)
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot style="background: #f8fafc; border-top: 2px solid #e2e8f0; font-weight: 700; color: #1e293b;">
                    <tr>
                        <td class="text-end py-3">รวมทั้งหมดทุกหอพัก</td>
                        <td class="text-end py-3 text-primary"><?= number_format($gtWater, 2) ?></td>
                        <td class="text-end py-3 text-warning"><?= number_format($gtElec, 2) ?></td>
                        <td class="text-end py-3" style="font-weight:600; color:#334155;"><?= number_format($gtNormal, 2) ?></td>
                        <td class="text-end py-3 text-danger"><?= number_format($gtFine, 2) ?></td>
                        <td class="text-end py-3 text-success fs-5"><?= number_format($gtTotal, 2) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

<?php endif; ?>

<!-- Unpaid Modal -->
<div class="modal fade" id="unpaidModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="modal-title mb-0" style="font-weight:700;">รายละเอียดห้องค้างชำระ</h5>
            <div id="modalDormName" style="font-size:0.85rem; color:#64748b; margin-top:2px;"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0" style="font-size:0.9rem;">
                <thead style="background:#f8fafc; color:#475569;">
                    <tr>
                        <th class="py-3 px-3" onclick="sortUnpaid('room_number')" style="cursor:pointer; user-select:none;">ห้อง / ผู้เช่า <span class="sort-icon ms-1" id="sort-icon-room_number"></span></th>
                        <th class="text-end py-3 px-3" onclick="sortUnpaid('water')" style="cursor:pointer; user-select:none;">ค่าน้ำ <span class="sort-icon ms-1" id="sort-icon-water"></span></th>
                        <th class="text-end py-3 px-3" onclick="sortUnpaid('elec')" style="cursor:pointer; user-select:none;">ค่าไฟ <span class="sort-icon ms-1" id="sort-icon-elec"></span></th>
                        <th class="text-end py-3 px-3" onclick="sortUnpaid('fine')" style="cursor:pointer; user-select:none;">ค่าปรับ <span class="sort-icon ms-1" id="sort-icon-fine"></span></th>
                        <th class="text-end py-3 px-3" onclick="sortUnpaid('total')" style="cursor:pointer; user-select:none;">ยอดรวม <span class="sort-icon ms-1" id="sort-icon-total"></span></th>
                        <th class="text-center py-3 px-3" onclick="sortUnpaid('status')" style="cursor:pointer; user-select:none;">สถานะ <span class="sort-icon ms-1" id="sort-icon-status"></span></th>
                    </tr>
                </thead>
                <tbody id="unpaidList">
                    <!-- Render via JS -->
                </tbody>
            </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const unpaidData = <?= json_encode($unpaidByDorm) ?>;
let modal;
let currentDormId = null;
let sortCol = 'room_number';
let sortDesc = false;

document.addEventListener('DOMContentLoaded', function() {
    modal = new bootstrap.Modal(document.getElementById('unpaidModal'));
});

function sortUnpaid(col) {
    if (sortCol === col) {
        sortDesc = !sortDesc;
    } else {
        sortCol = col;
        sortDesc = false;
    }
    renderUnpaidList();
}

function showUnpaid(dormId, dormName) {
    currentDormId = dormId;
    document.getElementById('modalDormName').textContent = 'หอพัก: ' + dormName;
    sortCol = 'room_number'; // reset default sort
    sortDesc = false;
    renderUnpaidList();
    modal.show();
}

function renderUnpaidList() {
    const list = document.getElementById('unpaidList');
    list.innerHTML = '';
    
    // Update sort icons
    document.querySelectorAll('.sort-icon').forEach(el => el.innerHTML = '');
    const iconEl = document.getElementById('sort-icon-' + sortCol);
    if (iconEl) {
        iconEl.innerHTML = sortDesc ? '<i class="bi bi-arrow-down-short"></i>' : '<i class="bi bi-arrow-up-short"></i>';
    }

    let rooms = unpaidData[currentDormId] ? [...unpaidData[currentDormId]] : [];
    if (rooms.length === 0) {
        list.innerHTML = '<tr><td colspan="6" class="p-4 text-center text-muted">ชำระครบทุกห้องแล้ว</td></tr>';
        return;
    }
    
    // Sort array
    rooms.sort((a, b) => {
        let valA, valB;
        switch(sortCol) {
            case 'room_number': valA = a.room_number; valB = b.room_number; break;
            case 'water': valA = parseFloat(a.water_amt) || 0; valB = parseFloat(b.water_amt) || 0; break;
            case 'elec': valA = parseFloat(a.elec_amt) || 0; valB = parseFloat(b.elec_amt) || 0; break;
            case 'fine': valA = parseFloat(a.water_fine) || 0; valB = parseFloat(b.water_fine) || 0; break;
            case 'total':
                valA = (parseFloat(a.water_amt)||0) + (parseFloat(a.elec_amt)||0) + (parseFloat(a.water_fine)||0);
                valB = (parseFloat(b.water_amt)||0) + (parseFloat(b.elec_amt)||0) + (parseFloat(b.water_fine)||0);
                break;
            case 'status':
                valA = a.payment_status || ''; valB = b.payment_status || ''; break;
        }
        
        if (valA < valB) return sortDesc ? 1 : -1;
        if (valA > valB) return sortDesc ? -1 : 1;
        return 0;
    });

    let html = '';
    rooms.forEach(r => {
        const wAmt = parseFloat(r.water_amt) || 0;
        const eAmt = parseFloat(r.elec_amt) || 0;
        const fAmt = parseFloat(r.water_fine) || 0;
        const tot = wAmt + eAmt + fAmt;
        
        let statusText = '';
        let statusBadge = '';
        if (r.water_status !== 'verified' || !r.elec_entered) {
            statusText = 'รอมิเตอร์';
            statusBadge = 'bg-secondary';
        } else if (!r.payment_status) {
            statusText = 'ยังไม่ส่งสลิป';
            statusBadge = 'bg-danger';
        } else if (r.payment_status === 'pending') {
            statusText = 'รอตรวจสอบ';
            statusBadge = 'bg-warning text-dark';
        } else {
            statusText = r.payment_status;
            statusBadge = 'bg-primary';
        }
        
        const p = r.student_phone ? `<br><small class="text-muted"><i class="bi bi-telephone"></i> ${escapeHtml(r.student_phone)}</small>` : '';
        const name = r.student_name ? escapeHtml(r.student_name) + p : '<em class="text-muted">ไม่มีข้อมูลผู้เช่า</em>';
        
        const waterDisp = wAmt === 0 ? '<span class="text-danger" style="font-weight:600;">รอมิเตอร์</span>' : wAmt.toLocaleString('th-TH', {minimumFractionDigits:2});
        const elecDisp = eAmt === 0 ? '<span class="text-danger" style="font-weight:600;">รอมิเตอร์</span>' : eAmt.toLocaleString('th-TH', {minimumFractionDigits:2});
        const fineDisp = fAmt === 0 ? '-' : fAmt.toLocaleString('th-TH', {minimumFractionDigits:2});
        const totDisp = tot > 0 ? tot.toLocaleString('th-TH', {minimumFractionDigits:2}) : '0.00';
        
        html += `
        <tr>
            <td class="px-3 py-2">
                <div style="font-weight:600; color:#1e293b;">ห้อง ${escapeHtml(r.room_number)}</div>
                <div style="font-size:0.8rem;">${name}</div>
            </td>
            <td class="text-end px-3 py-2 align-middle">${waterDisp}</td>
            <td class="text-end px-3 py-2 align-middle">${elecDisp}</td>
            <td class="text-end px-3 py-2 align-middle text-danger">${fineDisp}</td>
            <td class="text-end px-3 py-2 align-middle" style="font-weight:700; color:#e11d48;">${totDisp}</td>
            <td class="text-center px-3 py-2 align-middle">
                <span class="badge ${statusBadge}">${escapeHtml(statusText)}</span>
            </td>
        </tr>`;
    });
    list.innerHTML = html;
}

function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once 'includes/footer.php'; ?>
