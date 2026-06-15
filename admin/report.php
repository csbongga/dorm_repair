<?php
require_once 'includes/auth_check.php';
require_once '../connect.php';

// ====================================================================
// Parameters
// ====================================================================
$dateFrom   = $_GET['date_from'] ?? date('Y-m-01');
$dateTo     = $_GET['date_to']   ?? date('Y-m-d');
$filterCat  = $_GET['category']  ?? '';
$filterDorm = (int)($_GET['dorm_id'] ?? 0);
$printMode  = isset($_GET['print']);

$dateFromFmt = date('d/m/Y', strtotime($dateFrom));
$dateToFmt   = date('d/m/Y', strtotime($dateTo));
$dateToEnd   = $dateTo . ' 23:59:59'; // inclusive

$categories = ['ประปา', 'ไฟฟ้า', 'ซ่อมสร้าง'];
$catMeta = [
    'ประปา'    => ['emoji' => '💧', 'color' => '#3b82f6'],
    'ไฟฟ้า'   => ['emoji' => '⚡', 'color' => '#f59e0b'],
    'ซ่อมสร้าง' => ['emoji' => '🔨', 'color' => '#8b5cf6'],
];

// ====================================================================
// Queries
// ====================================================================
$baseWhere  = ['rr.created_at BETWEEN ? AND ?'];
$baseParams = [$dateFrom, $dateToEnd];

if ($filterCat) {
    $baseWhere[]  = 'rim.category = ?';
    $baseParams[] = $filterCat;
}
if ($filterDorm) {
    $baseWhere[]  = 'd.id = ?';
    $baseParams[] = $filterDorm;
}

$whereSQL = 'WHERE ' . implode(' AND ', $baseWhere);

// สรุปรายการอุปกรณ์
$itemsStmt = $pdo->prepare("
    SELECT
        rim.id,
        rim.item_name,
        rim.category,
        COUNT(DISTINCT ri.request_id)                                           AS request_count,
        SUM(ri.quantity)                                                         AS total_qty,
        SUM(CASE WHEN rr.status = 'เสร็จสิ้น'      THEN ri.quantity ELSE 0 END) AS qty_done,
        SUM(CASE WHEN rr.status = 'รอดำเนินการ'    THEN ri.quantity ELSE 0 END) AS qty_pending,
        SUM(CASE WHEN rr.status = 'กำลังดำเนินการ' THEN ri.quantity ELSE 0 END) AS qty_progress,
        SUM(CASE WHEN rr.status = 'ยกเลิก'         THEN ri.quantity ELSE 0 END) AS qty_cancelled
    FROM repair_items ri
    JOIN repair_items_master rim ON ri.item_master_id = rim.id
    JOIN repair_requests rr      ON ri.request_id     = rr.id
    JOIN rooms r                 ON rr.room_id         = r.id
    JOIN dorms d                 ON r.dorm_id          = d.id
    $whereSQL
    GROUP BY rim.id
    ORDER BY FIELD(rim.category,'ประปา','ไฟฟ้า','ซ่อมสร้าง'), total_qty DESC
");
$itemsStmt->execute($baseParams);
$reportItems = $itemsStmt->fetchAll();

// สรุปตามหมวด
$catSummary = array_fill_keys($categories, ['total_qty' => 0, 'qty_done' => 0, 'qty_pending' => 0, 'qty_progress' => 0, 'types' => 0]);
foreach ($reportItems as $it) {
    if (isset($catSummary[$it['category']])) {
        $catSummary[$it['category']]['total_qty']    += $it['total_qty'];
        $catSummary[$it['category']]['qty_done']     += $it['qty_done'];
        $catSummary[$it['category']]['qty_pending']  += $it['qty_pending'];
        $catSummary[$it['category']]['qty_progress'] += $it['qty_progress'];
        $catSummary[$it['category']]['types']++;
    }
}

// สถิติภาพรวม
$totalQty      = array_sum(array_column($reportItems, 'total_qty'));
$totalDone     = array_sum(array_column($reportItems, 'qty_done'));
$totalPending  = array_sum(array_column($reportItems, 'qty_pending'));
$totalProgress = array_sum(array_column($reportItems, 'qty_progress'));

// จำนวนใบงาน + ห้องที่แจ้ง
$reqParamCount  = [$dateFrom, $dateToEnd];
$reqWhereExtra  = '';
if ($filterDorm) { $reqWhereExtra .= ' AND d.id = ?'; $reqParamCount[] = $filterDorm; }
$reqStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT rr.id) AS req_count, COUNT(DISTINCT r.id) AS room_count
    FROM repair_requests rr
    JOIN rooms r ON rr.room_id = r.id
    JOIN dorms d ON r.dorm_id = d.id
    WHERE rr.created_at BETWEEN ? AND ? $reqWhereExtra
");
$reqStmt->execute($reqParamCount);
$reqStats = $reqStmt->fetch();

// สรุปตามหอพัก
$dormParamCount = [$dateFrom, $dateToEnd];
$dormWhereExtra = '';
if ($filterCat)  { $dormWhereExtra .= ' AND rim.category = ?'; $dormParamCount[] = $filterCat; }
if ($filterDorm) { $dormWhereExtra .= ' AND d.id = ?'; $dormParamCount[] = $filterDorm; }
$dormStmt = $pdo->prepare("
    SELECT d.name AS dorm_name,
           COUNT(DISTINCT rr.id)  AS req_count,
           SUM(ri.quantity)        AS total_qty
    FROM repair_requests rr
    JOIN rooms r                ON rr.room_id         = r.id
    JOIN dorms d                ON r.dorm_id          = d.id
    JOIN repair_items ri        ON rr.id              = ri.request_id
    JOIN repair_items_master rim ON ri.item_master_id = rim.id
    WHERE rr.created_at BETWEEN ? AND ? $dormWhereExtra
    GROUP BY d.id
    ORDER BY total_qty DESC
");
$dormStmt->execute($dormParamCount);
$dormReport = $dormStmt->fetchAll();

// ดึง dorms สำหรับ filter
$dorms = $pdo->query("SELECT id, name FROM dorms ORDER BY name")->fetchAll();
$selectedDormName = '';
if ($filterDorm) {
    foreach ($dorms as $d) { if ($d['id'] == $filterDorm) { $selectedDormName = $d['name']; break; } }
}

// ====================================================================
// Print Mode — แสดงหน้าพิมพ์ล้วน ไม่มี layout
// ====================================================================
if ($printMode):
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานอุปกรณ์ <?= $dateFromFmt ?> – <?= $dateToFmt ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Kanit', sans-serif; font-size: 13px; color: #1e293b; background: white; padding: 20px 28px; }

        .print-header { display: flex; align-items: flex-start; justify-content: space-between; border-bottom: 2.5px solid #06C755; padding-bottom: 12px; margin-bottom: 18px; }
        .print-header .logo { font-size: 1.2rem; font-weight: 700; color: #06C755; }
        .print-header .title { font-size: 1rem; font-weight: 600; color: #1e293b; }
        .print-header .subtitle { font-size: 0.78rem; color: #64748b; margin-top: 2px; }
        .print-header .meta { text-align: right; font-size: 0.75rem; color: #64748b; }

        .section-title { font-size: 0.88rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.04em; margin: 18px 0 8px 0; border-left: 3px solid #06C755; padding-left: 8px; }

        .stats-row { display: flex; gap: 12px; margin-bottom: 16px; }
        .stat-box { flex: 1; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; text-align: center; }
        .stat-box .val { font-size: 1.5rem; font-weight: 700; color: #1e293b; }
        .stat-box .lbl { font-size: 0.72rem; color: #64748b; }

        table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        th { background: #f8fafc; font-weight: 600; color: #475569; text-align: left; padding: 7px 10px; border-bottom: 1.5px solid #e2e8f0; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.03em; }
        td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        .tfoot-row td { background: #f8fafc; font-weight: 600; border-top: 1.5px solid #e2e8f0; }

        .cat-header td { background: #f0fdf4; font-weight: 700; font-size: 0.82rem; color: #065f46; border-top: 1.5px solid #bbf7d0; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 0.72rem; font-weight: 600; }
        .badge-pending   { background: #fef3c7; color: #d97706; }
        .badge-progress  { background: #dbeafe; color: #2563eb; }
        .badge-done      { background: #d1fae5; color: #059669; }

        .num { text-align: right; font-weight: 600; }
        .num-total { text-align: right; font-weight: 700; font-size: 1rem; color: #06C755; }

        .dorm-table { margin-top: 6px; }

        .footer-note { margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 10px; font-size: 0.72rem; color: #94a3b8; display: flex; justify-content: space-between; }

        @media print {
            body { padding: 10px 16px; }
            @page { margin: 12mm 14mm; size: A4; }
            .no-print { display: none !important; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

    <!-- ปุ่มพิมพ์ (ซ่อนตอนพิมพ์จริง) -->
    <div class="no-print" style="margin-bottom:16px;display:flex;gap:8px;">
        <button onclick="window.print()" style="background:#06C755;color:white;border:none;padding:9px 20px;border-radius:8px;font-family:'Kanit',sans-serif;font-size:0.9rem;font-weight:600;cursor:pointer;">
            🖨️ พิมพ์ / บันทึก PDF
        </button>
        <button onclick="window.close()" style="background:#f1f5f9;color:#475569;border:none;padding:9px 16px;border-radius:8px;font-family:'Kanit',sans-serif;font-size:0.88rem;cursor:pointer;">
            ✕ ปิด
        </button>
    </div>

    <!-- หัวรายงาน -->
    <div class="print-header">
        <div>
            <div class="logo">🔧 ระบบแจ้งซ่อมหอพัก</div>
            <div class="title" style="margin-top:4px;">รายงานอุปกรณ์และวัสดุที่ใช้ในการซ่อม</div>
            <div class="subtitle">
                ช่วงวันที่: <?= $dateFromFmt ?> – <?= $dateToFmt ?>
                <?= $filterCat ? ' | หมวด: ' . $catMeta[$filterCat]['emoji'] . ' ' . $filterCat : '' ?>
                <?= $selectedDormName ? ' | หอพัก: ' . htmlspecialchars($selectedDormName) : '' ?>
            </div>
        </div>
        <div class="meta">
            ออกรายงาน: <?= date('d/m/Y H:i') ?> น.<br>
            โดย: <?= htmlspecialchars($_SESSION['admin_name']) ?>
        </div>
    </div>

    <!-- สถิติภาพรวม -->
    <div class="stats-row">
        <div class="stat-box">
            <div class="val"><?= number_format($reqStats['req_count']) ?></div>
            <div class="lbl">ใบงานทั้งหมด</div>
        </div>
        <div class="stat-box">
            <div class="val"><?= number_format($reqStats['room_count']) ?></div>
            <div class="lbl">ห้องพักที่แจ้ง</div>
        </div>
        <div class="stat-box">
            <div class="val" style="color:#06C755;"><?= number_format($totalQty) ?></div>
            <div class="lbl">รายการอุปกรณ์รวม</div>
        </div>
        <div class="stat-box">
            <div class="val" style="color:#059669;"><?= number_format($totalDone) ?></div>
            <div class="lbl">ซ่อมเสร็จแล้ว</div>
        </div>
        <div class="stat-box">
            <div class="val" style="color:#d97706;"><?= number_format($totalPending + $totalProgress) ?></div>
            <div class="lbl">ยังไม่เสร็จ</div>
        </div>
    </div>

    <!-- ตารางอุปกรณ์ -->
    <div class="section-title">รายละเอียดอุปกรณ์แยกตามหมวด</div>
    <table>
        <thead>
            <tr>
                <th style="width:5%;">#</th>
                <th style="width:38%;">ชื่ออุปกรณ์</th>
                <th class="num" style="width:11%;">รวม (ชิ้น)</th>
                <th class="num" style="width:11%;">เสร็จแล้ว</th>
                <th class="num" style="width:11%;">กำลังซ่อม</th>
                <th class="num" style="width:11%;">รอดำเนินการ</th>
                <th class="num" style="width:13%;">ใบงาน</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $rowNum = 0;
        $currentCat = '';
        foreach ($reportItems as $item):
            if ($item['category'] !== $currentCat):
                $currentCat = $item['category'];
                $m = $catMeta[$currentCat];
        ?>
            <tr class="cat-header">
                <td colspan="7"><?= $m['emoji'] ?> <?= $currentCat ?> — รวม <?= number_format($catSummary[$currentCat]['total_qty']) ?> ชิ้น (<?= $catSummary[$currentCat]['types'] ?> ประเภท)</td>
            </tr>
        <?php $rowNum = 0; endif; $rowNum++; ?>
            <tr>
                <td style="color:#94a3b8;"><?= $rowNum ?></td>
                <td><?= htmlspecialchars($item['item_name']) ?></td>
                <td class="num-total"><?= number_format($item['total_qty']) ?></td>
                <td class="num" style="color:#059669;"><?= $item['qty_done'] > 0 ? number_format($item['qty_done']) : '—' ?></td>
                <td class="num" style="color:#2563eb;"><?= $item['qty_progress'] > 0 ? number_format($item['qty_progress']) : '—' ?></td>
                <td class="num" style="color:#d97706;"><?= $item['qty_pending'] > 0 ? number_format($item['qty_pending']) : '—' ?></td>
                <td class="num"><?= $item['request_count'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($reportItems)): ?>
            <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:20px;">ไม่พบข้อมูลในช่วงวันที่เลือก</td></tr>
        <?php endif; ?>
        </tbody>
        <tfoot>
            <tr class="tfoot-row">
                <td colspan="2" style="font-weight:700;">รวมทั้งหมด</td>
                <td class="num" style="color:#06C755;font-size:1rem;"><?= number_format($totalQty) ?></td>
                <td class="num" style="color:#059669;"><?= number_format($totalDone) ?></td>
                <td class="num" style="color:#2563eb;"><?= number_format($totalProgress) ?></td>
                <td class="num" style="color:#d97706;"><?= number_format($totalPending) ?></td>
                <td class="num"><?= number_format($reqStats['req_count']) ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- สรุปตามหอพัก -->
    <?php if (!empty($dormReport)): ?>
    <div class="section-title" style="margin-top:24px;">สรุปตามหอพัก</div>
    <table class="dorm-table">
        <thead>
            <tr>
                <th style="width:50%;">หอพัก</th>
                <th class="num" style="width:25%;">จำนวนอุปกรณ์รวม</th>
                <th class="num" style="width:25%;">จำนวนใบงาน</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($dormReport as $dr): ?>
            <tr>
                <td><?= htmlspecialchars($dr['dorm_name']) ?></td>
                <td class="num" style="font-weight:600;color:#06C755;"><?= number_format($dr['total_qty']) ?></td>
                <td class="num"><?= $dr['req_count'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Footer -->
    <div class="footer-note">
        <span>ระบบแจ้งซ่อมหอพัก © <?= date('Y') ?></span>
        <span>รายงานนี้สร้างจากข้อมูลระบบ ณ วันที่ <?= date('d/m/Y H:i') ?> น.</span>
    </div>

    <script>
        // Auto-print เมื่อ URL มี autoprint=1
        if (new URLSearchParams(window.location.search).get('autoprint') === '1') {
            window.addEventListener('load', () => setTimeout(() => window.print(), 500));
        }
    </script>
</body>
</html>
<?php
exit;
endif;

// ====================================================================
// Normal Admin View
// ====================================================================
$page_title   = 'ออกรายงาน';
$current_page = 'report';

$extra_head = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>';

include 'includes/header.php';

// Build print URL
$printParams = http_build_query([
    'print'     => '1',
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
    'category'  => $filterCat,
    'dorm_id'   => $filterDorm,
]);
$printUrl = 'report.php?' . $printParams;
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-file-earmark-bar-graph-fill me-2" style="color:#06C755;"></i>ออกรายงาน</h2>
        <p class="page-desc">รายงานอุปกรณ์และวัสดุที่ใช้ในการซ่อม</p>
    </div>
    <a href="<?= htmlspecialchars($printUrl) ?>" target="_blank"
       class="btn btn-sm d-flex align-items-center gap-2"
       style="background:#1e293b;color:white;border:none;border-radius:10px;padding:9px 18px;font-size:0.88rem;font-weight:600;">
        <i class="bi bi-file-earmark-pdf-fill"></i> ดาวน์โหลด PDF
    </a>
</div>

<!-- Filter -->
<div class="panel mb-4">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-funnel-fill"></i> เงื่อนไขรายงาน</span>
    </div>
    <div class="panel-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label mb-1" style="font-size:0.82rem;font-weight:500;color:#64748b;">วันเริ่มต้น</label>
                <input type="date" name="date_from" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($dateFrom) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label mb-1" style="font-size:0.82rem;font-weight:500;color:#64748b;">วันสิ้นสุด</label>
                <input type="date" name="date_to" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($dateTo) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label mb-1" style="font-size:0.82rem;font-weight:500;color:#64748b;">หมวดอุปกรณ์</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">ทุกหมวด</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= $c ?>" <?= $filterCat === $c ? 'selected' : '' ?>>
                        <?= $catMeta[$c]['emoji'] ?> <?= $c ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label mb-1" style="font-size:0.82rem;font-weight:500;color:#64748b;">หอพัก</label>
                <select name="dorm_id" class="form-select form-select-sm">
                    <option value="">ทุกหอพัก</option>
                    <?php foreach ($dorms as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $filterDorm == $d['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm flex-fill" style="background:#06C755;color:white;border:none;border-radius:8px;">
                    <i class="bi bi-search me-1"></i>ดูรายงาน
                </button>
                <a href="report.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>

            <!-- Quick date shortcuts -->
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2" style="font-size:0.8rem;">
                    <span style="color:#94a3b8;align-self:center;">เลือกเร็ว:</span>
                    <?php
                    $shortcuts = [
                        'เดือนนี้'       => [date('Y-m-01'), date('Y-m-d')],
                        'เดือนที่แล้ว'   => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last month'))],
                        '3 เดือน'        => [date('Y-m-d', strtotime('-3 months')), date('Y-m-d')],
                        '6 เดือน'        => [date('Y-m-d', strtotime('-6 months')), date('Y-m-d')],
                        'ปีนี้'          => [date('Y-01-01'), date('Y-m-d')],
                    ];
                    foreach ($shortcuts as $label => [$from, $to]):
                    ?>
                    <a href="?date_from=<?= $from ?>&date_to=<?= $to ?>&category=<?= urlencode($filterCat) ?>&dorm_id=<?= $filterDorm ?>"
                       class="badge text-decoration-none"
                       style="background:<?= ($dateFrom === $from && $dateTo === $to) ? '#06C755' : '#f1f5f9' ?>;color:<?= ($dateFrom === $from && $dateTo === $to) ? 'white' : '#475569' ?>;padding:5px 10px;border-radius:8px;font-size:0.78rem;font-weight:500;">
                        <?= $label ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f1f5f9;color:#475569;"><i class="bi bi-calendar-range-fill"></i></div>
            <div class="stat-value" style="font-size:1.1rem;"><?= $dateFromFmt ?></div>
            <div class="stat-label">ถึง <?= $dateToFmt ?></div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe;color:#3b82f6;"><i class="bi bi-clipboard2-fill"></i></div>
            <div class="stat-value" style="color:#3b82f6;"><?= number_format($reqStats['req_count']) ?></div>
            <div class="stat-label">ใบงานทั้งหมด</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f0fdf4;color:#06C755;"><i class="bi bi-box-seam-fill"></i></div>
            <div class="stat-value" style="color:#06C755;"><?= number_format($totalQty) ?></div>
            <div class="stat-label">รายการรวม</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:#d1fae5;color:#059669;"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-value text-success"><?= number_format($totalDone) ?></div>
            <div class="stat-label">เสร็จแล้ว</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef3c7;color:#f59e0b;"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-value text-warning"><?= number_format($totalPending) ?></div>
            <div class="stat-label">รอดำเนินการ</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe;color:#2563eb;"><i class="bi bi-gear-fill"></i></div>
            <div class="stat-value text-primary"><?= number_format($totalProgress) ?></div>
            <div class="stat-label">กำลังดำเนินการ</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Main report table -->
    <div class="col-lg-8">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-table"></i> รายละเอียดอุปกรณ์</span>
                <a href="<?= htmlspecialchars($printUrl) ?>" target="_blank"
                   style="font-size:0.8rem;color:#06C755;text-decoration:none;font-weight:500;">
                    <i class="bi bi-printer me-1"></i>พิมพ์/PDF
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-clean mb-0">
                    <thead>
                        <tr>
                            <th style="padding-left:16px;">#</th>
                            <th>ชื่ออุปกรณ์</th>
                            <th class="text-center">รวม</th>
                            <th class="text-center">เสร็จ</th>
                            <th class="text-center">กำลังซ่อม</th>
                            <th class="text-center">รอ</th>
                            <th class="text-center" style="padding-right:16px;">ใบงาน</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $currentCat = '';
                    $rowNum = 0;
                    foreach ($reportItems as $item):
                        if ($item['category'] !== $currentCat):
                            $currentCat = $item['category'];
                            $m = $catMeta[$currentCat];
                            $rowNum = 0;
                    ?>
                        <tr style="background:<?= $m['color'] ?>08;">
                            <td colspan="7" style="padding-left:16px;font-weight:700;font-size:0.82rem;color:<?= $m['color'] ?>;border-bottom:1px solid <?= $m['color'] ?>22;">
                                <?= $m['emoji'] ?> <?= $currentCat ?>
                                <span style="font-weight:400;color:#94a3b8;margin-left:8px;">รวม <?= number_format($catSummary[$currentCat]['total_qty']) ?> ชิ้น</span>
                            </td>
                        </tr>
                    <?php $rowNum = 0; endif; $rowNum++; ?>
                        <tr>
                            <td style="padding-left:16px;color:#94a3b8;font-size:0.8rem;"><?= $rowNum ?></td>
                            <td style="font-weight:500;font-size:0.9rem;"><?= htmlspecialchars($item['item_name']) ?></td>
                            <td class="text-center" style="font-weight:700;font-size:1rem;color:#06C755;"><?= number_format($item['total_qty']) ?></td>
                            <td class="text-center">
                                <?= $item['qty_done'] > 0 ? "<span class='badge-status badge-completed' style='font-size:0.75rem;'>{$item['qty_done']}</span>" : '<span style="color:#cbd5e1;">—</span>' ?>
                            </td>
                            <td class="text-center">
                                <?= $item['qty_progress'] > 0 ? "<span class='badge-status badge-progress' style='font-size:0.75rem;'>{$item['qty_progress']}</span>" : '<span style="color:#cbd5e1;">—</span>' ?>
                            </td>
                            <td class="text-center">
                                <?= $item['qty_pending'] > 0 ? "<span class='badge-status badge-pending' style='font-size:0.75rem;'>{$item['qty_pending']}</span>" : '<span style="color:#cbd5e1;">—</span>' ?>
                            </td>
                            <td class="text-center" style="padding-right:16px;color:#64748b;font-size:0.88rem;"><?= $item['request_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($reportItems)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                            ไม่พบข้อมูลในช่วงวันที่เลือก
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                    <?php if (!empty($reportItems)): ?>
                    <tfoot>
                        <tr style="background:#f8fafc;border-top:2px solid #e2e8f0;">
                            <td colspan="2" style="padding-left:16px;font-weight:700;color:#1e293b;">รวมทั้งหมด</td>
                            <td class="text-center" style="font-weight:700;font-size:1.1rem;color:#06C755;"><?= number_format($totalQty) ?></td>
                            <td class="text-center" style="font-weight:600;color:#059669;"><?= number_format($totalDone) ?></td>
                            <td class="text-center" style="font-weight:600;color:#2563eb;"><?= number_format($totalProgress) ?></td>
                            <td class="text-center" style="font-weight:600;color:#d97706;"><?= number_format($totalPending) ?></td>
                            <td class="text-center" style="padding-right:16px;font-weight:600;"><?= $reqStats['req_count'] ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Right: Charts & Dorm summary -->
    <div class="col-lg-4">

        <!-- Donut chart: สัดส่วน category -->
        <?php if (!empty($reportItems)): ?>
        <div class="panel mb-4">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-pie-chart-fill"></i> สัดส่วนตามหมวด</span>
            </div>
            <div class="panel-body text-center">
                <canvas id="catChart" height="180"></canvas>
                <div class="d-flex justify-content-center gap-3 mt-3 flex-wrap">
                    <?php foreach ($categories as $c):
                        if ($catSummary[$c]['total_qty'] == 0) continue;
                        $m = $catMeta[$c];
                    ?>
                    <div style="font-size:0.78rem;text-align:center;">
                        <div style="width:10px;height:10px;border-radius:50%;background:<?= $m['color'] ?>;display:inline-block;margin-right:4px;"></div>
                        <?= $m['emoji'] ?> <?= $c ?>
                        <div style="font-weight:700;color:<?= $m['color'] ?>;font-size:0.9rem;"><?= number_format($catSummary[$c]['total_qty']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- สรุปตามหอพัก -->
        <?php if (!empty($dormReport)): ?>
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-building-fill"></i> สรุปตามหอพัก</span>
            </div>
            <div class="panel-body pt-0">
                <?php
                $maxDormQty = max(array_column($dormReport, 'total_qty') ?: [1]);
                foreach ($dormReport as $dr):
                    $pct = round($dr['total_qty'] / $maxDormQty * 100);
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span style="font-size:0.85rem;font-weight:500;color:#334155;"><?= htmlspecialchars($dr['dorm_name']) ?></span>
                        <span style="font-size:0.82rem;font-weight:600;color:#06C755;"><?= number_format($dr['total_qty']) ?> ชิ้น</span>
                    </div>
                    <div style="height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden;">
                        <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,#06C755,#05a044);border-radius:3px;"></div>
                    </div>
                    <div style="font-size:0.72rem;color:#94a3b8;margin-top:2px;"><?= $dr['req_count'] ?> ใบงาน</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$catLabels = json_encode(array_filter($categories, fn($c) => $catSummary[$c]['total_qty'] > 0));
$catValues = json_encode(array_values(array_filter(array_map(fn($c) => $catSummary[$c]['total_qty'], $categories), fn($v) => $v > 0)));
$catColors = json_encode(array_values(array_filter(
    array_map(fn($c) => $catSummary[$c]['total_qty'] > 0 ? $catMeta[$c]['color'] : null, $categories),
    fn($v) => $v !== null
)));

$extra_scripts = <<<JS
<script>
(function() {
    const ctx = document.getElementById('catChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: {$catLabels},
            datasets: [{
                data: {$catValues},
                backgroundColor: {$catColors},
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            cutout: '65%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.label + ': ' + ctx.parsed.toLocaleString() + ' ชิ้น'
                    }
                }
            }
        }
    });
})();
</script>
JS;

include 'includes/footer.php';
?>
