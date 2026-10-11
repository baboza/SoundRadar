<?php
/**
 * One-click Database Migration and Setup Script
 */
$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    // 1. Connect to MySQL Server (without selecting database yet)
    $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // 2. Read schema.sql
    $sqlFile = __DIR__ . '/schema.sql';
    if (!file_exists($sqlFile)) {
        die("Error: schema.sql not found at " . $sqlFile);
    }

    $sql = file_get_contents($sqlFile);

    // 3. Execute SQL batch
    $pdo->exec($sql);

    // 4. Verify tables
    $pdo->exec("USE `khaen_community`");
    $usersCount = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
    $itemsCount = $pdo->query("SELECT COUNT(*) FROM `marketplace_items`")->fetchColumn();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'success',
        'message' => 'Database initialized successfully!',
        'database' => 'khaen_community',
        'users_count' => (int)$usersCount,
        'items_count' => (int)$itemsCount
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database migration failed: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
