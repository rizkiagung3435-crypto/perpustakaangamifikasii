<?php
/**
 * Halaman Profil - Perpustakaan Digital Gamifikasi
 * SMK Mardi Yuana Cikembar
 */

require_once '../config/koneksi.php';
require_once '../config/auth.php';

$database = new Database();
$conn = $database->getConnection();

// Get user data
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $kelas = $database->escapeString($_POST['kelas']);
        $jurusan = $database->escapeString($_POST['jurusan']);
        
        $update_query = "UPDATE users SET kelas = ?, jurusan = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssi", $kelas, $jurusan, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['profile_success'] = "Profil berhasil diperbarui!";
        } else {
            $_SESSION['profile_error'] = "Gagal memperbarui profil!";
        }
        header('Location: profil.php');
        exit();
    }
    
    if (isset($_POST['update_avatar'])) {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['avatar']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                if ($_FILES['avatar']['size'] <= 2000000) { // 2MB max
                    $upload_dir = '../assets/img/avatars/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    // Generate unique filename
                    $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    // Delete old avatar if not default
                    if ($user['avatar'] != 'default-avatar.png' && file_exists($upload_dir . $user['avatar'])) {
                        unlink($upload_dir . $user['avatar']);
                    }
                    
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                        $update_query = "UPDATE users SET avatar = ? WHERE id = ?";
                        $stmt = $conn->prepare($update_query);
                        $stmt->bind_param("si", $new_filename, $user_id);
                        
                        if ($stmt->execute()) {
                            $_SESSION['avatar_success'] = "Avatar berhasil diperbarui!";
                        } else {
                            $_SESSION['avatar_error'] = "Gagal memperbarui avatar!";
                        }
                    } else {
                        $_SESSION['avatar_error'] = "Gagal mengupload file!";
                    }
                } else {
                    $_SESSION['avatar_error'] = "Ukuran file maksimal 2MB!";
                }
            } else {
                $_SESSION['avatar_error'] = "Format file harus JPG, JPEG, PNG, atau GIF!";
            }
        } else {
            $_SESSION['avatar_error'] = "Pilih file avatar terlebih dahulu!";
        }
        header('Location: profil.php');
        exit();
    }
}

// Refresh user data after update
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get user achievements
$query_achievements = "SELECT a.*, ua.tanggal_dapat 
                      FROM achievement a 
                      JOIN user_achievement ua ON a.id = ua.id_achievement 
                      WHERE ua.id_user = ?
                      ORDER BY ua.tanggal_dapat DESC";
$stmt = $conn->prepare($query_achievements);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$achievements = $stmt->get_result();

// Get reading history
$query_history = "SELECT p.*, b.judul, b.penulis, b.cover 
                 FROM peminjaman p
                 JOIN buku b ON p.id_buku = b.id_buku
                 WHERE p.id_user = ?
                 ORDER BY p.tanggal_pinjam DESC
                 LIMIT 10";
$stmt = $conn->prepare($query_history);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$history = $stmt->get_result();

// Get reading stats
$query_stats = "SELECT 
                COUNT(*) as total_pinjam,
                SUM(CASE WHEN status = 'kembali' THEN 1 ELSE 0 END) as total_kembali,
                SUM(CASE WHEN status = 'dipinjam' THEN 1 ELSE 0 END) as total_dipinjam,
                AVG(DATEDIFF(tanggal_kembali, tanggal_pinjam)) as rata_rata_hari
                FROM peminjaman 
                WHERE id_user = ?";
$stmt = $conn->prepare($query_stats);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get favorite categories
$query_categories = "SELECT b.kategori, COUNT(*) as total 
                    FROM peminjaman p
                    JOIN buku b ON p.id_buku = b.id_buku
                    WHERE p.id_user = ? AND b.kategori IS NOT NULL
                    GROUP BY b.kategori
                    ORDER BY total DESC
                    LIMIT 5";
$stmt = $conn->prepare($query_categories);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$categories = $stmt->get_result();

// Get session messages
$profile_success = $_SESSION['profile_success'] ?? '';
$profile_error = $_SESSION['profile_error'] ?? '';
$avatar_success = $_SESSION['avatar_success'] ?? '';
$avatar_error = $_SESSION['avatar_error'] ?? '';
unset($_SESSION['profile_success'], $_SESSION['profile_error'], $_SESSION['avatar_success'], $_SESSION['avatar_error']);

// Get avatar HTML function
function getAvatar($user) {
    if ($user['avatar'] && $user['avatar'] != 'default-avatar.png' && file_exists("../assets/img/avatars/" . $user['avatar'])) {
        return "<img src='../assets/img/avatars/" . htmlspecialchars($user['avatar']) . "' alt='Avatar' class='avatar-img' style='width: 100%; height: 100%; object-fit: cover;'>";
    }
    return "<span>" . strtoupper(substr($user['nama'], 0, 1)) . "</span>";
}

// Page title
$page_title = "Profil - Perpustakaan Digital";
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
    
    <!-- Chart.js for statistics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Anime.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
    
    <!-- Cropper.js for image cropping -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    
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

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(5deg); }
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

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.5s ease;
        }

        .alert-success {
            background: linear-gradient(135deg, #f0fff4, #c6f6d5);
            border: 1px solid #9ae6b4;
            color: #22543d;
        }

        .alert-error {
            background: linear-gradient(135deg, #fff5f5, #fed7d7);
            border: 1px solid #feb2b2;
            color: #c53030;
        }

        .alert i {
            font-size: 20px;
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

        /* Profile Header */
        .profile-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 40px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        .profile-content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 40px;
            flex-wrap: wrap;
        }

        .profile-avatar {
            position: relative;
            width: 150px;
            height: 150px;
        }

        .avatar-wrapper {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .avatar-wrapper:hover {
            transform: scale(1.05);
        }

        .avatar-wrapper:hover .avatar-overlay {
            opacity: 1;
        }

        .avatar-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            gap: 5px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .avatar-overlay i {
            font-size: 24px;
        }

        .avatar-overlay span {
            font-size: 12px;
        }

        .avatar-badge {
            position: absolute;
            bottom: -5px;
            right: -5px;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #48bb78, #38a169);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            border: 3px solid white;
            animation: pulse 2s infinite;
        }

        .avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .profile-name .highlight {
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .verification-badge {
            background: linear-gradient(135deg, #4299e1, #3182ce);
            color: white;
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .profile-details {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
            font-size: 16px;
        }

        .detail-item i {
            width: 30px;
            height: 30px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
        }

        .profile-stats {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }

        .profile-stat {
            text-align: center;
        }

        .profile-stat .stat-value {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.2;
        }

        .profile-stat .stat-label {
            font-size: 14px;
            color: #666;
        }

        .edit-profile-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 15px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        .edit-profile-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.2);
        }

        .stat-card-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: white;
        }

        .stat-card-info {
            flex: 1;
        }

        .stat-card-value {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.2;
        }

        .stat-card-label {
            font-size: 14px;
            color: #666;
        }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 25px;
            padding: 25px;
        }

        .chart-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .chart-title i {
            color: #667eea;
        }

        .chart-container {
            position: relative;
            height: 250px;
        }

        /* Achievements Section */
        .achievements-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 25px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        .view-all {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .view-all:hover {
            transform: translateX(5px);
        }

        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        .badge-card {
            background: linear-gradient(135deg, #f7fafc, #edf2f7);
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .badge-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.1) 0%, transparent 70%);
            animation: rotate 10s linear infinite;
        }

        .badge-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.2);
        }

        .badge-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: white;
            margin: 0 auto 15px;
            position: relative;
            z-index: 1;
            animation: bounce 2s infinite;
        }

        .badge-name {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .badge-date {
            font-size: 12px;
            color: #666;
        }

        /* History Section */
        .history-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 25px;
            padding: 25px;
        }

        .history-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .history-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 15px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 15px;
            transition: all 0.3s ease;
        }

        .history-item:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            transform: translateX(10px);
        }

        .history-item:hover .history-title,
        .history-item:hover .history-meta {
            color: white;
        }

        .history-cover {
            width: 60px;
            height: 80px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }

        .history-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .history-info {
            flex: 1;
        }

        .history-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .history-meta {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: #666;
        }

        .history-meta i {
            margin-right: 5px;
        }

        .history-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .history-status.dipinjam {
            background: rgba(237, 137, 54, 0.2);
            color: #ed8936;
        }

        .history-status.kembali {
            background: rgba(72, 187, 120, 0.2);
            color: #48bb78;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 30px;
            padding: 40px;
            max-width: 600px;
            width: 90%;
            position: relative;
            animation: slideUp 0.5s ease;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            transition: all 0.3s ease;
            z-index: 1;
        }

        .modal-close:hover {
            color: #f56565;
            transform: rotate(90deg);
        }

        .modal-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-input:disabled {
            background: #f7fafc;
            cursor: not-allowed;
        }

        .btn-save {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        /* Upload Area */
        .upload-area {
            border: 2px dashed #e0e0e0;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .upload-area:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .upload-area i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 10px;
        }

        .upload-area p {
            color: #666;
            margin-bottom: 5px;
        }

        .upload-area small {
            color: #999;
            font-size: 12px;
        }

        .upload-preview {
            width: 200px;
            height: 200px;
            margin: 20px auto;
            border-radius: 30px;
            overflow: hidden;
            border: 3px solid #667eea;
        }

        .upload-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Cropper Container */
        .cropper-container {
            max-height: 300px;
            margin-bottom: 20px;
        }

        /* Avatar Grid */
        .avatar-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .avatar-option {
            text-align: center;
            cursor: pointer;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            transition: all 0.3s ease;
        }

        .avatar-option:hover {
            border-color: #667eea;
            transform: scale(1.05);
        }

        .avatar-option.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }

        .avatar-option span {
            font-size: 40px;
        }

        /* Tab Navigation */
        .tab-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }

        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: none;
            color: #666;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 10px;
        }

        .tab-btn:hover {
            color: #667eea;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        /* Animations */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
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
                transform: scale(1.1);
            }
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Loading */
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

        /* Toast */
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

        .toast.error {
            background: linear-gradient(135deg, #f56565, #c53030);
            color: white;
        }

        /* Dark Mode */
        body.dark-mode {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
        }

        body.dark-mode .profile-header,
        body.dark-mode .stat-card,
        body.dark-mode .chart-card,
        body.dark-mode .achievements-section,
        body.dark-mode .history-section,
        body.dark-mode .navbar,
        body.dark-mode .modal-content {
            background: rgba(0, 0, 0, 0.8);
            border-color: rgba(255, 255, 255, 0.1);
        }

        body.dark-mode .profile-name,
        body.dark-mode .section-title,
        body.dark-mode .stat-card-label,
        body.dark-mode .detail-item,
        body.dark-mode .badge-name,
        body.dark-mode .modal-title,
        body.dark-mode .form-label {
            color: #e0e0e0;
        }

        body.dark-mode .history-item {
            background: rgba(255, 255, 255, 0.05);
        }

        body.dark-mode .form-input {
            background: #2d3748;
            border-color: #4a5568;
            color: white;
        }

        body.dark-mode .upload-area {
            border-color: #4a5568;
        }

        body.dark-mode .upload-area p {
            color: #a0aec0;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .stats-grid,
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-content {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-details {
                justify-content: center;
            }
            
            .profile-stats {
                justify-content: center;
            }
            
            .edit-profile-btn {
                margin: 0 auto;
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
            
            .profile-name {
                font-size: 28px;
                justify-content: center;
            }
            
            .badges-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .avatar-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .badges-grid {
                grid-template-columns: 1fr;
            }
            
            .history-item {
                flex-direction: column;
                text-align: center;
            }
            
            .history-meta {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .avatar-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="animated-bg">
        <div class="bg-shape"></div>
        <div class="bg-shape"></div>
        <div class="floating-book">📚</div>
        <div class="floating-book">📖</div>
    </div>

    <!-- Loading -->
    <div class="loading" id="loading">
        <div class="loading-spinner"></div>
    </div>

    <!-- Toast -->
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
                <li><a href="misi.php" class="nav-link"><i class="fas fa-tasks"></i>Misi</a></li>
                <li><a href="profil.php" class="nav-link active"><i class="fas fa-user"></i>Profil</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
                <li><button id="theme-toggle" class="theme-toggle-btn"><i class="fas fa-moon"></i></button></li>
            </ul>
        </div>
    </nav>

    <main class="main-container">
        <!-- Alert Messages -->
        <?php if ($profile_success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $profile_success; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($profile_error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $profile_error; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($avatar_success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $avatar_success; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($avatar_error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $avatar_error; ?>
        </div>
        <?php endif; ?>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-content">
                <div class="profile-avatar">
                    <div class="avatar-wrapper" onclick="openAvatarModal()">
                        <?php echo getAvatar($user); ?>
                        <div class="avatar-overlay">
                            <i class="fas fa-camera"></i>
                            <span>Ganti Foto</span>
                        </div>
                    </div>
                    <div class="avatar-badge">
                        <i class="fas fa-check"></i>
                    </div>
                </div>

                <div class="profile-info">
                    <div class="profile-name">
                        <span class="highlight"><?php echo htmlspecialchars($user['nama']); ?></span>
                        <span class="verification-badge">
                            <i class="fas fa-check-circle"></i> Terverifikasi
                        </span>
                    </div>

                    <div class="profile-details">
                        <div class="detail-item">
                            <i class="fas fa-graduation-cap"></i>
                            <span><?php echo htmlspecialchars($user['kelas'] ?? '-'); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-code"></i>
                            <span><?php echo htmlspecialchars($user['jurusan'] ?? '-'); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-calendar"></i>
                            <span>Bergabung <?php echo date('d M Y', strtotime($user['created_at'])); ?></span>
                        </div>
                    </div>

                    <div class="profile-stats">
                        <div class="profile-stat">
                            <div class="stat-value"><?php echo $stats['total_pinjam'] ?? 0; ?></div>
                            <div class="stat-label">Total Pinjam</div>
                        </div>
                        <div class="profile-stat">
                            <div class="stat-value"><?php echo $stats['total_kembali'] ?? 0; ?></div>
                            <div class="stat-label">Selesai</div>
                        </div>
                        <div class="profile-stat">
                            <div class="stat-value"><?php echo $stats['total_dipinjam'] ?? 0; ?></div>
                            <div class="stat-label">Sedang Dipinjam</div>
                        </div>
                        <div class="profile-stat">
                            <div class="stat-value"><?php echo round($stats['rata_rata_hari'] ?? 0); ?> hari</div>
                            <div class="stat-label">Rata-rata</div>
                        </div>
                    </div>
                </div>

                <button class="edit-profile-btn" onclick="openEditModal()">
                    <i class="fas fa-edit"></i>
                    Edit Profil
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-icon"><i class="fas fa-star"></i></div>
                <div class="stat-card-info">
                    <div class="stat-card-value"><?php echo number_format($user['xp']); ?></div>
                    <div class="stat-card-label">Total XP</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon"><i class="fas fa-trophy"></i></div>
                <div class="stat-card-info">
                    <div class="stat-card-value"><?php echo $achievements->num_rows; ?></div>
                    <div class="stat-card-label">Badges</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon"><i class="fas fa-fire"></i></div>
                <div class="stat-card-info">
                    <div class="stat-card-value">0</div>
                    <div class="stat-card-label">Streak</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-card-info">
                    <div class="stat-card-value"><?php echo $stats['total_pinjam'] ?? 0; ?></div>
                    <div class="stat-card-label">Total Transaksi</div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <h3 class="chart-title">
                    <i class="fas fa-chart-pie"></i>
                    Kategori Favorit
                </h3>
                <div class="chart-container">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3 class="chart-title">
                    <i class="fas fa-chart-line"></i>
                    Aktivitas Membaca
                </h3>
                <div class="chart-container">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Achievements -->
        <div class="achievements-section">
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-medal"></i>
                    Badges Terbaru
                </h3>
                <a href="achievement.php" class="view-all">
                    Lihat Semua <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <div class="badges-grid">
                <?php 
                $count = 0;
                while($badge = $achievements->fetch_assoc()): 
                    if($count++ >= 4) break;
                ?>
                <div class="badge-card">
                    <div class="badge-icon"><?php echo $badge['icon'] ?? '🏆'; ?></div>
                    <div class="badge-name"><?php echo htmlspecialchars($badge['nama_badge']); ?></div>
                    <div class="badge-date"><?php echo date('d M Y', strtotime($badge['tanggal_dapat'])); ?></div>
                </div>
                <?php endwhile; ?>
                
                <?php if($count == 0): ?>
                <div class="badge-card">
                    <div class="badge-icon">🔜</div>
                    <div class="badge-name">Belum Ada Badge</div>
                    <div class="badge-date">Ayo raih badge pertamamu!</div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- History -->
        <div class="history-section">
            <h3 class="section-title">
                <i class="fas fa-history"></i>
                Riwayat Peminjaman
            </h3>

            <div class="history-list">
                <?php if($history->num_rows > 0): ?>
                    <?php while($row = $history->fetch_assoc()): ?>
                    <div class="history-item">
                        <div class="history-cover">
                            <img src="../assets/img/covers/<?php echo $row['cover'] ?: 'default-book.jpg'; ?>" alt="Cover">
                        </div>
                        <div class="history-info">
                            <div class="history-title"><?php echo htmlspecialchars($row['judul']); ?></div>
                            <div class="history-meta">
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($row['penulis']); ?></span>
                                <span><i class="fas fa-calendar"></i> Pinjam: <?php echo date('d/m/Y', strtotime($row['tanggal_pinjam'])); ?></span>
                                <span><i class="fas fa-calendar-check"></i> Kembali: <?php echo date('d/m/Y', strtotime($row['tanggal_kembali'])); ?></span>
                            </div>
                        </div>
                        <div class="history-status <?php echo $row['status']; ?>">
                            <?php echo $row['status'] == 'dipinjam' ? 'Dipinjam' : 'Dikembalikan'; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-book-open" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>Belum ada riwayat peminjaman</p>
                        <a href="katalog.php" class="btn-primary" style="display: inline-block; margin-top: 15px; padding: 10px 25px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-decoration: none; border-radius: 10px;">
                            <i class="fas fa-search"></i> Cari Buku
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Edit Profile Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('editModal')">&times;</span>
            <h2 class="modal-title">Edit Profil</h2>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" class="form-input" value="<?php echo htmlspecialchars($user['nama']); ?>" disabled>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Kelas</label>
                    <input type="text" class="form-input" name="kelas" value="<?php echo htmlspecialchars($user['kelas']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Jurusan</label>
                    <select class="form-input" name="jurusan" required>
                        <option value="Rekayasa Perangkat Lunak" <?php echo $user['jurusan'] == 'Rekayasa Perangkat Lunak' ? 'selected' : ''; ?>>Rekayasa Perangkat Lunak</option>
                        <option value="DPB / Tata Busana" <?php echo $user['jurusan'] == 'DPB / Tata Busana' ? 'selected' : ''; ?>>DPB / Tata Busana</option>
                    </select>
                </div>
                
                <button type="submit" name="update_profile" class="btn-save">
                    <i class="fas fa-save"></i>
                    Simpan Perubahan
                </button>
            </form>
        </div>
    </div>

    <!-- Avatar Modal -->
    <div class="modal" id="avatarModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('avatarModal')">&times;</span>
            <h2 class="modal-title">Ganti Avatar</h2>
            
            <!-- Tab Navigation -->
            <div class="tab-nav">
                <button class="tab-btn active" onclick="switchTab('upload')">Upload Foto</button>
                <button class="tab-btn" onclick="switchTab('emoji')">Pilih Emoji</button>
            </div>
            
            <!-- Upload Tab -->
            <div class="tab-content active" id="uploadTab">
                <form method="POST" action="" enctype="multipart/form-data" id="avatarForm">
                    <div class="upload-area" onclick="document.getElementById('avatarInput').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Klik untuk memilih foto</p>
                        <small>Format: JPG, PNG, GIF (Maks. 2MB)</small>
                    </div>
                    
                    <input type="file" id="avatarInput" name="avatar" accept="image/*" style="display: none;" onchange="previewImage(this)">
                    
                    <div class="upload-preview" id="imagePreview" style="display: none;">
                        <img src="" alt="Preview">
                    </div>
                    
                    <div class="cropper-container" id="cropperContainer" style="display: none;">
                        <img id="cropperImage" src="">
                    </div>
                    
                    <button type="submit" name="update_avatar" class="btn-save" id="uploadBtn" style="display: none;">
                        <i class="fas fa-upload"></i>
                        Upload Foto
                    </button>
                </form>
            </div>
            
            <!-- Emoji Tab -->
            <div class="tab-content" id="emojiTab">
                <p style="text-align: center; color: #666; margin-bottom: 20px;">Pilih avatar emoji untuk profilmu</p>
                
                <form method="POST" action="" id="emojiForm">
                    <div class="avatar-grid">
                        <?php 
                        $avatars = ['🎓', '👨‍🎓', '👩‍🎓', '📚', '🌟', '⭐', '🎯', '🏆', '🎨', '🎭', '🎪', '🎢', '🌈', '⭐', '💫', '✨'];
                        foreach($avatars as $avatar): 
                        ?>
                        <div class="avatar-option" onclick="selectEmoji(this, '<?php echo $avatar; ?>')">
                            <span><?php echo $avatar; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <input type="hidden" name="emoji_avatar" id="selectedEmoji" value="">
                    <button type="submit" name="update_emoji" class="btn-save" id="emojiSaveBtn" style="display: none;">
                        <i class="fas fa-check"></i>
                        Pilih Emoji
                    </button>
                </form>
            </div>
        </div>
    </div>

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
        });

        // Check saved theme
        if(localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-mode');
            themeToggle.querySelector('i').className = 'fas fa-sun';
        }

        // Modal functions
        function openEditModal() {
            document.getElementById('editModal').classList.add('active');
        }

        function openAvatarModal() {
            document.getElementById('avatarModal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            // Reset tabs
            if(modalId === 'avatarModal') {
                switchTab('upload');
                document.getElementById('imagePreview').style.display = 'none';
                document.getElementById('uploadBtn').style.display = 'none';
            }
        }

        // Tab switching
        function switchTab(tab) {
            const tabs = document.querySelectorAll('.tab-btn');
            const contents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            
            if(tab === 'upload') {
                tabs[0].classList.add('active');
                document.getElementById('uploadTab').classList.add('active');
            } else {
                tabs[1].classList.add('active');
                document.getElementById('emojiTab').classList.add('active');
            }
        }

        // Image preview and cropping
        let cropper = null;

        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const uploadBtn = document.getElementById('uploadBtn');
            const cropperContainer = document.getElementById('cropperContainer');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Destroy previous cropper
                    if (cropper) {
                        cropper.destroy();
                    }
                    
                    // Show cropper
                    cropperContainer.style.display = 'block';
                    const image = document.getElementById('cropperImage');
                    image.src = e.target.result;
                    
                    // Initialize cropper
                    cropper = new Cropper(image, {
                        aspectRatio: 1,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 0.8,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        background: false,
                        ready: function() {
                            preview.style.display = 'none';
                        }
                    });
                    
                    uploadBtn.style.display = 'block';
                    uploadBtn.innerHTML = '<i class="fas fa-crop"></i> Crop & Upload';
                    
                    // Modify form submission
                    document.getElementById('avatarForm').onsubmit = function(e) {
                        e.preventDefault();
                        
                        if (cropper) {
                            const canvas = cropper.getCroppedCanvas({
                                width: 300,
                                height: 300
                            });
                            
                            canvas.toBlob(function(blob) {
                                const formData = new FormData();
                                formData.append('avatar', blob, 'avatar.jpg');
                                formData.append('update_avatar', '1');
                                
                                // Show loading
                                document.getElementById('loading').classList.add('active');
                                
                                // Upload
                                fetch('profil.php', {
                                    method: 'POST',
                                    body: formData
                                }).then(response => {
                                    if(response.redirected) {
                                        window.location.href = response.url;
                                    }
                                }).catch(error => {
                                    console.error('Error:', error);
                                    document.getElementById('loading').classList.remove('active');
                                    showToast('Gagal mengupload avatar', 'error');
                                });
                            }, 'image/jpeg', 0.9);
                        }
                    };
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Emoji selection
        function selectEmoji(element, emoji) {
            // Remove selected class from all options
            document.querySelectorAll('.avatar-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            element.classList.add('selected');
            
            // Set hidden input value
            document.getElementById('selectedEmoji').value = emoji;
            
            // Show save button
            const saveBtn = document.getElementById('emojiSaveBtn');
            saveBtn.style.display = 'block';
            saveBtn.innerHTML = '<i class="fas fa-check"></i> Pilih ' + emoji;
        }

        // Emoji form submission
        document.getElementById('emojiForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const emoji = document.getElementById('selectedEmoji').value;
            if (!emoji) {
                showToast('Pilih emoji terlebih dahulu!', 'error');
                return;
            }
            
            // Create form data
            const formData = new FormData();
            formData.append('emoji_avatar', emoji);
            formData.append('update_emoji', '1');
            
            // Show loading
            document.getElementById('loading').classList.add('active');
            
            // Send request
            fetch('profil.php', {
                method: 'POST',
                body: formData
            }).then(response => {
                if(response.redirected) {
                    window.location.href = response.url;
                }
            }).catch(error => {
                console.error('Error:', error);
                document.getElementById('loading').classList.remove('active');
                showToast('Gagal memperbarui avatar', 'error');
            });
        });

        // Toast
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            toast.className = `toast ${type}`;
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Category Chart
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: [<?php 
                        $categories->data_seek(0);
                        $labels = [];
                        $values = [];
                        while($cat = $categories->fetch_assoc()) {
                            $labels[] = "'" . addslashes($cat['kategori']) . "'";
                            $values[] = $cat['total'];
                        }
                        echo implode(',', $labels);
                    ?>],
                    datasets: [{
                        data: [<?php echo implode(',', $values); ?>],
                        backgroundColor: [
                            '#667eea',
                            '#764ba2',
                            '#48bb78',
                            '#fbbf24',
                            '#f56565'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#333'
                            }
                        }
                    }
                }
            });

            // Activity Chart
            const activityCtx = document.getElementById('activityChart').getContext('2d');
            new Chart(activityCtx, {
                type: 'line',
                data: {
                    labels: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
                    datasets: [{
                        label: 'Buku Dibaca',
                        data: [2, 4, 3, 5, 2, 3, 4],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        });

        // Animate stats on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if(entry.isIntersecting) {
                    anime({
                        targets: entry.target,
                        translateY: [30, 0],
                        opacity: [0, 1],
                        duration: 600,
                        easing: 'easeOutQuad'
                    });
                }
            });
        });

        document.querySelectorAll('.stat-card, .badge-card, .history-item').forEach(el => {
            observer.observe(el);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if(e.key === 'p' || e.key === 'P') {
                window.location.href = 'profil.php';
            }
            if(e.key === 'Escape') {
                closeModal('editModal');
                closeModal('avatarModal');
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

        // Animate profile on load
        anime({
            targets: '.profile-header',
            translateY: [50, 0],
            opacity: [0, 1],
            duration: 800,
            easing: 'easeOutElastic'
        });

        anime({
            targets: '.stat-card',
            translateY: [30, 0],
            opacity: [0, 1],
            duration: 600,
            delay: anime.stagger(100)
        });

        // Auto hide alerts
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 5000);
        });
    </script>
</body>
</html>