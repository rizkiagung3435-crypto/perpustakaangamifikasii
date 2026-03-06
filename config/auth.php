<?php
/**
 * Authentication Check - Perpustakaan Digital Gamifikasi
 * SMK Mardi Yuana Cikembar
 */

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Optional: Check session timeout (30 minutes)
$timeout = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}

$_SESSION['last_activity'] = time();

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin';
}

// Function to check if user is student
function isSiswa() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'siswa';
}

// Function to redirect if not admin
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: ../dashboard.php');
        exit();
    }
}

// Function to redirect if not student
function requireSiswa() {
    if (!isSiswa()) {
        header('Location: ../admin/dashboard_admin.php');
        exit();
    }
}
?>