<?php
/**
 * หน้าจอติดตามสถานะงานแจ้งซ่อมแบบ Mobile-First (LINE LIFF Optimized)
 * พัฒนาโดย Senior Frontend & PHP Developer สำหรับระบบแจ้งซ่อมหอพัก (dorm_repair)
 */

require_once 'connect.php';

// =========================================================================
// CONFIGURATION: กำหนด LINE LIFF ID ของท่านที่นี่
// =========================================================================
define('LIFF_ID', '2010214920-i3g2VEFa'); // ใส่ LIFF ID ของท่านที่ได้จาก LINE Developers Console
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ติดตามการแจ้งซ่อม | Dorm Repair System</title>
    <!-- SEO Optimization -->
    <meta name="description" content="ระบบติดตามสถานะงานแจ้งซ่อมหอพักออนไลน์ ตรวจสอบความคืบหน้าของใบงานและช่างผู้รับผิดชอบผ่าน LINE LIFF">
    <!-- Google Font (Kanit) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- SweetAlert2 (CSS) -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        :root {
            --line-green: #06C755;
            --line-green-hover: #05b04b;
            --line-green-light: rgba(6, 199, 85, 0.1);
            --bg-body: #f8fafc;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            
            /* สีแยกตามสถานะงานซ่อม */
            --status-pending: #f59e0b;      /* รอดำเนินการ - ส้ม */
            --status-pending-light: #fef3c7;
            --status-progress: #3b82f6;     /* กำลังดำเนินการ - ฟ้า */
            --status-progress-light: #dbeafe;
            --status-completed: #10b981;    /* เสร็จสิ้น - เขียว */
            --status-completed-light: #d1fae5;
            --status-cancelled: #ef4444;    /* ยกเลิก - แดง */
            --status-cancelled-light: #fee2e2;
        }

        body {
            font-family: 'Kanit', sans-serif;
            background-color: var(--bg-body);
            background-image: radial-gradient(circle at 90% 10%, rgba(6, 199, 85, 0.02) 0%, transparent 40%);
            min-height: 100vh;
            color: #334155;
            display: flex;
            flex-direction: column;
            padding-bottom: 30px;
        }

        /* Header Block Gradient */
        .liff-header {
            background: linear-gradient(135deg, var(--line-green) 0%, #05a044 100%);
            color: white;
            padding: 30px 20px 45px 20px;
            border-bottom-left-radius: 28px;
            border-bottom-right-radius: 28px;
            box-shadow: 0 4px 20px rgba(6, 199, 85, 0.15);
            text-align: center;
            position: relative;
        }

        .liff-header h1 {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .liff-header p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 0;
            font-weight: 300;
        }

        .btn-back-home {
            position: absolute;
            left: 15px;
            top: 25px;
            color: white;
            font-size: 1.5rem;
            text-decoration: none;
            transition: transform 0.2s ease;
        }

        .btn-back-home:active {
            transform: scale(0.9);
        }

        /* Container wrapper */
        .status-container {
            margin: -25px auto 0 auto;
            padding: 0 16px;
            max-width: 500px;
            width: 100%;
            z-index: 10;
        }

        /* LINE Profile Showcase Block */
        .line-profile-box {
            background: #ffffff;
            border: 1px solid rgba(6, 199, 85, 0.12);
            border-radius: 20px;
            padding: 12px 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--card-shadow);
        }

        .line-profile-box img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 2px solid var(--line-green);
            object-fit: cover;
        }

        .line-profile-box .profile-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: #1e293b;
            margin-bottom: 1px;
        }

        .line-profile-box .profile-badge {
            font-size: 0.72rem;
            color: var(--line-green);
            font-weight: 500;
            background: var(--line-green-light);
            padding: 1px 8px;
            border-radius: 12px;
            display: inline-block;
        }

        /* Summary Counters Row */
        .counters-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 16px;
            margin-bottom: 18px;
            box-shadow: var(--card-shadow);
            display: flex;
            justify-content: space-around;
            text-align: center;
        }

        .counter-item {
            flex: 1;
        }

        .counter-item:not(:last-child) {
            border-right: 1px solid #f1f5f9;
        }

        .counter-val {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.2;
        }

        .counter-lbl {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 2px;
        }

        /* Repair List Cards */
        .ticket-card {
            background: #ffffff;
            border-radius: 22px;
            border: 1px solid #f1f5f9;
            padding: 22px 18px;
            margin-bottom: 18px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease;
        }

        .ticket-card:active {
            transform: scale(0.99);
        }

        /* Ribbons of Status */
        .status-badge {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .status-pending { background-color: var(--status-pending-light); color: var(--status-pending); }
        .status-progress { background-color: var(--status-progress-light); color: var(--status-progress); }
        .status-completed { background-color: var(--status-completed-light); color: var(--status-completed); }
        .status-cancelled { background-color: var(--status-cancelled-light); color: var(--status-cancelled); }

        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px dashed #f1f5f9;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }

        .ticket-id {
            font-weight: 700;
            font-size: 1.05rem;
            color: #1e293b;
        }

        .ticket-date {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .info-row i {
            color: #64748b;
            font-size: 1rem;
            margin-top: 2px;
        }

        .info-lbl {
            font-weight: 500;
            color: #475569;
            flex-shrink: 0;
            width: 80px;
        }

        .info-val {
            color: #334155;
        }

        /* Items tag list */
        .item-tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
            margin-bottom: 12px;
        }

        .item-badge {
            background-color: #f1f5f9;
            color: #475569;
            font-size: 0.78rem;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 500;
            border: 1px solid #e2e8f0;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Images grid */
        .image-gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
            margin-bottom: 12px;
        }

        .gallery-img-wrapper {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            overflow: hidden;
            border: 1.5px solid #cbd5e1;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .gallery-img-wrapper:hover {
            transform: scale(1.05);
            border-color: var(--line-green);
        }

        .gallery-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Timeline indicator inside Card */
        .timeline-tracker {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
            position: relative;
        }

        .timeline-tracker::before {
            content: '';
            position: absolute;
            top: 25px;
            left: 20px;
            right: 20px;
            height: 3px;
            background-color: #e2e8f0;
            z-index: 1;
        }

        .timeline-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 2;
            width: 33.33%;
            text-align: center;
        }

        .step-dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: #cbd5e1;
            border: 3px solid #ffffff;
            box-shadow: 0 0 0 1px #cbd5e1;
            margin-bottom: 6px;
            transition: all 0.3s ease;
        }

        .step-label {
            font-size: 0.72rem;
            font-weight: 500;
            color: #94a3b8;
        }

        /* Active Timeline states */
        .step-active .step-dot {
            background-color: var(--line-green);
            box-shadow: 0 0 0 1px var(--line-green);
        }
        .step-active .step-label {
            color: var(--line-green);
            font-weight: 600;
        }

        .step-warning .step-dot {
            background-color: var(--status-pending);
            box-shadow: 0 0 0 1px var(--status-pending);
        }
        .step-warning .step-label {
            color: var(--status-pending);
            font-weight: 600;
        }

        .step-info .step-dot {
            background-color: var(--status-progress);
            box-shadow: 0 0 0 1px var(--status-progress);
        }
        .step-info .step-label {
            color: var(--status-progress);
            font-weight: 600;
        }

        /* Loader block */
        .loader-block {
            padding: 50px 0;
            text-align: center;
            color: #64748b;
        }

        /* Empty state styling */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            display: block;
            margin-bottom: 12px;
        }

        .empty-state h3 {
            font-size: 1.15rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
        }

        .empty-state p {
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 20px;
        }

        .btn-primary-action {
            background: linear-gradient(135deg, var(--line-green) 0%, var(--line-green-hover) 100%);
            border: none;
            color: white;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 10px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(6, 199, 85, 0.2);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .liff-status-banner {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 15px;
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

        .footer {
            text-align: center;
            font-size: 0.8rem;
            color: #94a3b8;
            margin-top: auto;
            padding: 20px 0;
        }
    </style>
</head>
<body>

    <!-- Header Block -->
    <div class="liff-header">
        <a href="repair_form.php" class="btn-back-home" title="กลับไปหน้าหลัก">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1>ติดตามสถานะงานแจ้งซ่อม</h1>
        <p>ตรวจสอบสถานะใบงานแจ้งซ่อมของคุณอย่างใกล้ชิด</p>
    </div>

    <!-- Main Container -->
    <div class="status-container">

        <!-- LIFF SDK Status and Loader Banner -->
        <div id="liffBanner" class="liff-status-banner">
            <div class="spinner-border spinner-border-sm text-success" role="status" id="liffSpinner"></div>
            <span id="liffStatusText">กำลังเริ่มต้นการทำงาน LINE LIFF...</span>
        </div>

        <!-- LINE Profile Showcase (Hidden initially) -->
        <div id="lineProfileBox" class="line-profile-box d-none">
            <img id="lineUserAvatar" src="" alt="LINE Avatar">
            <div class="profile-details">
                <div class="profile-name" id="lineUserName">ผู้ใช้ LINE</div>
                <div class="profile-badge">
                    <i class="bi bi-person-check-fill"></i> ยืนยันตัวตนสำเร็จ
                </div>
            </div>
        </div>

        <!-- Summary Counters Card -->
        <div class="counters-card">
            <div class="counter-item">
                <div class="counter-val" id="count-total">0</div>
                <div class="counter-lbl">แจ้งซ่อมทั้งหมด</div>
            </div>
            <div class="counter-item">
                <div class="counter-val text-warning" id="count-pending">0</div>
                <div class="counter-lbl">รอดำเนินการ</div>
            </div>
            <div class="counter-item">
                <div class="counter-val text-success" id="count-completed">0</div>
                <div class="counter-lbl">เสร็จสิ้นแล้ว</div>
            </div>
        </div>

        <!-- Ticket Card List Container -->
        <div id="tickets-list">
            <!-- Loading Indicator -->
            <div class="loader-block" id="loader">
                <div class="spinner-border text-success mb-2" role="status"></div>
                <p class="mb-0">กำลังโหลดประวัติการแจ้งซ่อมของท่าน...</p>
            </div>
        </div>

    </div>

    <!-- Footer Copyright -->
    <div class="footer">
        &copy; 2026 Dormitory Repair System. All Rights Reserved.
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- LINE LIFF SDK JS -->
    <script charset="utf-8" src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>

    <script>
        // =========================================================================
        // 1. ฟังก์ชันจัดแต่งสถานะด้วยไอคอนและคลาส CSS (Helper Functions)
        // =========================================================================
        function getStatusDetails(status) {
            switch(status) {
                case 'รอดำเนินการ':
                    return { icon: 'bi-hourglass-split', class: 'status-pending' };
                case 'กำลังดำเนินการ':
                    return { icon: 'bi-gear-fill', class: 'status-progress' };
                case 'เสร็จสิ้น':
                    return { icon: 'bi-check-circle-fill', class: 'status-completed' };
                case 'ยกเลิก':
                    return { icon: 'bi-x-circle-fill', class: 'status-cancelled' };
                default:
                    return { icon: 'bi-info-circle-fill', class: 'status-pending' };
            }
        }

        function getCategoryEmoji(cat) {
            switch(cat) {
                case 'ประปา': return '💧';
                case 'ไฟฟ้า': return '⚡';
                case 'ซ่อมสร้าง': return '🔨';
                default: return '🛠️';
            }
        }

        // =========================================================================
        // 2. ดึงประวัติการแจ้งซ่อมและวาดการ์ดประวัติ (Ajax rendering)
        // =========================================================================
        function loadRepairHistory(lineUid) {
            const listContainer = document.getElementById('tickets-list');
            
            fetch(`get_my_repairs.php?line_uid=${lineUid}`)
                .then(response => response.json())
                .then(data => {
                    // ลบตัวโหลด
                    listContainer.innerHTML = '';
                    
                    if (data.success && data.requests && data.requests.length > 0) {
                        const reqs = data.requests;
                        
                        // อัปเดตตัวนับ
                        let total = reqs.length;
                        let pending = 0;
                        let completed = 0;
                        
                        reqs.forEach(req => {
                            if (req.status === 'รอดำเนินการ') pending++;
                            if (req.status === 'เสร็จสิ้น') completed++;
                            
                            // 2.1 สร้างการ์ดใบงาน
                            const card = document.createElement('div');
                            card.className = 'ticket-card';
                            
                            const st = getStatusDetails(req.status);
                            
                            // จัดหมวดหมู่ของไอเทมชำรุดในใบงานพร้อมโชว์สถานะย่อย
                            let itemBadgesHTML = '';
                            if (req.items && req.items.length > 0) {
                                req.items.forEach(it => {
                                    const emoji = getCategoryEmoji(it.category);
                                    
                                    // กำหนดคลาสสีของสถานะอุปกรณ์ย่อย
                                    let statusColor = 'text-warning';
                                    if (it.status === 'กำลังดำเนินการ') statusColor = 'text-primary';
                                    else if (it.status === 'เสร็จสิ้น') statusColor = 'text-success';
                                    else if (it.status === 'ยกเลิก') statusColor = 'text-danger';
                                    
                                    itemBadgesHTML += `
                                        <span class="item-badge d-inline-flex align-items-center gap-1">
                                            <span>${emoji} ${it.item_name} x${it.quantity}</span>
                                            <span class="fw-bold ms-1 ${statusColor}" style="font-size: 0.7rem; border-left: 1px solid #cbd5e1; padding-left: 6px;">
                                                ${it.status}
                                            </span>
                                        </span>
                                    `;
                                });
                            }
                            
                            // ตรวจและวาดแกลเลอรี่รูปภาพประกอบ
                            let imagesHTML = '';
                            if (req.images && req.images.length > 0) {
                                imagesHTML += '<div class="image-gallery">';
                                req.images.forEach(img => {
                                    imagesHTML += `
                                        <div class="gallery-img-wrapper" onclick="previewImage('${img}')">
                                            <img src="${img}" alt="รูปชำรุด">
                                        </div>
                                    `;
                                });
                                imagesHTML += '</div>';
                            }
                            
                            // แปลงวันที่จัดให้สวยงาม (รูปแบบไทย)
                            const dateObj = new Date(req.created_at);
                            const formattedDate = dateObj.toLocaleDateString('th-TH', {
                                year: 'numeric',
                                month: 'short',
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            }) + ' น.';

                            // กำหนดการใช้งาน Timeline Steps
                            let step1 = 'step-active';
                            let step2 = '';
                            let step3 = '';
                            
                            if (req.status === 'กำลังดำเนินการ') {
                                step2 = 'step-info';
                            } else if (req.status === 'เสร็จสิ้น') {
                                step2 = 'step-active';
                                step3 = 'step-active';
                            } else if (req.status === 'รอดำเนินการ') {
                                step1 = 'step-warning';
                            } else if (req.status === 'ยกเลิก') {
                                step1 = 'step-warning'; // สำหรับงานยกเลิก
                            }

                            // วาง HTML ของการ์ด
                            card.innerHTML = `
                                <div class="ticket-header">
                                    <span class="ticket-id"><i class="bi bi-receipt me-1"></i>${req.ticket_id}</span>
                                    <span class="status-badge ${st.class}">
                                        <i class="bi ${st.icon}"></i> ${req.status}
                                    </span>
                                </div>
                                
                                <div class="info-row">
                                    <i class="bi bi-clock"></i>
                                    <span class="info-lbl">แจ้งเมื่อ:</span>
                                    <span class="info-val">${formattedDate}</span>
                                </div>
                                
                                <div class="info-row">
                                    <i class="bi bi-building"></i>
                                    <span class="info-lbl">ตำแหน่ง:</span>
                                    <span class="info-val">${req.dorm_name} ห้อง ${req.room_number}</span>
                                </div>
                                
                                <div class="info-row d-block mb-3">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <i class="bi bi-card-checklist"></i>
                                        <span class="info-lbl mb-0 w-auto">อุปกรณ์:</span>
                                    </div>
                                    <div class="item-tag-list ps-4 mt-1 mb-0">
                                        ${itemBadgesHTML}
                                    </div>
                                </div>
                                
                                <div class="info-row">
                                    <i class="bi bi-chat-left-dots"></i>
                                    <span class="info-lbl">รายละเอียด:</span>
                                    <span class="info-val">${req.additional_details ? htmlEscape(req.additional_details) : '<span class="text-muted">- ไม่มีคำอธิบาย -</span>'}</span>
                                </div>
                                
                                ${imagesHTML}
                                
                                <!-- Timeline steps tracker -->
                                <div class="timeline-tracker">
                                    <div class="timeline-step ${step1}">
                                        <div class="step-dot"></div>
                                        <div class="step-label">ยื่นคำขอ</div>
                                    </div>
                                    <div class="timeline-step ${step2}">
                                        <div class="step-dot"></div>
                                        <div class="step-label">ช่างรับเรื่อง</div>
                                    </div>
                                    <div class="timeline-step ${step3}">
                                        <div class="step-dot"></div>
                                        <div class="step-label">เสร็จสมบูรณ์</div>
                                    </div>
                                </div>
                            `;
                            listContainer.appendChild(card);
                        });
                        
                        // อัปเดตข้อมูล Counters
                        document.getElementById('count-total').textContent = total;
                        document.getElementById('count-pending').textContent = pending;
                        document.getElementById('count-completed').textContent = completed;
                        
                    } else {
                        // หน้าจอว่างเปล่า (Empty State)
                        listContainer.innerHTML = `
                            <div class="empty-state">
                                <i class="bi bi-journal-x"></i>
                                <h3>ไม่พบประวัติการแจ้งซ่อม</h3>
                                <p>ท่านยังไม่มีประวัติการยื่นเรื่องแจ้งซ่อมใดๆ ในระบบ</p>
                                <a href="repair_form.php" class="btn-primary-action">
                                    <i class="bi bi-plus-circle-fill"></i> แจ้งเรื่องแจ้งซ่อมใหม่
                                </a>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error fetching repairs:', error);
                    listContainer.innerHTML = `
                        <div class="alert alert-danger rounded-3" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            ไม่สามารถดึงข้อมูลได้ในขณะนี้ กรุณาลองใหม่อีกครั้งภายหลัง
                        </div>
                    `;
                });
        }

        // ฟังก์ชันช่วยพรีวิวขยายรูปซ่อมแซมสวยๆ ด้วย SweetAlert2
        function previewImage(imgUrl) {
            Swal.fire({
                imageUrl: imgUrl,
                imageAlt: 'รูปภาพอุปกรณ์ชำรุดประกอบใบงาน',
                confirmButtonText: 'ปิดรูปภาพ',
                confirmButtonColor: '#475569',
                background: '#ffffff',
                customClass: {
                    image: 'img-fluid rounded-3 border'
                }
            });
        }

        // ฟังก์ชันล้างอักษรพิเศษป้องกันการ XSS
        function htmlEscape(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        // =========================================================================
        // 3. ระบบเชื่อมต่อ LINE LIFF SDK
        // =========================================================================
        const liffId = "<?= LIFF_ID ?>";

        document.addEventListener("DOMContentLoaded", function() {
            if (liffId === "YOUR_LIFF_ID" || !liffId.trim()) {
                console.warn("LIFF ID is missing. Running in mock simulation mode.");
                showMockBadge();
                return;
            }

            // เริ่มต้นระบบ LINE LIFF SDK
            liff.init({ liffId: liffId })
                .then(() => {
                    if (!liff.isLoggedIn()) {
                        liff.login();
                    } else {
                        liff.getProfile()
                            .then(profile => {
                                // ซ่อนแถบแจ้งเตือนโหลด LINE
                                document.getElementById('liffBanner').classList.add('d-none');

                                // แสดงข้อมูลโปรไฟล์ผู้ใช้งาน LINE
                                document.getElementById('lineUserAvatar').src = profile.pictureUrl || 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
                                document.getElementById('lineUserName').textContent = profile.displayName;
                                document.getElementById('lineProfileBox').classList.remove('d-none');

                                // เรียกดึงประวัติการแจ้งซ่อมของ UID คนนี้
                                loadRepairHistory(profile.userId);
                            })
                            .catch(err => {
                                console.error('Error getting LINE profile:', err);
                                updateLiffStatus('❌ ดึงโปรไฟล์ LINE ไม่สำเร็จ');
                            });
                    }
                })
                .catch(err => {
                    console.error('LINE LIFF Init Failed:', err);
                    updateLiffStatus('⚠️ ไม่พบ LINE LIFF Environment. สลับเข้าสู่โหมดจำลอง');
                    setTimeout(() => {
                        showMockBadge();
                    }, 1000);
                });
        });

        function updateLiffStatus(msg) {
            document.getElementById('liffSpinner').classList.add('d-none');
            document.getElementById('liffStatusText').textContent = msg;
        }

        // โหมด Mock พรีเมียมสำหรับการดีบั๊กภายนอกแอป LINE
        function showMockBadge() {
            const banner = document.getElementById('liffBanner');
            banner.innerHTML = '<span class="simulation-badge">Dev Mock Mode</span> <span>ใช้งานนอกแอป LINE (คลิกเพื่อเชื่อมประวัติจำลอง)</span>';
            banner.className = "liff-status-banner";
            banner.style.cursor = "pointer";
            
            // เมื่อคลิกจะจำลองแอคเคาท์ทดสอบที่ลงทะเบียนแล้ว
            banner.addEventListener('click', function() {
                document.getElementById('lineUserAvatar').src = 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
                document.getElementById('lineUserName').textContent = 'Developer (Mock Profile)';
                document.getElementById('lineProfileBox').classList.remove('d-none');
                
                // โหลดข้อมูลประวัติของ Mock UID
                loadRepairHistory("MOCK_LINE_UID_650201");
            });
        }
    </script>
</body>
</html>
