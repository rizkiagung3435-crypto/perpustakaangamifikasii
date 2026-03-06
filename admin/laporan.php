<?php
/**
 * Halaman Laporan - Admin Perpustakaan Digital Gamifikasi
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

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['type']) ? $_GET['type'] : 'peminjaman';

// Get report data based on type
$report_data = null;
$chart_labels = [];
$chart_data = [];

if ($report_type == 'peminjaman') {
    // Laporan peminjaman per hari
    $query = "SELECT DATE(tanggal_pinjam) as tgl, 
                     COUNT(*) as total_pinjam,
                     SUM(CASE WHEN status = 'kembali' THEN 1 ELSE 0 END) as total_kembali
              FROM peminjaman 
              WHERE tanggal_pinjam BETWEEN ? AND ?
              GROUP BY DATE(tanggal_pinjam)
              ORDER BY tgl DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $report_data = $stmt->get_result();
    
    // Data untuk chart
    $chart_query = "SELECT DATE(tanggal_pinjam) as tgl, COUNT(*) as total
                   FROM peminjaman 
                   WHERE tanggal_pinjam BETWEEN ? AND ?
                   GROUP BY DATE(tanggal_pinjam)
                   ORDER BY tgl ASC";
    $chart_stmt = $conn->prepare($chart_query);
    $chart_stmt->bind_param("ss", $start_date, $end_date);
    $chart_stmt->execute();
    $chart_result = $chart_stmt->get_result();
    
    while ($row = $chart_result->fetch_assoc()) {
        $chart_labels[] = date('d/m', strtotime($row['tgl']));
        $chart_data[] = $row['total'];
    }
    
} elseif ($report_type == 'buku') {
    // Laporan buku populer
    $query = "SELECT b.*, COUNT(p.id_pinjam) as total_dipinjam
              FROM buku b
              LEFT JOIN peminjaman p ON b.id_buku = p.id_buku
              WHERE p.tanggal_pinjam BETWEEN ? AND ? OR p.tanggal_pinjam IS NULL
              GROUP BY b.id_buku
              ORDER BY total_dipinjam DESC
              LIMIT 20";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $report_data = $stmt->get_result();
    
    // Data untuk chart
    $chart_query = "SELECT b.kategori, COUNT(p.id_pinjam) as total
                   FROM buku b
                   LEFT JOIN peminjaman p ON b.id_buku = p.id_buku
                   WHERE p.tanggal_pinjam BETWEEN ? AND ? OR p.tanggal_pinjam IS NULL
                   GROUP BY b.kategori
                   ORDER BY total DESC
                   LIMIT 10";
    $chart_stmt = $conn->prepare($chart_query);
    $chart_stmt->bind_param("ss", $start_date, $end_date);
    $chart_stmt->execute();
    $chart_result = $chart_stmt->get_result();
    
    while ($row = $chart_result->fetch_assoc()) {
        if ($row['kategori']) {
            $chart_labels[] = $row['kategori'];
            $chart_data[] = $row['total'];
        }
    }
    
} elseif ($report_type == 'user') {
    // Laporan user aktif
    $query = "SELECT u.*, 
                     COUNT(p.id_pinjam) as total_pinjam,
                     MAX(p.tanggal_pinjam) as terakhir_pinjam
              FROM users u
              LEFT JOIN peminjaman p ON u.id = p.id_user
              WHERE u.role = 'siswa'
              GROUP BY u.id
              ORDER BY total_pinjam DESC
              LIMIT 20";
    $report_data = $conn->query($query);
    
    // Data untuk chart
    $chart_query = "SELECT u.kelas, COUNT(DISTINCT u.id) as total_user,
                           COUNT(p.id_pinjam) as total_pinjam
                    FROM users u
                    LEFT JOIN peminjaman p ON u.id = p.id_user
                    WHERE u.role = 'siswa'
                    GROUP BY u.kelas
                    ORDER BY total_user DESC
                    LIMIT 10";
    $chart_result = $conn->query($chart_query);
    
    while ($row = $chart_result->fetch_assoc()) {
        if ($row['kelas']) {
            $chart_labels[] = $row['kelas'];
            $chart_data[] = $row['total_user'];
        }
    }
}

// Get summary statistics
$summary = [];
$summary['total_pinjam'] = $conn->query("SELECT COUNT(*) as total FROM peminjaman WHERE tanggal_pinjam BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'];
$summary['total_kembali'] = $conn->query("SELECT COUNT(*) as total FROM peminjaman WHERE tanggal_pinjam BETWEEN '$start_date' AND '$end_date' AND status = 'kembali'")->fetch_assoc()['total'];
$summary['total_buku'] = $conn->query("SELECT COUNT(*) as total FROM buku")->fetch_assoc()['total'];
$summary['total_user'] = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'siswa'")->fetch_assoc()['total'];

$page_title = "Laporan - Admin Perpustakaan";
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
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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

        .btn-export {
            background: linear-gradient(145deg, #48bb78, #38a169);
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
            box-shadow: 0 15px 25px -8px rgba(72, 187, 120, 0.4);
            letter-spacing: 0.3px;
        }

        .btn-export:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 30px -8px rgba(72, 187, 120, 0.6);
        }

        .btn-export i {
            font-size: 16px;
            transition: transform 0.3s;
        }

        .btn-export:hover i {
            transform: translateX(5px);
        }

        /* Filter Box */
        .filter-box {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 28px;
            padding: 28px;
            margin-bottom: 40px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .filter-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 24px;
        }

        .filter-title i {
            color: #667eea;
            font-size: 20px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 20px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            color: #a0aec0;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.3px;
            margin-left: 5px;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid rgba(255, 255, 255, 0.05);
            border-radius: 18px;
            font-size: 14px;
            background: rgba(0, 0, 0, 0.2);
            color: white;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: all 0.3s;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(0, 0, 0, 0.3);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .filter-group input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            opacity: 0.5;
        }

        .filter-btn {
            padding: 14px 32px;
            background: linear-gradient(145deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 18px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            height: fit-content;
            letter-spacing: 0.3px;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px -8px rgba(102, 126, 234, 0.5);
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

        /* Chart Card */
        .chart-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 28px;
            padding: 28px;
            margin-bottom: 40px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .chart-header h3 {
            color: white;
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-header h3 i {
            color: #667eea;
        }

        .chart-period {
            background: rgba(255, 255, 255, 0.03);
            padding: 8px 16px;
            border-radius: 40px;
            color: #a0aec0;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-container {
            position: relative;
            height: 350px;
            margin-top: 20px;
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
            padding: 8px 16px;
            border-radius: 40px;
            display: flex;
            align-items: center;
            gap: 8px;
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

        tr:hover td {
            background: rgba(102, 126, 234, 0.05);
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

            .filter-form {
                grid-template-columns: 1fr;
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

            .chart-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .table-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .table-header span {
                width: 100%;
                justify-content: center;
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
        <div class="loading-text">Memuat laporan...</div>
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
                <li><a href="data_user.php" class="nav-link"><i class="fas fa-users"></i>Users</a></li>
                <li><a href="laporan.php" class="nav-link active"><i class="fas fa-chart-bar"></i>Laporan</a></li>
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
                    <i class="fas fa-chart-bar"></i>
                    Laporan Perpustakaan
                </h1>
                <div class="page-badge">
                    <i class="fas fa-calendar"></i>
                    <?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?>
                </div>
            </div>
            <a href="export_laporan.php?type=<?php echo $report_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn-export">
                <i class="fas fa-file-excel"></i>
                Export Excel
            </a>
        </div>

        <!-- Filter Box -->
        <div class="filter-box" data-aos="fade-up" data-aos-delay="100">
            <div class="filter-title">
                <i class="fas fa-filter"></i>
                Filter Laporan
            </div>
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label>Jenis Laporan</label>
                    <select name="type">
                        <option value="peminjaman" <?php echo $report_type == 'peminjaman' ? 'selected' : ''; ?>>📊 Laporan Peminjaman</option>
                        <option value="buku" <?php echo $report_type == 'buku' ? 'selected' : ''; ?>>📚 Laporan Buku Populer</option>
                        <option value="user" <?php echo $report_type == 'user' ? 'selected' : ''; ?>>👥 Laporan User Aktif</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Tanggal Mulai</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                
                <div class="filter-group">
                    <label>Tanggal Selesai</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                
                <button type="submit" class="filter-btn">
                    <i class="fas fa-sync-alt"></i>
                    Tampilkan
                </button>
            </form>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid" data-aos="fade-up" data-aos-delay="150">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-book-open"></i></div>
                <div class="stat-info">
                    <h3><?php echo $summary['total_pinjam']; ?></h3>
                    <p><i class="fas fa-circle" style="color: #667eea;"></i> Total Peminjaman</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo $summary['total_kembali']; ?></h3>
                    <p><i class="fas fa-circle" style="color: #48bb78;"></i> Pengembalian</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-book"></i></div>
                <div class="stat-info">
                    <h3><?php echo $summary['total_buku']; ?></h3>
                    <p><i class="fas fa-circle" style="color: #ed8936;"></i> Total Buku</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3><?php echo $summary['total_user']; ?></h3>
                    <p><i class="fas fa-circle" style="color: #fbbf24;"></i> Total Siswa</p>
                </div>
            </div>
        </div>

        <!-- Chart -->
        <?php if (!empty($chart_labels) && !empty($chart_data)): ?>
        <div class="chart-card" data-aos="fade-up" data-aos-delay="200">
            <div class="chart-header">
                <h3>
                    <i class="fas fa-chart-line"></i>
                    Grafik <?php 
                        echo $report_type == 'peminjaman' ? 'Peminjaman' : 
                             ($report_type == 'buku' ? 'Kategori Buku' : 'User per Kelas'); 
                    ?>
                </h3>
                <div class="chart-period">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo date('d M', strtotime($start_date)); ?> - <?php echo date('d M', strtotime($end_date)); ?>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="reportChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Data Table -->
        <div class="table-card" data-aos="fade-up" data-aos-delay="250">
            <div class="table-header">
                <h2>
                    <i class="fas fa-list"></i>
                    Detail Laporan
                </h2>
                <span>
                    <i class="fas fa-clock"></i>
                    Periode: <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?>
                </span>
            </div>
            
            <?php if ($report_data && $report_data->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <?php if ($report_type == 'peminjaman'): ?>
                                    <th>Tanggal</th>
                                    <th>Total Pinjam</th>
                                    <th>Total Kembali</th>
                                    <th>Sisa Dipinjam</th>
                                <?php elseif ($report_type == 'buku'): ?>
                                    <th>Judul</th>
                                    <th>Penulis</th>
                                    <th>Kategori</th>
                                    <th>Stok</th>
                                    <th>Total Dipinjam</th>
                                <?php elseif ($report_type == 'user'): ?>
                                    <th>Nama</th>
                                    <th>Kelas</th>
                                    <th>Jurusan</th>
                                    <th>Total Pinjam</th>
                                    <th>Terakhir Pinjam</th>
                                    <th>XP</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $report_data->fetch_assoc()): ?>
                            <tr>
                                <?php if ($report_type == 'peminjaman'): ?>
                                    <td><?php echo date('d/m/Y', strtotime($row['tgl'])); ?></td>
                                    <td><strong><?php echo $row['total_pinjam']; ?></strong></td>
                                    <td><?php echo $row['total_kembali']; ?></td>
                                    <td><?php echo $row['total_pinjam'] - $row['total_kembali']; ?></td>
                                <?php elseif ($report_type == 'buku'): ?>
                                    <td><strong><?php echo htmlspecialchars($row['judul']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['penulis']); ?></td>
                                    <td>
                                        <span style="background: rgba(102, 126, 234, 0.15); color: #667eea; padding: 4px 10px; border-radius: 20px; font-size: 11px;">
                                            <?php echo htmlspecialchars($row['kategori']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $row['stok']; ?></td>
                                    <td><strong style="color: #667eea;"><?php echo $row['total_dipinjam']; ?>x</strong></td>
                                <?php elseif ($report_type == 'user'): ?>
                                    <td><strong><?php echo htmlspecialchars($row['nama']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['kelas']); ?></td>
                                    <td><?php echo htmlspecialchars($row['jurusan']); ?></td>
                                    <td><?php echo $row['total_pinjam']; ?></td>
                                    <td><?php echo $row['terakhir_pinjam'] ? date('d/m/Y', strtotime($row['terakhir_pinjam'])) : '-'; ?></td>
                                    <td><strong style="color: #fbbf24;"><?php echo number_format($row['xp']); ?> XP</strong></td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3>Tidak Ada Data</h3>
                    <p>Tidak ada data laporan untuk periode yang dipilih</p>
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

        // Initialize Chart
        const ctx = document.getElementById('reportChart').getContext('2d');
        new Chart(ctx, {
            type: '<?php echo $report_type == "peminjaman" ? "line" : ($report_type == "buku" ? "doughnut" : "bar"); ?>',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: '<?php 
                        echo $report_type == "peminjaman" ? "Jumlah Peminjaman" : 
                             ($report_type == "buku" ? "Jumlah Pinjam" : "Jumlah User"); 
                    ?>',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)', 'rgba(118, 75, 162, 0.8)', 'rgba(72, 187, 120, 0.8)',
                        'rgba(251, 191, 36, 0.8)', 'rgba(245, 101, 101, 0.8)', 'rgba(159, 122, 234, 0.8)',
                        'rgba(237, 137, 54, 0.8)', 'rgba(66, 153, 225, 0.8)', 'rgba(56, 161, 105, 0.8)',
                        'rgba(236, 201, 75, 0.8)'
                    ],
                    borderColor: 'rgba(255, 255, 255, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: <?php echo $report_type != 'peminjaman' ? 'true' : 'false'; ?>,
                        labels: {
                            color: '#a0aec0',
                            font: {
                                family: 'Plus Jakarta Sans',
                                size: 12
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        grid: {
                            color: 'rgba(255,255,255,0.05)'
                        },
                        ticks: {
                            color: '#a0aec0',
                            font: {
                                family: 'Plus Jakarta Sans'
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#a0aec0',
                            font: {
                                family: 'Plus Jakarta Sans'
                            }
                        }
                    }
                }
            }
        });

        // Loading overlay on navigation
        document.querySelectorAll('.nav-link, .btn-export, .filter-btn').forEach(link => {
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
    </script>
</body>
</html>