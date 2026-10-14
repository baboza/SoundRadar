<?php
/**
 * Professional Admin Dashboard - Khaen Complex Center
 * Comprehensive Enterprise-grade Control Center for Khaen Complex Center
 * Features:
 * - Real-time Key Metrics & Analytics breakdown
 * - Pending Pin Approval Queue with quick action & Leaflet inspection modal
 * - User Directory Management (Role/Category filter, Verification badge toggle, Suspensions, Fast inline edit)
 * - Dedicated Marketplace Moderation (Specs inspection, Available/Sold toggle, Delete)
 * - Quick Add & Edit User Modal
 * - System Activity Logs & Audit Trail Simulation
 * - Export Data to CSV (Users & Marketplace Items)
 */
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db.php';

$currentUser = getCurrentUser();

if (!$currentUser || $currentUser['role'] !== 'admin') {
    die("<!DOCTYPE html>
<html lang='th'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>จำกัดสิทธิ์เฉพาะผู้ดูแลระบบ | Khaen Complex Center</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link href='https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap' rel='stylesheet'>
    <style>body{font-family:'Prompt',sans-serif;}</style>
</head>
<body class='bg-gray-100 flex items-center justify-center min-h-screen p-4'>
    <div class='max-w-md w-full bg-white p-8 rounded-3xl shadow-xl text-center border border-gray-100'>
        <div class='w-16 h-16 bg-red-50 text-red-600 rounded-2xl flex items-center justify-center mx-auto mb-4'>
            <svg class='w-8 h-8' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/></svg>
        </div>
        <h3 class='text-lg font-bold text-gray-900 mb-1'>จำกัดสิทธิ์เฉพาะผู้ดูแลระบบ (Admin Only)</h3>
        <p class='text-xs text-gray-500 mb-6'>คุณต้องเข้าสู่ระบบในฐานะแอดมิน เพื่อเข้าถึงระบบตรวจสอบความปลอดภัยและการอนุมัติข้อมูล</p>
        <div class='flex flex-col gap-2.5'>
            <a href='admin.php?switch_role=admin' class='w-full py-3 bg-amber-700 hover:bg-amber-800 text-white font-semibold rounded-xl text-xs sm:text-sm transition shadow-md shadow-amber-900/10'>สลับเป็นผู้ดูแลระบบ (Admin Mode)</a>
            <a href='index.php' class='w-full py-2.5 text-xs text-gray-500 hover:text-gray-700 font-medium'>กลับหน้าหลัก Khaen Complex Center</a>
        </div>
    </div>
</body>
</html>");
}

// Handle Export to CSV
if (isset($_GET['export'])) {
    $exportType = $_GET['export'];
    if ($exportType === 'users') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=khaen_complex_users_' . date('Ymd_His') . '.csv');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel UTF-8
        fputcsv($output, ['ID', 'ชื่อ-นามสกุล', 'อีเมล', 'เบอร์โทร', 'บทบาท', 'คำอธิบาย/ฉายา', 'จังหวัด', 'ที่อยู่', 'ละติจูด', 'ลองจิจูด', 'สถานะพิกัด', 'ยืนยันตัวตน', 'สถานะบัญชี', 'วันที่ลงทะเบียน']);
        $stmt = $pdo->query("SELECT * FROM users ORDER BY id ASC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['name'],
                $row['email'],
                $row['phone'],
                $row['category'],
                $row['title_desc'],
                $row['province'],
                $row['address'],
                $row['latitude'],
                $row['longitude'],
                $row['pin_status'] ?? 'none',
                $row['is_verified'] ? 'Verified' : 'Unverified',
                $row['status'],
                $row['created_at']
            ]);
        }
        fclose($output);
        exit;
    } elseif ($exportType === 'market') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=khaen_complex_marketplace_' . date('Ymd_His') . '.csv');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, ['ID', 'ชื่อสินค้า', 'ประเภท', 'คีย์', 'ราคา (บาท)', 'สภาพ', 'ชนิดไม้', 'ชนิดลิ้น', 'ผู้ขาย', 'สถานะ', 'วันที่ลงขาย']);
        $stmt = $pdo->query("SELECT m.*, u.name as seller_name FROM marketplace_items m JOIN users u ON m.user_id = u.id ORDER BY m.id ASC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['title'],
                $row['item_type'],
                $row['key_note'],
                $row['price'],
                $row['condition_status'],
                $row['wood_type'] ?? '-',
                $row['reed_type'] ?? '-',
                $row['seller_name'],
                $row['status'],
                $row['created_at']
            ]);
        }
        fclose($output);
        exit;
    }
}

// Handle Form Submissions (Create/Edit User)
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $editUserId = (int)($_POST['user_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $category = trim($_POST['category'] ?? 'player');
    $title_desc = trim($_POST['title_desc'] ?? '');
    $province = trim($_POST['province'] ?? 'ขอนแก่น');
    $address = trim($_POST['address'] ?? '');
    $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    $pin_status = trim($_POST['pin_status'] ?? 'none');
    $is_verified = isset($_POST['is_verified']) ? 1 : 0;
    $status = trim($_POST['status'] ?? 'active');

    if (empty($name) || empty($email)) {
        $msg = 'กรุณาระบุชื่อและอีเมลให้ครบถ้วน';
        $msgType = 'warning';
    } else {
        if ($editUserId > 0) {
            // Update
            $stmt = $pdo->prepare("
                UPDATE users SET 
                    name = ?, email = ?, phone = ?, category = ?, title_desc = ?, 
                    province = ?, address = ?, latitude = ?, longitude = ?, 
                    pin_status = ?, is_verified = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name, $email, $phone, $category, $title_desc,
                $province, $address, $latitude, $longitude,
                $pin_status, $is_verified, $status, $editUserId
            ]);
            $msg = 'อัปเดตข้อมูลผู้ใช้งานรหัส #' . $editUserId . ' สำเร็จ';
        } else {
            // Create New
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $msg = 'อีเมลนี้ถูกใช้งานแล้ว กรุณาใช้อีเมลอื่น';
                $msgType = 'warning';
            } else {
                $avatar = 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=400&q=80';
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, phone, category, title_desc, province, address, latitude, longitude, pin_status, is_verified, status, avatar)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $name, $email, $phone, $category, $title_desc,
                    $province, $address, $latitude, $longitude,
                    $pin_status, $is_verified, $status, $avatar
                ]);
                $msg = 'เพิ่มข้อมูลสมาชิกใหม่เรียบร้อยแล้ว';
            }
        }
    }
}

// Handle GET Actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = (int)($_GET['id'] ?? 0);

    if ($action === 'verify_user' && $id > 0) {
        $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$id]);
        $msg = 'อนุมัติการยืนยันตัวตนผู้ใช้งาน (Verified Badge) เรียบร้อยแล้ว';
    } elseif ($action === 'unverify_user' && $id > 0) {
        $pdo->prepare("UPDATE users SET is_verified = 0 WHERE id = ?")->execute([$id]);
        $msg = 'ปลดสถานะการยืนยันตัวตนเรียบร้อยแล้ว';
    } elseif ($action === 'approve_pin' && $id > 0) {
        $pdo->prepare("UPDATE users SET pin_status = 'approved' WHERE id = ?")->execute([$id]);
        $msg = 'อนุมัติพิกัด GPS เรียบร้อยแล้ว หมุดจะแสดงบนแผนที่สาธารณะทันที';
    } elseif ($action === 'reject_pin' && $id > 0) {
        $pdo->prepare("UPDATE users SET pin_status = 'rejected' WHERE id = ?")->execute([$id]);
        $msg = 'ปฏิเสธคำขอปักหมุดพิกัดเรียบร้อยแล้ว';
        $msgType = 'warning';
    } elseif ($action === 'reset_pin' && $id > 0) {
        $pdo->prepare("UPDATE users SET pin_status = 'none', latitude = NULL, longitude = NULL WHERE id = ?")->execute([$id]);
        $msg = 'ล้างพิกัดแผนที่ของผู้ใช้งานเรียบร้อยแล้ว';
        $msgType = 'warning';
    } elseif ($action === 'toggle_user_status' && $id > 0 && $id !== (int)$currentUser['id']) {
        $status = $_GET['status'] === 'suspended' ? 'suspended' : 'active';
        $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$status, $id]);
        $msg = $status === 'suspended' ? 'ระงับการใช้งานบัญชีสมาชิกเรียบร้อยแล้ว' : 'เปิดใช้งานบัญชีสมาชิกเรียบร้อยแล้ว';
    } elseif ($action === 'delete_user' && $id > 0 && $id !== (int)$currentUser['id']) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        $msg = 'ลบผู้ใช้งานและข้อมูลที่เกี่ยวข้องเรียบร้อยแล้ว';
        $msgType = 'warning';
    } elseif ($action === 'delete_item' && $id > 0) {
        $pdo->prepare("DELETE FROM marketplace_items WHERE id = ?")->execute([$id]);
        $msg = 'ลบรายการสินค้าในตลาดเรียบร้อยแล้ว';
        $msgType = 'warning';
    } elseif ($action === 'toggle_item_status' && $id > 0) {
        $newStatus = $_GET['status'] === 'sold' ? 'sold' : 'available';
        $pdo->prepare("UPDATE marketplace_items SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
        $msg = 'อัปเดตสถานะสินค้าเรียบร้อยแล้ว';
    }
}

// Active Tab
$activeTab = $_GET['tab'] ?? 'pins';

// Analytics & Counter Stats
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalMakers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE category = 'maker' OR FIND_IN_SET('maker', category) > 0")->fetchColumn();
$totalPlayers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE category = 'player' OR FIND_IN_SET('player', category) > 0")->fetchColumn();
$totalShops = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE category = 'shop' OR FIND_IN_SET('shop', category) > 0")->fetchColumn();
$totalLearningCenters = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE category = 'learning_center' OR FIND_IN_SET('learning_center', category) > 0")->fetchColumn();
$totalVerified = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_verified = 1")->fetchColumn();
$totalSuspended = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'suspended'")->fetchColumn();

$totalPendingPins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND pin_status = 'pending'")->fetchColumn();
$totalApprovedPins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND pin_status = 'approved'")->fetchColumn();
$totalRejectedPins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE pin_status = 'rejected'")->fetchColumn();

$totalItems = (int)$pdo->query("SELECT COUNT(*) FROM marketplace_items")->fetchColumn();
$totalAvailableItems = (int)$pdo->query("SELECT COUNT(*) FROM marketplace_items WHERE status = 'available'")->fetchColumn();
$totalSoldItems = (int)$pdo->query("SELECT COUNT(*) FROM marketplace_items WHERE status = 'sold'")->fetchColumn();
$marketVolumeVal = (float)$pdo->query("SELECT COALESCE(SUM(price), 0) FROM marketplace_items WHERE status = 'available'")->fetchColumn();

// Fetch Pending Approvals specifically
$pendingPinsList = $pdo->query("
    SELECT * FROM users 
    WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND pin_status = 'pending' 
    ORDER BY updated_at DESC
")->fetchAll();

// Search / Filter for Users
$userSearch = trim($_GET['user_search'] ?? '');
$userCategoryFilter = trim($_GET['user_category'] ?? 'all');
$userStatusFilter = trim($_GET['user_status'] ?? 'all');
$userSql = "SELECT * FROM users WHERE 1=1";
$userParams = [];

if (!empty($userSearch)) {
    $userSql .= " AND (name LIKE :us OR email LIKE :us OR phone LIKE :us OR province LIKE :us OR title_desc LIKE :us)";
    $userParams[':us'] = "%{$userSearch}%";
}
if ($userCategoryFilter !== 'all') {
    $userSql .= " AND (category = :ucat OR FIND_IN_SET(:ucat, category) > 0)";
    $userParams[':ucat'] = $userCategoryFilter;
}
if ($userStatusFilter !== 'all') {
    $userSql .= " AND status = :ustat";
    $userParams[':ustat'] = $userStatusFilter;
}
$userSql .= " ORDER BY id DESC";
$userStmt = $pdo->prepare($userSql);
$userStmt->execute($userParams);
$users = $userStmt->fetchAll();

// Search / Filter for Marketplace
$itemSearch = trim($_GET['item_search'] ?? '');
$itemStatusFilter = trim($_GET['item_status'] ?? 'all');
$itemSql = "
    SELECT m.*, u.name as seller_name, u.email as seller_email, u.phone as seller_phone, u.province as seller_province
    FROM marketplace_items m 
    JOIN users u ON m.user_id = u.id 
    WHERE 1=1
";
$itemParams = [];
if (!empty($itemSearch)) {
    $itemSql .= " AND (m.title LIKE :is OR m.key_note LIKE :is OR u.name LIKE :is)";
    $itemParams[':is'] = "%{$itemSearch}%";
}
if ($itemStatusFilter !== 'all') {
    $itemSql .= " AND m.status = :istat";
    $itemParams[':istat'] = $itemStatusFilter;
}
$itemSql .= " ORDER BY m.id DESC";
$itemStmt = $pdo->prepare($itemSql);
$itemStmt->execute($itemParams);
$items = $itemStmt->fetchAll();

// Top Provinces by Members
$topProvinces = $pdo->query("
    SELECT province, COUNT(*) as cnt 
    FROM users 
    GROUP BY province 
    ORDER BY cnt DESC 
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการผู้ดูแลระบบ (Admin Control Center) | Khaen Complex Center</title>
    
    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'isan-terracotta': '#C85A32',
                        'isan-bamboo': '#D4A373',
                        'isan-wood': '#8C5835',
                        'isan-indigo': '#1D2A44',
                        'isan-cream': '#FAEDCD',
                        'isan-bg': '#F8F9FA'
                    },
                    fontFamily: {
                        sans: ['"Bai Jamjuree"', '"Sarabun"', 'sans-serif'],
                        thai: ['"Charm"', 'cursive', 'serif']
                    }
                }
            }
        }
    </script>

    <!-- Google Fonts & Lucide Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bai+Jamjuree:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Charm:wght@400;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Leaflet.js for Inspecting Coordinates in Modal -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        body { font-family: 'Bai Jamjuree', 'Sarabun', sans-serif; }
        .font-thai-traditional { font-family: 'Charm', cursive, serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-isan-bg text-gray-800 min-h-screen flex flex-col">

    <!-- Top Admin Header Bar (Full width) -->
    <header class="bg-gray-950 text-white border-b border-gray-800 sticky top-0 z-40 shadow-md">
        <div class="w-full px-4 sm:px-6 lg:px-10 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-amber-600 to-amber-800 flex items-center justify-center text-white shadow-md">
                    <i data-lucide="shield-check" class="w-5 h-5"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-base tracking-tight text-white">Khaen Complex Center Admin</span>
                        <span class="text-[10px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30 px-2 py-0.5 rounded-full">SYSTEM CONTROL</span>
                    </div>
                    <p class="text-[11px] text-gray-400 hidden sm:block">ระบบตรวจสอบความปลอดภัย การอนุมัติพิกัด และจัดการชุมชนแคน</p>
                </div>
            </div>

            <!-- Header Quick Navigation & Actions -->
            <div class="flex items-center gap-2.5">
                <a href="index.php" class="text-xs text-gray-300 hover:text-white flex items-center gap-1.5 bg-gray-900 hover:bg-gray-800 px-3 py-2 rounded-xl transition border border-gray-800">
                    <i data-lucide="map-pin" class="w-4 h-4 text-amber-400"></i>
                    <span class="hidden md:inline">หน้าแผนที่สาธารณะ</span>
                </a>
                <a href="marketplace.php" class="text-xs text-gray-300 hover:text-white flex items-center gap-1.5 bg-gray-900 hover:bg-gray-800 px-3 py-2 rounded-xl transition border border-gray-800">
                    <i data-lucide="shopping-bag" class="w-4 h-4 text-orange-400"></i>
                    <span class="hidden md:inline">ตลาดซื้อขาย</span>
                </a>
                <a href="profile.php" class="text-xs text-gray-300 hover:text-white flex items-center gap-1.5 bg-gray-900 hover:bg-gray-800 px-3 py-2 rounded-xl transition border border-gray-800">
                    <i data-lucide="user" class="w-4 h-4 text-blue-400"></i>
                    <span class="hidden md:inline">โปรไฟล์ของฉัน</span>
                </a>

                <div class="h-5 w-px bg-gray-800 mx-1"></div>

                <a href="index.php?switch_role=maker" class="text-xs text-amber-400 hover:underline px-2 py-1 font-medium">
                    สลับเป็น User
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Area (Full screen width) -->
    <main class="w-full px-4 sm:px-6 lg:px-10 py-6 sm:py-8 flex-1">
        
        <!-- Flash Alert Message -->
        <?php if (!empty($msg)): ?>
        <div class="mb-6 p-4 rounded-2xl <?= $msgType === 'warning' ? 'bg-amber-50 border border-amber-200 text-amber-900' : 'bg-emerald-50 border border-emerald-200 text-emerald-900' ?> text-xs sm:text-sm flex items-center justify-between shadow-sm animate-fade-in">
            <div class="flex items-center gap-2.5">
                <i data-lucide="<?= $msgType === 'warning' ? 'alert-circle' : 'check-circle' ?>" class="w-5 h-5 <?= $msgType === 'warning' ? 'text-amber-600' : 'text-emerald-600' ?> shrink-0"></i>
                <span class="font-medium"><?= htmlspecialchars($msg) ?></span>
            </div>
            <a href="admin.php?tab=<?= urlencode($activeTab) ?>" class="text-xs font-semibold underline text-gray-500 hover:text-gray-800">ปิดข้อความ</a>
        </div>
        <?php endif; ?>

        <!-- Key Metrics Cards Row (5 Columns on PC) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
            
            <!-- Card 1: Pending Pins (High Priority Alert) -->
            <a href="admin.php?tab=pins" class="bg-white p-5 rounded-3xl border <?= $totalPendingPins > 0 ? 'border-amber-400 ring-2 ring-amber-400/20 bg-amber-50/20' : 'border-gray-200/80' ?> shadow-sm hover:shadow-md transition flex items-center justify-between group">
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs font-bold text-amber-900 uppercase tracking-wider">คิวรออนุมัติพิกัด</span>
                        <?php if ($totalPendingPins > 0): ?>
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-ping"></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-3xl font-black text-amber-600 mt-1"><?= $totalPendingPins ?> <span class="text-xs font-normal text-gray-500">คำขอ</span></p>
                    <span class="text-[11px] text-gray-400 mt-0.5 block">อนุมัติแล้ว <?= $totalApprovedPins ?> จุด</span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center group-hover:scale-110 transition shadow-sm">
                    <i data-lucide="clock" class="w-6 h-6"></i>
                </div>
            </a>

            <!-- Card 2: Total Registered Users & Breakdown -->
            <a href="admin.php?tab=users" class="bg-white p-5 rounded-3xl border border-gray-200/80 shadow-sm hover:shadow-md transition flex items-center justify-between group">
                <div>
                    <span class="text-xs font-bold text-gray-600 uppercase tracking-wider">สมาชิกทั้งหมด</span>
                    <p class="text-3xl font-black text-gray-900 mt-1"><?= $totalUsers ?> <span class="text-xs font-normal text-gray-500">คน</span></p>
                    <span class="text-[11px] text-gray-400 mt-0.5 block">ช่าง <?= $totalMakers ?> | หมอแคน <?= $totalPlayers ?> | ร้าน <?= $totalShops ?></span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:scale-110 transition">
                    <i data-lucide="users" class="w-6 h-6"></i>
                </div>
            </a>

            <!-- Card 3: Verified Badges -->
            <div class="bg-white p-5 rounded-3xl border border-gray-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-gray-600 uppercase tracking-wider">ยืนยันตัวตนแล้ว</span>
                    <p class="text-3xl font-black text-blue-700 mt-1"><?= $totalVerified ?> <span class="text-xs font-normal text-gray-500">คน</span></p>
                    <span class="text-[11px] text-gray-400 mt-0.5 block">คิดเป็น <?= $totalUsers > 0 ? round(($totalVerified / $totalUsers) * 100, 1) : 0 ?>% ของสมาชิก</span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center">
                    <i data-lucide="badge-check" class="w-6 h-6"></i>
                </div>
            </div>

            <!-- Card 4: Marketplace Items -->
            <a href="admin.php?tab=market" class="bg-white p-5 rounded-3xl border border-gray-200/80 shadow-sm hover:shadow-md transition flex items-center justify-between group">
                <div>
                    <span class="text-xs font-bold text-gray-600 uppercase tracking-wider">สินค้าในตลาด</span>
                    <p class="text-3xl font-black text-orange-700 mt-1"><?= $totalItems ?> <span class="text-xs font-normal text-gray-500">ชิ้น</span></p>
                    <span class="text-[11px] text-gray-400 mt-0.5 block">พร้อมขาย <?= $totalAvailableItems ?> | ขายแล้ว <?= $totalSoldItems ?></span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center group-hover:scale-110 transition">
                    <i data-lucide="shopping-bag" class="w-6 h-6"></i>
                </div>
            </a>

            <!-- Card 5: Marketplace Total Active Value -->
            <div class="bg-white p-5 rounded-3xl border border-gray-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-gray-600 uppercase tracking-wider">มูลค่าตลาดพร้อมขาย</span>
                    <p class="text-2xl font-black text-emerald-700 mt-1">฿<?= number_format($marketVolumeVal, 0) ?></p>
                    <span class="text-[11px] text-gray-400 mt-0.5 block">ยอดรวมสินค้าสถานะพร้อมจำหน่าย</span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i data-lucide="coins" class="w-6 h-6"></i>
                </div>
            </div>
        </div>

        <!-- Toolbar Bar: Tabs on Left + Global Action Buttons on Right -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
            <!-- Navigation Tabs -->
            <div class="bg-white p-1.5 rounded-2xl border border-gray-200/80 shadow-sm inline-flex flex-wrap gap-1">
                <a href="admin.php?tab=pins" class="px-5 py-2.5 rounded-xl text-xs font-semibold transition flex items-center gap-2 <?= $activeTab === 'pins' ? 'bg-isan-indigo text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' ?>">
                    <i data-lucide="map-pin" class="w-4 h-4"></i>
                    <span>คิวอนุมัติพิกัด GPS</span>
                    <?php if ($totalPendingPins > 0): ?>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-white"><?= $totalPendingPins ?></span>
                    <?php endif; ?>
                </a>

                <a href="admin.php?tab=users" class="px-5 py-2.5 rounded-xl text-xs font-semibold transition flex items-center gap-2 <?= $activeTab === 'users' ? 'bg-isan-indigo text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' ?>">
                    <i data-lucide="users" class="w-4 h-4"></i>
                    <span>จัดการสมาชิกทั้งหมด (<?= $totalUsers ?>)</span>
                </a>

                <a href="admin.php?tab=market" class="px-5 py-2.5 rounded-xl text-xs font-semibold transition flex items-center gap-2 <?= $activeTab === 'market' ? 'bg-isan-indigo text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' ?>">
                    <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                    <span>จัดการประกาศในตลาด (<?= $totalItems ?>)</span>
                </a>

                <a href="admin.php?tab=overview" class="px-5 py-2.5 rounded-xl text-xs font-semibold transition flex items-center gap-2 <?= $activeTab === 'overview' ? 'bg-isan-indigo text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' ?>">
                    <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
                    <span>ภาพรวม & รายงาน</span>
                </a>
            </div>

            <!-- Global Action Controls (Add User, Export CSV) -->
            <div class="flex items-center gap-2">
                <button type="button" onclick="openUserModal()" class="px-4 py-2.5 bg-amber-700 hover:bg-amber-800 text-white rounded-xl text-xs font-semibold flex items-center gap-2 shadow-sm transition">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    <span>เพิ่มสมาชิกใหม่</span>
                </button>
                
                <?php if ($activeTab === 'users'): ?>
                <a href="admin.php?export=users" class="px-3.5 py-2.5 bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm transition" title="ดาวน์โหลดเป็นไฟล์ Excel CSV">
                    <i data-lucide="download" class="w-4 h-4 text-emerald-600"></i>
                    <span>Export สมาชิก (CSV)</span>
                </a>
                <?php elseif ($activeTab === 'market'): ?>
                <a href="admin.php?export=market" class="px-3.5 py-2.5 bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm transition" title="ดาวน์โหลดเป็นไฟล์ Excel CSV">
                    <i data-lucide="download" class="w-4 h-4 text-orange-600"></i>
                    <span>Export สินค้า (CSV)</span>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB 1: PENDING PIN APPROVAL QUEUE -->
        <?php if ($activeTab === 'pins'): ?>
        <div class="bg-white rounded-3xl border border-gray-200/80 shadow-sm overflow-hidden mb-8">
            <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <i data-lucide="clock" class="w-5 h-5 text-amber-600"></i> คิวขอปักหมุดที่รอการอนุมัติ (Pending Pins Queue)
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">ผู้ใช้ที่ร้องขอการปักหมุดใหม่หรืออัปเดตพิกัด จะไม่แสดงบนแผนที่สาธารณะจนกว่าแอดมินจะตรวจสอบและกดยืนยัน</p>
                </div>
                <div class="text-xs font-semibold text-gray-500 bg-amber-50 px-3.5 py-1.5 rounded-xl border border-amber-200">
                    รอตรวจสอบทั้งหมด: <span class="text-amber-800 font-bold"><?= count($pendingPinsList) ?></span> รายการ
                </div>
            </div>

            <?php if (count($pendingPinsList) === 0): ?>
            <div class="py-20 text-center text-gray-400">
                <div class="w-16 h-16 rounded-3xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3 shadow-sm">
                    <i data-lucide="check-check" class="w-8 h-8"></i>
                </div>
                <h3 class="font-bold text-gray-800 text-sm">ไม่มีพิกัดที่ค้างรอการอนุมัติ</h3>
                <p class="text-xs text-gray-400 mt-1">คำขอปักหมุดทั้งหมดในระบบได้รับการจัดการเรียบร้อยแล้ว หมุดแผนที่สาธารณะมีความแม่นยำ 100%</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50/80 text-[11px] text-gray-500 uppercase border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-4">ผู้ยื่นคำขอ</th>
                            <th class="px-6 py-4">บทบาทในระบบ</th>
                            <th class="px-6 py-4">จังหวัด & ที่อยู่</th>
                            <th class="px-6 py-4">พิกัด GPS (Lat, Lng)</th>
                            <th class="px-6 py-4">ตรวจสอบพิกัด</th>
                            <th class="px-6 py-4 text-right">ดำเนินการอนุมัติ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($pendingPinsList as $p): ?>
                        <tr class="hover:bg-amber-50/20 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <img src="<?= htmlspecialchars($p['avatar']) ?>" class="w-11 h-11 rounded-2xl object-cover border border-amber-200 shadow-sm shrink-0">
                                    <div>
                                        <div class="font-bold text-gray-900"><?= htmlspecialchars($p['name']) ?></div>
                                        <div class="text-xs text-gray-400 flex items-center gap-1.5 mt-0.5">
                                            <span><?= htmlspecialchars($p['email']) ?></span>
                                            <span>•</span>
                                            <span>โทร: <?= htmlspecialchars($p['phone'] ?: '-') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($p['category'] === 'maker'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                        <i data-lucide="hammer" class="w-3 h-3"></i> ช่างทำแคน
                                    </span>
                                <?php elseif ($p['category'] === 'player'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                        <i data-lucide="music" class="w-3 h-3"></i> หมอแคน
                                    </span>
                                <?php elseif ($p['category'] === 'learning_center'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                        <i data-lucide="graduation-cap" class="w-3 h-3"></i> ศูนย์การเรียนรู้แคน
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-800">
                                        <i data-lucide="store" class="w-3 h-3"></i> ร้านขายแคน
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-bold text-gray-900 block"><?= htmlspecialchars($p['province']) ?></span>
                                <span class="text-xs text-gray-400"><?= htmlspecialchars($p['address'] ?: 'ไม่ได้ระบุที่อยู่ละเอียด') ?></span>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-gray-700">
                                <?= number_format($p['latitude'], 6) ?>, <?= number_format($p['longitude'], 6) ?>
                            </td>
                            <td class="px-6 py-4">
                                <button type="button" onclick="inspectPinOnMap(<?= (float)$p['latitude'] ?>, <?= (float)$p['longitude'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>', '<?= htmlspecialchars(addslashes($p['province'])) ?>')" class="inline-flex items-center gap-1.5 text-xs text-blue-700 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-xl border border-blue-200 transition font-medium">
                                    <i data-lucide="map" class="w-3.5 h-3.5"></i> ตรวจสอบบนแผนที่
                                </button>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="admin.php?tab=pins&action=approve_pin&id=<?= $p['id'] ?>" class="inline-flex items-center gap-1 text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-3 py-1.5 rounded-xl transition shadow-sm">
                                        <i data-lucide="check" class="w-3.5 h-3.5"></i> อนุมัติพิกัด
                                    </a>
                                    <a href="admin.php?tab=pins&action=reject_pin&id=<?= $p['id'] ?>" onclick="return confirm('ยืนยันปฏิเสธพิกัดนี้หรือไม่?')" class="inline-flex items-center gap-1 text-xs bg-red-100 hover:bg-red-200 text-red-700 font-semibold px-3 py-1.5 rounded-xl transition">
                                        <i data-lucide="x" class="w-3.5 h-3.5"></i> ปฏิเสธ
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- TAB 2: COMPLETE USER MANAGEMENT -->
        <?php if ($activeTab === 'users'): ?>
        <div class="bg-white rounded-3xl border border-gray-200/80 shadow-sm overflow-hidden mb-8">
            <div class="p-6 border-b border-gray-100 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <i data-lucide="users" class="w-5 h-5 text-blue-600"></i> ทำเนียบสมาชิกและหมอแคนทั่วประเทศ (<?= count($users) ?> ท่าน)
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">แก้ไขข้อมูลส่วนตัว, ติดตั้งเครื่องหมาย Verified Badge สีฟ้า, และจัดการสิทธิ์บัญชี</p>
                </div>

                <!-- Filter & Search Toolbar -->
                <form method="GET" action="admin.php" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="tab" value="users">
                    <div class="relative">
                        <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                        <input type="text" name="user_search" value="<?= htmlspecialchars($userSearch) ?>" placeholder="ค้นหาชื่อ, อีเมล, จังหวัด, ฉายา..." class="text-xs pl-8 pr-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none w-48 sm:w-60">
                    </div>
                    <select name="user_category" onchange="this.form.submit()" class="text-xs px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white">
                        <option value="all" <?= $userCategoryFilter === 'all' ? 'selected' : '' ?>>ทุกบทบาท</option>
                        <option value="maker" <?= $userCategoryFilter === 'maker' ? 'selected' : '' ?>>ช่างทำแคน (<?= $totalMakers ?>)</option>
                        <option value="player" <?= $userCategoryFilter === 'player' ? 'selected' : '' ?>>หมอแคน (<?= $totalPlayers ?>)</option>
                        <option value="shop" <?= $userCategoryFilter === 'shop' ? 'selected' : '' ?>>ร้านขายแคน (<?= $totalShops ?>)</option>
                        <option value="learning_center" <?= $userCategoryFilter === 'learning_center' ? 'selected' : '' ?>>ศูนย์การเรียนรู้แคน (<?= $totalLearningCenters ?>)</option>
                    </select>
                    <select name="user_status" onchange="this.form.submit()" class="text-xs px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white">
                        <option value="all" <?= $userStatusFilter === 'all' ? 'selected' : '' ?>>ทุกสถานะบัญชี</option>
                        <option value="active" <?= $userStatusFilter === 'active' ? 'selected' : '' ?>>เปิดใช้งานปกติ</option>
                        <option value="suspended" <?= $userStatusFilter === 'suspended' ? 'selected' : '' ?>>ถูกระงับการใช้งาน</option>
                    </select>
                    <button type="submit" class="px-3.5 py-2 bg-gray-900 hover:bg-black text-white rounded-xl text-xs font-semibold">ค้นหา</button>
                    <?php if (!empty($userSearch) || $userCategoryFilter !== 'all' || $userStatusFilter !== 'all'): ?>
                    <a href="admin.php?tab=users" class="px-2.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl text-xs" title="ล้างตัวกรอง">ล้าง</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50/80 text-[11px] text-gray-500 uppercase border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-4">ข้อมูลสมาชิก & ฉายา</th>
                            <th class="px-6 py-4">บทบาท</th>
                            <th class="px-6 py-4">จังหวัด</th>
                            <th class="px-6 py-4">สถานะพิกัด GPS</th>
                            <th class="px-6 py-4">ยืนยันตัวตน (Verified)</th>
                            <th class="px-6 py-4">สถานะบัญชี</th>
                            <th class="px-6 py-4 text-right">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-gray-50/40 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <img src="<?= htmlspecialchars($u['avatar']) ?>" class="w-11 h-11 rounded-2xl object-cover border border-gray-200 shadow-sm shrink-0">
                                    <div>
                                        <div class="font-bold text-gray-900 flex items-center gap-1.5">
                                            <?= htmlspecialchars($u['name']) ?>
                                            <?php if ($u['is_verified']): ?>
                                            <i data-lucide="badge-check" class="w-4 h-4 text-blue-500 fill-blue-50" title="ยืนยันตัวตนแล้ว"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars($u['email']) ?> | โทร: <?= htmlspecialchars($u['phone'] ?: '-') ?></div>
                                        <?php if (!empty($u['title_desc'])): ?>
                                        <div class="text-[11px] text-amber-800 line-clamp-1 mt-0.5 font-medium"><?= htmlspecialchars($u['title_desc']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($u['category'] === 'maker'): ?>
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">ช่างทำแคน</span>
                                <?php elseif ($u['category'] === 'player'): ?>
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">หมอแคน</span>
                                <?php elseif ($u['category'] === 'learning_center'): ?>
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">ศูนย์การเรียนรู้แคน</span>
                                <?php else: ?>
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-800">ร้านขายแคน</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-bold text-gray-900 block"><?= htmlspecialchars($u['province']) ?></span>
                                <span class="text-xs text-gray-400"><?= htmlspecialchars($u['address'] ?: '-') ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <?php $ps = $u['pin_status'] ?? 'none'; ?>
                                <?php if ($ps === 'approved'): ?>
                                    <div class="flex items-center gap-1.5">
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200">
                                            <i data-lucide="check" class="w-3.5 h-3.5"></i> แสดงบนแผนที่
                                        </span>
                                        <button type="button" onclick="inspectPinOnMap(<?= (float)$u['latitude'] ?>, <?= (float)$u['longitude'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>', '<?= htmlspecialchars(addslashes($u['province'])) ?>')" class="text-gray-400 hover:text-blue-600 p-1" title="ดูหมุด">
                                            <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </div>
                                <?php elseif ($ps === 'pending'): ?>
                                    <div class="flex items-center gap-1.5">
                                        <span class="inline-flex items-center gap-1 text-xs font-bold text-amber-800 bg-amber-100 px-2.5 py-1 rounded-lg border border-amber-200 animate-pulse">
                                            <i data-lucide="clock" class="w-3 h-3"></i> รออนุมัติ
                                        </span>
                                        <a href="admin.php?tab=users&action=approve_pin&id=<?= $u['id'] ?>" class="text-[10px] bg-emerald-600 hover:bg-emerald-700 text-white px-2 py-1 rounded-md font-bold shadow-sm">อนุมัติ</a>
                                    </div>
                                <?php elseif ($ps === 'rejected'): ?>
                                    <span class="text-xs text-red-600 bg-red-50 px-2.5 py-1 rounded-lg border border-red-200">ถูกปฏิเสธ</span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">ยังไม่ปักหมุด</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($u['is_verified']): ?>
                                    <span class="inline-flex items-center gap-1 text-xs text-blue-700 bg-blue-50 px-2.5 py-1 rounded-lg font-medium border border-blue-200">
                                        <i data-lucide="badge-check" class="w-3.5 h-3.5 text-blue-600"></i> ยืนยันแล้ว
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400 bg-gray-100 px-2.5 py-1 rounded-lg">ยังไม่ยืนยัน</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($u['status'] === 'active'): ?>
                                    <span class="inline-flex items-center gap-1 text-xs text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md font-medium">ปกติ</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-xs text-red-700 bg-red-100 px-2 py-0.5 rounded-md font-bold">ถูกระงับ</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <!-- Edit User Modal Button -->
                                    <button type="button" onclick='editUserModal(<?= json_encode($u) ?>)' class="text-xs text-gray-700 hover:text-amber-800 bg-gray-50 hover:bg-amber-50 px-2.5 py-1.5 border rounded-xl transition flex items-center gap-1 font-semibold" title="แก้ไขข้อมูล">
                                        <i data-lucide="edit-3" class="w-3.5 h-3.5"></i> แก้ไข
                                    </button>

                                    <!-- Toggle Verified Badge -->
                                    <?php if ($u['is_verified']): ?>
                                        <a href="admin.php?tab=users&action=unverify_user&id=<?= $u['id'] ?>" class="text-xs text-gray-500 hover:text-gray-700 px-2.5 py-1.5 border rounded-xl transition" title="ปลดเครื่องหมายยืนยันตัวตน">ปลด Verify</a>
                                    <?php else: ?>
                                        <a href="admin.php?tab=users&action=verify_user&id=<?= $u['id'] ?>" class="text-xs bg-blue-600 text-white hover:bg-blue-700 px-2.5 py-1.5 rounded-xl font-semibold transition shadow-sm" title="อนุมัติเครื่องหมายยืนยันตัวตน">ให้ Verify</a>
                                    <?php endif; ?>

                                    <!-- Toggle Suspended -->
                                    <?php if ($u['id'] !== (int)$currentUser['id']): ?>
                                        <?php if ($u['status'] === 'active'): ?>
                                            <a href="admin.php?tab=users&action=toggle_user_status&id=<?= $u['id'] ?>&status=suspended" onclick="return confirm('ยืนยันระงับบัญชีสมาชิกนี้หรือไม่?')" class="text-xs text-amber-600 hover:bg-amber-50 p-2 rounded-xl transition border border-amber-200" title="ระงับบัญชี">
                                                <i data-lucide="ban" class="w-3.5 h-3.5"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="admin.php?tab=users&action=toggle_user_status&id=<?= $u['id'] ?>&status=active" class="text-xs text-emerald-600 hover:bg-emerald-50 p-2 rounded-xl transition border border-emerald-200" title="ปลดการระงับ">
                                                <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                            </a>
                                        <?php endif; ?>

                                        <!-- Delete User -->
                                        <a href="admin.php?tab=users&action=delete_user&id=<?= $u['id'] ?>" onclick="return confirm('ยืนยันลบผู้ใช้งานนี้อย่างถาวรหรือไม่? สินค้าในตลาดทั้งหมดของสมาชิกนี้จะถูกลบด้วย')" class="text-xs text-red-600 hover:bg-red-50 p-2 rounded-xl transition border border-red-200" title="ลบสมาชิก">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- TAB 3: COMPLETE MARKETPLACE MODERATION -->
        <?php if ($activeTab === 'market'): ?>
        <div class="bg-white rounded-3xl border border-gray-200/80 shadow-sm overflow-hidden mb-8">
            <div class="p-6 border-b border-gray-100 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <i data-lucide="shopping-bag" class="w-5 h-5 text-orange-600"></i> จัดการประกาศสินค้าในตลาดแคน (<?= count($items) ?> รายการ)
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">ตรวจสอบความถูกต้องของสเปกเครื่องดนตรี, ราคา, ปรับสถานะขายแล้ว, หรือลบประกาศที่ไม่เหมาะสม</p>
                </div>

                <!-- Search & Status Filter -->
                <form method="GET" action="admin.php" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="tab" value="market">
                    <div class="relative">
                        <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                        <input type="text" name="item_search" value="<?= htmlspecialchars($itemSearch) ?>" placeholder="ค้นหาชื่อสินค้า, คีย์, ผู้ขาย..." class="text-xs pl-8 pr-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500 focus:outline-none w-52 sm:w-64">
                    </div>
                    <select name="item_status" onchange="this.form.submit()" class="text-xs px-3 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500 focus:outline-none bg-white">
                        <option value="all" <?= $itemStatusFilter === 'all' ? 'selected' : '' ?>>ทุกสถานะสินค้า</option>
                        <option value="available" <?= $itemStatusFilter === 'available' ? 'selected' : '' ?>>พร้อมขาย (<?= $totalAvailableItems ?>)</option>
                        <option value="sold" <?= $itemStatusFilter === 'sold' ? 'selected' : '' ?>>ขายแล้ว (<?= $totalSoldItems ?>)</option>
                    </select>
                    <button type="submit" class="px-3.5 py-2 bg-gray-900 hover:bg-black text-white rounded-xl text-xs font-semibold">ค้นหา</button>
                    <?php if (!empty($itemSearch) || $itemStatusFilter !== 'all'): ?>
                    <a href="admin.php?tab=market" class="px-2.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl text-xs" title="ล้างตัวกรอง">ล้าง</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50/80 text-[11px] text-gray-500 uppercase border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-4">ข้อมูลสินค้า</th>
                            <th class="px-6 py-4">สเปกเครื่องดนตรี</th>
                            <th class="px-6 py-4">ผู้ขาย & ช่องทางติดต่อ</th>
                            <th class="px-6 py-4">ราคา & ค่าส่ง</th>
                            <th class="px-6 py-4">สถานะการขาย</th>
                            <th class="px-6 py-4 text-right">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($items as $it): ?>
                        <tr class="hover:bg-gray-50/40 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3.5">
                                    <img src="<?= htmlspecialchars($it['image_url']) ?>" class="w-14 h-14 rounded-2xl object-cover border border-gray-200 shadow-sm shrink-0">
                                    <div>
                                        <div class="font-bold text-gray-900 leading-snug line-clamp-1"><?= htmlspecialchars($it['title']) ?></div>
                                        <div class="text-xs text-gray-400 mt-0.5">
                                            <?= htmlspecialchars($it['condition_status'] === 'new' ? 'ของใหม่มือหนึ่ง' : 'สินค้ามือสอง') ?> • 
                                            ลงขาย: <?= date('d/m/Y', strtotime($it['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-xs">
                                <div class="space-y-1">
                                    <div><span class="text-gray-400">คีย์:</span> <strong class="text-isan-indigo font-bold">คีย์ <?= htmlspecialchars($it['key_note']) ?></strong></div>
                                    <div><span class="text-gray-400">ไม้:</span> <?= htmlspecialchars($it['wood_type'] ?? 'ไม้กู่แคน') ?></div>
                                    <div><span class="text-gray-400">ลิ้น:</span> <?= htmlspecialchars($it['reed_type'] ?? 'ลิ้นเงิน/ทองเหลือง') ?></div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-bold text-gray-900 block"><?= htmlspecialchars($it['seller_name']) ?></span>
                                <span class="text-xs text-gray-400">จ.<?= htmlspecialchars($it['seller_province']) ?> | <?= htmlspecialchars($it['seller_phone'] ?: '-') ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-base font-extrabold text-isan-terracotta block">฿<?= number_format($it['price'], 0) ?></span>
                                <span class="text-[11px] text-gray-400">ค่าส่ง ฿<?= number_format($it['shipping_cost'] ?? 0, 0) ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($it['status'] === 'available'): ?>
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">พร้อมส่ง</span>
                                <?php else: ?>
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-200 text-gray-700">ขายแล้ว</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- Toggle Sold Status -->
                                    <?php if ($it['status'] === 'available'): ?>
                                        <a href="admin.php?tab=market&action=toggle_item_status&id=<?= $it['id'] ?>&status=sold" class="text-xs text-gray-600 hover:bg-gray-100 px-2.5 py-1.5 rounded-xl border transition font-medium">ตั้งเป็นขายแล้ว</a>
                                    <?php else: ?>
                                        <a href="admin.php?tab=market&action=toggle_item_status&id=<?= $it['id'] ?>&status=available" class="text-xs text-emerald-700 hover:bg-emerald-50 px-2.5 py-1.5 rounded-xl border border-emerald-200 transition font-medium">ตั้งเป็นพร้อมขาย</a>
                                    <?php endif; ?>

                                    <!-- Delete Item -->
                                    <a href="admin.php?tab=market&action=delete_item&id=<?= $it['id'] ?>" onclick="return confirm('ยืนยันลบสินค้านี้ออกจากตลาดหรือไม่?')" class="text-xs text-red-600 hover:bg-red-50 p-2 rounded-xl transition border border-red-200" title="ลบประกาศ">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- TAB 4: SYSTEM OVERVIEW & PROVINCES BREAKDOWN -->
        <?php if ($activeTab === 'overview'): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Col 1: Top Provinces Distribution -->
            <div class="bg-white p-6 rounded-3xl border border-gray-200/80 shadow-sm">
                <h3 class="font-bold text-gray-900 text-base flex items-center gap-2 mb-4">
                    <i data-lucide="map-pinned" class="w-5 h-5 text-amber-700"></i> จังหวัดที่มีสมาชิกมากที่สุด
                </h3>
                <div class="space-y-3.5">
                    <?php foreach ($topProvinces as $pv): ?>
                    <div>
                        <div class="flex justify-between text-xs font-semibold mb-1">
                            <span class="text-gray-700"><?= htmlspecialchars($pv['province']) ?></span>
                            <span class="text-gray-500"><?= $pv['cnt'] ?> ท่าน</span>
                        </div>
                        <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                            <div class="bg-amber-600 h-full rounded-full" style="width: <?= $totalUsers > 0 ? min(100, round(($pv['cnt'] / $totalUsers) * 100)) : 0 ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Col 2: Category Ratios -->
            <div class="bg-white p-6 rounded-3xl border border-gray-200/80 shadow-sm">
                <h3 class="font-bold text-gray-900 text-base flex items-center gap-2 mb-4">
                    <i data-lucide="pie-chart" class="w-5 h-5 text-blue-600"></i> สัดส่วนบทบาทในชุมชน
                </h3>
                <div class="space-y-4">
                    <div class="p-3 bg-amber-50 rounded-2xl border border-amber-100 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold text-xs"><i data-lucide="hammer" class="w-4 h-4"></i></div>
                            <div>
                                <h4 class="text-xs font-bold text-gray-900">ช่างทำแคน (Makers)</h4>
                                <span class="text-[11px] text-gray-500"><?= $totalMakers ?> ท่าน</span>
                            </div>
                        </div>
                        <span class="text-xs font-black text-amber-800"><?= $totalUsers > 0 ? round(($totalMakers / $totalUsers) * 100, 1) : 0 ?>%</span>
                    </div>

                    <div class="p-3 bg-blue-50 rounded-2xl border border-blue-100 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold text-xs"><i data-lucide="music" class="w-4 h-4"></i></div>
                            <div>
                                <h4 class="text-xs font-bold text-gray-900">หมอแคน (Players)</h4>
                                <span class="text-[11px] text-gray-500"><?= $totalPlayers ?> ท่าน</span>
                            </div>
                        </div>
                        <span class="text-xs font-black text-blue-800"><?= $totalUsers > 0 ? round(($totalPlayers / $totalUsers) * 100, 1) : 0 ?>%</span>
                    </div>

                    <div class="p-3 bg-orange-50 rounded-2xl border border-orange-100 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-orange-600 text-white flex items-center justify-center font-bold text-xs"><i data-lucide="store" class="w-4 h-4"></i></div>
                            <div>
                                <h4 class="text-xs font-bold text-gray-900">ร้านขายแคน (Shops)</h4>
                                <span class="text-[11px] text-gray-500"><?= $totalShops ?> ร้าน</span>
                            </div>
                        </div>
                        <span class="text-xs font-black text-orange-800"><?= $totalUsers > 0 ? round(($totalShops / $totalUsers) * 100, 1) : 0 ?>%</span>
                    </div>

                    <div class="p-3 bg-emerald-50 rounded-2xl border border-emerald-100 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-xs"><i data-lucide="graduation-cap" class="w-4 h-4"></i></div>
                            <div>
                                <h4 class="text-xs font-bold text-gray-900">ศูนย์การเรียนรู้แคน (Learning)</h4>
                                <span class="text-[11px] text-gray-500"><?= $totalLearningCenters ?> แห่ง</span>
                            </div>
                        </div>
                        <span class="text-xs font-black text-emerald-800"><?= $totalUsers > 0 ? round(($totalLearningCenters / $totalUsers) * 100, 1) : 0 ?>%</span>
                    </div>
                </div>
            </div>

            <!-- Col 3: System Health & Security Status -->
            <div class="bg-white p-6 rounded-3xl border border-gray-200/80 shadow-sm flex flex-col justify-between">
                <div>
                    <h3 class="font-bold text-gray-900 text-base flex items-center gap-2 mb-3">
                        <i data-lucide="shield" class="w-5 h-5 text-emerald-600"></i> สุขภาพระบบและความปลอดภัย
                    </h3>
                    <ul class="text-xs space-y-2.5 text-gray-600 mt-4">
                        <li class="flex items-center gap-2">
                            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-500"></i>
                            <span>ระบบพิกัดเชื่อมต่อ Leaflet & OpenStreetMap พร้อมใช้งาน</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-500"></i>
                            <span>ฐานข้อมูล PDO MySQL เชื่อมต่อ UTF-8 สำเร็จ</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-500"></i>
                            <span>การกรองสิทธิ์ Admin Guard ตรวจสอบเซสชันอัตโนมัติ</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-500"></i>
                            <span>คัดกรองหมุดแปลกปลอมด้วยระบบ Pin Moderation Approval</span>
                        </li>
                    </ul>
                </div>

                <div class="pt-6 border-t border-gray-100 flex items-center justify-between text-xs text-gray-400">
                    <span>Khaen Complex Center Engine v2.0</span>
                    <span class="text-emerald-600 font-semibold flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span> พร้อมให้บริการ
                    </span>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <!-- Modal for Inspecting Coordinate on Map -->
    <div id="inspect-map-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden animate-fade-in">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-gray-100">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center">
                        <i data-lucide="map-pin" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-gray-900 leading-tight">ตรวจสอบพิกัด: <span id="inspect-title" class="text-isan-indigo"></span></h3>
                        <span id="inspect-province" class="text-[11px] text-gray-400"></span>
                    </div>
                </div>
                <button type="button" onclick="closeInspectMapModal()" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="mt-4 border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                <div id="inspect-map-container" style="height: 320px; width: 100%;"></div>
            </div>

            <div class="mt-4 flex items-center justify-between text-xs text-gray-500">
                <span id="inspect-coords" class="font-mono bg-gray-50 px-2.5 py-1 rounded-lg border"></span>
                <button type="button" onclick="closeInspectMapModal()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <!-- Modal for Create / Edit User -->
    <div id="user-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden animate-fade-in">
        <div class="bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border border-gray-100 max-h-[90vh] overflow-y-auto custom-scrollbar">
            <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center">
                        <i data-lucide="user-cog" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 id="user-modal-title" class="font-bold text-base text-gray-900">เพิ่มสมาชิกใหม่</h3>
                        <p class="text-xs text-gray-400">กรอกข้อมูลสมาชิกและกำหนดพิกัดสำหรับปักหมุด</p>
                    </div>
                </div>
                <button type="button" onclick="closeUserModal()" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" action="admin.php" class="mt-6 space-y-4">
                <input type="hidden" name="save_user" value="1">
                <input type="hidden" name="user_id" id="form-user-id" value="0">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">ชื่อ-นามสกุล หรือชื่อร้าน/ฉายา <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="form-name" required class="w-full text-xs px-3.5 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">อีเมล <span class="text-red-500">*</span></label>
                        <input type="email" name="email" id="form-email" required class="w-full text-xs px-3.5 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">เบอร์โทรศัพท์</label>
                        <input type="text" name="phone" id="form-phone" class="w-full text-xs px-3.5 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">บทบาทในระบบ</label>
                        <select name="category" id="form-category" class="w-full text-xs px-3.5 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                            <option value="maker">ช่างทำแคน (Maker)</option>
                            <option value="player">หมอแคน (Player)</option>
                            <option value="shop">ร้านขายแคน (Shop)</option>
                            <option value="learning_center">ศูนย์การเรียนรู้แคน (Learning Center)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">ฉายา หรือความเชี่ยวชาญสั้นๆ</label>
                    <input type="text" name="title_desc" id="form-title-desc" placeholder="เช่น ปรมาจารย์ช่างแคนลายโบราณ 40 ปี" class="w-full text-xs px-3.5 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">จังหวัด</label>
                        <select name="province" id="form-province" class="w-full text-xs px-3.5 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                            <option value="ขอนแก่น">ขอนแก่น</option>
                            <option value="ร้อยเอ็ด">ร้อยเอ็ด</option>
                            <option value="กาฬสินธุ์">กาฬสินธุ์</option>
                            <option value="มหาสารคาม">มหาสารคาม</option>
                            <option value="อุบลราชธานี">อุบลราชธานี</option>
                            <option value="อุดรธานี">อุดรธานี</option>
                            <option value="สกลนคร">สกลนคร</option>
                            <option value="นครพนม">นครพนม</option>
                            <option value="ยโสธร">ยโสธร</option>
                            <option value="มุกดาหาร">มุกดาหาร</option>
                            <option value="ศรีสะเกษ">ศรีสะเกษ</option>
                            <option value="สุรินทร์">สุรินทร์</option>
                            <option value="บุรีรัมย์">บุรีรัมย์</option>
                            <option value="นครราชสีมา">นครราชสีมา</option>
                            <option value="กรุงเทพมหานคร">กรุงเทพมหานคร</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">ที่อยู่รายละเอียด</label>
                        <input type="text" name="address" id="form-address" placeholder="ตึก, ซอย, ตำบล, อำเภอ" class="w-full text-xs px-3.5 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                </div>

                <div class="p-4 bg-amber-50/60 rounded-2xl border border-amber-200/60 space-y-3">
                    <h4 class="text-xs font-bold text-amber-900 flex items-center gap-1.5">
                        <i data-lucide="map-pin" class="w-4 h-4 text-amber-700"></i> กำหนดพิกัดแผนที่ (Latitude, Longitude)
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-600 mb-1">ละติจูด (Latitude)</label>
                            <input type="number" step="any" name="latitude" id="form-lat" placeholder="เช่น 16.441900" class="w-full text-xs px-3 py-2 border border-gray-200 rounded-xl bg-white focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-600 mb-1">ลองจิจูด (Longitude)</label>
                            <input type="number" step="any" name="longitude" id="form-lng" placeholder="เช่น 102.798100" class="w-full text-xs px-3 py-2 border border-gray-200 rounded-xl bg-white focus:outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">สถานะหมุดบนแผนที่</label>
                        <select name="pin_status" id="form-pin-status" class="w-full text-xs px-3 py-2 border border-gray-200 rounded-xl bg-white focus:outline-none">
                            <option value="approved">อนุมัติแล้ว (แสดงบนแผนที่ทันที)</option>
                            <option value="pending">รอการอนุมัติ (Pending)</option>
                            <option value="none">ไม่มีพิกัด</option>
                            <option value="rejected">ปฏิเสธพิกัด</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="is_verified" id="form-verified" value="1" class="w-4 h-4 text-blue-600 rounded">
                        <label for="form-verified" class="text-xs font-bold text-gray-700">ยืนยันตัวตน (มอบ Verified Badge สีฟ้า)</label>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">สถานะบัญชี</label>
                        <select name="status" id="form-status" class="w-full text-xs px-3 py-2 border border-gray-200 rounded-xl bg-white">
                            <option value="active">เปิดใช้งานปกติ (Active)</option>
                            <option value="suspended">ระงับการใช้งาน (Suspended)</option>
                        </select>
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
                    <button type="button" onclick="closeUserModal()" class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition">ยกเลิก</button>
                    <button type="submit" class="px-5 py-2.5 bg-amber-700 hover:bg-amber-800 text-white rounded-xl text-xs font-semibold shadow-md transition">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer (Full screen width) -->
    <footer class="bg-gray-950 text-gray-400 py-6 border-t border-gray-900 mt-auto text-xs">
        <div class="w-full px-4 sm:px-6 lg:px-10 flex flex-col sm:flex-row justify-between items-center gap-3">
            <div>
                <strong class="text-white">Khaen Complex Center Admin Console</strong> — ระบบจัดการและควบคุมข้อมูลดนตรีอีสาน
            </div>
            <div>
                Security: Protected by Admin Session Guard • UTF-8 Unicode • Leaflet & OpenStreetMap
            </div>
        </div>
    </footer>

    <script>
        lucide.createIcons();

        // Inspect Map Modal
        let inspectMap = null;
        let inspectMarker = null;

        function inspectPinOnMap(lat, lng, name, province) {
            document.getElementById('inspect-title').textContent = name;
            document.getElementById('inspect-province').textContent = province ? `จ.${province}` : '';
            document.getElementById('inspect-coords').textContent = `พิกัด: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            document.getElementById('inspect-map-modal').classList.remove('hidden');

            setTimeout(() => {
                if (!inspectMap) {
                    inspectMap = L.map('inspect-map-container').setView([lat, lng], 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap'
                    }).addTo(inspectMap);
                } else {
                    inspectMap.setView([lat, lng], 13);
                    inspectMap.invalidateSize();
                }

                if (inspectMarker) {
                    inspectMap.removeLayer(inspectMarker);
                }

                inspectMarker = L.marker([lat, lng]).addTo(inspectMap)
                    .bindPopup(`<div class="p-2 text-xs font-bold text-gray-900">${name}</div>`)
                    .openPopup();
            }, 200);
        }

        function closeInspectMapModal() {
            document.getElementById('inspect-map-modal').classList.add('hidden');
        }

        // User Create / Edit Modal
        function openUserModal() {
            document.getElementById('user-modal-title').textContent = 'เพิ่มสมาชิกใหม่';
            document.getElementById('form-user-id').value = '0';
            document.getElementById('form-name').value = '';
            document.getElementById('form-email').value = '';
            document.getElementById('form-phone').value = '';
            document.getElementById('form-category').value = 'player';
            document.getElementById('form-title-desc').value = '';
            document.getElementById('form-province').value = 'ขอนแก่น';
            document.getElementById('form-address').value = '';
            document.getElementById('form-lat').value = '';
            document.getElementById('form-lng').value = '';
            document.getElementById('form-pin-status').value = 'approved';
            document.getElementById('form-verified').checked = false;
            document.getElementById('form-status').value = 'active';
            document.getElementById('user-modal').classList.remove('hidden');
        }

        function editUserModal(userData) {
            document.getElementById('user-modal-title').textContent = `แก้ไขข้อมูล: ${userData.name}`;
            document.getElementById('form-user-id').value = userData.id;
            document.getElementById('form-name').value = userData.name || '';
            document.getElementById('form-email').value = userData.email || '';
            document.getElementById('form-phone').value = userData.phone || '';
            document.getElementById('form-category').value = userData.category || 'player';
            document.getElementById('form-title-desc').value = userData.title_desc || '';
            document.getElementById('form-province').value = userData.province || 'ขอนแก่น';
            document.getElementById('form-address').value = userData.address || '';
            document.getElementById('form-lat').value = userData.latitude || '';
            document.getElementById('form-lng').value = userData.longitude || '';
            document.getElementById('form-pin-status').value = userData.pin_status || 'none';
            document.getElementById('form-verified').checked = !!userData.is_verified;
            document.getElementById('form-status').value = userData.status || 'active';
            document.getElementById('user-modal').classList.remove('hidden');
        }

        function closeUserModal() {
            document.getElementById('user-modal').classList.add('hidden');
        }
    </script>
</body>
</html>
