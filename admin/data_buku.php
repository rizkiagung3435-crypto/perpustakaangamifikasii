<?php
/**
 * Halaman Data Buku - Admin Perpustakaan Digital Gamifikasi
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

// Get success message from session
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Get all books with optional search
$search = isset($_GET['search']) ? $database->escapeString($_GET['search']) : '';

$query = "SELECT * FROM buku";
if ($search) {
    $query .= " WHERE judul LIKE '%$search%' OR penulis LIKE '%$search%' OR isbn LIKE '%$search%'";
}
$query .= " ORDER BY created_at DESC";
$books = $conn->query($query);

// Get statistics for header
$total_books = $conn->query("SELECT COUNT(*) as total FROM buku")->fetch_assoc()['total'];
$available_books = $conn->query("SELECT COUNT(*) as total FROM buku WHERE stok > 0")->fetch_assoc()['total'];
$low_stock = $conn->query("SELECT COUNT(*) as total FROM buku WHERE stok <= 3 AND stok > 0")->fetch_assoc()['total'];
$out_of_stock = $conn->query("SELECT COUNT(*) as total FROM buku WHERE stok = 0")->fetch_assoc()['total'];

$page_title = "Data Buku - Admin Perpustakaan";
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
            padding: 14px 32px;
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
            color: #48bb78;
            font-size: 12px;
        }

        /* Search Box */
        .search-box {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .search-form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 300px;
            position: relative;
        }

        .search-input i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 16px;
            z-index: 1;
        }

        .search-input input {
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

        .search-input input:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(0, 0, 0, 0.3);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .search-input input::placeholder {
            color: #4a5568;
            font-weight: 400;
        }

        .search-btn {
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

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px -8px rgba(102, 126, 234, 0.5);
        }

        .search-btn.reset {
            background: rgba(255, 255, 255, 0.05);
            color: #e2e8f0;
        }

        .search-btn.reset:hover {
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

        .book-cover {
            width: 45px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .book-cover-placeholder {
            width: 45px;
            height: 60px;
            background: linear-gradient(145deg, #667eea, #764ba2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .stock-badge {
            padding: 6px 14px;
            border-radius: 40px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
            letter-spacing: 0.3px;
        }

        .stock-high {
            background: rgba(72, 187, 120, 0.15);
            color: #48bb78;
            border: 1px solid rgba(72, 187, 120, 0.3);
        }

        .stock-low {
            background: rgba(237, 137, 54, 0.15);
            color: #ed8936;
            border: 1px solid rgba(237, 137, 54, 0.3);
        }

        .stock-out {
            background: rgba(245, 101, 101, 0.15);
            color: #f56565;
            border: 1px solid rgba(245, 101, 101, 0.3);
        }

        .action-buttons {
            display: flex;
            gap: 8px;
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
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .search-form {
                flex-direction: column;
            }

            .search-input {
                width: 100%;
            }

            .search-btn {
                width: 100%;
                justify-content: center;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-icon {
                width: 100%;
            }

            td, th {
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
                <li><a href="data_buku.php" class="nav-link active"><i class="fas fa-book"></i>Buku</a></li>
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
        <!-- Page Header -->
        <div class="page-header" data-aos="fade-up">
            <div class="page-title">
                <h1>
                    <i class="fas fa-book"></i>
                    Data Buku
                </h1>
                <div class="page-badge">
                    <i class="fas fa-database"></i>
                    Total <?php echo $total_books; ?> Buku
                </div>
            </div>
            <a href="tambah_buku.php" class="btn-primary">
                <i class="fas fa-plus"></i>
                Tambah Buku Baru
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-book"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_books; ?></h3>
                    <p><i class="fas fa-circle"></i> Total Buku</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo $available_books; ?></h3>
                    <p><i class="fas fa-circle" style="color: #48bb78;"></i> Tersedia</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-info">
                    <h3><?php echo $low_stock; ?></h3>
                    <p><i class="fas fa-circle" style="color: #ed8936;"></i> Stok Menipis</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo $out_of_stock; ?></h3>
                    <p><i class="fas fa-circle" style="color: #f56565;"></i> Stok Habis</p>
                </div>
            </div>
        </div>

        <!-- Search Box -->
        <div class="search-box" data-aos="fade-up" data-aos-delay="150">
            <form method="GET" action="" class="search-form">
                <div class="search-input">
                    <i class="fas fa-search"></i>
                    <input type="text" 
                           name="search" 
                           placeholder="Cari berdasarkan judul, penulis, atau ISBN..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i>
                    Cari
                </button>
                <?php if ($search): ?>
                    <a href="data_buku.php" class="search-btn reset">
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
                    Daftar Buku
                </h2>
                <span>
                    <i class="fas fa-sort"></i>
                    Terbaru
                </span>
            </div>

            <?php if ($books && $books->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Cover</th>
                                <th>Judul</th>
                                <th>Penulis</th>
                                <th>Kategori</th>
                                <th>Stok</th>
                                <th>Tahun</th>
                                <th>ISBN</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($book = $books->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php if ($book['cover'] && file_exists("../assets/img/covers/" . $book['cover'])): ?>
                                        <img src="../assets/img/covers/<?php echo $book['cover']; ?>" 
                                             alt="Cover" 
                                             class="book-cover">
                                    <?php else: ?>
                                        <div class="book-cover-placeholder">
                                            <i class="fas fa-book"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($book['judul']); ?></strong></td>
                                <td><?php echo htmlspecialchars($book['penulis']); ?></td>
                                <td>
                                    <span style="background: rgba(102, 126, 234, 0.15); color: #667eea; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;">
                                        <?php echo htmlspecialchars($book['kategori']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="stock-badge <?php 
                                        echo $book['stok'] > 5 ? 'stock-high' : ($book['stok'] > 0 ? 'stock-low' : 'stock-out');
                                    ?>">
                                        <?php echo $book['stok']; ?> buku
                                    </span>
                                </td>
                                <td><?php echo $book['tahun_terbit'] ?: '-'; ?></td>
                                <td><?php echo $book['isbn'] ?: '-'; ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="detail_buku.php?id=<?php echo $book['id_buku']; ?>" class="btn-icon view" title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_buku.php?id=<?php echo $book['id_buku']; ?>" class="btn-icon edit" title="Edit Buku">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" class="btn-icon delete" title="Hapus Buku" onclick="return confirmDelete(<?php echo $book['id_buku']; ?>, '<?php echo htmlspecialchars($book['judul']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </a>
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
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h3>Belum Ada Data Buku</h3>
                    <p><?php echo $search ? 'Tidak ditemukan buku dengan kata kunci "' . htmlspecialchars($search) . '"' : 'Mulai dengan menambahkan buku pertama Anda'; ?></p>
                    <a href="tambah_buku.php" class="btn-primary">
                        <i class="fas fa-plus"></i>
                        Tambah Buku
                    </a>
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

        // Confirm delete with SweetAlert2
        function confirmDelete(id, judul) {
            Swal.fire({
                title: 'Hapus Buku?',
                html: `Apakah Anda yakin ingin menghapus buku <strong>"${judul}"</strong>?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f56565',
                cancelButtonColor: '#4a5568',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                background: '#1a1b3b',
                color: '#fff',
                backdrop: 'rgba(102,126,234,0.1)'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'hapus_buku.php?id=' + id;
                }
            });
            return false;
        }

        // Loading overlay on navigation
        document.querySelectorAll('.nav-link, .btn-primary, .btn-icon:not(.delete)').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!this.classList.contains('active') && !this.classList.contains('delete')) {
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
            // Ctrl + N untuk tambah buku
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'tambah_buku.php';
            }
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