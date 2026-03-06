<?php
/**
 * Halaman Data User - Admin Perpustakaan Digital Gamifikasi
 * SMK Mardi Yuana Cikembar
 */

require_once '../config/koneksi.php';
require_once '../config/auth.php';

// Check if user is admin
if (!isAdmin()) {
    header('Location: ../dashboard.php');
    exit();
}

$database = new Database();
$conn = $database->getConnection();

// Get admin data for header
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get messages from session
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Cek apakah kolom is_active ada
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
$has_is_active = $check_column->num_rows > 0;

// Get filter parameters
$search = isset($_GET['search']) ? $database->escapeString($_GET['search']) : '';
$role = isset($_GET['role']) ? $database->escapeString($_GET['role']) : '';
$status = isset($_GET['status']) ? $database->escapeString($_GET['status']) : '';

// Build query with dynamic fields
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM peminjaman WHERE id_user = u.id) as total_pinjam,
          (SELECT COUNT(*) FROM user_achievement WHERE id_user = u.id) as total_badges
          FROM users u WHERE 1=1";

if ($search) {
    $query .= " AND (u.nama LIKE '%$search%' OR u.email LIKE '%$search%' OR u.kelas LIKE '%$search%')";
}
if ($role) {
    $query .= " AND u.role = '$role'";
}
if ($status && $has_is_active) {
    $query .= " AND u.is_active = " . ($status == 'aktif' ? '1' : '0');
}

$query .= " ORDER BY u.created_at DESC";
$users = $conn->query($query);

// Get statistics
$total_siswa = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'siswa'")->fetch_assoc();
$total_admin = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'")->fetch_assoc();

if ($has_is_active) {
    $total_aktif = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1")->fetch_assoc();
    $total_nonaktif = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_active = 0")->fetch_assoc();
} else {
    $total_aktif = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc();
    $total_nonaktif = ['total' => 0];
}

$page_title = "Data User - Admin Perpustakaan";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(145deg, #0f0c1f 0%, #1a1b3b 100%);
            min-height: 100vh;
            color: #e2e8f0;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background with Particles */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .bg-gradient {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 30%, rgba(102, 126, 234, 0.15) 0%, transparent 50%),
                        radial-gradient(circle at 80% 70%, rgba(118, 75, 162, 0.15) 0%, transparent 50%);
        }

        .bg-grid {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
        }

        .bg-shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
            filter: blur(60px);
        }

        .bg-shape:nth-child(1) {
            width: 600px;
            height: 600px;
            top: -300px;
            right: -300px;
            background: linear-gradient(135deg, #667eea20, #764ba220);
            animation: floatShape 15s ease-in-out infinite;
        }

        .bg-shape:nth-child(2) {
            width: 500px;
            height: 500px;
            bottom: -250px;
            left: -250px;
            background: linear-gradient(135deg, #48dbfb20, #1dd1a120);
            animation: floatShape 12s ease-in-out infinite reverse;
        }

        @keyframes floatShape {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-30px) scale(1.05); }
        }

        /* Navigation */
        .navbar {
            background: rgba(15, 12, 31, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .nav-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 12px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo a {
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(145deg, #667eea, #764ba2);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            box-shadow: 0 10px 20px -5px rgba(102, 126, 234, 0.3);
            transition: all 0.3s;
        }

        .logo-icon:hover {
            transform: scale(1.05) rotate(5deg);
        }

        .logo-text {
            font-size: 22px;
            font-weight: 700;
            background: linear-gradient(145deg, #fff, #e2e8f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 8px;
            flex-wrap: wrap;
        }

        .nav-link {
            color: #a0aec0;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 14px;
            border: 1px solid transparent;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateY(-2px);
        }

        .nav-link.active {
            background: linear-gradient(145deg, #667eea, #764ba2);
            color: white;
            border-color: transparent;
            box-shadow: 0 10px 20px -5px rgba(102, 126, 234, 0.3);
        }

        .admin-badge {
            background: linear-gradient(145deg, #fbbf24, #f59e0b);
            color: #0f0c1f;
            padding: 6px 16px;
            border-radius: 40px;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.3px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(255, 255, 255, 0.03);
            padding: 8px 20px 8px 12px;
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s;
        }

        .user-profile:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            background: linear-gradient(145deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            font-weight: 600;
        }

        .user-info {
            line-height: 1.4;
        }

        .user-name {
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .user-role {
            color: #fbbf24;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        /* Main Container */
        .main-container {
            max-width: 1440px;
            margin: 40px auto;
            padding: 0 30px;
            position: relative;
            z-index: 1;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-title {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .page-title h1 {
            font-size: 36px;
            font-weight: 800;
            color: white;
            display: flex;
            align-items: center;
            gap: 15px;
            letter-spacing: -0.5px;
        }

        .page-title h1 i {
            background: linear-gradient(145deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 40px;
        }

        .page-badge {
            background: linear-gradient(145deg, #fbbf24, #f59e0b);
            color: #0f0c1f;
            padding: 6px 20px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
        }

        .btn-primary {
            background: linear-gradient(145deg, #667eea, #764ba2);
            color: white;
            padding: 14px 28px;
            border: none;
            border-radius: 18px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            box-shadow: 0 15px 25px -8px rgba(102, 126, 234, 0.4);
            letter-spacing: 0.3px;
        }

        .btn-primary:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 30px -8px rgba(102, 126, 234, 0.6);
        }

        .btn-primary i {
            font-size: 16px;
            transition: transform 0.3s;
        }

        .btn-primary:hover i {
            transform: translateX(5px);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 28px;
            padding: 24px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #667eea, #764ba2, transparent);
            transform: translateX(-100%);
            transition: transform 0.5s;
        }

        .stat-card:hover::before {
            transform: translateX(100%);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(102, 126, 234, 0.3);
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.5);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(145deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #667eea;
            margin-bottom: 16px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .stat-info h3 {
            font-size: 32px;
            font-weight: 800;
            color: white;
            line-height: 1.2;
            margin-bottom: 4px;
            letter-spacing: -0.5px;
        }

        .stat-info p {
            color: #a0aec0;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stat-info p i {
            font-size: 12px;
        }

        /* Filter Box */
        .filter-box {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .filter-form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-input {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .filter-input i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 16px;
            z-index: 1;
        }

        .filter-input input,
        .filter-input select {
            width: 100%;
            padding: 16px 18px 16px 52px;
            border: 2px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            font-size: 15px;
            background: rgba(0, 0, 0, 0.2);
            color: white;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: all 0.3s;
        }

        .filter-input select {
            padding: 16px 18px 16px 52px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%23667eea' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 18px center;
            background-size: 18px;
        }

        .filter-input select option {
            background: #1a1b3b;
            color: white;
        }

        .filter-input input:focus,
        .filter-input select:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(0, 0, 0, 0.3);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .filter-input input::placeholder {
            color: #4a5568;
            font-weight: 400;
        }

        .filter-btn {
            padding: 16px 32px;
            background: linear-gradient(145deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 20px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            letter-spacing: 0.3px;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px -8px rgba(102, 126, 234, 0.5);
        }

        .filter-btn.reset {
            background: rgba(255, 255, 255, 0.05);
            color: #e2e8f0;
        }

        .filter-btn.reset:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Alert Messages */
        .alert {
            padding: 18px 24px;
            border-radius: 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 14px;
            animation: slideIn 0.5s ease;
            font-size: 15px;
            font-weight: 500;
            border-left: 4px solid transparent;
            backdrop-filter: blur(10px);
        }

        .alert-success {
            background: rgba(72, 187, 120, 0.1);
            border-left-color: #48bb78;
            color: #48bb78;
            border: 1px solid rgba(72, 187, 120, 0.2);
        }

        .alert-error {
            background: rgba(245, 101, 101, 0.1);
            border-left-color: #f56565;
            color: #f56565;
            border: 1px solid rgba(245, 101, 101, 0.2);
        }

        .alert i {
            font-size: 22px;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Table Card */
        .table-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 28px;
            padding: 28px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .table-header h2 {
            font-size: 20px;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-header h2 i {
            color: #667eea;
        }

        .table-header span {
            color: #a0aec0;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.03);
            padding: 6px 14px;
            border-radius: 40px;
        }

        .table-responsive {
            overflow-x: auto;
            border-radius: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 16px;
            color: #a0aec0;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            background: rgba(0, 0, 0, 0.2);
        }

        td {
            padding: 16px;
            color: #e2e8f0;
            font-size: 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.02);
        }

        tr {
            transition: all 0.3s;
        }

        tr:hover td {
            background: rgba(102, 126, 234, 0.05);
        }

        .user-avatar-small {
            width: 45px;
            height: 45px;
            background: linear-gradient(145deg, #667eea, #764ba2);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .user-info-cell {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .user-name {
            font-weight: 700;
            color: white;
            font-size: 15px;
        }

        .user-email {
            font-size: 12px;
            color: #a0aec0;
            margin-top: 2px;
        }

        .role-badge {
            padding: 6px 14px;
            border-radius: 40px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
            letter-spacing: 0.3px;
        }

        .role-admin {
            background: linear-gradient(145deg, #fbbf24, #f59e0b);
            color: #0f0c1f;
            box-shadow: 0 5px 15px rgba(251, 191, 36, 0.2);
        }

        .role-siswa {
            background: linear-gradient(145deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 40px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
            letter-spacing: 0.3px;
        }

        .status-aktif {
            background: rgba(72, 187, 120, 0.15);
            color: #48bb78;
            border: 1px solid rgba(72, 187, 120, 0.3);
        }

        .status-nonaktif {
            background: rgba(245, 101, 101, 0.15);
            color: #f56565;
            border: 1px solid rgba(245, 101, 101, 0.3);
        }

        .xp-level {
            display: inline-block;
            background: rgba(102, 126, 234, 0.1);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            color: #667eea;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .stat-icons {
            display: flex;
            gap: 10px;
            font-size: 12px;
        }

        .stat-icons span {
            display: flex;
            align-items: center;
            gap: 4px;
            background: rgba(255, 255, 255, 0.03);
            padding: 4px 8px;
            border-radius: 20px;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            border: 1px solid transparent;
            font-size: 16px;
        }

        .btn-icon.view {
            background: rgba(102, 126, 234, 0.15);
            color: #667eea;
            border-color: rgba(102, 126, 234, 0.3);
        }

        .btn-icon.view:hover {
            background: #667eea;
            color: white;
            transform: translateY(-3px);
            border-color: transparent;
        }

        .btn-icon.edit {
            background: rgba(72, 187, 120, 0.15);
            color: #48bb78;
            border-color: rgba(72, 187, 120, 0.3);
        }

        .btn-icon.edit:hover {
            background: #48bb78;
            color: white;
            transform: translateY(-3px);
        }

        .btn-icon.toggle {
            background: rgba(237, 137, 54, 0.15);
            color: #ed8936;
            border-color: rgba(237, 137, 54, 0.3);
        }

        .btn-icon.toggle:hover {
            background: #ed8936;
            color: white;
            transform: translateY(-3px);
        }

        .btn-icon.reset {
            background: rgba(245, 101, 101, 0.15);
            color: #f56565;
            border-color: rgba(245, 101, 101, 0.3);
        }

        .btn-icon.reset:hover {
            background: #f56565;
            color: white;
            transform: translateY(-3px);
        }

        .btn-icon.delete {
            background: rgba(245, 101, 101, 0.15);
            color: #f56565;
            border-color: rgba(245, 101, 101, 0.3);
        }

        .btn-icon.delete:hover {
            background: #f56565;
            color: white;
            transform: translateY(-3px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-icon {
            font-size: 80px;
            color: rgba(102, 126, 234, 0.3);
            margin-bottom: 24px;
            animation: bounce 3s infinite;
        }

        .empty-state h3 {
            color: white;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .empty-state p {
            color: #a0aec0;
            margin-bottom: 24px;
            font-size: 16px;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }

        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 12, 31, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            gap: 24px;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-spinner {
            width: 70px;
            height: 70px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loading-text {
            color: white;
            font-size: 20px;
            font-weight: 600;
            letter-spacing: 1px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
            }

            .page-header {
                flex-direction: column;
                text-align: center;
            }

            .page-title h1 {
                justify-content: center;
            }

            .page-badge {
                margin: 0 auto;
            }

            td, th {
                padding: 12px;
                font-size: 13px;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-form {
                flex-direction: column;
            }

            .filter-input {
                width: 100%;
            }

            .filter-btn {
                width: 100%;
                justify-content: center;
            }

            .action-buttons {
                flex-wrap: wrap;
                justify-content: center;
            }

            .btn-icon {
                width: 100%;
            }

            .user-info-cell {
                flex-direction: column;
                text-align: center;
            }

            .stat-icons {
                flex-direction: column;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(145deg, #667eea, #764ba2);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(145deg, #764ba2, #667eea);
        }

        /* Tooltip */
        [data-tooltip] {
            position: relative;
            cursor: help;
        }

        [data-tooltip]:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 8px 12px;
            background: linear-gradient(145deg, #667eea, #764ba2);
            color: white;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
            z-index: 1000;
            box-shadow: 0 10px 20px -5px rgba(102, 126, 234, 0.4);
            animation: fadeIn 0.3s ease;
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="animated-bg">
        <div class="bg-gradient"></div>
        <div class="bg-grid"></div>
        <div class="bg-shape"></div>
        <div class="bg-shape"></div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Memuat data...</div>
    </div>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="dashboard_admin.php">
                    <div class="logo-icon">⚡</div>
                    <span class="logo-text">AdminPanel</span>
                </a>
            </div>
            
            <ul class="nav-links">
                <li><a href="dashboard_admin.php" class="nav-link"><i class="fas fa-home"></i>Dashboard</a></li>
                <li><a href="tambah_buku.php" class="nav-link"><i class="fas fa-plus-circle"></i>Tambah</a></li>
                <li><a href="data_buku.php" class="nav-link"><i class="fas fa-book"></i>Buku</a></li>
                <li><a href="data_user.php" class="nav-link active"><i class="fas fa-users"></i>Users</a></li>
                <li><a href="laporan.php" class="nav-link"><i class="fas fa-chart-bar"></i>Laporan</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
            </ul>

            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['nama'], 0, 1)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user['nama']); ?></div>
                    <div class="user-role">
                        <i class="fas fa-crown"></i> ADMIN
                    </div>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-shield-alt"></i>
                </div>
            </div>
        </div>
    </nav>

    <main class="main-container">
        <!-- Page Header with Tambah User Button -->
        <div class="page-header" data-aos="fade-up">
            <div class="page-title">
                <h1>
                    <i class="fas fa-users"></i>
                    Manajemen User
                </h1>
                <div class="page-badge">
                    <i class="fas fa-database"></i>
                    Total <?php echo $total_siswa['total'] + $total_admin['total']; ?> User
                </div>
            </div>
            <a href="tambah_user.php" class="btn-primary">
                <i class="fas fa-user-plus"></i>
                Tambah User Baru
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_siswa['total']; ?></h3>
                    <p><i class="fas fa-circle" style="color: #667eea;"></i> Total Siswa</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_admin['total']; ?></h3>
                    <p><i class="fas fa-circle" style="color: #fbbf24;"></i> Total Admin</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_aktif['total']; ?></h3>
                    <p><i class="fas fa-circle" style="color: #48bb78;"></i> User Aktif</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_nonaktif['total']; ?></h3>
                    <p><i class="fas fa-circle" style="color: #f56565;"></i> User Nonaktif</p>
                </div>
            </div>
        </div>

        <!-- Filter Box -->
        <div class="filter-box" data-aos="fade-up" data-aos-delay="150">
            <form method="GET" action="" class="filter-form">
                <div class="filter-input">
                    <i class="fas fa-search"></i>
                    <input type="text" 
                           name="search" 
                           placeholder="Cari berdasarkan nama, email, atau kelas..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-input">
                    <i class="fas fa-user-tag"></i>
                    <select name="role">
                        <option value="">Semua Role</option>
                        <option value="siswa" <?php echo $role == 'siswa' ? 'selected' : ''; ?>>Siswa</option>
                        <option value="admin" <?php echo $role == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                
                <?php if ($has_is_active): ?>
                <div class="filter-input">
                    <i class="fas fa-circle"></i>
                    <select name="status">
                        <option value="">Semua Status</option>
                        <option value="aktif" <?php echo $status == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="nonaktif" <?php echo $status == 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                    </select>
                </div>
                <?php endif; ?>
                
                <button type="submit" class="filter-btn">
                    <i class="fas fa-filter"></i>
                    Filter
                </button>
                
                <?php if ($search || $role || $status): ?>
                    <a href="data_user.php" class="filter-btn reset">
                        <i class="fas fa-times"></i>
                        Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success" data-aos="fade-up">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error" data-aos="fade-up">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Table -->
        <div class="table-card" data-aos="fade-up" data-aos-delay="200">
            <div class="table-header">
                <h2>
                    <i class="fas fa-list"></i>
                    Daftar User
                </h2>
                <span>
                    <i class="fas fa-sort"></i>
                    Terbaru
                </span>
            </div>

            <?php if ($users && $users->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Kelas/Jurusan</th>
                                <th>XP & Level</th>
                                <th>Statistik</th>
                                <th>Status</th>
                                <th>Bergabung</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $users->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="user-info-cell">
                                        <div class="user-avatar-small">
                                            <?php echo strtoupper(substr($row['nama'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="user-name"><?php echo htmlspecialchars($row['nama']); ?></div>
                                            <div class="user-email"><?php echo htmlspecialchars($row['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge role-<?php echo $row['role']; ?>">
                                        <?php echo $row['role'] == 'admin' ? 'Admin' : 'Siswa'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['kelas'] || $row['jurusan']): ?>
                                        <strong><?php echo htmlspecialchars($row['kelas'] ?: '-'); ?></strong>
                                        <div style="font-size: 11px; color: #a0aec0; margin-top: 2px;">
                                            <?php echo htmlspecialchars($row['jurusan'] ?: '-'); ?>
                                        </div>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="xp-level">
                                        <i class="fas fa-star" style="color: #fbbf24;"></i>
                                        <?php echo number_format($row['xp']); ?> XP
                                    </span>
                                    <div style="font-size: 11px; color: #a0aec0; margin-top: 4px;">
                                        Level <?php echo $row['level']; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="stat-icons">
                                        <span><i class="fas fa-book" style="color: #667eea;"></i> <?php echo $row['total_pinjam']; ?></span>
                                        <span><i class="fas fa-medal" style="color: #fbbf24;"></i> <?php echo $row['total_badges']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($has_is_active): ?>
                                        <span class="status-badge status-<?php echo $row['is_active'] ? 'aktif' : 'nonaktif'; ?>">
                                            <?php echo $row['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-aktif">Aktif</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="detail_user.php?id=<?php echo $row['id']; ?>" class="btn-icon view" title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_user.php?id=<?php echo $row['id']; ?>" class="btn-icon edit" title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($has_is_active && $row['id'] != $_SESSION['user_id']): ?>
                                        <a href="#" class="btn-icon toggle" title="<?php echo $row['is_active'] ? 'Nonaktifkan' : 'Aktifkan'; ?>" onclick="return toggleStatus(<?php echo $row['id']; ?>, '<?php echo $row['is_active'] ? 'nonaktifkan' : 'aktifkan'; ?>', '<?php echo htmlspecialchars($row['nama']); ?>')">
                                            <i class="fas fa-<?php echo $row['is_active'] ? 'ban' : 'check-circle'; ?>"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                        <a href="#" class="btn-icon reset" title="Reset Password" onclick="return resetPassword(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['nama']); ?>')">
                                            <i class="fas fa-key"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Tidak Ada Data User</h3>
                    <p><?php echo $search ? 'Tidak ditemukan user dengan kata kunci "' . htmlspecialchars($search) . '"' : 'Belum ada user yang terdaftar'; ?></p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- AOS Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        // Initialize AOS
        AOS.init({
            once: true,
            duration: 800,
            easing: 'ease-in-out'
        });

        // Toggle user status
        function toggleStatus(id, action, nama) {
            Swal.fire({
                title: 'Konfirmasi',
                html: `Apakah Anda yakin ingin <strong>${action}</strong> user <strong>"${nama}"</strong>?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: action === 'nonaktifkan' ? '#f56565' : '#48bb78',
                cancelButtonColor: '#4a5568',
                confirmButtonText: 'Ya, ' + action + '!',
                cancelButtonText: 'Batal',
                background: '#1a1b3b',
                color: '#fff',
                backdrop: 'rgba(102,126,234,0.1)'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'toggle_user.php?id=' + id;
                }
            });
            return false;
        }

        // Reset password
        function resetPassword(id, nama) {
            Swal.fire({
                title: 'Reset Password',
                html: `Reset password untuk user <strong>"${nama}"</strong>?<br><br>Password akan direset menjadi <strong>password123</strong>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#667eea',
                cancelButtonColor: '#4a5568',
                confirmButtonText: 'Ya, Reset!',
                cancelButtonText: 'Batal',
                background: '#1a1b3b',
                color: '#fff',
                backdrop: 'rgba(102,126,234,0.1)'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'reset_password.php?id=' + id;
                }
            });
            return false;
        }

        // Loading overlay on navigation
        document.querySelectorAll('.nav-link, .btn-primary, .btn-icon:not(.delete)').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!this.classList.contains('active')) {
                    document.getElementById('loadingOverlay').classList.add('active');
                }
            });
        });

        // Auto-hide alerts
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                alert.style.transition = 'all 0.5s';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 500);
            }, 5000);
        });

        // Parallax effect
        document.addEventListener('mousemove', function(e) {
            const shapes = document.querySelectorAll('.bg-shape');
            const mouseX = e.clientX / window.innerWidth;
            const mouseY = e.clientY / window.innerHeight;
            
            shapes.forEach((shape, index) => {
                const speed = (index + 1) * 25;
                const x = (window.innerWidth - mouseX * speed) / 100;
                const y = (window.innerHeight - mouseY * speed) / 100;
                shape.style.transform = `translate(${x}px, ${y}px)`;
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + F untuk fokus search
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.querySelector('input[name="search"]').focus();
            }
            // / untuk fokus search
            if (e.key === '/' && !e.ctrlKey && document.activeElement.tagName !== 'INPUT') {
                e.preventDefault();
                document.querySelector('input[name="search"]').focus();
            }
        });

        // Tooltip animation
        document.querySelectorAll('[title]').forEach(el => {
            el.addEventListener('mouseenter', function() {
                const title = this.getAttribute('title');
                this.setAttribute('data-tooltip', title);
                this.removeAttribute('title');
            });
        });
    </script>
</body>
</html>