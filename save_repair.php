<?php
/**
 * บันทึกข้อมูลใบแจ้งซ่อมหอพัก (save_repair.php)
 * พัฒนาโดย Senior Frontend & PHP Developer สำหรับระบบแจ้งซ่อมหอพัก (dorm_repair)
 */

header('Content-Type: text/html; charset=utf-8');
require_once 'connect.php';
require_once 'includes/image_resize.php';

$success = false;
$error_message = '';
$ticket_id = '';

// ตรวจสอบชนิดการรับส่งข้อมูล (ต้องเป็น POST เท่านั้น)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับข้อมูลและทำความสะอาดข้อมูลเบื้องต้น
    $student_id       = trim($_POST['student_id'] ?? '');
    $reporter_name    = trim($_POST['fullname'] ?? '');
    $room_id          = filter_var($_POST['room_id'] ?? null, FILTER_VALIDATE_INT);
    $additional_desc  = trim($_POST['description'] ?? '');
    $selected_items   = $_POST['repair_items'] ?? [];
    $repair_quantities = $_POST['repair_quantities'] ?? []; // array [item_master_id => quantity]

    // 1. ตรวจสอบข้อมูลจำเป็นเบื้องต้น
    if (empty($student_id) || empty($reporter_name) || !$room_id || empty($selected_items)) {
        $error_message = 'ข้อมูลไม่ครบถ้วน กรุณากรอกรหัสนักศึกษา ชื่อ เลือกห้องพัก และเลือกอุปกรณ์แจ้งซ่อมอย่างน้อย 1 รายการ';
    } else {
        try {
            // ดึงข้อมูลเบอร์โทรศัพท์ของนักศึกษาโดยอัตโนมัติจากฐานข้อมูล
            $phoneStmt = $pdo->prepare("SELECT phone FROM students WHERE student_id = :student_id LIMIT 1");
            $phoneStmt->execute(['student_id' => $student_id]);
            $studentPhone = $phoneStmt->fetchColumn();
            $reporter_phone = $studentPhone ? $studentPhone : null;

            // 2. จัดการเรื่องอัปโหลดรูปภาพหลายรูป (Multiple Images Upload)
            $uploaded_files = [];
            if (isset($_FILES['repair_images']) && is_array($_FILES['repair_images']['name'])) {
                $files = $_FILES['repair_images'];
                $fileCount = count($files['name']);
                
                // ตรวจสอบเบื้องต้น
                if ($fileCount === 0 || empty($files['name'][0])) {
                    $error_message = 'กรุณาอัปโหลดรูปภาพประกอบอย่างน้อย 1 รูป';
                } else {
                    for ($i = 0; $i < $fileCount; $i++) {
                        if ($files['error'][$i] === UPLOAD_ERR_OK) {
                            $fileTmpPath   = $files['tmp_name'][$i];
                            $fileName      = $files['name'][$i];
                            $fileSize      = $files['size'][$i];
                            
                            // ตรวจสอบนามสกุลไฟล์
                            $fileNameCmps  = explode(".", $fileName);
                            $fileExtension = strtolower(end($fileNameCmps));
                            $allowedExtensions = ['jpg', 'jpeg', 'png'];

                            if (in_array($fileExtension, $allowedExtensions)) {
                                // จำกัดขนาดไฟล์ที่ 5MB ต่อรูป
                                if ($fileSize <= 5 * 1024 * 1024) {
                                    // สร้างโฟลเดอร์สำหรับเก็บรูปภาพหากยังไม่มี
                                    $uploadDir = 'uploads/';
                                    if (!is_dir($uploadDir)) {
                                        mkdir($uploadDir, 0755, true);
                                    }

                                    // ตั้งชื่อไฟล์ใหม่เพื่อป้องกันชื่อซ้ำและตัดอักขระพิเศษออก
                                    $newFileName = 'repair_' . date('Ymd_His') . '_' . uniqid() . '_' . $i . '.' . $fileExtension;
                                    $dest_path = $uploadDir . $newFileName;

                                    if (resizeAndSave($fileTmpPath, $dest_path)) {
                                        $uploaded_files[] = $dest_path;
                                    } else {
                                        $error_message = "ไม่สามารถอัปโหลดไฟล์รูปภาพลำดับที่ " . ($i + 1) . " ได้";
                                        break;
                                    }
                                } else {
                                    $error_message = "รูปภาพลำดับที่ " . ($i + 1) . " มีขนาดใหญ่เกินกว่า 5MB ที่กำหนดไว้";
                                    break;
                                }
                            } else {
                                $error_message = "รูปภาพลำดับที่ " . ($i + 1) . " มีรูปแบบไม่ถูกต้อง รองรับเฉพาะ .jpg, .jpeg และ .png";
                                break;
                            }
                        } else {
                            $error_message = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพลำดับที่ " . ($i + 1);
                            break;
                        }
                    }
                }
            } else {
                $error_message = 'กรุณาอัปโหลดรูปภาพประกอบอุปกรณ์ที่ชำรุดอย่างน้อย 1 รูป';
            }

            // หากจัดการเรื่องรูปภาพเสร็จสิ้นและไม่มีข้อผิดพลาด
            if (empty($error_message)) {
                // 3. เริ่มต้น Database Transaction เพื่อความถูกต้อง 100%
                $pdo->beginTransaction();

                // สร้าง Ticket ID แบบสุ่มและเป็นเอกลักษณ์ (Unique)
                // รูปแบบ: REP-ปีเดือนวัน-สุ่มสี่ตัว (เช่น REP-20260527-4859)
                do {
                    $random_suffix = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $temp_ticket = 'REP-' . date('Ymd') . '-' . $random_suffix;

                    // เช็คว่ามีซ้ำในระบบหรือไม่
                    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM repair_requests WHERE ticket_id = ?");
                    $stmtCheck->execute([$temp_ticket]);
                    $exists = $stmtCheck->fetchColumn() > 0;
                } while ($exists);

                $ticket_id = $temp_ticket;

                // 4. บันทึกลงตารางแจ้งซ่อมหลัก (repair_requests)
                $insertRequest = $pdo->prepare("
                    INSERT INTO repair_requests (ticket_id, room_id, student_id, reporter_type, reporter_name, reporter_phone, additional_details, status) 
                    VALUES (:ticket_id, :room_id, :student_id, 'student', :reporter_name, :reporter_phone, :additional_details, 'รอดำเนินการ')
                ");
                $insertRequest->execute([
                    'ticket_id'          => $ticket_id,
                    'room_id'            => $room_id,
                    'student_id'         => $student_id,
                    'reporter_name'      => $reporter_name,
                    'reporter_phone'     => $reporter_phone,
                    'additional_details' => $additional_desc
                ]);

                // ดึงรหัสแจ้งซ่อมล่าสุดเพื่อนำมาเป็น Foreign Key
                $request_id = $pdo->lastInsertId();

                // 5. บันทึกรูปภาพประกอบหลายรูปลงตาราง (repair_images)
                if (!empty($uploaded_files)) {
                    $insertImage = $pdo->prepare("
                        INSERT INTO repair_images (request_id, image_url) 
                        VALUES (:request_id, :image_url)
                    ");
                    foreach ($uploaded_files as $image_path) {
                        $insertImage->execute([
                            'request_id' => $request_id,
                            'image_url'  => $image_path
                        ]);
                    }
                }

                // 6. บันทึกรายการชิ้นส่วนอุปกรณ์ที่ชำรุดรายชิ้นลงในตาราง (repair_items)
                $insertItem = $pdo->prepare("
                    INSERT INTO repair_items (request_id, item_master_id, quantity, status)
                    VALUES (:request_id, :item_master_id, :quantity, 'รอดำเนินการ')
                ");
                foreach ($selected_items as $item_master_id) {
                    $item_id = filter_var($item_master_id, FILTER_VALIDATE_INT);
                    // ใช้ค่าจำนวนที่ผู้ใช้ระบุ ถ้าไม่มีหรือ <= 0 ใช้ 1
                    $qty = isset($repair_quantities[$item_id]) ? max(1, (int)$repair_quantities[$item_id]) : 1;
                    $insertItem->execute([
                        'request_id'     => $request_id,
                        'item_master_id' => $item_id,
                        'quantity'       => $qty
                    ]);
                }

                // ยืนยันการทำรายการทั้งหมดสำเร็จ
                $pdo->commit();
                $success = true;
            }
        } catch (PDOException $e) {
            // ม้วนกลับข้อมูลทั้งหมดกรณีเกิดข้อผิดพลาด
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Save Repair Database Failure: " . $e->getMessage());
            $error_message = 'เกิดข้อผิดพลาดทางระบบฐานข้อมูล: ' . $e->getMessage();
        }
    }
} else {
    $error_message = 'ไม่สนับสนุนการเข้าใช้งานด้วยรูปแบบส่งข้อมูลดังกล่าว';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สถานะการส่งข้อมูลแจ้งซ่อม</title>
    <!-- Google Font (Kanit) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background-color: #f8fafc;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
    </style>
</head>
<body>

    <?php if ($success): ?>
        <script>
            Swal.fire({
                title: 'ส่งแจ้งซ่อมสำเร็จ! 🛠️',
                html: 'ระบบได้รับเรื่องแจ้งซ่อมของท่านเรียบร้อยแล้ว<br>หมายเลขใบงาน: <strong style="color: #06C755; font-size: 1.25rem;"><?= htmlspecialchars($ticket_id) ?></strong>',
                icon: 'success',
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#06C755',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // ส่งผู้ใช้งานไปยังหน้าติดตามสถานะงานแจ้งซ่อมทันทีเมื่อทำรายการสำเร็จ
                    window.location.href = 'repair_status.php';
                }
            });
        </script>
    <?php else: ?>
        <script>
            Swal.fire({
                title: 'ไม่สามารถบันทึกได้! ❌',
                text: '<?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>',
                icon: 'error',
                confirmButtonText: 'กลับไปแก้ไขข้อมูล',
                confirmButtonColor: '#dc3545',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // ย้อนกลับไปหน้าฟอร์มเพื่อไม่ให้ข้อมูลที่กรอกไว้แล้วหายไป
                    window.history.back();
                }
            });
        </script>
    <?php endif; ?>

</body>
</html>
