<?php
/**
 * Main Web Application - Clean Modern Isan Theme
 * Khaen Complex Center: ชุมชนแคนอีสาน (Khaen Community Portal & Map)
 */
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/db.php';

$currentUser = getCurrentUser();

// Fetch marketplace items for the marketplace section
$marketQuery = $pdo->query("
    SELECT m.*, u.name as seller_name, u.province as seller_province, u.phone as seller_phone, u.category as seller_category
    FROM marketplace_items m
    JOIN users u ON m.user_id = u.id
    WHERE m.status = 'available'
    ORDER BY m.id DESC
");
$marketItems = $marketQuery->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khaen Complex Center | ชุมชนคนรักแคน & ตลาดเครื่องดนตรีอีสาน</title>

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
                        thai: ['"Charm"', 'cursive', 'serif'],
                        head: ['"Bai Jamjuree"', 'sans-serif']
                    }
                }
            }
        }
    </script>

    <!-- Google Fonts: Authentic Thai Cultural Fonts (Bai Jamjuree & Charm & Sarabun) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bai+Jamjuree:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Charm:wght@400;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Leaflet.js CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { font-family: 'Bai Jamjuree', 'Sarabun', sans-serif; }
        .font-thai-traditional { font-family: 'Charm', cursive, serif; }
        #map { height: 620px; z-index: 10; border-radius: 1rem; }
        @media (min-width: 1024px) {
            #map { height: calc(100vh - 270px); min-height: 600px; }
            #side-directory-container { height: calc(100vh - 270px); min-height: 600px; }
        }
        
        /* Custom Modern Pin styling */
        .custom-pin {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            color: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            border: 2px solid #ffffff;
            transition: transform 0.2s ease;
        }
        .custom-pin:hover {
            transform: scale(1.15);
        }
        .pin-maker { background-color: #D97706; }    /* Warm Bamboo / Amber */
        .pin-player { background-color: #1E3A8A; }   /* Mo-hom Indigo */
        .pin-shop { background-color: #C2410C; }     /* Terracotta Red-Orange */
        .pin-learning { background-color: #059669; } /* Emerald / Forest Green for Learning Center */
        .pin-user { background-color: #10B981; }     /* Emerald for user GPS */

        /* Leaflet popup overrides */
        .leaflet-popup-content-wrapper {
            border-radius: 1rem;
            padding: 0;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }
        .leaflet-popup-content {
            margin: 0;
            line-height: 1.4;
        }
    </style>
</head>
<body class="bg-isan-bg text-gray-800 antialiased min-h-screen flex flex-col">

    <!-- Top Navigation Bar -->
    <header class="bg-white/95 backdrop-blur-md sticky top-0 z-50 border-b border-amber-900/10 shadow-sm">
        <div class="w-full px-4 sm:px-6 lg:px-10">
            <div class="flex items-center justify-between h-20">
                <!-- Brand / Logo -->
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-isan-terracotta to-isan-wood flex items-center justify-center text-white shadow-md shadow-amber-900/20">
                        <i data-lucide="radio" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xl sm:text-2xl font-bold tracking-tight text-isan-indigo">Khaen Complex Center</span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-medium">แคนอีสาน</span>
                        </div>
                        <p class="text-xs text-gray-500">แพลตฟอร์มพิกัดช่างแคน หมอแคน และตลาดเครื่องดนตรีอีสาน</p>
                    </div>
                </div>

                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center gap-8 text-sm font-medium">
                    <a href="index.php" class="text-isan-indigo hover:text-isan-terracotta transition flex items-center gap-1.5 font-semibold">
                        <i data-lucide="map-pin" class="w-4 h-4"></i> ค้นหาพิกัดแคน
                    </a>
                    <a href="marketplace.php" class="text-gray-600 hover:text-isan-terracotta transition flex items-center gap-1.5">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i> ตลาดซื้อขายแคน (Marketplace)
                    </a>
                    <a href="profile.php" class="text-gray-600 hover:text-isan-terracotta transition flex items-center gap-1.5">
                        <i data-lucide="user" class="w-4 h-4"></i> โปรไฟล์ (Profile)
                    </a>
                    <a href="#about-section" class="text-gray-600 hover:text-isan-terracotta transition flex items-center gap-1.5">
                        <i data-lucide="graduation-cap" class="w-4 h-4"></i> ข้อมูลงานวิจัย
                    </a>
                </nav>

                <!-- User Session / Role Bar -->
                <div class="flex items-center gap-3">
                    <?php if ($currentUser): ?>
                        <div class="flex items-center gap-3 bg-amber-50/80 border border-amber-200/80 rounded-full py-1.5 pl-2 pr-4">
                            <img src="<?= htmlspecialchars($currentUser['avatar']) ?>" class="w-8 h-8 rounded-full object-cover border border-amber-300">
                            <div class="text-left hidden sm:block">
                                <p class="text-xs font-semibold text-isan-indigo leading-tight"><?= htmlspecialchars($currentUser['name']) ?></p>
                                <span class="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.2 rounded bg-amber-200 text-amber-900">
                                    <?= htmlspecialchars($currentUser['role']) ?> (<?= htmlspecialchars($currentUser['category']) ?>)
                                </span>
                            </div>
                        </div>

                        <!-- Role Simulator Quick Switcher (Click to toggle) -->
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
                    <?php else: ?>
                        <!-- Mock Google Sign In Button -->
                        <a href="?switch_role=maker" class="inline-flex items-center gap-2 bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 text-xs font-medium px-3.5 py-2 rounded-xl shadow-sm transition">
                            <svg class="w-4 h-4" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
                            เข้าสู่ระบบด้วย Google
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Map Section -->
    <section id="map-section" class="w-full px-4 sm:px-6 lg:px-10 pt-6 sm:pt-8 pb-16">
        <!-- Control Center & Filter Bar (Unified Modern Design) -->
        <div class="bg-white rounded-3xl p-4 sm:p-5 shadow-sm border border-amber-900/10 mb-6 space-y-4">
            
            <!-- Row 1: Search Input, Province Dropdown, and Action Buttons -->
            <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-3">
                
                <!-- Search Box -->
                <div class="relative flex-1">
                    <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-4 top-1/2 -translate-y-1/2"></i>
                    <input type="text" id="map-search-input" oninput="applyFilters()" placeholder="ค้นหาชื่อช่าง, หมอแคน, ร้านค้า หรือคำอธิบาย..." class="w-full text-xs sm:text-sm pl-11 pr-10 py-3 bg-gray-50/80 hover:bg-gray-50 focus:bg-white border border-gray-200 rounded-2xl focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 focus:outline-none transition">
                    <button type="button" onclick="document.getElementById('map-search-input').value=''; applyFilters();" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500 p-0.5">
                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                    </button>
                </div>

                <!-- Province Selector -->
                <div class="relative w-full lg:w-64">
                    <i data-lucide="map-pin" class="w-4 h-4 text-amber-700 absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    <select id="province-filter" onchange="applyFilters()" class="w-full text-xs sm:text-sm pl-10 pr-9 py-3 bg-gray-50/80 hover:bg-gray-50 focus:bg-white border border-gray-200 rounded-2xl focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 focus:outline-none appearance-none cursor-pointer transition font-medium text-gray-700">
                        <option value="all">ทุกจังหวัดทั่วไทย</option>
                        <option value="ขอนแก่น">จ.ขอนแก่น</option>
                        <option value="ร้อยเอ็ด">จ.ร้อยเอ็ด</option>
                        <option value="กาฬสินธุ์">จ.กาฬสินธุ์</option>
                        <option value="อุบลราชธานี">จ.อุบลราชธานี</option>
                    </select>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                </div>

                <!-- Action Buttons: Near Me & Pin My Location -->
                <div class="flex items-center gap-2.5 shrink-0">
                    <button onclick="locateUserPosition()" class="flex-1 lg:flex-initial inline-flex items-center justify-center gap-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200/80 font-medium text-xs sm:text-sm px-4 py-3 rounded-2xl transition shadow-sm active:scale-95">
                        <i data-lucide="locate" class="w-4 h-4 text-emerald-600"></i>
                        <span>หมอแคนใกล้ฉัน</span>
                    </button>
                    
                    <?php if ($currentUser): ?>
                    <button onclick="openUpdateLocationModal()" class="flex-1 lg:flex-initial inline-flex items-center justify-center gap-2 bg-isan-terracotta hover:bg-amber-700 text-white font-medium text-xs sm:text-sm px-4 py-3 rounded-2xl transition shadow-md shadow-orange-900/10 active:scale-95">
                        <i data-lucide="map-pin-plus" class="w-4 h-4"></i>
                        <span>ปักหมุดของฉัน</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Row 2: Category Segmented Pills (Clean Modern Look) -->
            <div class="pt-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs text-gray-400 font-medium hidden sm:inline mr-1">กรองพิกัด:</span>

                    <!-- Filter All -->
                    <button onclick="filterCategory('all')" id="filter-all" class="filter-btn active inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-semibold bg-isan-indigo text-white shadow-sm transition">
                        <i data-lucide="layers" class="w-3.5 h-3.5"></i>
                        <span>ทั้งหมด</span>
                        <span id="count-all" class="px-1.5 py-0.2 rounded-full text-[10px] bg-white/20 text-white font-bold">0</span>
                    </button>

                    <!-- Filter Makers -->
                    <button onclick="filterCategory('maker')" id="filter-maker" class="filter-btn inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-medium bg-amber-50 text-amber-900 hover:bg-amber-100 border border-amber-200/80 transition">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <span>ช่างแคน</span>
                        <span id="count-maker" class="px-1.5 py-0.2 rounded-full text-[10px] bg-amber-200/80 text-amber-900 font-bold">0</span>
                    </button>

                    <!-- Filter Players -->
                    <button onclick="filterCategory('player')" id="filter-player" class="filter-btn inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-medium bg-blue-50 text-blue-900 hover:bg-blue-100 border border-blue-200/80 transition">
                        <span class="w-2 h-2 rounded-full bg-blue-700"></span>
                        <span>หมอแคน</span>
                        <span id="count-player" class="px-1.5 py-0.2 rounded-full text-[10px] bg-blue-200/80 text-blue-900 font-bold">0</span>
                    </button>

                    <!-- Filter Shops -->
                    <button onclick="filterCategory('shop')" id="filter-shop" class="filter-btn inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-medium bg-orange-50 text-orange-900 hover:bg-orange-100 border border-orange-200/80 transition">
                        <span class="w-2 h-2 rounded-full bg-orange-600"></span>
                        <span>ร้านขายแคน</span>
                        <span id="count-shop" class="px-1.5 py-0.2 rounded-full text-[10px] bg-orange-200/80 text-orange-900 font-bold">0</span>
                    </button>

                    <!-- Filter Learning Centers -->
                    <button onclick="filterCategory('learning_center')" id="filter-learning_center" class="filter-btn inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-medium bg-emerald-50 text-emerald-900 hover:bg-emerald-100 border border-emerald-200/80 transition">
                        <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                        <span>ศูนย์การเรียนรู้แคน</span>
                        <span id="count-learning" class="px-1.5 py-0.2 rounded-full text-[10px] bg-emerald-200/80 text-emerald-900 font-bold">0</span>
                    </button>
                </div>

                <!-- Quick Reset Indicator -->
                <div class="text-[11px] text-gray-400 hidden md:block">
                    คลิกเลือกหมวดหมู่เพื่อเปิด/ปิดหมุดบนแผนที่
                </div>
            </div>
        </div>

        <!-- The Interactive Map Container & Side Directory List -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Leaflet Map Container (Takes 8 of 12 cols on desktop, 9 of 12 on large PC) -->
            <div class="lg:col-span-8 xl:col-span-9 relative bg-white p-2 rounded-2xl shadow-lg border border-amber-900/10">
                <div id="map"></div>
                
                <!-- Map Legend Overlay -->
                <div class="absolute bottom-6 right-6 z-20 bg-white/95 backdrop-blur-md p-3 rounded-xl shadow-md border border-gray-100 text-xs flex flex-col gap-1.5 hidden sm:flex">
                    <span class="font-semibold text-gray-700 mb-0.5">สัญลักษณ์พิกัด:</span>
                    <div class="flex items-center gap-2 text-gray-600">
                        <span class="w-3 h-3 rounded-full bg-amber-500"></span> ช่างทำแคน (Khaen Maker)
                    </div>
                    <div class="flex items-center gap-2 text-gray-600">
                        <span class="w-3 h-3 rounded-full bg-blue-700"></span> หมอแคน (Khaen Player)
                    </div>
                    <div class="flex items-center gap-2 text-gray-600">
                        <span class="w-3 h-3 rounded-full bg-orange-600"></span> ร้านขายแคน (Khaen Shop)
                    </div>
                </div>
            </div>

            <!-- Side Directory List (Takes 4 of 12 cols on desktop, 3 of 12 on large PC) -->
            <div id="side-directory-container" class="lg:col-span-4 xl:col-span-3 bg-white rounded-2xl p-4 sm:p-5 shadow-lg border border-amber-900/10 flex flex-col h-[620px]">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                    <div>
                        <h3 class="font-bold text-gray-900 text-sm flex items-center gap-1.5">
                            <i data-lucide="users" class="w-4 h-4 text-isan-terracotta"></i> รายชื่อผู้ลงทะเบียน
                        </h3>
                        <span class="text-[11px] text-gray-400">คลิกที่การ์ดเพื่อเลื่อนพิกัดบนแผนที่</span>
                    </div>
                    <span id="side-list-counter" class="text-xs bg-amber-50 text-amber-800 font-semibold px-2.5 py-1 rounded-full border border-amber-200">
                        0 รายการ
                    </span>
                </div>
                
                <!-- Directory Scrollable List Container -->
                <div id="side-directory-list" class="flex-1 overflow-y-auto mt-3 space-y-3 pr-1">
                    <!-- Populated dynamically via JavaScript -->
                </div>
            </div>
        </div>
    </section>

    <!-- Marketplace Teaser Showcase (ตลาดซื้อขายแคน - แยกหน้า) -->
    <section class="bg-amber-50/50 border-t border-amber-900/10 py-16">
        <div class="w-full px-4 sm:px-6 lg:px-10">
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
                <div>
                    <span class="text-xs uppercase font-bold text-isan-terracotta tracking-wider">Khaen Marketplace</span>
                    <h2 class="text-2xl sm:text-3xl font-bold text-isan-indigo mt-1">ตลาดซื้อขายแคนและอุปกรณ์อีสาน</h2>
                    <p class="text-sm text-gray-500 mt-1">ชมแคนทำมือเกรดพรีเมียมจากช่างแคนชั้นครูทั่วภาคอีสาน</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="marketplace.php" class="inline-flex items-center gap-2 bg-isan-terracotta hover:bg-amber-700 text-white text-xs sm:text-sm font-semibold px-5 py-2.5 rounded-xl transition shadow-md shadow-orange-900/10">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i> เข้าสู่ตลาดซื้อขายเต็มรูปแบบ
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                </div>
            </div>

            <!-- Preview Grid (Top 4 Items) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                <?php foreach (array_slice($marketItems, 0, 4) as $item): ?>
                <a href="marketplace.php?q=<?= urlencode($item['title']) ?>" class="bg-white rounded-2xl overflow-hidden border border-amber-900/10 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col group">
                    <div class="relative h-48 overflow-hidden bg-gray-100">
                        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        <span class="absolute top-3 left-3 bg-white/95 backdrop-blur-sm text-isan-indigo text-[11px] font-bold px-2.5 py-1 rounded-lg shadow-sm">
                            คีย์ <?= htmlspecialchars($item['key_note']) ?>
                        </span>
                        <span class="absolute top-3 right-3 <?= $item['condition_status'] === 'new' ? 'bg-emerald-600' : 'bg-amber-600' ?> text-white text-[10px] font-semibold px-2 py-0.5 rounded-md shadow-sm">
                            <?= $item['condition_status'] === 'new' ? 'ของใหม่' : 'มือสอง' ?>
                        </span>
                    </div>
                    <div class="p-5 flex-1 flex flex-col justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900 text-base line-clamp-1 group-hover:text-isan-terracotta transition">
                                <?= htmlspecialchars($item['title']) ?>
                            </h3>
                            <p class="text-xs text-gray-500 mt-1.5 line-clamp-2 leading-relaxed">
                                <?= htmlspecialchars($item['description']) ?>
                            </p>
                        </div>
                        <div class="pt-4 mt-4 border-t border-gray-100 flex items-center justify-between">
                            <div>
                                <span class="text-xs text-gray-400 block">ราคา</span>
                                <span class="text-lg font-bold text-isan-terracotta">฿<?= number_format($item['price'], 0) ?></span>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-gray-600 font-medium block"><?= htmlspecialchars($item['seller_name']) ?></span>
                                <span class="text-[11px] text-gray-400">จ.<?= htmlspecialchars($item['seller_province']) ?></span>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Modal for Updating Lat/Lng (User Profile GPS) -->
    <div id="location-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="font-bold text-lg text-isan-indigo flex items-center gap-2">
                    <i data-lucide="map-pin" class="w-5 h-5 text-isan-terracotta"></i> อัปเดตพิกัดบนแผนที่
                </h3>
                <button onclick="closeUpdateLocationModal()" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <p class="text-xs text-gray-500 mt-3 leading-relaxed">
                คลิกปุ่ม "ดึงพิกัดปัจจุบันจาก GPS" หรือกรอกพิกัดละติจูด/ลองจิจูด เพื่อให้หมอแคนและผู้สนใจค้นหาคุณเจอ
            </p>

            <form id="update-location-form" onsubmit="handleLocationSubmit(event)" class="mt-4 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">ละติจูด (Latitude)</label>
                    <input type="text" id="input-lat" name="latitude" required placeholder="เช่น 16.4322" class="w-full text-sm border border-gray-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">ลองจิจูด (Longitude)</label>
                    <input type="text" id="input-lng" name="longitude" required placeholder="เช่น 102.8236" class="w-full text-sm border border-gray-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <button type="button" onclick="getBrowserCoordinates()" class="w-full py-2 px-3 border border-gray-300 rounded-xl text-xs font-medium text-gray-700 hover:bg-gray-50 flex items-center justify-center gap-2">
                    <i data-lucide="crosshair" class="w-4 h-4 text-emerald-600"></i> ดึงพิกัดปัจจุบันจาก GPS ของอุปกรณ์
                </button>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closeUpdateLocationModal()" class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-xl">ยกเลิก</button>
                    <button type="submit" class="px-5 py-2 text-xs font-semibold text-white bg-isan-terracotta hover:bg-amber-700 rounded-xl shadow-sm">บันทึกพิกัด</button>
                </div>
            </form>
        </div>
    </div>

    <!-- About Khaen & Sound of Isan Section (เกี่ยวกับเสียงแคนและวัฒนธรรมอีสาน) -->
    <!-- Research Project for Isan Music Community Section (งานวิจัยเพื่อพัฒนาชุมชนดนตรีอีสาน) -->
    <section id="about-section" class="py-16 bg-white border-t border-amber-900/10">
        <div class="w-full px-4 sm:px-6 lg:px-10">
            
            <!-- Main Research Banner & Academic Attribution -->
            <div class="max-w-4xl mx-auto text-center mb-12">
                <span class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-900 border border-amber-200 shadow-xs mb-3">
                    <i data-lucide="graduation-cap" class="w-4 h-4 text-isan-terracotta"></i>
                    <span>งานวิจัยเพื่อพัฒนาชุมชนดนตรีอีสาน (Research & Community Development)</span>
                </span>
                
                <h2 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-isan-indigo tracking-tight leading-tight">
                    งานวิจัยเพื่อพัฒนาชุมชนดนตรีอีสาน
                </h2>
                
                <div class="mt-4 p-5 rounded-2xl bg-amber-50/70 border border-amber-900/10 max-w-3xl mx-auto shadow-sm">
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-4 text-center sm:text-left">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-isan-terracotta to-isan-wood text-white flex items-center justify-center shadow-md shrink-0">
                            <i data-lucide="award" class="w-8 h-8"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 leading-snug">
                                อาจารย์ ดร.สัญชัย ด้วงบุ้ง
                            </h3>
                            <p class="text-xs sm:text-sm text-isan-terracotta font-semibold mt-0.5">
                                อาจารย์ประจำภาควิชาดนตรีศึกษา
                            </p>
                            <p class="text-xs text-gray-600 mt-1">
                                วิทยาลัยดุริยางคศิลป์ มหาวิทยาลัยมหาสารคาม
                            </p>
                        </div>
                    </div>
                </div>

                <p class="text-xs sm:text-sm text-gray-600 mt-4 max-w-2xl mx-auto leading-relaxed">
                    โครงการวิจัยเชิงบูรณาการเพื่ออนุรักษ์ ฟื้นฟู และยกระดับเศรษฐกิจสร้างสรรค์ของกลุ่มช่างแคน ช่างทำเครื่องดนตรี และหมอแคนพื้นบ้าน ผ่านการประยุกต์ใช้เทคโนโลยีดิจิทัลและระบบพิกัดสารสนเทศภูมิศาสตร์ (GIS)
                </p>
            </div>

            <!-- Research Objectives & Impacts Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8 max-w-6xl mx-auto">
                <!-- Pillar 1 -->
                <div class="p-6 rounded-2xl bg-amber-50/50 border border-amber-900/10 hover:shadow-md transition flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 rounded-xl bg-amber-600/10 text-amber-700 flex items-center justify-center mb-4">
                            <i data-lucide="map-pin" class="w-6 h-6"></i>
                        </div>
                        <h3 class="font-bold text-base text-gray-900">การสร้างแผนที่เครือข่ายช่างแคนและหมอแคน</h3>
                        <p class="text-xs text-gray-600 mt-2 leading-relaxed">
                            รวบรวมและจัดทำฐานข้อมูลพิกัด GPS ของช่างทำแคน ครูเพลง และศิลปินหมอแคนในภาคอีสาน เพื่อเชื่อมโยงช่างฝีมือชั้นครูกับผู้เรียนและนักดนตรีรุ่นใหม่ได้อย่างทั่วถึง
                        </p>
                    </div>
                    <div class="mt-4 pt-3 border-t border-amber-900/5 text-[11px] font-semibold text-isan-wood flex items-center gap-1">
                        <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-600"></i> แผนที่พิกัดดิจิทัลอัจฉริยะ
                    </div>
                </div>

                <!-- Pillar 2 -->
                <div class="p-6 rounded-2xl bg-blue-50/50 border border-blue-900/10 hover:shadow-md transition flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 rounded-xl bg-blue-600/10 text-blue-700 flex items-center justify-center mb-4">
                            <i data-lucide="sparkles" class="w-6 h-6"></i>
                        </div>
                        <h3 class="font-bold text-base text-gray-900">การยกระดับเศรษฐกิจสร้างสรรค์ชุมชน</h3>
                        <p class="text-xs text-gray-600 mt-2 leading-relaxed">
                            ส่งเสริมช่องทางการจำหน่ายแคนและอุปกรณ์ดนตรีอีสานคุณภาพสูงโดยตรงจากผู้ผลิต สร้างรายได้สู่ชุมชน และยกระดับมาตรฐานคุณภาพสินค้าพื้นถิ่นสู่ระดับสากล
                        </p>
                    </div>
                    <div class="mt-4 pt-3 border-t border-blue-900/5 text-[11px] font-semibold text-blue-900 flex items-center gap-1">
                        <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-600"></i> ตลาดซื้อขายที่เป็นธรรม
                    </div>
                </div>

                <!-- Pillar 3 -->
                <div class="p-6 rounded-2xl bg-orange-50/50 border border-orange-900/10 hover:shadow-md transition flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 rounded-xl bg-orange-600/10 text-orange-700 flex items-center justify-center mb-4">
                            <i data-lucide="book-open" class="w-6 h-6"></i>
                        </div>
                        <h3 class="font-bold text-base text-gray-900">การสืบสานและถ่ายทอดดนตรีศึกษา</h3>
                        <p class="text-xs text-gray-600 mt-2 leading-relaxed">
                            บันทึกองค์ความรู้ เทคนิคการเทียบเสียงแคน (440Hz Standard) ตลอดจนลายแคนโบราณ เพื่อเป็นแหล่งเรียนรู้ทางวิชาการแก่นิสิต นักศึกษา และผู้สนใจดนตรีพื้นบ้านไทย
                        </p>
                    </div>
                    <div class="mt-4 pt-3 border-t border-orange-900/5 text-[11px] font-semibold text-orange-900 flex items-center gap-1">
                        <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-600"></i> บูรณาการหลักสูตรดุริยางคศิลป์
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-isan-indigo text-gray-300 py-10 mt-auto border-t border-amber-950">
        <div class="w-full px-4 sm:px-6 lg:px-10 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div>
                <p class="font-bold text-white text-base">Khaen Complex Center — งานวิจัยเพื่อพัฒนาชุมชนดนตรีอีสาน</p>
                <p class="text-xs text-gray-400 mt-1">
                    โดย อาจารย์ ดร.สัญชัย ด้วงบุ้ง ภาควิชาดนตรีศึกษา วิทยาลัยดุริยางคศิลป์ มหาวิทยาลัยมหาสารคาม
                </p>
            </div>
            <div class="text-xs text-gray-400">
                Leaflet.js • PHP PDO • Tailwind CSS
            </div>
        </div>
    </footer>

    <!-- Frontend Map & Application Script -->
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // 1. Setup Leaflet Map centered at Isan region (Khon Kaen as focal point)
        const map = L.map('map', {
            zoomControl: true,
            scrollWheelZoom: true
        }).setView([16.4322, 102.8236], 8);

        // Standard OpenStreetMap Tile Layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Marker layer groups
        let markersLayer = L.layerGroup().addTo(map);
        let userLocationMarker = null;
        let allLocations = [];
        let currentCategoryFilter = 'all';

        // 2. Fetch Pin Data from get_locations.php
        async function fetchLocations(category = 'all') {
            try {
                const response = await fetch(`get_locations.php?category=${encodeURIComponent(category)}`);
                const result = await response.json();

                if (result.status === 'success') {
                    allLocations = result.data;
                    renderMarkers(allLocations);
                    updateCounters();
                } else {
                    console.error('API Error:', result.message);
                }
            } catch (err) {
                console.error('Fetch error:', err);
            }
        }

        // Global marker registry for pan/zoom on click
        let markerMap = {};

        // 3. Render Leaflet Markers with Custom Badges and Popups
        function renderMarkers(items) {
            markersLayer.clearLayers();
            markerMap = {};

            items.forEach(item => {
                // Determine icon CSS class based on role/category
                let pinClass = 'pin-maker';
                let iconName = 'wrench';
                if (item.category === 'player') {
                    pinClass = 'pin-player';
                    iconName = 'music';
                } else if (item.category === 'shop') {
                    pinClass = 'pin-shop';
                    iconName = 'store';
                } else if (item.category === 'learning_center') {
                    pinClass = 'pin-learning';
                    iconName = 'graduation-cap';
                }

                // Custom HTML Icon for Leaflet
                const customIcon = L.divIcon({
                    className: 'custom-leaflet-marker',
                    html: `
                        <div class="custom-pin ${pinClass}">
                            <i data-lucide="${iconName}" style="width: 18px; height: 18px;"></i>
                        </div>
                    `,
                    iconSize: [38, 38],
                    iconAnchor: [19, 19],
                    popupAnchor: [0, -20]
                });

                // Modern Card Popup Content (Clickable User Profile)
                const popupContent = `
                    <div class="w-72 bg-white text-gray-800 rounded-2xl overflow-hidden shadow-lg">
                        <div class="h-24 bg-gradient-to-r from-amber-700 to-amber-900 relative">
                            <span class="absolute top-2 right-2 text-[10px] font-bold px-2 py-0.5 rounded-full text-white shadow-sm" style="background-color: ${item.badge_color};">
                                ${item.category_name.split(' ')[0]}
                            </span>
                        </div>
                        <div class="px-4 pb-4 -mt-10 relative">
                            <a href="profile.php?id=${item.id}" class="inline-block group" title="คลิกเพื่อดูโปรไฟล์">
                                <img src="${item.avatar}" class="w-16 h-16 rounded-full border-4 border-white shadow-md object-cover bg-white mb-2 group-hover:scale-105 transition">
                            </a>
                            <div class="flex items-center gap-1.5">
                                <a href="profile.php?id=${item.id}" class="font-bold text-sm text-gray-900 hover:text-amber-800 transition flex items-center gap-1 leading-tight group" title="คลิกเพื่อดูโปรไฟล์">
                                    <span>${item.name}</span>
                                    <i data-lucide="external-link" class="w-3.5 h-3.5 text-gray-400 group-hover:text-amber-700 transition"></i>
                                </a>
                                ${item.is_verified ? '<i data-lucide="badge-check" class="w-4 h-4 text-blue-500 fill-blue-50 shrink-0" title="ยืนยันตัวตนแล้ว"></i>' : ''}
                            </div>
                            <p class="text-[11px] text-amber-700 font-semibold mt-0.5">${item.title_desc || ''}</p>
                            
                            <p class="text-xs text-gray-600 mt-2 line-clamp-2 leading-relaxed">
                                ${item.bio || 'ไม่มีคำอธิบายเพิ่มเติม'}
                            </p>

                            <!-- Button to visit user profile -->
                            <div class="mt-3">
                                <a href="profile.php?id=${item.id}" class="w-full py-2 px-3 bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200/80 rounded-xl text-xs font-semibold transition flex items-center justify-center gap-1.5 shadow-xs">
                                    <i data-lucide="user" class="w-3.5 h-3.5 text-amber-700"></i>
                                    <span>เข้าดูโปรไฟล์ผู้ใช้งาน</span>
                                </a>
                            </div>

                            <div class="mt-3 pt-2 border-t border-gray-100 flex items-center justify-between text-xs">
                                <span class="text-gray-500 flex items-center gap-1">
                                    <i data-lucide="map-pin" class="w-3 h-3 text-red-500"></i> ${item.province}
                                </span>
                                <a href="tel:${item.phone}" class="text-isan-terracotta font-semibold hover:underline flex items-center gap-1">
                                    <i data-lucide="phone" class="w-3 h-3"></i> ${item.phone}
                                </a>
                            </div>
                        </div>
                    </div>
                `;

                const marker = L.marker([item.lat, item.lng], { icon: customIcon });
                marker.bindPopup(popupContent);
                
                // Refresh Lucide icons when popup opens
                marker.on('popupopen', () => {
                    lucide.createIcons();
                });

                markersLayer.addLayer(marker);
                markerMap[item.id] = marker;
            });

            // Re-render Lucide icons on newly inserted DOM elements
            lucide.createIcons();

            // Render side directory
            renderSideDirectory(items);
        }

        // 3.5 Render Side Directory Cards
        function renderSideDirectory(items) {
            const container = document.getElementById('side-directory-list');
            const counter = document.getElementById('side-list-counter');
            if (!container) return;

            counter.textContent = `${items.length} รายการ`;

            if (items.length === 0) {
                container.innerHTML = `
                    <div class="p-8 text-center text-gray-400">
                        <i data-lucide="map-pin-off" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                        <p class="text-xs">ไม่พบพิกัดที่ตรงกับเงื่อนไขการค้นหา</p>
                    </div>
                `;
                lucide.createIcons();
                return;
            }

            container.innerHTML = items.map(item => `
                <div class="p-3 rounded-xl border border-gray-100 hover:border-amber-300 hover:bg-amber-50/40 transition flex items-center gap-3 group relative">
                    <a href="profile.php?id=${item.id}" title="ดูโปรไฟล์">
                        <img src="${item.avatar}" class="w-11 h-11 rounded-full object-cover border border-amber-200 hover:scale-105 transition shrink-0">
                    </a>
                    <div onclick="focusPinOnMap(${item.id}, ${item.lat}, ${item.lng})" class="flex-1 min-w-0 cursor-pointer">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold text-gray-900 group-hover:text-amber-800 truncate">${item.name}</h4>
                            <span class="text-[10px] px-2 py-0.5 rounded-full text-white font-medium ml-1 shrink-0" style="background-color: ${item.badge_color};">
                                ${item.category_name.split(' ')[0]}
                            </span>
                        </div>
                        <p class="text-[11px] text-gray-500 truncate mt-0.5">${item.title_desc || item.address}</p>
                        <div class="flex items-center justify-between mt-1 text-[11px] text-gray-400">
                            <span class="flex items-center gap-1"><i data-lucide="map-pin" class="w-3 h-3 text-red-500"></i> ${item.province}</span>
                            <span class="text-isan-terracotta font-medium">${item.phone}</span>
                        </div>
                    </div>
                    <a href="profile.php?id=${item.id}" class="text-gray-400 hover:text-amber-700 p-1.5 rounded-lg hover:bg-white transition border border-transparent hover:border-gray-200" title="เข้าดูโปรไฟล์">
                        <i data-lucide="user" class="w-4 h-4"></i>
                    </a>
                </div>
            `).join('');

            lucide.createIcons();
        }

        // Focus map onto a specific person's pin
        function focusPinOnMap(id, lat, lng) {
            map.flyTo([lat, lng], 13, { animate: true, duration: 1.2 });
            if (markerMap[id]) {
                setTimeout(() => {
                    markerMap[id].openPopup();
                }, 1200);
            }
        }

        // 4. Combined Filtering (Category + Search Keyword + Province)
        function filterCategory(category) {
            currentCategoryFilter = category;

            // Reset all filter buttons
            const styles = {
                'all':             { active: 'bg-isan-indigo text-white shadow-sm', inactive: 'bg-gray-100 text-gray-700 hover:bg-gray-200' },
                'maker':           { active: 'bg-amber-600 text-white shadow-sm', inactive: 'bg-amber-50 text-amber-900 hover:bg-amber-100 border border-amber-200/80' },
                'player':          { active: 'bg-blue-700 text-white shadow-sm', inactive: 'bg-blue-50 text-blue-900 hover:bg-blue-100 border border-blue-200/80' },
                'shop':            { active: 'bg-orange-600 text-white shadow-sm', inactive: 'bg-orange-50 text-orange-900 hover:bg-orange-100 border border-orange-200/80' },
                'learning_center': { active: 'bg-emerald-600 text-white shadow-sm', inactive: 'bg-emerald-50 text-emerald-900 hover:bg-emerald-100 border border-emerald-200/80' }
            };

            ['all', 'maker', 'player', 'shop', 'learning_center'].forEach(cat => {
                const btn = document.getElementById(`filter-${cat}`);
                if (btn) {
                    btn.className = `filter-btn inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-semibold transition ${cat === category ? styles[cat].active : styles[cat].inactive}`;
                }
            });

            applyFilters();
        }

        function applyFilters() {
            const query = (document.getElementById('map-search-input')?.value || '').toLowerCase().trim();
            const province = document.getElementById('province-filter')?.value || 'all';

            const filtered = allLocations.filter(loc => {
                const matchCategory = currentCategoryFilter === 'all' || 
                    loc.category === currentCategoryFilter || 
                    (loc.category && loc.category.split(',').map(s => s.trim()).includes(currentCategoryFilter));
                const matchProvince = province === 'all' || loc.province === province;
                const matchQuery = !query || 
                    loc.name.toLowerCase().includes(query) || 
                    (loc.title_desc && loc.title_desc.toLowerCase().includes(query)) ||
                    (loc.bio && loc.bio.toLowerCase().includes(query)) ||
                    (loc.province && loc.province.toLowerCase().includes(query));

                return matchCategory && matchProvince && matchQuery;
            });

            renderMarkers(filtered);
        }

        // 5. Update category counter numbers in buttons
        function updateCounters() {
            document.getElementById('count-all').textContent = allLocations.length;
            document.getElementById('count-maker').textContent = allLocations.filter(i => i.category === 'maker' || (i.category && i.category.split(',').map(s=>s.trim()).includes('maker'))).length;
            document.getElementById('count-player').textContent = allLocations.filter(i => i.category === 'player' || (i.category && i.category.split(',').map(s=>s.trim()).includes('player'))).length;
            document.getElementById('count-shop').textContent = allLocations.filter(i => i.category === 'shop' || (i.category && i.category.split(',').map(s=>s.trim()).includes('shop'))).length;
            const learningCountElem = document.getElementById('count-learning');
            if (learningCountElem) {
                learningCountElem.textContent = allLocations.filter(i => i.category === 'learning_center' || (i.category && i.category.split(',').map(s=>s.trim()).includes('learning_center'))).length;
            }
        }

        // 6. Near Me (HTML5 Geolocation API)
        function locateUserPosition() {
            if (!navigator.geolocation) {
                alert('เบราว์เซอร์ของคุณไม่รองรับการระบุตำแหน่ง GPS');
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const userLat = position.coords.latitude;
                    const userLng = position.coords.longitude;

                    // Move map to user's position
                    map.flyTo([userLat, userLng], 12, { animate: true, duration: 1.5 });

                    // Remove existing user marker if any
                    if (userLocationMarker) {
                        map.removeLayer(userLocationMarker);
                    }

                    // Create distinctive user marker
                    const userIcon = L.divIcon({
                        className: 'user-pin-marker',
                        html: `
                            <div class="custom-pin pin-user animate-bounce">
                                <i data-lucide="navigation" style="width: 18px; height: 18px;"></i>
                            </div>
                        `,
                        iconSize: [38, 38],
                        iconAnchor: [19, 19]
                    });

                    userLocationMarker = L.marker([userLat, userLng], { icon: userIcon })
                        .addTo(map)
                        .bindPopup(`
                            <div class="p-3 text-center">
                                <p class="text-xs font-bold text-emerald-800">คุณอยู่ที่นี่ (ตำแหน่งของคุณ)</p>
                                <p class="text-[11px] text-gray-500">${userLat.toFixed(4)}, ${userLng.toFixed(4)}</p>
                            </div>
                        `)
                        .openPopup();

                    lucide.createIcons();
                },
                (error) => {
                    alert('ไม่สามารถระบุพิกัดได้: ' + error.message);
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        }

        // 7. Modal Handlers for Updating Profile Lat/Lng
        function openUpdateLocationModal() {
            document.getElementById('location-modal').classList.remove('hidden');
        }

        function closeUpdateLocationModal() {
            document.getElementById('location-modal').classList.add('hidden');
        }

        function getBrowserCoordinates() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(pos => {
                    document.getElementById('input-lat').value = pos.coords.latitude.toFixed(6);
                    document.getElementById('input-lng').value = pos.coords.longitude.toFixed(6);
                }, err => {
                    alert('ไม่สามารถดึงพิกัดจากอุปกรณ์ได้: ' + err.message);
                });
            }
        }

        async function handleLocationSubmit(e) {
            e.preventDefault();
            const lat = document.getElementById('input-lat').value;
            const lng = document.getElementById('input-lng').value;

            try {
                const response = await fetch('update_location.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ latitude: lat, longitude: lng })
                });
                const res = await response.json();

                if (res.status === 'success') {
                    alert('อัปเดตพิกัดของคุณสำเร็จแล้ว!');
                    closeUpdateLocationModal();
                    fetchLocations(); // Refresh pins on map
                } else {
                    alert('เกิดข้อผิดพลาด: ' + res.message);
                }
            } catch (error) {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
            }
        }

        // 7.5 Marketplace Item Modal Handlers
        function openAddItemModal() {
            document.getElementById('add-item-modal').classList.remove('hidden');
        }

        function closeAddItemModal() {
            document.getElementById('add-item-modal').classList.add('hidden');
        }

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
                    alert('ลงประกาศขายแคนเรียบร้อยแล้ว!');
                    closeAddItemModal();
                    form.reset();
                    window.location.reload(); // Refresh to see newly added card in grid
                } else {
                    alert('เกิดข้อผิดพลาด: ' + res.message);
                }
            } catch (err) {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
            }
        }

        // 8. Role Switcher Dropdown Click Handler
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

        // Close dropdown when clicking outside
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
        // Initial Load
        fetchLocations();
    </script>
</body>
</html>
