<?php
/**
 * Database Connection using PDO
 * Clean, secure, and configured for UTF-8 (Thai language support)
 */

$db_host = '127.0.0.1';
$db_port = '3306';
$db_name = 'khaen_community';
$db_user = 'root';
$db_pass = ''; // Default XAMPP password is empty

$dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);

    // Auto-migration checks for columns if they don't exist
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS pin_status ENUM('none', 'pending', 'approved', 'rejected') DEFAULT 'approved'");
    } catch (\Exception $ex) {}

    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS showcase_images TEXT NULL");
    } catch (\Exception $ex) {}

    try {
        $pdo->exec("ALTER TABLE marketplace_items ADD COLUMN IF NOT EXISTS images TEXT NULL");
    } catch (\Exception $ex) {}

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `user_reviews` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `target_user_id` INT NOT NULL,
                `reviewer_id` INT NULL,
                `reviewer_name` VARCHAR(150) NOT NULL,
                `reviewer_avatar` VARCHAR(255) DEFAULT 'assets/images/default-avatar.png',
                `rating` TINYINT NOT NULL DEFAULT 5,
                `comment` TEXT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (`target_user_id`),
                CONSTRAINT `fk_review_target_user` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (\Exception $ex) {}
} catch (\PDOException $e) {
    // If request is an API request, return JSON error
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Database connection failed: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Otherwise show friendly error for webpage
    die("<div style='font-family: sans-serif; padding: 2rem; background: #fff5f5; border: 1px solid #feb2b2; border-radius: 8px; max-width: 600px; margin: 40px auto;'>
            <div style='display: flex; align-items: center; gap: 8px; margin-bottom: 8px;'>
                <svg style='width: 24px; height: 24px; color: #c53030;' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/></svg>
                <h3 style='color: #c53030; margin: 0;'>เชื่อมต่อฐานข้อมูลไม่สำเร็จ (Database Connection Failed)</h3>
            </div>
            <p>กรุณาตรวจสอบว่าเปิด MySQL ใน XAMPP Control Panel แล้ว และสร้างฐานข้อมูล <code>khaen_community</code> หรือรันไฟล์ <code>schema.sql</code> แล้วหรือไม่</p>
            <p style='color: #718096; font-size: 0.875rem;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>
         </div>");
}
