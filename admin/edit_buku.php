<?php
/**
 * Halaman Edit Buku - Admin Perpustakaan Digital Gamifikasi
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

// Get book ID from URL
$id_buku = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_buku === 0) {
    header('Location: data_buku.php');
    exit();
}

// Get book data
$query = "SELECT * FROM buku WHERE id_buku = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_buku);
$stmt->execute();
$result = $stmt->get_result();
$book = $result->fetch_assoc();

if (!$book) {
    header('Location: data_buku.php');
    exit();
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
    $cover = $book['cover']; // Keep existing cover by default
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
                
                // Delete old cover if exists and not default
                if ($book['cover'] && file_exists($target_dir . $book['cover'])) {
                    unlink($target_dir . $book['cover']);
                }
                
                $cover = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
                $target_file = $target_dir . $cover;
                
                if (!move_uploaded_file($_FILES['cover']['tmp_name'], $target_file)) {
                    $error = "Gagal mengupload cover buku";
                    $cover = $book['cover']; // Revert to old cover on error
                }
            } else {
                $error = "Ukuran file maksimal 2MB!";
            }
        } else {
            $error = "Format file harus JPG, JPEG, PNG, atau GIF!";
        }
    }
    
    // Remove cover if requested
    if (isset($_POST['remove_cover']) && $_POST['remove_cover'] == '1') {
        if ($book['cover'] && file_exists("../assets/img/covers/" . $book['cover'])) {
            unlink("../assets/img/covers/" . $book['cover']);
        }
        $cover = null;
    }
    
    // Update buku
    $query = "UPDATE buku SET 
              judul = ?, 
              penulis = ?, 
              kategori = ?, 
              deskripsi = ?, 
              cover = ?, 
              tahun_terbit = ?, 
              isbn = ?, 
              penerbit = ?, 
              lokasi_rak = ?, 
              stok = ? 
              WHERE id_buku = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssissssi", 
        $judul, $penulis, $kategori, $deskripsi, $cover, 
        $tahun, $isbn, $penerbit, $lokasi_rak, $stok, $id_buku
    );
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Buku berhasil diperbarui!";
        header('Location: data_buku.php');
        exit();
    } else {
        $error = "Gagal memperbarui buku: " . $conn->error;
    }
}

// Get statistics for header
$total_books = $conn->query("SELECT COUNT(*) as total FROM buku")->fetch_assoc()['total'];
$available_books = $conn->query("SELECT COUNT(*) as total FROM buku WHERE stok > 0")->fetch_assoc()['total'];
$low_stock = $conn->query("SELECT COUNT(*) as total FROM buku WHERE stok <= 3 AND stok > 0")->fetch_assoc()['total'];
$out_of_stock = $conn->query("SELECT COUNT(*) as total FROM buku WHERE stok = 0")->fetch_assoc()['total'];

$page_title = "Edit Buku - Admin Perpustakaan";
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

        /* Form Card */
        .form-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 28px;
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.5s ease;
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
            font-size: 36px;
            font-weight: 800;
            color: white;
            margin-bottom: 10px;
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
            font-size: 40px;
        }

        .form-header p {
            color: #a0aec0;
            font-size: 16px;
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
            margin-bottom: 8px;
            color: #e0e0e0;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        .form-group label i {
            color: #667eea;
            margin-right: 8px;
            font-size: 14px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 16px;
            transition: all 0.3s;
            z-index: 1;
        }

        .form-control {
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
            color: #764ba2;
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
        }

        select.form-control option {
            background: #1a1b3b;
            color: white;
        }

        /* Current Cover */
        .current-cover {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-top: 20px;
            margin-bottom: 20px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .cover-preview {
            width: 100px;
            height: 140px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid #667eea;
            box-shadow: 0 10px 20px -5px rgba(102, 126, 234, 0.3);
        }

        .cover-info {
            flex: 1;
        }

        .cover-info p {
            color: white;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .cover-info p i {
            color: #48bb78;
            margin-right: 8px;
        }

        .cover-info small {
            color: #a0aec0;
            font-size: 13px;
            display: block;
            margin-bottom: 12px;
        }

        .btn-remove-cover {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: rgba(245, 101, 101, 0.15);
            border: 1px solid #f56565;
            border-radius: 40px;
            color: #f56565;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            letter-spacing: 0.3px;
        }

        .btn-remove-cover:hover {
            background: rgba(245, 101, 101, 0.3);
            transform: translateY(-2px);
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
            margin-top: 15px;
        }

        .file-upload:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
            transform: translateY(-2px);
        }

        .file-upload i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 15px;
        }

        .file-upload p {
            color: white;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .file-upload small {
            color: #a0aec0;
            font-size: 13px;
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
            border-radius: 12px;
            border: 3px solid #667eea;
            box-shadow: 0 15px 30px -8px rgba(102, 126, 234, 0.4);
        }

        .btn-remove-file {
            position: absolute;
            top: -10px;
            right: 50%;
            transform: translateX(75px);
            width: 36px;
            height: 36px;
            background: #f56565;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .btn-remove-file:hover {
            transform: translateX(75px) scale(1.1);
            background: #ef5350;
        }

        /* Button Group */
        .button-group {
            display: flex;
            gap: 16px;
            margin-top: 40px;
            flex-wrap: wrap;
        }

        .btn-primary, .btn-secondary, .btn-danger {
            padding: 16px 32px;
            border: none;
            border-radius: 18px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            flex: 1;
            min-width: 180px;
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

        .btn-primary i {
            transition: transform 0.3s;
        }

        .btn-primary:hover i {
            transform: translateX(5px);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-4px);
            border-color: rgba(102, 126, 234, 0.3);
        }

        .btn-secondary:hover i {
            transform: translateX(-5px);
        }

        .btn-danger {
            background: rgba(245, 101, 101, 0.15);
            color: #f56565;
            border: 1px solid #f56565;
        }

        .btn-danger:hover {
            background: rgba(245, 101, 101, 0.3);
            transform: translateY(-4px);
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

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: 1;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .form-card {
                padding: 30px 20px;
            }

            .form-header h1 {
                font-size: 28px;
            }

            .button-group {
                flex-direction: column;
            }

            .btn-primary, .btn-secondary, .btn-danger {
                width: 100%;
            }

            .current-cover {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .form-card {
                padding: 20px 15px;
            }

            .form-header h1 {
                font-size: 24px;
            }

            .form-control {
                padding: 14px 14px 14px 45px;
                font-size: 14px;
            }

            .file-upload {
                padding: 30px 15px;
            }

            .file-upload i {
                font-size: 40px;
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
                    <i class="fas fa-edit"></i>
                    Edit Buku
                </h1>
                <div class="page-badge">
                    <i class="fas fa-database"></i>
                    Total <?php echo $total_books; ?> Buku
                </div>
            </div>
            <a href="data_buku.php" class="btn-primary">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Data Buku
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

        <!-- Form Card -->
        <div class="form-card" data-aos="fade-up" data-aos-delay="200">
            <div class="form-header">
                <h1>
                    <i class="fas fa-book-open"></i>
                    Edit Informasi Buku
                </h1>
                <p>Edit data buku "<?php echo htmlspecialchars($book['judul']); ?>"</p>
            </div>

            <!-- Error Message -->
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" action="" enctype="multipart/form-data" id="editBukuForm">
                <div class="form-grid">
                    <!-- Judul Buku -->
                    <div class="form-group full-width">
                        <label for="judul">
                            <i class="fas fa-heading"></i>
                            Judul Buku <span style="color: #f56565;">*</span>
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-book"></i>
                            <input type="text" 
                                   class="form-control" 
                                   id="judul" 
                                   name="judul" 
                                   value="<?php echo htmlspecialchars($book['judul']); ?>"
                                   placeholder="Masukkan judul buku"
                                   required>
                        </div>
                    </div>

                    <!-- Penulis -->
                    <div class="form-group full-width">
                        <label for="penulis">
                            <i class="fas fa-pen-fancy"></i>
                            Penulis <span style="color: #f56565;">*</span>
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" 
                                   class="form-control" 
                                   id="penulis" 
                                   name="penulis" 
                                   value="<?php echo htmlspecialchars($book['penulis']); ?>"
                                   placeholder="Masukkan nama penulis"
                                   required>
                        </div>
                    </div>

                    <!-- Kategori -->
                    <div class="form-group">
                        <label for="kategori">
                            <i class="fas fa-tag"></i>
                            Kategori <span style="color: #f56565;">*</span>
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-list"></i>
                            <select class="form-control" id="kategori" name="kategori" required>
                                <option value="">Pilih Kategori</option>
                                <option value="Fiksi" <?php echo $book['kategori'] == 'Fiksi' ? 'selected' : ''; ?>>Fiksi</option>
                                <option value="Non-Fiksi" <?php echo $book['kategori'] == 'Non-Fiksi' ? 'selected' : ''; ?>>Non-Fiksi</option>
                                <option value="Pendidikan" <?php echo $book['kategori'] == 'Pendidikan' ? 'selected' : ''; ?>>Pendidikan</option>
                                <option value="Teknologi" <?php echo $book['kategori'] == 'Teknologi' ? 'selected' : ''; ?>>Teknologi</option>
                                <option value="Sejarah" <?php echo $book['kategori'] == 'Sejarah' ? 'selected' : ''; ?>>Sejarah</option>
                                <option value="Biografi" <?php echo $book['kategori'] == 'Biografi' ? 'selected' : ''; ?>>Biografi</option>
                                <option value="Komik" <?php echo $book['kategori'] == 'Komik' ? 'selected' : ''; ?>>Komik</option>
                                <option value="Majalah" <?php echo $book['kategori'] == 'Majalah' ? 'selected' : ''; ?>>Majalah</option>
                                <option value="Novel" <?php echo $book['kategori'] == 'Novel' ? 'selected' : ''; ?>>Novel</option>
                                <option value="Sains" <?php echo $book['kategori'] == 'Sains' ? 'selected' : ''; ?>>Sains</option>
                            </select>
                        </div>
                    </div>

                    <!-- Stok -->
                    <div class="form-group">
                        <label for="stok">
                            <i class="fas fa-boxes"></i>
                            Stok Buku <span style="color: #f56565;">*</span>
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-sort-numeric-up"></i>
                            <input type="number" 
                                   class="form-control" 
                                   id="stok" 
                                   name="stok" 
                                   min="0" 
                                   value="<?php echo $book['stok']; ?>" 
                                   placeholder="Masukkan jumlah stok"
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
                                   value="<?php echo $book['tahun_terbit']; ?>"
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
                                   value="<?php echo htmlspecialchars($book['isbn']); ?>"
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
                                   value="<?php echo htmlspecialchars($book['penerbit']); ?>"
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
                                   value="<?php echo htmlspecialchars($book['lokasi_rak']); ?>"
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
                                  placeholder="Masukkan deskripsi buku..."><?php echo htmlspecialchars($book['deskripsi']); ?></textarea>
                    </div>

                    <!-- Cover Buku -->
                    <div class="form-group full-width">
                        <label for="cover">
                            <i class="fas fa-image"></i>
                            Cover Buku
                        </label>
                        
                        <?php if ($book['cover']): ?>
                        <div class="current-cover">
                            <img src="../assets/img/covers/<?php echo $book['cover']; ?>" 
                                 alt="Current Cover" 
                                 class="cover-preview">
                            <div class="cover-info">
                                <p><i class="fas fa-check-circle"></i> Cover saat ini</p>
                                <small>Upload cover baru untuk mengganti file yang ada</small>
                                <button type="button" class="btn-remove-cover" onclick="removeCurrentCover()">
                                    <i class="fas fa-trash"></i>
                                    Hapus Cover
                                </button>
                                <input type="hidden" name="remove_cover" id="remove_cover" value="0">
                            </div>
                        </div>
                        <?php endif; ?>

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
                            <div class="btn-remove-file" onclick="removeFile()">
                                <i class="fas fa-times"></i>
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
                    <a href="data_buku.php" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Batal
                    </a>
                    <a href="#" class="btn-danger" onclick="return confirmDelete(<?php echo $book['id_buku']; ?>, '<?php echo htmlspecialchars($book['judul']); ?>')">
                        <i class="fas fa-trash"></i>
                        Hapus Buku
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

        function removeCurrentCover() {
            Swal.fire({
                title: 'Hapus Cover?',
                text: 'Cover buku akan dihapus permanen!',
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
                    document.getElementById('remove_cover').value = '1';
                    document.querySelector('.current-cover').style.opacity = '0.5';
                    document.querySelector('.current-cover').style.pointerEvents = 'none';
                    Swal.fire({
                        icon: 'success',
                        title: 'Cover akan dihapus',
                        text: 'Cover akan dihapus saat menyimpan perubahan',
                        confirmButtonColor: '#667eea',
                        background: '#1a1b3b',
                        color: '#fff'
                    });
                }
            });
        }

        // Form submission
        document.getElementById('editBukuForm').addEventListener('submit', function(e) {
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

        // Confirm delete
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
        document.querySelectorAll('.nav-link, .btn-secondary, .btn-primary').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!this.classList.contains('active') && !this.classList.contains('btn-danger')) {
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
        document.getElementById('editBukuForm').addEventListener('submit', function(e) {
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
                document.getElementById('editBukuForm').submit();
            }
            // Escape to cancel
            if (e.key === 'Escape') {
                window.location.href = 'data_buku.php';
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