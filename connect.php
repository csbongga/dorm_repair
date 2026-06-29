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

    // ── Auto-cycle: สร้างและตั้งรอบบิลเดือนปัจจุบันอัตโนมัติ ─────────────
    // ทำงานทุกครั้งที่โหลดหน้า แต่ query จริงเกิดเฉพาะเมื่อรอบเปลี่ยน
    try {
        $m   = (int)date('n');
        $yBE = (int)date('Y') + 543;
        $cid = $yBE . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);

        $curCid = $pdo->query("SELECT id FROM bill_cycles WHERE is_current = 1 LIMIT 1")->fetchColumn();

        if ($curCid !== $cid) {
            $thaiMonths = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
                           'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
            $thaiShort  = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.',
                           'ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
            $nextM = $m === 12 ? 1 : $m + 1;
            $nextY = $m === 12 ? $yBE + 1 : $yBE;
            $label = $thaiMonths[$m] . ' ' . $yBE;
            $short = $thaiShort[$m];
            $due   = '12 ' . $thaiShort[$nextM] . ' ' . $nextY;

            $pdo->prepare("INSERT IGNORE INTO bill_cycles (id, label, short_label, due_text, is_current) VALUES (?,?,?,?,0)")
                ->execute([$cid, $label, $short, $due]);
            $pdo->query("UPDATE bill_cycles SET is_current = 0");
            $pdo->prepare("UPDATE bill_cycles SET is_current = 1 WHERE id = ?")->execute([$cid]);
        }
    } catch (PDOException $_e) { /* bill_cycles ยังไม่มีในระบบ — ข้ามไป */ }
    // ─────────────────────────────────────────────────────────────────────

    // ── Auto-migrate: เพิ่มคอลัมน์เก็บประวัติเรทราคาและยอดเงิน ────────────────
    try {
        $pdo->query("SELECT water_fine FROM bill_meters LIMIT 1");
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER TABLE bill_meters ADD COLUMN water_fine DECIMAL(10,2) DEFAULT 0");
        } catch (PDOException $e2) { }
    }

    try {
        $pdo->query("SELECT water_rate, elec_rate, water_amt, elec_amt FROM bill_meters LIMIT 1");
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER TABLE bill_meters 
                ADD COLUMN water_rate DECIMAL(10,2) DEFAULT NULL,
                ADD COLUMN elec_rate DECIMAL(10,2) DEFAULT NULL,
                ADD COLUMN water_amt DECIMAL(10,2) DEFAULT NULL,
                ADD COLUMN elec_amt DECIMAL(10,2) DEFAULT NULL
            ");
        } catch (PDOException $e2) { }
    }
    
    // ── Data Migration: คำนวณข้อมูลเก่าที่ยังไม่มีเรทยอดเงิน (ทำครั้งเดียว) ───────
    try {
        $needsMigration = $pdo->query("SELECT 1 FROM bill_meters WHERE water_amt IS NULL AND water_curr IS NOT NULL LIMIT 1")->fetchColumn();
        if ($needsMigration) {
            $ratesStmt = $pdo->query("SELECT setting_key, value FROM bill_settings WHERE setting_key IN ('rate_water','rate_elec')");
            $rates     = $ratesStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $rW = (float)($rates['rate_water'] ?? 0);
            $rE = (float)($rates['rate_elec']  ?? 0);
            
            $pdo->exec("
                UPDATE bill_meters 
                SET water_rate = {$rW}, 
                    elec_rate = {$rE},
                    water_amt = IF(water_curr IS NOT NULL AND water_prev IS NOT NULL, (water_curr - water_prev) * {$rW}, NULL),
                    elec_amt  = IF(elec_curr IS NOT NULL AND elec_prev IS NOT NULL, (elec_curr - elec_prev) * {$rE}, NULL)
                WHERE water_amt IS NULL AND water_curr IS NOT NULL
            ");
        }
    } catch (PDOException $e) { }
    // ─────────────────────────────────────────────────────────────────────

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
