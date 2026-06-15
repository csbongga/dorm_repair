<?php
/**
 * ไฟล์สำหรับเชื่อมต่อฐานข้อมูล MySQL ด้วย PDO
 * พัฒนาสำหรับระบบแจ้งซ่อมหอพัก (dorm_repair)
 */

// กำหนดรายละเอียดการเชื่อมต่อฐานข้อมูล (กรุณาแก้ไขรายละเอียดเหล่านี้เมื่อนำไปใช้งานจริง)
$host     = 'localhost';         // โฮสต์ของฐานข้อมูล (เช่น localhost หรือ 127.0.0.1)
$db_name  = 'dorm_repair';       // ชื่อฐานข้อมูล
$username = 'dorm_repair';     // ชื่อผู้ใช้งานระบบฐานข้อมูล (Placeholder)
$password = '*1Foreverlove';     // รหัสผ่านระบบฐานข้อมูล (Placeholder)
$charset  = 'utf8mb4';           // ใช้ utf8mb4 เพื่อรองรับภาษาไทยอย่างสมบูรณ์ (รวมถึง Emoji)

// กำหนด DSN (Data Source Name)
$dsn = "mysql:host=$host;dbname=$db_name;charset=$charset";

// กำหนด Options สำหรับ PDO เพื่อความปลอดภัยและประสิทธิภาพสูงสุด
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // โยน Exception เมื่อเกิดข้อผิดพลาด
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // ดึงข้อมูลเป็น Associative Array เสมอ
    PDO::ATTR_EMULATE_PREPARES   => false,                  // ใช้ Real Prepared Statements ป้องกัน SQL Injection อย่างแท้จริง
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci" // มั่นใจเรื่องภาษาไทย 100%
];

try {
    // สร้างการเชื่อมต่อด้วย PDO
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // ตั้งค่า Timezone ให้ตรงกับประเทศไทย (ตัวเลือกเพิ่มเติมเพื่อความถูกต้องของเวลา)
    $pdo->exec("SET time_zone = '+07:00'");
    
} catch (PDOException $e) {
    // บันทึกรายละเอียดข้อผิดพลาดจริงลง Error Log ของ Server (ป้องกันการ Leak ข้อมูลเชื่อมต่อให้ภายนอกเห็น)
    error_log("Database Connection Failure: " . $e->getMessage());
    
    // แสดงข้อความแจ้งเตือนภาษาไทยที่เข้าใจง่ายและสุภาพ
    header('Content-Type: text/html; charset=utf-8');
    die("<!DOCTYPE html>
<html lang='th'>
<head>
    <meta charset='UTF-8'>
    <title>เกิดข้อผิดพลาดในการเชื่อมต่อ</title>
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f8d7da; color: #721c24; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .error-container { background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); border-left: 5px solid #dc3545; max-width: 500px; text-align: center; }
        h1 { font-size: 24px; margin-bottom: 15px; color: #dc3545; }
        p { font-size: 16px; line-height: 1.6; color: #495057; }
    </style>
</head>
<body>
    <div class='error-container'>
        <h1>⚠️ เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล</h1>
        <p>ขออภัยครับ ระบบไม่สามารถเชื่อมต่อกับฐานข้อมูลหอพัก (dorm_repair) ได้ในขณะนี้</p>
        <p>กรุณาตรวจสอบการตั้งค่า Host, Username หรือ Password ในไฟล์ <code>connect.php</code> และติดต่อผู้ดูแลระบบหากปัญหายังคงอยู่</p>
    </div>
</body>
</html>");
}
