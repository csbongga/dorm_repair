<?php
require_once 'includes/auth_check.php';
require_once '../connect.php';

$page_title   = 'ห้องที่รอดำเนินการ';
$current_page = 'pending';

// ====================================================================
// Filter
// ====================================================================
$filter_category = $_GET['category'] ?? '';   // ประปา | ไฟฟ้า | ซ่อมสร้าง | ''
$filter_dorm     = (int)($_GET['dorm_id'] ?? 0);

$categories = ['ประปา', 'ไฟฟ้า', 'ซ่อมสร้าง'];
$catMeta = [
    'ประปา'    => ['emoji' => '💧', 'color' => '#3b82f6', 'bg' => '#dbeafe', 'icon' => 'bi-droplet-fill'],
    'ไฟฟ้า'   => ['emoji' => '⚡', 'color' => '#f59e0b', 'bg' => '#fef3c7', 'icon' => 'bi-lightning-fill'],
    'ซ่อมสร้าง' => ['emoji' => '🔨', 'color' => '#8b5cf6', 'bg' => '#ede9fe', 'icon' => 'bi-hammer'],
];

// ====================================================================
// Query: ดึงรายการอุปกรณ์ที่ยังรออยู่ทุกห้อง
// ====================================================================
$where  = [
    "rr.status = 'รอดำเนินการ'",
    "ri.status = 'รอดำเนินการ'",
];
$params = [];

if ($filter_category) {
    $where[]  = 'rim.category = ?';
    $params[] = $filter_category;
}
if ($filter_dorm) {
    $where[]  = 'd.id = ?';
    $params[] = $filter_dorm;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$rowsStmt = $pdo->prepare("
    SELECT
        d.id          AS dorm_id,
        d.name        AS dorm_name,
        r.id          AS room_id,
        r.room_number,
        rr.id         AS request_id,
        rr.ticket_id,
        rr.reporter_name,
        rr.additional_details,
        rr.created_at,
        rim.id        AS item_id,
        rim.item_name,
        rim.category,
        ri.quantity
    FROM repair_requests rr
    JOIN rooms r                ON rr.room_id         = r.id
    JOIN dorms d                ON r.dorm_id           = d.id
    JOIN repair_items ri        ON rr.id              = ri.request_id
    JOIN repair_items_master rim ON ri.item_master_id = rim.id
    $whereSQL
    ORDER BY d.name ASC, r.room_number ASC, rr.created_at ASC, rim.category ASC
");
$rowsStmt->execute($params);
$rawRows = $rowsStmt->fetchAll();

// ====================================================================
// จัดกลุ่ม: room → tickets → items
// ====================================================================
$roomMap = [];   // key = dorm_id|room_number

foreach ($rawRows as $row) {
    $roomKey   = $row['dorm_id'] . '|' . $row['room_number'];
    $ticketKey = $row['ticket_id'];

    if (!isset($roomMap[$roomKey])) {
        $roomMap[$roomKey] = [
            'dorm_name'   => $row['dorm_name'],
            'room_number' => $row['room_number'],
            'room_id'     => $row['room_id'],
            'tickets'     => [],
        ];
    }

    if (!isset($roomMap[$roomKey]['tickets'][$ticketKey])) {
        $roomMap[$roomKey]['tickets'][$ticketKey] = [
            'ticket_id'          => $row['ticket_id'],
            'reporter_name'      => $row['reporter_name'],
            'additional_details' => $row['additional_details'],
            'created_at'         => $row['created_at'],
            'items'              => [],
        ];
    }

    $roomMap[$roomKey]['tickets'][$ticketKey]['items'][] = [
        'item_name' => $row['item_name'],
        'category'  => $row['category'],
        'quantity'  => $row['quantity'],
    ];
}

// ====================================================================
// Stats
// ====================================================================
$totalRooms   = count($roomMap);
$totalTickets = count($rawRows ? array_unique(array_column($rawRows, 'ticket_id')) : []);
$totalItems   = array_sum(array_column($rawRows, 'quantity'));

// จำนวนแต่ละ category
$catCount = array_fill_keys($categories, 0);
foreach ($rawRows as $r) {
    if (isset($catCount[$r['category']])) $catCount[$r['category']] += $r['quantity'];
}

// ดึง dorms ทั้งหมดสำหรับ filter
$dorms = $pdo->query("SELECT id, name FROM dorms ORDER BY name")->fetchAll();

include 'includes/header.php';
?>

<style>
.room-card {
    background: white;
    border-radius: 16px;
    border: 1px solid #f1f5f9;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
    transition: box-shadow 0.2s, transform 0.2s;
}

.room-card:hover {
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}

.room-card-header {
    padding: 14px 16px 10px 16px;
    border-bottom: 1px solid #f1f5f9;
    background: #f8fafc;
}

.room-card-body {
    padding: 12px 16px;
    flex: 1;
}

.ticket-block {
    padding: 10px 12px;
    border-radius: 10px;
    background: #fafafa;
    border: 1px solid #f1f5f9;
    margin-bottom: 8px;
}

.ticket-block:last-child { margin-bottom: 0; }

.item-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 0;
    border-bottom: 1px dashed #f1f5f9;
    font-size: 0.85rem;
}

.item-row:last-child { border-bottom: none; }

.item-emoji {
    font-size: 0.9rem;
    width: 18px;
    text-align: center;
    flex-shrink: 0;
}

.item-name {
    flex: 1;
    color: #334155;
    font-weight: 500;
}

.item-qty {
    font-weight: 700;
    color: #06C755;
    font-size: 0.82rem;
    background: #f0fdf4;
    padding: 1px 7px;
    border-radius: 6px;
    flex-shrink: 0;
}

.cat-filter-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    border: 1.5px solid #e2e8f0;
    background: white;
    color: #475569;
    text-decoration: none;
    transition: all 0.15s;
    cursor: pointer;
}

.cat-filter-btn:hover {
    border-color: #06C755;
    color: #06C755;
}

.cat-filter-btn.active {
    border-color: var(--active-color, #06C755);
    background: var(--active-color, #06C755);
    color: white;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 20px;
    border: 1px solid #f1f5f9;
}
</style>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h2>
            <i class="bi bi-exclamation-circle-fill me-2" style="color:#f59e0b;"></i>
            ห้องที่รอดำเนินการ
        </h2>
        <p class="page-desc">
            <?= $totalRooms ?> ห้อง &nbsp;·&nbsp;
            <?= $totalTickets ?> ใบงาน &nbsp;·&nbsp;
            <?= number_format($totalItems) ?> รายการที่ยังไม่ดำเนินการ
        </p>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef3c7;color:#f59e0b;">
                <i class="bi bi-door-closed-fill"></i>
            </div>
            <div class="stat-value text-warning"><?= $totalRooms ?></div>
            <div class="stat-label">ห้องที่รอ</div>
        </div>
    </div>
    <?php foreach ($categories as $cat):
        $m   = $catMeta[$cat];
        $qty = $catCount[$cat];
    ?>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="cursor:pointer;" onclick="window.location.href='?category=<?= urlencode($cat) ?><?= $filter_dorm ? '&dorm_id='.$filter_dorm : '' ?>'">
            <div class="stat-icon" style="background:<?= $m['bg'] ?>;color:<?= $m['color'] ?>;">
                <i class="bi <?= $m['icon'] ?>"></i>
            </div>
            <div class="stat-value" style="color:<?= $m['color'] ?>;"><?= number_format($qty) ?></div>
            <div class="stat-label"><?= $m['emoji'] ?> <?= $cat ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter Bar -->
<div class="panel mb-4">
    <div class="panel-body py-3">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <!-- Category filter -->
            <span style="font-size:0.82rem;font-weight:500;color:#64748b;white-space:nowrap;">ประเภทอุปกรณ์:</span>

            <a href="?<?= $filter_dorm ? 'dorm_id='.$filter_dorm.'&' : '' ?>"
               class="cat-filter-btn <?= !$filter_category ? 'active' : '' ?>"
               style="--active-color:#06C755;">
                🔧 ทั้งหมด
                <span style="font-size:0.75rem;opacity:0.8;">(<?= number_format($totalItems) ?>)</span>
            </a>

            <?php foreach ($categories as $cat):
                $m = $catMeta[$cat];
            ?>
            <a href="?category=<?= urlencode($cat) ?><?= $filter_dorm ? '&dorm_id='.$filter_dorm : '' ?>"
               class="cat-filter-btn <?= $filter_category === $cat ? 'active' : '' ?>"
               style="--active-color:<?= $m['color'] ?>;">
                <?= $m['emoji'] ?> <?= $cat ?>
                <span style="font-size:0.75rem;opacity:0.8;">(<?= number_format($catCount[$cat]) ?>)</span>
            </a>
            <?php endforeach; ?>

            <!-- Dorm filter -->
            <div class="ms-auto">
                <select class="form-select form-select-sm"
                        style="min-width:160px;"
                        onchange="
                            const params = new URLSearchParams(window.location.search);
                            if (this.value) params.set('dorm_id', this.value);
                            else params.delete('dorm_id');
                            window.location.href = '?' + params.toString();
                        ">
                    <option value="" <?= !$filter_dorm ? 'selected' : '' ?>>ทุกหอพัก</option>
                    <?php foreach ($dorms as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $filter_dorm == $d['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Room Cards Grid -->
<?php if (empty($roomMap)): ?>
<div class="empty-state">
    <i class="bi bi-check-circle-fill" style="font-size:3.5rem;color:#06C755;display:block;margin-bottom:14px;"></i>
    <h4 style="font-weight:700;color:#1e293b;margin-bottom:6px;">
        <?= $filter_category ? 'ไม่มีอุปกรณ์ ' . $filter_category . ' ที่รอดำเนินการ' : 'ไม่มีห้องที่รอดำเนินการ' ?>
    </h4>
    <p class="text-muted" style="font-size:0.9rem;">
        ใบงานทุกรายการได้รับการดำเนินการแล้ว
    </p>
    <?php if ($filter_category || $filter_dorm): ?>
    <a href="pending.php" class="btn btn-sm btn-outline-secondary mt-2">
        <i class="bi bi-x-circle me-1"></i>ล้างตัวกรอง
    </a>
    <?php endif; ?>
</div>
<?php else: ?>

<div class="row g-3">
    <?php foreach ($roomMap as $roomData): ?>
    <?php
    // นับรวมอุปกรณ์ทั้งหมดในห้องนี้
    $roomTotalItems = 0;
    $roomCategories = [];
    foreach ($roomData['tickets'] as $t) {
        foreach ($t['items'] as $it) {
            $roomTotalItems += $it['quantity'];
            $roomCategories[$it['category']] = true;
        }
    }
    $ticketCount = count($roomData['tickets']);
    ?>
    <div class="col-12 col-md-6 col-lg-4">
        <div class="room-card">
            <!-- Card Header: ข้อมูลห้อง -->
            <div class="room-card-header">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div>
                        <div style="font-size:1.05rem;font-weight:700;color:#1e293b;">
                            ห้อง <span style="font-family:monospace;"><?= htmlspecialchars($roomData['room_number']) ?></span>
                        </div>
                        <div style="font-size:0.8rem;color:#64748b;">
                            <?= htmlspecialchars($roomData['dorm_name']) ?>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0">
                        <div style="font-size:1.3rem;font-weight:700;color:#f59e0b;line-height:1;"><?= $roomTotalItems ?></div>
                        <div style="font-size:0.7rem;color:#94a3b8;">รายการรอ</div>
                    </div>
                </div>
                <!-- Category badges -->
                <div class="d-flex gap-1 mt-2 flex-wrap">
                    <?php foreach (array_keys($roomCategories) as $rc):
                        $m = $catMeta[$rc] ?? null;
                        if (!$m) continue;
                    ?>
                    <span style="font-size:0.68rem;background:<?= $m['bg'] ?>;color:<?= $m['color'] ?>;padding:2px 7px;border-radius:6px;font-weight:500;">
                        <?= $m['emoji'] ?> <?= $rc ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Card Body: ใบงานและรายการอุปกรณ์ -->
            <div class="room-card-body">
                <?php foreach ($roomData['tickets'] as $ticket): ?>
                <div class="ticket-block">
                    <!-- Ticket header -->
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <a href="repair_detail.php?ticket=<?= urlencode($ticket['ticket_id']) ?>"
                           style="font-weight:700;font-size:0.82rem;color:#06C755;text-decoration:none;">
                            <i class="bi bi-receipt me-1"></i><?= htmlspecialchars($ticket['ticket_id']) ?>
                        </a>
                        <span style="font-size:0.72rem;color:#94a3b8;">
                            <?= date('d/m/Y', strtotime($ticket['created_at'])) ?>
                        </span>
                    </div>

                    <?php if ($ticket['additional_details']): ?>
                    <div style="font-size:0.75rem;color:#64748b;margin-bottom:8px;padding:5px 8px;background:#f8fafc;border-radius:6px;border-left:2px solid #e2e8f0;line-height:1.4;">
                        <?= htmlspecialchars(mb_substr($ticket['additional_details'], 0, 70)) ?>
                        <?= mb_strlen($ticket['additional_details']) > 70 ? '...' : '' ?>
                    </div>
                    <?php endif; ?>

                    <!-- Items -->
                    <?php foreach ($ticket['items'] as $item):
                        $m = $catMeta[$item['category']] ?? ['emoji' => '🛠️'];
                    ?>
                    <div class="item-row">
                        <span class="item-emoji"><?= $m['emoji'] ?></span>
                        <span class="item-name"><?= htmlspecialchars($item['item_name']) ?></span>
                        <span class="item-qty">×<?= $item['quantity'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Card Footer -->
            <div style="padding:10px 16px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:0.75rem;color:#94a3b8;">
                    <?= $ticketCount ?> ใบงาน
                </span>
                <?php if ($ticketCount === 1): ?>
                <?php $onlyTicket = reset($roomData['tickets']); ?>
                <a href="repair_detail.php?ticket=<?= urlencode($onlyTicket['ticket_id']) ?>"
                   class="btn btn-sm" style="background:#06C755;color:white;border:none;border-radius:8px;font-size:0.78rem;padding:4px 12px;">
                    <i class="bi bi-arrow-right me-1"></i>ดูใบงาน
                </a>
                <?php else: ?>
                <a href="repairs.php?q=<?= urlencode($roomData['room_number']) ?>"
                   class="btn btn-sm" style="background:#06C755;color:white;border:none;border-radius:8px;font-size:0.78rem;padding:4px 12px;">
                    <i class="bi bi-arrow-right me-1"></i>ดูทั้งหมด
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Summary footer -->
<div class="mt-4 text-center" style="font-size:0.82rem;color:#94a3b8;">
    แสดง <?= $totalRooms ?> ห้อง / <?= $totalTickets ?> ใบงาน / <?= number_format($totalItems) ?> รายการที่รอดำเนินการ
    <?php if ($filter_category): ?>
    — กรองเฉพาะ <?= $catMeta[$filter_category]['emoji'] ?> <?= $filter_category ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
