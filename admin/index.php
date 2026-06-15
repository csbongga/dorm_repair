<?php
require_once 'includes/auth_check.php';
require_once '../connect.php';

$page_title   = 'Dashboard';
$current_page = 'dashboard';

// ===== ดึงข้อมูลสถิติ =====
// ยอดใบงานรวมแยกตามสถานะ
$statsStmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'รอดำเนินการ') AS pending,
        SUM(status = 'กำลังดำเนินการ') AS in_progress,
        SUM(status = 'เสร็จสิ้น') AS completed,
        SUM(status = 'ยกเลิก') AS cancelled
    FROM repair_requests
");
$stats = $statsStmt->fetch();

// จำนวนนักศึกษาที่ลงทะเบียน
$studentCount = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

// ใบงานใหม่วันนี้
$todayCount = $pdo->query("SELECT COUNT(*) FROM repair_requests WHERE DATE(created_at) = CURDATE()")->fetchColumn();

// 10 ใบงานล่าสุด
$recentStmt = $pdo->query("
    SELECT rr.ticket_id, rr.reporter_name, rr.status, rr.created_at,
           d.name AS dorm_name, r.room_number
    FROM repair_requests rr
    JOIN rooms r ON rr.room_id = r.id
    JOIN dorms d ON r.dorm_id = d.id
    ORDER BY rr.created_at DESC
    LIMIT 10
");
$recentRepairs = $recentStmt->fetchAll();

// สถิติรายการอุปกรณ์ที่แจ้งซ่อมมากที่สุด
$topItemsStmt = $pdo->query("
    SELECT rim.item_name, rim.category, COUNT(*) AS total
    FROM repair_items ri
    JOIN repair_items_master rim ON ri.item_master_id = rim.id
    GROUP BY rim.id
    ORDER BY total DESC
    LIMIT 6
");
$topItems = $topItemsStmt->fetchAll();

// สถิติรายหอพัก
$dormStatsStmt = $pdo->query("
    SELECT d.name AS dorm_name,
           COUNT(rr.id) AS total,
           SUM(rr.status = 'รอดำเนินการ') AS pending
    FROM dorms d
    LEFT JOIN rooms r ON d.id = r.dorm_id
    LEFT JOIN repair_requests rr ON r.id = rr.room_id
    GROUP BY d.id
    ORDER BY total DESC
");
$dormStats = $dormStatsStmt->fetchAll();

// Chart data: ใบงาน 7 วันย้อนหลัง
$chartStmt = $pdo->query("
    SELECT DATE(created_at) AS day, COUNT(*) AS cnt
    FROM repair_requests
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");
$chartRaw = $chartStmt->fetchAll();
$chartLabels = [];
$chartData   = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('d/m', strtotime($date));
    $chartLabels[] = $label;
    $found = false;
    foreach ($chartRaw as $row) {
        if ($row['day'] === $date) {
            $chartData[] = (int)$row['cnt'];
            $found = true;
            break;
        }
    }
    if (!$found) $chartData[] = 0;
}

// ===== ข้อมูลบิลค่าน้ำ =====
$cycleRow    = $pdo->query("SELECT id, label FROM bill_cycles WHERE is_current = 1 LIMIT 1")->fetch();
$cycle_id    = $cycleRow['id']    ?? null;
$cycle_label = $cycleRow['label'] ?? null;

$meterPending = $meterVerified = $meterNoData = 0;
if ($cycle_id) {
    $mp = $pdo->prepare("SELECT COUNT(*) FROM bill_meters WHERE cycle_id = ? AND water_status = 'review'");
    $mp->execute([$cycle_id]); $meterPending = (int)$mp->fetchColumn();

    $mv = $pdo->prepare("SELECT COUNT(*) FROM bill_meters WHERE cycle_id = ? AND water_status = 'verified'");
    $mv->execute([$cycle_id]); $meterVerified = (int)$mv->fetchColumn();

    $mn = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
    $ms = $pdo->prepare("SELECT COUNT(DISTINCT room_id) FROM bill_meters WHERE cycle_id = ?");
    $ms->execute([$cycle_id]); $meterNoData = max(0, (int)$mn - (int)$ms->fetchColumn());
}

$extra_head = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>';

include 'includes/header.php';

function statusBadge($status) {
    $map = [
        'รอดำเนินการ'    => 'badge-pending',
        'กำลังดำเนินการ' => 'badge-progress',
        'เสร็จสิ้น'      => 'badge-completed',
        'ยกเลิก'         => 'badge-cancelled',
    ];
    $cls = $map[$status] ?? 'badge-pending';
    return "<span class='badge-status {$cls}'>{$status}</span>";
}
?>

<?php
$rate = $stats['total'] > 0 ? round($stats['completed'] / $stats['total'] * 100) : 0;
?>

<!-- Section: งานซ่อม -->
<div style="font-size:0.72rem;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;">
    <i class="bi bi-clipboard2-check-fill me-1"></i> งานซ่อม
</div>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="repairs.php" class="stat-card d-block text-decoration-none">
            <div class="stat-icon" style="background:#fef3c7; color:#f59e0b;">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-value text-warning"><?= number_format($stats['pending']) ?></div>
            <div class="stat-label">รอดำเนินการ</div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="repairs.php" class="stat-card d-block text-decoration-none">
            <div class="stat-icon" style="background:#fee2e2; color:#dc2626;">
                <i class="bi bi-gear-fill"></i>
            </div>
            <div class="stat-value text-danger"><?= number_format($stats['in_progress']) ?></div>
            <div class="stat-label">กำลังดำเนินการ</div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#d1fae5; color:#059669;">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div class="stat-value text-success"><?= number_format($stats['completed']) ?></div>
            <div class="stat-label">เสร็จสิ้นแล้ว</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#ecfdf5; color:#059669;">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            <div class="stat-value text-success"><?= $rate ?>%</div>
            <div class="stat-label">อัตราซ่อมสำเร็จ</div>
        </div>
    </div>
</div>

<!-- Section: ค่าน้ำ / ค่าไฟ -->
<div style="font-size:0.72rem;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;">
    <i class="bi bi-droplet-fill me-1"></i> ค่าน้ำ / ค่าไฟ
    <?php if ($cycle_label): ?>
    <span style="background:#f0fdf4;color:#0d9488;border:1px solid #bbf7d0;border-radius:20px;padding:2px 10px;font-size:0.72rem;margin-left:8px;">
        <?= htmlspecialchars($cycle_label) ?>
    </span>
    <?php endif; ?>
</div>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="meter_verify.php" class="stat-card d-block text-decoration-none">
            <div class="stat-icon" style="background:#fef3c7; color:#d97706;">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-value" style="color:#d97706;"><?= $meterPending ?></div>
            <div class="stat-label">มิเตอร์รอตรวจสอบ</div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="meter_verify.php" class="stat-card d-block text-decoration-none">
            <div class="stat-icon" style="background:#ccfbf1; color:#0d9488;">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div class="stat-value" style="color:#0d9488;"><?= $meterVerified ?></div>
            <div class="stat-label">ยืนยันแล้ว</div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="meter_verify.php" class="stat-card d-block text-decoration-none">
            <div class="stat-icon" style="background:#f1f5f9; color:#94a3b8;">
                <i class="bi bi-dash-circle-fill"></i>
            </div>
            <div class="stat-value text-secondary"><?= $meterNoData ?></div>
            <div class="stat-label">ยังไม่มีข้อมูล</div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f0fdf4; color:#06C755;">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="stat-value" style="color:#06C755;"><?= number_format($studentCount) ?></div>
            <div class="stat-label">นักศึกษาลงทะเบียน</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Chart: ใบงาน 7 วันย้อนหลัง -->
    <div class="col-lg-8">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">
                    <i class="bi bi-bar-chart-fill"></i> ใบงานแจ้งซ่อม 7 วันย้อนหลัง
                </span>
            </div>
            <div class="panel-body">
                <canvas id="repairChart" height="80"></canvas>
            </div>
        </div>

        <!-- Recent Repairs -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">
                    <i class="bi bi-clock-history"></i> ใบงานล่าสุด
                </span>
                <a href="repairs.php" class="btn btn-sm btn-outline-secondary rounded-pill" style="font-size:0.8rem;">
                    ดูทั้งหมด <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-clean mb-0">
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>ผู้แจ้ง</th>
                            <th>ห้อง</th>
                            <th>สถานะ</th>
                            <th>วันที่</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRepairs as $r): ?>
                        <tr>
                            <td><span class="fw-600" style="font-size:0.85rem;font-weight:600;"><?= htmlspecialchars($r['ticket_id']) ?></span></td>
                            <td><?= htmlspecialchars($r['reporter_name']) ?></td>
                            <td>
                                <small class="text-muted"><?= htmlspecialchars($r['dorm_name']) ?></small><br>
                                ห้อง <?= htmlspecialchars($r['room_number']) ?>
                            </td>
                            <td><?= statusBadge($r['status']) ?></td>
                            <td style="font-size:0.82rem;color:#94a3b8;">
                                <?= date('d/m/Y H:i', strtotime($r['created_at'])) ?>
                            </td>
                            <td>
                                <a href="repair_detail.php?ticket=<?= urlencode($r['ticket_id']) ?>"
                                   class="btn btn-sm btn-outline-primary rounded-pill" style="font-size:0.78rem;">
                                    ดู
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentRepairs)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มีใบงานในระบบ</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right column -->
    <div class="col-lg-4">
        <!-- Top Items -->
        <div class="panel mb-4">
            <div class="panel-header">
                <span class="panel-title">
                    <i class="bi bi-list-ol"></i> อุปกรณ์ที่แจ้งซ่อมมากสุด
                </span>
            </div>
            <div class="panel-body pt-0">
                <?php foreach ($topItems as $i => $item): ?>
                <div class="d-flex align-items-center gap-3 py-2 border-bottom" style="border-color:#f1f5f9 !important;">
                    <div style="width:24px;height:24px;background:#f1f5f9;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;color:#64748b;flex-shrink:0;">
                        <?= $i + 1 ?>
                    </div>
                    <div class="flex-fill" style="min-width:0;">
                        <div style="font-size:0.88rem;font-weight:500;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?= htmlspecialchars($item['item_name']) ?>
                        </div>
                        <div style="font-size:0.75rem;color:#94a3b8;"><?= htmlspecialchars($item['category']) ?></div>
                    </div>
                    <div style="font-size:1rem;font-weight:700;color:#06C755;flex-shrink:0;"><?= $item['total'] ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($topItems)): ?>
                <div class="text-center text-muted py-3" style="font-size:0.85rem;">ยังไม่มีข้อมูล</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dorm Stats -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">
                    <i class="bi bi-building-fill"></i> สถิติรายหอพัก
                </span>
            </div>
            <div class="panel-body pt-0">
                <?php foreach ($dormStats as $d): ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span style="font-size:0.82rem;font-weight:500;color:#334155;">
                            <?= htmlspecialchars($d['dorm_name']) ?>
                        </span>
                        <span style="font-size:0.82rem;color:#64748b;"><?= $d['total'] ?> ใบ</span>
                    </div>
                    <?php
                    $maxTotal = max(array_column($dormStats, 'total') ?: [1]);
                    $pct = $maxTotal > 0 ? round($d['total'] / $maxTotal * 100) : 0;
                    ?>
                    <div style="height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden;">
                        <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,#06C755,#05a044);border-radius:3px;transition:width 0.5s;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php
$extra_scripts = '
<script>
const ctx = document.getElementById("repairChart").getContext("2d");
new Chart(ctx, {
    type: "bar",
    data: {
        labels: ' . json_encode($chartLabels) . ',
        datasets: [{
            label: "จำนวนใบงาน",
            data: ' . json_encode($chartData) . ',
            backgroundColor: "rgba(6,199,85,0.2)",
            borderColor: "#06C755",
            borderWidth: 2,
            borderRadius: 6,
            hoverBackgroundColor: "rgba(6,199,85,0.35)"
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1, font: { family: "Kanit" } },
                grid: { color: "#f1f5f9" }
            },
            x: {
                ticks: { font: { family: "Kanit" } },
                grid: { display: false }
            }
        }
    }
});
</script>';

include 'includes/footer.php';
?>
