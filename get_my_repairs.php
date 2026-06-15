<?php
/**
 * API สำหรับดึงข้อมูลการแจ้งซ่อมของนักศึกษาตาม LINE UID
 * พัฒนาโดย Senior Frontend & PHP Developer สำหรับระบบแจ้งซ่อมหอพัก (dorm_repair)
 */

header('Content-Type: application/json; charset=utf-8');
require_once 'connect.php';

$response = [
    'success' => false,
    'requests' => [],
    'message' => ''
];

try {
    if (isset($_GET['line_uid'])) {
        $line_uid = trim($_GET['line_uid']);

        if (!empty($line_uid)) {
            // 1. ดึงใบแจ้งซ่อมหลักทั้งหมดของนักศึกษาที่มี LINE UID นี้
            $stmt = $pdo->prepare("
                SELECT r.id, r.ticket_id, r.additional_details, r.status, r.created_at, rm.room_number, d.name AS dorm_name
                FROM repair_requests r
                INNER JOIN rooms rm ON r.room_id = rm.id
                INNER JOIN dorms d ON rm.dorm_id = d.id
                INNER JOIN students s ON r.student_id = s.student_id
                WHERE s.line_uid = :line_uid
                ORDER BY r.created_at DESC
            ");
            $stmt->execute(['line_uid' => $line_uid]);
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. ดึงรายละเอียดอุปกรณ์ชำรุดและรูปภาพประกอบสำหรับแต่ละใบงาน
            $finalRequests = [];
            foreach ($requests as $req) {
                $requestId = $req['id'];

                // ดึงรายการอุปกรณ์ชำรุดรายชิ้นพร้อมสถานะเฉพาะของแต่ละอุปกรณ์
                $itemStmt = $pdo->prepare("
                    SELECT ri.quantity, ri.status, rim.item_name, rim.category
                    FROM repair_items ri
                    INNER JOIN repair_items_master rim ON ri.item_master_id = rim.id
                    WHERE ri.request_id = :request_id
                ");
                $itemStmt->execute(['request_id' => $requestId]);
                $req['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

                // ดึงรูปภาพประกอบทั้งหมดของใบงานนี้
                $imgStmt = $pdo->prepare("
                    SELECT image_url 
                    FROM repair_images 
                    WHERE request_id = :request_id
                ");
                $imgStmt->execute(['request_id' => $requestId]);
                $req['images'] = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

                $finalRequests[] = $req;
            }

            $response['success'] = true;
            $response['requests'] = $finalRequests;
            $response['message'] = 'ดึงข้อมูลประวัติการแจ้งซ่อมสำเร็จ';
        } else {
            $response['message'] = 'LINE UID ต้องไม่เป็นค่าว่าง';
        }
    } else {
        $response['message'] = 'กรุณาระบุพารามิเตอร์ line_uid';
    }
} catch (PDOException $e) {
    error_log("API get_my_repairs error: " . $e->getMessage());
    $response['message'] = 'เกิดข้อผิดพลาดในระบบฐานข้อมูล';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
