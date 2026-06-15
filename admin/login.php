<?php
session_start();

// ถ้า login แล้วให้ redirect ไป dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once '../connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, name, role FROM staff WHERE username = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$username]);
        $staff = $stmt->fetch();

        if ($staff && password_verify($password, $staff['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $staff['id'];
            $_SESSION['admin_name'] = $staff['name'];
            $_SESSION['admin_role'] = $staff['role'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ Admin | Dorm Repair</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            background: white;
            border-radius: 24px;
            padding: 40px 36px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
        }

        .login-logo {
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

        .login-title {
            text-align: center;
            font-size: 1.35rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .login-subtitle {
            text-align: center;
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 28px;
        }

        .form-label {
            font-weight: 500;
            color: #475569;
            font-size: 0.9rem;
            margin-bottom: 6px;
        }

        .form-control {
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 0.95rem;
            font-family: 'Kanit', sans-serif;
            background: #f8fafc;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: #06C755;
            box-shadow: 0 0 0 3px rgba(6, 199, 85, 0.15);
            background: white;
        }

        .input-icon-wrapper {
            position: relative;
        }

        .input-icon-wrapper .bi {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1rem;
        }

        .input-icon-wrapper .form-control {
            padding-left: 38px;
        }

        .btn-login {
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

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(6, 199, 85, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
        }

        .login-footer {
            text-align: center;
            font-size: 0.78rem;
            color: #94a3b8;
            margin-top: 24px;
        }

        .toggle-password {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 0;
            font-size: 1rem;
        }

        .toggle-password:hover {
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">
            <i class="bi bi-tools"></i>
        </div>
        <div class="login-title">Dorm Repair Admin</div>
        <div class="login-subtitle">ระบบแจ้งซ่อมหอพัก — เข้าสู่ระบบเจ้าหน้าที่</div>

        <?php if ($error): ?>
        <div class="alert-error">
            <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="mb-3">
                <label class="form-label">ชื่อผู้ใช้</label>
                <div class="input-icon-wrapper">
                    <i class="bi bi-person"></i>
                    <input type="text" name="username" class="form-control"
                           placeholder="กรอก Username" required
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">รหัสผ่าน</label>
                <div class="input-icon-wrapper" style="position:relative;">
                    <i class="bi bi-lock"></i>
                    <input type="password" name="password" id="passwordInput" class="form-control"
                           placeholder="กรอกรหัสผ่าน" required>
                    <button type="button" class="toggle-password" id="togglePwd">
                        <i class="bi bi-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="bi bi-shield-lock me-2"></i>เข้าสู่ระบบ
            </button>
        </form>

        <div class="login-footer">
            &copy; 2026 Dormitory Repair System &nbsp;|&nbsp;
            <a href="setup.php" style="color:#94a3b8;">ตั้งค่าบัญชีแรก</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('togglePwd').addEventListener('click', function() {
            const input = document.getElementById('passwordInput');
            const icon  = document.getElementById('toggleIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        });
    </script>
</body>
</html>
