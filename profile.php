<?php
/**
 * User Profile Page - SoundRadar
 * Edit personal information, category (Maker/Player/Shop), bio, GPS coordinates on interactive map, and view personal listings
 */
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db.php';

$currentUser = getCurrentUser();

// Check if viewing another user's profile (?id=...) or preview mode
$viewId = isset($_GET['id']) ? (int)$_GET['id'] : ($currentUser ? (int)$currentUser['id'] : 1);
$isAccountOwner = $currentUser && ((int)$currentUser['id'] === $viewId);
$isPreviewMode = isset($_GET['preview']) && $isAccountOwner;
$isOwnProfile = $isAccountOwner && !$isPreviewMode;

$userId = $viewId;
$successMsg = '';
$errorMsg = '';

// Fetch Current Profile User Data first
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userData = $userStmt->fetch();

if (!$userData) {
    header("Location: index.php");
    exit;
}

// Handle Profile Update Submission (Only allow if editing own profile)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!$isOwnProfile) {
        $errorMsg = 'คุณไม่มีสิทธิ์แก้ไขข้อมูลโปรไฟล์ของผู้อื่น';
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // Handle multiple categories checklist
        $selectedCategories = $_POST['categories'] ?? [];
        if (!is_array($selectedCategories) || empty($selectedCategories)) {
            $selectedCategories = ['player'];
        }
        // Store as comma-separated or primary category
        $primaryCategory = $selectedCategories[0];
        $category = implode(',', $selectedCategories);
        
        $title_desc = trim($_POST['title_desc'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $province = trim($_POST['province'] ?? 'ขอนแก่น');
        $latitude = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

        // Handle avatar upload if provided
        $avatarUrl = $userData['avatar'] ?? 'assets/images/default-avatar.png';
        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileTmpPath = $_FILES['avatar_file']['tmp_name'];
            $fileName = $_FILES['avatar_file']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($fileExtension, $allowedExtensions)) {
                $newFileName = 'avatar_' . $userId . '_' . time() . '.' . $fileExtension;
                $destPath = $uploadDir . $newFileName;
                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $avatarUrl = 'uploads/' . $newFileName;
                }
            }
        }

        // Handle Workshop & Craft Showcase Images (ภาพถ่ายผลงานจริงและบรรยากาศศูนย์ทำแคน)
        $existingShowcase = [];
        if (!empty($userData['showcase_images'])) {
            $decoded = json_decode($userData['showcase_images'], true);
            if (is_array($decoded)) {
                $existingShowcase = $decoded;
            }
        }

        // Retain existing showcase images that user hasn't deleted
        if (isset($_POST['keep_showcase_images']) && is_array($_POST['keep_showcase_images'])) {
            $existingShowcase = array_values(array_intersect($existingShowcase, $_POST['keep_showcase_images']));
        } elseif (isset($_POST['has_showcase_section'])) {
            $existingShowcase = [];
        }

        // Upload new showcase images (up to 6)
        if (isset($_FILES['showcase_files']) && !empty($_FILES['showcase_files']['name'][0])) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            $fileCount = count($_FILES['showcase_files']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['showcase_files']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmp = $_FILES['showcase_files']['tmp_name'][$i];
                    $origName = $_FILES['showcase_files']['name'][$i];
                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    if (in_array($ext, $allowedExtensions)) {
                        $newSCName = 'showcase_' . $userId . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                        $dest = $uploadDir . $newSCName;
                        if (move_uploaded_file($tmp, $dest)) {
                            $existingShowcase[] = 'uploads/' . $newSCName;
                        }
                    }
                }
                if (count($existingShowcase) >= 6) {
                    break;
                }
            }
        }

        $showcaseJson = !empty($existingShowcase) ? json_encode(array_slice($existingShowcase, 0, 6), JSON_UNESCAPED_SLASHES) : null;

        if (empty($name)) {
            $errorMsg = 'กรุณากรอกชื่อ-นามสกุล หรือชื่อช่าง/ฉายาหมอแคน';
        } else {
            try {
                // Check if coordinates changed or provided
                $currPinStatus = $userData['pin_status'] ?? 'none';
                $newPinStatus = $currPinStatus;
                
                if ($latitude !== false && $longitude !== false) {
                    // If user is admin, approve immediately. Otherwise, set to pending approval!
                    if ($currentUser && $currentUser['role'] === 'admin') {
                        $newPinStatus = 'approved';
                    } elseif ($latitude != $userData['latitude'] || $longitude != $userData['longitude'] || $currPinStatus === 'none' || $currPinStatus === 'rejected') {
                        $newPinStatus = 'pending';
                    }
                }

                $updateStmt = $pdo->prepare("
                    UPDATE users SET 
                        name = :name,
                        phone = :phone,
                        category = :category,
                        title_desc = :title_desc,
                        bio = :bio,
                        address = :address,
                        province = :province,
                        latitude = :lat,
                        longitude = :lng,
                        pin_status = :pin_status,
                        avatar = :avatar,
                        showcase_images = :showcase_images
                    WHERE id = :id
                ");

                $updateStmt->execute([
                    ':name' => $name,
                    ':phone' => $phone,
                    ':category' => $category,
                    ':title_desc' => $title_desc,
                    ':bio' => $bio,
                    ':address' => $address,
                    ':province' => $province,
                    ':lat' => $latitude !== false ? $latitude : null,
                    ':lng' => $longitude !== false ? $longitude : null,
                    ':pin_status' => $newPinStatus,
                    ':avatar' => $avatarUrl,
                    ':showcase_images' => $showcaseJson,
                    ':id' => $userId
                ]);

                // Update in-memory session if own profile
                if ($currentUser && (int)$currentUser['id'] === $userId) {
                    $_SESSION['user']['name'] = $name;
                    $_SESSION['user']['avatar'] = $avatarUrl;
                    $_SESSION['user']['category'] = $category;
                    $_SESSION['user']['lat'] = $latitude;
                    $_SESSION['user']['lng'] = $longitude;
                }

                if ($newPinStatus === 'pending') {
                $successMsg = 'บันทึกข้อมูลสำเร็จ! พิกัดของคุณถูกส่งให้ผู้ดูแลระบบตรวจสอบ (Pending Approval) จะแสดงบนแผนที่เมื่อได้รับอนุมัติ';
            } else {
                $successMsg = 'บันทึกข้อมูลโปรไฟล์และพิกัดแผนที่สำเร็จแล้ว!';
            }
            } catch (\Exception $e) {
                $errorMsg = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
            }
        }
    }
}

// Handle Marketplace Item Management (จัดการสินค้าของตนเอง: เปลี่ยนสถานะ, สินค้าหมด, ปิดประกาศ, ลบ)
if ($isOwnProfile && isset($_GET['item_action'])) {
    $itemAction = $_GET['item_action'];
    $targetItemId = (int)($_GET['item_id'] ?? 0);

    if ($targetItemId > 0) {
        // Verify ownership
        $checkOwner = $pdo->prepare("SELECT id, status FROM marketplace_items WHERE id = ? AND user_id = ?");
        $checkOwner->execute([$targetItemId, $userId]);
        $ownedItem = $checkOwner->fetch();

        if ($ownedItem) {
            if ($itemAction === 'mark_sold') {
                $pdo->prepare("UPDATE marketplace_items SET status = 'sold' WHERE id = ?")->execute([$targetItemId]);
                $successMsg = 'ปรับสถานะสินค้าเป็น "สินค้าหมด / ขายแล้ว (Sold Out)" เรียบร้อยแล้ว';
            } elseif ($itemAction === 'mark_available') {
                $pdo->prepare("UPDATE marketplace_items SET status = 'available' WHERE id = ?")->execute([$targetItemId]);
                $successMsg = 'เปิดวางจำหน่ายสินค้าในตลาดอีกครั้งเรียบร้อยแล้ว';
            } elseif ($itemAction === 'mark_hidden') {
                $pdo->prepare("UPDATE marketplace_items SET status = 'hidden' WHERE id = ?")->execute([$targetItemId]);
                $successMsg = 'ปิดประกาศและนำสินค้าออกจากตลาดเรียบร้อยแล้ว';
            } elseif ($itemAction === 'delete_item') {
                $pdo->prepare("DELETE FROM marketplace_items WHERE id = ?")->execute([$targetItemId]);
                $successMsg = 'ลบประกาศสินค้าออกจากระบบเรียบร้อยแล้ว';
            }
        } else {
            $errorMsg = 'คุณไม่มีสิทธิ์จัดการรายการสินค้านี้';
        }
    }
}

// Handle Marketplace Item Edit Form Submission
if ($isOwnProfile && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_item_submit'])) {
    $editItemId = (int)($_POST['edit_item_id'] ?? 0);
    $itemTitle = trim($_POST['title'] ?? '');
    $itemType = trim($_POST['item_type'] ?? 'khaen_ped');
    $itemKey = trim($_POST['key_note'] ?? 'Am');
    $itemWood = trim($_POST['wood_type'] ?? 'ไม้กู่แคน (ไม้ซาง)');
    $itemReed = trim($_POST['reed_type'] ?? 'ลิ้นเงินแท้');
    $itemPitch = trim($_POST['pitch_standard'] ?? 'A440 Hz');
    $itemMaker = trim($_POST['maker_name'] ?? '');
    $itemPrice = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
    $itemShipping = filter_var($_POST['shipping_cost'] ?? 0, FILTER_VALIDATE_FLOAT);
    $itemCondition = trim($_POST['condition_status'] ?? 'new');
    $itemStatus = trim($_POST['status'] ?? 'available');
    $itemDesc = trim($_POST['description'] ?? '');

    // Verify ownership
    $checkOwner = $pdo->prepare("SELECT id, image_url, images FROM marketplace_items WHERE id = ? AND user_id = ?");
    $checkOwner->execute([$editItemId, $userId]);
    $existingItem = $checkOwner->fetch();

    if (!$existingItem) {
        $errorMsg = 'ไม่พบรายการสินค้า หรือคุณไม่มีสิทธิ์แก้ไข';
    } elseif (empty($itemTitle) || $itemPrice === false || $itemPrice <= 0) {
        $errorMsg = 'กรุณาระบุชื่อสินค้าและราคาขายที่ถูกต้อง';
    } else {
        try {
            $currentImages = [];
            if (!empty($existingItem['images'])) {
                $dec = json_decode($existingItem['images'], true);
                if (is_array($dec)) $currentImages = $dec;
            }
            if (empty($currentImages) && !empty($existingItem['image_url'])) {
                $currentImages = [$existingItem['image_url']];
            }

            // Check if new images uploaded (up to 5 images)
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            if (isset($_FILES['edit_images']) && is_array($_FILES['edit_images']['name']) && !empty($_FILES['edit_images']['name'][0])) {
                $newImages = [];
                $countFiles = min(count($_FILES['edit_images']['name']), 5);
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

                for ($i = 0; $i < $countFiles; $i++) {
                    if ($_FILES['edit_images']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmp = $_FILES['edit_images']['tmp_name'][$i];
                        $name = $_FILES['edit_images']['name'][$i];
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (in_array($ext, $allowed)) {
                            $fName = 'khaen_' . time() . '_' . rand(1000, 9999) . '_' . ($i + 1) . '.' . $ext;
                            if (move_uploaded_file($tmp, $uploadDir . $fName)) {
                                $newImages[] = 'uploads/' . $fName;
                            }
                        }
                    }
                }
                if (!empty($newImages)) {
                    $currentImages = $newImages;
                }
            }

            $primaryImg = !empty($currentImages[0]) ? $currentImages[0] : $existingItem['image_url'];
            $imagesJson = json_encode(array_values($currentImages), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $updateItemStmt = $pdo->prepare("
                UPDATE marketplace_items SET
                    title = :title,
                    item_type = :item_type,
                    key_note = :key_note,
                    wood_type = :wood_type,
                    reed_type = :reed_type,
                    pitch_standard = :pitch_standard,
                    maker_name = :maker_name,
                    price = :price,
                    shipping_cost = :shipping_cost,
                    condition_status = :condition_status,
                    status = :status,
                    description = :description,
                    image_url = :image_url,
                    images = :images
                WHERE id = :id AND user_id = :user_id
            ");

            $updateItemStmt->execute([
                ':title' => $itemTitle,
                ':item_type' => $itemType,
                ':key_note' => $itemKey,
                ':wood_type' => $itemWood,
                ':reed_type' => $reed_type ?? $itemReed,
                ':pitch_standard' => $itemPitch,
                ':maker_name' => $itemMaker,
                ':price' => $itemPrice,
                ':shipping_cost' => $itemShipping !== false ? $itemShipping : 0,
                ':condition_status' => $itemCondition,
                ':status' => $itemStatus,
                ':description' => $itemDesc,
                ':image_url' => $primaryImg,
                ':images' => $imagesJson,
                ':id' => $editItemId,
                ':user_id' => $userId
            ]);

            $successMsg = 'แก้ไขและอัปเดตข้อมูลสินค้า #' . $editItemId . ' เรียบร้อยแล้ว!';
        } catch (\Exception $e) {
            $errorMsg = 'เกิดข้อผิดพลาดในการแก้ไขข้อมูลสินค้า: ' . $e->getMessage();
        }
    }
}

// Fetch Fresh User Data
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userData = $userStmt->fetch();

// Prepare Workshop & Craft Showcase Images List (ภาพถ่ายผลงานจริงและบรรยากาศศูนย์ทำแคน)
$userShowcaseList = [];
if (!empty($userData['showcase_images'])) {
    $decoded = json_decode($userData['showcase_images'], true);
    if (is_array($decoded)) {
        $userShowcaseList = $decoded;
    }
}
// Default sample showcase for mock masters if not yet set
if (empty($userShowcaseList) && in_array($userId, [2, 3, 4])) {
    $userShowcaseList = [
        'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=800&q=80',
        'https://images.unsplash.com/photo-1520697830682-bbb6e85e2b0b?auto=format&fit=crop&w=800&q=80',
        'https://images.unsplash.com/photo-1520523839898-507125cd53c1?auto=format&fit=crop&w=800&q=80'
    ];
}

// Handle Review Submission (เมื่อมีคนให้ดาวและคอมเมนต์รีวิว user นี้)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = filter_var($_POST['rating'] ?? 5, FILTER_VALIDATE_INT);
    if ($rating < 1 || $rating > 5) $rating = 5;
    $comment = trim($_POST['comment'] ?? '');
    
    // Reviewer name & avatar (Use current user if logged in, otherwise guest name)
    $reviewerId = $currentUser ? (int)$currentUser['id'] : null;
    $reviewerName = $currentUser ? $currentUser['name'] : trim($_POST['reviewer_name'] ?? 'ผู้ใช้งาน SoundRadar');
    $reviewerAvatar = $currentUser ? $currentUser['avatar'] : 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=400&q=80';

    if (empty($reviewerName)) {
        $reviewerName = 'ผู้ใช้งาน SoundRadar';
    }

    if (empty($comment)) {
        $errorMsg = 'กรุณากรอกข้อความแสดงความคิดเห็นหรือคำติชม';
    } elseif ($currentUser && (int)$currentUser['id'] === $userId) {
        $errorMsg = 'คุณไม่สามารถเขียนรีวิวให้กับโปรไฟล์ของตนเองได้';
    } else {
        try {
            $insertRev = $pdo->prepare("
                INSERT INTO user_reviews (target_user_id, reviewer_id, reviewer_name, reviewer_avatar, rating, comment)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insertRev->execute([$userId, $reviewerId, $reviewerName, $reviewerAvatar, $rating, $comment]);
            $successMsg = 'ส่งรีวิวและให้คะแนนดาวเรียบร้อยแล้ว ขอบคุณสำหรับการแบ่งปันความคิดเห็น!';
        } catch (\Exception $e) {
            $errorMsg = 'เกิดข้อผิดพลาดในการบันทึกรีวิว: ' . $e->getMessage();
        }
    }
}

// Fetch User's Marketplace Listings
$myListingsStmt = $pdo->prepare("SELECT * FROM marketplace_items WHERE user_id = ? ORDER BY id DESC");
$myListingsStmt->execute([$userId]);
$myListings = $myListingsStmt->fetchAll();

// Seed initial sample reviews if table is empty for this user (to ensure rich showcase)
$checkRevCount = $pdo->prepare("SELECT COUNT(*) FROM user_reviews WHERE target_user_id = ?");
$checkRevCount->execute([$userId]);
if ((int)$checkRevCount->fetchColumn() === 0 && in_array($userId, [2, 3, 4, 6, 7])) {
    $sampleReviews = [
        [
            'target' => 2,
            'name' => 'ครูบอย ลายแคนกาฬสินธุ์',
            'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=400&q=80',
            'rating' => 5,
            'comment' => 'สั่งแคนแปด คีย์ Am มาขึ้นเวทีหมอลำ เสียงใสกังวานมาก ไม่เปลืองลมเลย เทียบคีย์ตรงกับดนตรีสากลพอดี สมชื่อครูช่างมือทองครับ'
        ],
        [
            'target' => 2,
            'name' => 'นิสิต เอกดนตรี ม.ขอนแก่น',
            'avatar' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=400&q=80',
            'rating' => 5,
            'comment' => 'เดินทางมาตามหมุดพิกัด GPS ใน SoundRadar สะดวกมาก เข้ามาลองเป่าถึงที่ร้าน เจ้าของให้คำแนะนำเรื่องการจูนเสียงดีมากครับ'
        ],
        [
            'target' => 3,
            'name' => 'หมอแคนสมใจ ร้อยเอ็ด',
            'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=400&q=80',
            'rating' => 5,
            'comment' => 'งานไม้ประณีตมาก ลิ้นเงินแท้เป่าง่าย เสียงหวานจับใจ สั่งทำพิเศษ 2 สัปดาห์ได้ของตรงเวลา'
        ]
    ];
    $ins = $pdo->prepare("INSERT INTO user_reviews (target_user_id, reviewer_name, reviewer_avatar, rating, comment) VALUES (?, ?, ?, ?, ?)");
    foreach ($sampleReviews as $sr) {
        if ($sr['target'] === $userId) {
            $ins->execute([$sr['target'], $sr['name'], $sr['avatar'], $sr['rating'], $sr['comment']]);
        }
    }
}

// Fetch Reviews for this user
$revStmt = $pdo->prepare("SELECT * FROM user_reviews WHERE target_user_id = ? ORDER BY id DESC");
$revStmt->execute([$userId]);
$userReviews = $revStmt->fetchAll();

// Calculate Average Rating & Count
$totalReviewCount = count($userReviews);
$avgRating = 5.0;
if ($totalReviewCount > 0) {
    $sumRating = array_sum(array_column($userReviews, 'rating'));
    $avgRating = round($sumRating / $totalReviewCount, 1);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ผู้ใช้งาน | <?= htmlspecialchars($userData['name']) ?> - SoundRadar</title>

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
                        'isan-bg': '#FDFBF7'
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

    <!-- Leaflet.js for Coordinate Picker -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        body { font-family: 'Bai Jamjuree', 'Sarabun', sans-serif; }
        .font-thai-traditional { font-family: 'Charm', cursive, serif; }
        #picker-map { height: 320px; z-index: 10; border-radius: 1rem; }
    </style>
</head>
<body class="bg-isan-bg text-gray-800 antialiased min-h-screen flex flex-col">

    <!-- Top Navigation Bar (Full Width) -->
    <header class="bg-white/95 backdrop-blur-md sticky top-0 z-50 border-b border-amber-900/10 shadow-sm">
        <div class="w-full px-4 sm:px-6 lg:px-10">
            <div class="flex items-center justify-between h-20">
                <!-- Brand / Logo -->
                <a href="index.php" class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-isan-terracotta to-isan-wood flex items-center justify-center text-white shadow-md shadow-amber-900/20">
                        <i data-lucide="radio" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-2xl font-bold tracking-tight text-isan-indigo">SoundRadar</span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-medium">โปรไฟล์</span>
                        </div>
                        <p class="text-xs text-gray-500">จัดการข้อมูลส่วนตัว พิกัดแผนที่ และประกาศขายแคน</p>
                    </div>
                </a>

                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center gap-8 text-sm font-medium">
                    <a href="index.php" class="text-gray-600 hover:text-isan-terracotta transition flex items-center gap-1.5">
                        <i data-lucide="map-pin" class="w-4 h-4"></i> ค้นหาพิกัดแคน (Map)
                    </a>
                    <a href="marketplace.php" class="text-gray-600 hover:text-isan-terracotta transition flex items-center gap-1.5">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i> ตลาดซื้อขายแคน (Marketplace)
                    </a>
                    <a href="profile.php" class="text-isan-terracotta font-semibold flex items-center gap-1.5 border-b-2 border-isan-terracotta pb-1">
                        <i data-lucide="user" class="w-4 h-4"></i> โปรไฟล์ของฉัน
                    </a>
                </nav>

                <!-- Actions -->
                <div class="flex items-center gap-3">
                    <a href="index.php" class="inline-flex items-center gap-2 px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-medium transition">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i> กลับหน้าหลัก
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Container (Full Width PC Layout) -->
    <main class="w-full px-4 sm:px-6 lg:px-10 py-8 flex-1">

        <?php if ($isPreviewMode): ?>
        <div class="mb-6 p-4 rounded-2xl bg-amber-500/10 border border-amber-300 text-amber-950 text-xs sm:text-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
            <div class="flex items-center gap-2.5">
                <i data-lucide="eye" class="w-5 h-5 text-amber-700 shrink-0"></i>
                <span><strong>โหมดดูตัวอย่างสาธารณะ:</strong> คุณกำลังดูหน้าโปรไฟล์ของคุณในมุมมองของบุคคลภายนอก (Public View) ที่ผู้ใช้งานและลูกค้าจะมองเห็น</span>
            </div>
            <a href="profile.php?id=<?= $userId ?>" class="inline-flex items-center gap-1.5 px-4 py-2 bg-isan-terracotta hover:bg-amber-700 text-white rounded-xl text-xs font-semibold shadow-xs transition shrink-0">
                <i data-lucide="edit-3" class="w-3.5 h-3.5"></i> กลับสู่โหมดแก้ไขข้อมูล
            </a>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($successMsg)): ?>
        <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-2.5 shadow-sm">
            <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600 shrink-0"></i>
            <span><?= htmlspecialchars($successMsg) ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
        <div class="mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-800 text-sm flex items-center gap-2.5 shadow-sm">
            <i data-lucide="alert-circle" class="w-5 h-5 text-red-600 shrink-0"></i>
            <span><?= htmlspecialchars($errorMsg) ?></span>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Left Column: User Summary Card (4 of 12 cols) -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Profile Avatar & Badge Card -->
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-amber-900/10 text-center relative overflow-hidden">
                    <div class="h-28 bg-gradient-to-r from-isan-terracotta via-amber-700 to-isan-wood -mx-6 -mt-6 mb-12 relative">
                        <span class="absolute top-3 right-3 text-[11px] font-bold px-3 py-1 rounded-full text-white bg-black/30 backdrop-blur-sm">
                            <?= htmlspecialchars($userData['role'] === 'admin' ? 'ผู้ดูแลระบบ (Admin)' : 'สมาชิก (User)') ?>
                        </span>
                    </div>

                    <div class="relative inline-block -mt-20 mb-4">
                        <img src="<?= htmlspecialchars($userData['avatar']) ?>" alt="<?= htmlspecialchars($userData['name']) ?>" class="w-28 h-28 rounded-full object-cover border-4 border-white shadow-xl bg-white mx-auto">
                        <?php if ($userData['is_verified']): ?>
                        <span class="absolute bottom-1 right-1 bg-blue-500 text-white p-1 rounded-full shadow-md" title="ยืนยันตัวตนแล้ว">
                            <i data-lucide="check" class="w-4 h-4"></i>
                        </span>
                        <?php endif; ?>
                    </div>

                    <h2 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($userData['name']) ?></h2>
                    <p class="text-xs font-semibold text-isan-terracotta mt-1"><?= htmlspecialchars($userData['title_desc'] ?: 'ยังไม่ได้ระบุฉายา/คำนิยาม') ?></p>
                    
                    <!-- Role Badges (รองรับได้หลายหมวด) -->
                    <div class="mt-3 flex flex-wrap items-center justify-center gap-1.5">
                        <?php 
                            $cats = explode(',', $userData['category'] ?? 'player');
                            foreach ($cats as $c):
                                $c = trim($c);
                                if ($c === 'maker'):
                        ?>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span> ช่างทำแคน
                            </span>
                        <?php elseif ($c === 'player'): ?>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span> หมอแคน
                            </span>
                        <?php elseif ($c === 'shop'): ?>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-orange-600"></span> ร้านขายแคน
                            </span>
                        <?php endif; endforeach; ?>
                    </div>

                    <!-- Pin Approval Status Banner -->
                    <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-center">
                        <?php $pStatus = $userData['pin_status'] ?? 'none'; ?>
                        <?php if ($pStatus === 'approved'): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <i data-lucide="map-pin" class="w-3.5 h-3.5 text-emerald-600"></i> หมุดแสดงบนแผนที่แล้ว (อนุมัติ)
                            </span>
                        <?php elseif ($pStatus === 'pending'): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-300 animate-pulse">
                                <i data-lucide="clock" class="w-3.5 h-3.5 text-amber-600"></i> รอแอดมินอนุมัติพิกัด (Pending)
                            </span>
                        <?php elseif ($pStatus === 'rejected'): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200">
                                <i data-lucide="x-circle" class="w-3.5 h-3.5 text-red-600"></i> ไม่อนุมัติพิกัด กรุณาปักใหม่
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                                <i data-lucide="map-pin-off" class="w-3.5 h-3.5 text-gray-400"></i> ยังไม่ได้ขอปักหมุด
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- User Bio -->
                    <div class="mt-4 pt-4 border-t border-gray-100 text-left">
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider block mb-1">ประวัติ / ผลงาน</span>
                        <p class="text-xs text-gray-600 leading-relaxed"><?= nl2br(htmlspecialchars($userData['bio'] ?: 'ยังไม่มีข้อมูลประวัติ')) ?></p>
                    </div>

                    <!-- Contact Details -->
                    <div class="mt-4 pt-4 border-t border-gray-100 text-left space-y-2 text-xs text-gray-600">
                        <div class="flex items-center gap-2">
                            <i data-lucide="mail" class="w-4 h-4 text-gray-400"></i>
                            <span><?= htmlspecialchars($userData['email']) ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i data-lucide="phone" class="w-4 h-4 text-gray-400"></i>
                            <span><?= htmlspecialchars($userData['phone'] ?: 'ไม่ได้ระบุเบอร์โทร') ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i data-lucide="map-pin" class="w-4 h-4 text-red-500"></i>
                            <span><?= htmlspecialchars($userData['address'] ?: '') ?> จ.<?= htmlspecialchars($userData['province']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Trust & Credibility Showcase Card (การันตีความน่าเชื่อถือและมาตรฐาน) -->
                <div class="bg-gradient-to-br from-amber-500/10 via-amber-50 to-orange-50/40 rounded-3xl p-6 border border-amber-300/60 shadow-sm space-y-4">
                    <div class="flex items-center gap-2.5 pb-3 border-b border-amber-200/60">
                        <div class="w-8 h-8 rounded-xl bg-amber-600 text-white flex items-center justify-center shadow-xs">
                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-gray-900 leading-tight">การันตีความน่าเชื่อถือ</h3>
                            <span class="text-[10px] text-amber-800 font-medium">SoundRadar Verified Master</span>
                        </div>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="flex items-start gap-2.5">
                            <i data-lucide="badge-check" class="w-4 h-4 text-blue-600 shrink-0 mt-0.5"></i>
                            <div>
                                <span class="font-bold text-gray-900 block">ยืนยันตัวตนกับทางระบบ (ID Verified)</span>
                                <span class="text-[11px] text-gray-500">ผ่านการตรวจสอบบัตรประชาชนและมีหน้าร้าน/โรงงานจริง</span>
                            </div>
                        </div>

                        <div class="flex items-start gap-2.5">
                            <i data-lucide="navigation" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5"></i>
                            <div>
                                <span class="font-bold text-gray-900 block">พิกัด GPS ผ่านการอนุมัติ (GPS Inspected)</span>
                                <span class="text-[11px] text-gray-500">หมุดตรงกับสถานที่จริง สามารถเดินทางมาทดลองเสียงได้</span>
                            </div>
                        </div>

                        <div class="flex items-start gap-2.5">
                            <i data-lucide="sparkles" class="w-4 h-4 text-amber-700 shrink-0 mt-0.5"></i>
                            <div>
                                <span class="font-bold text-gray-900 block">รับประกันคุณภาพเสียง (Tuning 440Hz)</span>
                                <span class="text-[11px] text-gray-500">เทียบเสียงมาตรฐานสากล เป่าตรงคีย์ ไม่เปลืองลม</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- My Listings Widget -->
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-amber-900/10">
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                        <h3 class="font-bold text-sm text-gray-900 flex items-center gap-2">
                            <i data-lucide="shopping-bag" class="w-4 h-4 text-isan-terracotta"></i> รายการสินค้า (<?= count($myListings) ?>)
                        </h3>
                        <?php if ($isOwnProfile): ?>
                        <a href="#my-products-management" class="text-xs text-isan-terracotta font-semibold hover:underline flex items-center gap-1">
                            <i data-lucide="sliders-horizontal" class="w-3 h-3"></i> จัดการสินค้า
                        </a>
                        <?php else: ?>
                        <a href="marketplace.php" class="text-xs text-amber-700 hover:underline">ไปที่ตลาด</a>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4 space-y-3">
                        <?php if (count($myListings) === 0): ?>
                            <p class="text-xs text-gray-400 text-center py-4">คุณยังไม่มีรายการประกาศขายแคน</p>
                        <?php else: ?>
                            <?php foreach ($myListings as $listing): ?>
                            <a href="marketplace.php?item_id=<?= $listing['id'] ?>" class="flex items-center gap-3 p-2.5 rounded-2xl hover:bg-amber-50 hover:border-amber-300 transition border border-gray-100 group">
                                <img src="<?= htmlspecialchars($listing['image_url']) ?>" class="w-12 h-12 rounded-xl object-cover group-hover:scale-105 transition">
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-xs font-bold text-gray-900 group-hover:text-isan-terracotta truncate transition"><?= htmlspecialchars($listing['title']) ?></h4>
                                    <span class="text-[11px] font-semibold text-isan-terracotta">฿<?= number_format($listing['price'], 0) ?></span>
                                    <span class="text-[10px] text-gray-400 ml-2">คีย์ <?= htmlspecialchars($listing['key_note']) ?></span>
                                </div>
                                <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-gray-400 group-hover:text-isan-terracotta transition"></i>
                            </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Profile Showcase or Edit Form (8 of 12 cols) -->
            <div class="lg:col-span-8">
                <?php if (!$isOwnProfile): ?>
                <!-- PUBLIC PROFILE VIEW (เมื่อผู้อื่นคลิกเข้ามาดู) -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-amber-900/10 space-y-6">
                    <div class="pb-5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-2xl font-bold text-isan-indigo"><?= htmlspecialchars($userData['name']) ?></h2>
                                <?php if ($userData['is_verified']): ?>
                                <i data-lucide="badge-check" class="w-5 h-5 text-blue-500 fill-blue-50" title="ยืนยันตัวตนแล้ว"></i>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-amber-700 font-semibold mt-0.5"><?= htmlspecialchars($userData['title_desc'] ?: 'ผู้สืบสานมรดกดนตรีแคนอีสาน') ?></p>
                        </div>

                        <div class="flex items-center gap-2">
                            <?php if (!empty($userData['phone'])): ?>
                            <a href="tel:<?= htmlspecialchars($userData['phone']) ?>" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm transition">
                                <i data-lucide="phone" class="w-4 h-4"></i> โทรติดต่อ
                            </a>
                            <?php endif; ?>
                            <a href="mailto:<?= htmlspecialchars($userData['email']) ?>" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 transition">
                                <i data-lucide="mail" class="w-4 h-4"></i> ส่งอีเมล
                            </a>
                        </div>
                    </div>

                    <!-- Key Stats Highlights Bar (เพิ่มความน่าเชื่อถือ) -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="p-3.5 rounded-2xl bg-amber-50/60 border border-amber-200/50 text-center">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block">ประสบการณ์</span>
                            <span class="text-xl font-black text-amber-800">
                                <?= $userData['category'] === 'maker' ? '20+ ปี' : ($userData['category'] === 'player' ? '15+ ปี' : '10+ ปี') ?>
                            </span>
                            <span class="text-[10px] text-gray-400 block mt-0.5">ในวงการดนตรีอีสาน</span>
                        </div>
                        <div class="p-3.5 rounded-2xl bg-blue-50/60 border border-blue-200/50 text-center">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block">การันตีเสียง</span>
                            <span class="text-xl font-black text-blue-800">100%</span>
                            <span class="text-[10px] text-gray-400 block mt-0.5">เทียบคีย์ 440Hz ทุกเต้า</span>
                        </div>
                        <div class="p-3.5 rounded-2xl bg-emerald-50/60 border border-emerald-200/50 text-center">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block">สินค้าพร้อมส่ง</span>
                            <span class="text-xl font-black text-emerald-800"><?= count($myListings) ?> รายการ</span>
                            <span class="text-[10px] text-gray-400 block mt-0.5">ในสต็อกของร้าน</span>
                        </div>
                        <div class="p-3.5 rounded-2xl bg-purple-50/60 border border-purple-200/50 text-center">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block">คะแนนความพึงพอใจ</span>
                            <span class="text-xl font-black text-purple-800"><?= number_format($avgRating, 1) ?> / 5</span>
                            <span class="text-[10px] text-gray-400 block mt-0.5">จาก <?= $totalReviewCount ?> รีวิวความประทับใจ</span>
                        </div>
                    </div>

                    <!-- Workshop & Craft Showcase Gallery (ภาพถ่ายผลงานและบรรยากาศโรงงาน/ร้าน) -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                <i data-lucide="images" class="w-4 h-4 text-amber-700"></i> ภาพถ่ายผลงานจริงและบรรยากาศศูนย์ทำแคน
                            </h3>
                            <span class="text-[11px] text-gray-400"><?= count($userShowcaseList) ?> รูปภาพจากสถานที่จริง</span>
                        </div>
                        <?php if (empty($userShowcaseList)): ?>
                            <div class="p-8 text-center bg-amber-50/40 rounded-2xl border border-dashed border-amber-200">
                                <i data-lucide="image-off" class="w-8 h-8 text-amber-300 mx-auto mb-2"></i>
                                <p class="text-xs text-gray-500 font-medium">ยังไม่มีการเพิ่มภาพถ่ายผลงานจริงหรือบรรยากาศศูนย์ทำแคน</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <?php foreach ($userShowcaseList as $scIndex => $scImgUrl): ?>
                                <div onclick="openShowcaseLightbox('<?= htmlspecialchars($scImgUrl) ?>')" class="relative rounded-2xl overflow-hidden border border-gray-200 group shadow-xs cursor-pointer aspect-video bg-gray-100">
                                    <img src="<?= htmlspecialchars($scImgUrl) ?>" alt="ภาพถ่ายผลงานจริงและบรรยากาศ" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition flex items-center justify-center">
                                        <i data-lucide="zoom-in" class="w-6 h-6 text-white opacity-0 group-hover:opacity-100 transition transform scale-75 group-hover:scale-100"></i>
                                    </div>
                                    <span class="absolute bottom-1.5 left-1.5 text-[10px] bg-black/60 backdrop-blur-xs text-white px-2 py-0.5 rounded-md flex items-center gap-1">
                                        <i data-lucide="camera" class="w-2.5 h-2.5"></i> ผลงานจริง
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- About / Bio Section -->
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 mb-2 flex items-center gap-2">
                            <i data-lucide="book-open" class="w-4 h-4 text-amber-700"></i> ประวัติ ภูมิปัญญา และลายดนตรีที่เชี่ยวชาญ
                        </h3>
                        <div class="bg-amber-50/40 p-5 rounded-2xl border border-amber-900/5 text-xs sm:text-sm text-gray-700 leading-relaxed space-y-2">
                            <p><?= nl2br(htmlspecialchars($userData['bio'] ?: 'สมาชิกท่านนี้ยังไม่ได้เพิ่มข้อมูลประวัติส่วนตัว')) ?></p>
                            <div class="pt-2 border-t border-amber-200/40 flex flex-wrap gap-2 text-[11px] font-semibold">
                                <span class="bg-amber-100 text-amber-800 px-2.5 py-1 rounded-lg">แคนแปดมาตรฐาน</span>
                                <span class="bg-amber-100 text-amber-800 px-2.5 py-1 rounded-lg">ลายสุดสะแนน</span>
                                <span class="bg-amber-100 text-amber-800 px-2.5 py-1 rounded-lg">ลายโปงลาง</span>
                                <span class="bg-amber-100 text-amber-800 px-2.5 py-1 rounded-lg">ลายเต้ย</span>
                                <span class="bg-amber-100 text-amber-800 px-2.5 py-1 rounded-lg">ส่งด่วนทั่วไทยและต่างประเทศ</span>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Reviews & Testimonials Showcase (เสียงตอบรับและรีวิวให้ดาว) -->
                    <div id="reviews-section" class="pt-6 border-t border-gray-100">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                            <div>
                                <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                    <i data-lucide="message-square-heart" class="w-5 h-5 text-rose-600"></i> รีวิวและคะแนนความพึงพอใจ (<?= $totalReviewCount ?> รีวิว)
                                </h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <div class="flex items-center text-amber-500">
                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                            <i data-lucide="star" class="w-4 h-4 <?= $s <= round($avgRating) ? 'fill-amber-400 text-amber-400' : 'text-gray-300' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="text-sm font-bold text-gray-900"><?= number_format($avgRating, 1) ?> จาก 5</span>
                                    <span class="text-xs text-gray-400">• ยืนยันสมาชิกชุมชน</span>
                                </div>
                            </div>

                            <?php if (!$isOwnProfile): ?>
                            <button type="button" onclick="toggleReviewForm()" class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-amber-600 to-isan-terracotta hover:from-amber-700 hover:to-orange-800 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                                <i data-lucide="pen-square" class="w-4 h-4"></i> เขียนรีวิว / ให้คะแนนดาว
                            </button>
                            <?php endif; ?>
                        </div>

                        <?php if (!$isOwnProfile): ?>
                        <!-- Interactive Review Submission Box (ซ่อน/แสดง) -->
                        <div id="review-form-box" class="mb-6 p-5 rounded-2xl bg-amber-50/70 border border-amber-200/80 hidden transition-all">
                            <form method="POST" action="profile.php?id=<?= $userId ?>#reviews-section" class="space-y-4">
                                <input type="hidden" name="submit_review" value="1">
                                
                                <div class="flex items-center justify-between pb-3 border-b border-amber-200/60">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-amber-900 flex items-center gap-1.5">
                                        <i data-lucide="star" class="w-4 h-4 text-amber-600 fill-amber-500"></i> ให้คะแนนและเขียนความประทับใจ
                                    </h4>
                                    <button type="button" onclick="toggleReviewForm()" class="text-xs text-gray-500 hover:text-gray-700">ปิด</button>
                                </div>

                                <!-- Star Rating Selector (Interactive 1-5 Stars) -->
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">เลือกระดับคะแนนดาว <span class="text-red-500">*</span></label>
                                    <div class="flex items-center gap-2" id="star-picker">
                                        <input type="hidden" name="rating" id="review-rating-input" value="5">
                                        <div class="flex items-center gap-1 cursor-pointer">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <button type="button" onclick="setRating(<?= $i ?>)" onmouseover="hoverRating(<?= $i ?>)" onmouseleave="resetHoverRating()" class="star-btn p-1 text-amber-400 hover:scale-110 transition" data-val="<?= $i ?>">
                                                <i data-lucide="star" class="w-6 h-6 fill-amber-400"></i>
                                            </button>
                                            <?php endfor; ?>
                                        </div>
                                        <span id="rating-label" class="text-xs font-bold text-amber-900 ml-2">5 ดาว (ดีเยี่ยมมาก)</span>
                                    </div>
                                </div>

                                <?php if (!$currentUser): ?>
                                <!-- Reviewer name input if not logged in -->
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">ชื่อผู้เขียนรีวิว <span class="text-red-500">*</span></label>
                                    <input type="text" name="reviewer_name" required placeholder="เช่น หมอแคนศิษย์ครูบุญมี / ผู้ซื้อจากขอนแก่น" class="w-full text-xs sm:text-sm px-3.5 py-2.5 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                </div>
                                <?php endif; ?>

                                <!-- Review Comment Text -->
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">ข้อความรีวิว / คำชื่นชม / ประสบการณ์การใช้งาน <span class="text-red-500">*</span></label>
                                    <textarea name="comment" required rows="3" placeholder="เช่น แคนคุณภาพดีมาก ลิ้นเงินแท้เป่าง่าย เสียงหวานตรงคีย์ หรือมาตามพิกัดแผนที่แล้วบริการเป็นกันเอง..." class="w-full text-xs sm:text-sm p-3 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none"></textarea>
                                </div>

                                <div class="flex items-center justify-end gap-3 pt-2">
                                    <button type="button" onclick="toggleReviewForm()" class="px-4 py-2 text-xs text-gray-600 hover:bg-gray-100 rounded-xl">ยกเลิก</button>
                                    <button type="submit" class="px-5 py-2 text-xs font-semibold text-white bg-isan-terracotta hover:bg-amber-700 rounded-xl shadow-md transition flex items-center gap-1.5">
                                        <i data-lucide="send" class="w-3.5 h-3.5"></i> ส่งคะแนนและคอมเมนต์
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>

                        <!-- Reviews List Display -->
                        <?php if (count($userReviews) === 0): ?>
                        <div class="p-8 text-center bg-gray-50 rounded-2xl border border-gray-100">
                            <i data-lucide="message-square-dashed" class="w-10 h-10 text-gray-300 mx-auto mb-2"></i>
                            <p class="text-xs text-gray-500 font-medium">ยังไม่มีรีวิวสำหรับสมาชิกท่านนี้ ร่วมเป็นคนแรกที่เขียนรีวิวและให้คะแนนดาว!</p>
                        </div>
                        <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <?php foreach ($userReviews as $rev): ?>
                            <div class="p-4 rounded-2xl bg-white border border-amber-900/10 shadow-xs space-y-2 hover:shadow-md transition">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2.5">
                                        <img src="<?= htmlspecialchars($rev['reviewer_avatar']) ?>" alt="<?= htmlspecialchars($rev['reviewer_name']) ?>" class="w-8 h-8 rounded-full object-cover border border-amber-200">
                                        <div>
                                            <span class="text-xs font-bold text-gray-900 block leading-tight"><?= htmlspecialchars($rev['reviewer_name']) ?></span>
                                            <span class="text-[10px] text-gray-400"><?= date('d M Y', strtotime($rev['created_at'])) ?></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-0.5 text-amber-500 bg-amber-50 px-2 py-0.5 rounded-lg border border-amber-100">
                                        <?php for ($star = 1; $star <= 5; $star++): ?>
                                            <i data-lucide="star" class="w-3 h-3 <?= $star <= (int)$rev['rating'] ? 'fill-amber-500 text-amber-500' : 'text-gray-300' ?>"></i>
                                        <?php endfor; ?>
                                        <span class="text-[11px] font-bold text-amber-800 ml-1"><?= (int)$rev['rating'] ?>.0</span>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-600 leading-relaxed bg-amber-50/20 p-2.5 rounded-xl border border-amber-900/5">
                                    "<?= nl2br(htmlspecialchars($rev['comment'])) ?>"
                                </p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Map Location Showcase -->
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 mb-2 flex items-center gap-2">
                            <i data-lucide="map-pin" class="w-4 h-4 text-amber-700"></i> ตำแหน่งพิกัดสถานที่ / ศูนย์จำหน่าย
                        </h3>
                        <p class="text-xs text-gray-500 mb-3">
                            <?= htmlspecialchars($userData['address'] ?: 'ต.ในเมือง อ.เมือง') ?> จ.<?= htmlspecialchars($userData['province']) ?>
                        </p>
                        <div class="border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                            <div id="picker-map" style="height: 320px;"></div>
                        </div>
                    </div>

                    <!-- User's Marketplace Showcase Items -->
                    <?php if (count($myListings) > 0): ?>
                    <div class="pt-6 border-t border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                <i data-lucide="shopping-bag" class="w-4 h-4 text-isan-terracotta"></i> เครื่องดนตรีที่ลงประกาศขาย (<?= count($myListings) ?> รายการ)
                            </h3>
                            <a href="marketplace.php" class="text-xs text-amber-700 hover:underline">ดูสินค้าทั้งหมดในตลาด</a>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <?php foreach ($myListings as $it): ?>
                            <a href="marketplace.php?item_id=<?= $it['id'] ?>" class="bg-white border border-gray-200 rounded-2xl p-3 shadow-xs hover:shadow-md hover:border-amber-400 transition flex items-center gap-3 group">
                                <img src="<?= htmlspecialchars($it['image_url']) ?>" class="w-16 h-16 rounded-xl object-cover group-hover:scale-105 transition">
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-xs font-bold text-gray-900 group-hover:text-isan-terracotta truncate transition"><?= htmlspecialchars($it['title']) ?></h4>
                                    <div class="text-[11px] text-gray-500 mt-0.5">คีย์ <?= htmlspecialchars($it['key_note']) ?> • <?= $it['condition_status'] === 'new' ? 'ของใหม่' : 'มือสอง' ?></div>
                                    <div class="text-sm font-extrabold text-isan-terracotta mt-1">฿<?= number_format($it['price'], 0) ?></div>
                                </div>
                                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400 group-hover:text-isan-terracotta transition"></i>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php else: ?>
                <!-- OWN PROFILE EDIT VIEW (สำหรับเจ้าของบัญชีแก้ไขข้อมูล) -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-amber-900/10">
                    <div class="pb-5 border-b border-gray-100 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-bold text-isan-indigo flex items-center gap-2">
                                <i data-lucide="user-pen" class="w-6 h-6 text-isan-terracotta"></i> แก้ไขข้อมูลโปรไฟล์และพิกัดแผนที่
                            </h2>
                            <p class="text-xs text-gray-500 mt-1">อัปเดตข้อมูลเพื่อให้หมอแคน ช่างแคน และผู้ใช้งานอื่นค้นหาคุณพบบนแผนที่</p>
                        </div>
                        <a href="profile.php?id=<?= $userId ?>&preview=1" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200/80 rounded-xl text-xs font-semibold shadow-xs transition shrink-0">
                            <i data-lucide="eye" class="w-4 h-4 text-amber-700"></i> ดูตัวอย่างหน้าโปรไฟล์จริง (Preview)
                        </a>
                    </div>

                    <form method="POST" action="profile.php" enctype="multipart/form-data" class="space-y-6">
                        <input type="hidden" name="update_profile" value="1">

                        <!-- Section 1: Basic Info -->
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-2">
                                <i data-lucide="badge-info" class="w-4 h-4 text-amber-700"></i> ข้อมูลพื้นฐานและสถานะ
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">ชื่อ-นามสกุล / ฉายาในวงการ <span class="text-red-500">*</span></label>
                                    <input type="text" name="name" required value="<?= htmlspecialchars($userData['name']) ?>" class="w-full text-xs sm:text-sm px-3.5 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                </div>

                                <?php 
                                    $userCatArray = explode(',', $userData['category'] ?? 'player');
                                ?>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-bold text-gray-800 mb-1.5 flex items-center justify-between">
                                        <span class="flex items-center gap-1.5">
                                            <span>หมวดหมู่สมาชิก (Category)</span>
                                            <span class="text-red-500">*</span>
                                        </span>
                                        <span class="text-[11px] font-normal text-amber-800 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200">
                                            (สามารถเลือกได้มากกว่า 1 หมวด)
                                        </span>
                                    </label>
                                    
                                    <!-- Category Checklist Grid -->
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <!-- Checklist 1: Maker -->
                                        <label class="relative flex items-center gap-3 p-3.5 rounded-2xl border-2 cursor-pointer transition select-none hover:bg-amber-50/50 <?= in_array('maker', $userCatArray) ? 'border-amber-500 bg-amber-50/40 text-amber-950 font-bold' : 'border-gray-200 bg-white text-gray-700' ?>">
                                            <input type="checkbox" name="categories[]" value="maker" <?= in_array('maker', $userCatArray) ? 'checked' : '' ?> class="w-4 h-4 text-amber-600 rounded focus:ring-amber-500 border-gray-300">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center shrink-0">
                                                    <i data-lucide="wrench" class="w-4 h-4"></i>
                                                </div>
                                                <div class="leading-tight">
                                                    <span class="text-xs block">ช่างทำแคน</span>
                                                    <span class="text-[10px] text-gray-500 font-normal">Khaen Maker</span>
                                                </div>
                                            </div>
                                        </label>

                                        <!-- Checklist 2: Player -->
                                        <label class="relative flex items-center gap-3 p-3.5 rounded-2xl border-2 cursor-pointer transition select-none hover:bg-blue-50/50 <?= in_array('player', $userCatArray) ? 'border-blue-500 bg-blue-50/40 text-blue-950 font-bold' : 'border-gray-200 bg-white text-gray-700' ?>">
                                            <input type="checkbox" name="categories[]" value="player" <?= in_array('player', $userCatArray) ? 'checked' : '' ?> class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500 border-gray-300">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-lg bg-blue-100 text-blue-800 flex items-center justify-center shrink-0">
                                                    <i data-lucide="music" class="w-4 h-4"></i>
                                                </div>
                                                <div class="leading-tight">
                                                    <span class="text-xs block">หมอแคน</span>
                                                    <span class="text-[10px] text-gray-500 font-normal">Khaen Player</span>
                                                </div>
                                            </div>
                                        </label>

                                        <!-- Checklist 3: Shop -->
                                        <label class="relative flex items-center gap-3 p-3.5 rounded-2xl border-2 cursor-pointer transition select-none hover:bg-orange-50/50 <?= in_array('shop', $userCatArray) ? 'border-orange-500 bg-orange-50/40 text-orange-950 font-bold' : 'border-gray-200 bg-white text-gray-700' ?>">
                                            <input type="checkbox" name="categories[]" value="shop" <?= in_array('shop', $userCatArray) ? 'checked' : '' ?> class="w-4 h-4 text-orange-600 rounded focus:ring-orange-500 border-gray-300">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-lg bg-orange-100 text-orange-800 flex items-center justify-center shrink-0">
                                                    <i data-lucide="store" class="w-4 h-4"></i>
                                                </div>
                                                <div class="leading-tight">
                                                    <span class="text-xs block">ร้านขายแคน</span>
                                                    <span class="text-[10px] text-gray-500 font-normal">Khaen Shop</span>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Title description & Phone -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">คำนิยามสั้นๆ / ตำแหน่ง</label>
                                <input type="text" name="title_desc" value="<?= htmlspecialchars($userData['title_desc'] ?? '') ?>" placeholder="เช่น ช่างแคนลายโบราณ 40 ปี, หมอแคนรับงานแห่" class="w-full text-xs sm:text-sm px-3.5 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">เบอร์โทรศัพท์ติดต่อ</label>
                                <input type="text" name="phone" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>" placeholder="เช่น 089-123-4567" class="w-full text-xs sm:text-sm px-3.5 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            </div>
                        </div>

                        <!-- Avatar Upload -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">เปลี่ยนรูปโปรไฟล์ (Avatar)</label>
                            <input type="file" name="avatar_file" accept="image/*" class="w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-800 hover:file:bg-amber-100">
                        </div>

                        <!-- Bio -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">ประวัติผลงาน / ความเชี่ยวชาญ (Bio)</label>
                            <textarea name="bio" rows="3" placeholder="ระบุผลงาน ประสบการณ์ การทำแคน หรือประวัติการเป่าแคน..." class="w-full text-xs sm:text-sm px-3.5 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none"><?= htmlspecialchars($userData['bio'] ?? '') ?></textarea>
                        </div>

                        <!-- Section: Workshop & Craft Showcase Gallery (ภาพถ่ายผลงานจริงและบรรยากาศศูนย์ทำแคน) -->
                        <div class="pt-4 border-t border-gray-100">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                        <i data-lucide="images" class="w-4 h-4 text-amber-700"></i> ภาพถ่ายผลงานจริงและบรรยากาศศูนย์ทำแคน (Showcase Gallery)
                                    </h3>
                                    <p class="text-[11px] text-gray-500">อัปโหลดภาพถ่ายขั้นตอนการทำแคน บรรยากาศโรงงาน หรือหน้าร้านจริงเพื่อสร้างความน่าเชื่อถือและความประทับใจ (สูงสุด 6 รูป)</p>
                                </div>
                                <span id="showcase-count-badge" class="self-start sm:self-auto text-xs font-semibold text-amber-800 bg-amber-50 px-2.5 py-1 rounded-lg border border-amber-200">
                                    <?= count($userShowcaseList) ?>/6 รูป
                                </span>
                            </div>

                            <input type="hidden" name="has_showcase_section" value="1">

                            <!-- Existing Showcase Photos Grid -->
                            <?php if (!empty($userShowcaseList)): ?>
                            <div class="mb-4">
                                <label class="block text-xs font-semibold text-gray-700 mb-2">ภาพถ่ายผลงานและบรรยากาศในปัจจุบัน (กดกากบาทสีแดงเพื่อลบ):</label>
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3" id="existing-showcase-container">
                                    <?php foreach ($userShowcaseList as $scIdx => $scImgPath): ?>
                                    <div class="relative group rounded-xl overflow-hidden border border-gray-200 aspect-square bg-gray-50 shadow-xs" id="sc-thumb-<?= $scIdx ?>">
                                        <img src="<?= htmlspecialchars($scImgPath) ?>" class="w-full h-full object-cover">
                                        <input type="hidden" name="keep_showcase_images[]" value="<?= htmlspecialchars($scImgPath) ?>">
                                        <button type="button" onclick="removeExistingShowcase(<?= $scIdx ?>)" class="absolute top-1 right-1 w-6 h-6 rounded-full bg-red-600 hover:bg-red-700 text-white flex items-center justify-center transition shadow-sm" title="ลบรูปนี้">
                                            <i data-lucide="x" class="w-3.5 h-3.5"></i>
                                        </button>
                                        <span class="absolute bottom-1 left-1 text-[9px] bg-black/60 text-white px-1.5 py-0.5 rounded">รูปที่ <?= $scIdx + 1 ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Upload Box for New Showcase Photos -->
                            <div class="relative border-2 border-dashed border-amber-300 hover:border-amber-500 rounded-2xl p-5 text-center bg-amber-50/40 hover:bg-amber-50/70 transition cursor-pointer group" onclick="document.getElementById('showcase-files-input').click()">
                                <input type="file" id="showcase-files-input" name="showcase_files[]" multiple accept="image/*" class="hidden" onchange="handleShowcaseFilesSelection(event)">
                                <div class="flex flex-col items-center justify-center gap-1.5 pointer-events-none">
                                    <div class="w-11 h-11 rounded-full bg-amber-100 text-amber-800 flex items-center justify-center group-hover:scale-110 transition shadow-xs">
                                        <i data-lucide="upload-cloud" class="w-5 h-5"></i>
                                    </div>
                                    <div class="mt-1">
                                        <span class="text-xs font-bold text-gray-800 block">คลิกเพื่อเลือกภาพถ่ายผลงานและบรรยากาศศูนย์ทำแคน</span>
                                        <span class="text-[11px] text-gray-400">รองรับไฟล์ JPG, PNG, WebP (สามารถเลือกหลายรูปพร้อมกันได้)</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-[10px] text-amber-900 bg-amber-100/80 px-3 py-1 rounded-full font-medium mt-1">
                                        <i data-lucide="sparkles" class="w-3 h-3 text-amber-700"></i> แนะนำ: ขั้นตอนคัดไม้กู่แคน, รีดลิ้นเงิน/ทองเหลือง, บรรยากาศหน้าร้านหรือป้ายศูนย์เรียนรู้
                                    </div>
                                </div>
                            </div>

                            <!-- Preview Grid for newly selected photos -->
                            <div id="new-showcase-preview" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3 mt-3 hidden"></div>
                        </div>

                        <!-- Section 2: Address & Province -->
                        <div class="pt-4 border-t border-gray-100">
                            <h3 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-2">
                                <i data-lucide="map-pin" class="w-4 h-4 text-red-500"></i> ที่อยู่และจังหวัด
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">ที่อยู่ / ตำบล / อำเภอ</label>
                                    <input type="text" name="address" value="<?= htmlspecialchars($userData['address'] ?? '') ?>" placeholder="เช่น บ.สีถาน ต.บ้านเป็ด อ.เมือง" class="w-full text-xs sm:text-sm px-3.5 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">จังหวัด</label>
                                    <select name="province" class="w-full text-xs sm:text-sm px-3.5 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                                        <?php 
                                            $provinces = ['ขอนแก่น', 'ร้อยเอ็ด', 'กาฬสินธุ์', 'อุบลราชธานี', 'มหาสารคาม', 'อุดรธานี', 'สกลนคร', 'นครพนม', 'มุกดาหาร', 'ยโสธร', 'อำนาจเจริญ', 'บุรีรัมย์', 'สุรินทร์', 'ศรีสะเกษ', 'นครราชสีมา', 'ชัยภูมิ', 'เลย', 'หนองคาย', 'บึงกาฬ', 'หนองบัวลำภู', 'กรุงเทพมหานคร'];
                                            foreach ($provinces as $prov):
                                        ?>
                                        <option value="<?= $prov ?>" <?= $userData['province'] === $prov ? 'selected' : '' ?>><?= $prov ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: GPS Coordinates & Interactive Map Picker -->
                        <div class="pt-4 border-t border-gray-100">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                        <i data-lucide="crosshair" class="w-4 h-4 text-emerald-600"></i> ปักหมุดพิกัด GPS บนแผนที่
                                    </h3>
                                    <p class="text-[11px] text-gray-400">คลิกบนแผนที่เพื่อเลือกจุด หรือกดปุ่มดึงพิกัดจาก GPS อัตโนมัติ</p>
                                </div>
                                <button type="button" onclick="getDeviceGPS()" class="inline-flex items-center gap-1.5 text-xs bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 px-3 py-1.5 rounded-xl font-medium transition">
                                    <i data-lucide="locate" class="w-3.5 h-3.5"></i> ดึงพิกัดจาก GPS
                                </button>
                            </div>

                            <div class="grid grid-cols-2 gap-3 mb-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">ละติจูด (Latitude)</label>
                                    <input type="text" id="prof-lat" name="latitude" value="<?= htmlspecialchars($userData['latitude'] ?? '') ?>" placeholder="เช่น 16.4419" class="w-full text-xs px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">ลองจิจูด (Longitude)</label>
                                    <input type="text" id="prof-lng" name="longitude" value="<?= htmlspecialchars($userData['longitude'] ?? '') ?>" placeholder="เช่น 102.7981" class="w-full text-xs px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                </div>
                            </div>

                            <!-- Interactive Leaflet Map for Pinning -->
                            <div class="border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                                <div id="picker-map"></div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="pt-6 border-t border-gray-100 flex items-center justify-end gap-3">
                            <a href="index.php" class="px-5 py-2.5 rounded-xl text-xs font-semibold text-gray-600 hover:bg-gray-100 transition">ยกเลิก</a>
                            <button type="submit" class="inline-flex items-center gap-2 px-7 py-2.5 rounded-xl text-xs font-semibold text-white bg-isan-terracotta hover:bg-amber-700 transition shadow-md shadow-orange-900/10">
                                <i data-lucide="save" class="w-4 h-4"></i> บันทึกการเปลี่ยนแปลง
                            </button>
                        </div>
                    </form>

                    <!-- Dedicated Marketplace Item Management Panel (จัดการสินค้าของฉัน) -->
                    <div id="my-products-management" class="mt-10 pt-8 border-t-2 border-amber-900/10">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                    <i data-lucide="boxes" class="w-5 h-5 text-isan-terracotta"></i> จัดการสินค้าในตลาดของฉัน (My Listings)
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">แก้ไขรายละเอียด ปรับสถานะเป็น "สินค้าหมด" หรือปิดประกาศนำสินค้าออกจากตลาด</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="marketplace.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 transition">
                                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i> ตลาดซื้อขาย
                                </a>
                                <a href="marketplace.php" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold bg-isan-terracotta hover:bg-amber-700 text-white shadow-sm transition">
                                    <i data-lucide="plus-circle" class="w-4 h-4"></i> ลงประกาศใหม่
                                </a>
                            </div>
                        </div>

                        <?php if (count($myListings) === 0): ?>
                        <div class="p-8 text-center bg-gray-50 rounded-2xl border border-gray-200/70">
                            <i data-lucide="package-open" class="w-12 h-12 text-gray-300 mx-auto mb-2"></i>
                            <h4 class="font-bold text-sm text-gray-700">คุณยังไม่มีรายการสินค้าในระบบ</h4>
                            <p class="text-xs text-gray-400 mt-1">เริ่มต้นลงประกาศขายแคน ลิ้นแคน หรืออุปกรณ์ดนตรีอีสานได้ง่ายๆ ที่หน้าตลาดซื้อขาย</p>
                            <a href="marketplace.php" class="inline-flex items-center gap-1.5 mt-3 px-4 py-2 rounded-xl bg-isan-terracotta text-white text-xs font-semibold shadow-xs">
                                <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i> ลงประกาศสินค้าชิ้นแรก
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($myListings as $prod): ?>
                            <div class="bg-white rounded-2xl p-4 sm:p-5 border <?= $prod['status'] === 'sold' ? 'border-amber-200 bg-amber-50/20' : ($prod['status'] === 'hidden' ? 'border-gray-200 bg-gray-50/40 opacity-75' : 'border-gray-200') ?> shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4 transition hover:shadow-md">
                                <!-- Product Info & Thumbnail -->
                                <div class="flex items-start gap-4 flex-1 min-w-0">
                                    <div class="relative w-20 h-20 sm:w-24 sm:h-24 rounded-2xl overflow-hidden bg-gray-100 shrink-0 border border-gray-200">
                                        <img src="<?= htmlspecialchars($prod['image_url']) ?>" alt="<?= htmlspecialchars($prod['title']) ?>" class="w-full h-full object-cover">
                                        
                                        <!-- Status Badge on Image -->
                                        <?php if ($prod['status'] === 'sold'): ?>
                                        <span class="absolute inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center text-white text-[10px] font-bold tracking-wider uppercase">
                                            สินค้าหมด
                                        </span>
                                        <?php elseif ($prod['status'] === 'hidden'): ?>
                                        <span class="absolute inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center text-amber-200 text-[10px] font-bold tracking-wider uppercase">
                                            ปิดประกาศ
                                        </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="flex-1 min-w-0 space-y-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="font-bold text-sm sm:text-base text-gray-900 truncate"><?= htmlspecialchars($prod['title']) ?></h4>
                                            
                                            <!-- Status Tag -->
                                            <?php if ($prod['status'] === 'available'): ?>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 flex items-center gap-1">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span> วางจำหน่ายปกติ
                                                </span>
                                            <?php elseif ($prod['status'] === 'sold'): ?>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300 flex items-center gap-1">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span> สินค้าหมด / ขายแล้ว
                                                </span>
                                            <?php elseif ($prod['status'] === 'hidden'): ?>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-600 border border-gray-300 flex items-center gap-1">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> ปิดประกาศ (ซ่อนจากตลาด)
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500">
                                            <span class="font-bold text-isan-terracotta text-sm">฿<?= number_format($prod['price'], 0) ?></span>
                                            <span>คีย์ <?= htmlspecialchars($prod['key_note']) ?></span>
                                            <span>ลิ้น: <?= htmlspecialchars($prod['reed_type'] ?? 'ลิ้นเงินแท้') ?></span>
                                            <span>ไม้: <?= htmlspecialchars($prod['wood_type'] ?? 'ไม้กู่แคน') ?></span>
                                            <span class="text-gray-400">ลงเมื่อ <?= date('d M Y', strtotime($prod['created_at'])) ?></span>
                                        </div>

                                        <p class="text-xs text-gray-400 line-clamp-1">
                                            <?= htmlspecialchars($prod['description'] ?: 'ไม่มีคำอธิบายเพิ่มเติม') ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Action Buttons Toolbar -->
                                <div class="flex flex-wrap items-center gap-2 w-full md:w-auto justify-end border-t md:border-t-0 pt-3 md:pt-0 border-gray-100">
                                    <!-- Edit Button -->
                                    <button type="button" onclick='openEditItemModal(<?= json_encode($prod, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="px-3.5 py-2 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 text-xs font-semibold flex items-center gap-1.5 transition">
                                        <i data-lucide="file-pen-line" class="w-3.5 h-3.5 text-isan-terracotta"></i> แก้ไขข้อมูล
                                    </button>

                                    <!-- Status Toggle Dropdown / Buttons -->
                                    <?php if ($prod['status'] === 'available'): ?>
                                        <!-- Mark as Sold Out -->
                                        <a href="profile.php?item_action=mark_sold&item_id=<?= $prod['id'] ?>#my-products-management" onclick="return confirm('ต้องการปรับสถานะเป็น สินค้าหมด (Sold Out) ใช่หรือไม่?')" class="px-3 py-2 rounded-xl bg-amber-100 hover:bg-amber-200 text-amber-900 text-xs font-semibold flex items-center gap-1.5 transition" title="ปรับสถานะเป็นสินค้าหมด">
                                            <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i> สินค้าหมด
                                        </a>
                                        <!-- Mark as Hidden -->
                                        <a href="profile.php?item_action=mark_hidden&item_id=<?= $prod['id'] ?>#my-products-management" onclick="return confirm('ต้องการปิดประกาศและนำออกจากหน้าตลาดชั่วคราวใช่หรือไม่?')" class="px-3 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold flex items-center gap-1.5 transition" title="ปิดประกาศนำออกจากตลาด">
                                            <i data-lucide="eye-off" class="w-3.5 h-3.5"></i> ปิดประกาศ
                                        </a>
                                    <?php elseif ($prod['status'] === 'sold'): ?>
                                        <!-- Put back to Available -->
                                        <a href="profile.php?item_action=mark_available&item_id=<?= $prod['id'] ?>#my-products-management" class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold flex items-center gap-1.5 transition shadow-xs">
                                            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> เปิดขายใหม่
                                        </a>
                                        <a href="profile.php?item_action=mark_hidden&item_id=<?= $prod['id'] ?>#my-products-management" class="px-3 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold flex items-center gap-1.5 transition">
                                            <i data-lucide="eye-off" class="w-3.5 h-3.5"></i> ปิดประกาศ
                                        </a>
                                    <?php elseif ($prod['status'] === 'hidden'): ?>
                                        <!-- Put back to Available -->
                                        <a href="profile.php?item_action=mark_available&item_id=<?= $prod['id'] ?>#my-products-management" class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold flex items-center gap-1.5 transition shadow-xs">
                                            <i data-lucide="eye" class="w-3.5 h-3.5"></i> เปิดประกาศในตลาด
                                        </a>
                                    <?php endif; ?>

                                    <!-- Delete Item Button -->
                                    <a href="profile.php?item_action=delete_item&item_id=<?= $prod['id'] ?>#my-products-management" onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบรายการสินค้านี้อย่างถาวร?')" class="p-2 rounded-xl text-gray-400 hover:text-red-600 hover:bg-red-50 transition" title="ลบสินค้าถาวร">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Reviews Received by this user (รีวิวที่ได้รับจากผู้อื่น) -->
                    <div class="mt-8 pt-6 border-t border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                    <i data-lucide="message-square-heart" class="w-4 h-4 text-rose-600"></i> รีวิวและคะแนนความพึงพอใจที่ได้รับ (<?= $totalReviewCount ?> รีวิว)
                                </h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <div class="flex items-center text-amber-500">
                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                            <i data-lucide="star" class="w-3.5 h-3.5 <?= $s <= round($avgRating) ? 'fill-amber-400 text-amber-400' : 'text-gray-300' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="text-xs font-bold text-gray-900"><?= number_format($avgRating, 1) ?> จาก 5</span>
                                    <span class="text-[10px] text-gray-400">• รีวิวจากสมาชิกชุมชน</span>
                                </div>
                            </div>
                        </div>

                        <?php if (count($userReviews) === 0): ?>
                        <div class="p-6 text-center bg-gray-50 rounded-2xl border border-gray-100">
                            <p class="text-xs text-gray-400">ยังไม่มีรีวิวสำหรับบัญชีของคุณ เมื่อมีสมาชิกเข้ามาเขียนรีวิวจะแสดงขึ้นที่นี่</p>
                        </div>
                        <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <?php foreach ($userReviews as $rev): ?>
                            <div class="p-3.5 rounded-2xl bg-amber-50/20 border border-gray-200/80 shadow-xs space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <img src="<?= htmlspecialchars($rev['reviewer_avatar']) ?>" alt="<?= htmlspecialchars($rev['reviewer_name']) ?>" class="w-7 h-7 rounded-full object-cover border border-amber-200">
                                        <div>
                                            <span class="text-xs font-bold text-gray-900 block leading-tight"><?= htmlspecialchars($rev['reviewer_name']) ?></span>
                                            <span class="text-[10px] text-gray-400"><?= date('d M Y', strtotime($rev['created_at'])) ?></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-0.5 text-amber-500 bg-amber-50 px-2 py-0.5 rounded-lg border border-amber-100">
                                        <?php for ($star = 1; $star <= 5; $star++): ?>
                                            <i data-lucide="star" class="w-2.5 h-2.5 <?= $star <= (int)$rev['rating'] ? 'fill-amber-500 text-amber-500' : 'text-gray-300' ?>"></i>
                                        <?php endfor; ?>
                                        <span class="text-[10px] font-bold text-amber-800 ml-1"><?= (int)$rev['rating'] ?>.0</span>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-600 leading-relaxed bg-white/70 p-2.5 rounded-xl border border-gray-100">
                                    "<?= nl2br(htmlspecialchars($rev['comment'])) ?>"
                                </p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- Edit Marketplace Item Modal (หน้าต่างแก้ไขข้อมูลสินค้า) -->
    <div id="edit-item-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border border-gray-100 max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                <div>
                    <h3 class="font-bold text-xl text-isan-indigo flex items-center gap-2">
                        <i data-lucide="file-pen-line" class="w-5 h-5 text-isan-terracotta"></i> แก้ไขข้อมูลสินค้า
                    </h3>
                    <p class="text-xs text-gray-500 mt-0.5">ปรับปรุงสเปกเครื่องดนตรี ราคา หรืออัปโหลดรูปภาพใหม่ (สูงสุด 5 รูป)</p>
                </div>
                <button type="button" onclick="closeEditItemModal()" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <form method="POST" action="profile.php#my-products-management" enctype="multipart/form-data" class="mt-6 space-y-4">
                <input type="hidden" name="edit_item_submit" value="1">
                <input type="hidden" name="edit_item_id" id="edit-prod-id">

                <!-- Title -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">ชื่อรายการสินค้า <span class="text-red-500">*</span></label>
                    <input type="text" name="title" id="edit-prod-title" required class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <!-- Type & Key -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">ประเภทเครื่องดนตรี</label>
                        <select name="item_type" id="edit-prod-type" class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                            <option value="khaen_ped">แคนแปด (16 ลำ - มาตรฐาน)</option>
                            <option value="khaen_jet">แคนเจ็ด (14 ลำ - สำหรับผู้เริ่มต้น)</option>
                            <option value="khaen_kao">แคนเก้า (18 ลำ - คอนเสิร์ต)</option>
                            <option value="accessories">อุปกรณ์ / ลิ้นแคน / เต้าแคน / ขี้สูตร</option>
                            <option value="other">เครื่องดนตรีอีสานอื่นๆ</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">คีย์เสียง (Key Note)</label>
                        <select name="key_note" id="edit-prod-key" class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                            <option value="Am">Am (มาตรฐานวงหมอลำ)</option>
                            <option value="Dm">Dm (หมอลำเพลิน)</option>
                            <option value="C">C (สากล)</option>
                            <option value="G">G (สากล)</option>
                            <option value="F">F</option>
                            <option value="All">ทุกคีย์ / อุปกรณ์เสริม</option>
                        </select>
                    </div>
                </div>

                <!-- Wood & Reed -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">ชนิดไม้กู่แคน</label>
                        <input type="text" name="wood_type" id="edit-prod-wood" class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">ชนิดลิ้นแคน</label>
                        <select name="reed_type" id="edit-prod-reed" class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                            <option value="ลิ้นเงินแท้">ลิ้นเงินแท้</option>
                            <option value="ลิ้นทองเหลืองเกรด A">ลิ้นทองเหลืองเกรด A</option>
                            <option value="ลิ้นทองแดงผสม">ลิ้นทองแดงผสม</option>
                        </select>
                    </div>
                </div>

                <!-- Tuning & Maker -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">มาตรฐานเทียบเสียง</label>
                        <select name="pitch_standard" id="edit-prod-pitch" class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                            <option value="A440 Hz">A440 Hz (เทียบเสียงดิจิทัล)</option>
                            <option value="ลายโบราณพื้นบ้าน">ลายโบราณพื้นบ้าน</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">ชื่อช่างผู้ทำ / แบรนด์</label>
                        <input type="text" name="maker_name" id="edit-prod-maker" class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                </div>

                <!-- Price, Shipping, Condition, Status -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">ราคา (บาท) <span class="text-red-500">*</span></label>
                        <input type="number" step="1" name="price" id="edit-prod-price" required class="w-full text-sm border border-gray-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">ค่าจัดส่ง (บาท)</label>
                        <input type="number" step="1" name="shipping_cost" id="edit-prod-shipping" class="w-full text-sm border border-gray-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">สภาพสินค้า</label>
                        <select name="condition_status" id="edit-prod-condition" class="w-full text-sm border border-gray-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                            <option value="new">ของใหม่</option>
                            <option value="used">มือสอง</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">สถานะสินค้า</label>
                        <select name="status" id="edit-prod-status" class="w-full text-sm border border-gray-300 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white font-semibold">
                            <option value="available">พร้อมขาย (Available)</option>
                            <option value="sold">สินค้าหมด (Sold Out)</option>
                            <option value="hidden">ปิดประกาศ (Hidden)</option>
                        </select>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">คำอธิบายเพิ่มเติม</label>
                    <textarea name="description" id="edit-prod-desc" rows="3" class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none"></textarea>
                </div>

                <!-- Upload New Images (Optional) -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        อัปโหลดรูปภาพใหม่ (เลือกได้สูงสุด 5 รูป - เว้นว่างไว้หากไม่ต้องการเปลี่ยน)
                    </label>
                    <input type="file" name="edit_images[]" multiple accept="image/*" class="w-full text-xs text-gray-500 file:mr-3 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-800 hover:file:bg-amber-100">
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                    <button type="button" onclick="closeEditItemModal()" class="px-5 py-2.5 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-xl">ยกเลิก</button>
                    <button type="submit" class="px-6 py-2.5 text-xs font-semibold text-white bg-isan-terracotta hover:bg-amber-700 rounded-xl shadow-md">บันทึกข้อมูลสินค้า</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-isan-indigo text-gray-300 py-10 mt-auto border-t border-amber-950">
        <div class="w-full px-4 sm:px-6 lg:px-10 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div>
                <p class="font-bold text-white text-base">SoundRadar — ชุมชนแคนอีสาน</p>
                <p class="text-xs text-gray-400 mt-1">โปรไฟล์สมาชิกและระบบหมุดพิกัด GPS อัจฉริยะ</p>
            </div>
            <div class="text-xs text-gray-400">
                PHP PDO • Leaflet.js Geolocation
            </div>
        </div>
    </footer>

    <!-- Leaflet Coordinate Picker Script -->
    <script>
        lucide.createIcons();

        const defaultLat = <?= !empty($userData['latitude']) ? (float)$userData['latitude'] : '16.4322' ?>;
        const defaultLng = <?= !empty($userData['longitude']) ? (float)$userData['longitude'] : '102.8236' ?>;

        // Initialize Map
        const pickerMap = L.map('picker-map').setView([defaultLat, defaultLng], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(pickerMap);

        const isOwn = <?= $isOwnProfile ? 'true' : 'false' ?>;

        // Marker for Current Coordinate
        let pickerMarker = L.marker([defaultLat, defaultLng], {
            draggable: isOwn
        }).addTo(pickerMap);

        if (!isOwn) {
            pickerMarker.bindPopup('<div class="p-2 text-xs font-bold text-gray-900"><?= htmlspecialchars(addslashes($userData['name'])) ?></div>').openPopup();
        }

        if (isOwn) {
            // Update Inputs on Drag
            pickerMarker.on('dragend', function(e) {
                const pos = e.target.getLatLng();
                const latInp = document.getElementById('prof-lat');
                const lngInp = document.getElementById('prof-lng');
                if (latInp) latInp.value = pos.lat.toFixed(6);
                if (lngInp) lngInp.value = pos.lng.toFixed(6);
            });

            // Click on map to move marker
            pickerMap.on('click', function(e) {
                pickerMarker.setLatLng(e.latlng);
                const latInp = document.getElementById('prof-lat');
                const lngInp = document.getElementById('prof-lng');
                if (latInp) latInp.value = e.latlng.lat.toFixed(6);
                if (lngInp) lngInp.value = e.latlng.lng.toFixed(6);
            });
        }

        // Get GPS from Browser
        function getDeviceGPS() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    pos => {
                        const lat = pos.coords.latitude;
                        const lng = pos.coords.longitude;
                        document.getElementById('prof-lat').value = lat.toFixed(6);
                        document.getElementById('prof-lng').value = lng.toFixed(6);
                        pickerMarker.setLatLng([lat, lng]);
                        pickerMap.setView([lat, lng], 14);
                    },
                    err => {
                        alert('ไม่สามารถดึงพิกัดจากอุปกรณ์ได้: ' + err.message);
                    },
                    { enableHighAccuracy: true }
                );
            } else {
                alert('เบราว์เซอร์ไม่รองรับ GPS');
            }
        }

        // Review Form Toggle & Star Rating Selection Handlers
        let selectedRating = 5;
        const ratingLabels = {
            1: '1 ดาว (ต้องปรับปรุง)',
            2: '2 ดาว (พอใช้)',
            3: '3 ดาว (ปานกลาง)',
            4: '4 ดาว (ดีมาก)',
            5: '5 ดาว (ดีเยี่ยมมาก)'
        };

        function toggleReviewForm() {
            const box = document.getElementById('review-form-box');
            if (box) {
                box.classList.toggle('hidden');
                if (!box.classList.contains('hidden')) {
                    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        }

        function setRating(val) {
            selectedRating = val;
            const input = document.getElementById('review-rating-input');
            if (input) input.value = val;
            updateStarDisplay(val);
            const label = document.getElementById('rating-label');
            if (label) label.innerText = ratingLabels[val] || `${val} ดาว`;
        }

        function hoverRating(val) {
            updateStarDisplay(val);
            const label = document.getElementById('rating-label');
            if (label) label.innerText = ratingLabels[val] || `${val} ดาว`;
        }

        function resetHoverRating() {
            updateStarDisplay(selectedRating);
            const label = document.getElementById('rating-label');
            if (label) label.innerText = ratingLabels[selectedRating] || `${selectedRating} ดาว`;
        }

        function updateStarDisplay(val) {
            const buttons = document.querySelectorAll('.star-btn');
            buttons.forEach(btn => {
                const btnVal = parseInt(btn.getAttribute('data-val'));
                const icon = btn.querySelector('svg');
                if (icon) {
                    if (btnVal <= val) {
                        icon.classList.add('fill-amber-400');
                        btn.classList.add('text-amber-400');
                        btn.classList.remove('text-gray-300');
                    } else {
                        icon.classList.remove('fill-amber-400');
                        btn.classList.remove('text-amber-400');
                        btn.classList.add('text-gray-300');
                    }
                }
            });
        }

        // Edit Item Modal Functions
        function openEditItemModal(prod) {
            if (!prod) return;
            document.getElementById('edit-prod-id').value = prod.id;
            document.getElementById('edit-prod-title').value = prod.title || '';
            document.getElementById('edit-prod-type').value = prod.item_type || 'khaen_ped';
            document.getElementById('edit-prod-key').value = prod.key_note || 'Am';
            document.getElementById('edit-prod-wood').value = prod.wood_type || '';
            document.getElementById('edit-prod-reed').value = prod.reed_type || 'ลิ้นเงินแท้';
            document.getElementById('edit-prod-pitch').value = prod.pitch_standard || 'A440 Hz';
            document.getElementById('edit-prod-maker').value = prod.maker_name || '';
            document.getElementById('edit-prod-price').value = Math.round(prod.price || 0);
            document.getElementById('edit-prod-shipping').value = Math.round(prod.shipping_cost || 0);
            document.getElementById('edit-prod-condition').value = prod.condition_status || 'new';
            document.getElementById('edit-prod-status').value = prod.status || 'available';
            document.getElementById('edit-prod-desc').value = prod.description || '';

            document.getElementById('edit-item-modal').classList.remove('hidden');
            lucide.createIcons();
        }

        function closeEditItemModal() {
            document.getElementById('edit-item-modal').classList.add('hidden');
        }

        // Close edit modal on background click
        document.getElementById('edit-item-modal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditItemModal();
            }
        });

        // Showcase Lightbox & Management Functions
        function openShowcaseLightbox(imgUrl) {
            const modal = document.getElementById('showcase-lightbox-modal');
            const img = document.getElementById('showcase-lightbox-img');
            if (modal && img) {
                img.src = imgUrl;
                modal.classList.remove('hidden');
                lucide.createIcons();
            }
        }

        function closeShowcaseLightbox() {
            const modal = document.getElementById('showcase-lightbox-modal');
            if (modal) modal.classList.add('hidden');
        }

        document.getElementById('showcase-lightbox-modal')?.addEventListener('click', function(e) {
            if (e.target === this) closeShowcaseLightbox();
        });

        function removeExistingShowcase(idx) {
            const thumb = document.getElementById('sc-thumb-' + idx);
            if (thumb) {
                thumb.remove();
                updateShowcaseCount();
            }
        }

        function updateShowcaseCount() {
            const existing = document.querySelectorAll('#existing-showcase-container > div').length;
            const badge = document.getElementById('showcase-count-badge');
            if (badge) {
                badge.innerText = `${existing}/6 รูป`;
            }
        }

        function handleShowcaseFilesSelection(e) {
            const files = Array.from(e.target.files);
            const previewContainer = document.getElementById('new-showcase-preview');
            if (!previewContainer) return;

            previewContainer.innerHTML = '';
            if (files.length === 0) {
                previewContainer.classList.add('hidden');
                return;
            }

            previewContainer.classList.remove('hidden');
            files.slice(0, 6).forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    const div = document.createElement('div');
                    div.className = 'relative rounded-xl overflow-hidden border-2 border-amber-500 aspect-square bg-gray-50 shadow-xs';
                    div.innerHTML = `
                        <img src="${evt.target.result}" class="w-full h-full object-cover">
                        <span class="absolute bottom-1 left-1 text-[9px] bg-amber-700 text-white px-1.5 py-0.5 rounded font-semibold">ใหม่ #${index + 1}</span>
                    `;
                    previewContainer.appendChild(div);
                };
                reader.readAsDataURL(file);
            });

            const existingCount = document.querySelectorAll('#existing-showcase-container > div').length;
            const totalCount = Math.min(6, existingCount + files.length);
            const badge = document.getElementById('showcase-count-badge');
            if (badge) {
                badge.innerText = `${totalCount}/6 รูป`;
            }
        }
    </script>

    <!-- Showcase Lightbox Modal -->
    <div id="showcase-lightbox-modal" class="fixed inset-0 bg-black/85 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
        <div class="relative max-w-4xl w-full flex flex-col items-center">
            <button onclick="closeShowcaseLightbox()" class="absolute -top-12 right-0 w-10 h-10 rounded-full bg-white/20 hover:bg-white/40 text-white flex items-center justify-center transition">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
            <img id="showcase-lightbox-img" src="" class="max-h-[85vh] max-w-full rounded-2xl shadow-2xl object-contain border border-white/20">
        </div>
    </div>
</body>
</html>
