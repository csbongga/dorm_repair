<?php
/**
 * API สำหรับดึงข้อมูลห้องพักตามรหัสหอพัก (dorm_id)
 * พัฒนาสำหรับระบบแจ้งซ่อมหอพัก (dorm_repair)
 */

header('Content-Type: application/json; charset=utf-8');
require_once 'connect.php';

$response = [
    'success' => false,
    'rooms' => [],
    'message' => ''
];

try {
    // ตรวจสอบว่ามีการส่งค่า dorm_id มาหรือไม่
    if (isset($_GET['dorm_id'])) {
        $dorm_id = filter_var($_GET['dorm_id'], FILTER_VALIDATE_INT);

        if ($dorm_id !== false) {
            // ดึงข้อมูลห้องพักที่ตรงกับ dorm_id และคัดกรองห้องที่พร้อมใช้งาน
            // ตาราง rooms: id, dorm_id, room_number, status
            $stmt = $pdo->prepare("
                SELECT id, room_number, status 
                FROM rooms 
                WHERE dorm_id = :dorm_id 
                ORDER BY room_number ASC
            ");
            $stmt->execute(['dorm_id' => $dorm_id]);
            $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response['success'] = true;
            $response['rooms'] = $rooms;
            $response['message'] = 'ดึงข้อมูลห้องสำเร็จ';
        } else {
            $response['message'] = 'รูปแบบรหัสหอพักไม่ถูกต้อง';
        }
    } else {
        $response['message'] = 'ไม่พบรหัสหอพักที่ต้องการค้นหา';
    }
} catch (PDOException $e) {
    // บันทึก Log ข้อผิดพลาดจริงและแจ้งเตือนผู้ใช้ด้วยข้อความทั่วไปเพื่อความปลอดภัย
    error_log("API get_rooms error: " . $e->getMessage());
    $response['message'] = 'เกิดข้อผิดพลาดในระบบฐานข้อมูล';
}

// ส่งผลลัพธ์กลับในรูปแบบ JSON รองรับภาษาไทยสมบูรณ์
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
