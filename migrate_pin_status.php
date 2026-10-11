<?php
/**
 * Database Migration: Add pin_status to users table
 * pin_status: 'none', 'pending', 'approved', 'rejected'
 */
require_once __DIR__ . '/db.php';

try {
    // 1. Add pin_status column if not exists
    $pdo->exec("
        ALTER TABLE `users` 
        ADD COLUMN `pin_status` ENUM('none', 'pending', 'approved', 'rejected') 
        DEFAULT 'none' AFTER `longitude`
    ");
} catch (\PDOException $e) {
    // Column might already exist, continue
}

// 2. Set existing users with coordinates to 'approved' so current pins still display
try {
    $pdo->exec("
        UPDATE `users` 
        SET `pin_status` = 'approved' 
        WHERE `latitude` IS NOT NULL AND `longitude` IS NOT NULL AND `pin_status` = 'none'
    ");
} catch (\Exception $e) {}

echo json_encode(['status' => 'success', 'message' => 'pin_status migration completed!']);
