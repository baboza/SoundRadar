<?php
/**
 * Database Migration: Add detailed fields to marketplace_items
 */
require_once __DIR__ . '/db.php';

try {
    $columnsToAdd = [
        "ADD COLUMN `wood_type` VARCHAR(100) DEFAULT 'ไม้กู่แคน (ไม้ซาง)' AFTER `key_note`",
        "ADD COLUMN `reed_type` VARCHAR(100) DEFAULT 'ลิ้นเงินแท้' AFTER `wood_type`",
        "ADD COLUMN `pitch_standard` VARCHAR(50) DEFAULT 'A440 Hz' AFTER `reed_type`",
        "ADD COLUMN `maker_name` VARCHAR(150) DEFAULT NULL AFTER `pitch_standard`",
        "ADD COLUMN `shipping_cost` DECIMAL(10, 2) DEFAULT 100.00 AFTER `price`",
        "ADD COLUMN `contact_line` VARCHAR(100) DEFAULT NULL AFTER `description`",
        "ADD COLUMN `contact_phone` VARCHAR(50) DEFAULT NULL AFTER `contact_line`"
    ];

    foreach ($columnsToAdd as $colSql) {
        try {
            $pdo->exec("ALTER TABLE `marketplace_items` " . $colSql);
        } catch (\PDOException $ex) {
            // Column may already exist, ignore duplicate column errors
        }
    }

    echo json_encode(['status' => 'success', 'message' => 'Marketplace schema enhanced successfully!']);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
