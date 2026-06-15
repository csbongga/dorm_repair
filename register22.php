<?php
/**
 * หน้าลงทะเบียนข้อมูลนักศึกษาสำหรับระบบหอพัก (LINE LIFF Optimized)
 * พัฒนาสำหรับระบบแจ้งซ่อมหอพัก (dorm_repair)
 */

require_once 'connect.php';

// กรุณาเปลี่�// ตรวจสอบว่ามีการส่งข้อมูลแบบ POST มาหรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่าตัวแปรจากฟอร์มอย่างครบถ้วนและทำความสะอาดข้อมูลเบื้องต้น
    $line_uid         = trim($_POST['line_uid'] ?? '');
    $line_profile_img = trim($_POST['line_profile_img'] ?? '');
    $student_id       = trim($_POST['student_id'] ?? '');
    $full_name        = trim($_POST['full_name'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $dorm_id          = trim($_POST['dorm_id'] ?? '');
    $room_id          = trim($_POST['room_id'] ?? '');

    // 1. ตรวจสอบความถูกต้อง (Validation): เช็คว่าค่าจากฟอร์มต้องไม่เป็นค่าว่าง
    if (empty($student_id) || empty($full_name) || empty($phone) || empty($dorm_id) || empty($room_id)) {
        $error_message = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วนทุกช่อง';
    } else {
        try {
            // 2. ตรวจสอบข้อมูลซ้ำ: ค้นหาในตาราง students ก่อนว่า line_uid หรือ student_id นี้เคยลงทะเบียนไปแล้วหรือยัง
            $checkStmt = $pdo->prepare("
                SELECT student_id, line_uid 
                FROM students 
                WHERE student_id = :student_id 
                   OR (line_uid = :line_uid AND line_uid IS NOT NULL AND line_uid != '') 
                LIMIT 1
            ");
            $checkStmt->execute([
                'student_id' => $student_id,
                'line_uid' => $line_uid
            ]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // ถ้าพบข้อมูลซ้ำในระบบ
                if ($existing['student_id'] === $student_id) {
                    $error_message = 'รหัสนักศึกษานี้เคยทำการลงทะเบียนในระบบเรียบร้อยแล้ว';
                } else {
                    $error_message = 'บัญชี LINE นี้เคยถูกใช้ลงทะเบียนไปแล้วในระบบ';
                }
            } else {
                // 3. บันทึกข้อมูลใหม่ (Data Insertion) โดยใช้ Prepared Statement เพื่อความปลอดภัยสูงสุด
                // ปรับให้ตรงตาม Schema ของผู้ใช้งาน (ใช้ฟิลด์ 'name' แทน 'full_name' และบันทึกรูปโปรไฟล์ LINE)
                $insertStmt = $pdo->prepare("
                    INSERT INTO students (student_id, name, phone, room_id, line_uid, line_profile_img) 
                    VALUES (:student_id, :full_name, :phone, :room_id, :line_uid, :line_profile_img)
                ");
                $insertStmt->execute([
                    'student_id'       => $student_id,
                    'full_name'        => $full_name,
                    'phone'            => $phone,
                    'room_id'          => $room_id,
                    'line_uid'         => !empty($line_uid) ? $line_uid : null,
                    'line_profile_img' => !empty($line_profile_img) ? $line_profile_img : null
                ]);

                // กำหนดสถานะบันทึกสำเร็จ
                $register_success = true;
            }
        } catch (PDOException $e) {
            // บันทึก Log ข้อผิดพลาดทางเทคนิค และระบุสาเหตุจริงเพื่อวิเคราะห์ปัญหาร่วมกับทีมพัฒนา
            error_log("Registration Database Error: " . $e->getMessage());
            $error_message = 'เกิดข้อผิดพลาดในระบบฐานข้อมูล: ' . $e->getMessage();
        }
    }
}rtion) โดยใช้ Prepared Statement เพื่อความปลอดภัยสูงสุด
                $insertStmt = $pdo->prepare("
                    INSERT INTO students (student_id, full_name, phone, room_id, line_uid) 
                    VALUES (:student_id, :full_name, :phone, :room_id, :line_uid)
                ");
                $insertStmt->execute([
                    'student_id' => $student_id,
                    'full_name'  => $full_name,
                    'phone'      => $phone,
                    'room_id'    => $room_id,
                    'line_uid'   => $line_uid
                ]);

                // กำหนดสถานะบันทึกสำเร็จ
                $register_success = true;
            }
        } catch (PDOException $e) {
            // บันทึก Log ข้อผิดพลาดทางเทคนิค และระบุสาเหตุจริงเพื่อวิเคราะห์ปัญหาร่วมกับทีมพัฒนา
            error_log("Registration Database Error: " . $e->getMessage());
            $error_message = 'เกิดข้อผิดพลาดในระบบฐานข้อมูล: ' . $e->getMessage();
        }
    }
}

// ดึงข้อมูลหอพักทั้งหมดเพื่อนำมาแสดงผลใน Dropdown
try {
    $stmt = $pdo->query("SELECT id, name, dorm_type FROM dorms ORDER BY name ASC");
    $dorms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching dorms for registration: " . $e->getMessage());
    $dorms = [];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ลงทะเบียนผู้ใช้งาน | Student Registration</title>
    <!-- Bootstrap 5 CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- SweetAlert2 via CDN (แจ้งเตือนสวยงาม) -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Google Font (Kanit) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #06C755; /* LINE Green */
            --primary-dark: #05b04b;
            --bg-gradient: linear-gradient(135deg, #f4fbf7 0%, #e8f7ee 100%);
            --card-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
            --input-focus: rgba(6, 199, 85, 0.25);
        }

        body {
            font-family: 'Kanit', sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            color: #333333;
            padding-bottom: 40px;
        }

        .liff-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 30px 20px;
            border-bottom-left-radius: 25px;
            border-bottom-right-radius: 25px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(6, 199, 85, 0.15);
            position: relative;
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

        /* กล่องโปรไฟล์ LINE พรีเมียม */
        .line-profile-card {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 15px;
            border: 1px solid rgba(6, 199, 85, 0.2);
            padding: 12px 16px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .line-profile-card img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 2px solid var(--primary-color);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .line-profile-card .profile-info {
            flex-grow: 1;
        }

        .line-profile-card .profile-name {
            font-weight: 500;
            font-size: 0.95rem;
            margin-bottom: 2px;
            color: #2d3748;
        }

        .line-profile-card .profile-badge {
            font-size: 0.75rem;
            color: var(--primary-dark);
            background-color: rgba(6, 199, 85, 0.1);
            padding: 2px 8px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 500;
        }

        .liff-card {
            background: white;
            border: none;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            margin: -20px 15px 0 15px;
            padding: 24px;
            position: relative;
            z-index: 10;
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

        .form-control:focus, .form-select:focus {
            background-color: #ffffff;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px var(--input-focus);
            outline: none;
        }

        .btn-register {
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

        .btn-register:active {
            transform: scale(0.98);
        }

        .footer-text {
            text-align: center;
            font-size: 0.8rem;
            color: #94a3b8;
            margin-top: 25px;
        }
        
        /* สปินเนอร์ตอนรอโหลดโหลดโปรไฟล์ LINE */
        .liff-loader {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #718096;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

    <!-- Header Section (LINE LIFF style) -->
    <div class="liff-header">
        <i class="bi bi-person-badge logo-container d-inline-block text-white mb-2" style="font-size: 2.5rem;"></i>
        <h1>ลงทะเบียนผู้ใช้งานใหม่</h1>
        <p>เชื่อมโยงข้อมูลนักศึกษาเข้ากับบัญชี LINE ของคุณ</p>
    </div>

    <!-- Main Card Container -->
    <div class="liff-card">
        
        <!-- ตัวโหลดสถานะ LINE Profile -->
        <div id="liffLoader" class="liff-loader">
            <div class="spinner-border spinner-border-sm text-success" role="status"></div>
            <span>กำลังดึงข้อมูลความปลอดภัยจาก LINE SDK...</span>
        </div>

        <!-- LINE Profile Card (จะปรากฏขึ้นหลังดึงข้อมูลสำเร็จ) -->
        <div id="lineProfileCard" class="line-profile-card d-none">
            <img id="lineAvatar" src="" alt="LINE Avatar">
            <div class="profile-info">
                <div class="profile-name" id="lineName">ผู้ใช้ LINE</div>
                <div class="profile-badge"><i class="bi bi-shield-fill-check"></i> เชื่อมต่อ LINE สำเร็จ</div>
            </div>
        </div>

        <form action="" method="POST" id="registerForm" class="needs-validation" novalidate>
            
            <!-- Hidden Input สำหรับเก็บค่า line_uid ที่ได้จาก LINE LIFF Profile -->
            <input type="hidden" name="line_uid" id="line_uid" value="">

            <!-- Hidden Input สำหรับเก็บ URL รูปภาพโปรไฟล์ LINE -->
            <input type="hidden" name="line_profile_img" id="line_profile_img" value="">

            <!-- รหัสนักศึกษา -->
            <div class="mb-4">
                <label for="student_id" class="form-label">
                    <i class="bi bi-card-text"></i> รหัสนักศึกษา
                </label>
                <input type="text" class="form-control" id="student_id" name="student_id" 
                       placeholder="ระบุรหัสนักศึกษา (เช่น 650XXXXXXX)" required 
                       inputmode="numeric" pattern="[0-9]{10,13}">
                <div class="invalid-feedback">กรุณากรอกรหัสนักศึกษาให้ถูกต้องเป็นตัวเลข 10-13 หลัก</div>
            </div>

            <!-- ชื่อ-นามสกุล -->
            <div class="mb-4">
                <label for="full_name" class="form-label">
                    <i class="bi bi-person"></i> ชื่อ - นามสกุล
                </label>
                <input type="text" class="form-control" id="full_name" name="full_name" 
                       placeholder="ระบุชื่อและนามสกุลจริง" required>
                <div class="invalid-feedback">กรุณาระบุชื่อและนามสกุลของคุณ</div>
            </div>

            <!-- เบอร์โทรศัพท์ -->
            <div class="mb-4">
                <label for="phone" class="form-label">
                    <i class="bi bi-telephone"></i> เบอร์โทรศัพท์ที่ติดต่อได้
                </label>
                <input type="tel" class="form-control" id="phone" name="phone" 
                       placeholder="ตัวอย่าง: 08XXXXXXXX" required 
                       inputmode="tel" pattern="[0-9]{9,10}">
                <div class="invalid-feedback">กรุณากรอกเบอร์โทรศัพท์มือถือ 9-10 หลัก</div>
            </div>

            <div class="row">
                <!-- หอพัก -->
                <div class="col-6 mb-4">
                    <label for="dorm_id" class="form-label">
                        <i class="bi bi-building"></i> หอพัก
                    </label>
                    <select class="form-select" id="dorm_id" name="dorm_id" required>
                        <option value="" selected disabled>เลือกหอพัก</option>
                        <?php foreach ($dorms as $dorm): ?>
                            <option value="<?= htmlspecialchars($dorm['id']) ?>">
                                <?= htmlspecialchars($dorm['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">เลือกหอพัก</div>
                </div>

                <!-- หมายเลขห้อง -->
                <div class="col-6 mb-4">
                    <label for="room_id" class="form-label">
                        <i class="bi bi-door-closed"></i> ห้องพัก
                    </label>
                    <select class="form-select" id="room_id" name="room_id" required disabled>
                        <option value="" selected disabled>กรุณาเลือกหอพักก่อน</option>
                    </select>
                    <div class="invalid-feedback">เลือกห้องพัก</div>
                </div>
            </div>

            <!-- ปุ่มสมัครสมาชิก -->
            <div class="mt-4">
                <button type="submit" class="btn btn-register btn-lg d-flex justify-content-center align-items-center gap-2">
                    <i class="bi bi-check-circle-fill"></i> ยืนยันข้อมูลและลงทะเบียน
                </button>
            </div>
            
        </form>
    </div>

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
        // ----------------------------------------------------
        // 1. ระบบเริ่มต้นใช้งาน LINE LIFF SDK (LIFF Init)
        // ----------------------------------------------------
        const liffId = "<?= LIFF_ID ?>";

        document.addEventListener("DOMContentLoaded", function() {
            if (liffId === 'YOUR_LIFF_ID' || !liffId) {
                console.warn("แจ้งเตือน: โปรดระบุ LIFF ID ในโค้ด PHP ด้านบน");
                document.getElementById('liffLoader').innerHTML = '⚠️ ยังไม่ได้ระบุ LIFF ID (รันในโหมดจำลองระบบทั่วไป)';
                return;
            }

            // เริ่มต้นระบบ LIFF
            liff.init({ liffId: liffId })
                .then(() => {
                    // ตรวจสอบว่าผู้ใช้งานได้ทำการล็อกอินแล้วหรือยัง
                    if (!liff.isLoggedIn()) {
                        liff.login();
                    } else {
                        // ดึงข้อมูลโปรไฟล์ผู้ใช้งาน LINE
                        liff.getProfile()
                            .then(profile => {
                                // ซ่อนตัวโหลดสถานะ
                                document.getElementById('liffLoader').classList.add('d-none');

                                // กำหนดค่า line_uid ลงใน Input Hidden
                                document.getElementById('line_uid').value = profile.userId;

                                // กำหนด URL รูปภาพโปรไฟล์ LINE ลงใน Input Hidden
                                document.getElementById('line_profile_img').value = profile.pictureUrl || '';

                                // แสดงหน้าบัตรข้อมูลโปรไฟล์ผู้ใช้ LINE
                                document.getElementById('lineAvatar').src = profile.pictureUrl || 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
                                document.getElementById('lineName').textContent = profile.displayName;
                                document.getElementById('lineProfileCard').classList.remove('d-none');
                            })
                            .catch(err => {
                                console.error('ดึงโปรไฟล์ LINE ล้มเหลว:', err);
                                document.getElementById('liffLoader').innerHTML = '❌ ดึงข้อมูลโปรไฟล์ LINE ล้มเหลว';
                            });
                    }
                })
                .catch(err => {
                    console.error('เริ่มต้นระบบ LIFF ล้มเหลว:', err);
                    document.getElementById('liffLoader').innerHTML = '❌ เริ่มต้นระบบความปลอดภัย LINE ล้มเหลว';
                });
        });

        // ----------------------------------------------------
        // 2. ระบบดึงข้อมูลห้องพักแบบไดนามิกตามหอพักที่เลือก
        // ----------------------------------------------------
        document.getElementById('dorm_id').addEventListener('change', function() {
            const dormId = this.value;
            const roomSelect = document.getElementById('room_id');
            
            roomSelect.innerHTML = '<option value="" selected disabled>⏳ กำลังโหลดห้องพัก...</option>';
            roomSelect.disabled = true;

            if (!dormId) {
                roomSelect.innerHTML = '<option value="" selected disabled>กรุณาเลือกหอพักก่อน</option>';
                return;
            }

            fetch(`get_rooms.php?dorm_id=${dormId}`)
                .then(response => {
                    if (!response.ok) throw new Error('การตอบกลับเครือข่ายผิดปกติ');
                    return response.json();
                })
                .then(data => {
                    roomSelect.innerHTML = '<option value="" selected disabled>เลือกห้องพัก</option>';
                    
                    if (data.success && data.rooms.length > 0) {
                        data.rooms.forEach(room => {
                            const option = document.createElement('option');
                            option.value = room.id;
                            
                            let statusNote = '';
                            if (room.status && room.status !== 'ready' && room.status !== 'active') {
                                statusNote = ` (${room.status})`;
                            }
                            
                            option.textContent = `ห้อง ${room.room_number}${statusNote}`;
                            roomSelect.appendChild(option);
                        });
                        roomSelect.disabled = false;
                    } else {
                        roomSelect.innerHTML = '<option value="" selected disabled>⚠️ ไม่พบห้องพักว่างในหอนี้</option>';
                    }
                })
                .catch(error => {
                    console.error('เกิดข้อผิดพลาดในการดึงห้อง:', error);
                    roomSelect.innerHTML = '<option value="" selected disabled>❌ เกิดข้อผิดพลาดในระบบ</option>';
                });
        });

        // ----------------------------------------------------
        // 3. ระบบตรวจสอบความถูกต้องของแบบฟอร์ม (Form Validation)
        // ----------------------------------------------------
        const form = document.getElementById('registerForm');
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    </script>

    <!-- ----------------------------------------------------
         4. แสดง SweetAlert2 แจ้งเตือนเมื่อสมัครสมาชิกสำเร็จ
         ---------------------------------------------------- -->
    <?php if ($register_success): ?>
        <script>
            Swal.fire({
                title: 'ลงทะเบียนสำเร็จ! 🎉',
                text: 'ระบบได้ผูกบัญชี LINE เข้ากับข้อมูลนักศึกษาของท่านเรียบร้อยแล้ว',
                icon: 'success',
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#06C755',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // ย้ายหน้าไปยังระบบแจ้งซ่อม
                    window.location.href = 'repair_form.php';
                }
            });
        </script>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <script>
            Swal.fire({
                title: 'เกิดข้อผิดพลาด! ⚠️',
                text: '<?= htmlspecialchars($error_message) ?>',
                icon: 'error',
                confirmButtonText: 'ลองอีกครั้ง',
                confirmButtonColor: '#dc3545'
            });
        </script>
    <?php endif; ?>

</body>
</html>
