<?php
/**
 * API Endpoint: get_locations.php
 * Fetches users with valid GPS coordinates (latitude, longitude)
 * Supports query filter: ?category=maker|player|shop|all
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';

try {
    $category = isset($_GET['category']) ? trim($_GET['category']) : 'all';
    
    // Base SQL query selecting users with coordinates
    $sql = "SELECT 
                id, 
                name, 
                email, 
                phone, 
                category, 
                title_desc, 
                bio, 
                avatar, 
                address, 
                province, 
                latitude, 
                longitude, 
                is_verified 
            FROM users 
            WHERE latitude IS NOT NULL 
              AND longitude IS NOT NULL 
              AND status = 'active'
              AND pin_status = 'approved'";

    $params = [];

    // Optional category filtering (Supports both single or multi-category users)
    if ($category !== 'all' && in_array($category, ['maker', 'player', 'shop'])) {
        $sql .= " AND (category = :category OR FIND_IN_SET(:category, category) > 0 OR category LIKE :category_like)";
        $params[':category'] = $category;
        $params[':category_like'] = "%{$category}%";
    }

    $sql .= " ORDER BY is_verified DESC, id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Map and enrich data for frontend Leaflet.js
    $locations = array_map(function($row) {
        $categoryNames = [
            'maker'  => 'ช่างแคน (Khaen Maker)',
            'player' => 'หมอแคน (Khaen Player)',
            'shop'   => 'ร้านขายแคน (Khaen Shop)'
        ];

        $categoryColors = [
            'maker'  => '#d97706', // Warm amber / bamboo wood
            'player' => '#1e3a8a', // Deep indigo / Mo-hom
            'shop'   => '#c2410c'  // Terracotta
        ];

        return [
            'id'             => (int)$row['id'],
            'name'           => $row['name'],
            'phone'          => $row['phone'] ?? 'ไม่ระบุเบอร์โทร',
            'category'       => $row['category'],
            'category_name'  => $categoryNames[$row['category']] ?? $row['category'],
            'badge_color'    => $categoryColors[$row['category']] ?? '#4b5563',
            'title_desc'     => $row['title_desc'] ?? '',
            'bio'            => $row['bio'] ?? '',
            'avatar'         => $row['avatar'] ?: 'assets/images/default-avatar.png',
            'address'        => $row['address'] ?? '',
            'province'       => $row['province'] ?? '',
            'lat'            => (float)$row['latitude'],
            'lng'            => (float)$row['longitude'],
            'is_verified'    => (bool)$row['is_verified']
        ];
    }, $rows);

    echo json_encode([
        'status'  => 'success',
        'count'   => count($locations),
        'data'    => $locations
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Failed to fetch location data: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
