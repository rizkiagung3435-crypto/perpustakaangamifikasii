<?php
/**
 * Dashboard Siswa - Perpustakaan Digital Gamifikasi
 * SMK Mardi Yuana Cikembar
 */

require_once 'config/koneksi.php';
require_once 'config/auth.php';

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

if (!$user) {
    header('Location: logout.php');
    exit();
}

// Handle book return
if (isset($_GET['return'])) {
    $id_peminjaman = (int)$_GET['return'];
    
    // Cari tahu nama kolom primary key di tabel peminjaman
    $pk_query = "SHOW KEYS FROM peminjaman WHERE Key_name = 'PRIMARY'";
    $pk_result = $conn->query($pk_query);
    $pk_column = 'id'; // default
    if ($pk_result && $pk_result->num_rows > 0) {
        $pk_data = $pk_result->fetch_assoc();
        $pk_column = $pk_data['Column_name'];
    } else {
        // Jika tidak ada primary key, coba cari kolom yang umum digunakan sebagai ID
        $columns_query = "SHOW COLUMNS FROM peminjaman";
        $columns_result = $conn->query($columns_query);
        if ($columns_result) {
            $common_id_columns = ['id_peminjaman', 'peminjaman_id', 'id', 'ID'];
            while ($column = $columns_result->fetch_assoc()) {
                if (in_array($column['Field'], $common_id_columns) || 
                    strpos($column['Field'], 'id') !== false || 
                    strpos($column['Field'], 'ID') !== false) {
                    $pk_column = $column['Field'];
                    break;
                }
            }
        }
    }
    
    // Check if the loan belongs to this user and is still borrowed
    $check_query = "SELECT * FROM peminjaman WHERE $pk_column = ? AND id_user = ? AND status = 'dipinjam'";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ii", $id_peminjaman, $user_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $loan = $check_result->fetch_assoc();
        
        // Update peminjaman status
        $tgl_kembali = date('Y-m-d');
        $update_query = "UPDATE peminjaman SET status = 'dikembalikan', tgl_kembali = ? WHERE $pk_column = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $tgl_kembali, $id_peminjaman);
        
        if ($stmt->execute()) {
            // Update stok buku
            $update_stok = "UPDATE buku SET stok = stok + 1 WHERE id_buku = ?";
            $stmt_stok = $conn->prepare($update_stok);
            $stmt_stok->bind_param("i", $loan['id_buku']);
            $stmt_stok->execute();
            
            // Add XP for returning book
            $xp_gained = 10;
            $update_xp = "UPDATE users SET xp = xp + ? WHERE id = ?";
            $stmt_xp = $conn->prepare($update_xp);
            $stmt_xp->bind_param("ii", $xp_gained, $user_id);
            $stmt_xp->execute();
            
            // Update level based on XP (Level = floor(xp/100) + 1)
            $level_query = "SELECT xp FROM users WHERE id = ?";
            $stmt_level = $conn->prepare($level_query);
            $stmt_level->bind_param("i", $user_id);
            $stmt_level->execute();
            $level_result = $stmt_level->get_result();
            $user_data = $level_result->fetch_assoc();
            
            $new_level = floor($user_data['xp'] / 100) + 1;
            $update_level = "UPDATE users SET level = ? WHERE id = ?";
            $stmt_level_update = $conn->prepare($update_level);
            $stmt_level_update->bind_param("ii", $new_level, $user_id);
            $stmt_level_update->execute();
            
            $_SESSION['success_message'] = "Buku berhasil dikembalikan! +$xp_gained XP";
        } else {
            $_SESSION['error_message'] = "Gagal mengembalikan buku. Silakan coba lagi.";
        }
    } else {
        $_SESSION['error_message'] = "Data peminjaman tidak valid.";
    }
    
    header('Location: dashboard.php');
    exit();
}

// Get messages from session
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

/**
 * Calculate level based on XP
 */
function calculateLevel($xp) {
    $level = floor($xp / 100) + 1;
    $level_names = [
        1 => 'Reader Beginner',
        2 => 'Book Explorer',
        3 => 'Knowledge Hunter',
        4 => 'Library Guardian',
        5 => 'Grand Scholar'
    ];
    
    $next_level_xp = $level * 100;
    $progress = ($xp / $next_level_xp) * 100;
    
    return [
        'level' => $level,
        'level_name' => $level_names[$level] ?? 'Master Reader',
        'next_level_xp' => $next_level_xp,
        'progress' => $progress
    ];
}

function getLevelName($level) {
    $level_names = [
        1 => 'Reader Beginner',
        2 => 'Book Explorer',
        3 => 'Knowledge Hunter',
        4 => 'Library Guardian',
        5 => 'Grand Scholar'
    ];
    return $level_names[$level] ?? 'Master Reader';
}

// Calculate level based on XP
$level_data = calculateLevel($user['xp']);
$level = $level_data['level'];
$level_name = $level_data['level_name'];
$next_level_xp = $level_data['next_level_xp'];
$xp_progress = $level_data['progress'];

// Cek struktur tabel peminjaman sekali saja dan cache hasilnya
$table_cache_file = 'cache/peminjaman_structure.cache';
$use_cache = false;
$cache_time = 3600; // 1 jam

if (file_exists($table_cache_file) && (time() - filemtime($table_cache_file) < $cache_time)) {
    $cached_data = file_get_contents($table_cache_file);
    $cache = unserialize($cached_data);
    $pk_column = $cache['pk_column'];
    $tanggal_pinjam_col = $cache['tanggal_pinjam_col'];
    $tanggal_kembali_col = $cache['tanggal_kembali_col'];
    $id_buku_col = $cache['id_buku_col'];
    $status_col = $cache['status_col'];
    $column_names = $cache['column_names'];
    $use_cache = true;
}

if (!$use_cache) {
    // Cek struktur tabel peminjaman
    $columns_query = "SHOW COLUMNS FROM peminjaman";
    $columns_result = $conn->query($columns_query);
    $columns = [];
    $column_names = [];
    if ($columns_result) {
        while ($column = $columns_result->fetch_assoc()) {
            $columns[] = $column;
            $column_names[] = $column['Field'];
        }
    }

    // Cari primary key atau kolom ID yang cocok
    $pk_column = '';
    foreach ($columns as $column) {
        if ($column['Key'] == 'PRI') {
            $pk_column = $column['Field'];
            break;
        }
    }

    // Jika tidak ada primary key, cari kolom yang umum digunakan sebagai ID
    if (empty($pk_column)) {
        $common_id_patterns = ['id_peminjaman', 'peminjaman_id', 'id', 'ID', 'Id', 'peminjamanId', 'loan_id'];
        foreach ($column_names as $col) {
            foreach ($common_id_patterns as $pattern) {
                if (strpos(strtolower($col), strtolower($pattern)) !== false) {
                    $pk_column = $col;
                    break 2;
                }
            }
        }
    }

    // Jika masih belum ditemukan, gunakan kolom pertama
    if (empty($pk_column) && !empty($column_names)) {
        $pk_column = $column_names[0];
    }

    // Tentukan nama kolom untuk tanggal
    $tanggal_pinjam_col = '';
    $tanggal_kembali_col = '';
    $id_buku_col = '';
    $status_col = '';

    foreach ($column_names as $col) {
        $col_lower = strtolower($col);
        
        // Cari kolom tanggal pinjam
        if (empty($tanggal_pinjam_col) && (strpos($col_lower, 'tgl_pinjam') !== false || 
            strpos($col_lower, 'tanggal_pinjam') !== false || 
            (strpos($col_lower, 'tgl') !== false && strpos($col_lower, 'pinjam') !== false))) {
            $tanggal_pinjam_col = $col;
        }
        
        // Cari kolom tanggal kembali
        if (empty($tanggal_kembali_col) && (strpos($col_lower, 'tgl_kembali') !== false || 
            strpos($col_lower, 'tanggal_kembali') !== false || 
            strpos($col_lower, 'tgl_harus_kembali') !== false || 
            strpos($col_lower, 'batas_waktu') !== false)) {
            $tanggal_kembali_col = $col;
        }
        
        // Cari kolom ID buku
        if (empty($id_buku_col) && (strpos($col_lower, 'id_buku') !== false || 
            strpos($col_lower, 'buku_id') !== false || 
            (strpos($col_lower, 'buku') !== false && strpos($col_lower, 'id') !== false))) {
            $id_buku_col = $col;
        }
        
        // Cari kolom status
        if (empty($status_col) && strpos($col_lower, 'status') !== false) {
            $status_col = $col;
        }
    }

    // Jika kolom tanggal pinjam tidak ditemukan, coba cari kolom date yang mungkin
    if (empty($tanggal_pinjam_col)) {
        foreach ($columns as $column) {
            if (strpos($column['Type'], 'date') !== false || strpos($column['Type'], 'timestamp') !== false) {
                if (empty($tanggal_pinjam_col)) {
                    $tanggal_pinjam_col = $column['Field'];
                } elseif (empty($tanggal_kembali_col) && $column['Field'] != $tanggal_pinjam_col) {
                    $tanggal_kembali_col = $column['Field'];
                }
            }
        }
    }

    // Jika masih belum ditemukan, gunakan default
    if (empty($tanggal_pinjam_col)) $tanggal_pinjam_col = 'tgl_pinjam';
    if (empty($tanggal_kembali_col)) $tanggal_kembali_col = 'tgl_harus_kembali';
    if (empty($id_buku_col)) $id_buku_col = 'id_buku';
    if (empty($status_col)) $status_col = 'status';

    // Simpan ke cache
    if (!is_dir('cache')) {
        mkdir('cache', 0777, true);
    }
    $cache_data = serialize([
        'pk_column' => $pk_column,
        'tanggal_pinjam_col' => $tanggal_pinjam_col,
        'tanggal_kembali_col' => $tanggal_kembali_col,
        'id_buku_col' => $id_buku_col,
        'status_col' => $status_col,
        'column_names' => $column_names
    ]);
    file_put_contents($table_cache_file, $cache_data);
}

// Get current loans (books being borrowed) dengan JOIN untuk efisiensi
$current_loans = [];
$borrowed_count = 0;

if (!empty($column_names)) { // Hanya jalankan jika tabel peminjaman ada
    // Gunakan JOIN untuk mengambil data buku sekaligus
    $query_current_loans = "SELECT p.*, b.judul, b.cover, b.penulis, b.kategori 
                            FROM peminjaman p 
                            LEFT JOIN buku b ON p.$id_buku_col = b.id_buku 
                            WHERE p.id_user = ? AND p.$status_col = 'dipinjam'";
    
    if (!empty($tanggal_kembali_col)) {
        $query_current_loans .= " ORDER BY p.$tanggal_kembali_col ASC";
    }
    
    $stmt = $conn->prepare($query_current_loans);
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $current_loans_result = $stmt->get_result();
        
        while ($row = $current_loans_result->fetch_assoc()) {
            // Hitung sisa hari
            $tgl_kembali = isset($row[$tanggal_kembali_col]) ? $row[$tanggal_kembali_col] : null;
            $tgl_pinjam = isset($row[$tanggal_pinjam_col]) ? $row[$tanggal_pinjam_col] : null;
            
            $row['lama_pinjam'] = $tgl_pinjam ? floor((time() - strtotime($tgl_pinjam)) / (60 * 60 * 24)) : 0;
            $row['sisa_hari'] = $tgl_kembali ? floor((strtotime($tgl_kembali) - time()) / (60 * 60 * 24)) : 7;
            $row['tanggal_pinjam_display'] = $tgl_pinjam ? date('d/m/Y', strtotime($tgl_pinjam)) : '-';
            $row['tanggal_kembali_display'] = $tgl_kembali ? date('d/m/Y', strtotime($tgl_kembali)) : '-';
            
            $current_loans[] = $row;
        }
    }
}
$borrowed_count = count($current_loans);

// Get reading stats dengan query yang lebih efisien
$books_read = 0;
if (!empty($column_names)) {
    $query_books_read = "SELECT COUNT(DISTINCT $id_buku_col) as total 
                        FROM peminjaman 
                        WHERE id_user = ? AND $status_col = 'dikembalikan'";
    $stmt = $conn->prepare($query_books_read);
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $books_read_result = $stmt->get_result();
        $books_read_data = $books_read_result->fetch_assoc();
        $books_read = $books_read_data['total'] ?? 0;
    }
}

// Get user rank dengan index yang lebih baik
$user_rank = 1;
$query_rank = "SELECT COUNT(*) + 1 as rank 
               FROM users 
               WHERE role = 'siswa' AND xp > (SELECT xp FROM users WHERE id = ?)";
$stmt = $conn->prepare($query_rank);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $rank_result = $stmt->get_result();
    if ($rank_result) {
        $rank_data = $rank_result->fetch_assoc();
        $user_rank = $rank_data['rank'] ?? 1;
    }
}

// Get recommended books dengan limit yang lebih kecil
$recommended_books = [];
$query_recommended = "SELECT * FROM buku 
                     WHERE stok > 0 
                     ORDER BY RAND() 
                     LIMIT 4";
$recommended_result = $conn->query($query_recommended);
if ($recommended_result) {
    while ($row = $recommended_result->fetch_assoc()) {
        $recommended_books[] = $row;
    }
}

// Set default values for other stats
$total_achievements = 0;
$completed_missions = 0;
$current_missions = [];
$xp_today = 0;

// Get avatar HTML
function getAvatar($avatar) {
    if ($avatar && $avatar != 'default-avatar.png' && file_exists("assets/img/avatars/" . $avatar)) {
        return "<img src='assets/img/avatars/" . htmlspecialchars($avatar) . "' alt='Avatar' class='avatar-img'>";
    }
    return "<div class='default-avatar'>" . strtoupper(substr($avatar ?? 'U', 0, 1)) . "</div>";
}

// Format date
function formatDate($date) {
    if (!$date) return '-';
    $months = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Ags', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];
    
    $timestamp = strtotime($date);
    $day = date('d', $timestamp);
    $month = $months[(int)date('m', $timestamp)];
    $year = date('Y', $timestamp);
    
    return "$day $month $year";
}

// Page title
$page_title = "Dashboard - Perpustakaan Digital Gamifikasi";
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
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
            position: relative;
            z-index: 1;
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
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .alert-success {
            border-left-color: #48bb78;
            color: #2f855a;
        }

        .alert-error {
            border-left-color: #f56565;
            color: #c53030;
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

        /* Welcome Section */
        .welcome-section {
            margin-bottom: 30px;
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

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .welcome-content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
        }

        .user-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: white;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
            animation: pulse 2s infinite;
        }

        .user-avatar:hover {
            transform: scale(1.05);
        }

        .user-avatar::after {
            content: '📷';
            position: absolute;
            bottom: -5px;
            right: -5px;
            background: linear-gradient(135deg, #f093fb, #f5576c);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            opacity: 0;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(245, 87, 108, 0.3);
        }

        .user-avatar:hover::after {
            opacity: 1;
            transform: scale(1);
        }

        .avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 30px;
        }

        .default-avatar {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            font-weight: 600;
            color: white;
        }

        .user-info h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .highlight {
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .wave-emoji {
            display: inline-block;
            animation: wave 2s infinite;
        }

        @keyframes wave {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(20deg); }
            75% { transform: rotate(-20deg); }
        }

        .user-detail {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .badge-class, .badge-jurusan {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 5px 15px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .separator {
            color: #ccc;
        }

        .user-stats-badges {
            display: flex;
            gap: 15px;
        }

        .rank-badge-small, .joined-badge {
            background: rgba(102, 126, 234, 0.1);
            padding: 5px 15px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 500;
            color: #667eea;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* Quick Stats */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .stat-icon {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            opacity: 0.7;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 20px;
            font-weight: 700;
        }

        .stat-item:hover .stat-value {
            color: white;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-card-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-card-content {
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
            font-size: 13px;
            color: #666;
        }

        .stat-card-trend {
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 20px;
        }

        .stat-card-trend.positive {
            background: rgba(72, 187, 120, 0.1);
            color: #48bb78;
        }

        .progress-mini {
            width: 60px;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
        }

        .progress-mini-bar {
            height: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 2px;
        }

        .btn-small {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-small:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* Current Loans Section */
        .loans-section {
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

        .title-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .view-all-link {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .view-all-link:hover {
            transform: translateX(5px);
        }

        .loans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .loan-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 20px;
            display: flex;
            gap: 20px;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .loan-card.overdue {
            border-left-color: #f56565;
            background: rgba(245, 101, 101, 0.05);
        }

        .loan-card.warning {
            border-left-color: #ed8936;
            background: rgba(237, 137, 54, 0.05);
        }

        .loan-card.normal {
            border-left-color: #48bb78;
        }

        .loan-cover {
            width: 80px;
            height: 100px;
            object-fit: cover;
            border-radius: 10px;
        }

        .loan-cover-placeholder {
            width: 80px;
            height: 100px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
        }

        .loan-info {
            flex: 1;
        }

        .loan-info h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }

        .loan-info p {
            font-size: 13px;
            color: #666;
            margin-bottom: 3px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .loan-info p i {
            width: 16px;
            color: #667eea;
        }

        .due-date {
            margin-top: 10px;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .due-date.overdue {
            background: rgba(245, 101, 101, 0.1);
            color: #f56565;
        }

        .due-date.warning {
            background: rgba(237, 137, 54, 0.1);
            color: #ed8936;
        }

        .due-date.normal {
            background: rgba(72, 187, 120, 0.1);
            color: #48bb78;
        }

        .btn-return {
            margin-top: 15px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-return:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        /* Level Section */
        .level-section {
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .level-progress-animation {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .level-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .level-info {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .level-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 8px 20px;
            border-radius: 30px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: white;
        }

        .level-number {
            font-weight: 700;
        }

        .level-name {
            opacity: 0.9;
            font-size: 14px;
        }

        .xp-info {
            background: rgba(102, 126, 234, 0.1);
            padding: 8px 20px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .xp-current {
            font-weight: 700;
            color: #667eea;
        }

        .xp-separator {
            color: #ccc;
        }

        .xp-next {
            color: #666;
        }

        .progress-container {
            width: 100%;
            height: 20px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 10px;
            overflow: hidden;
            margin: 15px 0;
            position: relative;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
            transition: width 0.5s ease;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
        }

        .progress-text {
            color: white;
            font-size: 12px;
            font-weight: 600;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }

        /* Badge Items */
        .badge-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .badge-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .badge-item:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            transform: translateX(10px);
            color: white;
        }

        .badge-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .badge-info {
            flex: 1;
        }

        .badge-name {
            font-weight: 600;
            margin-bottom: 3px;
        }

        .badge-date {
            font-size: 12px;
            opacity: 0.7;
        }

        .badge-item:hover .badge-date {
            color: rgba(255, 255, 255, 0.8);
        }

        /* Mission Items */
        .missions-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .mission-item {
            padding: 15px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 15px;
            transition: all 0.3s ease;
        }

        .mission-item:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            transform: translateY(-3px);
            color: white;
        }

        .mission-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .mission-title {
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .mission-xp {
            font-size: 13px;
            background: rgba(102, 126, 234, 0.2);
            padding: 3px 10px;
            border-radius: 15px;
            color: #667eea;
        }

        .mission-item:hover .mission-xp {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .mission-desc {
            font-size: 13px;
            opacity: 0.7;
            margin-bottom: 12px;
        }

        .mission-progress {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .progress-info {
            min-width: 60px;
            font-size: 13px;
        }

        .progress-current {
            font-weight: 700;
            color: #667eea;
        }

        .mission-item:hover .progress-current {
            color: white;
        }

        .progress-separator {
            opacity: 0.5;
        }

        .progress-target {
            opacity: 0.7;
        }

        .progress-container.small {
            flex: 1;
            height: 8px;
            background: rgba(102, 126, 234, 0.1);
        }

        /* Quick Actions */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .action-item {
            text-decoration: none;
            color: inherit;
            padding: 20px 10px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .action-item:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            transform: translateY(-5px);
        }

        .action-icon {
            font-size: 30px;
        }

        .action-label {
            font-size: 13px;
            font-weight: 500;
        }

        /* Books Grid */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .book-card {
            padding: 15px;
            transition: all 0.3s ease;
        }

        .book-cover-wrapper {
            position: relative;
            margin-bottom: 15px;
            border-radius: 15px;
            overflow: hidden;
        }

        .book-cover {
            width: 100%;
            height: 150px;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .book-card:hover .book-cover {
            transform: scale(1.1);
        }

        .stock-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stock-badge.available {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
        }

        .stock-badge.unavailable {
            background: linear-gradient(135deg, #f56565, #c53030);
            color: white;
        }

        .book-title {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }

        .book-author {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .book-meta {
            display: flex;
            gap: 10px;
            font-size: 11px;
            color: #999;
            margin-bottom: 15px;
        }

        .btn-borrow {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-borrow:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-borrow.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-icon {
            font-size: 50px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .empty-text {
            margin-bottom: 20px;
            color: #666;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #a0aec0, #718096);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(113, 128, 150, 0.4);
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
            padding: 5px 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 8px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        /* Achievement Modal */
        .achievement-unlock {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 30px;
            border-radius: 30px;
            text-align: center;
            color: white;
            animation: unlockPop 0.5s ease, unlockPulse 2s infinite;
            z-index: 1000;
            box-shadow: 0 20px 50px rgba(102, 126, 234, 0.5);
        }

        @keyframes unlockPop {
            0% { transform: translate(-50%, -50%) scale(0); }
            70% { transform: translate(-50%, -50%) scale(1.1); }
            100% { transform: translate(-50%, -50%) scale(1); }
        }

        @keyframes unlockPulse {
            0%, 100% { box-shadow: 0 0 30px rgba(102, 126, 234, 0.5); }
            50% { box-shadow: 0 0 60px rgba(102, 126, 234, 0.8); }
        }

        .achievement-icon {
            font-size: 60px;
            margin-bottom: 15px;
            animation: bounce 2s infinite;
        }

        /* Confetti */
        .confetti-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9999;
        }

        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            top: -10px;
            animation: confetti 3s ease infinite;
            z-index: 9999;
        }

        @keyframes confetti {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
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

        /* Responsive */
        @media (max-width: 1200px) {
            .books-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 992px) {
            .stats-grid,
            .quick-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .books-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .loans-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .stats-grid,
            .quick-stats {
                grid-template-columns: 1fr;
            }
            
            .actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .books-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-content {
                flex-direction: column;
                text-align: center;
            }
            
            .user-detail {
                justify-content: center;
            }
            
            .user-stats-badges {
                justify-content: center;
            }
            
            .level-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }
            
            .actions-grid {
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
        <div class="bg-shape"></div>
        <div class="bg-shape"></div>
        <div class="bg-shape"></div>
        <div class="floating-book">📚</div>
        <div class="floating-book">📖</div>
        <div class="floating-book">📕</div>
        <div class="floating-book">📗</div>
        <div class="floating-book">📘</div>
    </div>

    <!-- Loading Animation - DIHAPUS -->
    <!-- Loading overlay telah dihapus -->

    <!-- Confetti Container -->
    <div id="confetti-container" class="confetti-container" style="display: none;"></div>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="dashboard.php">
                    <div class="logo-icon">📚</div>
                    <span class="logo-text">Perpustakaan Digital</span>
                </a>
            </div>
            
            <button class="mobile-menu-toggle" id="mobileMenuToggle" style="display: none;">
                <span></span>
                <span></span>
                <span></span>
            </button>
            
            <ul class="nav-links" id="navLinks">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link active">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="siswa/katalog.php" class="nav-link">
                        <i class="fas fa-book"></i>
                        <span>Katalog</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="siswa/leaderboard.php" class="nav-link">
                        <i class="fas fa-trophy"></i>
                        <span>Leaderboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="siswa/misi.php" class="nav-link">
                        <i class="fas fa-tasks"></i>
                        <span>Misi</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="siswa/profil.php" class="nav-link">
                        <i class="fas fa-user"></i>
                        <span>Profil</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="nav-link">
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

    <main class="main-content">
        <div class="container">
            <!-- Welcome Section -->
            <section class="welcome-section glass-card fade-in floating">
                <div class="welcome-content">
                    <div class="user-avatar" onclick="showAvatarOptions()" data-tooltip="Klik untuk ganti avatar">
                        <?php echo getAvatar($user['avatar'] ?? ''); ?>
                    </div>
                    <div class="user-info">
                        <h1 class="welcome-title">
                            Selamat datang, <span class="highlight"><?php echo htmlspecialchars($user['nama']); ?></span>!
                            <span class="wave-emoji">👋</span>
                        </h1>
                        <p class="user-detail">
                            <span class="badge-class"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($user['kelas'] ?? '-'); ?></span>
                            <span class="separator">•</span>
                            <span class="badge-jurusan"><i class="fas fa-code"></i> <?php echo htmlspecialchars($user['jurusan'] ?? '-'); ?></span>
                        </p>
                        <div class="user-stats-badges">
                            <span class="rank-badge-small" data-tooltip="Peringkatmu">
                                <i class="fas fa-trophy"></i> #<?php echo number_format($user_rank); ?>
                            </span>
                            <span class="joined-badge" data-tooltip="Member since">
                                <i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($user['created_at'] ?? date('Y-m-d'))); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="quick-stats">
                    <div class="stat-item" data-tooltip="Total XP yang kamu kumpulkan">
                        <div class="stat-icon"><i class="fas fa-star"></i></div>
                        <div class="stat-label">XP</div>
                        <div class="stat-value"><?php echo number_format($user['xp'] ?? 0); ?></div>
                    </div>
                    <div class="stat-item" data-tooltip="Jumlah buku yang sudah dibaca">
                        <div class="stat-icon"><i class="fas fa-book-open"></i></div>
                        <div class="stat-label">Dibaca</div>
                        <div class="stat-value"><?php echo $books_read; ?></div>
                    </div>
                    <div class="stat-item" data-tooltip="Badge yang sudah diraih">
                        <div class="stat-icon"><i class="fas fa-medal"></i></div>
                        <div class="stat-label">Badges</div>
                        <div class="stat-value"><?php echo $total_achievements; ?></div>
                    </div>
                    <div class="stat-item" data-tooltip="Misi yang sudah diselesaikan">
                        <div class="stat-icon"><i class="fas fa-fire"></i></div>
                        <div class="stat-label">Misi</div>
                        <div class="stat-value"><?php echo $completed_missions; ?></div>
                    </div>
                </div>
            </section>

            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success fade-in">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error fade-in">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <section class="stats-grid">
                <div class="stat-card glass-card fade-in" style="animation-delay: 0.1s">
                    <div class="stat-card-icon"><i class="fas fa-star"></i></div>
                    <div class="stat-card-content">
                        <div class="stat-card-value"><?php echo number_format($user['xp'] ?? 0); ?></div>
                        <div class="stat-card-label">Total XP</div>
                    </div>
                    <div class="stat-card-trend positive">
                        <i class="fas fa-arrow-up"></i> +<?php echo $xp_today; ?> hari ini
                    </div>
                </div>

                <div class="stat-card glass-card fade-in" style="animation-delay: 0.2s">
                    <div class="stat-card-icon"><i class="fas fa-book-reader"></i></div>
                    <div class="stat-card-content">
                        <div class="stat-card-value"><?php echo $books_read; ?></div>
                        <div class="stat-card-label">Buku Dibaca</div>
                    </div>
                    <div class="stat-card-progress">
                        <div class="progress-mini" data-tooltip="<?php echo min(($books_read / 10) * 100, 100); ?>% dari target 10 buku">
                            <div class="progress-mini-bar" style="width: <?php echo min(($books_read / 10) * 100, 100); ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card glass-card fade-in" style="animation-delay: 0.3s">
                    <div class="stat-card-icon"><i class="fas fa-medal"></i></div>
                    <div class="stat-card-content">
                        <div class="stat-card-value"><?php echo $total_achievements; ?></div>
                        <div class="stat-card-label">Badges</div>
                    </div>
                </div>

                <div class="stat-card glass-card fade-in" style="animation-delay: 0.4s">
                    <div class="stat-card-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-card-content">
                        <div class="stat-card-value"><?php echo $borrowed_count; ?></div>
                        <div class="stat-card-label">Sedang Dipinjam</div>
                    </div>
                    <?php if ($borrowed_count > 0): ?>
                    <div class="stat-card-action">
                        <a href="#current-loans" class="btn-small">
                            <i class="fas fa-eye"></i> Lihat
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Current Loans Section -->
            <section id="current-loans" class="loans-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <span class="title-icon"><i class="fas fa-book-reader"></i></span>
                        Buku yang Sedang Dipinjam
                        <?php if ($borrowed_count > 0): ?>
                            <span class="badge-class" style="margin-left: 10px;"><?php echo $borrowed_count; ?> buku</span>
                        <?php endif; ?>
                    </h2>
                    <?php if ($borrowed_count > 0): ?>
                        <span class="view-all-link" data-tooltip="Kembalikan sebelum batas waktu">
                            <i class="fas fa-info-circle"></i>
                            Info Tenggat
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($current_loans)): ?>
                    <div class="loans-grid">
                        <?php foreach ($current_loans as $loan): 
                            $sisa_hari = $loan['sisa_hari'];
                            $overdue_class = 'normal';
                            if ($sisa_hari < 0) {
                                $overdue_class = 'overdue';
                            } elseif ($sisa_hari <= 2) {
                                $overdue_class = 'warning';
                            }
                            
                            // Dapatkan ID peminjaman
                            $loan_id = isset($loan[$pk_column]) ? $loan[$pk_column] : 0;
                        ?>
                        <div class="loan-card <?php echo $overdue_class; ?> fade-in">
                            <?php if (isset($loan['cover']) && $loan['cover'] && file_exists("assets/img/covers/" . $loan['cover'])): ?>
                                <img src="assets/img/covers/<?php echo $loan['cover']; ?>" alt="Cover" class="loan-cover">
                            <?php else: ?>
                                <div class="loan-cover-placeholder">
                                    <i class="fas fa-book"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="loan-info">
                                <h3><?php echo isset($loan['judul']) ? htmlspecialchars($loan['judul']) : 'Buku tidak ditemukan'; ?></h3>
                                <p><i class="fas fa-user"></i> <?php echo isset($loan['penulis']) ? htmlspecialchars($loan['penulis']) : '-'; ?></p>
                                <p><i class="fas fa-tag"></i> <?php echo isset($loan['kategori']) ? htmlspecialchars($loan['kategori']) : '-'; ?></p>
                                <p><i class="fas fa-calendar"></i> Dipinjam: <?php echo $loan['tanggal_pinjam_display']; ?></p>
                                
                                <?php
                                $due_class = 'normal';
                                $due_text = 'Tenggat: ' . $loan['tanggal_kembali_display'];
                                if ($sisa_hari < 0) {
                                    $due_class = 'overdue';
                                    $due_text = 'Terlambat ' . abs($sisa_hari) . ' hari';
                                } elseif ($sisa_hari == 0) {
                                    $due_class = 'warning';
                                    $due_text = 'Harus dikembalikan hari ini!';
                                } elseif ($sisa_hari <= 2) {
                                    $due_class = 'warning';
                                    $due_text = 'Sisa ' . $sisa_hari . ' hari lagi';
                                }
                                ?>
                                
                                <div class="due-date <?php echo $due_class; ?>">
                                    <i class="fas fa-hourglass-half"></i>
                                    <?php echo $due_text; ?>
                                </div>
                                
                                <a href="?return=<?php echo $loan_id; ?>" 
                                   class="btn-return" 
                                   onclick="return confirmReturn('<?php echo isset($loan['judul']) ? htmlspecialchars($loan['judul']) : 'Buku'; ?>')">
                                    <i class="fas fa-undo-alt"></i>
                                    Kembalikan Buku
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="glass-card empty-state">
                        <div class="empty-icon"><i class="fas fa-book-open"></i></div>
                        <h3 class="empty-text">Belum Ada Buku yang Dipinjam</h3>
                        <p style="color: #666; margin-bottom: 20px;">Yuk, pinjam buku pertama Anda!</p>
                        <a href="siswa/katalog.php" class="btn-secondary">
                            <i class="fas fa-search"></i> Jelajahi Katalog
                        </a>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Level Progress -->
            <section class="level-section glass-card fade-in">
                <div class="level-progress-animation"></div>
                <div class="level-header">
                    <div class="level-info">
                        <h2 class="section-title">
                            <span class="title-icon"><i class="fas fa-chart-line"></i></span>
                            Level Progress
                        </h2>
                        <div class="level-badge">
                            <span class="level-number"><i class="fas fa-crown"></i> Level <?php echo $level; ?></span>
                            <span class="level-name"><?php echo $level_name; ?></span>
                        </div>
                    </div>
                    <div class="xp-info">
                        <span class="xp-current"><?php echo number_format($user['xp'] ?? 0); ?> XP</span>
                        <span class="xp-separator">/</span>
                        <span class="xp-next"><?php echo number_format($next_level_xp); ?> XP</span>
                    </div>
                </div>
                
                <div class="progress-container">
                    <div class="progress-bar xp-progress" style="width: <?php echo $xp_progress; ?>%;">
                        <span class="progress-text"><?php echo round($xp_progress); ?>%</span>
                    </div>
                </div>
                
                <?php if ($level < 5): ?>
                <div class="next-level-preview" style="margin-top: 15px; padding: 10px; background: rgba(102, 126, 234, 0.05); border-radius: 10px; font-size: 14px;">
                    <i class="fas fa-gift" style="color: #667eea; margin-right: 10px;"></i>
                    <span style="color: #666;">Reward level selanjutnya:</span>
                    <span style="font-weight: 600; color: #667eea; margin-left: 5px;"><?php echo getLevelName($level + 1); ?></span>
                    <span style="color: #999; margin-left: 5px;">(Butuh <?php echo number_format($next_level_xp - ($user['xp'] ?? 0)); ?> XP lagi)</span>
                </div>
                <?php endif; ?>
            </section>

            <!-- Main Content Grid -->
            <div class="content-grid">
                <!-- Recent Achievements -->
                <section class="achievements-section glass-card fade-in">
                    <div class="section-header">
                        <h2 class="section-title">
                            <span class="title-icon"><i class="fas fa-trophy"></i></span>
                            Recent Badges
                        </h2>
                        <a href="#" class="view-all-link">
                            Lihat semua <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    
                    <div class="badge-container">
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-trophy"></i></div>
                            <p class="empty-text">Belum ada badge. Ayo pinjam buku untuk mendapatkan badge!</p>
                            <a href="siswa/katalog.php" class="btn-secondary">
                                <i class="fas fa-search"></i> Jelajahi Buku
                            </a>
                        </div>
                    </div>
                </section>

                <!-- Current Missions -->
                <section class="missions-section glass-card fade-in">
                    <div class="section-header">
                        <h2 class="section-title">
                            <span class="title-icon"><i class="fas fa-tasks"></i></span>
                            Misi Aktif
                        </h2>
                        <a href="siswa/misi.php" class="view-all-link">
                            Lihat semua <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    
                    <div class="missions-list">
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-tasks"></i></div>
                            <p class="empty-text">Belum ada misi aktif. Cek halaman misi untuk memulai!</p>
                            <a href="siswa/misi.php" class="btn-secondary">
                                <i class="fas fa-eye"></i> Lihat Misi
                            </a>
                        </div>
                    </div>
                </section>

                <!-- Quick Actions -->
                <section class="quick-actions-section glass-card fade-in">
                    <h2 class="section-title">
                        <span class="title-icon"><i class="fas fa-bolt"></i></span>
                        Quick Actions
                    </h2>
                    
                    <div class="actions-grid">
                        <a href="siswa/katalog.php" class="action-item">
                            <div class="action-icon"><i class="fas fa-book"></i></div>
                            <div class="action-label">Cari Buku</div>
                        </a>
                        
                        <a href="siswa/riwayat.php" class="action-item">
                            <div class="action-icon"><i class="fas fa-history"></i></div>
                            <div class="action-label">Riwayat</div>
                        </a>
                        
                        <a href="siswa/leaderboard.php" class="action-item">
                            <div class="action-icon"><i class="fas fa-trophy"></i></div>
                            <div class="action-label">Peringkat</div>
                        </a>
                        
                        <a href="siswa/misi.php" class="action-item">
                            <div class="action-icon"><i class="fas fa-tasks"></i></div>
                            <div class="action-label">Misi</div>
                        </a>
                        
                        <a href="siswa/profil.php" class="action-item">
                            <div class="action-icon"><i class="fas fa-user"></i></div>
                            <div class="action-label">Profil</div>
                        </a>
                        
                        <a href="#" class="action-item">
                            <div class="action-icon"><i class="fas fa-medal"></i></div>
                            <div class="action-label">Badges</div>
                        </a>
                    </div>
                </section>
            </div>

            <!-- Recommended Books -->
            <section class="recommended-section glass-card fade-in">
                <div class="section-header">
                    <h2 class="section-title">
                        <span class="title-icon"><i class="fas fa-bookmark"></i></span>
                        Rekomendasi Untukmu
                    </h2>
                    <a href="siswa/katalog.php" class="view-all-link">
                        Lihat semua buku <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                
                <div class="books-grid">
                    <?php if (!empty($recommended_books)): ?>
                        <?php foreach ($recommended_books as $book): ?>
                            <div class="book-card glass-card">
                                <div class="book-cover-wrapper">
                                    <img src="assets/img/covers/<?php echo $book['cover'] ?: 'default-book.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($book['judul']); ?>" 
                                         class="book-cover"
                                         loading="lazy">
                                    <?php if (($book['stok'] ?? 0) > 0): ?>
                                        <span class="stock-badge available">
                                            <i class="fas fa-check-circle"></i> Tersedia
                                        </span>
                                    <?php else: ?>
                                        <span class="stock-badge unavailable">
                                            <i class="fas fa-times-circle"></i> Habis
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="book-info">
                                    <h3 class="book-title"><?php echo htmlspecialchars($book['judul']); ?></h3>
                                    <p class="book-author">
                                        <i class="fas fa-pen-fancy"></i> <?php echo htmlspecialchars($book['penulis']); ?>
                                    </p>
                                    <div class="book-meta">
                                        <span class="book-category">
                                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($book['kategori'] ?? 'Umum'); ?>
                                        </span>
                                        <span class="book-year">
                                            <i class="far fa-calendar"></i> <?php echo $book['tahun_terbit'] ?? '-'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="book-actions">
                                    <?php if (($book['stok'] ?? 0) > 0): ?>
                                        <a href="siswa/pinjam.php?id=<?php echo $book['id_buku']; ?>" 
                                           class="btn-borrow"
                                           onclick="return confirmBorrow('<?php echo htmlspecialchars($book['judul']); ?>')">
                                            <i class="fas fa-book-open"></i>
                                            Pinjam Buku
                                        </a>
                                    <?php else: ?>
                                        <button class="btn-borrow disabled" disabled>
                                            <i class="fas fa-clock"></i>
                                            Stok Habis
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state full-width">
                            <div class="empty-icon"><i class="fas fa-books"></i></div>
                            <p class="empty-text">Belum ada buku tersedia.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <!-- Achievement Unlock Modal -->
    <div id="achievementModal" class="achievement-unlock" style="display: none;">
        <div class="achievement-icon">🏆</div>
        <h3 style="margin-bottom: 10px;">Achievement Unlocked!</h3>
        <p id="achievementName" style="margin-bottom: 5px;">First Book</p>
        <p class="achievement-xp" style="font-size: 14px;">+50 XP</p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initDashboard();
            initTooltips();
            initAnimations();
        });
        
        function initDashboard() {
            const cards = document.querySelectorAll('.stat-card, .book-card');
            cards.forEach((card, index) => {
                card.classList.add('floating');
                card.style.animationDelay = `${index * 0.2}s`;
            });
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        animateNumber(entry.target);
                    }
                });
            });
            
            document.querySelectorAll('.stat-card-value').forEach(el => {
                observer.observe(el);
            });
        }
        
        function animateNumber(element) {
            const target = parseInt(element.textContent.replace(/,/g, ''));
            if (isNaN(target)) return;
            
            let current = 0;
            const increment = target / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    element.textContent = target.toLocaleString();
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(current).toLocaleString();
                }
            }, 20);
        }
        
        function initTooltips() {
            const tooltips = document.querySelectorAll('[data-tooltip]');
            tooltips.forEach(el => {
                el.addEventListener('mouseenter', (e) => {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'tooltip';
                    tooltip.textContent = e.target.dataset.tooltip;
                    document.body.appendChild(tooltip);
                    
                    const rect = e.target.getBoundingClientRect();
                    tooltip.style.top = rect.top - 30 + 'px';
                    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                    
                    setTimeout(() => tooltip.remove(), 2000);
                });
            });
        }
        
        function initAnimations() {
            document.querySelectorAll('.btn-return, .btn-borrow').forEach(btn => {
                btn.addEventListener('mouseenter', () => {
                    btn.style.transform = 'scale(1.05)';
                });
                btn.addEventListener('mouseleave', () => {
                    btn.style.transform = 'scale(1)';
                });
            });
            
            document.querySelectorAll('.book-card').forEach(card => {
                card.addEventListener('click', (e) => {
                    if (!e.target.closest('a')) {
                        card.style.transform = 'scale(0.98)';
                        setTimeout(() => {
                            card.style.transform = 'scale(1)';
                        }, 200);
                    }
                });
            });
        }
        
        function showAchievementUnlock() {
            const modal = document.getElementById('achievementModal');
            modal.style.display = 'block';
            
            // Add confetti
            const container = document.getElementById('confetti-container');
            container.style.display = 'block';
            for (let i = 0; i < 50; i++) {
                createConfetti();
            }
            
            setTimeout(() => {
                modal.style.display = 'none';
                container.style.display = 'none';
                container.innerHTML = '';
            }, 3000);
        }
        
        function createConfetti() {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.animationDelay = Math.random() * 2 + 's';
            confetti.style.background = `linear-gradient(135deg, ${getRandomColor()}, ${getRandomColor()})`;
            document.getElementById('confetti-container').appendChild(confetti);
        }
        
        function getRandomColor() {
            const colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#48dbfb', '#1dd1a1'];
            return colors[Math.floor(Math.random() * colors.length)];
        }
        
        // Confirm return function
        function confirmReturn(judul) {
            Swal.fire({
                title: 'Kembalikan Buku?',
                html: `Apakah Anda yakin ingin mengembalikan buku <strong>"${judul}"</strong>?<br><br>Anda akan mendapatkan <strong>10 XP</strong> sebagai reward!`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#667eea',
                cancelButtonColor: '#a0aec0',
                confirmButtonText: 'Ya, Kembalikan!',
                cancelButtonText: 'Batal',
                background: '#fff',
                backdrop: 'rgba(102, 126, 234, 0.1)'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Loading akan ditangani oleh browser secara alami
                    return true;
                }
                return false;
            });
            return false;
        }
        
        // Confirm borrow function
        function confirmBorrow(judul) {
            Swal.fire({
                title: 'Pinjam Buku?',
                html: `Apakah Anda yakin ingin meminjam buku <strong>"${judul}"</strong>?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#667eea',
                cancelButtonColor: '#a0aec0',
                confirmButtonText: 'Ya, Pinjam!',
                cancelButtonText: 'Batal',
                background: '#fff',
                backdrop: 'rgba(102, 126, 234, 0.1)'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Loading akan ditangani oleh browser secara alami
                    return true;
                }
                return false;
            });
            return false;
        }
        
        window.showAvatarOptions = function() {
            Swal.fire({
                title: 'Ganti Avatar',
                text: 'Fitur ganti avatar akan segera hadir!',
                icon: 'info',
                confirmButtonColor: '#667eea',
                background: '#fff'
            });
        };
        
        // Dark mode toggle
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                document.body.classList.toggle('dark-mode');
                const icon = this.querySelector('i');
                if (document.body.classList.contains('dark-mode')) {
                    icon.className = 'fas fa-sun';
                } else {
                    icon.className = 'fas fa-moon';
                }
                
                this.style.transform = 'rotate(180deg)';
                setTimeout(() => {
                    this.style.transform = 'rotate(0)';
                }, 300);
            });
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'b' || e.key === 'B') {
                window.location.href = 'siswa/katalog.php';
            }
            if (e.key === 'l' || e.key === 'L') {
                window.location.href = 'siswa/leaderboard.php';
            }
            if (e.key === 'm' || e.key === 'M') {
                window.location.href = 'siswa/misi.php';
            }
        });
        
        // Parallax effect
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const parallax = document.querySelector('.welcome-section');
            if (parallax) {
                parallax.style.transform = `translateY(${scrolled * 0.1}px)`;
            }
        });
        
        // Mouse move parallax
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

        // Auto-hide alerts after 5 seconds
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
    </script>
</body>
</html>
