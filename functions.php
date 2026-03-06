<?php
/**
 * File: includes/functions.php
 * Fungsi-fungsi bantuan untuk aplikasi perpustakaan digital
 */

/**
 * Mendapatkan data user berdasarkan ID
 */
function getUserData($conn, $user_id) {
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Menghitung level berdasarkan XP
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

/**
 * Mendapatkan nama level
 */
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

/**
 * Mendapatkan achievements user
 */
function getUserAchievements($conn, $user_id) {
    $query = "SELECT a.*, ua.tanggal_dapat 
              FROM achievement a 
              JOIN user_achievement ua ON a.id = ua.id_achievement 
              WHERE ua.id_user = ?
              ORDER BY ua.tanggal_dapat DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $achievements = [];
    $total = 0;
    
    while ($row = $result->fetch_assoc()) {
        $achievements[] = $row;
        $total++;
    }
    
    return [
        'total' => $total,
        'list' => $achievements
    ];
}

/**
 * Mendapatkan jumlah buku yang sedang dipinjam
 */
function getBorrowedBooksCount($conn, $user_id) {
    $query = "SELECT COUNT(*) as total FROM peminjaman 
              WHERE id_user = ? AND status = 'dipinjam'";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    return $data['total'] ?? 0;
}

/**
 * Mendapatkan jumlah misi yang sudah selesai
 */
function getCompletedMissionsCount($conn, $user_id) {
    $query = "SELECT COUNT(*) as total FROM user_misi 
              WHERE id_user = ? AND status = 'selesai'";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    return $data['total'] ?? 0;
}

/**
 * Mendapatkan jumlah buku yang sudah dibaca
 */
function getBooksReadCount($conn, $user_id) {
    $query = "SELECT COUNT(DISTINCT id_buku) as total 
              FROM peminjaman 
              WHERE id_user = ? AND status = 'kembali'";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    return $data['total'] ?? 0;
}

/**
 * Mendapatkan misi yang sedang berjalan
 */
function getCurrentMissions($conn, $user_id, $limit = 3) {
    $query = "SELECT m.*, COALESCE(um.progress, 0) as progress 
              FROM misi m 
              LEFT JOIN user_misi um ON m.id_misi = um.id_misi AND um.id_user = ?
              WHERE um.status = 'berlangsung' OR um.id_misi IS NULL
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $missions = [];
    while ($row = $result->fetch_assoc()) {
        $missions[] = $row;
    }
    
    return $missions;
}

/**
 * Mendapatkan rekomendasi buku
 */
function getRecommendedBooks($conn, $limit = 4) {
    $query = "SELECT * FROM buku 
              WHERE stok > 0 
              ORDER BY RAND() 
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
    
    return $books;
}

/**
 * Mendapatkan peringkat user
 */
function getUserRank($conn, $user_id) {
    $query = "SELECT COUNT(*) + 1 as rank 
              FROM users 
              WHERE role = 'siswa' AND xp > (SELECT xp FROM users WHERE id = ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    return $data['rank'] ?? 1;
}

/**
 * Mendapatkan avatar user
 */
function getAvatar($avatar) {
    if ($avatar && file_exists("assets/img/avatars/" . $avatar)) {
        return "<img src='assets/img/avatars/" . htmlspecialchars($avatar) . "' alt='Avatar' class='avatar-img'>";
    }
    return "<div class='default-avatar'>" . strtoupper(substr($avatar ?? 'U', 0, 1)) . "</div>";
}

/**
 * Format tanggal
 */
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
?>