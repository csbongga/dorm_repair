<?php
/**
 * ไฟล์ admin_dashboard.php
 * หน้าจอระบบจัดการแจ้งซ่อมสำหรับเจ้าหน้าที่และช่างซ่อม (Admin Dashboard)
 * พัฒนาโดย Senior Full Stack PHP Developer ด้วยแนวคิด Modern, Clean, and Professional Design
 */

// 1. นำเข้าไฟล์เชื่อมต่อฐานข้อมูลเป็นบรรทัดแรกสุดตาม Requirement
require_once 'connect.php';

try {
    // 2. เขียนคำสั่ง SQL (PDO) เพื่อดึงข้อมูลการแจ้งซ่อมทั้งหมด โดยใช้การ JOIN ตาม SQL ที่ระบุ
    $sql = "SELECT r.id, 
                   r.ticket_id, 
                   d.name AS dorm_name, 
                   rm.room_number, 
                   r.reporter_name, 
                   r.status, 
                   r.created_at 
            FROM repair_requests r 
            JOIN rooms rm ON r.room_id = rm.id 
            JOIN dorms d ON rm.dorm_id = d.id 
            ORDER BY r.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $requests = $stmt->fetchAll();

    // 3. คำนวณหาจำนวนสถานะต่างๆ เพื่อแสดงผลบน Stat Cards
    $total_jobs = count($requests);
    $pending_jobs = 0;
    $processing_jobs = 0;
    $completed_jobs = 0;
    $cancelled_jobs = 0;

    foreach ($requests as $req) {
        switch ($req['status']) {
            case 'รอดำเนินการ':
                $pending_jobs++;
                break;
            case 'กำลังดำเนินการ':
                $processing_jobs++;
                break;
            case 'เสร็จสิ้น':
                $completed_jobs++;
                break;
            case 'ยกเลิก':
                $cancelled_jobs++;
                break;
        }
    }

} catch (PDOException $e) {
    // บันทึกข้อผิดพลาดจริงลงระบบ Log ของเซิร์ฟเวอร์
    error_log("Dashboard Query Failure: " . $e->getMessage());
    $error_msg = "ขออภัย เกิดข้อผิดพลาดทางเทคนิคในการดึงข้อมูลแจ้งซ่อม";
}

/**
 * ฟังก์ชันแปลงรูปแบบวันที่และเวลาเป็นภาษาไทยเชิงวิชาชีพ (พ.ศ.) พร้อมเวลา
 * @param string $dateString วันที่จากฐานข้อมูล
 * @return string รูปแบบวันที่ภาษาไทย
 */
function formatThaiDate($dateString) {
    $months = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
        7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];
    $timestamp = strtotime($dateString);
    if (!$timestamp) return '-';
    
    $d = date('j', $timestamp);
    $m = date('n', $timestamp);
    $y = date('Y', $timestamp) + 543; // แปลง ค.ศ. เป็น พ.ศ.
    $time = date('H:i', $timestamp);
    
    return "$d {$months[$m]} $y ($time น.)";
}

/**
 * ฟังก์ชันส่งคืนโค้ด HTML ของ Badge สถานะตามฟิลด์ status
 * @param string $status สถานะงานซ่อม
 * @return string HTML Badge
 */
function getStatusBadge($status) {
    switch ($status) {
        case 'รอดำเนินการ':
            return '<span class="badge bg-danger rounded-pill px-3 py-2 fw-medium text-white shadow-sm"><i class="bi bi-hourglass-split me-1"></i>รอดำเนินการ</span>';
        case 'กำลังดำเนินการ':
            return '<span class="badge bg-warning text-dark rounded-pill px-3 py-2 fw-medium shadow-sm"><i class="bi bi-gear-fill me-1"></i>กำลังดำเนินการ</span>';
        case 'เสร็จสิ้น':
            return '<span class="badge bg-success rounded-pill px-3 py-2 fw-medium text-white shadow-sm"><i class="bi bi-check-circle-fill me-1"></i>เสร็จสิ้น</span>';
        case 'ยกเลิก':
            return '<span class="badge bg-secondary rounded-pill px-3 py-2 fw-medium text-white shadow-sm"><i class="bi bi-x-circle-fill me-1"></i>ยกเลิก</span>';
        default:
            return '<span class="badge bg-light text-dark rounded-pill px-3 py-2 fw-medium border shadow-sm">' . htmlspecialchars($status) . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการแจ้งซ่อม หอพักราชภัฏ - ส่วนเจ้าหน้าที่</title>
    <!-- SEO Optimization -->
    <meta name="description" content="ระบบจัดการใบงานแจ้งซ่อมสำหรับเจ้าหน้าที่และช่างซ่อมประจำหอพัก มหาวิทยาลัยราชภัฏ">
    <meta name="author" content="Senior Full Stack PHP Developer">
    
    <!-- Google Font (Kanit) เพื่อความทันสมัยและสวยงามกลมกลืนตามมาตรฐานสากล -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CSS (ผ่าน CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1e3a8a;       /* สีกรมท่าสง่างามแบบองค์กร */
            --primary-hover: #172554;
            --accent-color: #0284c7;        /* สีฟ้าสดสำหรับความกระฉับกระเฉง */
            --bg-body: #f8fafc;             /* สีพื้นหลังครีมออฟไวท์เย็นตา */
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --transition-speed: 0.25s;
        }

        body {
            font-family: 'Kanit', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ตกแต่ง Navbar ให้ดูหรูหราแบบ Modern Flat */
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1e40af 100%);
            box-shadow: 0 4px 15px rgba(30, 58, 138, 0.15);
            padding: 15px 0;
        }

        .navbar-brand-title {
            font-weight: 600;
            letter-spacing: 0.5px;
            font-size: 1.25rem;
            color: #ffffff;
        }

        .navbar-brand-subtitle {
            font-size: 0.85rem;
            opacity: 0.85;
            font-weight: 300;
        }

        /* ตกแต่ง Container และ Layout */
        .main-content {
            flex: 1;
            padding: 40px 0;
        }

        /* การตกแต่งส่วนหัวและปุ่มสร้างงานใหม่ */
        .page-header-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-title {
            font-weight: 700;
            color: var(--primary-color);
            position: relative;
            padding-left: 15px;
            margin-bottom: 0;
        }

        .page-title::before {
            content: '';
            position: absolute;
            left: 0;
            top: 5px;
            bottom: 5px;
            width: 5px;
            background-color: var(--accent-color);
            border-radius: 4px;
        }

        /* Stat Cards ดีไซน์พรีเมียม */
        .stat-card {
            background-color: var(--bg-card);
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 16px;
            padding: 22px;
            transition: all var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.08);
        }

        .stat-card::after {
            content: '';
            position: absolute;
            right: -20px;
            bottom: -20px;
            width: 100px;
            height: 100px;
            background-color: rgba(0, 0, 0, 0.015);
            border-radius: 50%;
            z-index: 1;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
            z-index: 2;
            position: relative;
        }

        .stat-val {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 6px;
            color: var(--text-main);
        }

        .stat-label {
            font-size: 0.88rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* ตกแต่งตารางและกล่องเก็บข้อมูลหลัก */
        .content-card {
            background-color: var(--bg-card);
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            padding: 30px;
            margin-top: 25px;
        }

        .table-custom-wrapper {
            margin-top: 15px;
        }

        .table-custom {
            vertical-align: middle;
            margin-bottom: 0;
        }

        .table-custom thead th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.82rem;
            letter-spacing: 0.5px;
            padding: 16px 20px;
            border-bottom: 2px solid #e2e8f0;
        }

        .table-custom tbody tr {
            transition: background-color var(--transition-speed) ease;
        }

        .table-custom tbody tr:hover {
            background-color: #f1f5f9;
        }

        .table-custom tbody td {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.92rem;
        }

        .ticket-id-link {
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .ticket-id-link:hover {
            color: var(--accent-color);
            text-decoration: underline;
        }

        /* ปุ่มดูรายละเอียด */
        .btn-action-view {
            background-color: #ffffff;
            border: 1.5px solid #cbd5e1;
            color: #334155;
            font-size: 0.85rem;
            font-weight: 500;
            padding: 6px 16px;
            border-radius: 8px;
            transition: all var(--transition-speed) ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-action-view:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(30, 58, 138, 0.15);
        }

        /* ระบบค้นหาและตัวกรองที่ลื่นไหล */
        .filter-section {
            background-color: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px dashed #e2e8f0;
        }

        .search-input-icon {
            position: relative;
        }

        .search-input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .search-input-icon input {
            padding-left: 42px;
        }

        /* การปรับแต่งความรับผิดชอบของหน้าจอ (Responsive Support) */
        @media (max-width: 768px) {
            .stat-card-row > div {
                margin-bottom: 15px;
            }
            .content-card {
                padding: 15px;
            }
            .table-custom thead th, 
            .table-custom tbody td {
                padding: 12px 10px;
            }
        }

        /* Footer */
        .footer-custom {
            background-color: #0f172a;
            color: #94a3b8;
            padding: 25px 0;
            font-size: 0.85rem;
            margin-top: auto;
            border-top: 1px solid #1e293b;
        }
    </style>
</head>
<body>

    <!-- Header / Navbar ด้านบนสุด -->
    <nav class="navbar navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                <span class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                    <i class="bi bi-tools" style="font-size: 1.15rem;"></i>
                </span>
                <div>
                    <div class="navbar-brand-title">ระบบจัดการแจ้งซ่อม หอพักราชภัฏ</div>
                    <div class="navbar-brand-subtitle">ส่วนเจ้าหน้าที่และช่างซ่อมบำรุง (Staff Management Console)</div>
                </div>
            </a>
            <div class="d-none d-md-flex align-items-center gap-3">
                <span class="text-white opacity-75 small">
                    <i class="bi bi-person-circle me-1"></i> เจ้าหน้าที่ดูแลระบบ
                </span>
                <span class="badge bg-light text-primary fw-semibold px-3 py-2">
                    <i class="bi bi-shield-check me-1"></i> Admin Panel
                </span>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="main-content">
        <div class="container">
            
            <!-- Page Header และชื่อหน้าจอ -->
            <div class="page-header-box">
                <div>
                    <h2 class="page-title">แดชบอร์ดสรุปงานแจ้งซ่อมหอพัก</h2>
                    <p class="text-muted mb-0 mt-1">บริหารจัดการใบงาน ติดตามความก้าวหน้า และควบคุมการทำงานของทีมช่าง</p>
                </div>
                <div>
                    <!-- สามารถขยายเพิ่มฟังก์ชันอื่นๆ ได้ที่ปุ่มฝั่งขวา -->
                    <button class="btn btn-outline-primary rounded-pill px-3 py-2 d-inline-flex align-items-center gap-2" onclick="window.location.reload();">
                        <i class="bi bi-arrow-clockwise"></i> รีเฟรชข้อมูล
                    </button>
                </div>
            </div>

            <!-- ข้อความกรณีมี Error ตอนเชื่อมต่อฐานข้อมูล -->
            <?php if (isset($error_msg)): ?>
                <div class="alert alert-danger d-flex align-items-center gap-3 border-0 rounded-4 p-4 shadow-sm" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-2"></i>
                    <div>
                        <h5 class="alert-heading fw-bold mb-1">เกิดข้อผิดพลาดในการดึงข้อมูล</h5>
                        <p class="mb-0"><?= htmlspecialchars($error_msg); ?></p>
                    </div>
                </div>
            <?php else: ?>

                <!-- Row 1: Stat Cards สรุปข้อมูลระบบ -->
                <div class="row g-4 mb-4">
                    <!-- 1. การแจ้งซ่อมทั้งหมด -->
                    <div class="col-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-receipt-cutoff"></i>
                            </div>
                            <div class="stat-val"><?= number_format($total_jobs); ?></div>
                            <div class="stat-label">ใบงานทั้งหมดในระบบ</div>
                        </div>
                    </div>
                    <!-- 2. งานรอดำเนินการ -->
                    <div class="col-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                            <div class="stat-val text-danger"><?= number_format($pending_jobs); ?></div>
                            <div class="stat-label">รอดำเนินการ (ยังไม่รับเรื่อง)</div>
                        </div>
                    </div>
                    <!-- 3. กำลังดำเนินการ -->
                    <div class="col-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-gear-fill animate-spin-custom"></i>
                            </div>
                            <div class="stat-val text-warning-emphasis"><?= number_format($processing_jobs); ?></div>
                            <div class="stat-label">กำลังดำเนินการแก้ไข</div>
                        </div>
                    </div>
                    <!-- 4. เสร็จสิ้นสมบูรณ์ -->
                    <div class="col-6 col-lg-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                            <div class="stat-val text-success"><?= number_format($completed_jobs); ?></div>
                            <div class="stat-label">ดำเนินการแก้ไขเสร็จสิ้น</div>
                        </div>
                    </div>
                </div>

                <!-- Row 2: ส่วนจัดการ ค้นหา และตารางหลัก -->
                <div class="content-card">
                    
                    <!-- ส่วนหัวตารางและปุ่มค้นหาอัจฉริยะ -->
                    <div class="row align-items-center mb-4">
                        <div class="col-12 col-md-6">
                            <h4 class="fw-bold mb-1"><i class="bi bi-list-task text-primary me-2"></i>รายการใบงานแจ้งซ่อมทั้งหมด</h4>
                            <p class="text-muted small mb-md-0">มีข้อมูลงานซ่อมทั้งหมดในระบบจำลองแสดงอยู่ด้านล่างนี้</p>
                        </div>
                        <div class="col-12 col-md-6">
                            <!-- ฟอร์มค้นหาด่วนแบบเรียลไทม์เพื่อสร้าง Dynamic Experience -->
                            <div class="search-input-icon">
                                <i class="bi bi-search"></i>
                                <input type="text" id="searchInput" class="form-control rounded-pill py-2 border-slate" placeholder="ค้นหา Ticket ID, ชื่อนศ., ตึก หรือห้อง...">
                            </div>
                        </div>
                    </div>

                    <!-- ส่วนตัวกรองแบบดรอปดาวน์เพื่อการกรองอย่างเป็นระบบ -->
                    <div class="filter-section">
                        <div class="row g-3 align-items-center">
                            <div class="col-auto">
                                <span class="fw-semibold text-secondary small"><i class="bi bi-funnel-fill me-1"></i> ตัวกรองสถานะ:</span>
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 btn-filter" onclick="filterStatus('all')">ทั้งหมด</button>
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1 btn-filter" onclick="filterStatus('รอดำเนินการ')">รอดำเนินการ (<?= $pending_jobs; ?>)</button>
                                <button type="button" class="btn btn-sm btn-outline-warning text-dark rounded-pill px-3 py-1 btn-filter" onclick="filterStatus('กำลังดำเนินการ')">กำลังดำเนินการ (<?= $processing_jobs; ?>)</button>
                                <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 btn-filter" onclick="filterStatus('เสร็จสิ้น')">เสร็จสิ้น (<?= $completed_jobs; ?>)</button>
                                <?php if ($cancelled_jobs > 0): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 btn-filter" onclick="filterStatus('ยกเลิก')">ยกเลิก (<?= $cancelled_jobs; ?>)</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 3. ตารางข้อมูลหลักแบบ Responsive (table-responsive) -->
                    <div class="table-responsive table-custom-wrapper">
                        <table class="table table-hover table-custom" id="repairTable">
                            <thead>
                                <tr>
                                    <th style="width: 12%;">Ticket ID</th>
                                    <th style="width: 18%;">วันที่แจ้ง</th>
                                    <th style="width: 25%;">หอพัก-ห้อง</th>
                                    <th style="width: 20%;">ชื่อผู้แจ้ง</th>
                                    <th style="width: 13%;">สถานะ</th>
                                    <th style="width: 12%; text-align: center;">จัดการ (Action)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($requests)): ?>
                                    <tr id="noDataRow">
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                                            ยังไม่มีรายการแจ้งเรื่องซ่อมแซมใดๆ เข้ามาในระบบหอพัก
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($requests as $row): ?>
                                        <!-- เพิ่มคลาส status สำหรับใช้คัดกรองใน Javascript -->
                                        <tr class="repair-row" data-status="<?= htmlspecialchars($row['status']); ?>">
                                            <td>
                                                <!-- Ticket ID มีปุ่มลิงก์จำลอง -->
                                                <a href="view_ticket.php?id=<?= $row['id']; ?>" class="ticket-id-link">
                                                    <i class="bi bi-hash"></i><?= htmlspecialchars($row['ticket_id']); ?>
                                                </a>
                                            </td>
                                            <td class="text-secondary">
                                                <?= formatThaiDate($row['created_at']); ?>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($row['dorm_name']); ?></div>
                                                <div class="text-muted small">ห้องพักหมายเลข: <strong><?= htmlspecialchars($row['room_number']); ?></strong></div>
                                            </td>
                                            <td>
                                                <div class="fw-medium text-dark"><?= htmlspecialchars($row['reporter_name']); ?></div>
                                            </td>
                                            <td>
                                                <!-- ใช้ Badge แสดงสีตามฟิลด์สถานะตาม Requirement -->
                                                <?= getStatusBadge($row['status']); ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <!-- ปุ่มจัดการ ดึงไปหน้า view_ticket.php?id=... -->
                                                <a href="view_ticket.php?id=<?= $row['id']; ?>" class="btn btn-action-view" title="ดูรายละเอียดใบงานนี้">
                                                    <i class="bi bi-search"></i>
                                                    ดูรายละเอียด
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <!-- แถวแสดงผลเมื่อค้นหาแล้วไม่พบข้อมูล -->
                                <tr id="noResultsRow" style="display: none;">
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-search fs-2 d-block mb-3 opacity-50"></i>
                                        ไม่พบข้อมูลที่ตรงกับคำค้นหาของคุณ
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Footer ส่วนลิขสิทธิ์ระบบหลังบ้าน -->
    <footer class="footer-custom">
        <div class="container text-center">
            <div class="row align-items-center">
                <div class="col-md-6 text-md-start mb-3 mb-md-0">
                    <p class="mb-0">&copy; 2026 ระบบจัดแจ้งซ่อมหอพักนักศึกษา มหาวิทยาลัยราชภัฏ. สงวนลิขสิทธิ์ทั้งหมด.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="badge bg-secondary py-2 px-3 small rounded-3">
                        <i class="bi bi-cpu me-1"></i> Senior Full Stack PHP Developer Edition
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle (ผ่าน CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- สคริปต์ Javascript สำหรับตัวกรองและระบบค้นหาแบบ Real-time เพื่อยกระดับ UX -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('searchInput');
            const tableRows = document.querySelectorAll('.repair-row');
            const noResultsRow = document.getElementById('noResultsRow');
            let currentStatusFilter = 'all';

            // 1. ระบบดักฟังการพิมพ์ค้นหา (Search)
            if (searchInput) {
                searchInput.addEventListener('keyup', function () {
                    filterAndSearch();
                });
            }

            // 2. ฟังก์ชันกรองและค้นหาร่วมกัน
            window.filterStatus = function (status) {
                currentStatusFilter = status;
                
                // อัปเดตสีปุ่ม Filter เพื่อให้เห็นสถานะการเลือกชัดเจน
                const filterButtons = document.querySelectorAll('.btn-filter');
                filterButtons.forEach(btn => {
                    // ล้างคลาส active เสมอ
                    btn.classList.remove('active');
                    if (status === 'all' && btn.innerText.includes('ทั้งหมด')) {
                        btn.classList.add('active');
                    } else if (btn.innerText.includes(status)) {
                        btn.classList.add('active');
                    }
                });

                filterAndSearch();
            };

            function filterAndSearch() {
                const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
                let visibleRowsCount = 0;

                tableRows.forEach(row => {
                    const status = row.getAttribute('data-status');
                    const text = row.textContent.toLowerCase();
                    
                    const matchesStatus = (currentStatusFilter === 'all' || status === currentStatusFilter);
                    const matchesSearch = (text.includes(query));

                    if (matchesStatus && matchesSearch) {
                        row.style.display = '';
                        visibleRowsCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // แสดง/ซ่อนแถวแจ้งว่าไม่พบข้อมูล
                if (noResultsRow) {
                    if (visibleRowsCount === 0 && tableRows.length > 0) {
                        noResultsRow.style.display = '';
                    } else {
                        noResultsRow.style.display = 'none';
                    }
                }
            }

            // ตั้งค่าปุ่มกรอง 'ทั้งหมด' เป็นค่าเริ่มต้น (Active)
            filterStatus('all');
        });
    </script>
</body>
</html>
