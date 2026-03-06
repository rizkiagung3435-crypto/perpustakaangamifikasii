<?php
/**
 * Halaman Misi - Perpustakaan Digital Gamifikasi
 * SMK Mardi Yuana Cikembar
 */

require_once '../config/koneksi.php';
require_once '../config/auth.php';

$database = new Database();
$conn = $database->getConnection();

// Get user data
$user_id = $_SESSION['user_id'];

// Get all missions
$query_missions = "SELECT m.*, 
                   COALESCE(um.progress, 0) as user_progress,
                   COALESCE(um.status, 'belum_dimulai') as user_status,
                   um.completed_at
                   FROM misi m
                   LEFT JOIN user_misi um ON m.id_misi = um.id_misi AND um.id_user = ?
                   ORDER BY 
                       CASE 
                           WHEN um.status = 'berlangsung' THEN 1
                           WHEN um.status IS NULL THEN 2
                           WHEN um.status = 'selesai' THEN 3
                       END,
                   m.id_misi";
$stmt = $conn->prepare($query_missions);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$missions = $stmt->get_result();

// Get mission categories
$categories = [
    'reading' => ['icon' => '📖', 'color' => '#4299e1', 'name' => 'Membaca'],
    'borrow' => ['icon' => '📚', 'color' => '#48bb78', 'name' => 'Peminjaman'],
    'review' => ['icon' => '✍️', 'color' => '#ed8936', 'name' => 'Review'],
    'achievement' => ['icon' => '🏆', 'color' => '#9f7aea', 'name' => 'Achievement'],
    'streak' => ['icon' => '🔥', 'color' => '#f56565', 'name' => 'Streak']
];

// Get user stats for mission progress
$query_stats = "SELECT 
                (SELECT COUNT(*) FROM peminjaman WHERE id_user = ?) as total_borrow,
                (SELECT COUNT(DISTINCT id_buku) FROM peminjaman WHERE id_user = ? AND status = 'kembali') as total_read,
                (SELECT COUNT(*) FROM review_buku WHERE id_user = ?) as total_review,
                (SELECT COUNT(*) FROM user_achievement WHERE id_user = ?) as total_achievement,
                (SELECT DATEDIFF(NOW(), MIN(tanggal_pinjam)) FROM peminjaman WHERE id_user = ?) as member_days
                FROM dual";
$stmt = $conn->prepare($query_stats);
$stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$user_stats = $stmt->get_result()->fetch_assoc();

// Page title
$page_title = "Misi - Perpustakaan Digital";
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
    
    <!-- Anime.js for advanced animations -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
    
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

        .floating-book:nth-child(6) { top: 15%; left: 5%; }
        .floating-book:nth-child(7) { top: 75%; left: 85%; animation-delay: 3s; }
        .floating-book:nth-child(8) { top: 45%; left: 92%; animation-delay: 6s; }

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

        /* Header Section */
        .header-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 40px;
            margin-bottom: 30px;
            text-align: center;
            animation: slideDown 0.8s ease;
            position: relative;
            overflow: hidden;
        }

        .header-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        .header-content {
            position: relative;
            z-index: 1;
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

        .header-section p {
            color: #666;
            font-size: 18px;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideUp 0.5s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            transform: translateX(-100%);
            transition: transform 0.5s ease;
        }

        .stat-card:hover::before {
            transform: translateX(0);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.2);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: white;
            position: relative;
            z-index: 1;
        }

        .stat-info {
            flex: 1;
            position: relative;
            z-index: 1;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
        }

        /* Mission Filters */
        .mission-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 12px 25px;
            border: 2px solid #e0e0e0;
            border-radius: 30px;
            background: white;
            color: #666;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .filter-btn:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-2px);
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: transparent;
            color: white;
        }

        .filter-btn i {
            font-size: 16px;
        }

        /* Missions Grid */
        .missions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .mission-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 25px;
            padding: 25px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.5s ease;
        }

        .mission-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.5s ease;
        }

        .mission-card:hover::before {
            transform: scaleX(1);
        }

        .mission-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.2);
        }

        .mission-card.completed {
            background: linear-gradient(135deg, rgba(72, 187, 120, 0.1), rgba(56, 161, 105, 0.1));
            border-color: #48bb78;
        }

        .mission-card.in-progress {
            background: linear-gradient(135deg, rgba(237, 137, 54, 0.1), rgba(221, 107, 32, 0.1));
            border-color: #ed8936;
        }

        .mission-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .mission-category {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .mission-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-badge.completed {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
        }

        .status-badge.in-progress {
            background: linear-gradient(135deg, #ed8936, #dd6b20);
            color: white;
        }

        .status-badge.not-started {
            background: linear-gradient(135deg, #a0aec0, #718096);
            color: white;
        }

        .mission-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .mission-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .mission-rewards {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .reward-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .reward-item.xp {
            color: #667eea;
            font-weight: 600;
        }

        .reward-item.badge {
            color: #ed8936;
            font-weight: 600;
        }

        .mission-progress {
            margin-bottom: 15px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
            color: #666;
        }

        .progress-bar-container {
            width: 100%;
            height: 10px;
            background: #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 5px;
            transition: width 0.5s ease;
            position: relative;
            overflow: hidden;
        }

        .progress-bar-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmer 2s infinite;
        }

        .mission-actions {
            display: flex;
            gap: 10px;
        }

        .btn-mission {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-mission.claim {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
        }

        .btn-mission.claim:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(72, 187, 120, 0.3);
        }

        .btn-mission.track {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-mission.track:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-mission:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Daily Missions Section */
        .daily-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 30px;
            padding: 30px;
            margin-top: 40px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }

        .section-title i {
            color: #fbbf24;
        }

        .daily-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .daily-card {
            background: linear-gradient(135deg, #f7fafc, #edf2f7);
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .daily-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .daily-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: white;
            margin: 0 auto 15px;
        }

        .daily-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .daily-reward {
            color: #667eea;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .daily-check {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 2px solid #e0e0e0;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #48bb78;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .daily-check.checked {
            background: #48bb78;
            border-color: #48bb78;
            color: white;
        }

        .daily-check:hover {
            transform: scale(1.1);
        }

        /* Animations */
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* Confetti Animation */
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background: var(--color);
            position: absolute;
            top: -10px;
            animation: confetti 3s ease infinite;
            z-index: 9999;
        }

        @keyframes confetti {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
        }

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

        /* Toast Notification */
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 15px 25px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 350px;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        .toast.success {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
        }

        .toast.info {
            background: linear-gradient(135deg, #4299e1, #3182ce);
            color: white;
        }

        .toast.warning {
            background: linear-gradient(135deg, #ed8936, #dd6b20);
            color: white;
        }

        /* Dark Mode */
        body.dark-mode {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
        }

        body.dark-mode .header-section,
        body.dark-mode .stat-card,
        body.dark-mode .mission-card,
        body.dark-mode .daily-section,
        body.dark-mode .navbar {
            background: rgba(0, 0, 0, 0.8);
            border-color: rgba(255, 255, 255, 0.1);
        }

        body.dark-mode .mission-title,
        body.dark-mode .section-title,
        body.dark-mode .stat-label,
        body.dark-mode .header-section p {
            color: #e0e0e0;
        }

        body.dark-mode .mission-description {
            color: #a0a0a0;
        }

        body.dark-mode .filter-btn {
            background: #2d3748;
            border-color: #4a5568;
            color: #e0e0e0;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .daily-grid {
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
            
            .main-container {
                padding: 15px;
            }
            
            .missions-grid {
                grid-template-columns: 1fr;
            }
            
            .daily-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .header-section h1 {
                font-size: 28px;
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .mission-actions {
                flex-direction: column;
            }
            
            .stat-card {
                flex-direction: column;
                text-align: center;
            }
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
    </div>

    <!-- Loading Animation -->
    <div class="loading" id="loading">
        <div class="loading-spinner"></div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>

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
                <li><a href="../dashboard.php" class="nav-link"><i class="fas fa-home"></i>Dashboard</a></li>
                <li><a href="katalog.php" class="nav-link"><i class="fas fa-book"></i>Katalog</a></li>
                <li><a href="leaderboard.php" class="nav-link"><i class="fas fa-trophy"></i>Leaderboard</a></li>
                <li><a href="misi.php" class="nav-link active"><i class="fas fa-tasks"></i>Misi</a></li>
                <li><a href="profil.php" class="nav-link"><i class="fas fa-user"></i>Profil</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
                <li><button id="theme-toggle" class="theme-toggle-btn"><i class="fas fa-moon"></i></button></li>
            </ul>
        </div>
    </nav>

    <main class="main-container">
        <!-- Header -->
        <div class="header-section">
            <div class="header-content">
                <h1>
                    <i class="fas fa-tasks"></i>
                    Misi Membaca
                    <i class="fas fa-tasks"></i>
                </h1>
                <p>Selesaikan misi untuk mendapatkan XP dan badge keren!</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card" data-aos="fade-up">
                <div class="stat-icon"><i class="fas fa-book-reader"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $user_stats['total_read'] ?? 0; ?></div>
                    <div class="stat-label">Buku Dibaca</div>
                </div>
            </div>
            
            <div class="stat-card" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-icon"><i class="fas fa-tasks"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $missions->num_rows; ?></div>
                    <div class="stat-label">Total Misi</div>
                </div>
            </div>
            
            <div class="stat-card" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-icon"><i class="fas fa-star"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?php 
                        $completed = 0;
                        $missions->data_seek(0);
                        while($m = $missions->fetch_assoc()) {
                            if($m['user_status'] == 'selesai') $completed++;
                        }
                        echo $completed;
                    ?></div>
                    <div class="stat-label">Misi Selesai</div>
                </div>
            </div>
            
            <div class="stat-card" data-aos="fade-up" data-aos-delay="300">
                <div class="stat-icon"><i class="fas fa-fire"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $user_stats['member_days'] ?? 0; ?></div>
                    <div class="stat-label">Hari Aktif</div>
                </div>
            </div>
        </div>

        <!-- Mission Filters -->
        <div class="mission-filters">
            <button class="filter-btn active" data-filter="all">
                <i class="fas fa-layer-group"></i> Semua Misi
            </button>
            <button class="filter-btn" data-filter="in-progress">
                <i class="fas fa-spinner"></i> Sedang Berjalan
            </button>
            <button class="filter-btn" data-filter="completed">
                <i class="fas fa-check-circle"></i> Selesai
            </button>
            <button class="filter-btn" data-filter="not-started">
                <i class="fas fa-clock"></i> Belum Dimulai
            </button>
        </div>

        <!-- Missions Grid -->
        <div class="missions-grid">
            <?php 
            $missions->data_seek(0);
            while($mission = $missions->fetch_assoc()): 
                $progress = ($mission['user_progress'] / $mission['target']) * 100;
                $category = $categories[array_rand($categories)]; // Random for demo
            ?>
            <div class="mission-card <?php 
                echo $mission['user_status'] == 'selesai' ? 'completed' : 
                    ($mission['user_status'] == 'berlangsung' ? 'in-progress' : ''); 
            ?>" data-status="<?php echo $mission['user_status'] ?? 'not-started'; ?>">
                
                <div class="mission-header">
                    <div class="mission-category" style="background: <?php echo $category['color']; ?>">
                        <?php echo $category['icon']; ?>
                    </div>
                    <div class="mission-status">
                        <span class="status-badge <?php 
                            echo $mission['user_status'] == 'selesai' ? 'completed' : 
                                ($mission['user_status'] == 'berlangsung' ? 'in-progress' : 'not-started'); 
                        ?>">
                            <i class="fas fa-<?php 
                                echo $mission['user_status'] == 'selesai' ? 'check-circle' : 
                                    ($mission['user_status'] == 'berlangsung' ? 'spinner fa-spin' : 'clock'); 
                            ?>"></i>
                            <?php 
                                echo $mission['user_status'] == 'selesai' ? 'Selesai' : 
                                    ($mission['user_status'] == 'berlangsung' ? 'Sedang Berjalan' : 'Belum Dimulai'); 
                            ?>
                        </span>
                    </div>
                </div>

                <h3 class="mission-title"><?php echo htmlspecialchars($mission['nama_misi']); ?></h3>
                <p class="mission-description"><?php echo htmlspecialchars($mission['deskripsi']); ?></p>

                <div class="mission-rewards">
                    <div class="reward-item xp">
                        <i class="fas fa-star"></i>
                        <span>+<?php echo $mission['xp_reward']; ?> XP</span>
                    </div>
                    <?php if($mission['badge_reward']): ?>
                    <div class="reward-item badge">
                        <i class="fas fa-medal"></i>
                        <span><?php echo $mission['badge_reward']; ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mission-progress">
                    <div class="progress-header">
                        <span>Progress</span>
                        <span><?php echo $mission['user_progress']; ?>/<?php echo $mission['target']; ?></span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo $progress; ?>%"></div>
                    </div>
                </div>

                <div class="mission-actions">
                    <?php if($mission['user_status'] == 'selesai'): ?>
                        <button class="btn-mission claim" disabled>
                            <i class="fas fa-check"></i> Sudah Diklaim
                        </button>
                    <?php elseif($mission['user_status'] == 'berlangsung'): ?>
                        <?php if($mission['user_progress'] >= $mission['target']): ?>
                            <button class="btn-mission claim" onclick="claimMission(<?php echo $mission['id_misi']; ?>)">
                                <i class="fas fa-gift"></i> Klaim Hadiah
                            </button>
                        <?php else: ?>
                            <button class="btn-mission track" onclick="trackMission(<?php echo $mission['id_misi']; ?>)">
                                <i class="fas fa-chart-line"></i> Lacak Progress
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="btn-mission track" onclick="startMission(<?php echo $mission['id_misi']; ?>)">
                            <i class="fas fa-play"></i> Mulai Misi
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- Daily Missions -->
        <div class="daily-section">
            <h2 class="section-title">
                <i class="fas fa-calendar-day"></i>
                Misi Harian
            </h2>
            
            <div class="daily-grid">
                <div class="daily-card">
                    <div class="daily-icon">📖</div>
                    <h3 class="daily-title">Baca 1 Buku</h3>
                    <div class="daily-reward">+50 XP</div>
                    <div class="daily-check" onclick="checkDaily(this)">
                        <i class="fas fa-check"></i>
                    </div>
                </div>
                
                <div class="daily-card">
                    <div class="daily-icon">⭐</div>
                    <h3 class="daily-title">Beri 1 Review</h3>
                    <div class="daily-reward">+30 XP</div>
                    <div class="daily-check" onclick="checkDaily(this)">
                        <i class="fas fa-check"></i>
                    </div>
                </div>
                
                <div class="daily-card">
                    <div class="daily-icon">🔥</div>
                    <h3 class="daily-title">Login 3 Hari Berturut</h3>
                    <div class="daily-reward">+100 XP</div>
                    <div class="daily-check" onclick="checkDaily(this)">
                        <i class="fas fa-check"></i>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Dark mode toggle
        const themeToggle = document.getElementById('theme-toggle');
        themeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            const icon = this.querySelector('i');
            if(document.body.classList.contains('dark-mode')) {
                icon.className = 'fas fa-sun';
                localStorage.setItem('theme', 'dark');
            } else {
                icon.className = 'fas fa-moon';
                localStorage.setItem('theme', 'light');
            }
            this.style.transform = 'rotate(180deg)';
            setTimeout(() => this.style.transform = 'rotate(0)', 300);
        });

        // Check saved theme
        if(localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-mode');
            themeToggle.querySelector('i').className = 'fas fa-sun';
        }

        // Filter missions
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                const cards = document.querySelectorAll('.mission-card');
                
                cards.forEach(card => {
                    if(filter === 'all') {
                        card.style.display = 'block';
                        animateCard(card);
                    } else {
                        const status = card.dataset.status;
                        if(status === filter || (filter === 'in-progress' && status === 'berlangsung') ||
                           (filter === 'completed' && status === 'selesai') ||
                           (filter === 'not-started' && status === 'not-started')) {
                            card.style.display = 'block';
                            animateCard(card);
                        } else {
                            card.style.display = 'none';
                        }
                    }
                });
            });
        });

        function animateCard(card) {
            card.style.animation = 'none';
            card.offsetHeight;
            card.style.animation = 'fadeIn 0.5s ease';
        }

        // Mission actions
        function startMission(missionId) {
            showToast('Misi dimulai! Semangat!', 'info');
            // Add confetti effect
            createConfetti();
        }

        function trackMission(missionId) {
            showToast('Lacak progress misi di halaman profil', 'info');
        }

        function claimMission(missionId) {
            showToast('Selamat! Kamu mendapatkan hadiah! +100 XP', 'success');
            createConfetti();
            
            // Animate the claim button
            const btn = event.target;
            btn.innerHTML = '<i class="fas fa-check"></i> Sudah Diklaim';
            btn.classList.add('claim');
            btn.disabled = true;
        }

        // Daily check
        function checkDaily(element) {
            if(!element.classList.contains('checked')) {
                element.classList.add('checked');
                showToast('Misi harian selesai! +50 XP', 'success');
                createConfetti();
                
                // Animate
                anime({
                    targets: element,
                    scale: [1, 1.2, 1],
                    duration: 500,
                    easing: 'easeInOutSine'
                });
            }
        }

        // Toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = `toast ${type}`;
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Confetti effect
        function createConfetti() {
            for(let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.animationDelay = Math.random() * 2 + 's';
                confetti.style.setProperty('--color', `hsl(${Math.random() * 360}, 100%, 50%)`);
                document.body.appendChild(confetti);
                
                setTimeout(() => confetti.remove(), 3000);
            }
        }

        // Animate stats on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if(entry.isIntersecting) {
                    entry.target.style.animation = 'slideUp 0.5s ease';
                }
            });
        });

        document.querySelectorAll('.stat-card').forEach(card => {
            observer.observe(card);
        });

        // Parallax effect
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

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if(e.key === 'm' || e.key === 'M') {
                window.location.href = 'misi.php';
            }
            if(e.key === '1') {
                document.querySelector('[data-filter="all"]').click();
            }
            if(e.key === '2') {
                document.querySelector('[data-filter="in-progress"]').click();
            }
            if(e.key === '3') {
                document.querySelector('[data-filter="completed"]').click();
            }
        });

        // Loading animation on navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if(!this.classList.contains('active')) {
                    document.getElementById('loading').classList.add('active');
                }
            });
        });

        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate header
            anime({
                targets: '.header-section',
                translateY: [50, 0],
                opacity: [0, 1],
                duration: 800,
                easing: 'easeOutElastic'
            });

            // Animate stat cards
            anime({
                targets: '.stat-card',
                translateY: [30, 0],
                opacity: [0, 1],
                duration: 600,
                delay: anime.stagger(100)
            });

            // Animate mission cards
            anime({
                targets: '.mission-card',
                translateY: [30, 0],
                opacity: [0, 1],
                duration: 600,
                delay: anime.stagger(50)
            });
        });
    </script>
</body>
</html>