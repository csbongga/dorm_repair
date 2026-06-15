<?php
require_once 'includes/auth_check.php';
require_once '../connect.php';

$page_title   = 'จัดการใบงานแจ้งซ่อม';
$current_page = 'repairs';

// ===== Filter Parameters =====
$filter_status = $_GET['status'] ?? '';
$filter_dorm   = $_GET['dorm_id'] ?? '';
$filter_search = trim($_GET['q'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 15;

// ===== Build Query =====
$where  = [];
$params = [];

if ($filter_status) {
    $where[]  = 'rr.status = ?';
    $params[] = $filter_status;
}
if ($filter_dorm) {
    $where[]  = 'd.id = ?';
    $params[] = (int)$filter_dorm;
}
if ($filter_search) {
    $where[]  = '(rr.ticket_id LIKE ? OR rr.reporter_name LIKE ? OR r.room_number LIKE ?)';
    $params[] = "%$filter_search%";
    $params[] = "%$filter_search%";
    $params[] = "%$filter_search%";
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$totalStmt = $pdo->prepare("
    SELECT COUNT(*) FROM repair_requests rr
    JOIN rooms r ON rr.room_id = r.id
    JOIN dorms d ON r.dorm_id = d.id
    $whereSQL
");
$totalStmt->execute($params);
$total      = (int)$totalStmt->fetchColumn();
$total_pages = ceil($total / $per_page);
$offset      = ($page - 1) * $per_page;

$repairsStmt = $pdo->prepare("
    SELECT rr.id, rr.ticket_id, rr.reporter_name, rr.reporter_phone,
           rr.status, rr.created_at, rr.additional_details,
           d.name AS dorm_name, r.room_number,
           (SELECT COUNT(*) FROM repair_items ri WHERE ri.request_id = rr.id) AS item_count,
           (SELECT COUNT(*) FROM repair_images img WHERE img.request_id = rr.id) AS image_count
    FROM repair_requests rr
    JOIN rooms r ON rr.room_id = r.id
    JOIN dorms d ON r.dorm_id = d.id
    $whereSQL
    ORDER BY rr.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$repairsStmt->execute($params);
$repairs = $repairsStmt->fetchAll();

// ดึง Dorms สำหรับ filter
$dorms = $pdo->query("SELECT id, name FROM dorms ORDER BY name")->fetchAll();

$statuses = ['รอดำเนินการ', 'กำลังดำเนินการ', 'เสร็จสิ้น', 'ยกเลิก'];

include 'includes/header.php';

function statusBadge($s) {
    $map = [
        'รอดำเนินการ'    => 'badge-pending',
        'กำลังดำเนินการ' => 'badge-progress',
        'เสร็จสิ้น'      => 'badge-completed',
        'ยกเลิก'         => 'badge-cancelled',
    ];
    return "<span class='badge-status " . ($map[$s] ?? 'badge-pending') . "'>{$s}</span>";
}
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-clipboard2-check-fill me-2" style="color:#06C755;"></i>ใบงานแจ้งซ่อม</h2>
        <p class="page-desc">ทั้งหมด <?= number_format($total) ?> รายการ</p>
    </div>
</div>

<!-- Filter Panel -->
<div class="panel mb-4">
    <div class="panel-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label mb-1" style="font-size:0.82rem;font-weight:500;color:#64748b;">ค้นหา</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" name="q" class="form-control border-start-0"
                           placeholder="Ticket ID, ชื่อ, เลขห้อง..."
                           value="<?= htmlspecialchars($filter_search) ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label mb-1" style="font-size:0.82rem;font-weight:500;color:#64748b;">สถานะ</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">ทุกสถานะ</option>
                    <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label mb-1" style="font-size:0.82rem;font-weight:500;color:#64748b;">หอพัก</label>
                <select name="dorm_id" class="form-select form-select-sm">
                    <option value="">ทุกหอพัก</option>
                    <?php foreach ($dorms as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $filter_dorm == $d['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-success flex-fill" style="background:#06C755;border:none;">
                    <i class="bi bi-funnel-fill me-1"></i>กรอง
                </button>
                <a href="repairs.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="panel">
    <div class="table-responsive">
        <table class="table table-clean mb-0">
            <thead>
                <tr>
                    <th style="padding-left:20px;">#</th>
                    <th>Ticket ID</th>
                    <th>ผู้แจ้ง</th>
                    <th>ห้อง / หอพัก</th>
                    <th>อุปกรณ์</th>
                    <th>สถานะ</th>
                    <th>วันที่แจ้ง</th>
                    <th style="padding-right:20px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($repairs)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                        ไม่พบใบงานที่ตรงกับเงื่อนไข
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($repairs as $idx => $rr): ?>
                <tr>
                    <td style="padding-left:20px;color:#94a3b8;font-size:0.82rem;">
                        <?= $offset + $idx + 1 ?>
                    </td>
                    <td>
                        <span style="font-weight:600;font-size:0.88rem;color:#1e293b;">
                            <?= htmlspecialchars($rr['ticket_id']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="font-size:0.9rem;font-weight:500;"><?= htmlspecialchars($rr['reporter_name']) ?></div>
                        <?php if ($rr['reporter_phone']): ?>
                        <small class="text-muted"><?= htmlspecialchars($rr['reporter_phone']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-size:0.88rem;">ห้อง <?= htmlspecialchars($rr['room_number']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($rr['dorm_name']) ?></small>
                    </td>
                    <td>
                        <span class="badge bg-light text-secondary border" style="font-size:0.78rem;">
                            <i class="bi bi-wrench me-1"></i><?= $rr['item_count'] ?> รายการ
                        </span>
                        <?php if ($rr['image_count'] > 0): ?>
                        <span class="badge bg-light text-secondary border ms-1" style="font-size:0.78rem;">
                            <i class="bi bi-image me-1"></i><?= $rr['image_count'] ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td><?= statusBadge($rr['status']) ?></td>
                    <td style="font-size:0.82rem;color:#94a3b8;white-space:nowrap;">
                        <?= date('d/m/Y', strtotime($rr['created_at'])) ?><br>
                        <span style="font-size:0.78rem;"><?= date('H:i', strtotime($rr['created_at'])) ?> น.</span>
                    </td>
                    <td style="padding-right:20px;">
                        <a href="repair_detail.php?ticket=<?= urlencode($rr['ticket_id']) ?>"
                           class="btn btn-sm btn-outline-primary rounded-pill" style="font-size:0.8rem;white-space:nowrap;">
                            <i class="bi bi-eye me-1"></i>ดูรายละเอียด
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top" style="border-color:#f1f5f9 !important;">
        <small class="text-muted">
            แสดง <?= $offset + 1 ?>–<?= min($offset + $per_page, $total) ?> จากทั้งหมด <?= $total ?> รายการ
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0 gap-1">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link rounded" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php endif; ?>
                <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link rounded" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link rounded" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
