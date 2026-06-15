<?php
/**
 * หน้าจอแจ้งซ่อมหอพักแบบ Mobile-First (LINE LIFF Optimized)
 * พัฒนาโดย Senior Frontend & PHP Developer สำหรับระบบแจ้งซ่อมหอพัก (dorm_repair)
 */

require_once 'connect.php';

// =========================================================================
// CONFIGURATION: กำหนด LINE LIFF ID ของท่านที่นี่
// =========================================================================
define('LIFF_ID', '2010214920-djwel6M7'); // ใส่ LIFF ID ของท่านที่ได้จาก LINE Developers Console

try {
    // ดึงข้อมูลหอพักทั้งหมดเพื่อใช้ใน Dropdown
    // ตาราง dorms: id, name, dorm_type
    $stmt = $pdo->query("SELECT id, name, dorm_type FROM dorms ORDER BY name ASC");
    $dorms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ดึงข้อมูลรายการอุปกรณ์แจ้งซ่อม (Master Data) ทั้งหมดที่พร้อมใช้งาน (is_active = 1)
    $stmtItems = $pdo->query("SELECT id, category, item_name, require_quantity FROM repair_items_master WHERE is_active = 1 ORDER BY category, CASE WHEN item_name LIKE 'อื่น%' THEN 1 ELSE 0 END ASC, item_name ASC");
    $masterItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
    
    // จัดกลุ่มรายการอุปกรณ์แยกตาม 3 ประเภทอุปกรณ์ (ประปา, ไฟฟ้า, ซ่อมสร้าง)
    $itemsByCategory = [
        'ประปา' => [],
        'ไฟฟ้า' => [],
        'ซ่อมสร้าง' => []
    ];
    foreach ($masterItems as $item) {
        $cat = $item['category'];
        if (array_key_exists($cat, $itemsByCategory)) {
            $itemsByCategory[$cat][] = $item;
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching database schema in repair_form: " . $e->getMessage());
    $dorms = [];
    $itemsByCategory = ['ประปา' => [], 'ไฟฟ้า' => [], 'ซ่อมสร้าง' => []];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>แจ้งซ่อมหอพัก | Dormitory Repair System</title>
    <!-- SEO Optimization -->
    <meta name="description" content="ระบบแจ้งซ่อมหอพักออนไลน์ อำนวยความสะดวกในการแจ้งซ่อมแซมวัสดุอุปกรณ์ชำรุดภายในห้องพักผ่าน LINE LIFF">
    <!-- Bootstrap 5 CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- SweetAlert2 via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Google Font (Kanit) - ทันสมัยและเหมาะสำหรับภาษาไทย -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #06C755; /* LINE Green */
            --primary-dark: #05b04b;
            --bg-gradient: linear-gradient(135deg, #f4fbf7 0%, #e8f7ee 100%);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            --input-focus: rgba(6, 199, 85, 0.25);
        }

        body {
            font-family: 'Kanit', sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            color: #333333;
            padding-bottom: 40px;
            display: flex;
            flex-direction: column;
        }
        body.swal2-shown select {
    visibility: hidden !important;
}

        .liff-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 30px 20px 45px 20px;
            border-bottom-left-radius: 25px;
            border-bottom-right-radius: 25px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(6, 199, 85, 0.15);
            position: relative;
        }

        .liff-header img.logo {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: white;
            padding: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 12px;
        }

        .liff-header h1 {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .liff-header p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 0;
        }

        .liff-card-wrapper {
            margin: -25px auto 30px auto;
            padding: 0 16px;
            max-width: 500px;
            width: 100%;
        }

        .liff-card {
            background: white;
            border: none;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            padding: 28px 24px;
        }

        /* LINE Profile Info Section */
        .line-profile-box {
            background: #f8fafc;
            border: 1px solid rgba(6, 199, 85, 0.15);
            border-radius: 18px;
            padding: 14px 18px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.3s ease;
        }

        .line-profile-box img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid var(--primary-color);
            object-fit: cover;
            box-shadow: 0 3px 8px rgba(6, 199, 85, 0.15);
        }

        .line-profile-box .profile-details {
            flex-grow: 1;
        }

        .line-profile-box .profile-name {
            font-weight: 600;
            font-size: 0.95rem;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .line-profile-box .profile-status {
            font-size: 0.75rem;
            color: #16a34a;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 500;
            background: #f0fdf4;
            padding: 2px 10px;
            border-radius: 20px;
        }

        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-label i {
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .form-control, .form-select {
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            background-color: #f8fafc;
        }

        /* สไตล์แบบธรรมดา (Pure Native Select) เพื่อหลีกเลี่ยงบั๊กทุกชนิดของ iOS Safari / LINE LIFF WebView */
        select {
            display: block !important;
            width: 100% !important;
            height: 50px !important;
            padding: 12px 16px !important;
            font-size: 16px !important; /* ป้องกัน iOS ซูมหน้าจอ */
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 12px !important;
            background-color: #ffffff !important;
            color: #1e293b !important;
            outline: none !important;
            pointer-events: auto !important;
            opacity: 1 !important;
        }
        select:focus {
            border-color: var(--primary-color) !important;
        }

        .form-control:focus, .form-select:focus {
            background-color: #ffffff;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px var(--input-focus);
            outline: none;
        }

        .form-control[readonly] {
            background-color: #e2e8f0;
            color: #64748b;
            border-color: #cbd5e1;
            cursor: not-allowed;
        }

        /* สำหรับ Input File ตกแต่งพิเศษ */
        .image-upload-wrapper {
            position: relative;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            background-color: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .image-upload-wrapper:hover {
            border-color: var(--primary-color);
            background-color: #f0fdf4;
        }

        .image-upload-wrapper input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .image-upload-wrapper i {
            font-size: 2rem;
            color: #94a3b8;
            margin-bottom: 8px;
            display: block;
        }

        .image-upload-wrapper span {
            font-size: 0.9rem;
            color: #64748b;
        }

        .image-preview-box {
            position: relative;
            margin-top: 15px;
            border-radius: 12px;
            overflow: hidden;
            display: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .image-preview-box img {
            width: 100%;
            height: auto;
            max-height: 250px;
            object-fit: cover;
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 12px;
            padding: 15px;
            font-weight: 600;
            font-size: 1.1rem;
            color: white;
            box-shadow: 0 4px 15px rgba(6, 199, 85, 0.3);
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-submit:active {
            transform: scale(0.98);
            box-shadow: 0 2px 10px rgba(6, 199, 85, 0.2);
        }

        /* LIFF Loading and Simulation banners */
        .liff-status-banner {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 20px;
            padding: 8px;
            background: #f1f5f9;
            border-radius: 12px;
        }

        .simulation-badge {
            background-color: #f59e0b;
            color: white;
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 8px;
            font-weight: 500;
        }

        .footer-text {
            text-align: center;
            font-size: 0.8rem;
            color: #94a3b8;
            margin-top: auto;
            padding: 20px 0;
        }
    </style>
</head>
<body>

    <!-- Header Section (LINE LIFF style) -->
    <div class="liff-header">
        <!-- ปุ่มประวัติการแจ้งซ่อมย้ายไปหน้าติดตามสถานะ -->
        <a href="repair_status.php" class="position-absolute end-0 top-0 m-3 text-white fs-4" title="ประวัติและสถานะการแจ้งซ่อม" style="text-decoration: none; transition: transform 0.2s;" onmousedown="this.style.transform='scale(0.9)'" onmouseup="this.style.transform='scale(1)'">
            <i class="bi bi-clock-history"></i>
        </a>
        <i class="bi bi-tools logo-container d-inline-block text-white mb-2" style="font-size: 2.5rem;"></i>
        <h1>แจ้งซ่อมหอพักออนไลน์</h1>
    </div>

    <!-- Main Card Container -->
    <div class="liff-card-wrapper">
        <div class="liff-card">

            <!-- LIFF SDK Status and Loader Banner -->
            <div id="liffBanner" class="liff-status-banner">
                <div class="spinner-border spinner-border-sm text-success" role="status" id="liffSpinner"></div>
                <span id="liffStatusText">กำลังดึงโปรไฟล์ LINE และยืนยันตัวตน...</span>
            </div>

            <!-- LINE Profile Showcase (Hidden initially) -->
            <div id="lineProfileBox" class="line-profile-box d-none">
                <img id="lineUserAvatar" src="" alt="LINE Avatar">
                <div class="profile-details">
                    <div class="profile-name" id="lineUserName">ผู้ใช้ LINE</div>
                    <div class="profile-status">
                        <i class="bi bi-shield-fill-check"></i> ดึงข้อมูลหอพักของคุณเรียบร้อย
                    </div>
                </div>
            </div>

            <!-- Fallback Error UI when opened outside LINE App -->
            <div id="liffErrorBox" class="text-center py-4 d-none">
                <i class="bi bi-chat-quote-fill text-success mb-3" style="font-size: 3.2rem; display: block; color: var(--primary-color) !important;"></i>
                <h4 class="fw-bold mb-2" style="color: #1e293b;">เข้าใช้งานผ่าน LINE เท่านั้น ⚠️</h4>
                <p class="text-muted mb-4 px-2" style="font-size: 0.95rem; line-height: 1.6;">ระบบแจ้งซ่อมหอพักนี้ออกแบบมาเพื่อใช้งานผ่านแอปพลิเคชัน LINE LIFF บนโทรศัพท์มือถือเป็นหลัก กรุณาเข้าลิงก์ผ่านห้องแชท LINE เพื่อลงทะเบียนและแจ้งซ่อม</p>
                <a href="https://line.me" class="btn btn-submit rounded-pill px-4 py-2 d-inline-flex align-items-center justify-content-center gap-2" style="width: auto; background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);">
                    <i class="bi bi-line fs-5"></i> เปิดแอปพลิเคชัน LINE
                </a>
            </div>

            <form action="save_repair.php" method="POST" enctype="multipart/form-data" id="repairForm" class="needs-validation" novalidate>
                
                <!-- รหัสนักศึกษา -->
                <div class="mb-4">
                    <label for="student_id" class="form-label">
                        <i class="bi bi-card-text"></i> รหัสนักศึกษา
                    </label>
                    <input type="text" class="form-control" id="student_id" name="student_id" 
                           placeholder="ระบบจะดึงข้อมูลอัตโนมัติ" required 
                           inputmode="numeric" pattern="[0-9]{10,13}">
                    <div class="invalid-feedback">กรุณากรอกรหัสนักศึกษาเป็นตัวเลข 10-13 หลัก</div>
                </div>

                <!-- ชื่อ-นามสกุล -->
                <div class="mb-4">
                    <label for="fullname" class="form-label">
                        <i class="bi bi-person"></i> ชื่อ - นามสกุล
                    </label>
                    <input type="text" class="form-control" id="fullname" name="fullname" 
                           placeholder="ระบบจะดึงข้อมูลอัตโนมัติ" required>
                    <div class="invalid-feedback">กรุณากรอกชื่อและนามสกุลของคุณ</div>
                </div>

                <div class="row">
                    <!-- หอพัก -->
                    <div class="col-6 mb-4">
                        <label for="dorm_id" class="form-label">
                            <i class="bi bi-building"></i> หอพัก
                        </label>
                        <select id="dorm_id" name="dorm_id" required>
                            <?php if (empty($dorms)): ?>
                                <option value="" selected disabled>⚠️ ไม่พบข้อมูลหอพักในระบบฐานข้อมูล</option>
                            <?php else: ?>
                                <option value="" selected disabled>เลือกหอพัก</option>
                                <?php foreach ($dorms as $dorm): ?>
                                    <option value="<?= htmlspecialchars($dorm['id']) ?>">
                                        <?= htmlspecialchars($dorm['name']) ?> 
                                        (<?= htmlspecialchars($dorm['dorm_type']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <input type="text" class="form-control d-none" id="dorm_name_display" readonly placeholder="ระบบจะดึงข้อมูลอัตโนมัติ">
                        <div class="invalid-feedback">เลือกหอพัก</div>
                    </div>

                    <!-- หมายเลขห้อง -->
                    <div class="col-6 mb-4">
                        <label for="room_id" class="form-label">
                            <i class="bi bi-door-closed"></i> ห้องพัก
                        </label>
                        <select id="room_id" name="room_id" required>
                            <option value="" selected disabled>กรุณาเลือกหอพักก่อน</option>
                        </select>
                        <input type="text" class="form-control d-none" id="room_name_display" readonly placeholder="ระบบจะดึงข้อมูลอัตโนมัติ">
                        <div class="invalid-feedback">เลือกห้อง</div>
                    </div>
                </div>

                <!-- เลือกรายการอุปกรณ์ที่ชำรุดแยกตามประเภทอุปกรณ์ -->
                <div class="mb-4">
                    <label class="form-label">
                        <i class="bi bi-card-checklist"></i> เลือกอุปกรณ์ที่ชำรุด (เลือกได้มากกว่า 1 รายการ)
                    </label>
                    
                    <div class="accordion" id="repairItemsAccordion">
                        
                        <!-- 1. อุปกรณ์ประปา -->
                        <div class="accordion-item border rounded-3 mb-2 overflow-hidden">
                            <h2 class="accordion-header" id="headingPlumbing">
                                <button class="accordion-button collapsed bg-light text-dark fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePlumbing" aria-expanded="true" aria-controls="collapsePlumbing">
                                    <span class="fs-5 me-2">💧</span> อุปกรณ์ประปา
                                </button>
                            </h2>
                            <div id="collapsePlumbing" class="accordion-collapse collapse" aria-labelledby="headingPlumbing" data-bs-parent="#repairItemsAccordion">
                                <div class="accordion-body bg-white">
                                    <div class="row row-cols-1 row-cols-sm-2 g-2">
                                        <?php foreach ($itemsByCategory['ประปา'] as $item): ?>
                                            <div class="col">
                                                <div class="p-3 border rounded-3 bg-light h-100" style="transition: background 0.2s;">
                                                    <div class="d-flex align-items-center" style="cursor:pointer;">
                                                        <input class="form-check-input repair-item-checkbox mt-0 me-3"
                                                               type="checkbox"
                                                               name="repair_items[]"
                                                               value="<?= $item['id'] ?>"
                                                               id="item_<?= $item['id'] ?>"
                                                               style="width:1.35rem;height:1.35rem;flex-shrink:0;cursor:pointer;"
                                                               <?php if ($item['require_quantity']): ?>onchange="toggleQtyInput(this,<?= $item['id'] ?>)"<?php endif; ?>>
                                                        <label class="form-check-label w-100 text-start" for="item_<?= $item['id'] ?>" style="cursor:pointer;font-weight:500;font-size:0.95rem;user-select:none;line-height:1.4;">
                                                            <?= htmlspecialchars($item['item_name']) ?>
                                                            <?php if ($item['require_quantity']): ?>
                                                            <span style="font-size:0.7rem;background:#fef3c7;color:#d97706;padding:1px 6px;border-radius:4px;font-weight:600;margin-left:4px;vertical-align:middle;">ระบุจำนวน</span>
                                                            <?php endif; ?>
                                                        </label>
                                                    </div>
                                                    <?php if ($item['require_quantity']): ?>
                                                    <div id="qty_box_<?= $item['id'] ?>" style="display:none;margin-top:10px;padding-top:10px;border-top:1px dashed #e2e8f0;">
                                                        <div style="font-size:0.78rem;color:#64748b;margin-bottom:6px;">ระบุจำนวน:</div>
                                                        <div style="display:flex;align-items:center;gap:8px;">
                                                            <button type="button" onclick="changeQty(<?= $item['id'] ?>,-1)"
                                                                    style="width:32px;height:32px;border-radius:8px;border:1.5px solid #e2e8f0;background:white;font-size:1.1rem;font-weight:700;color:#475569;cursor:pointer;flex-shrink:0;display:flex;align-items:center;justify-content:center;">−</button>
                                                            <input type="number"
                                                                   name="repair_quantities[<?= $item['id'] ?>]"
                                                                   id="qty_val_<?= $item['id'] ?>"
                                                                   value="1" min="1" max="99"
                                                                   style="width:56px;height:32px;text-align:center;border:1.5px solid #06C755;border-radius:8px;font-size:1rem;font-weight:600;font-family:'Kanit',sans-serif;"
                                                                   oninput="this.value=Math.max(1,parseInt(this.value)||1)">
                                                            <button type="button" onclick="changeQty(<?= $item['id'] ?>,+1)"
                                                                    style="width:32px;height:32px;border-radius:8px;border:1.5px solid #e2e8f0;background:white;font-size:1.1rem;font-weight:700;color:#475569;cursor:pointer;flex-shrink:0;display:flex;align-items:center;justify-content:center;">+</button>
                                                            <span style="font-size:0.82rem;color:#94a3b8;">ชิ้น</span>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. อุปกรณ์ไฟฟ้า -->
                        <div class="accordion-item border rounded-3 mb-2 overflow-hidden">
                            <h2 class="accordion-header" id="headingElectric">
                                <button class="accordion-button collapsed bg-light text-dark fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseElectric" aria-expanded="false" aria-controls="collapseElectric">
                                    <span class="fs-5 me-2">⚡</span> อุปกรณ์ไฟฟ้า
                                </button>
                            </h2>
                            <div id="collapseElectric" class="accordion-collapse collapse" aria-labelledby="headingElectric" data-bs-parent="#repairItemsAccordion">
                                <div class="accordion-body bg-white">
                                    <div class="row row-cols-1 row-cols-sm-2 g-2">
                                        <?php foreach ($itemsByCategory['ไฟฟ้า'] as $item): ?>
                                            <div class="col">
                                                <div class="p-3 border rounded-3 bg-light h-100" style="transition: background 0.2s;">
                                                    <div class="d-flex align-items-center" style="cursor:pointer;">
                                                        <input class="form-check-input repair-item-checkbox mt-0 me-3"
                                                               type="checkbox"
                                                               name="repair_items[]"
                                                               value="<?= $item['id'] ?>"
                                                               id="item_<?= $item['id'] ?>"
                                                               style="width:1.35rem;height:1.35rem;flex-shrink:0;cursor:pointer;"
                                                               <?php if ($item['require_quantity']): ?>onchange="toggleQtyInput(this,<?= $item['id'] ?>)"<?php endif; ?>>
                                                        <label class="form-check-label w-100 text-start" for="item_<?= $item['id'] ?>" style="cursor:pointer;font-weight:500;font-size:0.95rem;user-select:none;line-height:1.4;">
                                                            <?= htmlspecialchars($item['item_name']) ?>
                                                            <?php if ($item['require_quantity']): ?>
                                                            <span style="font-size:0.7rem;background:#fef3c7;color:#d97706;padding:1px 6px;border-radius:4px;font-weight:600;margin-left:4px;vertical-align:middle;">ระบุจำนวน</span>
                                                            <?php endif; ?>
                                                        </label>
                                                    </div>
                                                    <?php if ($item['require_quantity']): ?>
                                                    <div id="qty_box_<?= $item['id'] ?>" style="display:none;margin-top:10px;padding-top:10px;border-top:1px dashed #e2e8f0;">
                                                        <div style="font-size:0.78rem;color:#64748b;margin-bottom:6px;">ระบุจำนวน:</div>
                                                        <div style="display:flex;align-items:center;gap:8px;">
                                                            <button type="button" onclick="changeQty(<?= $item['id'] ?>,-1)"
                                                                    style="width:32px;height:32px;border-radius:8px;border:1.5px solid #e2e8f0;background:white;font-size:1.1rem;font-weight:700;color:#475569;cursor:pointer;flex-shrink:0;display:flex;align-items:center;justify-content:center;">−</button>
                                                            <input type="number"
                                                                   name="repair_quantities[<?= $item['id'] ?>]"
                                                                   id="qty_val_<?= $item['id'] ?>"
                                                                   value="1" min="1" max="99"
                                                                   style="width:56px;height:32px;text-align:center;border:1.5px solid #06C755;border-radius:8px;font-size:1rem;font-weight:600;font-family:'Kanit',sans-serif;"
                                                                   oninput="this.value=Math.max(1,parseInt(this.value)||1)">
                                                            <button type="button" onclick="changeQty(<?= $item['id'] ?>,+1)"
                                                                    style="width:32px;height:32px;border-radius:8px;border:1.5px solid #e2e8f0;background:white;font-size:1.1rem;font-weight:700;color:#475569;cursor:pointer;flex-shrink:0;display:flex;align-items:center;justify-content:center;">+</button>
                                                            <span style="font-size:0.82rem;color:#94a3b8;">ชิ้น</span>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. อุปกรณ์ซ่อมสร้าง & เฟอร์นิเจอร์ -->
                        <div class="accordion-item border rounded-3 mb-2 overflow-hidden">
                            <h2 class="accordion-header" id="headingBuild">
                                <button class="accordion-button collapsed bg-light text-dark fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBuild" aria-expanded="false" aria-controls="collapseBuild">
                                    <span class="fs-5 me-2">🔨</span> อุปกรณ์ซ่อมสร้าง & เฟอร์นิเจอร์
                                </button>
                            </h2>
                            <div id="collapseBuild" class="accordion-collapse collapse" aria-labelledby="headingBuild" data-bs-parent="#repairItemsAccordion">
                                <div class="accordion-body bg-white">
                                    <div class="row row-cols-1 row-cols-sm-2 g-2">
                                        <?php foreach ($itemsByCategory['ซ่อมสร้าง'] as $item): ?>
                                            <div class="col">
                                                <div class="p-3 border rounded-3 bg-light h-100" style="transition: background 0.2s;">
                                                    <div class="d-flex align-items-center" style="cursor:pointer;">
                                                        <input class="form-check-input repair-item-checkbox mt-0 me-3"
                                                               type="checkbox"
                                                               name="repair_items[]"
                                                               value="<?= $item['id'] ?>"
                                                               id="item_<?= $item['id'] ?>"
                                                               style="width:1.35rem;height:1.35rem;flex-shrink:0;cursor:pointer;"
                                                               <?php if ($item['require_quantity']): ?>onchange="toggleQtyInput(this,<?= $item['id'] ?>)"<?php endif; ?>>
                                                        <label class="form-check-label w-100 text-start" for="item_<?= $item['id'] ?>" style="cursor:pointer;font-weight:500;font-size:0.95rem;user-select:none;line-height:1.4;">
                                                            <?= htmlspecialchars($item['item_name']) ?>
                                                            <?php if ($item['require_quantity']): ?>
                                                            <span style="font-size:0.7rem;background:#fef3c7;color:#d97706;padding:1px 6px;border-radius:4px;font-weight:600;margin-left:4px;vertical-align:middle;">ระบุจำนวน</span>
                                                            <?php endif; ?>
                                                        </label>
                                                    </div>
                                                    <?php if ($item['require_quantity']): ?>
                                                    <div id="qty_box_<?= $item['id'] ?>" style="display:none;margin-top:10px;padding-top:10px;border-top:1px dashed #e2e8f0;">
                                                        <div style="font-size:0.78rem;color:#64748b;margin-bottom:6px;">ระบุจำนวน:</div>
                                                        <div style="display:flex;align-items:center;gap:8px;">
                                                            <button type="button" onclick="changeQty(<?= $item['id'] ?>,-1)"
                                                                    style="width:32px;height:32px;border-radius:8px;border:1.5px solid #e2e8f0;background:white;font-size:1.1rem;font-weight:700;color:#475569;cursor:pointer;flex-shrink:0;display:flex;align-items:center;justify-content:center;">−</button>
                                                            <input type="number"
                                                                   name="repair_quantities[<?= $item['id'] ?>]"
                                                                   id="qty_val_<?= $item['id'] ?>"
                                                                   value="1" min="1" max="99"
                                                                   style="width:56px;height:32px;text-align:center;border:1.5px solid #06C755;border-radius:8px;font-size:1rem;font-weight:600;font-family:'Kanit',sans-serif;"
                                                                   oninput="this.value=Math.max(1,parseInt(this.value)||1)">
                                                            <button type="button" onclick="changeQty(<?= $item['id'] ?>,+1)"
                                                                    style="width:32px;height:32px;border-radius:8px;border:1.5px solid #e2e8f0;background:white;font-size:1.1rem;font-weight:700;color:#475569;cursor:pointer;flex-shrink:0;display:flex;align-items:center;justify-content:center;">+</button>
                                                            <span style="font-size:0.82rem;color:#94a3b8;">ชิ้น</span>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="text-danger mt-2" id="checkbox-error-message" style="display: none; font-size: 0.85rem;">
                        ⚠️ กรุณาเลือกรายการอุปกรณ์ที่ต้องการแจ้งซ่อมอย่างน้อย 1 รายการ
                    </div>
                </div>

                <!-- รายละเอียดปัญหา -->
                <div class="mb-4">
                    <label for="description" class="form-label">
                        <i class="bi bi-chat-left-dots"></i> รายละเอียดปัญหา
                    </label>
                    <textarea class="form-control" id="description" name="description" rows="3" 
                              placeholder="อธิบายปัญหา เช่น หลอดไฟห้องน้ำดับ หรือ น้ำซึมใต้ก๊อกอ่างล้างหน้า" required></textarea>
                    <div class="invalid-feedback">กรุณากรอกรายละเอียดของปัญหาที่พบ</div>
                </div>

                <!-- อัปโหลดรูปภาพประกอบ -->
                <div class="mb-4">
                    <label class="form-label">
                        <i class="bi bi-camera"></i> รูปภาพประกอบ (อัปโหลดได้หลายรูป, รองรับ .jpg, .png)
                    </label>
                    <div class="image-upload-wrapper">
                        <i class="bi bi-images"></i>
                        <span id="upload-status-text">แตะเพื่อเลือกรูปภาพ (เลือกได้พร้อมกันหลายรูป)</span>
                        <input type="file" id="repair_image" name="repair_images[]" accept="image/png, image/jpeg, image/jpg" required multiple>
                    </div>
                    <!-- ตารางแสดงพรีวิวรูปแบบตารางกริดพรีเมียม -->
                    <div class="row row-cols-3 row-cols-sm-4 g-2 mt-3" id="imagePreviewGrid" style="display: none;">
                        <!-- JS จะนำรูปพรีวิวมาใส่ที่นี่ -->
                    </div>
                    <div class="invalid-feedback" id="image-error-feedback" style="display: none; font-size: 0.85em; color: #dc3545; margin-top: 5px;">
                        กรุณาเลือกอัปโหลดรูปภาพประกอบอย่างน้อย 1 รูป
                    </div>
                </div>

                <!-- ปุ่มส่งข้อมูล -->
                <div class="mt-4">
                    <button type="submit" class="btn btn-submit btn-lg d-flex justify-content-center align-items-center gap-2">
                        <i class="bi bi-send-fill"></i> ส่งเรื่องแจ้งซ่อม
                    </button>
                </div>
                
            </form>
        </div>
    </div>

    <!-- ลิขสิทธิ์และรุ่นของระบบ -->
    <div class="footer-text">
        &copy; 2026 Dormitory Repair System. All Rights Reserved.
    </div>

    <!-- Bootstrap 5 JS Bundle via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- LINE LIFF SDK via CDN -->
    <script charset="utf-8" src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>

    <script>
        // =========================================================================
        // 0. ระบบดักจับและแสดงข้อผิดพลาดบนหน้าจอ (Mobile Diagnostic Error Catcher)
        // =========================================================================
        window.onerror = function(message, source, lineno, colno, error) {
            const errText = `Browser Error: ${message} (Line: ${lineno})`;
            console.error(errText, error);
            const statusTextEl = document.getElementById('liffStatusText');
            if (statusTextEl) {
                statusTextEl.innerHTML = `<span style="color:#dc3545; font-weight:600;">❌ เกิดข้อผิดพลาดของเบราว์เซอร์:</span><br><small style="font-size:0.75rem; font-family:monospace; color:#64748b;">${message}<br>ที่บรรทัด ${lineno}:${colno}</small>`;
                const spinner = document.getElementById('liffSpinner');
                if (spinner) spinner.classList.add('d-none');
                const banner = document.getElementById('liffBanner');
                if (banner) {
                    banner.style.backgroundColor = '#fef2f2';
                    banner.style.border = '1px solid #fecaca';
                }
            }
            return false;
        };
        // =========================================================================
        // 1. ระบบโหลดห้องพักแบบ Dynamic รองรับทั้งโหลดปกติและโหลดระบุห้องล่วงหน้า
        // =========================================================================
        function loadRooms(dormId, selectedRoomId = null) {
            const roomSelect = document.getElementById('room_id');
            roomSelect.innerHTML = '<option value="" selected disabled>⏳ กำลังโหลดห้องพัก...</option>';
            roomSelect.disabled = false;

            if (!dormId) {
                roomSelect.innerHTML = '<option value="" selected disabled>กรุณาเลือกหอพักก่อน</option>';
                return Promise.resolve();
            }

            return fetch(`get_rooms.php?dorm_id=${dormId}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response error');
                    return response.json();
                })
                .then(data => {
                    roomSelect.innerHTML = '<option value="" selected disabled>เลือกห้องพัก</option>';
                    
                    if (data.success && data.rooms.length > 0) {
                        data.rooms.forEach(room => {
                            const option = document.createElement('option');
                            option.value = room.id;
                            
                            let statusNote = '';
                            if (room.status && room.status !== 'ready' && room.status !== 'active' && room.status !== 'พร้อมใช้งาน') {
                                statusNote = ` (${room.status})`;
                            }
                            
                            option.textContent = `ห้อง ${room.room_number}${statusNote}`;
                            
                            // ถ้ามีห้องที่ถูกเลือกจากข้อมูลนักศึกษา
                            if (selectedRoomId && room.id == selectedRoomId) {
                                option.selected = true;
                            }
                            
                            roomSelect.appendChild(option);
                        });
                        roomSelect.disabled = false;
                    } else {
                        roomSelect.innerHTML = '<option value="" selected disabled>⚠️ ไม่พบห้องพักว่างในหอนี้</option>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching rooms:', error);
                    roomSelect.innerHTML = '<option value="" selected disabled>❌ เกิดข้อผิดพลาดในการโหลดข้อมูล</option>';
                });
        }

        // เชื่อมโยง Event ตอนผู้ใช้เปลี่ยนค่าหอพักในฟอร์มเอง
        document.getElementById('dorm_id').addEventListener('change', function() {
            loadRooms(this.value);
        });

        // =========================================================================
        // 2. ระบบดึงประวัตินักศึกษาจากฐานข้อมูลมาอำนวยความสะดวก (Auto Fill Profile)
        // =========================================================================
        function fetchStudentProfile(lineUid, displayName = '', pictureUrl = '') {
            fetch(`get_student.php?line_uid=${lineUid}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.student) {
                        document.getElementById('liffBanner').classList.add('d-none');

                        const s = data.student;
                        const isMonitor = s.role === 'monitor';

                        // กรอกรหัสนักศึกษาและชื่อ (ล็อคทั้งสองกรณี)
                        document.getElementById('student_id').value = s.student_id;
                        document.getElementById('fullname').value   = s.fullname;
                        document.getElementById('student_id').readOnly = true;
                        document.getElementById('fullname').readOnly   = true;

                        if (isMonitor) {
                            // === คนดูแลหอพัก: เลือกหอ+ห้องได้เสรี ===
                            // Pre-select หอของตัวเองไว้เป็น default (เปลี่ยนได้)
                            const dormSelect = document.getElementById('dorm_id');
                            dormSelect.value = s.dorm_id;
                            dormSelect.classList.remove('d-none'); // แสดง select ตามปกติ

                            document.getElementById('dorm_name_display').classList.add('d-none');

                            // โหลดห้องพักของหอนั้น pre-select ห้องตัวเองไว้ก่อน แต่เปลี่ยนได้
                            loadRooms(s.dorm_id, s.room_id);

                            // ฟัง event เปลี่ยนหอพักปกติ (ผูกไว้แล้วใน DOMContentLoaded)
                        } else {
                            // === นักศึกษาทั่วไป: ล็อคหอ+ห้องของตัวเอง ===
                            const dormSelect = document.getElementById('dorm_id');
                            dormSelect.value = s.dorm_id;
                            dormSelect.classList.add('d-none');

                            const dormDisplay = document.getElementById('dorm_name_display');
                            dormDisplay.value = s.dorm_name;
                            dormDisplay.classList.remove('d-none');

                            loadRooms(s.dorm_id, s.room_id).then(() => {
                                const roomSelect = document.getElementById('room_id');
                                roomSelect.classList.add('d-none');

                                const roomDisplay = document.getElementById('room_name_display');
                                roomDisplay.value = 'ห้อง ' + s.room_number;
                                roomDisplay.classList.remove('d-none');
                            });
                        }
                    } else {
                        // ไม่พบประวัติลงทะเบียนในฐานข้อมูลด้วย LINE UID นี้
                        document.getElementById('liffBanner').classList.add('d-none');
                        
                        // ฟังก์ชันพาไปยังหน้าลงทะเบียนพร้อมแนบโปรไฟล์
                        const redirectToRegister = () => {
                            const name = encodeURIComponent(displayName);
                            const img = encodeURIComponent(pictureUrl);
                            window.location.href = `register.php?line_uid=${lineUid}&line_name=${name}&line_img=${img}`;
                        };

                        // แสดงแจ้งเตือนอย่างพรีเมียมด้วย SweetAlert2
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'ยังไม่ได้ลงทะเบียนหอพัก ⚠️',
                                text: 'ขออภัยครับ ระบบไม่พบข้อมูลหอพักที่เชื่อมโยงกับบัญชี LINE ของท่าน กรุณาลงทะเบียนผู้ใช้งานก่อนเพื่อเริ่มแจ้งซ่อม',
                                icon: 'warning',
                                confirmButtonText: 'ไปหน้าลงทะเบียน ➡️',
                                confirmButtonColor: '#06C755',
                                allowOutsideClick: false,
                                allowEscapeKey: false
                            }).then((result) => {
                                redirectToRegister();
                            });
                        } else {
                            // ระบบสำรอง (Fallback) บล็อกการทำงานของบราวเซอร์เพื่อบังคับโชว์ข้อความแจ้งเตือน 100%
                            alert("ยังไม่ได้ลงทะเบียนหอพัก ⚠️\n\nขออภัยครับ ระบบไม่พบข้อมูลหอพักที่เชื่อมโยงกับบัญชี LINE ของท่าน กรุณาลงทะเบียนผู้ใช้งานก่อนเพื่อเริ่มแจ้งซ่อม");
                            redirectToRegister();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking student registration:', error);
                    updateLiffStatus('❌ เกิดข้อผิดพลาดในการตรวจสอบบัญชี');
                    Swal.fire({
                        title: 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์! ❌',
                        text: 'ไม่สามารถส่งคำขอตรวจสอบข้อมูลนักศึกษาได้สำเร็จ (ข้อมูลบั๊ก: ' + error.message + ')',
                        icon: 'error',
                        confirmButtonText: 'รับทราบ',
                        confirmButtonColor: '#dc3545'
                    });
                });
        }

        // =========================================================================
        // 3. ระบบเชื่อมต่อ LINE LIFF SDK
        // =========================================================================
        const liffId = "<?= LIFF_ID ?>";

        document.addEventListener("DOMContentLoaded", function() {
            // เช็คการติดตั้ง LIFF ID
            if (liffId === "YOUR_LIFF_ID" || !liffId.trim()) {
                console.warn("LIFF ID is missing. Running in mock development mode.");
                showMockBadge();
                return;
            }

            // เริ่มต้นระบบ LINE LIFF SDK
            liff.init({ liffId: liffId })
                .then(() => {
                    // ป้องกัน iOS เด้งหลุดด้วยการเช็คว่าถ้าอยู่ในแอป LINE (isInClient) ให้ดึงโปรไฟล์โดยตรง ไม่ต้องเช็ค isLoggedIn หรือสั่ง login ซ้ำซ้อน
                    if (liff.isInClient() || liff.isLoggedIn()) {
                        // ดึงข้อมูลโปรไฟล์ LINE
                        liff.getProfile()
                            .then(profile => {
                                // ซ่อน Banner โหลดหลักของ LINE
                                document.getElementById('liffBanner').classList.add('d-none');

                                // แสดงหน้าประวัติการเชื่อมต่อ LINE ด้านบน
                                document.getElementById('lineUserAvatar').src = profile.pictureUrl || 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
                                document.getElementById('lineUserName').textContent = profile.displayName;
                                document.getElementById('lineProfileBox').classList.remove('d-none');

                                // ดำเนินการเช็คโปรไฟล์และกรอกข้อมูลจาก LINE UID ที่ดึงมาได้
                                fetchStudentProfile(profile.userId, profile.displayName, profile.pictureUrl || '');
                            })
                            .catch(err => {
                                console.error('Error getting LINE profile:', err);
                                updateLiffStatus('❌ ดึงโปรไฟล์ LINE ไม่สำเร็จ');
                                document.getElementById('repairForm').classList.add('d-none');
                                document.getElementById('liffErrorBox').classList.remove('d-none');
                            });
                    } else {
                        // ถ้าอยู่นอกแอป LINE และยังไม่ได้ล็อกอิน ให้บังคับล็อกอิน
                        liff.login();
                    }
                })
                .catch(err => {
                    console.error('LINE LIFF Init Failed:', err);
                    // เปิดใช้งานนอก LINE: ซ่อนฟอร์มและแจ้งเตือนเพื่อให้เปิดผ่าน LINE เท่านั้น
                    document.getElementById('liffBanner').classList.add('d-none');
                    document.getElementById('repairForm').classList.add('d-none');
                    document.getElementById('liffErrorBox').classList.remove('d-none');
                });
        });

        function updateLiffStatus(msg) {
            document.getElementById('liffSpinner').classList.add('d-none');
            document.getElementById('liffStatusText').textContent = msg;
        }

        // =========================================================================
        // 4. พรีวิวรูปภาพก่อนอัปโหลดแบบหลายรูป (Multiple Images Live Preview)
        // =========================================================================
        const fileInput = document.getElementById('repair_image');
        const uploadStatusText = document.getElementById('upload-status-text');
        const previewGrid = document.getElementById('imagePreviewGrid');
        const imageErrorFeedback = document.getElementById('image-error-feedback');

        fileInput.addEventListener('change', function(e) {
            const files = e.target.files;
            previewGrid.innerHTML = ''; // ล้างรูปพรีวิวเดิม
            
            if (files && files.length > 0) {
                const validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                let invalidFile = false;
                
                // ตรวจสอบชนิดไฟล์แต่ละไฟล์
                for (let i = 0; i < files.length; i++) {
                    if (!validTypes.includes(files[i].type)) {
                        invalidFile = true;
                        break;
                    }
                }
                
                if (invalidFile) {
                    Swal.fire({
                        title: 'รูปแบบไฟล์ไม่ถูกต้อง! ⚠️',
                        text: 'รองรับเฉพาะไฟล์รูปภาพ .jpg, .jpeg และ .png เท่านั้น',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                    fileInput.value = '';
                    resetImagePreview();
                    return;
                }

                // แสดงสถานะจำนวนรูปที่เลือก
                uploadStatusText.innerHTML = `เลือกรูปภาพแล้ว: <strong class="text-success">${files.length} รูป</strong>`;
                imageErrorFeedback.style.display = 'none';
                previewGrid.style.display = 'flex';

                // วนลูปอ่านและสร้างภาพพรีวิวในตารางกริด
                for (let i = 0; i < files.length; i++) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        const col = document.createElement('div');
                        col.className = 'col position-relative';
                        col.innerHTML = `
                            <div class="ratio ratio-1x1 border rounded-3 overflow-hidden shadow-sm bg-light" style="border: 2px solid #cbd5e1 !important;">
                                <img src="${event.target.result}" class="w-100 h-100" style="object-fit: cover;">
                            </div>
                        `;
                        previewGrid.appendChild(col);
                    }
                    reader.readAsDataURL(files[i]);
                }
            } else {
                resetImagePreview();
            }
        });

        function resetImagePreview() {
            uploadStatusText.textContent = 'แตะเพื่อเลือกรูปภาพ (เลือกได้พร้อมกันหลายรูป)';
            previewGrid.style.display = 'none';
            previewGrid.innerHTML = '';
        }

        // =========================================================================
        // 5. ระบบตรวจสอบความถูกต้องของฟอร์ม (Form Validation)
        // =========================================================================
        const form = document.getElementById('repairForm');
        form.addEventListener('submit', function (event) {
            let isFileValid = true;

            // ตรวจสอบรูปภาพ
            if (!fileInput.files.length) {
                imageErrorFeedback.style.display = 'block';
                isFileValid = false;
            }

            // ตรวจสอบเช็คบ็อกซ์อุปกรณ์ชำรุดอย่างน้อย 1 รายการ
            const checkboxes = document.querySelectorAll('.repair-item-checkbox');
            let isCheckboxValid = false;
            checkboxes.forEach(cb => {
                if (cb.checked) isCheckboxValid = true;
            });

            const checkboxError = document.getElementById('checkbox-error-message');
            if (!isCheckboxValid) {
                checkboxError.style.display = 'block';
            } else {
                checkboxError.style.display = 'none';
            }

            if (!form.checkValidity() || !isFileValid || !isCheckboxValid) {
                event.preventDefault();
                event.stopPropagation();

                if (!isCheckboxValid) {
                    Swal.fire({
                        title: 'ข้อมูลไม่ครบถ้วน! ⚠️',
                        text: 'กรุณาเลือกรายการอุปกรณ์ที่ต้องการแจ้งซ่อมอย่างน้อย 1 รายการ',
                        icon: 'warning',
                        confirmButtonText: 'ตกลง',
                        confirmButtonColor: '#06C755'
                    });
                }
            }

            form.classList.add('was-validated');
        }, false);

        // =========================================================================
        // 6. ระบุจำนวนอุปกรณ์ (require_quantity items)
        // =========================================================================
        function toggleQtyInput(checkbox, itemId) {
            const box = document.getElementById('qty_box_' + itemId);
            if (!box) return;
            box.style.display = checkbox.checked ? 'block' : 'none';
            if (!checkbox.checked) {
                document.getElementById('qty_val_' + itemId).value = 1;
            }
        }

        function changeQty(itemId, delta) {
            const input = document.getElementById('qty_val_' + itemId);
            input.value = Math.max(1, (parseInt(input.value) || 1) + delta);
        }
    </script>
</body>
</html>
