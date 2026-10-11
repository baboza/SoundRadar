<?php
/**
 * API Endpoint: update_location.php
 * Allows a logged-in user to update their GPS coordinates (latitude, longitude)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db.php';

$currentUser = getCurrentUser();

if (!$currentUser) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'กรุณาเข้าสู่ระบบก่อนทำการอัปเดตพิกัด'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['latitude']) || !isset($input['longitude'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'พิกัดละติจูดและลองจิจูดไม่ถูกต้อง'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$lat = filter_var($input['latitude'], FILTER_VALIDATE_FLOAT);
$lng = filter_var($input['longitude'], FILTER_VALIDATE_FLOAT);

if ($lat === false || $lng === false) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'รูปแบบค่าพิกัดต้องเป็นตัวเลขทศนิยม'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pinStatus = ($currentUser['role'] === 'admin') ? 'approved' : 'pending';

    $stmt = $pdo->prepare("UPDATE users SET latitude = :lat, longitude = :lng, pin_status = :pin_status WHERE id = :id");
    $stmt->execute([
        ':lat' => $lat,
        ':lng' => $lng,
        ':pin_status' => $pinStatus,
        ':id'  => $currentUser['id']
    ]);

    // Update in-memory session
    $_SESSION['user']['lat'] = $lat;
    $_SESSION['user']['lng'] = $lng;

    $msg = ($pinStatus === 'pending') 
        ? 'ขอปักหมุดพิกัดสำเร็จ! พิกัดของคุณจะแสดงบนแผนที่เมื่อได้รับการอนุมัติจากผู้ดูแลระบบ' 
        : 'อัปเดตพิกัด GPS สำเร็จแล้ว';

    echo json_encode([
        'status' => 'success',
        'message' => $msg,
        'pin_status' => $pinStatus,
        'latitude' => $lat,
        'longitude' => $lng
    ], JSON_UNESCAPED_UNICODE);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่สามารถบันทึกพิกัดลงฐานข้อมูลได้: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
