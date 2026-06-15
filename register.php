<?php
/**
 * หน้าลงทะเบียนข้อมูลนักศึกษาสำหรับระบบหอพัก (LINE LIFF Optimized)
 * พัฒนาโดย Senior Frontend & PHP Developer สำหรับระบบแจ้งซ่อมหอพัก (dorm_repair)
 */

require_once 'connect.php';

// =========================================================================
// CONFIGURATION: กำหนด LINE LIFF ID ของท่านที่นี่
// =========================================================================
define('LIFF_ID', '2010214920-pnbPdoey'); // ใส่ LIFF ID ของท่านที่ได้จาก LINE Developers Console

$register_success = false;
$error_message    = '';

// หน้าที่จะ redirect กลับหลังลงทะเบียนสำเร็จ (whitelist เฉพาะหน้าในระบบ)
$allowed_redirects = ['repair_form.php', 'meter_submit.php'];
$redirect_to = $_POST['redirect'] ?? $_GET['redirect'] ?? 'repair_form.php';
if (!in_array($redirect_to, $allowed_redirects)) {
    $redirect_to = 'repair_form.php';
}

// ตรวจสอบการส่งข้อมูลผ่านฟอร์ม (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่าและทำความสะอาดข้อมูลเบื้องต้นเพื่อความปลอดภัย (Prevent XSS & Trim whitespace)
    $line_uid         = trim($_POST['line_uid'] ?? '');
    $line_profile_img = trim($_POST['line_profile_img'] ?? '');
    $student_id       = trim($_POST['student_id'] ?? '');
    $full_name        = trim($_POST['full_name'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $dorm_id          = trim($_POST['dorm_id'] ?? '');
    $room_id          = trim($_POST['room_id'] ?? '');

    // 1. ตรวจสอบข้อมูลจำเป็นต้องไม่เป็นค่าว่าง (Server-Side Validation)
    if (empty($line_uid)) {
        $error_message = 'ไม่พบรหัสประจำตัว LINE UID กรุณาเข้าใช้งานผ่านแอปพลิเคชัน LINE เพื่อทำการลงทะเบียน';
    } elseif (empty($student_id) || empty($full_name) || empty($phone) || empty($dorm_id) || empty($room_id)) {
        $error_message = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วนทุกช่อง';
    } else {
        try {
            // เริ่มต้น Database Transaction เพื่อความถูกต้องของข้อมูล
            $pdo->beginTransaction();

            // 2. ตรวจสอบสิทธิ์และการซ้ำซ้อนของข้อมูล (Data Conflict Checking)
            // เช็คว่ารหัสนักศึกษานี้ถูกผูกกับ LINE UID อื่นไปแล้วหรือไม่
            $checkStudent = $pdo->prepare("SELECT student_id, line_uid FROM students WHERE student_id = :student_id LIMIT 1");
            $checkStudent->execute(['student_id' => $student_id]);
            $existingStudent = $checkStudent->fetch();

            // เช็คว่า LINE UID นี้ถูกผูกกับรหัสนักศึกษาอื่นไปแล้วหรือไม่ (ถ้ามี LINE UID ส่งมา)
            $existingLineUser = null;
            if (!empty($line_uid)) {
                $checkLine = $pdo->prepare("SELECT student_id, line_uid FROM students WHERE line_uid = :line_uid LIMIT 1");
                $checkLine->execute(['line_uid' => $line_uid]);
                $existingLineUser = $checkLine->fetch();
            }

            if ($existingStudent && !empty($existingStudent['line_uid']) && $existingStudent['line_uid'] !== $line_uid && strpos($existingStudent['line_uid'], 'MOCK') === false) {
                // เคสรหัสนักศึกษาถูกคนอื่นผูกไปแล้ว (ที่ไม่ใช่ไอดีทดสอบ/Mock)
                $error_message = 'รหัสนักศึกษานี้เคยถูกผูกกับบัญชี LINE อื่นในระบบแล้ว';
            } elseif ($existingLineUser && $existingLineUser['student_id'] !== $student_id) {
                // เคส LINE account นี้ผูกกับรหัสนักศึกษาคนอื่นอยู่
                $error_message = 'บัญชี LINE นี้ถูกใช้ลงทะเบียนกับรหัสนักศึกษาอื่นไปแล้ว';
            } else {
                // 3. ดำเนินการบันทึกข้อมูล (UPSERT Logic - INSERT หรือ UPDATE)
                if ($existingLineUser) {
                    // มีข้อมูล LINE UID นี้อยู่แล้ว -> อัปเดตข้อมูลนักศึกษาตามฟอร์มล่าสุดโดยอ้างอิงจาก line_uid
                    $updateStmt = $pdo->prepare("
                        UPDATE students 
                        SET student_id = :student_id,
                            name = :name,
                            phone = :phone,
                            room_id = :room_id,
                            line_profile_img = :line_profile_img
                        WHERE line_uid = :line_uid
                    ");
                    $updateStmt->execute([
                        'student_id'       => $student_id,
                        'name'             => $full_name,
                        'phone'            => $phone,
                        'room_id'          => $room_id,
                        'line_profile_img' => !empty($line_profile_img) ? $line_profile_img : null,
                        'line_uid'         => $line_uid
                    ]);
                } elseif ($existingStudent) {
                    // มีรหัสนักศึกษานี้อยู่แล้วแต่ยังไม่ได้ผูก LINE -> ผูก LINE UID และอัปเดตข้อมูลล่าสุดโดยอ้างอิงจาก student_id
                    $updateStmt = $pdo->prepare("
                        UPDATE students 
                        SET line_uid = :line_uid,
                            name = :name,
                            phone = :phone,
                            room_id = :room_id,
                            line_profile_img = :line_profile_img
                        WHERE student_id = :student_id
                    ");
                    $updateStmt->execute([
                        'line_uid'         => !empty($line_uid) ? $line_uid : null,
                        'name'             => $full_name,
                        'phone'            => $phone,
                        'room_id'          => $room_id,
                        'line_profile_img' => !empty($line_profile_img) ? $line_profile_img : null,
                        'student_id'       => $student_id
                    ]);
                } else {
                    // ข้อมูลใหม่ทั้งหมด -> บันทึกข้อมูลนักศึกษาและผูก LINE UID ทันที
                    $insertStmt = $pdo->prepare("
                        INSERT INTO students (student_id, name, phone, room_id, line_uid, line_profile_img) 
                        VALUES (:student_id, :name, :phone, :room_id, :line_uid, :line_profile_img)
                    ");
                    $insertStmt->execute([
                        'student_id'       => $student_id,
                        'name'             => $full_name,
                        'phone'            => $phone,
                        'room_id'          => $room_id,
                        'line_uid'         => !empty($line_uid) ? $line_uid : null,
                        'line_profile_img' => !empty($line_profile_img) ? $line_profile_img : null
                    ]);
                }

                // ยืนยันบันทึกข้อมูลสำเร็จ
                $pdo->commit();
                $register_success = true;
            }
        } catch (PDOException $e) {
            // ยกเลิกการทำรายการกรณีมีข้อผิดพลาด
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Dorm Registration Error: " . $e->getMessage());
            $error_message = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
        }
    }
}

// ดึงข้อมูลหอพักทั้งหมดมาเพื่อนำไปแสดงผลใน Dropdown ของฟอร์ม
try {
    $stmt = $pdo->query("SELECT id, name, dorm_type FROM dorms ORDER BY name ASC");
    $dorms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching dorms for registration: " . $e->getMessage());
    $dorms = [];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ลงทะเบียนข้อมูลนักศึกษา | Dorm Repair System</title>
    <!-- SEO Optimization -->
    <meta name="description" content="ระบบลงทะเบียนข้อมูลผู้ใช้งานหอพัก เชื่อมต่อ LINE LIFF อำนวยความสะดวกในการแจ้งซ่อมแซมวัสดุอุปกรณ์ออนไลน์">
    <!-- Google Font (Kanit) - เรียบหรู ทันสมัย เหมาะกับแนวคิดโมเดิร์นของ LINE -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- SweetAlert2 (CSS) -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        :root {
            --line-green: #06C755;
            --line-green-hover: #05b04b;
            --line-green-light: rgba(6, 199, 85, 0.1);
            --line-green-focus: rgba(6, 199, 85, 0.25);
            --bg-body: #f8fafc;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            --input-border: #e2e8f0;
        }

        body {
            font-family: 'Kanit', sans-serif;
            background-color: var(--bg-body);
            background-image: radial-gradient(circle at 10% 20%, rgba(6, 199, 85, 0.03) 0%, transparent 40%),
                              radial-gradient(circle at 90% 80%, rgba(6, 199, 85, 0.02) 0%, transparent 40%);
            min-height: 100vh;
            color: #334155;
            display: flex;
            flex-direction: column;
        }
body.swal2-shown select {
    visibility: hidden !important;
}
        /* LIFF Header Premium Glassmorphism styling */
        .liff-header {
            background: linear-gradient(135deg, var(--line-green) 0%, #05a044 100%);
            color: white;
            padding: 35px 20px 45px 20px;
            border-bottom-left-radius: 30px;
            border-bottom-right-radius: 30px;
            box-shadow: 0 4px 20px rgba(6, 199, 85, 0.15);
            text-align: center;
            position: relative;
        }

        .liff-header .header-icon {
            font-size: 2.8rem;
            animation: float 3s ease-in-out infinite;
            display: inline-block;
            margin-bottom: 8px;
        }

        .liff-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .liff-header p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 0;
            font-weight: 300;
        }

        /* Container & Card adjustments */
        .liff-card-wrapper {
            margin: -25px auto 30px auto;
            padding: 0 16px;
            max-width: 500px;
            width: 100%;
        }

        .liff-card {
            background: #ffffff;
            border: none;
            border-radius: 24px;
            box-shadow: var(--card-shadow);
            padding: 28px 24px;
        }

        /* LINE Profile Info Section */
        .line-profile-box {
            background: var(--bg-body);
            border: 1px solid rgba(6, 199, 85, 0.15);
            border-radius: 18px;
            padding: 14px 18px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.3s ease;
        }

        .line-profile-box img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid var(--line-green);
            object-fit: cover;
            box-shadow: 0 3px 8px rgba(6, 199, 85, 0.15);
        }

        .line-profile-box .profile-details {
            flex-grow: 1;
        }

        .line-profile-box .profile-name {
            font-weight: 600;
            font-size: 0.95rem;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .line-profile-box .profile-status {
            font-size: 0.75rem;
            color: #16a34a;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 500;
            background: #f0fdf4;
            padding: 2px 10px;
            border-radius: 20px;
        }

        /* Form styling */
        .form-label {
            font-weight: 500;
            color: #475569;
            margin-bottom: 8px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-label i {
            color: var(--line-green);
            font-size: 1rem;
        }

        .form-control, .form-select {
            border: 1.5px solid var(--input-border);
            border-radius: 14px;
            padding: 12px 16px;
            font-size: 0.95rem;
            color: #1e293b;
            background-color: #f8fafc;
            transition: all 0.25s ease;
        }

        /* สไตล์แบบธรรมดา (Pure Native Select) เพื่อหลีกเลี่ยงบั๊กทุกชนิดของ iOS Safari / LINE LIFF WebView */
        select {
            display: block !important;
            width: 100% !important;
            height: 50px !important;
            padding: 12px 16px !important;
            font-size: 16px !important; /* ป้องกัน iOS ซูมหน้าจอ */
            border: 1.5px solid var(--input-border) !important;
            border-radius: 14px !important;
            background-color: #ffffff !important;
            color: #1e293b !important;
            outline: none !important;
            pointer-events: auto !important;
            opacity: 1 !important;
        }
        select:focus {
            border-color: var(--line-green) !important;
        }

        /* ปุ่มตัวเลือกใน Modal */
        .modal-option-btn {
            background-color: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: 14px;
            padding: 14px 18px;
            width: 100%;
            text-align: left;
            margin-bottom: 10px;
            font-weight: 500;
            color: #334155;
            transition: all 0.2s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-option-btn:hover, .modal-option-btn:active {
            background-color: #f0fdf4;
            border-color: var(--line-green);
            color: var(--line-green);
            transform: translateY(-1px);
        }

        .form-control:focus, .form-select:focus {
            background-color: #ffffff;
            border-color: var(--line-green);
            box-shadow: 0 0 0 4px var(--line-green-focus);
            outline: none;
        }

        .form-control::placeholder {
            color: #94a3b8;
            font-weight: 300;
        }

        /* Custom Button */
        .btn-register-submit {
            background: linear-gradient(135deg, var(--line-green) 0%, var(--line-green-hover) 100%);
            border: none;
            color: white;
            font-weight: 600;
            font-size: 1.05rem;
            padding: 15px;
            border-radius: 16px;
            width: 100%;
            box-shadow: 0 8px 20px rgba(6, 199, 85, 0.2);
            transition: all 0.3s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .btn-register-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(6, 199, 85, 0.25);
        }

        .btn-register-submit:active {
            transform: translateY(1px);
            box-shadow: 0 4px 10px rgba(6, 199, 85, 0.15);
        }

        /* LIFF Loading and Simulation badges */
        .liff-status-banner {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 20px;
            padding: 8px;
            background: #f1f5f9;
            border-radius: 12px;
        }

        .simulation-badge {
            background-color: #f59e0b;
            color: white;
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 8px;
            font-weight: 500;
        }

        .footer {
            margin-top: auto;
            padding: 20px 0;
            text-align: center;
            font-size: 0.8rem;
            color: #94a3b8;
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-6px); }
            100% { transform: translateY(0px); }
        }
    </style>
</head>
<body>

    <!-- Header Block -->
    <div class="liff-header">
        <div class="header-icon"><i class="bi bi-person-badge"></i></div>
        <h1>ลงทะเบียนผู้ใช้งานหอพัก </h1>
    </div>

    <!-- Main Content Container -->
    <div class="liff-card-wrapper">
        <div class="liff-card">

            <!-- LIFF SDK Status and Loader Banner -->
            <div id="liffBanner" class="liff-status-banner">
                <div class="spinner-border spinner-border-sm text-success" role="status" id="liffSpinner"></div>
                <span id="liffStatusText">กำลังเริ่มต้นการทำงาน LINE LIFF...</span>
            </div>

            <!-- LINE Profile Showcase (Hidden initially) -->
            <div id="lineProfileBox" class="line-profile-box d-none">
                <img id="lineUserAvatar" src="" alt="LINE Avatar">
                <div class="profile-details">
                    <div class="profile-name" id="lineUserName">ผู้ใช้ LINE</div>
                    <div class="profile-status">
                        <i class="bi bi-shield-fill-check"></i> เชื่อมต่อ LINE แล้ว
                    </div>
                </div>
            </div>

            <!-- Fallback Error UI when opened outside LINE App -->
            <div id="liffErrorBox" class="text-center py-4 d-none">
                <i class="bi bi-chat-quote-fill text-success mb-3" style="font-size: 3.2rem; display: block; color: var(--line-green) !important;"></i>
                <h4 class="fw-bold mb-2" style="color: #1e293b;">เข้าใช้งานผ่าน LINE เท่านั้น ⚠️</h4>
                <p class="text-muted mb-4 px-2" style="font-size: 0.95rem; line-height: 1.6;">หน้าจอลงทะเบียนนี้ออกแบบมาเพื่อใช้งานผ่านแอปพลิเคชัน LINE บนโทรศัพท์มือถือเป็นหลัก เพื่อทำการเชื่อมบัญชี LINE ของท่านเข้ากับหอพัก กรุณาเข้าลิงก์ผ่าน LINE</p>
                <a href="https://line.me" class="btn btn-register-submit rounded-pill px-4 py-2 d-inline-flex align-items-center justify-content-center gap-2" style="width: auto;">
                    <i class="bi bi-line fs-5"></i> เปิดแอปพลิเคชัน LINE
                </a>
            </div>

            <!-- Registration Form -->
            <form action="" method="POST" id="registerForm" class="needs-validation" novalidate>
                
                <!-- Hidden inputs to receive LINE variables -->
                <input type="hidden" name="line_uid" id="line_uid" value="">
                <input type="hidden" name="line_profile_img" id="line_profile_img" value="">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect_to) ?>">

                <!-- Student ID Input -->
                <div class="mb-3">
                    <label for="student_id" class="form-label">
                        <i class="bi bi-card-text"></i> รหัสนักศึกษา
                    </label>
                    <input type="text" class="form-control" id="student_id" name="student_id" 
                           placeholder="เช่น 6502012345" required 
                           inputmode="numeric" pattern="[0-9]{10,13}">
                    <div class="invalid-feedback">กรุณากรอกรหัสนักศึกษาให้ถูกต้องเป็นตัวเลข 10-13 หลัก</div>
                </div>

                <!-- Full Name Input -->
                <div class="mb-3">
                    <label for="full_name" class="form-label">
                        <i class="bi bi-person"></i> ชื่อ - นามสกุล
                    </label>
                    <input type="text" class="form-control" id="full_name" name="full_name" 
                           placeholder="ตัวอย่าง: นายสมหมาย ใจดีมาก" required>
                    <div class="invalid-feedback">กรุณากรอกชื่อและนามสกุลของคุณ</div>
                </div>

                <!-- Phone Input -->
                <div class="mb-3">
                    <label for="phone" class="form-label">
                        <i class="bi bi-telephone"></i> เบอร์โทรศัพท์มือถือ
                    </label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           placeholder="เช่น 0891234567" required 
                           inputmode="tel" pattern="[0-9]{9,10}">
                    <div class="invalid-feedback">กรุณากรอกเบอร์โทรศัพท์ 9-10 หลัก (เฉพาะตัวเลข)</div>
                </div>

                <div class="row">
                    <!-- Dorm Selection -->
                    <div class="col-6 mb-4">
                        <label class="form-label">
                            <i class="bi bi-building"></i> หอพัก
                        </label>
                        <!-- Input ซ่อนเพื่อให้ระบบตรวจสอบความถูกต้องฟอร์มทำงาน -->
                        <input type="text" id="dorm_id" name="dorm_id" required style="opacity: 0; position: absolute; width: 0; height: 0; pointer-events: none;">
                        <div id="dorm_picker_btn" class="form-control d-flex justify-content-between align-items-center" style="cursor: pointer; height: 50px; background-color: #ffffff; border: 1.5px solid var(--input-border); border-radius: 14px; padding: 12px 16px;" data-bs-toggle="modal" data-bs-target="#dormModal">
                            <span id="dorm_selected_text" style="color: #94a3b8; font-size: 0.95rem;">เลือกหอพัก</span>
                            <i class="bi bi-chevron-down text-muted" style="font-size: 0.8rem;"></i>
                        </div>
                        <div class="invalid-feedback">กรุณาเลือกหอพัก</div>
                    </div>

                    <!-- Room Selection -->
                    <div class="col-6 mb-4">
                        <label class="form-label">
                            <i class="bi bi-door-closed"></i> ห้องพัก
                        </label>
                        <!-- Input ซ่อนเพื่อใช้ในการตรวจความถูกต้องฟอร์ม -->
                        <input type="text" id="room_id" name="room_id" required style="opacity: 0; position: absolute; width: 0; height: 0; pointer-events: none;">
                        <div id="room_picker_btn" class="form-control d-flex justify-content-between align-items-center" style="cursor: pointer; height: 50px; background-color: #f1f5f9; border: 1.5px solid var(--input-border); border-radius: 14px; padding: 12px 16px; pointer-events: none;" data-bs-toggle="modal" data-bs-target="#roomModal">
                            <span id="room_selected_text" style="color: #94a3b8; font-size: 0.95rem;">เลือกหอพักก่อน</span>
                            <i class="bi bi-chevron-down text-muted" style="font-size: 0.8rem;"></i>
                        </div>
                        <div class="invalid-feedback">กรุณาเลือกห้องพัก</div>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-register-submit btn-lg">
                    <i class="bi bi-check-circle-fill"></i> ยืนยันและลงทะเบียน
                </button>

            </form>
        </div>
    </div>

    <!-- Copyright Footer -->
    <div class="footer">
        &copy; 2026 Dormitory Repair System. All Rights Reserved.
    </div>

    <!-- =========================================================================
         MODALS PICKER: ระบบจำลองปุ่มดรอปดาวน์ด้วยกล่องป๊อปอัปเพื่อเลี่ยงบั๊ก iOS / LINE WebView
         ========================================================================= -->
    <!-- 1. Modal เลือกหอพัก -->
    <div class="modal fade" id="dormModal" tabindex="-1" aria-labelledby="dormModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content" style="border-radius: 24px; border: none; box-shadow: 0 15px 40px rgba(0,0,0,0.12);">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold" id="dormModalLabel" style="color: #1e293b; font-size: 1.2rem;">
                        <i class="bi bi-building text-success"></i> เลือกหอพักของคุณ
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="list-group list-group-flush" style="max-height: 350px;">
                        <?php if (empty($dorms)): ?>
                            <div class="text-center py-4 text-muted">ไม่พบข้อมูลหอพักในระบบ</div>
                        <?php else: ?>
                            <?php foreach ($dorms as $dorm): ?>
                                <button type="button" class="modal-option-btn dorm-option" 
                                        data-value="<?= htmlspecialchars($dorm['id']) ?>" 
                                        data-name="<?= htmlspecialchars($dorm['name']) ?>">
                                    <span><?= htmlspecialchars($dorm['name']) ?> (<?= htmlspecialchars($dorm['dorm_type']) ?>)</span>
                                    <i class="bi bi-chevron-right text-muted" style="font-size: 0.8rem;"></i>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Modal เลือกห้องพัก -->
    <div class="modal fade" id="roomModal" tabindex="-1" aria-labelledby="roomModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content" style="border-radius: 24px; border: none; box-shadow: 0 15px 40px rgba(0,0,0,0.12);">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold" id="roomModalLabel" style="color: #1e293b; font-size: 1.2rem;">
                        <i class="bi bi-door-closed text-success"></i> เลือกห้องพักของคุณ
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- แสดงข้อความเตือนเมื่อยังไม่โหลดห้อง -->
                    <div class="text-center text-muted py-4" id="roomModalPlaceholder">
                        ⏳ กรุณาเลือกหอพักก่อนเพื่อดูรายชื่อห้องพัก
                    </div>
                    <!-- กล่องใส่ปุ่มเลือกห้อง (JS จะโหลดมาแปะที่นี่) -->
                    <div class="list-group list-group-flush d-none" id="roomModalList" style="max-height: 350px;">
                        <!-- Dynamic items -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- LINE LIFF SDK JS -->
    <script charset="utf-8" src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>

    <script>
        // =========================================================================
        // 0. ระบบดักจับและแสดงข้อผิดพลาดบนหน้าจอ (Mobile Diagnostic Error Catcher)
        // =========================================================================
        window.onerror = function(message, source, lineno, colno, error) {
            const errText = `Browser Error: ${message} (Line: ${lineno})`;
            console.error(errText, error);
            const statusTextEl = document.getElementById('liffStatusText');
            if (statusTextEl) {
                statusTextEl.innerHTML = `<span style="color:#dc3545; font-weight:600;">❌ เกิดข้อผิดพลาดของเบราว์เซอร์:</span><br><small style="font-size:0.75rem; font-family:monospace; color:#64748b;">${message}<br>ที่บรรทัด ${lineno}:${colno}</small>`;
                const spinner = document.getElementById('liffSpinner');
                if (spinner) spinner.classList.add('d-none');
                const banner = document.getElementById('liffBanner');
                if (banner) {
                    banner.style.backgroundColor = '#fef2f2';
                    banner.style.border = '1px solid #fecaca';
                }
            }
            return false;
        };
        // =========================================================================
        // 1. ระบบดึงข้อมูลห้องพักแบบ Dynamic ดึงข้อมูลผ่าน get_rooms.php
        // =========================================================================
        // =========================================================================
        // 1. ระบบเลือกหอพักและห้องพักผ่าน Modal (เลี่ยงบั๊กคลิกเลือกไม่ได้บน LINE WebView)
        // =========================================================================
        document.querySelectorAll('.dorm-option').forEach(btn => {
            btn.addEventListener('click', function() {
                const dormId = this.getAttribute('data-value');
                const dormName = this.getAttribute('data-name');
                
                // อัปเดตค่าและเปลี่ยนข้อความที่ปุ่มแสดงผล
                document.getElementById('dorm_id').value = dormId;
                const dormSelectedText = document.getElementById('dorm_selected_text');
                dormSelectedText.textContent = dormName;
                dormSelectedText.style.color = '#1e293b'; // เปลี่ยนตัวอักษรเป็นสีเข้ม
                
                // ปิด Modal หอพัก
                const dormModalEl = document.getElementById('dormModal');
                const dormModal = bootstrap.Modal.getInstance(dormModalEl) || new bootstrap.Modal(dormModalEl);
                dormModal.hide();
                
                // เคลียร์ค่าห้องเก่าและปรับโฉมปุ่มห้องพักให้แสดงสถานะกำลังโหลด
                document.getElementById('room_id').value = '';
                const roomSelectedText = document.getElementById('room_selected_text');
                roomSelectedText.textContent = '⏳ กำลังโหลดห้อง...';
                roomSelectedText.style.color = '#94a3b8';
                
                const roomPickerBtn = document.getElementById('room_picker_btn');
                roomPickerBtn.style.pointerEvents = 'none';
                roomPickerBtn.style.backgroundColor = '#f1f5f9';

                // โหลดข้อมูลห้องพักผ่าน Ajax
                fetch(`get_rooms.php?dorm_id=${dormId}`)
                    .then(response => {
                        if (!response.ok) throw new Error('API Response Error');
                        return response.json();
                    })
                    .then(data => {
                        const roomList = document.getElementById('roomModalList');
                        const roomPlaceholder = document.getElementById('roomModalPlaceholder');
                        
                        roomList.innerHTML = '';
                        
                        if (data.success && data.rooms && data.rooms.length > 0) {
                            data.rooms.forEach(room => {
                                let statusSuffix = '';
                                if (room.status && room.status !== 'ready' && room.status !== 'active' && room.status !== 'พร้อมใช้งาน') {
                                    statusSuffix = ` (${room.status})`;
                                }
                                
                                const btn = document.createElement('button');
                                btn.type = 'button';
                                btn.className = 'modal-option-btn room-option';
                                btn.setAttribute('data-value', room.id);
                                btn.setAttribute('data-name', `ห้อง ${room.room_number}${statusSuffix}`);
                                btn.innerHTML = `
                                    <span>ห้อง ${room.room_number}${statusSuffix}</span>
                                    <i class="bi bi-chevron-right text-muted" style="font-size: 0.8rem;"></i>
                                `;
                                
                                // ผูกสัมผัสปุ่มเลือกห้อง
                                btn.addEventListener('click', function() {
                                    const roomId = this.getAttribute('data-value');
                                    const roomName = this.getAttribute('data-name');
                                    
                                    document.getElementById('room_id').value = roomId;
                                    roomSelectedText.textContent = roomName;
                                    roomSelectedText.style.color = '#1e293b';
                                    
                                    const roomModalEl = document.getElementById('roomModal');
                                    const roomModal = bootstrap.Modal.getInstance(roomModalEl) || new bootstrap.Modal(roomModalEl);
                                    roomModal.hide();
                                });
                                
                                roomList.appendChild(btn);
                            });
                            
                            roomPlaceholder.classList.add('d-none');
                            roomList.classList.remove('d-none');
                            
                            roomSelectedText.textContent = 'เลือกห้องพัก';
                            roomPickerBtn.style.pointerEvents = 'auto';
                            roomPickerBtn.style.backgroundColor = '#ffffff';
                        } else {
                            roomPlaceholder.textContent = '⚠️ ไม่พบห้องพักว่างในหอนี้';
                            roomPlaceholder.classList.remove('d-none');
                            roomList.classList.add('d-none');
                            roomSelectedText.textContent = 'ไม่มีห้องว่าง';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching rooms:', error);
                        roomSelectedText.textContent = '❌ โหลดข้อมูลผิดพลาด';
                        const roomPlaceholder = document.getElementById('roomModalPlaceholder');
                        roomPlaceholder.textContent = '❌ ไม่สามารถโหลดข้อมูลห้องพักได้';
                    });
            });
        });

        // ฟังก์ชันช่วยดึงข้อมูลจาก URL Query String
        function getUrlParam(name) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(name);
        }

        const liffId = "<?= LIFF_ID ?>";

        document.addEventListener("DOMContentLoaded", function() {
            // 1. ตรวจสอบว่ามีการส่งต่อข้อมูล LINE Profile มาทาง URL หรือไม่ (เลี่ยงปัญหาระบบ iOS LIFF initialization บล็อก)
            const paramUid = getUrlParam('line_uid');
            const paramName = getUrlParam('line_name');
            const paramImg = getUrlParam('line_img');

            if (paramUid) {
                // ซ่อน Banner โหลด และกรอกข้อมูลโปรไฟล์ LINE ที่แนบมาทันที โดยไม่ต้องรัน liff.init
                document.getElementById('liffBanner').classList.add('d-none');
                document.getElementById('line_uid').value = paramUid;
                document.getElementById('line_profile_img').value = paramImg || '';

                document.getElementById('lineUserAvatar').src = paramImg || 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
                document.getElementById('lineUserName').textContent = decodeURIComponent(paramName || 'ผู้ใช้ LINE');
                document.getElementById('lineProfileBox').classList.remove('d-none');
                
                console.log("LINE Profile loaded successfully from URL parameters. Skipping LIFF SDK init.");
                return; // สิ้นสุดการทำงานของสคริปต์ ดึงข้อมูลสำเร็จโดยไม่ต้องเรียก SDK
            }

            // 2. หากเปิดโดยตรงโดยไม่มี URL Parameter ให้รันระบบ LIFF ปกติ (Android / iOS)
            // เช็คว่าผู้ใช้งานระบุ LIFF ID หรือยัง
            if (liffId === "YOUR_LIFF_ID" || !liffId.trim()) {
                console.warn("LIFF ID is missing.");
                document.getElementById('liffBanner').classList.add('d-none');
                document.getElementById('registerForm').classList.add('d-none');
                document.getElementById('liffErrorBox').classList.remove('d-none');
                return;
            }

            // เริ่มต้นระบบ LINE LIFF SDK
            liff.init({ liffId: liffId })
                .then(() => {
                    // ป้องกัน iOS เด้งหลุดด้วยการเช็คว่าถ้าอยู่ในแอป LINE (isInClient) ให้ดึงโปรไฟล์โดยตรง ไม่ต้องเช็ค isLoggedIn หรือสั่ง login ซ้ำซ้อน
                    if (liff.isInClient() || liff.isLoggedIn()) {
                        // ดึงข้อมูลโปรไฟล์ความปลอดภัยจาก LINE
                        liff.getProfile()
                            .then(profile => {
                                // ซ่อนแถบสถานะกำลังโหลด
                                document.getElementById('liffBanner').classList.add('d-none');

                                // กำหนดค่าลงใน Input Fields (Hidden)
                                document.getElementById('line_uid').value = profile.userId;
                                document.getElementById('line_profile_img').value = profile.pictureUrl || '';

                                // แสดงข้อมูลโปรไฟล์ LINE ของผู้ใช้งาน
                                document.getElementById('lineUserAvatar').src = profile.pictureUrl || 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
                                document.getElementById('lineUserName').textContent = profile.displayName;
                                document.getElementById('lineProfileBox').classList.remove('d-none');
                            })
                            .catch(err => {
                                console.error('Error getting LINE profile:', err);
                                updateLiffStatus('❌ ดึงโปรไฟล์ LINE ไม่สำเร็จ โปรดตรวจสอบสิทธิ์');
                                document.getElementById('registerForm').classList.add('d-none');
                                document.getElementById('liffErrorBox').classList.remove('d-none');
                            });
                    } else {
                        // ถ้าอยู่นอกแอป LINE และยังไม่ได้ล็อกอิน ให้บังคับล็อกอิน
                        liff.login();
                    }
                })
                .catch(err => {
                    console.error('LINE LIFF Init Failed:', err);
                    document.getElementById('liffBanner').classList.add('d-none');
                    document.getElementById('registerForm').classList.add('d-none');
                    document.getElementById('liffErrorBox').classList.remove('d-none');
                });
        });

        // ฟังก์ชันช่วยอัปเดตข้อความแถบสถานะ
        function updateLiffStatus(msg) {
            document.getElementById('liffSpinner').classList.add('d-none');
            document.getElementById('liffStatusText').textContent = msg;
        }

        // =========================================================================
        // 3. ระบบ Client-Side Form Validation (Bootstrap 5 Style)
        // =========================================================================
        const form = document.getElementById('registerForm');
        form.addEventListener('submit', function (event) {
            // เช็คว่ามี LINE UID หรือยัง
            const lineUid = document.getElementById('line_uid').value;
            if (!lineUid || !lineUid.trim()) {
                event.preventDefault();
                event.stopPropagation();
                Swal.fire({
                    title: 'ไม่สามารถลงทะเบียนได้! ⚠️',
                    text: 'ไม่พบข้อมูล LINE UID กรุณาเปิดหน้าจอนี้ผ่านแอปพลิเคชัน LINE เพื่อลงทะเบียนผูกบัญชีหอพัก',
                    icon: 'error',
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }

            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    </script>

    <!-- =========================================================================
         4. ระบบการแสดงการตอบสนองความสำเร็จและการแจ้งเตือน (SweetAlert2 PHP Handler)
         ========================================================================= -->
    <?php if ($register_success): ?>
        <script>
            Swal.fire({
                title: 'ลงทะเบียนสำเร็จ! 🎉',
                text: 'ระบบได้บันทึกข้อมูลของท่านเรียบร้อยแล้ว เตรียมพาท่านไปยังหน้าจอแจ้งซ่อม...',
                icon: 'success',
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#06C755',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '<?= htmlspecialchars($redirect_to) ?>';
                }
            });
        </script>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <script>
            Swal.fire({
                title: 'เกิดข้อผิดพลาด! ⚠️',
                text: '<?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>',
                icon: 'error',
                confirmButtonText: 'ลองอีกครั้ง',
                confirmButtonColor: '#dc3545'
            });
        </script>
    <?php endif; ?>

</body>
</html>
