<?php
/**
 * Halaman Leaderboard - Perpustakaan Digital Gamifikasi
 * SMK Mardi Yuana Cikembar
 */

require_once '../config/koneksi.php';
require_once '../config/auth.php';

$database = new Database();
$conn = $database->getConnection();

// Get leaderboard data based on XP
$query_xp = "SELECT id, nama, kelas, jurusan, xp,
             (SELECT COUNT(*) + 1 FROM users u2 WHERE u2.role = 'siswa' AND u2.xp > u1.xp) as ranking
             FROM users u1
             WHERE role = 'siswa' 
             ORDER BY xp DESC 
             LIMIT 10";
$xp_leaderboard = $conn->query($query_xp);

// Get leaderboard based on books read
$query_books = "SELECT u.id, u.nama, u.kelas, u.jurusan, 
                COUNT(p.id_pinjam) as total_buku,
                (SELECT COUNT(*) + 1 FROM 
                    (SELECT u2.id, COUNT(p2.id_pinjam) as total 
                     FROM users u2 
                     LEFT JOIN peminjaman p2 ON u2.id = p2.id_user AND p2.status = 'kembali'
                     WHERE u2.role = 'siswa' 
                     GROUP BY u2.id
                    ) t2 
                 WHERE t2.total > COUNT(p.id_pinjam)
                ) as ranking
                FROM users u
                LEFT JOIN peminjaman p ON u.id = p.id_user AND p.status = 'kembali'
                WHERE u.role = 'siswa'
                GROUP BY u.id
                ORDER BY total_buku DESC
                LIMIT 10";
$books_leaderboard = $conn->query($query_books);

// Get current user rank
$user_id = $_SESSION['user_id'];
$query_user_rank = "SELECT COUNT(*) + 1 as rank 
                    FROM users 
                    WHERE role = 'siswa' AND xp > (SELECT xp FROM users WHERE id = ?)";
$stmt = $conn->prepare($query_user_rank);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_user_rank = $stmt->get_result();
$user_rank = $result_user_rank->fetch_assoc();

// Get total statistics
$query_stats = "SELECT 
                COUNT(DISTINCT id) as total_pembaca,
                COALESCE(AVG(xp), 0) as rata_rata_xp,
                COALESCE(MAX(xp), 0) as xp_tertinggi
                FROM users WHERE role = 'siswa'";
$stats_result = $conn->query($query_stats);
$stats = $stats_result->fetch_assoc();

$query_badges = "SELECT COUNT(*) as total_badges FROM user_achievement";
$badges_result = $conn->query($query_badges);
$badges = $badges_result->fetch_assoc();

// Page title
$page_title = "Leaderboard - Perpustakaan Digital";
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        .bg-shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .bg-shape:nth-child(1) {
            width: 400px;
            height: 400px;
            top: -200px;
            right: -200px;
            background: linear-gradient(135deg, #ff6b6b, #feca57);
        }

        .bg-shape:nth-child(2) {
            width: 300px;
            height: 300px;
            bottom: -150px;
            left: -150px;
            background: linear-gradient(135deg, #48dbfb, #1dd1a1);
            animation-delay: 2s;
        }

        .bg-shape:nth-child(3) {
            width: 200px;
            height: 200px;
            top: 30%;
            right: 10%;
            background: linear-gradient(135deg, #f368e0, #ff9f43);
            animation-delay: 4s;
        }

        .bg-shape:nth-child(4) {
            width: 250px;
            height: 250px;
            bottom: 20%;
            right: 15%;
            background: linear-gradient(135deg, #54a0ff, #5f27cd);
            animation-delay: 1s;
        }

        .bg-shape:nth-child(5) {
            width: 150px;
            height: 150px;
            top: 60%;
            left: 10%;
            background: linear-gradient(135deg, #00d2d3, #01a3a4);
            animation-delay: 3s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(5deg); }
        }

        /* Floating Books */
        .floating-book {
            position: absolute;
            font-size: 3rem;
            opacity: 0.1;
            animation: floatBook 15s linear infinite;
        }

        .floating-book:nth-child(6) { top: 15%; left: 5%; animation-delay: 0s; }
        .floating-book:nth-child(7) { top: 75%; left: 85%; animation-delay: 3s; }
        .floating-book:nth-child(8) { top: 45%; left: 92%; animation-delay: 6s; }
        .floating-book:nth-child(9) { top: 85%; left: 15%; animation-delay: 9s; }
        .floating-book:nth-child(10) { top: 25%; left: 75%; animation-delay: 12s; }

        @keyframes floatBook {
            0% { transform: translateY(0) rotate(0deg); opacity: 0.1; }
            50% { transform: translateY(-100px) rotate(10deg); opacity: 0.2; }
            100% { transform: translateY(0) rotate(0deg); opacity: 0.1; }
        }

        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo a {
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            animation: pulse 2s infinite;
        }

        .logo-text {
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav-link {
            color: #666;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 14px;
        }

        .nav-link:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .nav-link.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .theme-toggle-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            padding: 10px;
            border-radius: 50%;
            transition: all 0.3s ease;
            color: #666;
        }

        .theme-toggle-btn:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: rotate(180deg);
        }

        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
            position: relative;
            z-index: 1;
        }

        /* Glass Card */
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 50px rgba(102, 126, 234, 0.2);
        }

        /* Header Section */
        .header-section {
            text-align: center;
            margin-bottom: 40px;
            animation: fadeInDown 0.8s ease;
        }

        .header-section h1 {
            font-size: 48px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .header-section h1 i {
            font-size: 48px;
            color: #fbbf24;
            -webkit-text-fill-color: initial;
        }

        .header-section p {
            color: #666;
            font-size: 18px;
        }

        /* User Rank Card */
        .user-rank-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 40px;
            border-radius: 30px;
            margin-bottom: 40px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.3);
            animation: pulse 2s infinite;
            position: relative;
            overflow: hidden;
        }

        .user-rank-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        .user-rank-card h3 {
            font-size: 24px;
            font-weight: 500;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }

        .user-rank-card .rank-number {
            font-size: 80px;
            font-weight: 700;
            margin: 15px 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
            position: relative;
            z-index: 1;
        }

        .user-rank-card p {
            font-size: 16px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        /* Leaderboard Grid */
        .leaderboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 50px;
        }

        /* Leaderboard Cards */
        .leaderboard-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .leaderboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 50px rgba(102, 126, 234, 0.2);
        }

        .leaderboard-title {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(102, 126, 234, 0.2);
        }

        .leaderboard-title span {
            font-size: 32px;
        }

        .leaderboard-title h2 {
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }

        /* Leaderboard List */
        .leaderboard-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .leaderboard-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 15px;
            transition: all 0.3s ease;
            border: 1px solid transparent;
            animation: slideIn 0.5s ease forwards;
            opacity: 0;
        }

        .leaderboard-item:hover {
            transform: translateX(10px) scale(1.02);
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: transparent;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.2);
        }

        .leaderboard-item:hover .user-name,
        .leaderboard-item:hover .user-class,
        .leaderboard-item:hover .user-score {
            color: white;
        }

        .leaderboard-item.current-user {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: 2px solid white;
            box-shadow: 0 0 30px rgba(102, 126, 234, 0.5);
        }

        .leaderboard-item.current-user .user-name,
        .leaderboard-item.current-user .user-class,
        .leaderboard-item.current-user .user-score {
            color: white;
        }

        /* Rank Badge */
        .rank-badge {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            margin-right: 20px;
            background: white;
            border-radius: 50%;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .leaderboard-item:hover .rank-badge {
            transform: scale(1.1);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .rank-1 {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            animation: glow 2s infinite;
        }

        .rank-2 {
            background: linear-gradient(135deg, #94a3b8, #64748b);
            color: white;
        }

        .rank-3 {
            background: linear-gradient(135deg, #d97706, #b45309);
            color: white;
        }

        /* User Info */
        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            font-size: 16px;
            color: #333;
            margin-bottom: 4px;
        }

        .user-class {
            font-size: 13px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .user-class i {
            font-size: 12px;
        }

        /* User Score */
        .user-score {
            font-size: 18px;
            font-weight: 700;
            background: rgba(255, 255, 255, 0.9);
            padding: 8px 20px;
            border-radius: 30px;
            color: #667eea;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .leaderboard-item:hover .user-score {
            background: white;
            transform: scale(1.05);
        }

        .leaderboard-item.current-user .user-score {
            background: white;
            color: #667eea;
        }

        /* Stats Section */
        .stats-section {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-top: 50px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 25px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.2);
        }

        .stat-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 42px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 5px;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 16px;
            color: #666;
            font-weight: 500;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
            animation: bounce 2s infinite;
        }

        .empty-state p {
            color: #666;
            font-size: 16px;
        }

        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.02);
            }
        }

        @keyframes glow {
            0%, 100% {
                box-shadow: 0 0 20px #fbbf24;
            }
            50% {
                box-shadow: 0 0 40px #fbbf24;
            }
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-20px);
            }
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Animation Delays */
        .leaderboard-item:nth-child(1) { animation-delay: 0.1s; }
        .leaderboard-item:nth-child(2) { animation-delay: 0.2s; }
        .leaderboard-item:nth-child(3) { animation-delay: 0.3s; }
        .leaderboard-item:nth-child(4) { animation-delay: 0.4s; }
        .leaderboard-item:nth-child(5) { animation-delay: 0.5s; }
        .leaderboard-item:nth-child(6) { animation-delay: 0.6s; }
        .leaderboard-item:nth-child(7) { animation-delay: 0.7s; }
        .leaderboard-item:nth-child(8) { animation-delay: 0.8s; }
        .leaderboard-item:nth-child(9) { animation-delay: 0.9s; }
        .leaderboard-item:nth-child(10) { animation-delay: 1s; }

        /* Loading Animation */
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .loading.active {
            display: flex;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Dark Mode */
        body.dark-mode {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
        }

        body.dark-mode .glass-card,
        body.dark-mode .leaderboard-card,
        body.dark-mode .stat-card,
        body.dark-mode .navbar {
            background: rgba(0, 0, 0, 0.8);
            border-color: rgba(255, 255, 255, 0.1);
        }

        body.dark-mode .leaderboard-title h2,
        body.dark-mode .user-name,
        body.dark-mode .stat-label,
        body.dark-mode .header-section p {
            color: #e0e0e0;
        }

        body.dark-mode .user-class {
            color: #a0a0a0;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .leaderboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-section {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .header-section h1 {
                font-size: 36px;
            }
        }

        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-links {
                justify-content: center;
            }
            
            .main-container {
                padding: 15px;
            }
            
            .user-rank-card .rank-number {
                font-size: 60px;
            }
            
            .stats-section {
                grid-template-columns: 1fr;
            }
            
            .leaderboard-item {
                flex-wrap: wrap;
            }
            
            .user-score {
                margin-top: 10px;
                width: 100%;
                text-align: center;
            }
            
            .header-section h1 {
                font-size: 28px;
                flex-direction: column;
                gap: 10px;
            }
        }

        @media (max-width: 480px) {
            .rank-badge {
                width: 40px;
                height: 40px;
                font-size: 16px;
                margin-right: 10px;
            }
            
            .user-name {
                font-size: 14px;
            }
            
            .user-class {
                font-size: 11px;
            }
            
            .user-score {
                font-size: 14px;
                padding: 5px 12px;
            }
            
            .stat-value {
                font-size: 32px;
            }
        }

        /* Floating Animation */
        .floating {
            animation: floating 3s ease infinite;
        }

        @keyframes floating {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* Fade In Animation */
        .fade-in {
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="animated-bg">
        <div class="bg-shape"></div>
        <div class="bg-shape"></div>
        <div class="bg-shape"></div>
        <div class="bg-shape"></div>
        <div class="bg-shape"></div>
        <div class="floating-book">📚</div>
        <div class="floating-book">📖</div>
        <div class="floating-book">📕</div>
        <div class="floating-book">📗</div>
        <div class="floating-book">📘</div>
    </div>

    <!-- Loading Animation -->
    <div class="loading" id="loading">
        <div class="loading-spinner"></div>
    </div>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="../dashboard.php">
                    <div class="logo-icon">📚</div>
                    <span class="logo-text">Perpustakaan Digital</span>
                </a>
            </div>
            
            <ul class="nav-links">
                <li class="nav-item">
                    <a href="../dashboard.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="katalog.php" class="nav-link">
                        <i class="fas fa-book"></i>
                        <span>Katalog</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="leaderboard.php" class="nav-link active">
                        <i class="fas fa-trophy"></i>
                        <span>Leaderboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="misi.php" class="nav-link">
                        <i class="fas fa-tasks"></i>
                        <span>Misi</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profil.php" class="nav-link">
                        <i class="fas fa-user"></i>
                        <span>Profil</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
                <li class="nav-item">
                    <button id="theme-toggle" class="theme-toggle-btn" title="Ganti Tema">
                        <i class="fas fa-moon"></i>
                    </button>
                </li>
            </ul>
        </div>
    </nav>

    <main class="main-container">
        <!-- Header -->
        <div class="header-section glass-card fade-in floating">
            <h1>
                <i class="fas fa-trophy"></i>
                Leaderboard
                <i class="fas fa-trophy"></i>
            </h1>
            <p>Peringkat pembaca teraktif di SMK Mardi Yuana Cikembar</p>
        </div>

        <!-- User Rank -->
        <?php if ($user_rank && isset($user_rank['rank'])): ?>
        <div class="user-rank-card fade-in">
            <h3>Peringkat Kamu</h3>
            <div class="rank-number">#<?php echo $user_rank['rank']; ?></div>
            <p>Terus membaca untuk naik peringkat!</p>
        </div>
        <?php endif; ?>

        <!-- Leaderboard Grid -->
        <div class="leaderboard-grid">
            <!-- XP Leaderboard -->
            <div class="leaderboard-card fade-in">
                <div class="leaderboard-title">
                    <span>⭐</span>
                    <h2>Berdasarkan XP</h2>
                </div>
                
                <div class="leaderboard-list">
                    <?php 
                    if ($xp_leaderboard && $xp_leaderboard->num_rows > 0):
                        $rank = 1;
                        while($user = $xp_leaderboard->fetch_assoc()): 
                    ?>
                        <div class="leaderboard-item <?php echo ($user['id'] == $_SESSION['user_id']) ? 'current-user' : ''; ?>">
                            <div class="rank-badge rank-<?php echo $rank; ?>">
                                <?php
                                if ($rank == 1) echo '🥇';
                                elseif ($rank == 2) echo '🥈';
                                elseif ($rank == 3) echo '🥉';
                                else echo '#'.$rank;
                                ?>
                            </div>
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars($user['nama']); ?></div>
                                <div class="user-class">
                                    <i class="fas fa-graduation-cap"></i>
                                    <?php echo htmlspecialchars($user['kelas'] ?? '-'); ?> • 
                                    <i class="fas fa-code"></i>
                                    <?php echo htmlspecialchars($user['jurusan'] ?? '-'); ?>
                                </div>
                            </div>
                            <div class="user-score">
                                <?php echo number_format($user['xp']); ?> XP
                            </div>
                        </div>
                    <?php 
                        $rank++;
                        endwhile; 
                    else:
                    ?>
                        <div class="empty-state">
                            <div class="empty-icon">📊</div>
                            <p>Belum ada data leaderboard.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Books Read Leaderboard -->
            <div class="leaderboard-card fade-in">
                <div class="leaderboard-title">
                    <span>📚</span>
                    <h2>Berdasarkan Buku Dibaca</h2>
                </div>
                
                <div class="leaderboard-list">
                    <?php 
                    if ($books_leaderboard && $books_leaderboard->num_rows > 0):
                        $rank = 1;
                        while($user = $books_leaderboard->fetch_assoc()): 
                    ?>
                        <div class="leaderboard-item <?php echo ($user['id'] == $_SESSION['user_id']) ? 'current-user' : ''; ?>">
                            <div class="rank-badge rank-<?php echo $rank; ?>">
                                <?php
                                if ($rank == 1) echo '🥇';
                                elseif ($rank == 2) echo '🥈';
                                elseif ($rank == 3) echo '🥉';
                                else echo '#'.$rank;
                                ?>
                            </div>
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars($user['nama']); ?></div>
                                <div class="user-class">
                                    <i class="fas fa-graduation-cap"></i>
                                    <?php echo htmlspecialchars($user['kelas'] ?? '-'); ?> • 
                                    <i class="fas fa-code"></i>
                                    <?php echo htmlspecialchars($user['jurusan'] ?? '-'); ?>
                                </div>
                            </div>
                            <div class="user-score">
                                <?php echo $user['total_buku']; ?> Buku
                            </div>
                        </div>
                    <?php 
                        $rank++;
                        endwhile; 
                    else:
                    ?>
                        <div class="empty-state">
                            <div class="empty-icon">📚</div>
                            <p>Belum ada data buku yang dibaca.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-section">
            <div class="stat-card fade-in">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo number_format($stats['total_pembaca'] ?? 0); ?></div>
                <div class="stat-label">Total Pembaca</div>
            </div>
            
            <div class="stat-card fade-in">
                <div class="stat-icon">📊</div>
                <div class="stat-value"><?php echo number_format(round($stats['rata_rata_xp'] ?? 0)); ?></div>
                <div class="stat-label">Rata-rata XP</div>
            </div>
            
            <div class="stat-card fade-in">
                <div class="stat-icon">🏅</div>
                <div class="stat-value"><?php echo number_format($badges['total_badges'] ?? 0); ?></div>
                <div class="stat-label">Total Badges</div>
            </div>
        </div>
    </main>

    <script>
        // Dark mode toggle
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                // Check for saved theme
                const savedTheme = localStorage.getItem('theme');
                if (savedTheme === 'dark') {
                    document.body.classList.add('dark-mode');
                    themeToggle.querySelector('i').className = 'fas fa-sun';
                }
                
                themeToggle.addEventListener('click', function() {
                    document.body.classList.toggle('dark-mode');
                    const icon = this.querySelector('i');
                    
                    if (document.body.classList.contains('dark-mode')) {
                        icon.className = 'fas fa-sun';
                        localStorage.setItem('theme', 'dark');
                    } else {
                        icon.className = 'fas fa-moon';
                        localStorage.setItem('theme', 'light');
                    }
                    
                    this.style.transform = 'rotate(180deg)';
                    setTimeout(() => {
                        this.style.transform = 'rotate(0)';
                    }, 300);
                });
            }
            
            // Add loading animation on navigation
            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!this.classList.contains('active')) {
                        document.getElementById('loading').classList.add('active');
                    }
                });
            });
        });

        // Parallax effect on mouse move
        document.addEventListener('mousemove', function(e) {
            const shapes = document.querySelectorAll('.bg-shape');
            const mouseX = e.clientX / window.innerWidth;
            const mouseY = e.clientY / window.innerHeight;
            
            shapes.forEach((shape, index) => {
                const speed = (index + 1) * 30;
                const x = (window.innerWidth - mouseX * speed) / 100;
                const y = (window.innerHeight - mouseY * speed) / 100;
                shape.style.transform = `translate(${x}px, ${y}px)`;
            });
        });

        // Smooth scroll to top
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });

        // Add floating animation to stat cards
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach((card, index) => {
            card.classList.add('floating');
            card.style.animationDelay = `${index * 0.2}s`;
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Press 'D' for dashboard
            if (e.key === 'd' || e.key === 'D') {
                window.location.href = '../dashboard.php';
            }
            // Press 'K' for catalog
            if (e.key === 'k' || e.key === 'K') {
                window.location.href = 'katalog.php';
            }
            // Press 'L' for leaderboard
            if (e.key === 'l' || e.key === 'L') {
                window.location.href = 'leaderboard.php';
            }
            // Press 'M' for missions
            if (e.key === 'm' || e.key === 'M') {
                window.location.href = 'misi.php';
            }
            // Press 'P' for profile
            if (e.key === 'p' || e.key === 'P') {
                window.location.href = 'profil.php';
            }
        });
    </script>
</body>
</html>