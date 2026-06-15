<?php
require_once 'includes/auth_check.php';
require_once '../connect.php';

// ====================================================================
// AJAX: ดึงรายการห้องที่ใช้อุปกรณ์นี้
// ====================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');

    $itemId        = (int)($_GET['item_id'] ?? 0);
    $filterStatus  = $_GET['status']   ?? '';
    $filterCat     = $_GET['category'] ?? '';

    if (!$itemId) {
        echo json_encode(['rows' => [], 'total_qty' => 0]);
        exit;
    }

    $where  = ['ri.item_master_id = ?'];
    $params = [$itemId];

    if ($filterStatus) {
        $where[]  = 'rr.status = ?';
        $params[] = $filterStatus;
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            rr.ticket_id,
            rr.status    AS req_status,
            ri.quantity,
            ri.status    AS item_status,
            r.room_number,
            d.name       AS dorm_name,
            DATE_FORMAT(rr.created_at, '%d/%m/%Y %H:%i') AS created_at
        FROM repair_items ri
        JOIN repair_requests rr ON ri.request_id  = rr.id
        JOIN rooms r             ON rr.room_id     = r.id
        JOIN dorms d             ON r.dorm_id      = d.id
        $whereSQL
        ORDER BY rr.created_at DESC
    ");
    $stmt->execute($params);
    $rows      = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalQty  = array_sum(array_column($rows, 'quantity'));

    echo json_encode(['rows' => $rows, 'total_qty' => $totalQty]);
    exit;
}

$page_title   = 'วัสดุ / อุปกรณ์ที่ต้องใช้';
$current_page = 'materials';

// ====================================================================
// Filters
// ====================================================================
$filter_category = $_GET['category'] ?? '';   // กรองตามหมวดอุปกรณ์

$categories = ['ประปา', 'ไฟฟ้า', 'ซ่อมสร้าง'];

// ====================================================================
// Query: สรุปรายการอุปกรณ์ที่ยังอยู่ระหว่างดำเนินงาน (ri.status ยังไม่เสร็จ/ยกเลิก)
// ====================================================================
$where  = ["ri.status IN ('รอดำเนินการ','กำลังดำเนินการ')"];
$params = [];

if ($filter_category) {
    $where[]  = 'rim.category = ?';
    $params[] = $filter_category;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$summaryStmt = $pdo->prepare("
    SELECT
        rim.id,
        rim.item_name,
        rim.category,
        COUNT(DISTINCT ri.request_id)                                      AS request_count,
        SUM(ri.quantity)                                                    AS total_qty,
        SUM(CASE WHEN rr.status = 'รอดำเนินการ'    THEN ri.quantity ELSE 0 END) AS qty_pending,
        SUM(CASE WHEN rr.status = 'กำลังดำเนินการ' THEN ri.quantity ELSE 0 END) AS qty_progress,
        SUM(CASE WHEN rr.status = 'เสร็จสิ้น'      THEN ri.quantity ELSE 0 END) AS qty_done,
        GROUP_CONCAT(
            DISTINCT CONCAT(d.name, ' ห้อง ', r.room_number)
            ORDER BY d.name, r.room_number
            SEPARATOR ', '
        ) AS rooms_summary
    FROM repair_items_master rim
    JOIN repair_items ri   ON rim.id         = ri.item_master_id
    JOIN repair_requests rr ON ri.request_id = rr.id
    JOIN rooms r            ON rr.room_id    = r.id
    JOIN dorms d            ON r.dorm_id     = d.id
    $whereSQL
    GROUP BY rim.id
    ORDER BY
        FIELD(rim.category, 'ประปา', 'ไฟฟ้า', 'ซ่อมสร้าง'),
        total_qty DESC
");
$summaryStmt->execute($params);
$summaryItems = $summaryStmt->fetchAll();

// ====================================================================
// Stats header
// ====================================================================
$totalQty   = array_sum(array_column($summaryItems, 'total_qty'));
$totalTypes = count($summaryItems);

$catMeta = [
    'ประปา'    => ['emoji' => '💧', 'color' => '#3b82f6', 'bg' => '#dbeafe', 'icon' => 'bi-droplet-fill'],
    'ไฟฟ้า'   => ['emoji' => '⚡', 'color' => '#f59e0b', 'bg' => '#fef3c7', 'icon' => 'bi-lightning-fill'],
    'ซ่อมสร้าง' => ['emoji' => '🔨', 'color' => '#8b5cf6', 'bg' => '#ede9fe', 'icon' => 'bi-hammer'],
];

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-box-seam-fill me-2" style="color:#06C755;"></i>วัสดุ / อุปกรณ์ที่ต้องใช้</h2>
        <p class="page-desc">รายการอุปกรณ์จากใบงานแจ้งซ่อม — รวม <?= $totalTypes ?> ประเภท / <?= number_format($totalQty) ?> ชิ้นรวม</p>
    </div>
</div>


<!-- Filters -->
<div class="d-flex gap-2 flex-wrap mb-4">
    <a href="materials.php"
       class="cat-btn <?= $filter_category === '' ? 'cat-btn-active' : '' ?>">
        🛠️ ทั้งหมด
    </a>
    <?php foreach ($categories as $c):
        $m = $catMeta[$c];
    ?>
    <a href="materials.php?category=<?= urlencode($c) ?>"
       class="cat-btn <?= $filter_category === $c ? 'cat-btn-active' : '' ?>"
       style="<?= $filter_category === $c ? "--cat-color:{$m['color']};--cat-bg:{$m['bg']};" : '' ?>">
        <?= $m['emoji'] ?> <?= $c ?>
    </a>
    <?php endforeach; ?>
</div>

<style>
.cat-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 18px; border-radius: 20px;
    font-size: 0.88rem; font-weight: 500;
    text-decoration: none; transition: all .18s;
    border: 1.5px solid #e2e8f0;
    background: white; color: #475569;
}
.cat-btn:hover { border-color: #06C755; color: #06C755; background: #f0fdf4; }
.cat-btn-active {
    background: var(--cat-bg, #f0fdf4);
    color: var(--cat-color, #16a34a);
    border-color: var(--cat-color, #16a34a);
    font-weight: 600;
}
</style>

<!-- Main Table -->
<?php if (empty($summaryItems)): ?>
<div class="panel">
    <div class="panel-body text-center py-5 text-muted">
        <i class="bi bi-inbox" style="font-size:2.5rem;display:block;margin-bottom:10px;"></i>
        ไม่พบรายการอุปกรณ์ที่ตรงกับเงื่อนไข
    </div>
</div>
<?php else: ?>

<?php
// จัดกลุ่มตาม category เพื่อแสดงเป็น section
$grouped = [];
foreach ($summaryItems as $item) {
    $grouped[$item['category']][] = $item;
}
?>

<?php foreach ($grouped as $cat => $items):
    $meta    = $catMeta[$cat] ?? ['emoji' => '🛠️', 'color' => '#64748b', 'bg' => '#f1f5f9', 'icon' => 'bi-tools'];
    $catQty  = array_sum(array_column($items, 'total_qty'));
?>
<div class="panel mb-4">
    <!-- Category Header -->
    <div class="panel-header" style="background:<?= $meta['bg'] ?>;">
        <div class="d-flex align-items-center gap-2">
            <div style="width:34px;height:34px;border-radius:9px;background:<?= $meta['color'] ?>22;display:flex;align-items:center;justify-content:center;color:<?= $meta['color'] ?>;">
                <i class="bi <?= $meta['icon'] ?>"></i>
            </div>
            <span style="font-weight:700;font-size:0.95rem;color:#1e293b;"><?= $meta['emoji'] ?> <?= $cat ?></span>
            <span class="badge ms-1" style="background:<?= $meta['color'] ?>22;color:<?= $meta['color'] ?>;font-size:0.75rem;">
                <?= count($items) ?> ประเภท
            </span>
        </div>
        <span style="font-size:0.88rem;font-weight:600;color:<?= $meta['color'] ?>;">
            รวม <?= number_format($catQty) ?> ชิ้น
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-clean mb-0">
            <thead>
                <tr>
                    <th style="padding-left:20px;width:50%;">ชื่ออุปกรณ์</th>
                    <th class="text-center">จำนวนรวม</th>
                    <th class="text-center">ใบงาน</th>
                    <th style="padding-right:20px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td style="padding-left:20px;">
                        <div style="font-weight:600;font-size:0.92rem;color:#1e293b;">
                            <?= htmlspecialchars($item['item_name']) ?>
                        </div>
                        <!-- ห้องที่ใช้ (preview สั้น) -->
                        <?php if ($item['rooms_summary']): ?>
                        <div style="font-size:0.75rem;color:#94a3b8;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:280px;"
                             title="<?= htmlspecialchars($item['rooms_summary']) ?>">
                            <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($item['rooms_summary']) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <span style="font-size:1.2rem;font-weight:700;color:#1e293b;"><?= number_format($item['total_qty']) ?></span>
                        <div style="font-size:0.7rem;color:#94a3b8;">ชิ้น</div>
                    </td>
                    <td class="text-center">
                        <span style="font-size:0.88rem;font-weight:500;color:#475569;"><?= $item['request_count'] ?> ใบ</span>
                    </td>
                    <td style="padding-right:20px;">
                        <button class="btn btn-sm"
                                style="background:#06C755;color:white;border:none;border-radius:8px;font-size:0.78rem;white-space:nowrap;"
                                onclick="showRooms(<?= $item['id'] ?>, '<?= htmlspecialchars($item['item_name'], ENT_QUOTES) ?>')">
                            <i class="bi bi-geo-alt-fill me-1"></i>ดูห้อง
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <!-- Category subtotal row -->
            <tfoot>
                <tr style="background:#f8fafc;">
                    <td style="padding-left:20px;font-weight:600;font-size:0.85rem;color:#475569;">รวม <?= $cat ?></td>
                    <td class="text-center" style="font-weight:700;color:#1e293b;"><?= number_format($catQty) ?></td>
                    <td class="text-center" style="font-weight:600;color:#475569;"><?= array_sum(array_column($items, 'request_count')) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- ====================================================================
     Modal: รายละเอียดห้องที่ใช้อุปกรณ์
===================================================================== -->
<div class="modal fade" id="roomsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header border-0 pb-2">
                <div>
                    <h6 class="modal-title fw-bold mb-0" id="modalItemTitle">—</h6>
                    <div style="font-size:0.8rem;color:#94a3b8;" id="modalItemSub">รายการห้องพักที่แจ้งซ่อมอุปกรณ์นี้</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <!-- Loader -->
                <div id="modalLoader" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-success me-2"></div>
                    <span style="font-size:0.88rem;color:#64748b;">กำลังโหลด...</span>
                </div>
                <!-- Table -->
                <div id="modalTable" style="display:none;">
                    <table class="table table-clean mb-0">
                        <thead>
                            <tr>
                                <th style="padding-left:4px;">#</th>
                                <th>Ticket ID</th>
                                <th>หอพัก</th>
                                <th>ห้อง</th>
                                <th class="text-center">จำนวน</th>
                                <th>สถานะใบงาน</th>
                                <th>สถานะอุปกรณ์</th>
                                <th>วันที่แจ้ง</th>
                            </tr>
                        </thead>
                        <tbody id="modalTbody"></tbody>
                    </table>
                </div>
                <div id="modalEmpty" style="display:none;" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox" style="font-size:1.8rem;display:block;margin-bottom:6px;"></i>
                    ไม่พบข้อมูล
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$filterStatus   = json_encode($filter_status);
$filterCategory = json_encode($filter_category);
$extra_scripts  = <<<'JS'
<script>
const statusBadgeClass = {
    'รอดำเนินการ':    'badge-pending',
    'กำลังดำเนินการ': 'badge-progress',
    'เสร็จสิ้น':      'badge-completed',
    'ยกเลิก':         'badge-cancelled',
};

function showRooms(itemId, itemName) {
    document.getElementById('modalItemTitle').textContent = itemName;
    document.getElementById('modalItemSub').textContent = 'รายการห้องพักที่แจ้งซ่อมอุปกรณ์นี้';
    document.getElementById('modalLoader').style.display = 'block';
    document.getElementById('modalTable').style.display  = 'none';
    document.getElementById('modalEmpty').style.display  = 'none';

    const modal = new bootstrap.Modal(document.getElementById('roomsModal'));
    modal.show();

    // Build URL with current filters
    const params = new URLSearchParams(window.location.search);
    params.set('ajax', '1');
    params.set('item_id', itemId);
    const url = 'materials.php?' + params.toString();

    fetch(url)
        .then(r => r.json())
        .then(data => {
            document.getElementById('modalLoader').style.display = 'none';
            if (!data.rows || data.rows.length === 0) {
                document.getElementById('modalEmpty').style.display = 'block';
                return;
            }
            let html = '';
            data.rows.forEach((row, i) => {
                const reqCls  = statusBadgeClass[row.req_status]  || 'badge-pending';
                const itemCls = statusBadgeClass[row.item_status] || 'badge-pending';
                html += `<tr>
                    <td style="padding-left:4px;color:#94a3b8;font-size:0.8rem;">${i + 1}</td>
                    <td>
                        <a href="repair_detail.php?ticket=${row.ticket_id}"
                           style="font-weight:600;font-size:0.85rem;color:#06C755;text-decoration:none;">
                           ${row.ticket_id}
                        </a>
                    </td>
                    <td style="font-size:0.85rem;">${row.dorm_name}</td>
                    <td style="font-weight:600;font-size:0.9rem;font-family:monospace;">${row.room_number}</td>
                    <td class="text-center" style="font-weight:700;font-size:1rem;">${row.quantity}</td>
                    <td><span class="badge-status ${reqCls}" style="font-size:0.75rem;">${row.req_status}</span></td>
                    <td><span class="badge-status ${itemCls}" style="font-size:0.75rem;">${row.item_status}</span></td>
                    <td style="font-size:0.78rem;color:#94a3b8;white-space:nowrap;">${row.created_at}</td>
                </tr>`;
            });
            document.getElementById('modalTbody').innerHTML = html;
            document.getElementById('modalTable').style.display = 'block';

            // Update subtitle with count
            document.getElementById('modalItemSub').textContent =
                `พบ ${data.rows.length} ใบงาน · รวม ${data.total_qty} ชิ้น`;
        })
        .catch(() => {
            document.getElementById('modalLoader').style.display = 'none';
            document.getElementById('modalEmpty').style.display = 'block';
            document.getElementById('modalEmpty').innerHTML = '<i class="bi bi-exclamation-circle text-danger" style="font-size:1.5rem;display:block;margin-bottom:6px;"></i>เกิดข้อผิดพลาด';
        });
}
</script>
JS;

include 'includes/footer.php';
?>
