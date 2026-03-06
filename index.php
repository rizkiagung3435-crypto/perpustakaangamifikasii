<?php
/**
 * Halaman Utama - Perpustakaan Digital Gamifikasi
 * SMK Mardi Yuana Cikembar
 */

session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] == 'admin') {
        header('Location: admin/dashboard_admin.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perpustakaan Digital Gamifikasi - SMK Mardi Yuana Cikembar</title>
    
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
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
            background: rgba(255, 255, 255, 0.1);
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

        .floating-book {
            position: absolute;
            font-size: 5rem;
            opacity: 0.1;
            animation: floatBook 20s linear infinite;
        }

        .floating-book:nth-child(3) { top: 10%; left: 5%; }
        .floating-book:nth-child(4) { top: 70%; left: 80%; animation-delay: 5s; }
        .floating-book:nth-child(5) { top: 40%; left: 90%; animation-delay: 10s; }

        @keyframes floatShape {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-40px) scale(1.05); }
        }

        @keyframes floatBook {
            0% { transform: translateY(0) rotate(0deg); opacity: 0.1; }
            50% { transform: translateY(-150px) rotate(15deg); opacity: 0.2; }
            100% { transform: translateY(0) rotate(0deg); opacity: 0.1; }
        }

        .container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        .hero-card {
            max-width: 1000px;
            width: 100%;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 50px;
            padding: 60px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
            animation: cardEntrance 0.8s ease;
            border: 1px solid rgba(255, 255, 255, 0.4);
        }

        @keyframes cardEntrance {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(50px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .hero-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            align-items: center;
        }

        .hero-text h1 {
            font-size: 48px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .hero-text p {
            color: #4a5568;
            font-size: 18px;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .cta-buttons {
            display: flex;
            gap: 20px;
        }

        .btn-primary, .btn-secondary {
            padding: 16px 35px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            border: 2px solid transparent;
        }

        .btn-primary:hover, .btn-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary:hover {
            background: rgba(102, 126, 234, 0.15);
        }

        .hero-image {
            text-align: center;
        }

        .hero-image img {
            max-width: 100%;
            animation: float 3s infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 50px;
        }

        .feature-item {
            text-align: center;
            padding: 25px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 30px;
            transition: all 0.3s;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .feature-item:hover {
            transform: translateY(-5px);
            background: rgba(102, 126, 234, 0.1);
            box-shadow: 0 20px 30px rgba(102, 126, 234, 0.2);
        }

        .feature-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .feature-title {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .feature-desc {
            font-size: 13px;
            color: #718096;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .hero-content {
                grid-template-columns: 1fr;
            }

            .hero-card {
                padding: 30px;
            }

            .hero-text h1 {
                font-size: 36px;
            }

            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .cta-buttons {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .features-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="animated-bg">
        <div class="bg-shape"></div>
        <div class="bg-shape"></div>
        <div class="floating-book">📚</div>
        <div class="floating-book">📖</div>
        <div class="floating-book">📕</div>
    </div>

    <div class="container">
        <div class="hero-card" data-aos="fade-up">
            <div class="hero-content">
                <div class="hero-text">
                    <h1>📚 Perpustakaan Digital Gamifikasi</h1>
                    <p>SMK Mardi Yuana Cikembar</p>
                    <p>Tingkatkan pengalaman membaca Anda dengan sistem poin, level, dan badge eksklusif!</p>
                    
                    <div class="cta-buttons">
                        <a href="login.php" class="btn-primary">
                            <i class="fas fa-sign-in-alt"></i>
                            Login
                        </a>
                        <a href="register.php" class="btn-secondary">
                            <i class="fas fa-user-plus"></i>
                            Daftar
                        </a>
                    </div>
                </div>
                
                <div class="hero-image">
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 200'%3E%3Ccircle cx='100' cy='100' r='80' fill='%23667eea' opacity='0.2'/%3E%3Ctext x='50' y='120' font-size='80'%3E📚%3C/text%3E%3C/svg%3E" alt="Library">
                </div>
            </div>

            <div class="features-grid">
                <div class="feature-item" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-icon">📖</div>
                    <h3 class="feature-title">1000+ Buku</h3>
                    <p class="feature-desc">Koleksi buku lengkap untuk semua jurusan</p>
                </div>
                <div class="feature-item" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-icon">⭐</div>
                    <h3 class="feature-title">Sistem XP</h3>
                    <p class="feature-desc">Dapatkan XP dari setiap aktivitas membaca</p>
                </div>
                <div class="feature-item" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-icon">🏆</div>
                    <h3 class="feature-title">Achievement</h3>
                    <p class="feature-desc">Kumpulkan badge keren dan naikkan level</p>
                </div>
                <div class="feature-item" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-icon">📊</div>
                    <h3 class="feature-title">Leaderboard</h3>
                    <p class="feature-desc">Bersaing dengan siswa lain untuk jadi yang terbaik</p>
                </div>
            </div>
        </div>
    </div>

    <!-- AOS Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            once: true,
            duration: 800,
            easing: 'ease-in-out'
        });
    </script>
</body>
</html>