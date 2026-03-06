<?php
/**
 * Halaman Tambah Buku - Admin Perpustakaan Digital Gamifikasi
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

// Get admin details
$admin_details = null;
$check_admin_table = $conn->query("SHOW TABLES LIKE 'admin_details'");
if ($check_admin_table->num_rows > 0) {
    $query_details = "SELECT * FROM admin_details WHERE user_id = ?";
    $stmt_details = $conn->prepare($query_details);
    $stmt_details->bind_param("i", $user_id);
    $stmt_details->execute();
    $admin_details = $stmt_details->get_result()->fetch_assoc();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $judul = $database->escapeString($_POST['judul']);
    $penulis = $database->escapeString($_POST['penulis']);
    $kategori = $database->escapeString($_POST['kategori']);
    $deskripsi = $database->escapeString($_POST['deskripsi']);
    $tahun = !empty($_POST['tahun_terbit']) ? (int)$_POST['tahun_terbit'] : null;
    $isbn = !empty($_POST['isbn']) ? $database->escapeString($_POST['isbn']) : null;
    $penerbit = !empty($_POST['penerbit']) ? $database->escapeString($_POST['penerbit']) : null;
    $lokasi_rak = !empty($_POST['lokasi_rak']) ? $database->escapeString($_POST['lokasi_rak']) : null;
    $stok = (int)$_POST['stok'];
    
    // Handle cover upload
    $cover = null;
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['cover']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            if ($_FILES['cover']['size'] <= 2000000) { // 2MB max
                $target_dir = "../assets/img/covers/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                
                $cover = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
                $target_file = $target_dir . $cover;
                
                if (!move_uploaded_file($_FILES['cover']['tmp_name'], $target_file)) {
                    $error = "Gagal mengupload cover buku";
                    $cover = null;
                }
            } else {
                $error = "Ukuran file maksimal 2MB!";
            }
        } else {
            $error = "Format file harus JPG, JPEG, PNG, atau GIF!";
        }
    }
    
    // Cek struktur tabel buku terlebih dahulu
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM buku");
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    // Bangun query dinamis berdasarkan kolom yang ada
    $fields = ['judul', 'penulis', 'kategori', 'deskripsi', 'cover', 'tahun_terbit', 'isbn', 'stok'];
    $placeholders = ['?', '?', '?', '?', '?', '?', '?', '?'];
    $types = "ssssssii"; // string, string, string, string, string, integer, string, integer
    $params = [$judul, $penulis, $kategori, $deskripsi, $cover, $tahun, $isbn, $stok];
    
    // Tambahkan penerbit jika kolom ada
    if (in_array('penerbit', $columns)) {
        $fields[] = 'penerbit';
        $placeholders[] = '?';
        $types .= "s";
        $params[] = $penerbit;
    }
    
    // Tambahkan lokasi_rak jika kolom ada
    if (in_array('lokasi_rak', $columns)) {
        $fields[] = 'lokasi_rak';
        $placeholders[] = '?';
        $types .= "s";
        $params[] = $lokasi_rak;
    }
    
    // Buat query dinamis
    $query = "INSERT INTO buku (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    
    $stmt = $conn->prepare($query);
    
    // Bind parameters dynamically
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Buku berhasil ditambahkan!";
        header('Location: data_buku.php');
        exit();
    } else {
        $error = "Gagal menambahkan buku: " . $conn->error;
    }
}

$page_title = "Tambah Buku - Admin Perpustakaan";
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

        /* Animated Background */
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
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
            position: relative;
            z-index: 1;
        }

        /* Form Card */
        .form-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 40px;
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 30px 60px -20px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.6s ease;
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

        .form-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .form-header h1 {
            font-size: 32px;
            font-weight: 800;
            color: white;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            letter-spacing: -0.5px;
        }

        .form-header h1 i {
            background: linear-gradient(145deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 36px;
        }

        .form-header p {
            color: #a0aec0;
            font-size: 16px;
        }

        /* Alert Messages */
        .alert {
            padding: 16px 24px;
            border-radius: 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.5s ease;
            font-size: 14px;
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

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #e2e8f0;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        .form-group label i {
            color: #667eea;
            margin-right: 8px;
            font-size: 16px;
        }

        .form-group label span {
            color: #f56565;
            margin-left: 4px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 16px;
            transition: all 0.3s;
            z-index: 1;
            opacity: 0.7;
        }

        .form-control {
            width: 100%;
            padding: 16px 18px 16px 50px;
            border: 2px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            font-size: 15px;
            transition: all 0.3s;
            background: rgba(0, 0, 0, 0.2);
            color: white;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .form-control:hover {
            border-color: rgba(102, 126, 234, 0.3);
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(0, 0, 0, 0.3);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .form-control:focus + i {
            color: #667eea;
            opacity: 1;
        }

        .form-control::placeholder {
            color: #4a5568;
            font-weight: 400;
        }

        textarea.form-control {
            padding: 16px 18px;
            resize: vertical;
            min-height: 120px;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%23667eea' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 18px center;
            background-size: 18px;
            padding-right: 50px;
        }

        select.form-control option {
            background: #1a1b3b;
            color: white;
        }

        /* File Upload */
        .file-upload {
            position: relative;
            border: 2px dashed rgba(102, 126, 234, 0.3);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: rgba(0, 0, 0, 0.2);
        }

        .file-upload:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .file-upload i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 16px;
            opacity: 0.7;
        }

        .file-upload p {
            color: #e2e8f0;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .file-upload small {
            color: #a0aec0;
            font-size: 12px;
        }

        .file-upload input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .file-preview {
            display: none;
            margin-top: 20px;
            position: relative;
            text-align: center;
        }

        .file-preview img {
            width: 150px;
            height: 200px;
            object-fit: cover;
            border-radius: 16px;
            border: 3px solid #667eea;
            box-shadow: 0 20px 30px -10px rgba(102, 126, 234, 0.3);
        }

        .file-preview .remove-file {
            position: absolute;
            top: -10px;
            right: calc(50% - 85px);
            width: 30px;
            height: 30px;
            background: #f56565;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid white;
        }

        .file-preview .remove-file:hover {
            transform: scale(1.1);
            background: #c53030;
        }

        /* Button Group */
        .button-group {
            display: flex;
            gap: 20px;
            margin-top: 40px;
        }

        .btn-primary, .btn-secondary {
            padding: 16px 32px;
            border: none;
            border-radius: 20px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            text-decoration: none;
            flex: 1;
            letter-spacing: 0.3px;
        }

        .btn-primary {
            background: linear-gradient(145deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 15px 25px -8px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 30px -8px rgba(102, 126, 234, 0.6);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-4px);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .btn-primary i, .btn-secondary i {
            font-size: 18px;
            transition: transform 0.3s;
        }

        .btn-primary:hover i {
            transform: translateX(5px);
        }

        .btn-secondary:hover i {
            transform: translateX(-5px);
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
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: 1;
            }

            .form-card {
                padding: 30px 20px;
            }

            .form-header h1 {
                font-size: 24px;
            }

            .button-group {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .form-card {
                padding: 25px 15px;
            }

            .form-control {
                padding: 14px 16px 14px 45px;
                font-size: 14px;
            }

            .file-upload {
                padding: 30px;
            }

            .file-upload i {
                font-size: 40px;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
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
        <div class="loading-text">Menyimpan data...</div>
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
                <li><a href="tambah_buku.php" class="nav-link active"><i class="fas fa-plus-circle"></i>Tambah</a></li>
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
        <div class="form-card" data-aos="fade-up">
            <div class="form-header">
                <h1>
                    <i class="fas fa-plus-circle"></i>
                    Tambah Buku Baru
                </h1>
                <p>Tambahkan koleksi buku baru ke perpustakaan digital</p>
            </div>

            <!-- Error Message -->
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" action="" enctype="multipart/form-data" id="tambahBukuForm">
                <div class="form-grid">
                    <!-- Judul Buku -->
                    <div class="form-group full-width">
                        <label for="judul">
                            <i class="fas fa-heading"></i>
                            Judul Buku <span>*</span>
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-book"></i>
                            <input type="text" 
                                   class="form-control" 
                                   id="judul" 
                                   name="judul" 
                                   placeholder="Masukkan judul buku"
                                   required>
                        </div>
                    </div>

                    <!-- Penulis -->
                    <div class="form-group full-width">
                        <label for="penulis">
                            <i class="fas fa-pen-fancy"></i>
                            Penulis <span>*</span>
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" 
                                   class="form-control" 
                                   id="penulis" 
                                   name="penulis" 
                                   placeholder="Masukkan nama penulis"
                                   required>
                        </div>
                    </div>

                    <!-- Kategori -->
                    <div class="form-group">
                        <label for="kategori">
                            <i class="fas fa-tag"></i>
                            Kategori <span>*</span>
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-list"></i>
                            <select class="form-control" id="kategori" name="kategori" required>
                                <option value="">Pilih Kategori</option>
                                <option value="Fiksi">📖 Fiksi</option>
                                <option value="Non-Fiksi">📚 Non-Fiksi</option>
                                <option value="Pendidikan">🎓 Pendidikan</option>
                                <option value="Teknologi">💻 Teknologi</option>
                                <option value="Sejarah">📜 Sejarah</option>
                                <option value="Biografi">👤 Biografi</option>
                                <option value="Komik">🦸 Komik</option>
                                <option value="Majalah">📰 Majalah</option>
                                <option value="Novel">📘 Novel</option>
                                <option value="Sains">🔬 Sains</option>
                            </select>
                        </div>
                    </div>

                    <!-- Stok -->
                    <div class="form-group">
                        <label for="stok">
                            <i class="fas fa-boxes"></i>
                            Stok Buku <span>*</span>
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-sort-numeric-up"></i>
                            <input type="number" 
                                   class="form-control" 
                                   id="stok" 
                                   name="stok" 
                                   min="0" 
                                   value="1" 
                                   required>
                        </div>
                    </div>

                    <!-- Tahun Terbit -->
                    <div class="form-group">
                        <label for="tahun_terbit">
                            <i class="fas fa-calendar"></i>
                            Tahun Terbit
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-calendar-alt"></i>
                            <input type="number" 
                                   class="form-control" 
                                   id="tahun_terbit" 
                                   name="tahun_terbit" 
                                   min="1900" 
                                   max="<?php echo date('Y'); ?>"
                                   placeholder="Contoh: 2024">
                        </div>
                    </div>

                    <!-- ISBN -->
                    <div class="form-group">
                        <label for="isbn">
                            <i class="fas fa-barcode"></i>
                            ISBN
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-qrcode"></i>
                            <input type="text" 
                                   class="form-control" 
                                   id="isbn" 
                                   name="isbn" 
                                   placeholder="Contoh: 978-602-1234-56-7">
                        </div>
                    </div>

                    <!-- Penerbit -->
                    <div class="form-group">
                        <label for="penerbit">
                            <i class="fas fa-building"></i>
                            Penerbit
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-building"></i>
                            <input type="text" 
                                   class="form-control" 
                                   id="penerbit" 
                                   name="penerbit" 
                                   placeholder="Nama penerbit">
                        </div>
                    </div>

                    <!-- Lokasi Rak -->
                    <div class="form-group">
                        <label for="lokasi_rak">
                            <i class="fas fa-map-marker-alt"></i>
                            Lokasi Rak
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-map-pin"></i>
                            <input type="text" 
                                   class="form-control" 
                                   id="lokasi_rak" 
                                   name="lokasi_rak" 
                                   placeholder="Contoh: RAK A-1">
                        </div>
                    </div>

                    <!-- Deskripsi -->
                    <div class="form-group full-width">
                        <label for="deskripsi">
                            <i class="fas fa-align-left"></i>
                            Deskripsi
                        </label>
                        <textarea class="form-control" 
                                  id="deskripsi" 
                                  name="deskripsi" 
                                  rows="4" 
                                  placeholder="Masukkan deskripsi buku..."></textarea>
                    </div>

                    <!-- Cover Buku -->
                    <div class="form-group full-width">
                        <label for="cover">
                            <i class="fas fa-image"></i>
                            Cover Buku
                        </label>
                        <div class="file-upload" id="fileUpload">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Klik untuk memilih file atau drag & drop</p>
                            <small>Format: JPG, JPEG, PNG, GIF (Maks. 2MB)</small>
                            <input type="file" 
                                   id="cover" 
                                   name="cover" 
                                   accept="image/*">
                        </div>
                        <div class="file-preview" id="filePreview">
                            <img src="" alt="Preview">
                            <div class="remove-file" onclick="removeFile()">
                                <i class="fas fa-times"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Button Group -->
                <div class="button-group">
                    <button type="submit" class="btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i>
                        Simpan Buku
                    </button>
                    <a href="data_buku.php" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Kembali
                    </a>
                </div>
            </form>
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

        // File upload preview
        const fileInput = document.getElementById('cover');
        const fileUpload = document.getElementById('fileUpload');
        const filePreview = document.getElementById('filePreview');
        const previewImage = filePreview.querySelector('img');

        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Check file size
                if (file.size > 2 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Ukuran File Terlalu Besar',
                        text: 'Ukuran file maksimal 2MB!',
                        confirmButtonColor: '#667eea',
                        background: '#1a1b3b',
                        color: '#fff'
                    });
                    fileInput.value = '';
                    return;
                }

                // Check file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Format File Tidak Didukung',
                        text: 'Format file harus JPG, JPEG, PNG, atau GIF!',
                        confirmButtonColor: '#667eea',
                        background: '#1a1b3b',
                        color: '#fff'
                    });
                    fileInput.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    fileUpload.style.display = 'none';
                    filePreview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });

        function removeFile() {
            fileInput.value = '';
            fileUpload.style.display = 'block';
            filePreview.style.display = 'none';
            previewImage.src = '';
        }

        // Form submission with loading
        document.getElementById('tambahBukuForm').addEventListener('submit', function(e) {
            const judul = document.getElementById('judul').value;
            const penulis = document.getElementById('penulis').value;
            const kategori = document.getElementById('kategori').value;
            const stok = document.getElementById('stok').value;

            if (!judul || !penulis || !kategori || !stok) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Form Belum Lengkap',
                    text: 'Harap isi semua field yang wajib diisi!',
                    confirmButtonColor: '#667eea',
                    background: '#1a1b3b',
                    color: '#fff'
                });
                return;
            }

            const btn = document.getElementById('submitBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
            btn.disabled = true;
            document.getElementById('loadingOverlay').classList.add('active');
        });

        // Loading overlay on navigation
        document.querySelectorAll('.nav-link').forEach(link => {
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

        // Character counter for deskripsi
        document.getElementById('deskripsi').addEventListener('input', function() {
            const maxLength = 500;
            const currentLength = this.value.length;
            
            if (currentLength > maxLength) {
                this.value = this.value.slice(0, maxLength);
            }
        });

        // Prevent double submission
        let formSubmitted = false;
        document.getElementById('tambahBukuForm').addEventListener('submit', function(e) {
            if (formSubmitted) {
                e.preventDefault();
                return;
            }
            formSubmitted = true;
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + S to submit form
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                document.getElementById('tambahBukuForm').submit();
            }
            // Escape to reset form
            if (e.key === 'Escape') {
                Swal.fire({
                    title: 'Reset Form?',
                    text: 'Semua data yang telah diisi akan dihapus',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#f56565',
                    cancelButtonColor: '#4a5568',
                    confirmButtonText: 'Ya, Reset!',
                    cancelButtonText: 'Batal',
                    background: '#1a1b3b',
                    color: '#fff'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('tambahBukuForm').reset();
                        removeFile();
                    }
                });
            }
        });
    </script>
</body>
</html>