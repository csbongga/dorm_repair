<?php
/**
   * เครื่องมือวินิจฉัยและจัดการฐานข้อมูลสำหรับระบบแจ้งซ่อมหอพัก (Developer Diagnostic Dashboard)
   * พัฒนาโดย Senior Frontend & PHP Developer
   */

require_once 'connect.php';

$action = $_GET['action'] ?? '';
$status_message = '';
$status_type = 'info';

// ดำเนินการตามคำสั่ง Action
try {
    if ($action === 'clear_mock') {
        // ลบข้อมูลไอดีจำลอง Mock ทั้งหมด
        $stmt = $pdo->prepare("DELETE FROM students WHERE line_uid LIKE 'MOCK%'");
        $stmt->execute();
        $affected = $stmt->rowCount();
        $status_message = "🧹 ลบข้อมูลไอดีจำลอง (Mock Student) สำเร็จทั้งหมด {$affected} รายการ!";
        $status_type = 'success';
    } elseif ($action === 'unbind' && isset($_GET['student_id'])) {
        // ปลดการเชื่อมต่อ LINE UID ของนักศึกษาคนนี้ออก
        $student_id = trim($_GET['student_id']);
        $stmt = $pdo->prepare("UPDATE students SET line_uid = NULL, line_profile_img = NULL WHERE student_id = :student_id");
        $stmt->execute(['student_id' => $student_id]);
        $status_message = "🔓 ปลดการผูก LINE UID สำหรับรหัสนักศึกษา {$student_id} เรียบร้อยแล้ว (สามารถลงทะเบียนใหม่ได้ทันที)";
        $status_type = 'success';
    } elseif ($action === 'delete' && isset($_GET['student_id'])) {
        // ลบข้อมูลนักศึกษาคนนี้ออกทั้งหมด
        $student_id = trim($_GET['student_id']);
        $stmt = $pdo->prepare("DELETE FROM students WHERE student_id = :student_id");
        $stmt->execute(['student_id' => $student_id]);
        $status_message = "🗑️ ลบข้อมูลนักศึกษา {$student_id} ออกจากระบบเรียบร้อยแล้ว";
        $status_type = 'danger';
    }
} catch (PDOException $e) {
    $status_message = "❌ เกิดข้อผิดพลาดในการทำรายการ: " . $e->getMessage();
    $status_type = 'danger';
}

// ค้นหาหรือดึงข้อมูลนักศึกษา
$students = [];
$stats = [
    'total_students' => 0,
    'mock_students' => 0,
    'real_students' => 0,
    'total_dorms' => 0,
    'total_rooms' => 0
];

try {
    // คำนวณ Stats
    $stats['total_students'] = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $stats['mock_students'] = $pdo->query("SELECT COUNT(*) FROM students WHERE line_uid LIKE 'MOCK%'")->fetchColumn();
    $stats['real_students'] = $pdo->query("SELECT COUNT(*) FROM students WHERE line_uid IS NOT NULL AND line_uid NOT LIKE 'MOCK%'")->fetchColumn();
    $stats['total_dorms'] = $pdo->query("SELECT COUNT(*) FROM dorms")->fetchColumn();
    $stats['total_rooms'] = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();

    // ดึงข้อมูลนักศึกษาทั้งหมด
    $stmt = $pdo->query("
        SELECT s.student_id, s.name, s.phone, s.line_uid, s.line_profile_img, r.room_number, d.name AS dorm_name
        FROM students s
        LEFT JOIN rooms r ON s.room_id = r.id
        LEFT JOIN dorms d ON r.dorm_id = d.id
        ORDER BY s.updated_at DESC
    ");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "เกิดข้อผิดพลาดในการโหลดข้อมูล: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Diagnostic Dashboard | ระบบจัดการหอพัก</title>
    <!-- Google Font (Kanit) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background-color: #f1f5f9;
            color: #334155;
            padding: 30px 15px;
        }
        .dashboard-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header-title {
            font-weight: 700;
            color: #0f172a;
        }
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            transition: all 0.2s ease;
        }
        .card:hover {
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.05);
        }
        .stat-card {
            background-color: #ffffff;
            border-left: 5px solid #06C755;
        }
        .table-responsive {
            background-color: white;
            border-radius: 16px;
            padding: 10px;
        }
        .avatar-img {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #06C755;
        }
        .btn-action {
            border-radius: 10px;
            font-weight: 500;
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        .code-box {
            background-color: #1e293b;
            color: #38bdf8;
            font-family: monospace;
            padding: 12px;
            border-radius: 10px;
            font-size: 0.85rem;
            overflow-x: auto;
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    
    <!-- Title and Meta -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h2 class="header-title mb-0">🛠️ LINE LIFF Developer Diagnostics</h2>
            <p class="text-muted mb-0">ระบบวินิจฉัยและแก้ไขสถานะการลงทะเบียนของฐานข้อมูล (Live Server)</p>
        </div>
        <div>
            <span class="badge bg-success p-2 fs-6 rounded-pill"><i class="bi bi-database-check"></i> เชื่อมต่อฐานข้อมูลสำเร็จ</span>
        </div>
    </div>

    <!-- Alert Message -->
    <?php if (!empty($status_message)): ?>
        <div class="alert alert-<?= $status_type ?> alert-dismissible fade show border-0 rounded-4 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-info-circle-fill fs-5"></i>
                <div><?= $status_message ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Statistics Grid -->
    <div class="row g-3 mb-4">
        <!-- Total Students -->
        <div class="col-md-4">
            <div class="card stat-card p-3">
                <span class="text-muted small">นักศึกษาที่ลงทะเบียนทั้งหมด</span>
                <h2 class="fw-bold mb-0 text-dark"><?= $stats['total_students'] ?> <span class="fs-6 text-muted font-normal">คน</span></h2>
            </div>
        </div>
        <!-- Mock Students -->
        <div class="col-md-4">
            <div class="card stat-card p-3" style="border-left-color: #f59e0b;">
                <span class="text-muted small">ไอดีจำลองที่ค้างอยู่ (Mock UID)</span>
                <h2 class="fw-bold mb-0 text-warning"><?= $stats['mock_students'] ?> <span class="fs-6 text-muted font-normal">คน</span></h2>
            </div>
        </div>
        <!-- Real LINE Users -->
        <div class="col-md-4">
            <div class="card stat-card p-3" style="border-left-color: #3b82f6;">
                <span class="text-muted small">ผู้ใช้ LINE บนมือถือจริง</span>
                <h2 class="fw-bold mb-0 text-primary"><?= $stats['real_students'] ?> <span class="fs-6 text-muted font-normal">คน</span></h2>
            </div>
        </div>
    </div>

    <!-- Quick Utilities -->
    <div class="card p-4 mb-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-lightning-charge-fill text-warning"></i> แผงแก้ไขข้อผิดพลาดเร่งด่วน</h5>
        <div class="row g-2 align-items-center">
            <div class="col-md-8">
                <p class="text-muted mb-0 small" style="line-height: 1.5;">หากคุณลงทะเบียนผ่าน PC หรือจำลองไอดีและทำให้ติดบั๊กไม่สามารถลงทะเบียนผ่านแอป LINE จริงได้ กดปุ่มด้านขวาเพื่อล้างไอดีทดสอบทั้งหมดออกจากระบบทันที</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="?action=clear_mock" onclick="return confirm('⚠️ ยืนยันการลบข้อมูลจำลองทั้งหมดในฐานข้อมูลหรือไม่? ข้อมูลจริงจะไม่ได้รับผลกระทบ')" class="btn btn-warning w-100 py-2 btn-action text-dark fw-bold shadow-sm d-inline-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-trash3-fill"></i> ล้างไอดีจำลอง (Mock) ทั้งหมด
                </a>
            </div>
        </div>
    </div>

    <!-- Students Database Table -->
    <div class="card p-4">
        <h5 class="fw-bold mb-4"><i class="bi bi-people-fill text-success"></i> รายชื่อผู้ลงทะเบียนในระบบ</h5>
        
        <?php if (empty($students)): ?>
            <div class="text-center py-5">
                <i class="bi bi-emoji-neutral fs-1 text-muted mb-3 d-block"></i>
                <p class="text-muted mb-0">ไม่พบรายชื่อผู้ลงทะเบียนในตาราง students</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>โปรไฟล์ LINE</th>
                            <th>รหัสนักศึกษา</th>
                            <th>ชื่อ - นามสกุล</th>
                            <th>เบอร์โทร</th>
                            <th>หอพัก / ห้อง</th>
                            <th>LINE UID</th>
                            <th class="text-center">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $s): ?>
                            <?php 
                            $is_mock = strpos($s['line_uid'] ?? '', 'MOCK') === 0;
                            $uid_display = $s['line_uid'] ? htmlspecialchars($s['line_uid']) : '<em class="text-muted">ไม่มี (ยังไม่ผูก)</em>';
                            if ($s['line_uid'] && strlen($s['line_uid']) > 15) {
                                $uid_display = substr($s['line_uid'], 0, 12) . '...';
                            }
                            ?>
                            <tr class="<?= $is_mock ? 'table-warning' : '' ?>">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= $s['line_profile_img'] ? htmlspecialchars($s['line_profile_img']) : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png' ?>" class="avatar-img" alt="Avatar">
                                        <?php if ($is_mock): ?>
                                            <span class="badge bg-warning text-dark px-2" style="font-size:0.7rem;">Mock ID</span>
                                        <?php elseif ($s['line_uid']): ?>
                                            <span class="badge bg-primary px-2" style="font-size:0.7rem;">Real User</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($s['student_id']) ?></td>
                                <td><?= htmlspecialchars($s['name']) ?></td>
                                <td><?= htmlspecialchars($s['phone']) ?></td>
                                <td>
                                    <span class="d-block text-dark small" style="font-weight: 500;"><?= htmlspecialchars($s['dorm_name'] ?? 'ไม่มีหอพัก') ?></span>
                                    <span class="text-muted small">ห้อง: <?= htmlspecialchars($s['room_number'] ?? 'ไม่มีห้อง') ?></span>
                                </td>
                                <td>
                                    <span class="small font-monospace p-1 rounded bg-light" title="<?= htmlspecialchars($s['line_uid'] ?? '') ?>"><?= $uid_display ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <?php if ($s['line_uid']): ?>
                                            <a href="?action=unbind&student_id=<?= urlencode($s['student_id']) ?>" onclick="return confirm('🔒 ต้องการปลดการเชื่อมต่อ LINE UID ของนักศึกษาคนนี้ใช่หรือไม่? หลังจากปลดแล้ว บัญชีนี้จะลงทะเบียนใหม่ได้ทันที')" class="btn btn-outline-warning btn-action d-inline-flex align-items-center gap-1" title="ปลด LINE UID เพื่อลงทะเบียนใหม่">
                                                <i class="bi bi-unlock-fill"></i> ปลด LINE
                                            </a>
                                        <?php endif; ?>
                                        <a href="?action=delete&student_id=<?= urlencode($s['student_id']) ?>" onclick="return confirm('⚠️ ยืนยันการลบนักศึกษาคนนี้ออกจากระบบอย่างถาวรหรือไม่?')" class="btn btn-outline-danger btn-action d-inline-flex align-items-center gap-1" title="ลบข้อมูลนักศึกษาคนนี้">
                                            <i class="bi bi-trash-fill"></i> ลบ
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Security Warning & Clean up instructions -->
    <div class="mt-4 p-3 bg-light border border-2 border-dashed rounded-4 text-center">
        <span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill"></i> คำเตือนความปลอดภัย:</span> 
        <span class="text-muted small">กรุณาลบไฟล์ <code>diagnose.php</code> ออกจากเซิร์ฟเวอร์ หรือเปลี่ยนชื่อเป็นชื่ออื่นหลังจากทดสอบเสร็จสิ้น เพื่อไม่ให้บุคคลภายนอกสามารถเข้ามาเห็นและแก้ไขรายชื่อผู้ใช้ระบบได้</span>
    </div>

</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
