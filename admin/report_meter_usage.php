<?php
require_once 'includes/auth_check.php';
require_once '../connect.php';

$page_title   = 'ตารางการใช้น้ำ/ไฟ';
$current_page = 'report_meter_usage';

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

// หอพักที่เลือก (สำหรับ filter เพิ่มเติมถ้ามี - ตอนนี้ดึงทั้งหมด)
$filter_dorm = $_GET['dorm_id'] ?? '';

$dorms = $pdo->query("SELECT id, name FROM dorms ORDER BY name ASC")->fetchAll();

// ดึงข้อมูลการใช้งานน้ำและไฟของแต่ละห้อง
$sql = "
    SELECT r.id AS room_id, r.room_number, r.status, r.status_note, d.name AS dorm_name,
           bm.water_prev, bm.water_curr, bm.water_amt,
           bm.elec_prev, bm.elec_curr, bm.elec_amt
    FROM rooms r
    JOIN dorms d ON r.dorm_id = d.id
    LEFT JOIN bill_meters bm ON r.id = bm.room_id AND bm.cycle_id = :cid
";
$params = ['cid' => $cycle_id];

if (!empty($filter_dorm)) {
    $sql .= " WHERE r.dorm_id = :did";
    $params['did'] = $filter_dorm;
}

$sql .= " ORDER BY d.id ASC, r.floor ASC, r.room_number ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

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
.table-usage th {
    background: #f8fafc; color: #64748b; font-weight: 600; font-size: 0.85rem;
    padding: 12px 10px; border-bottom: 2px solid #e2e8f0; white-space: nowrap;
}
.table-usage td {
    padding: 10px 10px; vertical-align: middle; border-bottom: 1px solid #e2e8f0;
    font-size: 0.9rem;
}
.val-number {
    font-family: monospace; font-size: 0.95rem; text-align: right;
}
.col-water { background-color: #f0f9ff; }
.col-elec { background-color: #fffbeb; }
</style>
CSS;

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-3">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label" style="font-size:0.85rem;color:#64748b;">เลือกรอบบิล</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-calendar-check text-muted"></i></span>
                            <select name="cycle_id" class="form-select border-start-0 ps-0" onchange="this.form.submit()">
                                <?php foreach ($cycles as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $cycle_id == $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['label']) ?>
                                        <?= $c['is_current'] ? ' (ปัจจุบัน)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" style="font-size:0.85rem;color:#64748b;">เลือกหอพัก</label>
                        <select name="dorm_id" class="form-select" onchange="this.form.submit()">
                            <option value="">-- ทุกหอพัก --</option>
                            <?php foreach ($dorms as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $filter_dorm == $d['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="summary-card">
    <div class="summary-header">
        <div class="summary-title"><i class="bi bi-table me-2 text-primary"></i> ข้อมูลการใช้งานน้ำและไฟ</div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-usage mb-0 align-middle">
            <thead>
                <tr>
                    <th rowspan="2" class="text-center">เลขห้อง</th>
                    <th colspan="4" class="text-center col-water" style="border-left: 2px solid #e2e8f0;">น้ำประปา</th>
                    <th colspan="4" class="text-center col-elec" style="border-left: 2px solid #e2e8f0; border-right: 2px solid #e2e8f0;">ไฟฟ้า</th>
                    <th rowspan="2" class="text-center">หมายเหตุ (สถานะห้อง)</th>
                </tr>
                <tr>
                    <!-- น้ำ -->
                    <th class="text-end col-water" style="border-left: 2px solid #e2e8f0;">เดือนที่แล้ว</th>
                    <th class="text-end col-water">เดือนนี้</th>
                    <th class="text-end col-water">หน่วยที่ใช้</th>
                    <th class="text-end col-water">คิดเป็นเงิน</th>
                    <!-- ไฟ -->
                    <th class="text-end col-elec" style="border-left: 2px solid #e2e8f0;">เดือนที่แล้ว</th>
                    <th class="text-end col-elec">เดือนนี้</th>
                    <th class="text-end col-elec">หน่วยที่ใช้</th>
                    <th class="text-end col-elec" style="border-right: 2px solid #e2e8f0;">คิดเป็นเงิน</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">ไม่พบข้อมูลในรอบบิลนี้</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): 
                        // น้ำ
                        $w_prev = $row['water_prev'];
                        $w_curr = $row['water_curr'];
                        $w_amt  = $row['water_amt'];
                        $w_units = ($w_curr !== null && $w_prev !== null) ? ($w_curr - $w_prev) : null;
                        
                        // ไฟ
                        $e_prev = $row['elec_prev'];
                        $e_curr = $row['elec_curr'];
                        $e_amt  = $row['elec_amt'];
                        $e_units = ($e_curr !== null && $e_prev !== null) ? ($e_curr - $e_prev) : null;
                        
                        // สถานะ
                        $statusText = $row['status'];
                        if (!empty($row['status_note'])) {
                            $statusText .= " ({$row['status_note']})";
                        }
                    ?>
                    <tr>
                        <td class="text-center fw-bold">
                            <?= htmlspecialchars($row['room_number']) ?><br>
                            <span style="font-size:0.7rem;color:#94a3b8;font-weight:normal;"><?= htmlspecialchars($row['dorm_name']) ?></span>
                        </td>
                        
                        <!-- น้ำ -->
                        <td class="val-number col-water" style="border-left: 2px solid #e2e8f0;"><?= $w_prev !== null ? number_format($w_prev) : '-' ?></td>
                        <td class="val-number col-water"><?= $w_curr !== null ? number_format($w_curr) : '-' ?></td>
                        <td class="val-number col-water fw-bold text-primary"><?= $w_units !== null ? number_format($w_units) : '-' ?></td>
                        <td class="val-number col-water" style="color:#059669;"><?= $w_amt !== null ? number_format($w_amt, 2) : '-' ?></td>
                        
                        <!-- ไฟ -->
                        <td class="val-number col-elec" style="border-left: 2px solid #e2e8f0;"><?= $e_prev !== null ? number_format($e_prev) : '-' ?></td>
                        <td class="val-number col-elec"><?= $e_curr !== null ? number_format($e_curr) : '-' ?></td>
                        <td class="val-number col-elec fw-bold text-warning" style="color:#d97706!important;"><?= $e_units !== null ? number_format($e_units) : '-' ?></td>
                        <td class="val-number col-elec" style="color:#059669; border-right: 2px solid #e2e8f0;"><?= $e_amt !== null ? number_format($e_amt, 2) : '-' ?></td>
                        
                        <td class="text-center">
                            <?php if ($row['status'] === 'พร้อมใช้งาน'): ?>
                                <span class="badge" style="background:#f0fdf4;color:#16a34a;font-weight:normal;"><?= htmlspecialchars($statusText) ?></span>
                            <?php elseif (in_array($row['status'], ['ห้องว่าง', 'ห้องสำรอง', 'ห้องอาจารย์'])): ?>
                                <span class="badge" style="background:#f1f5f9;color:#64748b;font-weight:normal;"><?= htmlspecialchars($statusText) ?></span>
                            <?php else: ?>
                                <span class="badge" style="background:#fef2f2;color:#ef4444;font-weight:normal;"><?= htmlspecialchars($statusText) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
