<?php
/**
 * หน้าส่งค่ามิเตอร์น้ำรายเดือน (สำหรับนักศึกษาผ่าน LINE LIFF)
 * อนุญาตให้ส่งค่าได้เฉพาะวันที่ 26–30 ของทุกเดือน
 */

require_once 'connect.php';
require_once 'includes/image_resize.php';

define('LIFF_ID',          '2010214920-61HdsmWW');
define('UPLOAD_DIR',       __DIR__ . '/uploads/meters/');
define('UPLOAD_WEB_PATH',  'uploads/meters/');
$_wStmt = $pdo->query("SELECT setting_key, value FROM bill_settings WHERE setting_key IN ('read_window_start','read_window_end')");
$_wSettings = $_wStmt->fetchAll(PDO::FETCH_KEY_PAIR);
define('SUBMIT_DAY_START', (int)($_wSettings['read_window_start'] ?? 1));
define('SUBMIT_DAY_END',   (int)($_wSettings['read_window_end']   ?? 30));

// =========================================================================
// GET: ประวัติการชำระเงิน
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_payment_history') {
    header('Content-Type: application/json; charset=utf-8');
    $room_id = (int)($_GET['room_id'] ?? 0);
    if ($room_id <= 0) { echo json_encode(['success' => false]); exit; }
    try {
        $ratesStmt = $pdo->query("SELECT setting_key, value FROM bill_settings WHERE setting_key IN ('rate_water','rate_elec')");
        $rates     = $ratesStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $rateW = (float)($rates['rate_water'] ?? 0);
        $rateE = (float)($rates['rate_elec']  ?? 0);

        $stmt = $pdo->prepare("
            SELECT bc.label,
                   bm.water_curr, bm.water_prev,
                   bm.elec_curr, bm.elec_prev,
                   bm.payment_status, bm.payment_submitted_at,
                   bm.payment_confirmed_at
            FROM bill_meters bm
            JOIN bill_cycles bc ON bc.id = bm.cycle_id
            WHERE bm.room_id = :rid
            ORDER BY bc.id DESC
            LIMIT 12
        ");
        $stmt->execute(['rid' => $room_id]);
        $rows = $stmt->fetchAll();

        $history = [];
        foreach ($rows as $r) {
            $wUnits = ($r['water_curr'] !== null && $r['water_prev'] !== null)
                ? (float)$r['water_curr'] - (float)$r['water_prev'] : null;
            $eUnits = ($r['elec_curr'] !== null && $r['elec_prev'] !== null)
                ? (float)$r['elec_curr'] - (float)$r['elec_prev'] : null;
            $wAmt = ($wUnits !== null && $rateW > 0) ? $wUnits * $rateW : null;
            $eAmt = ($eUnits !== null && $rateE > 0) ? $eUnits * $rateE : null;
            $history[] = [
                'label'          => $r['label'],
                'water_units'    => $wUnits,
                'elec_units'     => $eUnits,
                'water_amt'      => $wAmt,
                'elec_amt'       => $eAmt,
                'total'          => ($wAmt ?? 0) + ($eAmt ?? 0),
                'payment_status' => $r['payment_status'],
                'paid_at'        => $r['payment_confirmed_at'] ?? $r['payment_submitted_at'],
            ];
        }
        echo json_encode(['success' => true, 'history' => $history]);
    } catch (PDOException $e) {
        error_log('get_payment_history: ' . $e->getMessage());
        echo json_encode(['success' => false]);
    }
    exit;
}

// =========================================================================
// GET: ดึงสถานะมิเตอร์รอบปัจจุบันของห้อง
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_meter_status') {
    header('Content-Type: application/json; charset=utf-8');
    $room_id = (int)($_GET['room_id'] ?? 0);
    if ($room_id <= 0) {
        echo json_encode(['success' => false]);
        exit;
    }
    try {
        $cycleRow = $pdo->query("SELECT id, label FROM bill_cycles WHERE is_current = 1 LIMIT 1")->fetch();
        $submission = null;
        if ($cycleRow) {
            try {
                $subStmt = $pdo->prepare("
                    SELECT water_status, water_curr, water_submitted_at, water_reject_reason,
                           payment_slip, payment_status, payment_submitted_at
                    FROM bill_meters
                    WHERE cycle_id = :cid AND room_id = :rid
                    LIMIT 1
                ");
                $subStmt->execute(['cid' => $cycleRow['id'], 'rid' => $room_id]);
            } catch (PDOException $e2) {
                // fallback: payment columns ยังไม่มีใน DB
                $subStmt = $pdo->prepare("
                    SELECT water_status, water_curr, water_submitted_at, water_reject_reason
                    FROM bill_meters
                    WHERE cycle_id = :cid AND room_id = :rid
                    LIMIT 1
                ");
                $subStmt->execute(['cid' => $cycleRow['id'], 'rid' => $room_id]);
            }
            $submission = $subStmt->fetch() ?: null;

            // ดึงค่ามิเตอร์รอบที่แล้ว — ถ้าไม่มีใน bill_meters ให้ fallback ไป water_meter_init
            $prevStmt = $pdo->prepare("
                SELECT COALESCE(
                    (SELECT bm2.water_curr
                     FROM bill_meters bm2
                     WHERE bm2.room_id = :rid
                       AND bm2.water_status = 'verified'
                       AND bm2.cycle_id != :cid
                     ORDER BY bm2.cycle_id DESC LIMIT 1),
                    (SELECT r.water_meter_init FROM rooms r WHERE r.id = :rid2)
                ) AS water_prev
            ");
            $prevStmt->execute(['rid' => $room_id, 'cid' => $cycleRow['id'], 'rid2' => $room_id]);
            $waterPrev = $prevStmt->fetchColumn() ?: null;

            $settingsKeys = ['rate_water','rate_elec','bank_name','bank_holder','bank_acc','promptpay','qr_image'];
            $inClause = implode(',', array_fill(0, count($settingsKeys), '?'));
            $settingsStmt = $pdo->prepare("SELECT setting_key, value FROM bill_settings WHERE setting_key IN ($inClause)");
            $settingsStmt->execute($settingsKeys);
            $cfg = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $rateWater = isset($cfg['rate_water']) ? (float)$cfg['rate_water'] : null;
            $rateElec  = isset($cfg['rate_elec'])  ? (float)$cfg['rate_elec']  : null;

            // ตรวจสอบว่าไฟล์ QR มีอยู่จริง
            $qrPath = $cfg['qr_image'] ?? '';
            if ($qrPath && !file_exists(__DIR__ . '/' . $qrPath)) $qrPath = '';

            $elecStmt = $pdo->prepare("
                SELECT elec_curr, elec_prev, elec_entered
                FROM bill_meters
                WHERE cycle_id = :cid AND room_id = :rid
                LIMIT 1
            ");
            $elecStmt->execute(['cid' => $cycleRow['id'], 'rid' => $room_id]);
            $elecRow = $elecStmt->fetch() ?: null;
        }

        // ค้นหาบิลค้างชำระล่าสุด (ทุก cycle) — ใช้แสดงสรุปบิล+ชำระเงินแม้จะอยู่ในช่วงกรอกมิเตอร์
        $pendingBill = null;
        try {
            $pbStmt = $pdo->prepare("
                SELECT bm.water_curr, bm.water_prev, bm.elec_curr, bm.elec_prev,
                       bm.payment_status, bm.payment_slip, bm.payment_submitted_at,
                       bc.label AS cycle_label
                FROM bill_meters bm
                JOIN bill_cycles bc ON bc.id = bm.cycle_id
                WHERE bm.room_id = :rid
                  AND bm.water_status = 'verified'
                  AND bm.elec_entered = 1
                  AND (bm.payment_status IS NULL OR bm.payment_status = 'pending')
                ORDER BY bc.id DESC
                LIMIT 1
            ");
            $pbStmt->execute(['rid' => $room_id]);
            $pendingBill = $pbStmt->fetch() ?: null;
        } catch (PDOException $e2) { /* columns อาจยังไม่มี */ }

        echo json_encode([
            'success'      => true,
            'cycle_id'     => $cycleRow['id']    ?? null,
            'cycle_label'  => $cycleRow['label'] ?? null,
            'water_prev'   => $waterPrev ?? null,
            'rate_water'   => $rateWater ?? null,
            'submission'   => $submission,
            'elec_entered' => $elecRow && $elecRow['elec_entered'] ? true : false,
            'elec_curr'    => $elecRow['elec_curr']  ?? null,
            'elec_prev'    => $elecRow['elec_prev']  ?? null,
            'rate_elec'    => $rateElec ?? null,
            'bank_name'    => $cfg['bank_name']   ?? null,
            'bank_holder'  => $cfg['bank_holder'] ?? null,
            'bank_acc'     => $cfg['bank_acc']    ?? null,
            'promptpay'    => $cfg['promptpay']   ?? null,
            'qr_image'     => $qrPath ?: null,
            'pending_bill' => $pendingBill,
        ]);
    } catch (PDOException $e) {
        error_log('get_meter_status: ' . $e->getMessage());
        echo json_encode(['success' => false]);
    }
    exit;
}

// =========================================================================
// AJAX: ตรวจสอบ LINE UID
// =========================================================================
if (isset($_POST['action']) && $_POST['action'] === 'check_user') {
    header('Content-Type: application/json; charset=utf-8');
    $line_uid = trim($_POST['line_uid'] ?? '');

    if (empty($line_uid)) {
        echo json_encode(['registered' => false, 'error' => 'no_uid']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT s.student_id, s.name, s.room_id, r.room_number
            FROM students s
            LEFT JOIN rooms r ON r.id = s.room_id
            WHERE s.line_uid = :uid
            LIMIT 1
        ");
        $stmt->execute(['uid' => $line_uid]);
        $student = $stmt->fetch();

        if (!$student) {
            echo json_encode([
                'registered' => false,
                'reason'     => 'uid_not_found',
                'uid_prefix' => substr($line_uid, 0, 8),
            ]);
            exit;
        }

        if (empty($student['room_id'])) {
            echo json_encode(['registered' => false, 'reason' => 'no_room']);
            exit;
        }

        $cycleRow = $pdo->query("SELECT id, label FROM bill_cycles WHERE is_current = 1 LIMIT 1")->fetch();

        $submission = null;
        $waterPrev  = null;
        if ($cycleRow) {
            try {
                $subStmt = $pdo->prepare("
                    SELECT water_status, water_curr, water_submitted_at, water_reject_reason,
                           payment_slip, payment_status, payment_submitted_at
                    FROM bill_meters
                    WHERE cycle_id = :cid AND room_id = :rid
                    LIMIT 1
                ");
                $subStmt->execute(['cid' => $cycleRow['id'], 'rid' => $student['room_id']]);
            } catch (PDOException $e2) {
                $subStmt = $pdo->prepare("
                    SELECT water_status, water_curr, water_submitted_at, water_reject_reason
                    FROM bill_meters
                    WHERE cycle_id = :cid AND room_id = :rid
                    LIMIT 1
                ");
                $subStmt->execute(['cid' => $cycleRow['id'], 'rid' => $student['room_id']]);
            }
            $submission = $subStmt->fetch() ?: null;

            $prevStmt = $pdo->prepare("
                SELECT COALESCE(
                    (SELECT bm2.water_curr
                     FROM bill_meters bm2
                     WHERE bm2.room_id = :rid
                       AND bm2.water_status = 'verified'
                       AND bm2.cycle_id != :cid
                     ORDER BY bm2.cycle_id DESC LIMIT 1),
                    (SELECT r.water_meter_init FROM rooms r WHERE r.id = :rid2)
                ) AS water_prev
            ");
            $prevStmt->execute(['rid' => $student['room_id'], 'cid' => $cycleRow['id'], 'rid2' => $student['room_id']]);
            $waterPrev = $prevStmt->fetchColumn() ?: null;

            $rateRow = $pdo->query("SELECT value FROM bill_settings WHERE setting_key = 'rate_water' LIMIT 1")->fetch();
            $rateWater = $rateRow ? (float)$rateRow['value'] : null;
        }

        echo json_encode([
            'registered'  => true,
            'student_id'  => $student['student_id'],
            'name'        => $student['name'],
            'room_id'     => $student['room_id'],
            'room_number' => $student['room_number'],
            'cycle_id'    => $cycleRow['id']    ?? null,
            'cycle_label' => $cycleRow['label'] ?? null,
            'water_prev'  => $waterPrev,
            'rate_water'  => $rateWater ?? null,
            'submission'  => $submission,
        ]);

    } catch (PDOException $e) {
        error_log('meter_submit check_user: ' . $e->getMessage());
        echo json_encode(['registered' => false, 'error' => 'db_error']);
    }
    exit;
}

// =========================================================================
// AJAX: รับและบันทึกค่ามิเตอร์
// =========================================================================
if (isset($_POST['action']) && $_POST['action'] === 'submit_meter') {
    header('Content-Type: application/json; charset=utf-8');

    $line_uid   = trim($_POST['line_uid']    ?? '');
    $room_id    = (int)($_POST['room_id']    ?? 0);
    $water_curr = trim($_POST['water_curr']  ?? '');

    $today = (int)date('d');
    if ($today < SUBMIT_DAY_START || $today > SUBMIT_DAY_END) {
        echo json_encode(['success' => false, 'message' => 'ไม่อยู่ในช่วงเวลาที่อนุญาต (วันที่ 26–30 เท่านั้น)']);
        exit;
    }

    if (empty($line_uid) || $room_id <= 0 || !is_numeric($water_curr)) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน กรุณาตรวจสอบอีกครั้ง']);
        exit;
    }

    try {
        $authStmt = $pdo->prepare("SELECT student_id FROM students WHERE line_uid = :uid AND room_id = :rid LIMIT 1");
        $authStmt->execute(['uid' => $line_uid, 'rid' => $room_id]);
        if (!$authStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'ข้อมูลนักศึกษาและห้องพักไม่ตรงกัน']);
            exit;
        }

        $cycleRow = $pdo->query("SELECT id FROM bill_cycles WHERE is_current = 1 LIMIT 1")->fetch();
        if (!$cycleRow) {
            echo json_encode(['success' => false, 'message' => 'ไม่พบรอบบิลที่ใช้งานอยู่ กรุณาติดต่อแอดมิน']);
            exit;
        }
        $cycle_id = $cycleRow['id'];

        $existStmt = $pdo->prepare("SELECT id, water_status, water_photo FROM bill_meters WHERE cycle_id = :cid AND room_id = :rid LIMIT 1");
        $existStmt->execute(['cid' => $cycle_id, 'rid' => $room_id]);
        $existing = $existStmt->fetch();

        if ($existing && $existing['water_status'] === 'verified') {
            echo json_encode(['success' => false, 'message' => 'ค่ามิเตอร์รอบนี้ได้รับการยืนยันแล้ว ไม่สามารถแก้ไขได้']);
            exit;
        }

        // ดึง water_prev (จาก verified รอบก่อน หรือ water_meter_init)
        $prevStmt = $pdo->prepare("
            SELECT COALESCE(
                (SELECT bm2.water_curr
                 FROM bill_meters bm2
                 WHERE bm2.room_id = :rid
                   AND bm2.water_status = 'verified'
                   AND bm2.cycle_id != :cid
                 ORDER BY bm2.cycle_id DESC LIMIT 1),
                (SELECT r.water_meter_init FROM rooms r WHERE r.id = :rid2)
            ) AS water_prev
        ");
        $prevStmt->execute(['rid' => $room_id, 'cid' => $cycle_id, 'rid2' => $room_id]);
        $water_prev = $prevStmt->fetchColumn();

        if (empty($_FILES['meter_photo']) || $_FILES['meter_photo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'กรุณาแนบรูปถ่ายหน้าปัดมิเตอร์']);
            exit;
        }

        $file    = $_FILES['meter_photo'];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        $finfo   = finfo_open(FILEINFO_MIME_TYPE);
        $mime    = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!array_key_exists($mime, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'ประเภทไฟล์ไม่ถูกต้อง รองรับเฉพาะ .jpg และ .png']);
            exit;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'ขนาดไฟล์เกินกำหนด (สูงสุด 5 MB)']);
            exit;
        }

        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }

        $ext      = $allowed[$mime];
        $filename = 'meter_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest     = UPLOAD_DIR . $filename;

        if (!resizeAndSave($file['tmp_name'], $dest)) {
            echo json_encode(['success' => false, 'message' => 'อัปโหลดรูปภาพไม่สำเร็จ กรุณาลองใหม่']);
            exit;
        }

        $photo_path = UPLOAD_WEB_PATH . $filename;

        if ($existing) {
            if (!empty($existing['water_photo'])) {
                @unlink(__DIR__ . '/' . $existing['water_photo']);
            }
            $pdo->prepare("
                UPDATE bill_meters
                SET water_prev           = :prev,
                    water_curr           = :curr,
                    water_photo          = :photo,
                    water_status         = 'review',
                    water_submitted_at   = NOW(),
                    water_reject_reason  = NULL
                WHERE id = :id
            ")->execute(['prev' => $water_prev, 'curr' => $water_curr, 'photo' => $photo_path, 'id' => $existing['id']]);
        } else {
            $pdo->prepare("
                INSERT INTO bill_meters (cycle_id, room_id, water_prev, water_curr, water_photo, water_status, water_submitted_at)
                VALUES (:cid, :rid, :prev, :curr, :photo, 'review', NOW())
            ")->execute(['cid' => $cycle_id, 'rid' => $room_id, 'prev' => $water_prev, 'curr' => $water_curr, 'photo' => $photo_path]);
        }

        echo json_encode(['success' => true]);

    } catch (PDOException $e) {
        error_log('meter_submit submit_meter: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่']);
    }
    exit;
}
// =========================================================================
// AJAX: แจ้งการชำระเงิน (อัปโหลดสลิป)
// =========================================================================
if (isset($_POST['action']) && $_POST['action'] === 'submit_payment') {
    header('Content-Type: application/json; charset=utf-8');

    $line_uid = trim($_POST['line_uid'] ?? '');
    $room_id  = (int)($_POST['room_id'] ?? 0);

    if (empty($line_uid) || $room_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
        exit;
    }

    try {
        $authStmt = $pdo->prepare("SELECT student_id FROM students WHERE line_uid = :uid AND room_id = :rid LIMIT 1");
        $authStmt->execute(['uid' => $line_uid, 'rid' => $room_id]);
        if (!$authStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'ข้อมูลนักศึกษาไม่ถูกต้อง']);
            exit;
        }

        // หาบิลค้างชำระล่าสุด (ยืนยันน้ำ+ไฟ ยังไม่ confirmed) จากทุกรอบ ไม่จำกัดรอบปัจจุบัน
        $meterStmt = $pdo->prepare("
            SELECT bm.id, bm.payment_status, bm.payment_slip
            FROM bill_meters bm
            JOIN bill_cycles bc ON bc.id = bm.cycle_id
            WHERE bm.room_id = :rid
              AND bm.water_status = 'verified'
              AND bm.elec_entered = 1
              AND (bm.payment_status IS NULL OR bm.payment_status = 'pending')
            ORDER BY bc.id DESC
            LIMIT 1
        ");
        $meterStmt->execute(['rid' => $room_id]);
        $meter = $meterStmt->fetch();
        if (!$meter) {
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลมิเตอร์รอบนี้']);
            exit;
        }
        if ($meter['payment_status'] === 'confirmed') {
            echo json_encode(['success' => false, 'message' => 'ยืนยันการชำระเงินแล้ว ไม่สามารถส่งซ้ำได้']);
            exit;
        }

        if (empty($_FILES['slip_photo']) || $_FILES['slip_photo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'กรุณาแนบภาพสลิปการโอนเงิน']);
            exit;
        }
        $file    = $_FILES['slip_photo'];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        $finfo   = finfo_open(FILEINFO_MIME_TYPE);
        $mime    = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!array_key_exists($mime, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'ประเภทไฟล์ไม่ถูกต้อง รองรับเฉพาะ .jpg และ .png']);
            exit;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'ขนาดไฟล์เกินกำหนด (สูงสุด 5 MB)']);
            exit;
        }

        $slipDir = __DIR__ . '/uploads/slips/';
        if (!is_dir($slipDir)) mkdir($slipDir, 0755, true);

        $ext      = $allowed[$mime];
        $filename = 'slip_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest     = $slipDir . $filename;

        if (!resizeAndSave($file['tmp_name'], $dest)) {
            echo json_encode(['success' => false, 'message' => 'อัปโหลดรูปภาพไม่สำเร็จ กรุณาลองใหม่']);
            exit;
        }

        // ลบสลิปเก่า (ถ้ามี)
        $oldSlipStmt = $pdo->prepare("SELECT payment_slip FROM bill_meters WHERE id = :id");
        $oldSlipStmt->execute(['id' => $meter['id']]);
        $oldSlip = $oldSlipStmt->fetchColumn();
        if ($oldSlip && file_exists(__DIR__ . '/' . $oldSlip)) {
            @unlink(__DIR__ . '/' . $oldSlip);
        }

        $slipPath = 'uploads/slips/' . $filename;
        $pdo->prepare("
            UPDATE bill_meters
            SET payment_slip = :slip, payment_status = 'pending', payment_submitted_at = NOW()
            WHERE id = :id
        ")->execute(['slip' => $slipPath, 'id' => $meter['id']]);

        echo json_encode(['success' => true, 'slip_url' => $slipPath]);

    } catch (PDOException $e) {
        error_log('submit_payment: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่']);
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>บิลหอพัก | น้องเก็บบิล</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Kanit', sans-serif;
            background: #eef2f6;
            min-height: 100vh;
            color: #1e293b;
        }

        .app-wrap {
            max-width: 480px;
            margin: 0 auto;
            min-height: 100vh;
            background: #eef2f6;
            display: flex;
            flex-direction: column;
            padding-bottom: 24px;
        }

        /* ── Loading overlay ── */
        #loadingOverlay {
            position: fixed; inset: 0;
            background: rgba(255,255,255,.95);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: 14px;
            z-index: 200;
        }
        #loadingOverlay .lo-spinner {
            width: 38px; height: 38px;
            border: 3px solid #e2e8f0;
            border-top-color: #0d9488;
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }
        #loadingText { font-size: .88rem; color: #64748b; }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Card base ── */
        .card {
            background: #fff;
            border-radius: 20px;
            padding: 22px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,.055);
        }

        /* ── Hero card ── */
        .hero-card {
            margin: 16px 16px 0;
        }

        .bill-period-row {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 14px;
        }
        .bill-icon-wrap {
            width: 38px; height: 38px; border-radius: 50%;
            background: #ccfbf1;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .bill-icon-wrap i { color: #0d9488; font-size: 1rem; }
        .bill-period-info { flex: 1; min-width: 0; }
        .bill-period-label { font-size: .8rem; color: #64748b; font-weight: 400; }

        /* status badges */
        .badge-pill {
            display: inline-flex; align-items: center; gap: 5px;
            border-radius: 20px; padding: 3px 10px;
            font-size: .73rem; font-weight: 500; white-space: nowrap;
        }
        .badge-pill .dot {
            width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0;
        }
        .badge-pending  { background: #fff0f0; color: #dc2626; }
        .badge-pending .dot  { background: #dc2626; }
        .badge-review   { background: #eff6ff; color: #2563eb; }
        .badge-review .dot   { background: #2563eb; }
        .badge-verified { background: #f0fdf4; color: #16a34a; }
        .badge-verified .dot { background: #16a34a; }
        .badge-reject   { background: #fff1f2; color: #e11d48; }
        .badge-reject .dot   { background: #e11d48; }
        .badge-outside  { background: #fef9ee; color: #d97706; }
        .badge-outside .dot  { background: #d97706; }

        /* hero heading */
        .hero-heading {
            font-size: 1.48rem; font-weight: 700;
            color: #0f172a; line-height: 1.3;
            margin-bottom: 6px;
        }
        .hero-sub {
            font-size: .83rem; color: #64748b;
            font-weight: 400; margin-bottom: 20px;
        }

        /* CTA button */
        .btn-cta {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%;
            border: none; border-radius: 14px;
            padding: 15px; font-size: 1rem; font-weight: 600;
            font-family: 'Kanit', sans-serif; cursor: pointer;
            transition: opacity .2s, transform .15s;
            color: #fff;
        }
        .btn-cta:hover  { opacity: .92; transform: translateY(-1px); }
        .btn-cta:active { transform: translateY(1px); }
        .btn-cta:disabled { opacity: .6; cursor: not-allowed; transform: none; }
        .btn-cta.teal   { background: linear-gradient(135deg,#0d9488,#0891b2); box-shadow: 0 4px 16px rgba(13,148,136,.3); }
        .btn-cta.gray   { background: linear-gradient(135deg,#64748b,#475569); box-shadow: 0 4px 16px rgba(71,85,105,.25); }
        .btn-cta.red    { background: linear-gradient(135deg,#ef4444,#dc2626); box-shadow: 0 4px 16px rgba(239,68,68,.25); }

        /* outside-window message */
        .outside-msg { text-align: center; padding: 6px 0 4px; }
        .outside-msg .om-icon { font-size: 2.4rem; color: #d97706; margin-bottom: 10px; }
        .outside-msg h3 { font-size: 1.2rem; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
        .outside-msg p  { font-size: .85rem; color: #64748b; line-height: 1.7; }

        /* ── Summary section ── */
        .section-wrap { margin: 18px 16px 0; }
        .section-title { font-size: .85rem; font-weight: 500; color: #475569; margin-bottom: 12px; }

        .cards-row {
            display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;
        }

        /* meter summary cards */
        .meter-card {
            border-radius: 16px; padding: 16px 14px;
            box-shadow: 0 1px 6px rgba(0,0,0,.05);
        }
        .meter-card.water {
            background: linear-gradient(145deg,#f0fbff,#e0f5ff);
            border: 1px solid #bae6fd;
        }
        .meter-card.elec {
            background: linear-gradient(145deg,#fffbeb,#fef3c7);
            border: 1px solid #fde68a;
        }
        .mc-header { display: flex; align-items: center; gap: 7px; margin-bottom: 6px; }
        .mc-header i { font-size: .95rem; }
        .water .mc-header i { color: #0ea5e9; }
        .elec  .mc-header i { color: #f59e0b; }
        .mc-label { font-size: .84rem; font-weight: 600; color: #334155; }
        .mc-value { font-size: .78rem; color: #94a3b8; }

        /* utility cards */
        .util-card {
            background: #fff; border-radius: 16px; padding: 16px 14px;
            box-shadow: 0 1px 6px rgba(0,0,0,.05);
            cursor: pointer; transition: background .15s;
            display: flex; align-items: center; gap: 12px;
        }
        .util-card:hover { background: #f8fafc; }
        .uc-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: #f1f5f9; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
        }
        .uc-icon i { font-size: 1rem; color: #64748b; }
        .uc-title { font-size: .84rem; font-weight: 600; color: #334155; }
        .uc-sub   { font-size: .74rem; color: #94a3b8; }

        /* ── Form section ── */
        .form-card { margin: 16px 16px 0; }
        .form-section-title {
            font-size: .95rem; font-weight: 600; color: #0f172a;
            margin-bottom: 18px;
            display: flex; align-items: center; gap: 8px;
        }
        .form-section-title i { color: #0d9488; }

        .field-label {
            font-size: .85rem; font-weight: 500; color: #475569;
            margin-bottom: 8px;
            display: flex; align-items: center; gap: 6px;
        }
        .field-label i { color: #0ea5e9; font-size: .9rem; }

        .field-input {
            width: 100%;
            border: 1.5px solid #e2e8f0; border-radius: 12px;
            padding: 12px 16px; font-size: .95rem; color: #1e293b;
            background: #f8fafc; font-family: 'Kanit', sans-serif;
            transition: all .2s; outline: none;
        }
        .field-input:focus {
            background: #fff; border-color: #0d9488;
            box-shadow: 0 0 0 4px rgba(13,148,136,.12);
        }
        .field-input[readonly] { background: #f1f5f9; color: #64748b; cursor: default; }
        .field-input.is-invalid { border-color: #ef4444; }
        .field-error { font-size: .78rem; color: #ef4444; margin-top: 4px; display: none; }
        .field-input.is-invalid + .field-error { display: block; }

        /* photo upload */
        .photo-area {
            border: 2px dashed #e2e8f0; border-radius: 16px;
            padding: 24px 16px; text-align: center; cursor: pointer;
            background: #f8fafc; position: relative; transition: all .2s;
        }
        .photo-area:hover, .photo-area.dragover { border-color: #0d9488; background: #f0fdfa; }
        .photo-area input[type=file] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer;
            width: 100%; height: 100%;
        }
        .pu-icon  { font-size: 2rem; color: #94a3b8; margin-bottom: 8px; }
        .pu-text  { font-size: .86rem; color: #64748b; }
        .pu-hint  { font-size: .74rem; color: #94a3b8; margin-top: 4px; }
        #photoPreview {
            width: 100%; max-height: 200px; object-fit: cover;
            border-radius: 12px; margin-top: 12px;
            display: none; box-shadow: 0 4px 12px rgba(0,0,0,.08);
        }
        #photoError { font-size: .78rem; color: #ef4444; margin-top: 4px; display: none; }

        /* ── Not in LINE ── */
        .nil-box { margin: 16px; text-align: center; padding: 40px 20px; }
        .nil-box i { font-size: 3rem; color: #06C755; margin-bottom: 14px; display: block; }
        .nil-box h5 { font-weight: 700; margin-bottom: 8px; }
        .nil-box p  { font-size: .88rem; color: #64748b; }

        /* ── Calc box ── */
        .calc-box {
            display: flex; align-items: center; gap: 8px;
            background: #f0fdf4; border: 1px solid #bbf7d0;
            border-radius: 12px; padding: 10px 14px;
            font-size: .88rem; font-weight: 500; color: #15803d;
            margin-top: 12px;
        }
        .calc-box.verified { background: #f0fdf4; border-color: #86efac; }
        .calc-box i { font-size: .9rem; flex-shrink: 0; }
        .calc-note  { font-size: .75rem; color: #64748b; font-weight: 400; }

        /* ── Footer ── */
        footer {
            margin-top: auto; padding: 24px 16px;
            text-align: center; font-size: .74rem; color: #94a3b8;
        }

        /* ── Bill Summary Card ── */
        .bill-summary-card { margin: 16px 16px 0; }
        .bsc-header {
            display: flex; align-items: center; gap: 8px;
            font-size: .95rem; font-weight: 600; color: #0f172a;
            margin-bottom: 16px;
        }
        .bsc-header i { color: #16a34a; font-size: 1rem; }
        .bsc-row {
            display: flex; align-items: center;
            padding: 11px 0; border-bottom: 1px solid #f1f5f9; gap: 8px;
        }
        .bsc-row:last-child { border-bottom: none; }
        .bsc-icon { font-size: .9rem; width: 18px; flex-shrink: 0; }
        .bsc-icon.water { color: #0ea5e9; }
        .bsc-icon.elec  { color: #f59e0b; }
        .bsc-name  { flex: 1; font-size: .88rem; color: #334155; font-weight: 500; }
        .bsc-units { font-size: .8rem; color: #94a3b8; min-width: 80px; text-align: center; }
        .bsc-amt   { font-size: .92rem; font-weight: 600; color: #1e293b; min-width: 70px; text-align: right; }
        .bsc-total-box {
            display: flex; justify-content: space-between; align-items: center;
            background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px;
            padding: 12px 14px; margin: 14px 0 0;
        }
        .bsc-total-label { font-size: .88rem; color: #15803d; font-weight: 600; }
        .bsc-total-amt   { font-size: 1.25rem; color: #15803d; font-weight: 700; }

        /* Payment info */
        .bsc-divider {
            border: none; border-top: 1px dashed #e2e8f0;
            margin: 18px 0 16px;
        }
        .bsc-pay-title {
            font-size: .82rem; color: #64748b; font-weight: 500; margin-bottom: 10px;
        }
        .bsc-pay-row {
            display: flex; justify-content: space-between;
            font-size: .86rem; padding: 5px 0;
        }
        .bsc-pay-key   { color: #94a3b8; }
        .bsc-pay-val   { color: #1e293b; font-weight: 500; }
        .bsc-qr-wrap   { text-align: center; margin-top: 16px; }
        .bsc-qr-wrap img {
            max-width: 180px; border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,.1);
        }
        .bsc-qr-hint { font-size: .74rem; color: #94a3b8; margin-top: 8px; }
        .btn-dl-qr {
            display: inline-flex; align-items: center; gap: 6px;
            margin-top: 12px; padding: 9px 20px;
            background: #f1f5f9; border-radius: 10px;
            font-size: .84rem; font-weight: 500; color: #475569;
            text-decoration: none; transition: background .15s;
        }
        .btn-dl-qr:hover { background: #e2e8f0; }

        /* ── Payment card ── */
        .payment-card { margin: 16px 16px 0; }
        .pc-header {
            display: flex; align-items: center; gap: 8px;
            font-size: .95rem; font-weight: 600; color: #0f172a;
            margin-bottom: 6px;
        }
        .pc-header i { color: #0d9488; }
        .pc-hint { font-size: .84rem; color: #64748b; margin-bottom: 16px; }

        .pc-status-box {
            display: flex; flex-direction: column; align-items: center;
            padding: 20px 16px; text-align: center; gap: 8px;
        }
        .pc-status-icon { font-size: 2.4rem; }
        .pc-status-title { font-size: 1rem; font-weight: 700; color: #0f172a; }
        .pc-status-sub   { font-size: .83rem; color: #64748b; }
        .pc-slip-thumb {
            width: 100%; max-height: 180px; object-fit: cover;
            border-radius: 12px; margin-top: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.1);
        }
        .pc-resend-btn {
            background: none; border: 1px solid #e2e8f0; border-radius: 10px;
            padding: 8px 18px; font-size: .82rem; font-weight: 500;
            color: #64748b; font-family: 'Kanit', sans-serif;
            cursor: pointer; margin-top: 12px; transition: background .15s;
        }
        .pc-resend-btn:hover { background: #f8fafc; }

        /* ── Util card ── */
        .util-card {
            background: #fff; border-radius: 16px; padding: 16px 14px;
            box-shadow: 0 1px 6px rgba(0,0,0,.05);
            cursor: pointer; transition: background .15s;
            display: flex; align-items: center; gap: 12px;
        }
        .util-card:hover { background: #f8fafc; }
        .uc-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: #f1f5f9; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
        }
        .uc-icon i { font-size: 1rem; color: #64748b; }
        .uc-title { font-size: .84rem; font-weight: 600; color: #334155; }
        .uc-sub   { font-size: .74rem; color: #94a3b8; }

        /* ── History bottom sheet ── */
        .history-panel {
            position: fixed; inset: 0; z-index: 300;
            display: flex; align-items: flex-end;
        }
        .hp-backdrop {
            position: absolute; inset: 0;
            background: rgba(0,0,0,.5);
        }
        .hp-sheet {
            position: relative; width: 100%;
            background: white; border-radius: 20px 20px 0 0;
            max-height: 75vh; display: flex; flex-direction: column;
            animation: slideUp .28s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(100%); }
            to   { transform: translateY(0); }
        }
        .hp-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 20px 14px; border-bottom: 1px solid #f1f5f9; flex-shrink: 0;
        }
        .hp-title { font-size: .95rem; font-weight: 700; color: #0f172a; }
        .hp-close {
            background: #f1f5f9; border: none; border-radius: 8px;
            width: 30px; height: 30px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: .85rem; color: #64748b;
        }
        .hp-body { overflow-y: auto; padding: 12px 20px 28px; flex: 1; }
        .hp-item {
            padding: 14px 0; border-bottom: 1px solid #f1f5f9;
        }
        .hp-item:last-child { border-bottom: none; }
        .hp-item-head {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 8px;
        }
        .hp-month { font-size: .9rem; font-weight: 600; color: #0f172a; }
        .hp-badge-confirmed {
            background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0;
            border-radius: 20px; padding: 2px 10px; font-size: .72rem; font-weight: 600;
        }
        .hp-badge-pending {
            background: #fffbeb; color: #d97706; border: 1px solid #fde68a;
            border-radius: 20px; padding: 2px 10px; font-size: .72rem; font-weight: 600;
        }
        .hp-badge-none {
            background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0;
            border-radius: 20px; padding: 2px 10px; font-size: .72rem; font-weight: 600;
        }
        .hp-detail-row {
            display: flex; justify-content: space-between;
            font-size: .82rem; color: #64748b; padding: 2px 0;
        }
        .hp-total-row {
            display: flex; justify-content: space-between;
            font-size: .88rem; font-weight: 700;
            color: #0f172a; margin-top: 6px;
        }
        .hp-empty {
            text-align: center; padding: 40px 20px;
            color: #94a3b8; font-size: .88rem;
        }

        /* ── Utility ── */
        .d-none { display: none !important; }
        .mb-4   { margin-bottom: 16px; }
    </style>
</head>
<body>
<div class="app-wrap">

    <!-- Loading overlay -->
    <div id="loadingOverlay">
        <div class="lo-spinner"></div>
        <div id="loadingText">กำลังเริ่มต้น LINE LIFF...</div>
    </div>

    <!-- Not in LINE -->
    <div id="notInLineBox" class="d-none">
        <div class="nil-box">
            <i class="bi bi-chat-quote-fill"></i>
            <h5>กรุณาเข้าใช้งานผ่าน LINE</h5>
            <p>หน้านี้ต้องเปิดผ่านแอปพลิเคชัน LINE เท่านั้น</p>
        </div>
    </div>

    <!-- Main content -->
    <div id="mainContent" class="d-none">

        <!-- Hero card -->
        <div class="hero-card card">
            <div class="bill-period-row">
                <div class="bill-icon-wrap">
                    <i class="bi bi-droplet-fill"></i>
                </div>
                <div class="bill-period-info">
                    <div class="bill-period-label" id="billPeriodText">บิลประจำเดือน –</div>
                </div>
                <span id="waterStatusBadge"></span>
            </div>

            <!-- hero body (swapped by JS) -->
            <div id="heroBody"></div>
        </div>

        <!-- Bill summary card (shown when water verified + elec entered) -->
        <div class="bill-summary-card card d-none" id="billSummaryCard">
            <div class="bsc-header">
                <i class="bi bi-receipt-cutoff"></i> สรุปค่าใช้จ่ายเดือนนี้
            </div>
            <div id="bscRows">
                <div class="bsc-row">
                    <i class="bsc-icon water bi bi-droplet-fill"></i>
                    <span class="bsc-name">ค่าน้ำ</span>
                    <span class="bsc-units" id="bscWaterUnits">–</span>
                    <span class="bsc-amt"   id="bscWaterAmt">– บาท</span>
                </div>
                <div class="bsc-row">
                    <i class="bsc-icon elec bi bi-lightning-charge-fill"></i>
                    <span class="bsc-name">ค่าไฟ</span>
                    <span class="bsc-units" id="bscElecUnits">–</span>
                    <span class="bsc-amt"   id="bscElecAmt">– บาท</span>
                </div>
            </div>
            <div class="bsc-total-box">
                <span class="bsc-total-label">รวมที่ต้องชำระ</span>
                <span class="bsc-total-amt" id="bscTotal">– บาท</span>
            </div>
            <hr class="bsc-divider">
            <div class="bsc-pay-title">ช่องทางชำระเงิน</div>
            <div id="bscPayInfo"></div>
        </div>

        <!-- Payment slip card -->
        <div class="payment-card card d-none" id="paymentCard">

            <!-- state: upload -->
            <div id="payState_upload">
                <div class="pc-header"><i class="bi bi-send-check-fill"></i> แจ้งการชำระเงิน</div>
                <p class="pc-hint">อัปโหลดสลิปการโอนเงินเพื่อแจ้งให้ผู้ดูแลตรวจสอบ</p>
                <div class="photo-area" id="slipUploadArea">
                    <input type="file" id="slipPhotoInput" accept=".jpg,.jpeg,.png" capture="environment">
                    <div id="slipPlaceholder">
                        <div class="pu-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                        <div class="pu-text">แตะเพื่อถ่ายหรืออัปโหลดสลิป</div>
                        <div class="pu-hint">รองรับ .jpg, .png · สูงสุด 5 MB</div>
                    </div>
                    <img id="slipPreview" src="" alt="สลิปโอนเงิน" style="width:100%;max-height:200px;object-fit:cover;border-radius:12px;margin-top:12px;display:none;">
                </div>
                <div id="slipError" style="font-size:.78rem;color:#ef4444;margin-top:4px;display:none;">กรุณาเลือกภาพสลิปการโอนเงิน</div>
                <button type="button" id="submitSlipBtn" class="btn-cta teal" style="margin-top:16px;">
                    <i class="bi bi-send-fill"></i> ส่งสลิปการชำระเงิน
                </button>
            </div>

            <!-- state: pending -->
            <div id="payState_pending" class="d-none">
                <div class="pc-status-box">
                    <div class="pc-status-icon">🕐</div>
                    <div class="pc-status-title">รอตรวจสอบการชำระเงิน</div>
                    <div class="pc-status-sub">ผู้ดูแลจะตรวจสอบสลิปและแจ้งผลในภายหลัง</div>
                    <img id="paySlipThumb" src="" alt="สลิป" class="pc-slip-thumb">
                    <button type="button" class="pc-resend-btn" id="payResendBtn">
                        <i class="bi bi-arrow-clockwise"></i> ส่งสลิปใหม่
                    </button>
                </div>
            </div>

            <!-- state: confirmed -->
            <div id="payState_confirmed" class="d-none">
                <div class="pc-status-box">
                    <div class="pc-status-icon">✅</div>
                    <div class="pc-status-title">ชำระเงินเรียบร้อยแล้ว</div>
                    <div class="pc-status-sub">ผู้ดูแลยืนยันการรับเงินเรียบร้อย</div>
                    <img id="paySlipThumbConfirmed" src="" alt="สลิป" class="pc-slip-thumb">
                </div>
            </div>

        </div>

        <!-- Form card (hidden until CTA click) -->
        <div class="form-card card d-none" id="meterFormCard">
            <div class="form-section-title">
                <i class="bi bi-droplet"></i> กรอกค่ามิเตอร์น้ำ
            </div>

            <div class="mb-4">
                <div class="field-label"><i class="bi bi-door-open"></i> หมายเลขห้องพัก</div>
                <input type="text" id="roomNumberDisplay" class="field-input" readonly>
            </div>

            <div class="mb-4">
                <div class="field-label"><i class="bi bi-droplet"></i> ค่ามิเตอร์น้ำปัจจุบัน (หน่วย)</div>
                <input type="number" id="waterCurrInput" class="field-input"
                    placeholder="เช่น 1234.56" min="0" step="0.01" inputmode="decimal">
                <div class="field-error" id="waterCurrError">กรุณากรอกค่ามิเตอร์น้ำที่ถูกต้อง</div>
            </div>

            <div class="mb-4">
                <div class="field-label"><i class="bi bi-camera"></i> รูปถ่ายหน้าปัดมิเตอร์</div>
                <div class="photo-area" id="photoUploadArea">
                    <input type="file" id="meterPhotoInput" accept=".jpg,.jpeg,.png" capture="environment">
                    <div id="photoPlaceholder">
                        <div class="pu-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                        <div class="pu-text">แตะเพื่อถ่ายหรืออัปโหลดรูปภาพ</div>
                        <div class="pu-hint">รองรับ .jpg, .png · สูงสุด 5 MB</div>
                    </div>
                    <img id="photoPreview" src="" alt="ตัวอย่างรูปมิเตอร์">
                </div>
                <div id="photoError">กรุณาเลือกรูปถ่ายหน้าปัดมิเตอร์</div>
            </div>

            <button type="button" id="submitBtn" class="btn-cta teal">
                <i class="bi bi-send-fill"></i> ส่งค่ามิเตอร์
            </button>
        </div>

        <!-- Summary section -->
        <div class="section-wrap">
            <div class="section-title">สรุปมิเตอร์เดือนนี้</div>
            <div class="cards-row">
                <div class="meter-card water">
                    <div class="mc-header">
                        <i class="bi bi-droplet-fill"></i>
                        <span class="mc-label">ค่าน้ำ</span>
                    </div>
                    <div class="mc-value" id="waterCardStatus">–</div>
                </div>
                <div class="meter-card elec">
                    <div class="mc-header">
                        <i class="bi bi-lightning-charge-fill"></i>
                        <span class="mc-label">ค่าไฟ</span>
                    </div>
                    <div class="mc-value" id="elecCardStatus">รอผู้ดูแลกรอกเลข</div>
                </div>
            </div>
            <div class="util-card" onclick="openHistory()">
                <div class="uc-icon"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="uc-title">ประวัติการชำระเงิน</div>
                    <div class="uc-sub">ดูย้อนหลังทุกเดือน</div>
                </div>
                <i class="bi bi-chevron-right" style="margin-left:auto;color:#cbd5e1;font-size:.85rem;"></i>
            </div>
        </div>

        <!-- History bottom sheet -->
        <div id="historyPanel" class="history-panel d-none">
            <div class="hp-backdrop" onclick="closeHistory()"></div>
            <div class="hp-sheet">
                <div class="hp-header">
                    <span class="hp-title"><i class="bi bi-clock-history me-2"></i>ประวัติการชำระเงิน</span>
                    <button class="hp-close" onclick="closeHistory()"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="hp-body" id="historyBody">
                    <div class="hp-empty">กำลังโหลด...</div>
                </div>
            </div>
        </div>

    </div><!-- /mainContent -->

    <footer>น้องเก็บบิล · ระบบจัดการบิลหอพัก เพื่อความโปร่งใส ตรวจสอบได้</footer>

</div><!-- /app-wrap -->

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script charset="utf-8" src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>

<script>
window.onerror = function(msg, src, line) {
    document.getElementById('loadingText').textContent = '❌ JS Error: ' + msg + ' (L' + line + ')';
    return false;
};

const DAY_START = <?= SUBMIT_DAY_START ?>;
const DAY_END   = <?= SUBMIT_DAY_END ?>;

let currentUser = null; // { line_uid, room_id, room_number, cycle_id, cycle_label }

// ── Loading helpers ──────────────────────────────────────────────────────
function showLoading(txt) {
    const ov = document.getElementById('loadingOverlay');
    ov.style.display = 'flex';
    if (txt) document.getElementById('loadingText').textContent = txt;
}
function hideLoading() {
    document.getElementById('loadingOverlay').style.display = 'none';
}

// ── คำนวณหน่วยและค่าน้ำ ─────────────────────────────────────────────────
function calcWater(curr, prev, rate) {
    if (curr == null || prev == null) return null;
    const units = parseFloat(curr) - parseFloat(prev);
    if (units < 0) return null;
    const amount = rate != null ? units * parseFloat(rate) : null;
    return { units, amount };
}

function calcSummaryText(curr, prev, rate) {
    const c = calcWater(curr, prev, rate);
    if (!c) return null;
    let txt = c.units.toFixed(2) + ' หน่วย';
    if (c.amount != null) txt += ' · ≈' + c.amount.toFixed(0) + ' บาท';
    return txt;
}

// ── Render hero state ────────────────────────────────────────────────────
function renderHero(state, data) {
    data = data || {};
    const badge      = document.getElementById('waterStatusBadge');
    const heroBody   = document.getElementById('heroBody');
    const periodText = document.getElementById('billPeriodText');

    if (data.cycle_label) {
        periodText.textContent = 'บิลประจำเดือน ' + data.cycle_label;
    }

    const prevTxt = data.water_prev
        ? ' · เลขครั้งก่อน ' + parseFloat(data.water_prev).toFixed(0) + ' หน่วย'
        : '';

    // คำนวณสำหรับ state ที่มี water_curr แล้ว
    const calcTxt = calcSummaryText(data.water_curr, data.water_prev, data.rate_water);

    if (state === 'outside_window') {
        badge.innerHTML = '<span class="badge-pill badge-outside"><span class="dot"></span>นอกช่วงเวลา</span>';
        heroBody.innerHTML = `
            <div class="outside-msg">
                <div class="om-icon"><i class="bi bi-clock"></i></div>
                <h3>ยังไม่ถึงช่วงเวลาส่งค่ามิเตอร์</h3>
                <p>ระบบเปิดรับค่าน้ำเฉพาะ<br><strong>วันที่ ${DAY_START}–${DAY_END} ของทุกเดือน</strong><br>กรุณากลับมาใหม่ในช่วงเวลาดังกล่าว</p>
            </div>`;
        document.getElementById('waterCardStatus').textContent = 'ยังไม่ถึงรอบส่ง';

    } else if (state === 'pending') {
        badge.innerHTML = '<span class="badge-pill badge-pending"><span class="dot"></span>ค้างส่งเลขน้ำ</span>';
        heroBody.innerHTML = `
            <h1 class="hero-heading">ถึงเวลาส่งเลขมิเตอร์น้ำ</h1>
            <p class="hero-sub">เปิดรับ วันที่ ${DAY_START}–${DAY_END} ของทุกเดือน${prevTxt}</p>
            <button class="btn-cta teal" id="ctaBtn">
                <i class="bi bi-droplet-fill"></i> ถ่ายรูป &amp; ส่งเลขน้ำ
            </button>`;
        document.getElementById('ctaBtn').addEventListener('click', openForm);
        document.getElementById('waterCardStatus').textContent = 'ยังไม่ได้ส่งเลข';

    } else if (state === 'review') {
        badge.innerHTML = '<span class="badge-pill badge-review"><span class="dot"></span>รออนุมัติ</span>';
        const calcLine = calcTxt
            ? `<div class="calc-box"><i class="bi bi-calculator"></i> ${calcTxt} <span class="calc-note">(ประมาณการ)</span></div>`
            : '';
        heroBody.innerHTML = `
            <h1 class="hero-heading">ส่งค่ามิเตอร์แล้ว</h1>
            <p class="hero-sub">เลขที่ส่ง: <strong>${parseFloat(data.water_curr).toFixed(2)}</strong>${data.water_prev != null ? ' · ก่อนหน้า: ' + parseFloat(data.water_prev).toFixed(2) : ''}</p>
            ${calcLine}
            <button class="btn-cta gray" id="ctaBtn" style="margin-top:14px;">
                <i class="bi bi-pencil-fill"></i> แก้ไขค่ามิเตอร์
            </button>`;
        document.getElementById('ctaBtn').addEventListener('click', openForm);
        document.getElementById('waterCardStatus').textContent = calcTxt ? calcTxt + ' (รออนุมัติ)' : 'รออนุมัติ';

    } else if (state === 'verified') {
        badge.innerHTML = '<span class="badge-pill badge-verified"><span class="dot"></span>ยืนยันแล้ว</span>';
        const calcLine = calcTxt
            ? `<div class="calc-box verified"><i class="bi bi-calculator"></i> ${calcTxt}</div>`
            : '';
        heroBody.innerHTML = `
            <h1 class="hero-heading">ยืนยันค่ามิเตอร์แล้ว</h1>
            <p class="hero-sub">เลขที่ส่ง: <strong>${parseFloat(data.water_curr).toFixed(2)}</strong>${data.water_prev != null ? ' · ก่อนหน้า: ' + parseFloat(data.water_prev).toFixed(2) : ''}</p>
            ${calcLine}`;
        document.getElementById('waterCardStatus').textContent = calcTxt || ('ยืนยันแล้ว · ' + parseFloat(data.water_curr).toFixed(2) + ' หน่วย');

    } else if (state === 'reject') {
        badge.innerHTML = '<span class="badge-pill badge-reject"><span class="dot"></span>ส่งคืน</span>';
        const reason = data.water_reject_reason ? escapeHtml(data.water_reject_reason) : '';
        heroBody.innerHTML = `
            <h1 class="hero-heading">กรุณาส่งค่ามิเตอร์ใหม่</h1>
            <p class="hero-sub">${reason ? 'เหตุผล: ' + reason : 'แอดมินส่งคืนค่ามิเตอร์กลับมา'}</p>
            <button class="btn-cta red" id="ctaBtn">
                <i class="bi bi-arrow-clockwise"></i> ส่งใหม่อีกครั้ง
            </button>`;
        document.getElementById('ctaBtn').addEventListener('click', openForm);
        document.getElementById('waterCardStatus').textContent = 'ถูกส่งคืน';
    }
}

function openForm() {
    const card = document.getElementById('meterFormCard');
    card.classList.remove('d-none');
    card.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ── Fetch student + render ───────────────────────────────────────────────
function fetchStudentAndRender(lineUid, displayName, pictureUrl) {
    fetch('get_student.php?line_uid=' + encodeURIComponent(lineUid))
        .then(r => r.json())
        .then(data => {
            hideLoading();

            if (!data.success || !data.student) {
                Swal.fire({
                    title: 'ยังไม่ได้ลงทะเบียนหอพัก ⚠️',
                    text:  'ระบบไม่พบข้อมูลหอพักที่เชื่อมโยงกับบัญชี LINE ของท่าน กรุณาลงทะเบียนก่อน',
                    icon:  'warning',
                    confirmButtonText: 'ไปหน้าลงทะเบียน ➡️',
                    confirmButtonColor: '#06C755',
                    allowOutsideClick: false, allowEscapeKey: false
                }).then(() => {
                    var n = encodeURIComponent(displayName || '');
                    var i = encodeURIComponent(pictureUrl  || '');
                    window.location.href = 'register.php?line_uid=' + lineUid + '&line_name=' + n + '&line_img=' + i + '&redirect=meter_submit.php';
                });
                return;
            }

            var s = data.student;
            currentUser = { line_uid: lineUid, room_id: s.room_id, room_number: s.room_number };

            document.getElementById('mainContent').classList.remove('d-none');
            showLoading('กำลังโหลดข้อมูลมิเตอร์...');

            fetch('meter_submit.php?action=get_meter_status&room_id=' + s.room_id)
                .then(r => r.json())
                .then(meterData => {
                    hideLoading();
                    if (meterData.success) {
                        currentUser.cycle_id    = meterData.cycle_id;
                        currentUser.cycle_label = meterData.cycle_label;
                        currentUser.water_prev  = meterData.water_prev != null ? parseFloat(meterData.water_prev) : null;

                        const sub = meterData.submission;
                        const waterVerified = sub && sub.water_status === 'verified';

                        // อัปเดตการ์ดค่าไฟ
                        const elecEl = document.getElementById('elecCardStatus');
                        if (meterData.elec_entered && meterData.elec_curr != null && meterData.elec_prev != null) {
                            const units = parseFloat(meterData.elec_curr) - parseFloat(meterData.elec_prev);
                            let txt = units.toFixed(2) + ' หน่วย';
                            if (meterData.rate_elec) txt += ' · ≈' + (units * parseFloat(meterData.rate_elec)).toFixed(0) + ' บาท';
                            elecEl.textContent = txt;
                        } else {
                            elecEl.textContent = 'รอผู้ดูแลกรอกเลข';
                        }

                        const pb = meterData.pending_bill;

                        if (pb) {
                            // มีบิลค้างชำระ (รอบปัจจุบัน หรือรอบก่อนหน้า) → แสดงสรุปบิล+ชำระเงินเสมอ
                            const pbMeta = {
                                water_prev:  pb.water_prev,
                                elec_curr:   pb.elec_curr,
                                elec_prev:   pb.elec_prev,
                                rate_water:  meterData.rate_water,
                                rate_elec:   meterData.rate_elec,
                                bank_name:   meterData.bank_name,
                                bank_holder: meterData.bank_holder,
                                bank_acc:    meterData.bank_acc,
                                promptpay:   meterData.promptpay,
                                qr_image:    meterData.qr_image,
                                cycle_label: pb.cycle_label,
                            };
                            const pbSub = {
                                water_curr:           pb.water_curr,
                                water_status:         'verified',
                                payment_status:       pb.payment_status       || null,
                                payment_slip:         pb.payment_slip         || null,
                                payment_submitted_at: pb.payment_submitted_at || null,
                            };
                            renderBillSummary(pbMeta, pbSub);
                        } else if (waterVerified && meterData.elec_entered) {
                            // รอบปัจจุบัน ยืนยันครบทั้งน้ำและไฟ → แสดงสรุปบิล (fallback กรณี pending_bill เป็น null)
                            renderBillSummary(meterData, sub);
                        } else {
                            // ยังไม่ครบ → เช็คช่วงเวลา
                            const hData = {
                                cycle_label:         meterData.cycle_label,
                                water_prev:          meterData.water_prev,
                                rate_water:          meterData.rate_water,
                                water_curr:          sub ? sub.water_curr : null,
                                water_reject_reason: sub ? sub.water_reject_reason : null,
                            };

                            if (!isInWindow()) {
                                renderHero('outside_window', { cycle_label: meterData.cycle_label });
                            } else if (!sub) {
                                renderHero('pending', hData);
                            } else {
                                renderHero(sub.water_status, hData);
                            }

                            if (sub) {
                                document.getElementById('waterCurrInput').value = sub.water_curr || '';
                            }
                        }
                    } else {
                        if (!isInWindow()) {
                            renderHero('outside_window', { cycle_label: null });
                        } else {
                            renderHero('pending', { cycle_label: null });
                        }
                    }

                    document.getElementById('roomNumberDisplay').value = 'ห้อง ' + s.room_number;
                })
                .catch(err => {
                    hideLoading();
                    console.error('get_meter_status:', err);
                    renderHero('pending', { cycle_label: null });
                    document.getElementById('roomNumberDisplay').value = 'ห้อง ' + s.room_number;
                });
        })
        .catch(err => {
            hideLoading();
            document.getElementById('loadingText').textContent = '❌ ตรวจสอบบัญชีไม่สำเร็จ (' + err.message + ')';
            document.getElementById('loadingOverlay').style.display = 'flex';
        });
}

function isInWindow() {
    var d = new Date().getDate();
    return d >= DAY_START && d <= DAY_END;
}

// ── Photo input ──────────────────────────────────────────────────────────
document.getElementById('meterPhotoInput').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        const prev = document.getElementById('photoPreview');
        prev.src = e.target.result;
        prev.style.display = 'block';
        document.getElementById('photoPlaceholder').style.display = 'none';
        document.getElementById('photoError').style.display = 'none';
    };
    reader.readAsDataURL(file);
});

const uploadArea = document.getElementById('photoUploadArea');
uploadArea.addEventListener('dragover',  e => { e.preventDefault(); uploadArea.classList.add('dragover'); });
uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('dragover'));
uploadArea.addEventListener('drop', e => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        document.getElementById('meterPhotoInput').files = e.dataTransfer.files;
        document.getElementById('meterPhotoInput').dispatchEvent(new Event('change'));
    }
});

// real-time validation บน water input
document.getElementById('waterCurrInput').addEventListener('input', function() {
    const val  = parseFloat(this.value);
    const errEl = document.getElementById('waterCurrError');
    if (this.value.trim() && !isNaN(val) && currentUser && currentUser.water_prev != null && val < currentUser.water_prev) {
        errEl.textContent = `ค่าปัจจุบันต้องไม่ต่ำกว่าค่าครั้งก่อน (${currentUser.water_prev.toFixed(2)})`;
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});

// ── Submit ───────────────────────────────────────────────────────────────
document.getElementById('submitBtn').addEventListener('click', async function() {
    const waterInput = document.getElementById('waterCurrInput');
    const photoInput = document.getElementById('meterPhotoInput');
    let valid = true;

    waterInput.classList.remove('is-invalid');
    document.getElementById('waterCurrError').textContent = 'กรุณากรอกค่ามิเตอร์น้ำที่ถูกต้อง';
    document.getElementById('photoError').style.display = 'none';

    const val = parseFloat(waterInput.value);
    if (!waterInput.value.trim() || isNaN(val) || val < 0) {
        waterInput.classList.add('is-invalid');
        valid = false;
    } else if (currentUser.water_prev != null && val < currentUser.water_prev) {
        document.getElementById('waterCurrError').textContent =
            `ค่าปัจจุบันต้องไม่ต่ำกว่าค่าครั้งก่อน (${currentUser.water_prev.toFixed(2)})`;
        waterInput.classList.add('is-invalid');
        valid = false;
    }
    if (!photoInput.files.length) {
        document.getElementById('photoError').style.display = 'block';
        valid = false;
    }
    if (!valid) return;

    const conf = await Swal.fire({
        title: 'ยืนยันการส่งค่ามิเตอร์?',
        html:  `<div style="font-size:.92rem;">ห้อง <strong>${currentUser.room_number}</strong> · ค่าน้ำ <strong>${parseFloat(waterInput.value).toFixed(2)}</strong> หน่วย</div>`,
        icon:  'question',
        showCancelButton:   true,
        confirmButtonText:  'ยืนยัน ส่งเลย',
        cancelButtonText:   'ยกเลิก',
        confirmButtonColor: '#0d9488',
        cancelButtonColor:  '#94a3b8',
    });
    if (!conf.isConfirmed) return;

    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span style="display:inline-block;width:18px;height:18px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;margin-right:8px;"></span>กำลังส่งข้อมูล...';

    const fd = new FormData();
    fd.append('action',      'submit_meter');
    fd.append('line_uid',    currentUser.line_uid);
    fd.append('room_id',     currentUser.room_id);
    fd.append('water_curr',  waterInput.value);
    fd.append('meter_photo', photoInput.files[0]);

    try {
        const res  = await fetch('meter_submit.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            await Swal.fire({
                title: 'ส่งค่ามิเตอร์สำเร็จ! 💧',
                html:  'ระบบได้รับข้อมูลของคุณแล้ว<br><small style="color:#64748b;">แอดมินจะตรวจสอบและแจ้งผลในภายหลัง</small>',
                icon:  'success',
                confirmButtonText:  'รับทราบ',
                confirmButtonColor: '#0d9488',
                allowOutsideClick:  false,
            });
            window.location.reload();
        } else {
            Swal.fire({ title:'เกิดข้อผิดพลาด', text: data.message || 'กรุณาลองใหม่', icon:'error', confirmButtonColor:'#ef4444' });
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send-fill"></i> ส่งค่ามิเตอร์';
        }
    } catch (err) {
        Swal.fire({ title:'เกิดข้อผิดพลาด', text:'ไม่สามารถเชื่อมต่อระบบได้', icon:'error', confirmButtonColor:'#ef4444' });
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send-fill"></i> ส่งค่ามิเตอร์';
    }
});

// ── Helpers ──────────────────────────────────────────────────────────────
function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Payment Card ─────────────────────────────────────────────────────────
function renderPaymentCard(sub) {
    const card = document.getElementById('paymentCard');
    card.classList.remove('d-none');

    const status = sub ? sub.payment_status : null;

    if (status === 'confirmed') {
        document.getElementById('payState_upload').classList.add('d-none');
        document.getElementById('payState_pending').classList.add('d-none');
        document.getElementById('payState_confirmed').classList.remove('d-none');
        if (sub.payment_slip) {
            document.getElementById('paySlipThumbConfirmed').src = escapeHtml(sub.payment_slip);
        }
    } else if (status === 'pending') {
        document.getElementById('payState_upload').classList.add('d-none');
        document.getElementById('payState_confirmed').classList.add('d-none');
        document.getElementById('payState_pending').classList.remove('d-none');
        if (sub.payment_slip) {
            document.getElementById('paySlipThumb').src = escapeHtml(sub.payment_slip);
        }
    } else {
        document.getElementById('payState_pending').classList.add('d-none');
        document.getElementById('payState_confirmed').classList.add('d-none');
        document.getElementById('payState_upload').classList.remove('d-none');
    }
}

// ปุ่ม "ส่งสลิปใหม่" → กลับไป state upload
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('payResendBtn').addEventListener('click', function() {
        document.getElementById('payState_pending').classList.add('d-none');
        document.getElementById('payState_upload').classList.remove('d-none');
    });

    // preview สลิป
    document.getElementById('slipPhotoInput').addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            const prev = document.getElementById('slipPreview');
            prev.src = e.target.result;
            prev.style.display = 'block';
            document.getElementById('slipPlaceholder').style.display = 'none';
            document.getElementById('slipError').style.display = 'none';
        };
        reader.readAsDataURL(file);
    });

    // ส่งสลิป
    document.getElementById('submitSlipBtn').addEventListener('click', async function() {
        const photoInput = document.getElementById('slipPhotoInput');
        if (!photoInput.files.length) {
            document.getElementById('slipError').style.display = 'block';
            return;
        }

        const conf = await Swal.fire({
            title: 'ยืนยันส่งสลิปการชำระเงิน?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'ยืนยัน',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#0d9488',
            cancelButtonColor: '#94a3b8',
        });
        if (!conf.isConfirmed) return;

        const btn = document.getElementById('submitSlipBtn');
        btn.disabled = true;
        btn.innerHTML = '<span style="display:inline-block;width:18px;height:18px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;margin-right:8px;"></span>กำลังส่ง...';

        const fd = new FormData();
        fd.append('action',     'submit_payment');
        fd.append('line_uid',   currentUser.line_uid);
        fd.append('room_id',    currentUser.room_id);
        fd.append('slip_photo', photoInput.files[0]);

        try {
            const res  = await fetch('meter_submit.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                document.getElementById('payState_upload').classList.add('d-none');
                document.getElementById('payState_pending').classList.remove('d-none');
                if (data.slip_url) {
                    document.getElementById('paySlipThumb').src = data.slip_url;
                }
                Swal.fire({
                    title: 'ส่งสลิปสำเร็จ!',
                    text: 'ผู้ดูแลจะตรวจสอบและแจ้งผลในภายหลัง',
                    icon: 'success',
                    confirmButtonColor: '#0d9488',
                });
            } else {
                Swal.fire({ title: 'เกิดข้อผิดพลาด', text: data.message || 'กรุณาลองใหม่', icon: 'error', confirmButtonColor: '#ef4444' });
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send-fill"></i> ส่งสลิปการชำระเงิน';
            }
        } catch (err) {
            Swal.fire({ title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อระบบได้', icon: 'error', confirmButtonColor: '#ef4444' });
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send-fill"></i> ส่งสลิปการชำระเงิน';
        }
    });
});

// ── Bill Summary ─────────────────────────────────────────────────────────
function renderBillSummary(meterData, sub) {
    const fmt  = n => parseFloat(n).toFixed(2);
    const fmtB = n => Number(n).toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

    // ค่าน้ำ
    const wUnits = parseFloat(sub.water_curr) - parseFloat(meterData.water_prev || 0);
    const wAmt   = meterData.rate_water ? wUnits * parseFloat(meterData.rate_water) : null;
    document.getElementById('bscWaterUnits').textContent = fmt(wUnits) + ' หน่วย';
    document.getElementById('bscWaterAmt').textContent   = wAmt != null ? fmtB(wAmt) + ' บาท' : '– บาท';

    // อัปเดตการ์ดสรุปค่าน้ำ (section-wrap)
    let wCardTxt = wUnits.toFixed(2) + ' หน่วย';
    if (wAmt != null) wCardTxt += ' · ≈' + fmtB(wAmt) + ' บาท';
    document.getElementById('waterCardStatus').textContent = wCardTxt;

    // ค่าไฟ
    const eUnits = parseFloat(meterData.elec_curr) - parseFloat(meterData.elec_prev || 0);
    const eAmt   = meterData.rate_elec ? eUnits * parseFloat(meterData.rate_elec) : null;
    document.getElementById('bscElecUnits').textContent = fmt(eUnits) + ' หน่วย';
    document.getElementById('bscElecAmt').textContent   = eAmt != null ? fmtB(eAmt) + ' บาท' : '– บาท';

    // รวม
    const total = (wAmt ?? 0) + (eAmt ?? 0);
    document.getElementById('bscTotal').textContent =
        (wAmt != null || eAmt != null) ? fmtB(total) + ' บาท' : '– บาท';

    // ข้อมูลชำระเงิน
    const payEl = document.getElementById('bscPayInfo');
    const rows = [];
    if (meterData.bank_name)   rows.push(['ธนาคาร', escapeHtml(meterData.bank_name)]);
    if (meterData.bank_holder) rows.push(['ชื่อบัญชี', escapeHtml(meterData.bank_holder)]);
    if (meterData.bank_acc)    rows.push(['เลขบัญชี', escapeHtml(meterData.bank_acc)]);
    if (meterData.promptpay)   rows.push(['พร้อมเพย์', escapeHtml(meterData.promptpay)]);

    payEl.innerHTML = rows.map(([k, v]) =>
        `<div class="bsc-pay-row"><span class="bsc-pay-key">${k}</span><span class="bsc-pay-val">${v}</span></div>`
    ).join('') + (meterData.qr_image
        ? `<div class="bsc-qr-wrap">
               <img src="${escapeHtml(meterData.qr_image)}" alt="QR Code">
               <div class="bsc-qr-hint">สแกน QR เพื่อชำระเงิน</div>
               <a href="${escapeHtml(meterData.qr_image)}" download class="btn-dl-qr">
                   <i class="bi bi-download"></i> บันทึก QR Code
               </a>
           </div>`
        : '');

    document.querySelector('.hero-card').classList.add('d-none');

    if (sub && sub.payment_status === 'confirmed') {
        document.getElementById('billSummaryCard').classList.add('d-none');
    } else {
        document.getElementById('billSummaryCard').classList.remove('d-none');
    }

    renderPaymentCard(sub);
}

// ── Payment History ───────────────────────────────────────────────────────
function openHistory() {
    if (!currentUser || !currentUser.room_id) return;
    const panel = document.getElementById('historyPanel');
    document.getElementById('historyBody').innerHTML = '<div class="hp-empty">กำลังโหลด...</div>';
    panel.classList.remove('d-none');

    fetch('meter_submit.php?action=get_payment_history&room_id=' + currentUser.room_id)
        .then(r => r.json())
        .then(data => {
            const body = document.getElementById('historyBody');
            if (!data.success || !data.history.length) {
                body.innerHTML = '<div class="hp-empty"><i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:8px;"></i>ยังไม่มีประวัติการชำระเงิน</div>';
                return;
            }
            body.innerHTML = data.history.map(h => {
                const fmtB = n => n != null ? '฿' + Math.round(n).toLocaleString('th-TH') : '–';
                const fmtU = n => n != null ? parseFloat(n).toFixed(2) + ' หน่วย' : '–';

                let badge = '';
                if (h.payment_status === 'confirmed') {
                    badge = '<span class="hp-badge-confirmed">ชำระแล้ว ✓</span>';
                } else if (h.payment_status === 'pending') {
                    badge = '<span class="hp-badge-pending">รอตรวจสอบ</span>';
                } else {
                    badge = '<span class="hp-badge-none">ยังไม่ชำระ</span>';
                }

                return `<div class="hp-item">
                    <div class="hp-item-head">
                        <span class="hp-month">${escapeHtml(h.label)}</span>
                        ${badge}
                    </div>
                    <div class="hp-detail-row">
                        <span><i class="bi bi-droplet-fill me-1" style="color:#0ea5e9;"></i>ค่าน้ำ</span>
                        <span>${fmtU(h.water_units)} &nbsp; ${fmtB(h.water_amt)}</span>
                    </div>
                    <div class="hp-detail-row">
                        <span><i class="bi bi-lightning-charge-fill me-1" style="color:#f59e0b;"></i>ค่าไฟ</span>
                        <span>${fmtU(h.elec_units)} &nbsp; ${fmtB(h.elec_amt)}</span>
                    </div>
                    <div class="hp-total-row">
                        <span>รวม</span>
                        <span style="color:#16a34a;">${fmtB(h.total)}</span>
                    </div>
                </div>`;
            }).join('');
        })
        .catch(() => {
            document.getElementById('historyBody').innerHTML = '<div class="hp-empty">โหลดข้อมูลไม่สำเร็จ กรุณาลองใหม่</div>';
        });
}

function closeHistory() {
    document.getElementById('historyPanel').classList.add('d-none');
}

// ── LIFF Init ────────────────────────────────────────────────────────────
const liffId = "<?= LIFF_ID ?>";

document.addEventListener('DOMContentLoaded', function() {
    if (!liffId || liffId === 'YOUR_LIFF_ID') {
        hideLoading();
        document.getElementById('notInLineBox').classList.remove('d-none');
        return;
    }

    liff.init({ liffId: liffId })
        .then(function() {
            if (liff.isInClient() || liff.isLoggedIn()) {
                liff.getProfile()
                    .then(function(profile) {
                        fetchStudentAndRender(profile.userId, profile.displayName, profile.pictureUrl || '');
                    })
                    .catch(function(err) {
                        console.error('getProfile error:', err);
                        hideLoading();
                        document.getElementById('notInLineBox').classList.remove('d-none');
                    });
            } else {
                liff.login();
            }
        })
        .catch(function(err) {
            console.error('LIFF init failed:', err);
            hideLoading();
            document.getElementById('notInLineBox').classList.remove('d-none');
        });
});
</script>
</body>
</html>
