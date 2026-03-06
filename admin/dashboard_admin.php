<?php
/**
 * Dashboard Admin - Perpustakaan Digital Gamifikasi
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

// Get admin data
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get admin details from admin_details table if exists
$admin_details = null;
$check_admin_table = $conn->query("SHOW TABLES LIKE 'admin_details'");
if ($check_admin_table->num_rows > 0) {
    $query_details = "SELECT * FROM admin_details WHERE user_id = ?";
    $stmt_details = $conn->prepare($query_details);
    $stmt_details->bind_param("i", $user_id);
    $stmt_details->execute();
    $admin_details = $stmt_details->get_result()->fetch_assoc();
}

// Get statistics
$total_users = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'siswa'")->fetch_assoc();
$total_books = $conn->query("SELECT COUNT(*) as total FROM buku")->fetch_assoc();
$total_borrowed = $conn->query("SELECT COUNT(*) as total FROM peminjaman WHERE status = 'dipinjam'")->fetch_assoc();
$total_returned = $conn->query("SELECT COUNT(*) as total FROM peminjaman WHERE status = 'kembali'")->fetch_assoc();

// Get today's statistics
$today_borrowed = $conn->query("SELECT COUNT(*) as total FROM peminjaman WHERE DATE(tanggal_pinjam) = CURDATE()")->fetch_assoc();
$today_returned = $conn->query("SELECT COUNT(*) as total FROM peminjaman WHERE DATE(tanggal_kembali) = CURDATE() AND status = 'kembali'")->fetch_assoc();

// Get recent activities
$recent_activities = $conn->query("
    SELECT p.*, u.nama as nama_siswa, u.kelas, u.jurusan, b.judul as judul_buku 
    FROM peminjaman p
    JOIN users u ON p.id_user = u.id
    JOIN buku b ON p.id_buku = b.id_buku
    ORDER BY p.created_at DESC
    LIMIT 10
");

// Get top readers
$top_readers = $conn->query("
    SELECT u.id, u.nama, u.kelas, u.jurusan, u.xp, u.avatar,
           COUNT(p.id_pinjam) as total_pinjam,
           COUNT(CASE WHEN p.status = 'kembali' THEN 1 END) as total_kembali
    FROM users u
    LEFT JOIN peminjaman p ON u.id = p.id_user
    WHERE u.role = 'siswa'
    GROUP BY u.id
    ORDER BY u.xp DESC
    LIMIT 5
");

// Get popular books
$popular_books = $conn->query("
    SELECT b.*, COUNT(p.id_pinjam) as total_dipinjam
    FROM buku b
    LEFT JOIN peminjaman p ON b.id_buku = p.id_buku
    GROUP BY b.id_buku
    ORDER BY total_dipinjam DESC
    LIMIT 5
");

// Get low stock books
$low_stock = $conn->query("SELECT COUNT(*) as total FROM buku WHERE stok <= 3 AND stok > 0")->fetch_assoc();
$out_of_stock = $conn->query("SELECT COUNT(*) as total FROM buku WHERE stok = 0")->fetch_assoc();

// Page title
$page_title = "Admin Dashboard - Perpustakaan Digital";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
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

        /* Welcome Section */
        .welcome-section {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 28px;
            padding: 30px;
            margin-bottom: 40px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        .welcome-content {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .welcome-text h1 {
            font-size: 28px;
            font-weight: 800;
            color: white;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .welcome-text p {
            color: #a0aec0;
            font-size: 16px;
        }

        .date-badge {
            background: rgba(255, 255, 255, 0.03);
            padding: 12px 24px;
            border-radius: 50px;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            font-weight: 500;
        }

        .date-badge i {
            color: #667eea;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Stats Grid */
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
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.05);
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
            width: 60px;
            height: 60px;
            background: linear-gradient(145deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #667eea;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .stat-info {
            flex: 1;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: white;
            line-height: 1.2;
            margin-bottom: 4px;
            letter-spacing: -0.5px;
        }

        .stat-label {
            font-size: 14px;
            color: #a0aec0;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .stat-trend {
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 40px;
            background: rgba(72, 187, 120, 0.15);
            color: #48bb78;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid rgba(72, 187, 120, 0.3);
            font-weight: 600;
        }

        .stat-trend.warning {
            background: rgba(237, 137, 54, 0.15);
            color: #ed8936;
            border-color: rgba(237, 137, 54, 0.3);
        }

        .stat-trend i {
            font-size: 10px;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 40px;
        }

        .action-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 28px;
            padding: 28px 20px;
            text-align: center;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.05);
            text-decoration: none;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
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

        .action-card:hover::before {
            transform: translateX(100%);
        }

        .action-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(102, 126, 234, 0.3);
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.5);
        }

        .action-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(145deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #667eea;
            margin: 0 auto 16px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s;
        }

        .action-card:hover .action-icon {
            background: linear-gradient(145deg, #667eea, #764ba2);
            color: white;
            transform: scale(1.1);
        }

        .action-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: -0.3px;
        }

        .action-desc {
            font-size: 13px;
            color: #a0aec0;
            line-height: 1.5;
        }

        /* Tables */
        .table-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 28px;
            padding: 28px;
            margin-bottom: 40px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .table-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 700;
            color: white;
        }

        .table-title i {
            color: #667eea;
            font-size: 22px;
        }

        .view-all {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 40px;
            border: 1px solid rgba(102, 126, 234, 0.2);
            transition: all 0.3s;
        }

        .view-all:hover {
            background: rgba(102, 126, 234, 0.2);
            transform: translateX(5px);
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

        .status-badge {
            padding: 6px 14px;
            border-radius: 40px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            letter-spacing: 0.3px;
        }

        .status-badge.dipinjam {
            background: rgba(237, 137, 54, 0.15);
            color: #ed8936;
            border: 1px solid rgba(237, 137, 54, 0.3);
        }

        .status-badge.kembali {
            background: rgba(72, 187, 120, 0.15);
            color: #48bb78;
            border: 1px solid rgba(72, 187, 120, 0.3);
        }

        .rank-badge {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }

        .rank-1 {
            background: linear-gradient(145deg, #fbbf24, #f59e0b);
            color: #0f0c1f;
        }

        .rank-2 {
            background: linear-gradient(145deg, #94a3b8, #64748b);
            color: white;
        }

        .rank-3 {
            background: linear-gradient(145deg, #cd7f32, #b45309);
            color: white;
        }

        .rank-default {
            background: rgba(255, 255, 255, 0.1);
            color: #a0aec0;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-icon {
            font-size: 60px;
            color: rgba(102, 126, 234, 0.3);
            margin-bottom: 16px;
        }

        .empty-state p {
            color: #a0aec0;
            font-size: 14px;
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
            .stats-grid,
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
            }

            .welcome-content {
                flex-direction: column;
                text-align: center;
            }

            .table-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 20px;
            }

            .stats-grid,
            .quick-actions {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 20px;
            }

            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }

            .stat-value {
                font-size: 28px;
            }

            th, td {
                padding: 12px;
                font-size: 13px;
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
                <li><a href="dashboard_admin.php" class="nav-link active"><i class="fas fa-home"></i>Dashboard</a></li>
                <li><a href="tambah_buku.php" class="nav-link"><i class="fas fa-plus-circle"></i>Tambah</a></li>
                <li><a href="data_buku.php" class="nav-link"><i class="fas fa-book"></i>Buku</a></li>
                <li><a href="data_user.php" class="nav-link"><i class="fas fa-users"></i>Users</a></li>
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
        <!-- Welcome Section -->
        <div class="welcome-section" data-aos="fade-up">
            <div class="welcome-content">
                <div class="welcome-text">
                    <h1>Selamat datang, <?php echo htmlspecialchars($user['nama']); ?>!</h1>
                    <p>Kelola perpustakaan digital dengan mudah dan efisien</p>
                </div>
                <div class="date-badge">
                    <i class="fas fa-calendar"></i>
                    <?php echo date('l, d F Y'); ?>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo number_format($total_users['total']); ?></div>
                    <div class="stat-label">Total Siswa</div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i> Aktif
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-book"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo number_format($total_books['total']); ?></div>
                    <div class="stat-label">Total Buku</div>
                    <div class="stat-trend">
                        <i class="fas fa-plus"></i> Koleksi
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-book-open"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $total_borrowed['total']; ?></div>
                    <div class="stat-label">Sedang Dipinjam</div>
                    <div class="stat-trend warning">
                        <i class="fas fa-clock"></i> <?php echo $today_borrowed['total']; ?> hari ini
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $total_returned['total']; ?></div>
                    <div class="stat-label">Dikembalikan</div>
                    <div class="stat-trend">
                        <i class="fas fa-check"></i> <?php echo $today_returned['total']; ?> hari ini
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions" data-aos="fade-up" data-aos-delay="150">
            <a href="tambah_buku.php" class="action-card">
                <div class="action-icon"><i class="fas fa-plus"></i></div>
                <h3 class="action-title">Tambah Buku</h3>
                <p class="action-desc">Tambahkan koleksi buku baru</p>
            </a>
            
            <a href="data_buku.php" class="action-card">
                <div class="action-icon"><i class="fas fa-edit"></i></div>
                <h3 class="action-title">Kelola Buku</h3>
                <p class="action-desc">Edit atau hapus data buku</p>
            </a>
            
            <a href="data_user.php" class="action-card">
                <div class="action-icon"><i class="fas fa-user-plus"></i></div>
                <h3 class="action-title">Kelola User</h3>
                <p class="action-desc">Lihat dan kelola data siswa</p>
            </a>
            
            <a href="laporan.php" class="action-card">
                <div class="action-icon"><i class="fas fa-file-pdf"></i></div>
                <h3 class="action-title">Cetak Laporan</h3>
                <p class="action-desc">Generate laporan peminjaman</p>
            </a>
        </div>

        <!-- Recent Activities -->
        <div class="table-card" data-aos="fade-up" data-aos-delay="200">
            <div class="table-header">
                <h3 class="table-title">
                    <i class="fas fa-history"></i>
                    Aktivitas Terbaru
                </h3>
                <a href="laporan.php" class="view-all">
                    Lihat Semua <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Siswa</th>
                            <th>Kelas</th>
                            <th>Buku</th>
                            <th>Tanggal Pinjam</th>
                            <th>Tanggal Kembali</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_activities && $recent_activities->num_rows > 0): ?>
                            <?php while($activity = $recent_activities->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($activity['nama_siswa']); ?></strong></td>
                                <td><?php echo htmlspecialchars($activity['kelas']); ?></td>
                                <td><?php echo htmlspecialchars($activity['judul_buku']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($activity['tanggal_pinjam'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($activity['tanggal_kembali'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $activity['status']; ?>">
                                        <i class="fas fa-<?php echo $activity['status'] == 'dipinjam' ? 'book-open' : 'check-circle'; ?>"></i>
                                        <?php echo $activity['status'] == 'dipinjam' ? 'Dipinjam' : 'Dikembalikan'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <div class="empty-icon">
                                        <i class="fas fa-inbox"></i>
                                    </div>
                                    <p>Belum ada aktivitas peminjaman</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Readers -->
        <div class="table-card" data-aos="fade-up" data-aos-delay="250">
            <div class="table-header">
                <h3 class="table-title">
                    <i class="fas fa-trophy"></i>
                    Top 5 Pembaca
                </h3>
                <a href="data_user.php" class="view-all">
                    Lihat Semua <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Peringkat</th>
                            <th>Nama</th>
                            <th>Kelas</th>
                            <th>Total Baca</th>
                            <th>XP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($top_readers && $top_readers->num_rows > 0): ?>
                            <?php 
                            $rank = 1;
                            while($reader = $top_readers->fetch_assoc()): 
                            ?>
                            <tr>
                                <td>
                                    <div class="rank-badge <?php 
                                        echo $rank == 1 ? 'rank-1' : ($rank == 2 ? 'rank-2' : ($rank == 3 ? 'rank-3' : 'rank-default')); 
                                    ?>">
                                        <?php echo $rank; ?>
                                    </div>
                                </td>
                                <td><strong><?php echo htmlspecialchars($reader['nama']); ?></strong></td>
                                <td><?php echo htmlspecialchars($reader['kelas']); ?></td>
                                <td><?php echo $reader['total_kembali']; ?> buku</td>
                                <td><strong style="color: #fbbf24;"><?php echo number_format($reader['xp']); ?> XP</strong></td>
                            </tr>
                            <?php 
                            $rank++;
                            endwhile; 
                            ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <div class="empty-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <p>Belum ada data pembaca</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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

        // Loading overlay on navigation
        document.querySelectorAll('.nav-link, .action-card, .view-all').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!this.classList.contains('active')) {
                    document.getElementById('loadingOverlay').classList.add('active');
                }
            });
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

        // Refresh data every 30 seconds (optional - bisa dihapus jika tidak perlu)
        // setInterval(() => {
        //     location.reload();
        // }, 30000);
    </script>
</body>
</html>