<?php
/**
 * setup.php — สร้างบัญชี Admin ครั้งแรก
 * ลบไฟล์นี้ทิ้งทันทีหลังสร้างบัญชีสำเร็จแล้ว
 */
session_start();
require_once '../connect.php';

$msg     = '';
$msgType = '';
$done    = false;

// เช็คว่ามี admin อยู่แล้วหรือไม่
$adminCount = (int)$pdo->query("SELECT COUNT(*) FROM staff WHERE role = 'admin'")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');

    if (!$username || !$password || !$name) {
        $msg     = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        $msgType = 'danger';
    } elseif (strlen($password) < 6) {
        $msg     = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
        $msgType = 'danger';
    } elseif ($password !== $confirm) {
        $msg     = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
        $msgType = 'danger';
    } else {
        $check = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetchColumn() > 0) {
            $msg     = "Username '{$username}' มีอยู่ในระบบแล้ว";
            $msgType = 'danger';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO staff (username, password, name, phone, role, specialty, status) VALUES (?,?,?,?,'admin','ทั้งหมด','active')")
                ->execute([$username, $hashed, $name, $phone]);
            $done = true;
            $msg  = "สร้างบัญชี Admin '{$name}' (@{$username}) สำเร็จแล้ว";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าบัญชี Admin | Dorm Repair</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .setup-card {
            background: white;
            border-radius: 24px;
            padding: 40px 36px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
        }

        .setup-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #06C755, #05a044);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: white;
            margin: 0 auto 20px auto;
        }

        .setup-title {
            text-align: center;
            font-size: 1.3rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .setup-subtitle {
            text-align: center;
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 28px;
        }

        .form-label {
            font-weight: 500;
            color: #475569;
            font-size: 0.88rem;
            margin-bottom: 5px;
        }

        .form-control {
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.93rem;
            font-family: 'Kanit', sans-serif;
            background: #f8fafc;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: #06C755;
            box-shadow: 0 0 0 3px rgba(6, 199, 85, 0.15);
            background: white;
        }

        .btn-setup {
            background: linear-gradient(135deg, #06C755, #05a044);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-family: 'Kanit', sans-serif;
            font-weight: 600;
            font-size: 1rem;
            color: white;
            width: 100%;
            box-shadow: 0 4px 15px rgba(6, 199, 85, 0.3);
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn-setup:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(6, 199, 85, 0.4);
        }

        .warning-box {
            background: #fef3c7;
            border: 1px solid #fde68a;
            color: #92400e;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 0.83rem;
            margin-bottom: 20px;
            display: flex;
            gap: 8px;
        }

        .success-box {
            background: #f0fdf4;
            border: 2px solid #86efac;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
        }

        .alert-err {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.88rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>
<div class="setup-card">
    <div class="setup-icon">
        <i class="bi bi-shield-lock-fill"></i>
    </div>
    <div class="setup-title">ตั้งค่าบัญชี Admin</div>
    <div class="setup-subtitle">สร้างบัญชีผู้ดูแลระบบสำหรับ Dorm Repair Admin Panel</div>

    <?php if ($done): ?>
    <!-- Success State -->
    <div class="success-box">
        <i class="bi bi-check-circle-fill" style="font-size:3rem;color:#06C755;display:block;margin-bottom:12px;"></i>
        <div style="font-size:1.05rem;font-weight:700;color:#065f46;margin-bottom:6px;">สร้างบัญชีสำเร็จ!</div>
        <div style="font-size:0.88rem;color:#047857;margin-bottom:20px;"><?= htmlspecialchars($msg) ?></div>
        <div class="alert" style="background:#fef3c7;border:1px solid #fde68a;color:#92400e;border-radius:10px;font-size:0.82rem;padding:10px;">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <strong>กรุณาลบไฟล์ setup.php ออกจากเซิร์ฟเวอร์ทันที</strong>
        </div>
        <a href="login.php" class="btn-setup d-block text-center text-decoration-none mt-3" style="border-radius:10px;padding:12px;background:linear-gradient(135deg,#06C755,#05a044);color:white;font-weight:600;">
            <i class="bi bi-box-arrow-in-right me-1"></i>ไปหน้า Login
        </a>
    </div>

    <?php elseif ($adminCount > 0): ?>
    <!-- Already has admin -->
    <div class="success-box" style="background:#fef2f2;border-color:#fecaca;">
        <i class="bi bi-shield-fill-check" style="font-size:2.5rem;color:#dc2626;display:block;margin-bottom:12px;"></i>
        <div style="font-size:1rem;font-weight:700;color:#991b1b;margin-bottom:6px;">มีบัญชี Admin อยู่แล้ว</div>
        <div style="font-size:0.85rem;color:#b91c1c;margin-bottom:20px;">
            ระบบมีผู้ดูแลระบบ <?= $adminCount ?> บัญชีแล้ว<br>
            ไม่สามารถสร้างผ่านหน้านี้ได้อีก
        </div>
        <a href="login.php" class="btn-setup d-block text-center text-decoration-none" style="border-radius:10px;padding:12px;background:#1e293b;color:white;font-weight:600;">
            <i class="bi bi-arrow-left me-1"></i>กลับหน้า Login
        </a>
    </div>

    <?php else: ?>
    <!-- Setup Form -->
    <?php if ($msg): ?>
    <div class="alert-err">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <div class="warning-box">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
        <div>ไฟล์นี้ใช้สร้างบัญชี Admin ครั้งแรกเท่านั้น <strong>กรุณาลบทิ้งหลังใช้งาน</strong></div>
    </div>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required
                   placeholder="เช่น สมชาย มีดี"
                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Username <span class="text-danger">*</span></label>
            <input type="text" name="username" class="form-control" required
                   placeholder="ชื่อผู้ใช้สำหรับ Login" autocomplete="off"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">เบอร์โทรศัพท์</label>
            <input type="text" name="phone" class="form-control"
                   placeholder="0XX-XXX-XXXX"
                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">รหัสผ่าน <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-control" required
                   placeholder="อย่างน้อย 6 ตัวอักษร" autocomplete="new-password">
        </div>
        <div class="mb-4">
            <label class="form-label">ยืนยันรหัสผ่าน <span class="text-danger">*</span></label>
            <input type="password" name="confirm" class="form-control" required
                   placeholder="กรอกรหัสผ่านอีกครั้ง" autocomplete="new-password">
        </div>
        <button type="submit" class="btn-setup">
            <i class="bi bi-person-plus-fill me-2"></i>สร้างบัญชี Admin
        </button>
    </form>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
