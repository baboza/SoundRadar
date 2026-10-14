-- Database Schema for Khaen Community Web Application
-- Database: khaen_community (or khaen_complex_center)

CREATE DATABASE IF NOT EXISTS `khaen_community` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `khaen_community`;

-- Drop existing tables if needed
DROP TABLE IF EXISTS `user_reviews`;
DROP TABLE IF EXISTS `marketplace_items`;
DROP TABLE IF EXISTS `users`;

-- 1. Users Table (Supports Map Pinning, Roles, and Authentication)
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `phone` VARCHAR(50) DEFAULT NULL,
    `password` VARCHAR(255) DEFAULT NULL, -- Hashed password (or NULL for Google SSO)
    `google_id` VARCHAR(100) DEFAULT NULL,
    `role` ENUM('admin', 'user') DEFAULT 'user',
    `category` VARCHAR(150) NOT NULL DEFAULT 'player', 
    -- maker: ช่างแคน, player: หมอแคน, shop: ร้านขายแคน, learning_center: ศูนย์การเรียนรู้แคน
    `title_desc` VARCHAR(255) DEFAULT NULL, -- e.g. "ช่างแคนลายโบราณ 50 ปี", "หมอแคนรับงานแห่/คอนเสิร์ต"
    `bio` TEXT DEFAULT NULL,
    `avatar` VARCHAR(255) DEFAULT 'assets/images/default-avatar.png',
    `address` VARCHAR(255) DEFAULT NULL,
    `province` VARCHAR(100) DEFAULT 'ขอนแก่น',
    `latitude` DECIMAL(10, 8) DEFAULT NULL,
    `longitude` DECIMAL(11, 8) DEFAULT NULL,
    `is_verified` TINYINT(1) DEFAULT 0,
    `status` ENUM('active', 'suspended') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Marketplace Items Table (ตลาดซื้อขายแคน / อุปกรณ์ดนตรีอีสาน)
CREATE TABLE `marketplace_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `item_type` ENUM('khaen_ped', 'khaen_jet', 'khaen_kao', 'accessories', 'other') DEFAULT 'khaen_ped', 
    -- khaen_ped: แคนแปด, khaen_jet: แคนเจ็ด, khaen_kao: แคนเก้า, accessories: ลิ้นแคน/เต้าแคน/สูตร
    `key_note` VARCHAR(10) DEFAULT 'Am', -- Key Am, Dm, C, G, etc.
    `price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `condition_status` ENUM('new', 'used') DEFAULT 'new',
    `description` TEXT DEFAULT NULL,
    `image_url` VARCHAR(255) DEFAULT 'assets/images/sample-khaen.jpg',
    `status` ENUM('available', 'sold', 'hidden') DEFAULT 'available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_marketplace_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index for geolocation queries & filtering
CREATE INDEX `idx_users_category` ON `users`(`category`);
CREATE INDEX `idx_users_lat_lng` ON `users`(`latitude`, `longitude`);
CREATE INDEX `idx_marketplace_status` ON `marketplace_items`(`status`);

-- -----------------------------------------------------------------------------
-- Mock Data (Isan region focus: Khon Kaen, Roi Et, Kalasin, Ubon Ratchathani)
-- -----------------------------------------------------------------------------

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `role`, `category`, `title_desc`, `bio`, `avatar`, `address`, `province`, `latitude`, `longitude`, `is_verified`) VALUES
(1, 'แอดมิน ชุมชนคนรักแคน', 'admin@khaencomplex.local', '081-000-0001', 'admin', 'player', 'ผู้ดูแลระบบ & หมอแคนอิสระ', 'ดูแลระบบและเผยแพร่วัฒนธรรมดนตรีแคนอีสาน', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=400&q=80', 'อ.เมือง', 'ขอนแก่น', 16.4322, 102.8236, 1),

(2, 'ช่างพ่อบุญมี สืบสานลายแคน', 'boonmee@maker.local', '089-123-4567', 'user', 'maker', 'ปรมาจารย์ช่างแคนไม้กู่แคน ลิ้นเงินแท้', 'ทำแคนมานานกว่า 40 ปี เชี่ยวชาญแคนแปด คีย์ Am/Dm สำหรับวงหมอลำและนักสะสม', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=400&q=80', 'บ.สีถาน ต.บ้านเป็ด อ.เมือง', 'ขอนแก่น', 16.4419, 102.7981, 1),

(3, 'ช่างคำผาย เสียงเสนาะ', 'khamphai@maker.local', '084-567-8901', 'user', 'maker', 'ช่างแคนร้อยเอ็ด ประณีต ทรงคุณค่า', 'คัดสรรไม้กู่แคนเนื้อดี ลิ้นทองเหลืองสปริง นุ่มเป่าง่าย ไม่เปลืองลม', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=400&q=80', 'อ.โพนทอง', 'ร้อยเอ็ด', 16.2974, 103.9782, 1),

(4, 'หมอแคนทองสุข ลายโปงลาง', 'thongsuk@player.local', '086-789-0123', 'user', 'player', 'หมอแคนอาชีพ รับงานแห่และบันทึกเสียง', 'ประสบการณ์เป่าแคนวงหมอลำ 20 ปี รับงานแสดง ทั่วภาคอีสานและกรุงเทพฯ', 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=400&q=80', 'อ.กมลาไสย', 'กาฬสินธุ์', 16.3359, 103.5750, 1),

(5, 'หมอแคนน้อย อินดี้', 'noikhaen@player.local', '092-345-6789', 'user', 'player', 'หมอแคนรุ่นใหม่ ฟิวชั่นสากล & ลายโบราณ', 'สอนเป่าแคนเบื้องต้น รับสอนออนไลน์ สอนตัวต่อตัว และร่วมแจมงานดนตรี', 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=400&q=80', 'ใกล้มหาวิทยาลัยขอนแก่น', 'ขอนแก่น', 16.4744, 102.8277, 0),

(6, 'ร้านอีสานเครื่องดนตรีไทย (เฮียสมศักดิ์)', 'esanshop@shop.local', '043-221-999', 'user', 'shop', 'ศูนย์รวมแคน พิณ โหวต โปงลาง ครบวงจร', 'จำหน่ายแคนทุกขนาด ลิ้นแคนแท้ ขี้สูตร ไม้ซาง พร้อมจัดส่งทั่วประเทศและต่างประเทศ', 'https://images.unsplash.com/photo-1556742049-0a67c5574f73?auto=format&fit=crop&w=400&q=80', 'ตลาดเทศบาล 1 ถ.กลางเมือง', 'ขอนแก่น', 16.4285, 102.8361, 1),

(7, 'แคนสยาม มิวสิค อุบลฯ', 'khaensiam@shop.local', '081-888-9999', 'user', 'shop', 'ร้านจำหน่ายและปรับแต่งคีย์แคนมาตรฐานสากล', 'บริการเทียบเสียงแคนจูนเนอร์ 440Hz จำหน่ายแคนคอนเสิร์ตเกรดพรีเมียม', 'https://images.unsplash.com/photo-1578357078586-491adf1aa5c7?auto=format&fit=crop&w=400&q=80', 'ต.ในเมือง อ.เมือง', 'อุบลราชธานี', 15.2287, 104.8564, 1),
(8, 'ศูนย์การเรียนรู้ภูมิปัญญาแคนและดนตรีอีสาน', 'learning@khaencomplex.local', '043-754-321', 'user', 'learning_center', 'ศูนย์อนุรักษ์ สืบสาน และถ่ายทอดการทำแคนอีสาน', 'แหล่งเรียนรู้สำหรับเยาวชนและผู้สนใจ ศึกษาขั้นตอนการทำแคน ลายแคนโบราณ และการเทียบเสียงดนตรีพื้นบ้าน', 'https://images.unsplash.com/photo-1524178232363-1fb2b075b655?auto=format&fit=crop&w=400&q=80', 'วิทยาลัยดุริยางคศิลป์ ม.มหาสารคาม ต.ขามเรียง อ.กันทรวิชัย', 'มหาสารคาม', 16.2468, 103.2502, 1);

-- Mock Marketplace Items
INSERT INTO `marketplace_items` (`id`, `user_id`, `title`, `item_type`, `key_note`, `price`, `condition_status`, `description`, `image_url`, `status`) VALUES
(1, 2, 'แคนแปด ลายโบราณ คีย์ Am ไม้กู่แคนคัดพิเศษ', 'khaen_ped', 'Am', 2800.00, 'new', 'แคนแปดมาตรฐาน เสียงใส ดังกังวาน คีย์สากล Am เหมาะสำหรับขึ้นเวที เป่าง่ายประหยัดลม ปรับแต่งโดยช่างบุญมี ขอนแก่น', 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=600&q=80', 'available'),
(2, 3, 'แคนเจ็ด คีย์ Dm เหมาะสำหรับเยาวชนและผู้เริ่มต้น', 'khaen_jet', 'Dm', 1500.00, 'new', 'แคนเจ็ดขนาดกระชับมือ น้ำหนักเบา ลิ้นเงินแท้ เสียงตรงคีย์ เรียนรู้ง่าย มีตารางโน้ตแถมให้ฟรี', 'https://images.unsplash.com/photo-1520523839898-507125cd53c1?auto=format&fit=crop&w=600&q=80', 'available'),
(3, 6, 'ชุดลิ้นแคนทองเหลืองเกรด A (16 ลิ้น) พร้อมขี้สูตรแท้', 'accessories', 'All', 450.00, 'new', 'ลิ้นแคนรีดบางระดับพรีเมียม สปริงตัวดีเยี่ยม พร้อมขี้สูตรชันโรงธรรมชาติแท้ 1 ก้อนใหญ่ สำหรับซ่อมแซมหรือเปลี่ยนลิ้นแคน', 'https://images.unsplash.com/photo-1520697830682-bbb6e85e2b0b?auto=format&fit=crop&w=600&q=80', 'available'),
(4, 5, 'แคนแปด คีย์ C (มือสอง สภาพ 95% ซ่อมจูนใหม่เรียบร้อย)', 'khaen_ped', 'C', 1900.00, 'used', 'ใช้งานเองตอนทำวิจัยดนตรี 3 เดือน ดูแลรักษาอย่างดีในกล่องกันชื้น เสียงหวานนุ่ม คีย์ C หายาก', 'https://images.unsplash.com/photo-1465847899084-d164df4dedc6?auto=format&fit=crop&w=600&q=80', 'available');
