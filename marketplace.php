<?php
/**
 * Dedicated Marketplace Page - SoundRadar
 * Full-screen desktop layout, advanced filters, detailed product cards and posting modal
 */
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db.php';

$currentUser = getCurrentUser();

// Filters from GET query
$filterCategory = trim($_GET['category'] ?? 'all');
$filterKey = trim($_GET['key_note'] ?? 'all');
$filterCondition = trim($_GET['condition'] ?? 'all');
$searchQuery = trim($_GET['q'] ?? '');

// Base SQL
$sql = "
    SELECT m.*, u.name as seller_name, u.province as seller_province, u.phone as seller_phone, u.avatar as seller_avatar, u.category as seller_category, u.is_verified as seller_verified
    FROM marketplace_items m
    JOIN users u ON m.user_id = u.id
    WHERE m.status IN ('available', 'sold')
";
$params = [];

if ($filterCategory !== 'all') {
    $sql .= " AND m.item_type = :category";
    $params[':category'] = $filterCategory;
}

if ($filterKey !== 'all') {
    $sql .= " AND m.key_note = :key_note";
    $params[':key_note'] = $filterKey;
}

if ($filterCondition !== 'all') {
    $sql .= " AND m.condition_status = :condition";
    $params[':condition'] = $filterCondition;
}

if (!empty($searchQuery)) {
    $sql .= " AND (m.title LIKE :q OR m.description LIKE :q OR m.wood_type LIKE :q OR m.reed_type LIKE :q OR m.maker_name LIKE :q)";
    $params[':q'] = "%{$searchQuery}%";
}

$sql .= " ORDER BY m.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Count stats
$totalAvailable = count($items);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตลาดซื้อขายแคน & อุปกรณ์ดนตรีอีสาน | SoundRadar Marketplace</title>

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

    <!-- Google Fonts: Thai Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bai+Jamjuree:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Charm:wght@400;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { font-family: 'Bai Jamjuree', 'Sarabun', sans-serif; }
        .font-thai-traditional { font-family: 'Charm', cursive, serif; }
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
                            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-medium">ตลาดแคน</span>
                        </div>
                        <p class="text-xs text-gray-500">ศูนย์รวมการซื้อขายแคน ลิ้นแคน และอุปกรณ์ดนตรีอีสาน</p>
                    </div>
                </a>

                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center gap-8 text-sm font-medium">
                    <a href="index.php" class="text-gray-600 hover:text-isan-terracotta transition flex items-center gap-1.5">
                        <i data-lucide="map-pin" class="w-4 h-4"></i> แผนที่พิกัดแคน (Map)
                    </a>
                    <a href="marketplace.php" class="text-isan-terracotta font-semibold flex items-center gap-1.5 border-b-2 border-isan-terracotta pb-1">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i> ตลาดซื้อขาย (Marketplace)
                    </a>
                    <a href="index.php#about-section" class="text-gray-600 hover:text-isan-terracotta transition flex items-center gap-1.5">
                        <i data-lucide="graduation-cap" class="w-4 h-4"></i> ข้อมูลงานวิจัย
                    </a>
                </nav>

                <!-- User Session / Action Buttons -->
                <div class="flex items-center gap-3">
                    <button onclick="openAddItemModal()" class="inline-flex items-center gap-2 bg-isan-terracotta hover:bg-amber-700 text-white text-xs sm:text-sm font-semibold px-4 py-2 rounded-xl transition shadow-md shadow-orange-900/10">
                        <i data-lucide="plus-circle" class="w-4 h-4"></i> ลงประกาศขาย
                    </button>

                    <?php if ($currentUser): ?>
                        <div class="flex items-center gap-3 bg-amber-50/80 border border-amber-200/80 rounded-full py-1.5 pl-2 pr-4">
                            <img src="<?= htmlspecialchars($currentUser['avatar']) ?>" class="w-8 h-8 rounded-full object-cover border border-amber-300">
                            <div class="text-left hidden sm:block">
                                <p class="text-xs font-semibold text-isan-indigo leading-tight"><?= htmlspecialchars($currentUser['name']) ?></p>
                                <span class="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.2 rounded bg-amber-200 text-amber-900">
                                    <?= htmlspecialchars($currentUser['role']) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Role Simulator Quick Switcher -->
                        <div class="relative" id="role-menu-container">
                            <button id="role-toggle-btn" type="button" onclick="toggleRoleMenu(event)" class="text-xs text-gray-600 hover:text-isan-indigo p-2 border border-gray-200 rounded-lg bg-white hover:bg-gray-50 flex items-center gap-1.5 transition shadow-sm">
                                <i data-lucide="user-cog" class="w-4 h-4 text-isan-wood"></i>
                                <span class="hidden md:inline font-medium">สลับบทบาท</span>
                                <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-gray-400 transition-transform" id="role-chevron"></i>
                            </button>
                            <div id="role-dropdown" class="absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-xl border border-gray-100 py-2 hidden z-50 transition-all">
                                <div class="px-3 py-1.5 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">บัญชีผู้ใช้งาน</div>
                                <a href="profile.php" class="flex items-center gap-2 px-3 py-2 text-xs font-semibold text-isan-indigo hover:bg-amber-50 hover:text-amber-900 transition">
                                    <i data-lucide="user" class="w-3.5 h-3.5 text-isan-terracotta"></i> โปรไฟล์ของฉัน (Profile)
                                </a>
                                <div class="border-t border-gray-100 my-1"></div>
                                <div class="px-3 py-1 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">จำลองผู้ใช้งาน (Mock Role)</div>
                                <a href="?switch_role=maker" class="flex items-center gap-2 px-3 py-2 text-xs text-gray-700 hover:bg-amber-50 hover:text-amber-900 transition">
                                    <i data-lucide="wrench" class="w-3.5 h-3.5 text-amber-600"></i> ช่างแคน (User)
                                </a>
                                <a href="?switch_role=admin" class="flex items-center gap-2 px-3 py-2 text-xs text-gray-700 hover:bg-amber-50 hover:text-amber-900 transition">
                                    <i data-lucide="zap" class="w-3.5 h-3.5 text-blue-600"></i> แอดมิน (Admin)
                                </a>
                                <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
                                <a href="admin.php" class="flex items-center gap-2 px-3 py-2 text-xs font-semibold text-amber-700 bg-amber-50/70 hover:bg-amber-100 transition">
                                    <i data-lucide="shield-check" class="w-3.5 h-3.5 text-amber-600"></i> แผงจัดการระบบ (Admin Panel)
                                </a>
                                <?php endif; ?>
                                <div class="border-t border-gray-100 my-1"></div>
                                <a href="?switch_role=logout" class="flex items-center gap-2 px-3 py-2 text-xs text-red-600 hover:bg-red-50 transition">
                                    <i data-lucide="log-out" class="w-3.5 h-3.5"></i> ออกจากระบบ (Guest)
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Marketplace Header Banner -->
    <section class="bg-gradient-to-r from-isan-indigo via-slate-900 to-amber-950 text-white py-12 px-4 sm:px-6 lg:px-10">
        <div class="w-full flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/20 text-amber-400 mb-2">
                    <i data-lucide="sparkles" class="w-3.5 h-3.5"></i> Khaen Marketplace Community
                </span>
                <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight">ตลาดซื้อขายแคนและอุปกรณ์อีสาน</h1>
                <p class="text-sm text-gray-300 mt-2 max-w-2xl leading-relaxed">
                    ศูนย์กลางจำหน่ายแคนทำมือทุกคีย์เสียง ลิ้นแคนเงินแท้ ขี้สูตรชันโรง และอุปกรณ์ดนตรีพื้นบ้าน จากช่างแคนชั้นครูและสมาชิกทั่วประเทศ
                </p>
            </div>
            <div class="flex items-center gap-4 bg-white/10 backdrop-blur-md p-4 rounded-2xl border border-white/10">
                <div class="text-center px-4 border-r border-white/20">
                    <span class="text-xs text-gray-300 block">สินค้าพร้อมส่ง</span>
                    <span class="text-2xl font-bold text-amber-400"><?= $totalAvailable ?></span>
                </div>
                <div class="text-center px-4">
                    <span class="text-xs text-gray-300 block">มาตรฐานเสียง</span>
                    <span class="text-2xl font-bold text-emerald-400">A440 Hz</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content & Filters Section (Full Width PC Layout) -->
    <main class="w-full px-4 sm:px-6 lg:px-10 py-8 flex-1">
        
        <!-- Filter & Search Toolbar -->
        <div class="bg-white rounded-2xl p-4 sm:p-5 shadow-sm border border-amber-900/10 mb-8">
            <form method="GET" action="marketplace.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <!-- Search Keyword -->
                <div class="relative lg:col-span-2">
                    <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="ค้นหาชื่อสินค้า, ช่างทำ, ชนิดไม้, ลิ้นแคน..." class="w-full text-xs sm:text-sm pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <!-- Category Filter -->
                <div>
                    <select name="category" onchange="this.form.submit()" class="w-full text-xs sm:text-sm px-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                        <option value="all" <?= $filterCategory === 'all' ? 'selected' : '' ?>>ทุกประเภทสินค้า</option>
                        <option value="khaen_ped" <?= $filterCategory === 'khaen_ped' ? 'selected' : '' ?>>แคนแปด (16 ลำ)</option>
                        <option value="khaen_jet" <?= $filterCategory === 'khaen_jet' ? 'selected' : '' ?>>แคนเจ็ด (14 ลำ)</option>
                        <option value="khaen_kao" <?= $filterCategory === 'khaen_kao' ? 'selected' : '' ?>>แคนเก้า (18 ลำ)</option>
                        <option value="accessories" <?= $filterCategory === 'accessories' ? 'selected' : '' ?>>อุปกรณ์ / ลิ้นแคน / ขี้สูตร</option>
                        <option value="other" <?= $filterCategory === 'other' ? 'selected' : '' ?>>อื่นๆ</option>
                    </select>
                </div>

                <!-- Key Note Filter -->
                <div>
                    <select name="key_note" onchange="this.form.submit()" class="w-full text-xs sm:text-sm px-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                        <option value="all" <?= $filterKey === 'all' ? 'selected' : '' ?>>ทุกคีย์เสียง</option>
                        <option value="Am" <?= $filterKey === 'Am' ? 'selected' : '' ?>>คีย์ Am (หมอลำมาตรฐาน)</option>
                        <option value="Dm" <?= $filterKey === 'Dm' ? 'selected' : '' ?>>คีย์ Dm</option>
                        <option value="C" <?= $filterKey === 'C' ? 'selected' : '' ?>>คีย์ C</option>
                        <option value="G" <?= $filterKey === 'G' ? 'selected' : '' ?>>คีย์ G</option>
                        <option value="F" <?= $filterKey === 'F' ? 'selected' : '' ?>>คีย์ F</option>
                    </select>
                </div>

                <!-- Action Button -->
                <div class="flex items-center gap-2">
                    <button type="submit" class="flex-1 bg-isan-indigo hover:bg-slate-800 text-white text-xs sm:text-sm font-semibold py-2.5 rounded-xl transition shadow-sm flex items-center justify-center gap-1.5">
                        <i data-lucide="filter" class="w-4 h-4"></i> กรองสินค้า
                    </button>
                    <?php if ($filterCategory !== 'all' || $filterKey !== 'all' || !empty($searchQuery)): ?>
                    <a href="marketplace.php" class="p-2.5 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl transition text-xs" title="ล้างตัวกรอง">
                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Products Grid (Responsive Full Screen Desktop Grid: 1 to 5 cols) -->
        <?php if (count($items) === 0): ?>
            <div class="bg-white rounded-3xl p-16 text-center border border-gray-100 shadow-sm">
                <i data-lucide="package-search" class="w-16 h-16 text-gray-300 mx-auto mb-3"></i>
                <h3 class="font-bold text-lg text-gray-800">ไม่พบรายการสินค้าตรงกับที่คุณค้นหา</h3>
                <p class="text-xs text-gray-500 mt-1">ลองเปลี่ยนคำค้นหา หรือรีเซ็ตตัวกรองเพื่อดูสินค้าทั้งหมด</p>
                <a href="marketplace.php" class="inline-flex items-center gap-2 mt-4 px-4 py-2 bg-isan-indigo text-white text-xs rounded-xl font-medium">ดูสินค้าทั้งหมด</a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5 gap-6">
                <?php foreach ($items as $item): ?>
                <div onclick="openItemDetailModal(<?= $item['id'] ?>)" class="bg-white rounded-2xl overflow-hidden border border-amber-900/10 shadow-sm hover:shadow-xl hover:border-amber-400 transition-all duration-300 flex flex-col group cursor-pointer relative">
                    
                    <!-- Product Image & Tags -->
                    <div class="relative h-56 overflow-hidden bg-gray-100">
                        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        
                        <!-- Badges -->
                        <div class="absolute top-3 left-3 flex flex-col gap-1.5 pointer-events-none">
                            <span class="bg-white/95 backdrop-blur-sm text-isan-indigo text-xs font-bold px-2.5 py-1 rounded-lg shadow-sm border border-gray-100">
                                คีย์ <?= htmlspecialchars($item['key_note']) ?>
                            </span>
                            <?php if (!empty($item['pitch_standard'])): ?>
                            <span class="bg-slate-900/90 text-amber-300 text-[10px] font-semibold px-2 py-0.5 rounded-md shadow-sm">
                                <?= htmlspecialchars($item['pitch_standard']) ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <span class="absolute top-3 right-3 <?= $item['condition_status'] === 'new' ? 'bg-emerald-600' : 'bg-amber-600' ?> text-white text-[10px] font-semibold px-2.5 py-1 rounded-md shadow-sm pointer-events-none">
                            <?= $item['condition_status'] === 'new' ? 'ของใหม่' : 'มือสอง' ?>
                        </span>

                        <?php 
                            $imgList = [];
                            if (!empty($item['images'])) {
                                $decoded = json_decode($item['images'], true);
                                if (is_array($decoded)) $imgList = $decoded;
                            }
                            if (empty($imgList) && !empty($item['image_url'])) {
                                $imgList = [$item['image_url']];
                            }
                            $imgCount = count($imgList);
                        ?>
                        <?php if ($imgCount > 1): ?>
                        <div class="absolute bottom-3 right-3 bg-black/60 backdrop-blur-sm text-white text-[10px] font-medium px-2 py-0.5 rounded-full flex items-center gap-1 shadow-sm">
                            <i data-lucide="image" class="w-3 h-3"></i> <?= $imgCount ?> รูป
                        </div>
                        <?php endif; ?>

                        <?php if ($item['status'] === 'sold'): ?>
                        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-[1px] flex items-center justify-center pointer-events-none">
                            <span class="bg-red-600/95 text-white font-bold text-xs px-3.5 py-1.5 rounded-full shadow-lg border border-red-300 flex items-center gap-1.5">
                                <i data-lucide="check-circle" class="w-4 h-4"></i> สินค้าหมด (Sold Out)
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Details Body -->
                    <div class="p-5 flex-1 flex flex-col justify-between">
                        <div>
                            <!-- Title -->
                            <h3 class="font-bold text-gray-900 text-base leading-snug line-clamp-2 group-hover:text-isan-terracotta transition">
                                <?= htmlspecialchars($item['title']) ?>
                            </h3>

                            <!-- Instrument Specifications Grid -->
                            <div class="mt-3 py-2.5 px-3 bg-amber-50/50 rounded-xl border border-amber-900/5 text-[11px] text-gray-600 space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-400">เนื้อไม้:</span>
                                    <span class="font-medium text-gray-800 truncate"><?= htmlspecialchars($item['wood_type'] ?? 'ไม้กู่แคน') ?></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-400">ลิ้นแคน:</span>
                                    <span class="font-medium text-gray-800"><?= htmlspecialchars($item['reed_type'] ?? 'ลิ้นเงินแท้') ?></span>
                                </div>
                                <?php if (!empty($item['maker_name'])): ?>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-400">ช่างผู้สร้าง:</span>
                                    <span class="font-medium text-isan-wood"><?= htmlspecialchars($item['maker_name']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Description -->
                            <p class="text-xs text-gray-500 mt-2.5 line-clamp-2 leading-relaxed">
                                <?= htmlspecialchars($item['description'] ?? 'ไม่มีคำอธิบายเพิ่มเติม') ?>
                            </p>
                        </div>

                        <!-- Price, Shipping & Contact Footer -->
                        <div class="pt-4 mt-4 border-t border-gray-100">
                            <div class="flex items-baseline justify-between mb-2">
                                <div>
                                    <span class="text-[11px] text-gray-400 block">ราคา</span>
                                    <span class="text-xl font-extrabold text-isan-terracotta">฿<?= number_format($item['price'], 0) ?></span>
                                </div>
                                <div class="text-right text-[11px] text-gray-400">
                                    <span>ค่าส่ง ฿<?= number_format($item['shipping_cost'] ?? 0, 0) ?></span>
                                </div>
                            </div>

                            <!-- View Details Button -->
                            <button type="button" class="w-full py-2 mb-2 bg-amber-50 hover:bg-amber-100 text-isan-wood text-xs font-semibold rounded-xl flex items-center justify-center gap-1.5 transition">
                                <i data-lucide="eye" class="w-3.5 h-3.5 text-isan-terracotta"></i> ดูรายละเอียดสินค้า
                            </button>

                            <!-- Seller Bar & Quick Contact -->
                            <div class="pt-2 border-t border-dashed border-gray-100 flex items-center justify-between" onclick="event.stopPropagation()">
                                <a href="profile.php?id=<?= $item['user_id'] ?>" class="flex items-center gap-2 hover:opacity-80 transition group/seller">
                                    <img src="<?= htmlspecialchars($item['seller_avatar']) ?>" class="w-7 h-7 rounded-full object-cover border border-amber-200">
                                    <div>
                                        <div class="text-xs font-semibold text-gray-800 leading-tight truncate max-w-[100px] group-hover/seller:text-isan-terracotta"><?= htmlspecialchars($item['seller_name']) ?></div>
                                        <span class="text-[10px] text-gray-400">จ.<?= htmlspecialchars($item['seller_province']) ?></span>
                                    </div>
                                </a>

                                <div class="flex items-center gap-1.5">
                                    <?php $phone = $item['contact_phone'] ?: $item['seller_phone']; ?>
                                    <?php if (!empty($phone)): ?>
                                    <a href="tel:<?= htmlspecialchars($phone) ?>" class="p-2 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition" title="โทรสอบถาม: <?= htmlspecialchars($phone) ?>">
                                        <i data-lucide="phone" class="w-3.5 h-3.5"></i>
                                    </a>
                                    <?php endif; ?>

                                    <?php if (!empty($item['contact_line'])): ?>
                                    <a href="https://line.me/ti/p/~<?= urlencode($item['contact_line']) ?>" target="_blank" class="p-2 rounded-lg bg-green-50 text-green-700 hover:bg-green-100 transition" title="LINE ID: <?= htmlspecialchars($item['contact_line']) ?>">
                                        <i data-lucide="message-circle" class="w-3.5 h-3.5"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Modal for Detailed Marketplace Item Submission -->
    <div id="add-item-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border border-gray-100 max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                <div>
                    <h3 class="font-bold text-xl text-isan-indigo flex items-center gap-2">
                        <i data-lucide="plus-circle" class="w-5 h-5 text-isan-terracotta"></i> ลงประกาศขายแคน & อุปกรณ์
                    </h3>
                    <p class="text-xs text-gray-500 mt-0.5">กรอกข้อมูลสเปกเครื่องดนตรีให้ครบถ้วนเพื่อเพิ่มความน่าเชื่อถือ</p>
                </div>
                <button onclick="closeAddItemModal()" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <form id="add-item-form" onsubmit="handleAddItemSubmit(event)" enctype="multipart/form-data" class="mt-6 space-y-4">
                
                <!-- Title -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">ชื่อรายการสินค้า <span class="text-red-500">*</span></label>
                    <input type="text" name="title" required placeholder="เช่น แคนแปด ลายโบราณ ไม้กู่แคนคัดพิเศษ เกรดประกวด" class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <!-- Instrument Specs: Type & Key Note -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">ประเภทเครื่องดนตรี <span class="text-red-500">*</span></label>
                        <select name="item_type" class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                            <option value="khaen_ped">แคนแปด (16 ลำ - มาตรฐาน)</option>
                            <option value="khaen_jet">แคนเจ็ด (14 ลำ - สำหรับผู้เริ่มต้น)</option>
                            <option value="khaen_kao">แคนเก้า (18 ลำ - คอนเสิร์ต)</option>
                            <option value="accessories">อุปกรณ์ / ลิ้นแคน / เต้าแคน / ขี้สูตร</option>
                            <option value="other">เครื่องดนตรีอีสานอื่นๆ</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">คีย์เสียง (Key Note) <span class="text-red-500">*</span></label>
                        <select name="key_note" class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                            <option value="Am">Am (มาตรฐานวงหมอลำ)</option>
                            <option value="Dm">Dm (หมอลำเพลิน)</option>
                            <option value="C">C (สากล)</option>
                            <option value="G">G (สากล)</option>
                            <option value="F">F</option>
                            <option value="All">ทุกคีย์ / อุปกรณ์เสริม</option>
                        </select>
                    </div>
                </div>

                <!-- Detailed Specs: Wood, Reed, Pitch Standard, Maker -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">ชนิดไม้กู่แคน (ไม้ทำลำแคน)</label>
                        <input type="text" name="wood_type" placeholder="เช่น ไม้กู่แคนธรรมชาติ, ไม้ซางแห้ง 2 ปี" value="ไม้กู่แคน (ไม้ซางคัดพิเศษ)" class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">ชนิดลิ้นแคน (Reed Type)</label>
                        <select name="reed_type" class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                            <option value="ลิ้นเงินแท้">ลิ้นเงินแท้ (เสียงนุ่ม หวาน กังวาน)</option>
                            <option value="ลิ้นทองเหลืองเกรด A">ลิ้นทองเหลืองเกรด A (เสียงใส พุ่ง ดังกังวาน)</option>
                            <option value="ลิ้นทองแดงผสม">ลิ้นทองแดงผสม</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">มาตรฐานเทียบเสียง (Tuning Standard)</label>
                        <select name="pitch_standard" class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                            <option value="A440 Hz">A440 Hz (เทียบเสียงระบบดิจิทัลสากล)</option>
                            <option value="ลายโบราณพื้นบ้าน">ลายโบราณพื้นบ้าน (เทียบเสียงแบบดั้งเดิม)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">ชื่อช่างผู้ทำ / แบรนด์</label>
                        <input type="text" name="maker_name" placeholder="เช่น ช่างบุญมี ขอนแก่น" class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                </div>

                <!-- Price, Shipping, Condition -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">ราคาขาย (บาท) <span class="text-red-500">*</span></label>
                        <input type="number" step="1" name="price" required placeholder="เช่น 2800" class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">ค่าจัดส่ง (บาท)</label>
                        <input type="number" step="1" name="shipping_cost" placeholder="เช่น 100" value="100" class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">สภาพสินค้า</label>
                        <select name="condition_status" class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                            <option value="new">ของใหม่ (งานสั่งทำ / สต็อก)</option>
                            <option value="used">มือสองสภาพดี</option>
                        </select>
                    </div>
                </div>

                <!-- Contact Channels -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">เบอร์โทรติดต่อโดยตรง</label>
                        <input type="text" name="contact_phone" placeholder="เช่น 089-123-4567" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>" class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">LINE ID หรือลิงก์ติดต่อ</label>
                        <input type="text" name="contact_line" placeholder="เช่น khaen_isan" class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">คำอธิบายเพิ่มเติม / ประวัติแคน</label>
                    <textarea name="description" rows="3" placeholder="ระบุความยาว การดูแลรักษา ประวัติการเป่า หรืออุปกรณ์ของแถม..." class="w-full text-sm border border-gray-300 rounded-xl px-3.5 py-2.5 focus:ring-2 focus:ring-amber-500 focus:outline-none"></textarea>
                </div>

                <!-- Product Image Upload (Up to 5 images) -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-semibold text-gray-700">
                            รูปถ่ายสินค้าจริง (สูงสุด 5 รูป) <span class="text-xs text-amber-700 font-normal">JPG, PNG, WebP</span>
                        </label>
                        <span id="file-count-badge" class="text-[11px] font-medium text-gray-400">0/5 รูป</span>
                    </div>
                    <div class="relative border-2 border-dashed border-amber-200 hover:border-amber-400 rounded-2xl p-4 text-center bg-amber-50/30 transition cursor-pointer group" onclick="document.getElementById('product-images-input').click()">
                        <input type="file" id="product-images-input" name="images[]" multiple accept="image/*" class="hidden" onchange="handleImageSelection(event)">
                        <div class="flex flex-col items-center justify-center gap-1.5 pointer-events-none">
                            <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-800 flex items-center justify-center group-hover:scale-110 transition">
                                <i data-lucide="image-plus" class="w-5 h-5"></i>
                            </div>
                            <p class="text-xs font-semibold text-gray-700">คลิกเพื่อเลือกรูปภาพ (เลือกพร้อมกันได้สูงสุด 5 รูป)</p>
                            <p class="text-[10px] text-gray-400">ภาพที่ 1 จะถูกใช้เป็นรูปหน้าปกสินค้าหลัก</p>
                        </div>
                    </div>

                    <!-- Selected Images Preview Container -->
                    <div id="image-previews-container" class="grid grid-cols-5 gap-2 mt-3 hidden">
                        <!-- Preview slots generated by JavaScript -->
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                    <button type="button" onclick="closeAddItemModal()" class="px-5 py-2.5 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-xl">ยกเลิก</button>
                    <button type="submit" class="px-6 py-2.5 text-xs font-semibold text-white bg-isan-terracotta hover:bg-amber-700 rounded-xl shadow-md">บันทึกลงประกาศ</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-isan-indigo text-gray-300 py-10 mt-auto border-t border-amber-950">
        <div class="w-full px-4 sm:px-6 lg:px-10 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div>
                <p class="font-bold text-white text-base">SoundRadar Marketplace — ตลาดแคนอีสาน</p>
                <p class="text-xs text-gray-400 mt-1">สนับสนุนเศรษฐกิจสร้างสรรค์และภูมิปัญญาดนตรีพื้นบ้านไทย</p>
            </div>
            <div class="text-xs text-gray-400">
                PHP PDO • Tailwind CSS Full-Screen UI
            </div>
        </div>
    </footer>

    <!-- Product Detail Modal (Pop-up with Multi-image Gallery & Specifications) -->
    <div id="item-detail-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-3xl max-w-4xl w-full p-6 sm:p-8 shadow-2xl border border-gray-100 max-h-[92vh] overflow-y-auto relative animate-fade-in">
            <!-- Close Button -->
            <button onclick="closeItemDetailModal()" class="absolute top-5 right-5 w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-500 hover:text-gray-800 flex items-center justify-center transition z-10">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                
                <!-- Left: Multi-Image Gallery -->
                <div>
                    <!-- Main Large Image View -->
                    <div class="relative h-80 sm:h-96 w-full rounded-2xl overflow-hidden bg-gray-100 border border-gray-200 shadow-inner group">
                        <img id="detail-main-img" src="" alt="" class="w-full h-full object-cover transition duration-300">
                        <div class="absolute top-3 left-3 flex flex-col gap-1.5">
                            <span id="detail-key-badge" class="bg-white/95 backdrop-blur-sm text-isan-indigo text-xs font-bold px-3 py-1 rounded-lg shadow-sm"></span>
                            <span id="detail-pitch-badge" class="bg-slate-900/90 text-amber-300 text-[10px] font-semibold px-2 py-0.5 rounded-md shadow-sm"></span>
                        </div>
                        <span id="detail-condition-badge" class="absolute top-3 right-12 text-white text-[10px] font-semibold px-2.5 py-1 rounded-md shadow-sm"></span>
                    </div>

                    <!-- Thumbnails Carousel / Selector (Up to 5 images) -->
                    <div id="detail-thumbnails-list" class="grid grid-cols-5 gap-2.5 mt-3">
                        <!-- Populated by JS -->
                    </div>
                </div>

                <!-- Right: Details, Specifications, Pricing & Contact -->
                <div class="flex flex-col justify-between">
                    <div>
                        <!-- Category Subtitle -->
                        <span id="detail-item-type" class="text-xs font-semibold text-isan-terracotta uppercase tracking-wider"></span>
                        
                        <!-- Title -->
                        <h2 id="detail-title" class="text-2xl font-bold text-gray-900 mt-1 leading-snug"></h2>

                        <div id="detail-sold-banner" class="hidden mt-3 p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-bold flex items-center gap-2">
                            <i data-lucide="alert-circle" class="w-4 h-4 text-red-600"></i> รายการนี้ขายแล้ว / สินค้าหมด (Sold Out)
                        </div>

                        <!-- Price Section -->
                        <div class="mt-4 p-4 rounded-2xl bg-amber-50/60 border border-amber-900/10 flex items-baseline justify-between">
                            <div>
                                <span class="text-xs text-gray-500 block">ราคาจำหน่าย</span>
                                <span id="detail-price" class="text-3xl font-black text-isan-terracotta"></span>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-gray-400 block">ค่าจัดส่งทั่วไทย</span>
                                <span id="detail-shipping" class="text-sm font-semibold text-gray-700"></span>
                            </div>
                        </div>

                        <!-- Technical Specs Grid -->
                        <div class="mt-5">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2 flex items-center gap-1.5">
                                <i data-lucide="sliders" class="w-3.5 h-3.5 text-isan-wood"></i> ข้อมูลสเปกเครื่องดนตรี
                            </h4>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="bg-gray-50 p-2.5 rounded-xl border border-gray-100">
                                    <span class="text-gray-400 block text-[11px]">เนื้อไม้กู่แคน:</span>
                                    <span id="detail-wood" class="font-semibold text-gray-800"></span>
                                </div>
                                <div class="bg-gray-50 p-2.5 rounded-xl border border-gray-100">
                                    <span class="text-gray-400 block text-[11px]">ชนิดลิ้นแคน:</span>
                                    <span id="detail-reed" class="font-semibold text-gray-800"></span>
                                </div>
                                <div class="bg-gray-50 p-2.5 rounded-xl border border-gray-100">
                                    <span class="text-gray-400 block text-[11px]">มาตรฐานเสียง:</span>
                                    <span id="detail-pitch" class="font-semibold text-gray-800"></span>
                                </div>
                                <div class="bg-gray-50 p-2.5 rounded-xl border border-gray-100">
                                    <span class="text-gray-400 block text-[11px]">ช่างผู้สร้าง / แบรนด์:</span>
                                    <span id="detail-maker" class="font-semibold text-isan-wood"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="mt-5">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1 flex items-center gap-1.5">
                                <i data-lucide="file-text" class="w-3.5 h-3.5 text-isan-wood"></i> รายละเอียด / ประวัติแคน
                            </h4>
                            <p id="detail-desc" class="text-sm text-gray-600 leading-relaxed bg-amber-50/30 p-3.5 rounded-xl border border-amber-900/5 whitespace-pre-line"></p>
                        </div>
                    </div>

                    <!-- Seller Card & Action Buttons -->
                    <div class="mt-6 pt-5 border-t border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <img id="detail-seller-avatar" src="" class="w-11 h-11 rounded-full object-cover border-2 border-amber-300">
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <span id="detail-seller-name" class="font-bold text-sm text-gray-900"></span>
                                        <span id="detail-seller-verified" class="hidden text-amber-500" title="สมาชิกยืนยันตัวตนแล้ว">
                                            <i data-lucide="badge-check" class="w-4 h-4 fill-amber-400 text-white"></i>
                                        </span>
                                    </div>
                                    <span id="detail-seller-location" class="text-xs text-gray-500"></span>
                                </div>
                            </div>
                            <a id="detail-seller-profile-link" href="#" class="text-xs font-semibold text-isan-terracotta hover:underline flex items-center gap-1">
                                ดูโปรไฟล์ร้าน <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </a>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <a id="detail-phone-btn" href="#" class="py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold flex items-center justify-center gap-2 transition shadow-md shadow-emerald-700/20">
                                <i data-lucide="phone" class="w-4 h-4"></i> โทรติดต่อผู้ขาย
                            </a>
                            <a id="detail-line-btn" href="#" target="_blank" class="py-2.5 px-4 rounded-xl bg-green-500 hover:bg-green-600 text-white text-xs sm:text-sm font-semibold flex items-center justify-center gap-2 transition shadow-md shadow-green-600/20">
                                <i data-lucide="message-circle" class="w-4 h-4"></i> ทัก LINE ทันที
                            </a>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>

    <!-- Raw Items JSON for Modal Inspection -->
    <script>
        const marketplaceItems = <?= json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>

    <script>
        lucide.createIcons();

        // Modal Open/Close for Adding Item
        function openAddItemModal() {
            document.getElementById('add-item-modal').classList.remove('hidden');
        }

        function closeAddItemModal() {
            document.getElementById('add-item-modal').classList.add('hidden');
        }

        // Live image selection handler (up to 5 images)
        function handleImageSelection(e) {
            const files = Array.from(e.target.files);
            const previewContainer = document.getElementById('image-previews-container');
            const badge = document.getElementById('file-count-badge');

            if (files.length > 5) {
                alert('สามารถเลือกรูปภาพได้สูงสุด 5 รูปเท่านั้น ระบบจะเลือก 5 รูปแรกให้โดยอัตโนมัติ');
                files.splice(5);
            }

            badge.innerText = `${files.length}/5 รูป`;

            if (files.length === 0) {
                previewContainer.classList.add('hidden');
                previewContainer.innerHTML = '';
                return;
            }

            previewContainer.classList.remove('hidden');
            previewContainer.innerHTML = '';

            files.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    const slot = document.createElement('div');
                    slot.className = 'relative aspect-square rounded-xl overflow-hidden border border-amber-300 bg-gray-100 shadow-sm group';
                    slot.innerHTML = `
                        <img src="${evt.target.result}" class="w-full h-full object-cover">
                        <span class="absolute bottom-1 left-1 bg-black/70 text-white text-[9px] px-1.5 py-0.5 rounded font-bold">
                            ${index === 0 ? 'รูปปก' : '#' + (index + 1)}
                        </span>
                    `;
                    previewContainer.appendChild(slot);
                };
                reader.readAsDataURL(file);
            });
        }

        // Product Detail Modal Open Handler
        function openItemDetailModal(itemId) {
            const item = marketplaceItems.find(i => Number(i.id) === Number(itemId));
            if (!item) return;

            // Prepare images list (up to 5 images)
            let images = [];
            if (item.images) {
                try {
                    const parsed = JSON.parse(item.images);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        images = parsed;
                    }
                } catch(e) {}
            }
            if (images.length === 0 && item.image_url) {
                images = [item.image_url];
            }

            // Set main image & badges
            const mainImg = document.getElementById('detail-main-img');
            mainImg.src = images[0] || 'assets/images/sample-khaen.jpg';

            document.getElementById('detail-key-badge').innerText = `คีย์ ${item.key_note || 'Am'}`;
            document.getElementById('detail-pitch-badge').innerText = item.pitch_standard || 'A440 Hz';
            
            const condBadge = document.getElementById('detail-condition-badge');
            condBadge.innerText = item.condition_status === 'new' ? 'ของใหม่' : 'มือสอง';
            condBadge.className = `absolute top-3 right-12 text-white text-[10px] font-semibold px-2.5 py-1 rounded-md shadow-sm ${item.condition_status === 'new' ? 'bg-emerald-600' : 'bg-amber-600'}`;

            // Generate Thumbnails
            const thumbsList = document.getElementById('detail-thumbnails-list');
            thumbsList.innerHTML = '';

            images.forEach((imgUrl, idx) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `aspect-square rounded-xl overflow-hidden border-2 transition ${idx === 0 ? 'border-isan-terracotta ring-2 ring-amber-400' : 'border-gray-200 opacity-70 hover:opacity-100'}`;
                btn.innerHTML = `<img src="${imgUrl}" class="w-full h-full object-cover">`;
                btn.onclick = () => {
                    mainImg.src = imgUrl;
                    Array.from(thumbsList.children).forEach(c => {
                        c.className = 'aspect-square rounded-xl overflow-hidden border-2 border-gray-200 opacity-70 hover:opacity-100 transition';
                    });
                    btn.className = 'aspect-square rounded-xl overflow-hidden border-2 border-isan-terracotta ring-2 ring-amber-400 transition';
                };
                thumbsList.appendChild(btn);
            });

            // Text Info
            const typeNames = {
                'khaen_ped': 'แคนแปด (16 ลำ - มาตรฐาน)',
                'khaen_jet': 'แคนเจ็ด (14 ลำ)',
                'khaen_kao': 'แคนเก้า (18 ลำ)',
                'accessories': 'อุปกรณ์ / ลิ้นแคน / ขี้สูตร',
                'other': 'เครื่องดนตรีอีสานอื่นๆ'
            };
            document.getElementById('detail-item-type').innerText = typeNames[item.item_type] || 'เครื่องดนตรีอีสาน';
            document.getElementById('detail-title').innerText = item.title;

            const soldBanner = document.getElementById('detail-sold-banner');
            if (item.status === 'sold') {
                soldBanner?.classList.remove('hidden');
            } else {
                soldBanner?.classList.add('hidden');
            }

            document.getElementById('detail-price').innerText = '฿' + Number(item.price).toLocaleString();
            document.getElementById('detail-shipping').innerText = Number(item.shipping_cost) > 0 ? `฿${Number(item.shipping_cost).toLocaleString()}` : 'จัดส่งฟรี';

            document.getElementById('detail-wood').innerText = item.wood_type || 'ไม้กู่แคน (ไม้ซาง)';
            document.getElementById('detail-reed').innerText = item.reed_type || 'ลิ้นเงินแท้';
            document.getElementById('detail-pitch').innerText = item.pitch_standard || 'A440 Hz';
            document.getElementById('detail-maker').innerText = item.maker_name || '-';
            document.getElementById('detail-desc').innerText = item.description || 'ไม่มีคำอธิบายเพิ่มเติมจากผู้ขาย';

            // Seller Card
            document.getElementById('detail-seller-avatar').src = item.seller_avatar;
            document.getElementById('detail-seller-name').innerText = item.seller_name;
            document.getElementById('detail-seller-location').innerText = `จ.${item.seller_province || 'ขอนแก่น'}`;
            document.getElementById('detail-seller-profile-link').href = `profile.php?id=${item.user_id}`;

            const verifiedIcon = document.getElementById('detail-seller-verified');
            if (Number(item.seller_verified) === 1) {
                verifiedIcon.classList.remove('hidden');
            } else {
                verifiedIcon.classList.add('hidden');
            }

            // Contact Channels
            const phone = item.contact_phone || item.seller_phone;
            const phoneBtn = document.getElementById('detail-phone-btn');
            if (phone) {
                phoneBtn.href = `tel:${phone}`;
                phoneBtn.classList.remove('opacity-40', 'pointer-events-none');
            } else {
                phoneBtn.href = '#';
                phoneBtn.classList.add('opacity-40', 'pointer-events-none');
            }

            const lineBtn = document.getElementById('detail-line-btn');
            if (item.contact_line) {
                lineBtn.href = `https://line.me/ti/p/~${encodeURIComponent(item.contact_line)}`;
                lineBtn.classList.remove('opacity-40', 'pointer-events-none');
            } else {
                lineBtn.href = '#';
                lineBtn.classList.add('opacity-40', 'pointer-events-none');
            }

            document.getElementById('item-detail-modal').classList.remove('hidden');
            lucide.createIcons();
        }

        function closeItemDetailModal() {
            document.getElementById('item-detail-modal').classList.add('hidden');
        }

        // Close on background click
        document.getElementById('item-detail-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeItemDetailModal();
            }
        });

        // Check if item_id is passed in URL (?item_id=X)
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const itemId = urlParams.get('item_id');
            if (itemId) {
                openItemDetailModal(itemId);
            }
        });

        async function handleAddItemSubmit(e) {
            e.preventDefault();
            const form = document.getElementById('add-item-form');
            const formData = new FormData(form);

            try {
                const response = await fetch('add_item.php', {
                    method: 'POST',
                    body: formData
                });
                const res = await response.json();

                if (res.status === 'success') {
                    alert('ลงประกาศขายแคนพร้อมรูปภาพและสเปกเรียบร้อยแล้ว!');
                    closeAddItemModal();
                    form.reset();
                    window.location.reload();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + res.message);
                }
            } catch (err) {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
            }
        }

        // Role Switcher Click Handler
        function toggleRoleMenu(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('role-dropdown');
            const chevron = document.getElementById('role-chevron');
            if (dropdown) {
                const isHidden = dropdown.classList.contains('hidden');
                dropdown.classList.toggle('hidden');
                if (chevron) {
                    chevron.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
                }
            }
        }

        document.addEventListener('click', function(e) {
            const container = document.getElementById('role-menu-container');
            const dropdown = document.getElementById('role-dropdown');
            const chevron = document.getElementById('role-chevron');
            if (container && dropdown && !container.contains(e.target)) {
                dropdown.classList.add('hidden');
                if (chevron) {
                    chevron.style.transform = 'rotate(0deg)';
                }
            }
        });
    </script>
</body>
</html>
