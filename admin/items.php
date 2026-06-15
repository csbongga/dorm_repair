<?php
require_once 'includes/auth_check.php';
require_once '../connect.php';

$page_title   = 'จัดการรายการอุปกรณ์';
$current_page = 'items';

$msg     = '';
$msgType = '';

// ====================================================================
// POST Handlers
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_item') {
        $category         = $_POST['category'] ?? '';
        $item_name        = trim($_POST['item_name'] ?? '');
        $require_quantity = isset($_POST['require_quantity']) ? 1 : 0;
        $allowed_cats     = ['ประปา', 'ไฟฟ้า', 'ซ่อมสร้าง'];

        if ($item_name && in_array($category, $allowed_cats)) {
            $dup = $pdo->prepare("SELECT COUNT(*) FROM repair_items_master WHERE category=? AND item_name=?");
            $dup->execute([$category, $item_name]);
            if ($dup->fetchColumn() > 0) {
                $msg = "รายการ '{$item_name}' มีอยู่ในหมวด {$category} แล้ว";
                $msgType = 'warning';
            } else {
                $pdo->prepare("INSERT INTO repair_items_master (category, item_name, require_quantity, is_active) VALUES (?,?,?,1)")
                    ->execute([$category, $item_name, $require_quantity]);
                $msg = "เพิ่ม '{$item_name}' ในหมวด {$category} เรียบร้อยแล้ว";
                $msgType = 'success';
            }
        } else {
            $msg = 'กรุณากรอกชื่ออุปกรณ์และเลือกหมวดหมู่'; $msgType = 'danger';
        }
    }

    if ($action === 'edit_item') {
        $id               = (int)$_POST['item_id'];
        $category         = $_POST['category'] ?? '';
        $item_name        = trim($_POST['item_name'] ?? '');
        $require_quantity = isset($_POST['require_quantity']) ? 1 : 0;
        $allowed_cats     = ['ประปา', 'ไฟฟ้า', 'ซ่อมสร้าง'];

        if ($id && $item_name && in_array($category, $allowed_cats)) {
            $pdo->prepare("UPDATE repair_items_master SET category=?, item_name=?, require_quantity=? WHERE id=?")
                ->execute([$category, $item_name, $require_quantity, $id]);
            $msg = "อัปเดต '{$item_name}' เรียบร้อยแล้ว"; $msgType = 'success';
        }
    }

    if ($action === 'toggle_active') {
        $id        = (int)$_POST['item_id'];
        $newActive = (int)$_POST['new_active'];
        if ($id) {
            $pdo->prepare("UPDATE repair_items_master SET is_active=? WHERE id=?")->execute([$newActive, $id]);
            $msg = $newActive ? 'เปิดใช้งานรายการแล้ว' : 'ซ่อนรายการจากฟอร์มแจ้งซ่อมแล้ว';
            $msgType = 'success';
        }
    }

    if ($action === 'delete_item') {
        $id = (int)$_POST['item_id'];
        if ($id) {
            $used = $pdo->prepare("SELECT COUNT(*) FROM repair_items WHERE item_master_id=?");
            $used->execute([$id]);
            if ($used->fetchColumn() > 0) {
                $msg = 'ไม่สามารถลบได้ เนื่องจากรายการนี้ถูกใช้ในใบงานแจ้งซ่อมแล้ว (แนะนำให้ซ่อนแทน)';
                $msgType = 'danger';
            } else {
                $pdo->prepare("DELETE FROM repair_items_master WHERE id=?")->execute([$id]);
                $msg = 'ลบรายการเรียบร้อยแล้ว'; $msgType = 'warning';
            }
        }
    }

    header('Location: items.php?msg=' . urlencode($msg) . '&msg_type=' . urlencode($msgType));
    exit;
}

if (empty($msg) && isset($_GET['msg'])) {
    $msg     = $_GET['msg'];
    $msgType = $_GET['msg_type'] ?? 'info';
}

// ====================================================================
// ดึงข้อมูล — จัดกลุ่มตามหมวด พร้อมนับการใช้งาน
// ====================================================================
$itemsStmt = $pdo->query("
    SELECT m.*,
           COUNT(ri.id) AS used_count
    FROM repair_items_master m
    LEFT JOIN repair_items ri ON m.id = ri.item_master_id
    GROUP BY m.id
    ORDER BY m.category ASC, m.is_active DESC, m.item_name ASC
");
$allItems = $itemsStmt->fetchAll();

$categories = ['ประปา', 'ไฟฟ้า', 'ซ่อมสร้าง'];
$grouped    = array_fill_keys($categories, []);
foreach ($allItems as $item) {
    if (isset($grouped[$item['category']])) {
        $grouped[$item['category']][] = $item;
    }
}

$catMeta = [
    'ประปา'    => ['emoji' => '💧', 'color' => '#3b82f6', 'bg' => '#dbeafe', 'icon' => 'bi-droplet-fill'],
    'ไฟฟ้า'   => ['emoji' => '⚡', 'color' => '#f59e0b', 'bg' => '#fef3c7', 'icon' => 'bi-lightning-fill'],
    'ซ่อมสร้าง' => ['emoji' => '🔨', 'color' => '#8b5cf6', 'bg' => '#ede9fe', 'icon' => 'bi-hammer'],
];

$totalActive   = count(array_filter($allItems, fn($i) => $i['is_active']));
$totalInactive = count($allItems) - $totalActive;

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-tools me-2" style="color:#06C755;"></i>รายการอุปกรณ์แจ้งซ่อม</h2>
        <p class="page-desc">
            ทั้งหมด <?= count($allItems) ?> รายการ &nbsp;·&nbsp;
            <span style="color:#06C755;">ใช้งาน <?= $totalActive ?></span> &nbsp;·&nbsp;
            <span style="color:#94a3b8;">ซ่อน <?= $totalInactive ?></span>
        </p>
    </div>
    <button class="btn btn-sm" style="background:#06C755;color:white;border:none;border-radius:10px;padding:8px 18px;"
            onclick="openAddModal()">
        <i class="bi bi-plus-lg me-1"></i>เพิ่มรายการใหม่
    </button>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msgType) ?> rounded-3 d-flex align-items-center gap-2 mb-4" style="font-size:0.9rem;">
    <i class="bi bi-<?= $msgType === 'success' ? 'check-circle-fill' : ($msgType === 'danger' ? 'x-circle-fill' : 'info-circle-fill') ?>"></i>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- หมวดหมู่ทีละแผง -->
<div class="row g-4">
    <?php foreach ($categories as $cat):
        $meta  = $catMeta[$cat];
        $items = $grouped[$cat];
        $activeCount = count(array_filter($items, fn($i) => $i['is_active']));
    ?>
    <div class="col-12 col-lg-4">
        <div class="panel mb-0 h-100">
            <!-- Category Header -->
            <div class="panel-header" style="background:<?= $meta['bg'] ?>;">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:36px;height:36px;border-radius:10px;background:<?= $meta['color'] ?>20;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:<?= $meta['color'] ?>;">
                        <i class="bi <?= $meta['icon'] ?>"></i>
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:0.95rem;color:#1e293b;"><?= $meta['emoji'] ?> <?= $cat ?></div>
                        <div style="font-size:0.72rem;color:#64748b;"><?= $activeCount ?> / <?= count($items) ?> รายการ</div>
                    </div>
                </div>
                <button class="btn btn-sm" style="background:<?= $meta['color'] ?>;color:white;border:none;border-radius:8px;font-size:0.78rem;padding:5px 10px;"
                        onclick="openAddModal('<?= $cat ?>')">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>

            <!-- Items List -->
            <div style="max-height:480px;overflow-y:auto;">
                <?php if (empty($items)): ?>
                <div class="text-center text-muted py-5" style="font-size:0.85rem;">
                    <i class="bi bi-inbox" style="font-size:1.5rem;display:block;margin-bottom:6px;"></i>
                    ยังไม่มีรายการในหมวดนี้
                </div>
                <?php else: ?>
                <?php foreach ($items as $item): ?>
                <div class="d-flex align-items-center gap-3 px-4 py-3 border-bottom item-row"
                     style="border-color:#f1f5f9 !important;opacity:<?= $item['is_active'] ? 1 : 0.5 ?>;">

                    <!-- Active toggle dot -->
                    <div style="width:8px;height:8px;border-radius:50%;background:<?= $item['is_active'] ? '#06C755' : '#cbd5e1' ?>;flex-shrink:0;"></div>

                    <div class="flex-fill" style="min-width:0;">
                        <div style="font-size:0.9rem;font-weight:500;color:<?= $item['is_active'] ? '#1e293b' : '#94a3b8' ?>;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?= htmlspecialchars($item['item_name']) ?>
                        </div>
                        <div class="d-flex gap-2 mt-1 flex-wrap">
                            <?php if ($item['require_quantity']): ?>
                            <span style="font-size:0.68rem;background:#fef3c7;color:#d97706;padding:1px 6px;border-radius:4px;font-weight:500;">
                                ระบุจำนวน
                            </span>
                            <?php endif; ?>
                            <?php if ($item['used_count'] > 0): ?>
                            <span style="font-size:0.68rem;background:#f1f5f9;color:#64748b;padding:1px 6px;border-radius:4px;">
                                ใช้แล้ว <?= $item['used_count'] ?> ครั้ง
                            </span>
                            <?php endif; ?>
                            <?php if (!$item['is_active']): ?>
                            <span style="font-size:0.68rem;background:#f1f5f9;color:#94a3b8;padding:1px 6px;border-radius:4px;">
                                ซ่อนอยู่
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex gap-1 flex-shrink-0">
                        <!-- Toggle active -->
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                            <input type="hidden" name="new_active" value="<?= $item['is_active'] ? 0 : 1 ?>">
                            <button type="submit" class="btn btn-sm"
                                    style="width:30px;height:30px;padding:0;border-radius:8px;border:none;background:<?= $item['is_active'] ? '#f0fdf4' : '#f8fafc' ?>;color:<?= $item['is_active'] ? '#06C755' : '#94a3b8' ?>;"
                                    title="<?= $item['is_active'] ? 'ซ่อนจากฟอร์ม' : 'เปิดใช้งาน' ?>">
                                <i class="bi bi-<?= $item['is_active'] ? 'eye-fill' : 'eye-slash' ?>" style="font-size:0.75rem;"></i>
                            </button>
                        </form>

                        <!-- Edit -->
                        <button class="btn btn-sm"
                                style="width:30px;height:30px;padding:0;border-radius:8px;border:none;background:#dbeafe;color:#1d4ed8;"
                                onclick="openEditModal(<?= htmlspecialchars(json_encode($item)) ?>)"
                                title="แก้ไข">
                            <i class="bi bi-pencil-fill" style="font-size:0.75rem;"></i>
                        </button>

                        <!-- Delete -->
                        <form method="POST" style="margin:0;" onsubmit="return confirmDelete(event, '<?= htmlspecialchars($item['item_name'], ENT_QUOTES) ?>', <?= $item['used_count'] ?>)">
                            <input type="hidden" name="action" value="delete_item">
                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                            <button type="submit" class="btn btn-sm"
                                    style="width:30px;height:30px;padding:0;border-radius:8px;border:none;background:<?= $item['used_count'] > 0 ? '#f8fafc' : '#fee2e2' ?>;color:<?= $item['used_count'] > 0 ? '#cbd5e1' : '#dc2626' ?>;"
                                    title="<?= $item['used_count'] > 0 ? 'ไม่สามารถลบได้ (ถูกใช้ในใบงาน)' : 'ลบ' ?>"
                                    <?= $item['used_count'] > 0 ? 'disabled' : '' ?>>
                                <i class="bi bi-trash-fill" style="font-size:0.75rem;"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ====================================================================
     Modal: เพิ่มรายการ
===================================================================== -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-plus-circle-fill me-2" style="color:#06C755;"></i>เพิ่มรายการอุปกรณ์
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_item">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;font-weight:500;">หมวดหมู่ <span class="text-danger">*</span></label>
                        <div class="d-flex gap-2">
                            <?php foreach ($categories as $cat):
                                $m = $catMeta[$cat];
                            ?>
                            <label class="flex-fill text-center py-2 px-1 rounded-3"
                                   style="cursor:pointer;border:2px solid #e2e8f0;font-size:0.8rem;font-weight:500;transition:all 0.15s;"
                                   id="catLabel_<?= $cat ?>">
                                <input type="radio" name="category" value="<?= $cat ?>"
                                       class="d-none cat-radio" required
                                       onchange="selectCat('<?= $cat ?>')">
                                <div style="font-size:1.1rem;"><?= $m['emoji'] ?></div>
                                <?= $cat ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;font-weight:500;">ชื่ออุปกรณ์ <span class="text-danger">*</span></label>
                        <input type="text" name="item_name" id="addItemName" class="form-control form-control-sm" required
                               placeholder="เช่น ก๊อกน้ำ, หลอดไฟ LED, ลูกบิดประตู">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="require_quantity" id="addRequireQty">
                        <label class="form-check-label" for="addRequireQty" style="font-size:0.85rem;">
                            ให้ผู้แจ้งระบุจำนวน
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-sm" style="background:#06C755;color:white;border:none;">
                        <i class="bi bi-plus-lg me-1"></i>เพิ่ม
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====================================================================
     Modal: แก้ไขรายการ
===================================================================== -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold">แก้ไขรายการอุปกรณ์</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_item">
                <input type="hidden" name="item_id" id="editItemId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;font-weight:500;">หมวดหมู่ <span class="text-danger">*</span></label>
                        <select name="category" id="editCategory" class="form-select form-select-sm" required>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>"><?= $catMeta[$cat]['emoji'] ?> <?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;font-weight:500;">ชื่ออุปกรณ์ <span class="text-danger">*</span></label>
                        <input type="text" name="item_name" id="editItemName" class="form-control form-control-sm" required>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="require_quantity" id="editRequireQty">
                        <label class="form-check-label" for="editRequireQty" style="font-size:0.85rem;">
                            ให้ผู้แจ้งระบุจำนวน
                        </label>
                    </div>

                    <div id="editUsedWarning" class="mt-3 p-2 rounded-3 d-none"
                         style="background:#fef3c7;border:1px solid #fde68a;font-size:0.8rem;color:#92400e;">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        รายการนี้ถูกใช้ในใบงานแล้ว การแก้ไขจะมีผลกับชื่อที่แสดงในระบบ
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-sm" style="background:#06C755;color:white;border:none;">
                        <i class="bi bi-save me-1"></i>บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extra_scripts = <<<'JS'
<script>
// ===== Add Modal =====
function openAddModal(presetCat) {
    // reset form
    document.querySelectorAll('.cat-radio').forEach(r => r.checked = false);
    document.querySelectorAll('[id^=\"catLabel_\"]').forEach(el => {
        el.style.borderColor = '#e2e8f0';
        el.style.background  = 'white';
        el.style.color       = '#334155';
    });
    document.getElementById('addItemName').value = '';
    document.getElementById('addRequireQty').checked = false;

    if (presetCat) {
        const radio = document.querySelector('.cat-radio[value=\"' + presetCat + '\"]');
        if (radio) { radio.checked = true; selectCat(presetCat); }
    }
    new bootstrap.Modal(document.getElementById('addModal')).show();
    setTimeout(() => document.getElementById('addItemName').focus(), 400);
}

const catColors = {
    'ประปา':    '#3b82f6',
    'ไฟฟ้า':   '#f59e0b',
    'ซ่อมสร้าง': '#8b5cf6'
};

function selectCat(cat) {
    document.querySelectorAll('[id^=\"catLabel_\"]').forEach(el => {
        el.style.borderColor = '#e2e8f0';
        el.style.background  = 'white';
        el.style.color       = '#334155';
        el.style.fontWeight  = '500';
    });
    const lbl = document.getElementById('catLabel_' + cat);
    if (lbl) {
        const c = catColors[cat] || '#06C755';
        lbl.style.borderColor = c;
        lbl.style.background  = c + '12';
        lbl.style.color       = c;
        lbl.style.fontWeight  = '700';
    }
}

// ===== Edit Modal =====
function openEditModal(item) {
    document.getElementById('editItemId').value   = item.id;
    document.getElementById('editCategory').value = item.category;
    document.getElementById('editItemName').value = item.item_name;
    document.getElementById('editRequireQty').checked = item.require_quantity == 1;

    const warn = document.getElementById('editUsedWarning');
    if (item.used_count > 0) warn.classList.remove('d-none');
    else warn.classList.add('d-none');

    new bootstrap.Modal(document.getElementById('editModal')).show();
    setTimeout(() => document.getElementById('editItemName').focus(), 400);
}

// ===== Delete confirm =====
function confirmDelete(e, name, usedCount) {
    e.preventDefault();
    if (usedCount > 0) return false;
    Swal.fire({
        title: 'ลบรายการ?',
        html: `ต้องการลบ <strong>'${name}'</strong> ออกจากระบบ?<br><small class='text-muted'>รายการที่ยังไม่เคยถูกใช้งานเท่านั้นที่ลบได้</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ยืนยันการลบ',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b'
    }).then(r => { if (r.isConfirmed) e.target.closest('form').submit(); });
    return false;
}
</script>
JS;

include 'includes/footer.php';
?>
