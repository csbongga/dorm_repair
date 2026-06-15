<?php
require_once 'includes/auth_check.php';
require_once '../connect.php';
require_once '../includes/image_resize.php';

$page_title   = 'ตั้งค่าระบบบิล';
$current_page = 'bill_settings';

// ====================================================================
// POST: จัดการรอบบิล
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cycle_action'])) {
    $ca = $_POST['cycle_action'];

    if ($ca === 'set_current') {
        $cid = trim($_POST['cycle_id'] ?? '');
        if ($cid) {
            $pdo->query("UPDATE bill_cycles SET is_current = 0");
            $pdo->prepare("UPDATE bill_cycles SET is_current = 1 WHERE id = ?")->execute([$cid]);
        }
    } elseif ($ca === 'create') {
        $cid   = trim($_POST['new_id']          ?? '');
        $label = trim($_POST['new_label']        ?? '');
        $short = trim($_POST['new_short_label']  ?? '');
        $due   = trim($_POST['new_due_text']      ?? '');
        if ($cid && $label) {
            try {
                $pdo->prepare("INSERT INTO bill_cycles (id, label, short_label, due_text, is_current) VALUES (?,?,?,?,0)")
                    ->execute([$cid, $label, $short ?: $label, $due]);
            } catch (PDOException $e) { /* duplicate id — ignore */ }
        }
    } elseif ($ca === 'auto_current') {
        // สร้างรอบเดือนปัจจุบัน (ปีพุทธศักราช) และตั้งเป็น current อัตโนมัติ
        $thaiMonths = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
                       'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
        $thaiShort  = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.',
                       'ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
        $m    = (int)date('n');
        $yBE  = (int)date('Y') + 543;
        $cid  = $yBE . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
        $label = $thaiMonths[$m] . ' ' . $yBE;
        $short = $thaiShort[$m];
        // กำหนดชำระ = วันที่ 12 เดือนถัดไป
        $nextM   = $m === 12 ? 1 : $m + 1;
        $nextYBE = $m === 12 ? $yBE + 1 : $yBE;
        $due = '12 ' . $thaiShort[$nextM] . ' ' . $nextYBE;

        try {
            $pdo->prepare("INSERT IGNORE INTO bill_cycles (id, label, short_label, due_text, is_current) VALUES (?,?,?,?,0)")
                ->execute([$cid, $label, $short, $due]);
        } catch (PDOException $e) {}

        $pdo->query("UPDATE bill_cycles SET is_current = 0");
        $pdo->prepare("UPDATE bill_cycles SET is_current = 1 WHERE id = ?")->execute([$cid]);
    } elseif ($ca === 'delete') {
        $cid = trim($_POST['cycle_id'] ?? '');
        if ($cid) {
            $pdo->prepare("DELETE FROM bill_cycles WHERE id = ? AND is_current = 0")->execute([$cid]);
        }
    }

    header('Location: bill_settings.php#cycles');
    exit;
}

// ====================================================================
// POST: บันทึก
// ====================================================================
$successMsg = $errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = [
        'dorm_name', 'dorm_addr',
        'rate_water', 'rate_elec', 'read_window_start', 'read_window_end', 'due_text',
        'bank_name', 'bank_holder', 'bank_acc', 'promptpay',
        'line_channel_token',
    ];
    try {
        // อัปโหลด QR Code
        if (!empty($_FILES['qr_image']['name'])) {
            $file    = $_FILES['qr_image'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo   = finfo_open(FILEINFO_MIME_TYPE);
            $mime    = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed)) {
                throw new Exception('ไฟล์ต้องเป็นรูปภาพ (JPG, PNG, GIF, WEBP) เท่านั้น');
            }
            if ($file['size'] > 2 * 1024 * 1024) {
                throw new Exception('ไฟล์ต้องมีขนาดไม่เกิน 2MB');
            }

            $ext     = pathinfo($file['name'], PATHINFO_EXTENSION);
            $qrDir   = __DIR__ . '/../uploads/qr/';
            if (!is_dir($qrDir)) mkdir($qrDir, 0755, true);

            // ลบไฟล์เก่า
            foreach (glob($qrDir . 'qr_payment.*') as $old) unlink($old);

            $filename = 'qr_payment.' . strtolower($ext);
            resizeAndSave($file['tmp_name'], $qrDir . $filename, 800);
            $keys[] = 'qr_image';
            $_POST['qr_image'] = 'uploads/qr/' . $filename;
        }

        $stmt = $pdo->prepare("
            INSERT INTO bill_settings (setting_key, value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()
        ");
        foreach ($keys as $key) {
            $val = trim($_POST[$key] ?? '');
            $stmt->execute([$key, $val]);
        }
        $successMsg = 'บันทึกการตั้งค่าเรียบร้อยแล้ว';
    } catch (Exception $e) {
        error_log('bill_settings: ' . $e->getMessage());
        $errorMsg = $e->getMessage();
    }
}

// ====================================================================
// ดึงค่าปัจจุบัน
// ====================================================================
$rows   = $pdo->query("SELECT setting_key, value FROM bill_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$cycles = $pdo->query("SELECT * FROM bill_cycles ORDER BY id DESC")->fetchAll();

function s(array $rows, string $key): string {
    return htmlspecialchars($rows[$key] ?? '');
}

$extra_head = <<<'CSS'
<style>
.settings-section {
    background: white; border-radius: 16px;
    border: 1px solid #f1f5f9;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
    margin-bottom: 20px; overflow: hidden;
}
.settings-section-header {
    padding: 16px 20px; border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; gap: 10px;
}
.settings-section-header .sh-icon {
    width: 34px; height: 34px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.95rem; flex-shrink: 0;
}
.settings-section-header .sh-title {
    font-size: 0.95rem; font-weight: 600; color: #1e293b;
}
.settings-section-body { padding: 20px; }

.field-group { margin-bottom: 18px; }
.field-group:last-child { margin-bottom: 0; }
.field-label {
    display: block; font-size: 0.82rem; font-weight: 600;
    color: #475569; margin-bottom: 6px;
}
.field-hint {
    font-size: 0.75rem; color: #94a3b8; font-weight: 400; margin-left: 4px;
}
.field-input {
    width: 100%; border: 1.5px solid #e2e8f0; border-radius: 10px;
    padding: 10px 14px; font-size: 0.9rem;
    font-family: 'Kanit', sans-serif; color: #1e293b;
    background: #f8fafc; outline: none;
    transition: border-color .2s, box-shadow .2s;
}
.field-input:focus {
    border-color: #06C755;
    box-shadow: 0 0 0 3px rgba(6,199,85,.1);
    background: white;
}
.field-input.rate {
    max-width: 180px;
}
.input-suffix {
    display: flex; align-items: center; gap: 8px;
}
.input-suffix span {
    font-size: 0.85rem; color: #64748b; white-space: nowrap;
}

.btn-save {
    border: none; background: #06C755; color: white;
    border-radius: 10px; padding: 11px 28px;
    font-size: 0.92rem; font-weight: 600;
    font-family: 'Kanit', sans-serif; cursor: pointer;
    transition: opacity .2s;
    display: inline-flex; align-items: center; gap: 7px;
}
.btn-save:hover { opacity: .88; }

.qr-preview-box {
    border: 2px dashed #e2e8f0; border-radius: 12px;
    padding: 16px; text-align: center;
    background: #f8fafc; cursor: pointer;
    transition: border-color .2s, background .2s;
    position: relative;
}
.qr-preview-box:hover { border-color: #06C755; background: #f0fdf4; }
.qr-preview-box img {
    max-width: 160px; max-height: 160px;
    border-radius: 8px; display: block; margin: 0 auto 10px;
}
.qr-preview-box .qr-placeholder {
    color: #94a3b8; font-size: 0.85rem; padding: 20px 0;
}
.qr-preview-box .qr-placeholder i { font-size: 2.5rem; display: block; margin-bottom: 8px; }
.qr-change-hint {
    font-size: 0.75rem; color: #94a3b8; margin-top: 6px;
}
</style>
CSS;

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-gear-fill me-2" style="color:#06C755;"></i>ตั้งค่าระบบบิล</h2>
        <p class="page-desc">จัดการข้อมูลหอพัก อัตราค่าสาธารณูปโภค และช่องทางชำระเงิน</p>
    </div>
</div>

<?php if ($successMsg): ?>
<div class="alert alert-success rounded-3 d-flex align-items-center gap-2 mb-4" style="font-size:.9rem;">
    <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($successMsg) ?>
</div>
<?php endif; ?>
<?php if ($errorMsg): ?>
<div class="alert alert-danger rounded-3 d-flex align-items-center gap-2 mb-4" style="font-size:.9rem;">
    <i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($errorMsg) ?>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
<div class="row g-4">

    <!-- คอลัมน์ซ้าย -->
    <div class="col-lg-6">

        <!-- ข้อมูลหอพัก -->
        <div class="settings-section">
            <div class="settings-section-header">
                <div class="sh-icon" style="background:#dbeafe;color:#3b82f6;">
                    <i class="bi bi-building-fill"></i>
                </div>
                <div class="sh-title">ข้อมูลหอพัก</div>
            </div>
            <div class="settings-section-body">
                <div class="field-group">
                    <label class="field-label">ชื่อหอพัก</label>
                    <input type="text" name="dorm_name" class="field-input"
                           value="<?= s($rows, 'dorm_name') ?>" placeholder="เช่น หอพักราชพฤกษ์">
                </div>
                <div class="field-group">
                    <label class="field-label">ที่อยู่</label>
                    <textarea name="dorm_addr" class="field-input" rows="3"
                              placeholder="ที่อยู่หอพัก"><?= s($rows, 'dorm_addr') ?></textarea>
                </div>
            </div>
        </div>

        <!-- อัตราค่าสาธารณูปโภค -->
        <div class="settings-section">
            <div class="settings-section-header">
                <div class="sh-icon" style="background:#f0fdf4;color:#0d9488;">
                    <i class="bi bi-speedometer2"></i>
                </div>
                <div class="sh-title">อัตราค่าสาธารณูปโภค</div>
            </div>
            <div class="settings-section-body">
                <div class="field-group">
                    <label class="field-label">
                        ค่าน้ำ <span class="field-hint">(บาท/หน่วย)</span>
                    </label>
                    <div class="input-suffix">
                        <input type="number" name="rate_water" class="field-input rate"
                               value="<?= s($rows, 'rate_water') ?>" min="0" step="0.01" placeholder="18">
                        <span>บาท / หน่วย</span>
                    </div>
                </div>
                <div class="field-group">
                    <label class="field-label">
                        ค่าไฟ <span class="field-hint">(บาท/หน่วย)</span>
                    </label>
                    <div class="input-suffix">
                        <input type="number" name="rate_elec" class="field-input rate"
                               value="<?= s($rows, 'rate_elec') ?>" min="0" step="0.01" placeholder="8">
                        <span>บาท / หน่วย</span>
                    </div>
                </div>
                <div class="field-group">
                    <label class="field-label">ช่วงวันที่อ่านมิเตอร์ <span class="field-hint">(วันที่ของเดือน)</span></label>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <input type="number" name="read_window_start" class="field-input rate"
                               value="<?= s($rows, 'read_window_start') ?: 26 ?>" min="1" max="31" placeholder="26">
                        <span style="color:#64748b;font-size:.88rem;">ถึง</span>
                        <input type="number" name="read_window_end" class="field-input rate"
                               value="<?= s($rows, 'read_window_end') ?: 30 ?>" min="1" max="31" placeholder="30">
                        <span style="color:#94a3b8;font-size:.82rem;">ของทุกเดือน</span>
                    </div>
                </div>
                <div class="field-group">
                    <label class="field-label">กำหนดชำระ</label>
                    <input type="text" name="due_text" class="field-input"
                           value="<?= s($rows, 'due_text') ?>" placeholder="เช่น ชำระภายในวันที่ 12 ของเดือนถัดไป">
                </div>
            </div>
        </div>

    </div>

    <!-- คอลัมน์ขวา -->
    <div class="col-lg-6">

        <!-- ช่องทางชำระเงิน -->
        <div class="settings-section">
            <div class="settings-section-header">
                <div class="sh-icon" style="background:#fef3c7;color:#d97706;">
                    <i class="bi bi-credit-card-fill"></i>
                </div>
                <div class="sh-title">ช่องทางชำระเงิน</div>
            </div>
            <div class="settings-section-body">
                <div class="field-group">
                    <label class="field-label">ชื่อธนาคาร</label>
                    <input type="text" name="bank_name" class="field-input"
                           value="<?= s($rows, 'bank_name') ?>" placeholder="เช่น ธนาคารกรุงไทย">
                </div>
                <div class="field-group">
                    <label class="field-label">ชื่อบัญชี</label>
                    <input type="text" name="bank_holder" class="field-input"
                           value="<?= s($rows, 'bank_holder') ?>" placeholder="ชื่อเจ้าของบัญชี">
                </div>
                <div class="field-group">
                    <label class="field-label">เลขบัญชี</label>
                    <input type="text" name="bank_acc" class="field-input"
                           value="<?= s($rows, 'bank_acc') ?>" placeholder="xxx-x-xxxxx-x">
                </div>
                <div class="field-group">
                    <label class="field-label">พร้อมเพย์ <span class="field-hint">(เบอร์โทร / เลขบัตร)</span></label>
                    <input type="text" name="promptpay" class="field-input"
                           value="<?= s($rows, 'promptpay') ?>" placeholder="0XX-XXX-XXXX">
                </div>
                <div class="field-group">
                    <label class="field-label">QR Code ชำระเงิน</label>
                    <div class="qr-preview-box" onclick="document.getElementById('qrFileInput').click()">
                        <?php
                        $qrPath = $rows['qr_image'] ?? '';
                        $qrFile = $qrPath ? __DIR__ . '/../' . $qrPath : '';
                        ?>
                        <?php if ($qrPath && file_exists($qrFile)): ?>
                            <img id="qrPreviewImg" src="../<?= htmlspecialchars($qrPath) ?>?v=<?= filemtime($qrFile) ?>" alt="QR Code">
                            <div style="font-size:.8rem;color:#0d9488;font-weight:600;">
                                <i class="bi bi-check-circle-fill me-1"></i>มี QR Code แล้ว — คลิกเพื่อเปลี่ยน
                            </div>
                        <?php else: ?>
                            <div class="qr-placeholder" id="qrPlaceholder">
                                <i class="bi bi-qr-code"></i>
                                คลิกเพื่ออัปโหลด QR Code
                            </div>
                            <img id="qrPreviewImg" src="" alt="" style="display:none;max-width:160px;max-height:160px;border-radius:8px;margin:0 auto 10px;">
                        <?php endif; ?>
                        <input type="file" id="qrFileInput" name="qr_image"
                               accept="image/jpeg,image/png,image/gif,image/webp"
                               style="display:none;" onchange="previewQR(this)">
                    </div>
                    <div class="qr-change-hint">รองรับ JPG, PNG, WEBP · ขนาดไม่เกิน 2MB</div>
                </div>
            </div>
        </div>

        <!-- LINE Integration -->
        <div class="settings-section">
            <div class="settings-section-header">
                <div class="sh-icon" style="background:#dcfce7;color:#06C755;">
                    <i class="bi bi-chat-fill"></i>
                </div>
                <div class="sh-title">LINE Integration</div>
            </div>
            <div class="settings-section-body">
                <div class="field-group">
                    <label class="field-label">LINE Channel Access Token</label>
                    <input type="text" name="line_channel_token" class="field-input"
                           value="<?= s($rows, 'line_channel_token') ?>"
                           placeholder="Channel Access Token จาก LINE Developers">
                </div>
            </div>
        </div>

        <!-- Save button -->
        <div style="text-align:right;">
            <button type="submit" class="btn-save">
                <i class="bi bi-floppy-fill"></i> บันทึกการตั้งค่า
            </button>
        </div>

    </div>
</div>
</form>

<!-- ====================================================================
     รอบบิล (Cycle Management)
===================================================================== -->
<div class="panel mt-4" id="cycles">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-calendar3"></i> จัดการรอบบิล</span>
    </div>
    <div class="panel-body">

        <!-- ปุ่มเริ่มรอบเดือนนี้ -->
        <?php
        $curM   = (int)date('n');
        $curYBE = (int)date('Y') + 543;
        $thaiM  = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
                   'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
        $autoLabel = $thaiM[$curM] . ' ' . $curYBE;
        $autoCid   = $curYBE . '-' . str_pad($curM, 2, '0', STR_PAD_LEFT);
        $alreadyCurrent = false;
        foreach ($cycles as $c) {
            if ($c['id'] === $autoCid && $c['is_current']) { $alreadyCurrent = true; break; }
        }
        ?>
        <div class="d-flex align-items-center gap-3 mb-4 p-3"
             style="background:#f0fdf4;border-radius:12px;border:1px solid #bbf7d0;">
            <i class="bi bi-calendar-check-fill" style="color:#16a34a;font-size:1.4rem;flex-shrink:0;"></i>
            <div class="flex-fill">
                <div style="font-weight:600;font-size:0.95rem;color:#15803d;">รอบบิลเดือนนี้: <?= $autoLabel ?></div>
                <div style="font-size:0.8rem;color:#4ade80;">
                    <?= $alreadyCurrent ? '✅ ตั้งเป็นรอบปัจจุบันอยู่แล้ว' : 'กดปุ่มเพื่อสร้าง/ตั้งรอบนี้เป็นปัจจุบัน' ?>
                </div>
            </div>
            <?php if (!$alreadyCurrent): ?>
            <form method="POST">
                <input type="hidden" name="cycle_action" value="auto_current">
                <button type="submit" class="btn"
                        style="background:#16a34a;color:white;border:none;border-radius:10px;padding:10px 20px;font-family:'Kanit',sans-serif;font-weight:600;white-space:nowrap;">
                    <i class="bi bi-play-circle-fill me-1"></i>เริ่มรอบบิลเดือนนี้
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- รายการรอบบิล -->
        <?php if (empty($cycles)): ?>
        <p style="color:#94a3b8;font-size:0.88rem;">ยังไม่มีรอบบิล กรุณาสร้างรอบแรก</p>
        <?php else: ?>
        <div class="table-responsive mb-4">
            <table class="table table-clean mb-0">
                <thead>
                    <tr>
                        <th style="padding-left:4px;">รหัสรอบ</th>
                        <th>ชื่อรอบ</th>
                        <th>กำหนดชำระ</th>
                        <th class="text-center">สถานะ</th>
                        <th class="text-center">ตั้งเป็นปัจจุบัน</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cycles as $c): ?>
                    <tr>
                        <td style="padding-left:4px;font-family:monospace;font-weight:600;color:#475569;"><?= htmlspecialchars($c['id']) ?></td>
                        <td><?= htmlspecialchars($c['label']) ?></td>
                        <td style="font-size:0.85rem;color:#64748b;"><?= htmlspecialchars($c['due_text']) ?></td>
                        <td class="text-center">
                            <?php if ($c['is_current']): ?>
                            <span class="badge" style="background:#d1fae5;color:#059669;font-size:0.78rem;padding:4px 10px;border-radius:10px;">
                                <i class="bi bi-check-circle-fill me-1"></i>ปัจจุบัน
                            </span>
                            <?php else: ?>
                            <span style="color:#cbd5e1;font-size:0.82rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if (!$c['is_current']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="cycle_action" value="set_current">
                                <input type="hidden" name="cycle_id" value="<?= htmlspecialchars($c['id']) ?>">
                                <button type="submit" class="btn btn-sm"
                                        style="background:#06C755;color:white;border:none;border-radius:8px;font-size:0.78rem;">
                                    <i class="bi bi-check-lg me-1"></i>ตั้งเป็นปัจจุบัน
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$c['is_current']): ?>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('ลบรอบ <?= htmlspecialchars($c['id'], ENT_QUOTES) ?> ?')">
                                <input type="hidden" name="cycle_action" value="delete">
                                <input type="hidden" name="cycle_id" value="<?= htmlspecialchars($c['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" style="font-size:0.78rem;">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- ฟอร์มสร้างรอบใหม่ -->
        <div style="border-top:1px solid #f1f5f9;padding-top:18px;">
            <div style="font-size:0.88rem;font-weight:600;color:#1e293b;margin-bottom:12px;">
                <i class="bi bi-plus-circle me-1" style="color:#06C755;"></i>สร้างรอบบิลใหม่
            </div>
            <form method="POST" class="row g-2 align-items-end">
                <input type="hidden" name="cycle_action" value="create">
                <div class="col-6 col-md-2">
                    <label class="form-label mb-1" style="font-size:0.78rem;color:#64748b;">รหัสรอบ <span style="color:#94a3b8;">(เช่น 2569-06)</span></label>
                    <input type="text" name="new_id" class="form-control form-control-sm"
                           placeholder="2569-06" pattern="\d{4}-\d{2}" required
                           style="font-family:monospace;">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label mb-1" style="font-size:0.78rem;color:#64748b;">ชื่อรอบ</label>
                    <input type="text" name="new_label" class="form-control form-control-sm"
                           placeholder="มิถุนายน 2569" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-1" style="font-size:0.78rem;color:#64748b;">ชื่อย่อ</label>
                    <input type="text" name="new_short_label" class="form-control form-control-sm"
                           placeholder="มิ.ย.">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label mb-1" style="font-size:0.78rem;color:#64748b;">กำหนดชำระ</label>
                    <input type="text" name="new_due_text" class="form-control form-control-sm"
                           placeholder="12 ก.ค. 2569">
                </div>
                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-sm w-100"
                            style="background:#06C755;color:white;border:none;border-radius:8px;">
                        <i class="bi bi-plus-lg me-1"></i>สร้าง
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extra_scripts = <<<'JS'
<script>
function previewQR(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('qrPreviewImg');
        const ph  = document.getElementById('qrPlaceholder');
        img.src = e.target.result;
        img.style.display = 'block';
        if (ph) ph.style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}
</script>
JS;
include 'includes/footer.php';
?>
