<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Admin Panel') ?> | Dorm Repair Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #06C755;
            --primary-dark: #05a044;
            --sidebar-bg: #1e293b;
            --sidebar-hover: #0f172a;
            --sidebar-active: rgba(6, 199, 85, 0.15);
            --sidebar-text: #94a3b8;
            --sidebar-text-active: #06C755;
            --sidebar-width: 250px;
            --topbar-height: 60px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Kanit', sans-serif;
            background-color: #f1f5f9;
            color: #334155;
            margin: 0;
            padding: 0;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background-color: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            overflow-y: auto;
            transition: transform 0.3s ease;
        }

        .sidebar-brand {
            padding: 20px 20px 16px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-brand .brand-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: white;
            flex-shrink: 0;
        }

        .sidebar-brand .brand-text {
            font-size: 0.95rem;
            font-weight: 600;
            color: #f1f5f9;
            line-height: 1.3;
        }

        .sidebar-brand .brand-sub {
            font-size: 0.72rem;
            color: var(--sidebar-text);
            font-weight: 400;
        }

        .sidebar-section-label {
            font-size: 0.68rem;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 18px 20px 6px 20px;
        }

        .sidebar-nav {
            flex: 1;
            padding: 8px 12px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 2px;
            transition: all 0.2s ease;
        }

        .nav-item i {
            font-size: 1.05rem;
            width: 20px;
            text-align: center;
            flex-shrink: 0;
        }

        .nav-item:hover {
            background-color: rgba(255,255,255,0.06);
            color: #e2e8f0;
        }

        .nav-item.active {
            background-color: var(--sidebar-active);
            color: var(--sidebar-text-active);
            font-weight: 600;
        }

        .nav-item.active i {
            color: var(--primary);
        }

        .sidebar-footer {
            padding: 14px 16px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(255,255,255,0.05);
            margin-bottom: 8px;
        }

        .admin-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
            font-weight: 600;
            flex-shrink: 0;
        }

        .admin-name {
            font-size: 0.85rem;
            font-weight: 500;
            color: #e2e8f0;
            line-height: 1.3;
        }

        .admin-role {
            font-size: 0.7rem;
            color: var(--sidebar-text);
        }

        .btn-logout {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 8px 12px;
            border-radius: 8px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.85rem;
            border: 1px solid rgba(255,255,255,0.08);
            transition: all 0.2s;
        }

        .btn-logout:hover {
            background: rgba(239,68,68,0.1);
            color: #f87171;
            border-color: rgba(239,68,68,0.2);
        }

        /* Main content */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Topbar */
        .topbar {
            background: white;
            height: var(--topbar-height);
            display: flex;
            align-items: center;
            padding: 0 24px;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 100;
            gap: 12px;
        }

        .btn-sidebar-toggle {
            display: none;
            background: none;
            border: none;
            color: #64748b;
            font-size: 1.3rem;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .btn-sidebar-toggle:hover {
            background: #f1f5f9;
        }

        .topbar-title {
            font-size: 1.05rem;
            font-weight: 600;
            color: #1e293b;
            flex: 1;
        }

        .topbar-subtitle {
            font-size: 0.75rem;
            color: #94a3b8;
            font-weight: 400;
        }

        /* Content */
        .main-content {
            padding: 24px;
            flex: 1;
        }

        /* Cards */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            height: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 14px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 400;
        }

        /* Page header */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .page-header h2 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }

        .page-header .page-desc {
            font-size: 0.85rem;
            color: #64748b;
            margin: 0;
        }

        /* Panel cards */
        .panel {
            background: white;
            border-radius: 16px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            margin-bottom: 24px;
            overflow: hidden;
        }

        .panel-header {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .panel-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .panel-title i {
            color: var(--primary);
        }

        .panel-body {
            padding: 20px;
        }

        /* Status badges */
        .badge-status {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-pending   { background: #fef3c7; color: #d97706; }
        .badge-progress  { background: #dbeafe; color: #2563eb; }
        .badge-completed { background: #d1fae5; color: #059669; }
        .badge-cancelled { background: #fee2e2; color: #dc2626; }

        /* Table */
        .table-clean {
            font-size: 0.9rem;
        }

        .table-clean th {
            font-weight: 600;
            color: #475569;
            border-top: none;
            background: #f8fafc;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .table-clean td {
            vertical-align: middle;
            color: #334155;
            border-color: #f1f5f9;
        }

        .table-clean tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Sidebar overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-overlay.open {
                display: block;
            }

            .main-wrapper {
                margin-left: 0;
            }

            .btn-sidebar-toggle {
                display: block;
            }

            .main-content {
                padding: 16px;
            }
        }
    </style>
    <?= $extra_head ?? '' ?>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-tools"></i></div>
        <div>
            <div class="brand-text">Dorm Repair</div>
            <div class="brand-sub">Admin Panel</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-section-label">เมนูหลัก</div>
        <a href="index.php" class="nav-item <?= ($current_page ?? '') === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2-fill"></i> Dashboard
        </a>
        <a href="repairs.php" class="nav-item <?= ($current_page ?? '') === 'repairs' ? 'active' : '' ?>">
            <i class="bi bi-clipboard2-check-fill"></i> ใบงานแจ้งซ่อม
        </a>
        <a href="pending.php" class="nav-item <?= ($current_page ?? '') === 'pending' ? 'active' : '' ?>">
            <i class="bi bi-exclamation-circle-fill"></i> ห้องรอดำเนินการ
        </a>
        <a href="materials.php" class="nav-item <?= ($current_page ?? '') === 'materials' ? 'active' : '' ?>">
            <i class="bi bi-box-seam-fill"></i> วัสดุที่ต้องใช้
        </a>

        <div class="sidebar-section-label">รายงาน</div>
        <a href="report.php" class="nav-item <?= ($current_page ?? '') === 'report' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-bar-graph-fill"></i> ออกรายงาน
        </a>

        <div class="sidebar-section-label">จัดการข้อมูล</div>
        <a href="dorms.php" class="nav-item <?= ($current_page ?? '') === 'dorms' ? 'active' : '' ?>">
            <i class="bi bi-building-fill"></i> หอพัก / ห้องพัก
        </a>
        <a href="items.php" class="nav-item <?= ($current_page ?? '') === 'items' ? 'active' : '' ?>">
            <i class="bi bi-tools"></i> รายการอุปกรณ์
        </a>
        <a href="students.php" class="nav-item <?= ($current_page ?? '') === 'students' ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i> นักศึกษา
        </a>
        <a href="staff.php" class="nav-item <?= ($current_page ?? '') === 'staff' ? 'active' : '' ?>">
            <i class="bi bi-person-badge-fill"></i> เจ้าหน้าที่ / ช่าง
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="admin-avatar"><?= mb_substr($_SESSION['admin_name'] ?? 'A', 0, 1) ?></div>
            <div>
                <div class="admin-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? '') ?></div>
                <div class="admin-role"><?= $_SESSION['admin_role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ช่างซ่อม' ?></div>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">
            <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
        </a>
    </div>
</aside>

<!-- Main Wrapper -->
<div class="main-wrapper">
    <!-- Topbar -->
    <header class="topbar">
        <button class="btn-sidebar-toggle" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        <div class="flex-fill">
            <div class="topbar-title"><?= htmlspecialchars($page_title ?? '') ?></div>
            <?php if (!empty($page_subtitle)): ?>
            <div class="topbar-subtitle"><?= htmlspecialchars($page_subtitle) ?></div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
