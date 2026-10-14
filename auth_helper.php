<?php
/**
 * Simple Authentication Helper & Session Simulator
 * Supports Google SSO UI hook and role-switching simulation (Admin / User / Guest)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle Switch / Mock Login action
if (isset($_GET['switch_role'])) {
    $targetRole = $_GET['switch_role'];
    if ($targetRole === 'admin') {
        $_SESSION['user'] = [
            'id' => 1,
            'name' => 'แอดมิน ชุมชนคนรักแคน',
            'email' => 'admin@khaencomplex.local',
            'role' => 'admin',
            'category' => 'player',
            'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=400&q=80',
            'lat' => 16.4322,
            'lng' => 102.8236
        ];
    } elseif ($targetRole === 'maker') {
        $_SESSION['user'] = [
            'id' => 2,
            'name' => 'ช่างพ่อบุญมี สืบสานลายแคน',
            'email' => 'boonmee@maker.local',
            'role' => 'user',
            'category' => 'maker',
            'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=400&q=80',
            'lat' => 16.4419,
            'lng' => 102.7981
        ];
    } elseif ($targetRole === 'learning_center') {
        $_SESSION['user'] = [
            'id' => 8,
            'name' => 'ศูนย์การเรียนรู้ภูมิปัญญาแคนและดนตรีอีสาน',
            'email' => 'learning@khaencomplex.local',
            'role' => 'user',
            'category' => 'learning_center',
            'avatar' => 'https://images.unsplash.com/photo-1524178232363-1fb2b075b655?auto=format&fit=crop&w=400&q=80',
            'lat' => 16.2468,
            'lng' => 103.2502
        ];
    } elseif ($targetRole === 'logout') {
        unset($_SESSION['user']);
    }
    // If request comes with no session persistence or during testing, redirect only if not requesting same URL or handle gracefully
    if (!isset($_GET['noredirect'])) {
        $redirectUrl = strtok($_SERVER['REQUEST_URI'], '?');
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// Default to a logged-in User if not set, for smooth initial testing
if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = [
        'id' => 2,
        'name' => 'ช่างพ่อบุญมี สืบสานลายแคน',
        'email' => 'boonmee@maker.local',
        'role' => 'user',
        'category' => 'maker',
        'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=400&q=80',
        'lat' => 16.4419,
        'lng' => 102.7981
    ];
}

function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

function isAdmin() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}
