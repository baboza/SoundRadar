<?php
/**
 * API Endpoint: add_item.php
 * Handles creating new marketplace items with comprehensive instrument specs
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db.php';

$currentUser = getCurrentUser();

if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนลงประกาศขาย'], JSON_UNESCAPED_UNICODE);
    exit;
}

$title = trim($_POST['title'] ?? '');
$item_type = trim($_POST['item_type'] ?? 'khaen_ped');
$key_note = trim($_POST['key_note'] ?? 'Am');
$wood_type = trim($_POST['wood_type'] ?? 'ไม้กู่แคน (ไม้ซาง)');
$reed_type = trim($_POST['reed_type'] ?? 'ลิ้นเงินแท้');
$pitch_standard = trim($_POST['pitch_standard'] ?? 'A440 Hz');
$maker_name = trim($_POST['maker_name'] ?? '');
$price = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
$shipping_cost = filter_var($_POST['shipping_cost'] ?? 0, FILTER_VALIDATE_FLOAT);
$condition_status = trim($_POST['condition_status'] ?? 'new');
$contact_phone = trim($_POST['contact_phone'] ?? '');
$contact_line = trim($_POST['contact_line'] ?? '');
$description = trim($_POST['description'] ?? '');

if (empty($title) || $price === false || $price <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกชื่อสินค้าและราคาที่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Handle up to 5 uploaded images (or fallback sample)
$defaultSampleUrl = 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=600&q=80';
$uploadedImages = [];
$uploadDir = __DIR__ . '/uploads/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// 1. Check for multi-file upload under 'images' (up to 5 images)
if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
    $countFiles = min(count($_FILES['images']['name']), 5);
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    for ($i = 0; $i < $countFiles; $i++) {
        if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['images']['tmp_name'][$i];
            $origName = $_FILES['images']['name'][$i];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            if (in_array($ext, $allowedExtensions)) {
                $newFileName = 'khaen_' . time() . '_' . rand(1000, 9999) . '_' . ($i + 1) . '.' . $ext;
                $destPath = $uploadDir . $newFileName;
                if (move_uploaded_file($tmpPath, $destPath)) {
                    $uploadedImages[] = 'uploads/' . $newFileName;
                }
            }
        }
    }
}

// 2. Backward compatibility: check single 'image' input if no multi-files were uploaded
if (empty($uploadedImages) && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['image']['tmp_name'];
    $fileName = $_FILES['image']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (in_array($fileExtension, $allowedExtensions)) {
        $newFileName = 'khaen_' . time() . '_' . rand(100, 999) . '.' . $fileExtension;
        $destPath = $uploadDir . $newFileName;
        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $uploadedImages[] = 'uploads/' . $newFileName;
        }
    }
}

// If no images uploaded, use default sample image
if (empty($uploadedImages)) {
    $uploadedImages[] = $defaultSampleUrl;
}

// Primary thumbnail and JSON array of all images (up to 5)
$imageUrl = $uploadedImages[0];
$imagesJson = json_encode(array_values($uploadedImages), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

try {
    $stmt = $pdo->prepare("
        INSERT INTO marketplace_items (
            user_id, title, item_type, key_note, wood_type, reed_type, pitch_standard, 
            maker_name, price, shipping_cost, condition_status, contact_phone, contact_line, 
            description, image_url, images, status
        ) VALUES (
            :user_id, :title, :item_type, :key_note, :wood_type, :reed_type, :pitch_standard,
            :maker_name, :price, :shipping_cost, :condition_status, :contact_phone, :contact_line,
            :description, :image_url, :images, 'available'
        )
    ");

    $stmt->execute([
        ':user_id' => $currentUser['id'],
        ':title' => $title,
        ':item_type' => $item_type,
        ':key_note' => $key_note,
        ':wood_type' => $wood_type,
        ':reed_type' => $reed_type,
        ':pitch_standard' => $pitch_standard,
        ':maker_name' => $maker_name,
        ':price' => $price,
        ':shipping_cost' => $shipping_cost !== false ? $shipping_cost : 0,
        ':condition_status' => $condition_status,
        ':contact_phone' => $contact_phone,
        ':contact_line' => $contact_line,
        ':description' => $description,
        ':image_url' => $imageUrl,
        ':images' => $imagesJson
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'ลงประกาศขายแคนพร้อมรายละเอียดครบถ้วนสำเร็จแล้ว!',
        'item_id' => (int)$pdo->lastInsertId()
    ], JSON_UNESCAPED_UNICODE);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'บันทึกข้อมูลไม่สำเร็จ: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
