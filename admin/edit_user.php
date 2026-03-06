<?php
/**
 * Halaman Edit User - Admin Perpustakaan Digital Gamifikasi
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

// Get target user ID from URL
$target_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($target_id === 0) {
    header('Location: data_user.php');
    exit();
}

// Get target user data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $target_id);
$stmt->execute();
$result = $stmt->get_result();
$target_user = $result->fetch_assoc();

if (!$target_user) {
    header('Location: data_user.php');
    exit();
}

// Get admin details if user is admin
$admin_details = null;
if ($target_user['role'] == 'admin') {
    $check_admin_table = $conn->query("SHOW TABLES LIKE 'admin_details'");
    if ($check_admin_table->num_rows > 0) {
        $query_details = "SELECT * FROM admin_details WHERE user_id = ?";
        $stmt_details = $conn->prepare($query_details);
        $stmt_details->bind_param("i", $target_id);
        $stmt_details->execute();
        $admin_details = $stmt_details->get_result()->fetch_assoc();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $database->escapeString($_POST['nama']);
    $email = $database->escapeString($_POST['email']);
    $role = $database->escapeString($_POST['role']);
    
    // Check if email already exists for another user
    $check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("si", $email, $target_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $error = "Email sudah digunakan oleh user lain!";
    } else {
        // Update user
        if ($role == 'siswa') {
            $kelas = $database->escapeString($_POST['kelas']);
            $jurusan = $database->escapeString($_POST['jurusan']);
            
            $query = "UPDATE users SET nama = ?, email = ?, kelas = ?, jurusan = ?, role = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssssi", $nama, $email, $kelas, $jurusan, $role, $target_id);
        } else {
            $query = "UPDATE users SET nama = ?, email = ?, kelas = NULL, jurusan = NULL, role = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssi", $nama, $email, $role, $target_id);
        }
        
        if ($stmt->execute()) {
            // Update admin details
            if ($role == 'admin' && $check_admin_table->num_rows > 0) {
                $nip = !empty($_POST['nip']) ? $database->escapeString($_POST['nip']) : null;
                $jabatan = !empty($_POST['jabatan']) ? $database->escapeString($_POST['jabatan']) : null;
                $no_telp = !empty($_POST['no_telp']) ? $database->escapeString($_POST['no_telp']) : null;
                $alamat = !empty($_POST['alamat']) ? $database->escapeString($_POST['alamat']) : null;
                $akses_level = $database->escapeString($_POST['akses_level'] ?? 'admin_perpus');
                
                if ($admin_details) {
                    // Update existing
                    $query_details = "UPDATE admin_details SET nip = ?, jabatan = ?, no_telp = ?, alamat = ?, akses_level = ? WHERE user_id = ?";
                    $stmt_details = $conn->prepare($query_details);
                    $stmt_details->bind_param("sssssi", $nip, $jabatan, $no_telp, $alamat, $akses_level, $target_id);
                } else {
                    // Insert new
                    $query_details = "INSERT INTO admin_details (user_id, nip, jabatan, no_telp, alamat, akses_level) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt_details = $conn->prepare($query_details);
                    $stmt_details->bind_param("isssss", $target_id, $nip, $jabatan, $no_telp, $alamat, $akses_level);
                }
                $stmt_details->execute();
            }
            
            $_SESSION['success_message'] = "User berhasil diperbarui!";
            header('Location: data_user.php');
            exit();
        } else {
            $error = "Gagal memperbarui user: " . $conn->error;
        }
    }
}

// Cek apakah kolom is_active ada
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
$has_is_active = $check_column->num_rows > 0;

$page_title = "Edit User - Admin Perpustakaan";
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
        /* [SAME STYLE AS tambah_user.php] */
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

        /* Admin Fields */
        .admin-fields {
            display: none;
            grid-column: span 2;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 24px;
            padding: 24px;
            margin-top: 10px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .admin-fields.visible {
            display: block;
        }

        .admin-fields h3 {
            color: white;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-fields h3 i {
            color: #fbbf24;
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

            .admin-fields {
                padding: 20px;
            }
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
        <div class="loading-text">Menyimpan perubahan...</div>
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
        <div class="form-card" data-aos="fade-up">
            <div class="form-header">
                <h1>
                    <i class="fas fa-edit"></i>
                    Edit User
                </h1>
                <p>Edit data user: <?php echo htmlspecialchars($target_user['nama']); ?></p>
            </div>

            <!-- Error Message -->
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" action="" id="editUserForm">
                <div class="form-grid">
                    <!-- Nama Lengkap -->
                    <div class="form-group full-width">
                        <label for="nama">
                            <i class="fas fa-user"></i>
                            Nama Lengkap <span>*</span>
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" 
                                   class="form-control" 
                                   id="nama" 
                                   name="nama" 
                                   value="<?php echo htmlspecialchars($target_user['nama']); ?>"
                                   required>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="form-group full-width">
                        <label for="email">
                            <i class="fas fa-envelope"></i>
                            Email <span>*</span>
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($target_user['email']); ?>"
                                   required>
                        </div>
                    </div>

                    <!-- Role -->
                    <div class="form-group">
                        <label for="role">
                            <i class="fas fa-user-tag"></i>
                            Role <span>*</span>
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-tag"></i>
                            <select class="form-control" id="role" name="role" required onchange="toggleRoleFields()">
                                <option value="siswa" <?php echo $target_user['role'] == 'siswa' ? 'selected' : ''; ?>>👨‍🎓 Siswa</option>
                                <option value="admin" <?php echo $target_user['role'] == 'admin' ? 'selected' : ''; ?>>👑 Admin</option>
                            </select>
                        </div>
                    </div>

                    <!-- Empty column for grid -->
                    <div></div>

                    <!-- Siswa Fields -->
                    <div id="siswaFields" style="<?php echo $target_user['role'] == 'siswa' ? 'display: block;' : 'display: none;'; ?>" class="full-width">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="kelas">
                                    <i class="fas fa-graduation-cap"></i>
                                    Kelas <span>*</span>
                                </label>
                                <div class="input-wrapper">
                                    <i class="fas fa-graduation-cap"></i>
                                    <input type="text" 
                                           class="form-control" 
                                           id="kelas" 
                                           name="kelas" 
                                           value="<?php echo htmlspecialchars($target_user['kelas'] ?? ''); ?>"
                                           <?php echo $target_user['role'] == 'siswa' ? 'required' : ''; ?>>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="jurusan">
                                    <i class="fas fa-code"></i>
                                    Jurusan <span>*</span>
                                </label>
                                <div class="input-wrapper">
                                    <i class="fas fa-code"></i>
                                    <select class="form-control" id="jurusan" name="jurusan" <?php echo $target_user['role'] == 'siswa' ? 'required' : ''; ?>>
                                        <option value="">Pilih Jurusan</option>
                                        <option value="Rekayasa Perangkat Lunak" <?php echo ($target_user['jurusan'] ?? '') == 'Rekayasa Perangkat Lunak' ? 'selected' : ''; ?>>Rekayasa Perangkat Lunak</option>
                                        <option value="DPB / Tata Busana" <?php echo ($target_user['jurusan'] ?? '') == 'DPB / Tata Busana' ? 'selected' : ''; ?>>DPB / Tata Busana</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Fields -->
                    <div id="adminFields" style="<?php echo $target_user['role'] == 'admin' ? 'display: block;' : 'display: none;'; ?>" class="admin-fields <?php echo $target_user['role'] == 'admin' ? 'visible' : ''; ?>">
                        <h3>
                            <i class="fas fa-crown"></i>
                            Data Admin (Opsional)
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nip">
                                    <i class="fas fa-id-card"></i>
                                    NIP
                                </label>
                                <div class="input-wrapper">
                                    <i class="fas fa-id-card"></i>
                                    <input type="text" 
                                           class="form-control" 
                                           id="nip" 
                                           name="nip" 
                                           value="<?php echo htmlspecialchars($admin_details['nip'] ?? ''); ?>"
                                           placeholder="Nomor Induk Pegawai">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="jabatan">
                                    <i class="fas fa-briefcase"></i>
                                    Jabatan
                                </label>
                                <div class="input-wrapper">
                                    <i class="fas fa-briefcase"></i>
                                    <input type="text" 
                                           class="form-control" 
                                           id="jabatan" 
                                           name="jabatan" 
                                           value="<?php echo htmlspecialchars($admin_details['jabatan'] ?? ''); ?>"
                                           placeholder="Contoh: Kepala Perpustakaan">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="no_telp">
                                    <i class="fas fa-phone"></i>
                                    No. Telepon
                                </label>
                                <div class="input-wrapper">
                                    <i class="fas fa-phone"></i>
                                    <input type="text" 
                                           class="form-control" 
                                           id="no_telp" 
                                           name="no_telp" 
                                           value="<?php echo htmlspecialchars($admin_details['no_telp'] ?? ''); ?>"
                                           placeholder="Contoh: 081234567890">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="akses_level">
                                    <i class="fas fa-shield-alt"></i>
                                    Akses Level
                                </label>
                                <div class="input-wrapper">
                                    <i class="fas fa-shield-alt"></i>
                                    <select class="form-control" id="akses_level" name="akses_level">
                                        <option value="admin_perpus" <?php echo ($admin_details['akses_level'] ?? '') == 'admin_perpus' ? 'selected' : ''; ?>>Admin Perpustakaan</option>
                                        <option value="super_admin" <?php echo ($admin_details['akses_level'] ?? '') == 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                                        <option value="admin_teknis" <?php echo ($admin_details['akses_level'] ?? '') == 'admin_teknis' ? 'selected' : ''; ?>>Admin Teknis</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group full-width">
                                <label for="alamat">
                                    <i class="fas fa-map-marker-alt"></i>
                                    Alamat
                                </label>
                                <div class="input-wrapper">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <textarea class="form-control" 
                                              id="alamat" 
                                              name="alamat" 
                                              rows="3" 
                                              placeholder="Masukkan alamat lengkap"><?php echo htmlspecialchars($admin_details['alamat'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Button Group -->
                <div class="button-group">
                    <button type="submit" class="btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i>
                        Simpan Perubahan
                    </button>
                    <a href="data_user.php" class="btn-secondary">
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

        // Toggle role fields
        function toggleRoleFields() {
            const role = document.getElementById('role').value;
            const siswaFields = document.getElementById('siswaFields');
            const adminFields = document.getElementById('adminFields');
            
            // Sembunyikan semua
            siswaFields.style.display = 'none';
            adminFields.style.display = 'none';
            adminFields.classList.remove('visible');
            
            // Tampilkan sesuai role
            if (role === 'siswa') {
                siswaFields.style.display = 'block';
                document.getElementById('kelas').required = true;
                document.getElementById('jurusan').required = true;
            } else if (role === 'admin') {
                adminFields.style.display = 'block';
                adminFields.classList.add('visible');
                document.getElementById('kelas').required = false;
                document.getElementById('jurusan').required = false;
            }
        }

        // Form submission with validation
        document.getElementById('editUserForm').addEventListener('submit', function(e) {
            const nama = document.getElementById('nama').value;
            const email = document.getElementById('email').value;
            const role = document.getElementById('role').value;

            if (!nama || !email || !role) {
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

            // Validasi email
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Email Tidak Valid',
                    text: 'Format email tidak valid!',
                    confirmButtonColor: '#667eea',
                    background: '#1a1b3b',
                    color: '#fff'
                });
                return;
            }

            // Validasi field siswa
            if (role === 'siswa') {
                const kelas = document.getElementById('kelas').value;
                const jurusan = document.getElementById('jurusan').value;
                
                if (!kelas || !jurusan) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Data Siswa Belum Lengkap',
                        text: 'Harap isi kelas dan jurusan!',
                        confirmButtonColor: '#667eea',
                        background: '#1a1b3b',
                        color: '#fff'
                    });
                    return;
                }
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
    </script>
</body>
</html>