<?php
/**
 * Halaman Detail Buku - Admin Perpustakaan Digital Gamifikasi
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

// Get borrowing history for this book
$query_history = "SELECT p.*, u.nama as nama_siswa, u.kelas 
                 FROM peminjaman p
                 JOIN users u ON p.id_user = u.id
                 WHERE p.id_buku = ?
                 ORDER BY p.tanggal_pinjam DESC
                 LIMIT 10";
$stmt = $conn->prepare($query_history);
$stmt->bind_param("i", $id_buku);
$stmt->execute();
$history = $stmt->get_result();

$page_title = "Detail Buku - Admin Perpustakaan";
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
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
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
            overflow: hidden;
        }

        .bg-shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
            filter: blur(40px);
            animation: floatShape 8s ease-in-out infinite;
        }

        .bg-shape:nth-child(1) {
            width: 500px;
            height: 500px;
            top: -250px;
            right: -250px;
            background: linear-gradient(135deg, #ff6b6b, #feca57);
        }

        .bg-shape:nth-child(2) {
            width: 400px;
            height: 400px;
            bottom: -200px;
            left: -200px;
            background: linear-gradient(135deg, #48dbfb, #1dd1a1);
            animation-delay: 2s;
        }

        @keyframes floatShape {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-40px) scale(1.05); }
        }

        /* Navigation */
        .navbar {
            background: rgba(26, 26, 46, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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
            color: #a0aec0;
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

        .admin-badge {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: #1a1a2e;
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-left: 10px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 20px;
            border-radius: 40px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }

        .user-info {
            line-height: 1.3;
        }

        .user-name {
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .user-role {
            color: #fbbf24;
            font-size: 11px;
            font-weight: 500;
        }

        /* Main Container */
        .main-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            position: relative;
            z-index: 1;
        }

        /* Detail Card */
        .detail-card {
            background: rgba(26, 26, 46, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.5s ease;
            margin-bottom: 30px;
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

        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .detail-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .detail-header h1 i {
            color: #667eea;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-edit, .btn-back {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-edit {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-edit:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-3px);
        }

        /* Book Info Grid */
        .book-info-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        .book-cover-large {
            width: 100%;
            height: 400px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .book-cover-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .book-cover-large .no-cover {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .book-cover-large .no-cover i {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .book-details {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .detail-group {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 15px;
            padding: 20px;
        }

        .detail-row {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .detail-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .detail-label {
            width: 150px;
            color: #a0aec0;
            font-size: 14px;
        }

        .detail-label i {
            width: 20px;
            color: #667eea;
            margin-right: 10px;
        }

        .detail-value {
            flex: 1;
            color: white;
            font-size: 15px;
            font-weight: 500;
        }

        .stock-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .stock-high {
            background: rgba(72, 187, 120, 0.2);
            color: #48bb78;
        }

        .stock-low {
            background: rgba(237, 137, 54, 0.2);
            color: #ed8936;
        }

        .stock-out {
            background: rgba(245, 101, 101, 0.2);
            color: #f56565;
        }

        .deskripsi-box {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 15px;
            padding: 20px;
            color: #e0e0e0;
            font-size: 14px;
            line-height: 1.8;
        }

        /* History Section */
        .history-section {
            background: rgba(26, 26, 46, 0.95);
            border-radius: 20px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .section-title i {
            color: #667eea;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px;
            color: #a0aec0;
            font-weight: 500;
            font-size: 13px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.2);
        }

        td {
            padding: 12px;
            color: white;
            font-size: 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        tr:hover td {
            background: rgba(102, 126, 234, 0.1);
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .status-dipinjam {
            background: rgba(237, 137, 54, 0.2);
            color: #ed8936;
        }

        .status-kembali {
            background: rgba(72, 187, 120, 0.2);
            color: #48bb78;
        }

        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(26, 26, 46, 0.9);
            backdrop-filter: blur(8px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            gap: 20px;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(255, 255, 255, 0.1);
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loading-text {
            color: white;
            font-size: 18px;
            font-weight: 600;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 992px) {
            .book-info-grid {
                grid-template-columns: 1fr;
            }

            .book-cover-large {
                max-width: 300px;
                margin: 0 auto;
            }
        }

        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
            }

            .detail-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .detail-row {
                flex-direction: column;
                gap: 5px;
            }

            .detail-label {
                width: 100%;
            }

            table {
                font-size: 12px;
            }

            td, th {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="animated-bg">
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
                    <span class="logo-text">Admin Panel</span>
                </a>
            </div>
            
            <ul class="nav-links">
                <li><a href="dashboard_admin.php" class="nav-link"><i class="fas fa-home"></i>Dashboard</a></li>
                <li><a href="tambah_buku.php" class="nav-link"><i class="fas fa-plus-circle"></i>Tambah Buku</a></li>
                <li><a href="data_buku.php" class="nav-link active"><i class="fas fa-book"></i>Data Buku</a></li>
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
                        <i class="fas fa-crown"></i>
                        Administrator
                    </div>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-shield-alt"></i> Admin
                </div>
            </div>
        </div>
    </nav>

    <main class="main-container">
        <!-- Detail Card -->
        <div class="detail-card" data-aos="fade-up">
            <div class="detail-header">
                <h1>
                    <i class="fas fa-info-circle"></i>
                    Detail Buku
                </h1>
                <div class="action-buttons">
                    <a href="edit_buku.php?id=<?php echo $book['id_buku']; ?>" class="btn-edit">
                        <i class="fas fa-edit"></i>
                        Edit Buku
                    </a>
                    <a href="data_buku.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i>
                        Kembali
                    </a>
                </div>
            </div>

            <div class="book-info-grid">
                <!-- Book Cover -->
                <div class="book-cover-large">
                    <?php if ($book['cover'] && file_exists("../assets/img/covers/" . $book['cover'])): ?>
                        <img src="../assets/img/covers/<?php echo $book['cover']; ?>" alt="Cover Buku">
                    <?php else: ?>
                        <div class="no-cover">
                            <i class="fas fa-book"></i>
                            <span>Tidak Ada Cover</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Book Details -->
                <div class="book-details">
                    <div class="detail-group">
                        <div class="detail-row">
                            <div class="detail-label">
                                <i class="fas fa-heading"></i>
                                Judul
                            </div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($book['judul']); ?>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">
                                <i class="fas fa-user"></i>
                                Penulis
                            </div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($book['penulis']); ?>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">
                                <i class="fas fa-tag"></i>
                                Kategori
                            </div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($book['kategori']); ?>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">
                                <i class="fas fa-boxes"></i>
                                Stok
                            </div>
                            <div class="detail-value">
                                <span class="stock-badge <?php 
                                    echo $book['stok'] > 5 ? 'stock-high' : ($book['stok'] > 0 ? 'stock-low' : 'stock-out');
                                ?>">
                                    <?php echo $book['stok']; ?> buku
                                </span>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">
                                <i class="fas fa-calendar"></i>
                                Tahun Terbit
                            </div>
                            <div class="detail-value">
                                <?php echo $book['tahun_terbit'] ?: '-'; ?>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">
                                <i class="fas fa-barcode"></i>
                                ISBN
                            </div>
                            <div class="detail-value">
                                <?php echo $book['isbn'] ?: '-'; ?>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">
                                <i class="fas fa-building"></i>
                                Penerbit
                            </div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($book['penerbit'] ?: '-'); ?>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">
                                <i class="fas fa-map-marker-alt"></i>
                                Lokasi Rak
                            </div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($book['lokasi_rak'] ?: '-'); ?>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">
                                <i class="fas fa-clock"></i>
                                Ditambahkan
                            </div>
                            <div class="detail-value">
                                <?php echo date('d/m/Y H:i', strtotime($book['created_at'])); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Deskripsi -->
                    <div class="deskripsi-box">
                        <strong style="display: block; margin-bottom: 10px; color: white;">
                            <i class="fas fa-align-left" style="color: #667eea; margin-right: 8px;"></i>
                            Deskripsi
                        </strong>
                        <?php echo $book['deskripsi'] ? nl2br(htmlspecialchars($book['deskripsi'])) : '<em style="color: #718096;">Tidak ada deskripsi</em>'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Riwayat Peminjaman -->
        <div class="history-section" data-aos="fade-up" data-aos-delay="100">
            <h2 class="section-title">
                <i class="fas fa-history"></i>
                Riwayat Peminjaman
            </h2>

            <?php if ($history && $history->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Siswa</th>
                                <th>Kelas</th>
                                <th>Tanggal Pinjam</th>
                                <th>Tanggal Kembali</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $history->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['nama_siswa']); ?></td>
                                <td><?php echo htmlspecialchars($row['kelas']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($row['tanggal_pinjam'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($row['tanggal_kembali'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $row['status']; ?>">
                                        <?php echo $row['status'] == 'dipinjam' ? 'Dipinjam' : 'Dikembalikan'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #a0aec0;">
                    <i class="fas fa-inbox" style="font-size: 40px; margin-bottom: 15px;"></i>
                    <p>Belum ada riwayat peminjaman untuk buku ini</p>
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

        // Loading overlay on navigation
        document.querySelectorAll('.nav-link, .btn-back, .btn-edit').forEach(link => {
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
                const speed = (index + 1) * 30;
                const x = (window.innerWidth - mouseX * speed) / 100;
                const y = (window.innerHeight - mouseY * speed) / 100;
                shape.style.transform = `translate(${x}px, ${y}px)`;
            });
        });
    </script>
</body>
</html>