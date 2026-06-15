<?php
/**
 * API สำหรับดึงข้อมูลนักศึกษาตาม LINE UID
 * พัฒนาโดย Senior Frontend & PHP Developer สำหรับระบบแจ้งซ่อมหอพัก (dorm_repair)
 */

header('Content-Type: application/json; charset=utf-8');
require_once 'connect.php';

$response = [
    'success' => false,
    'student' => null,
    'message' => ''
];

try {
    // ตรวจสอบว่ามีการส่งค่า line_uid มาหรือไม่
    if (isset($_GET['line_uid'])) {
        $line_uid = trim($_GET['line_uid']);

        if (!empty($line_uid)) {
            // ดึงข้อมูลนักศึกษาเชื่อมโยงกับข้อมูลห้องพัก (rooms) เพื่อให้ได้ dorm_id ของผู้ใช้คนนั้น
            // ตาราง students: student_id, name, phone, room_id, line_uid
            // ตาราง rooms: id, room_number, dorm_id
            $stmt = $pdo->prepare("
                SELECT s.student_id, s.name AS fullname, s.phone, s.role, s.room_id, r.dorm_id, d.name AS dorm_name, r.room_number
                FROM students s
                INNER JOIN rooms r ON s.room_id = r.id
                INNER JOIN dorms d ON r.dorm_id = d.id
                WHERE s.line_uid = :line_uid
                LIMIT 1
            ");
            $stmt->execute(['line_uid' => $line_uid]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($student) {
                $response['success'] = true;
                $response['student'] = $student;
                $response['message'] = 'ดึงข้อมูลนักศึกษาสำเร็จ';
            } else {
                $response['message'] = 'ไม่พบข้อมูลการลงทะเบียนสำหรับ LINE UID นี้';
            }
        } else {
            $response['message'] = 'LINE UID ต้องไม่เป็นค่าว่าง';
        }
    } else {
        $response['message'] = 'กรุณาส่งพารามิเตอร์ line_uid ที่ต้องการค้นหา';
    }
} catch (PDOException $e) {
    error_log("API get_student error: " . $e->getMessage());
    $response['message'] = 'เกิดข้อผิดพลาดในระบบฐานข้อมูล';
}

// ส่งผลลัพธ์กลับในรูปแบบ JSON รองรับภาษาไทยสมบูรณ์
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
