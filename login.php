<?php
/**
 * Halaman Login - Perpustakaan Digital Gamifikasi
 * SMK Mardi Yuana Cikembar
 * Mendukung multi-user (Siswa & Admin)
 */

require_once 'config/koneksi.php';

$database = new Database();
$conn = $database->getConnection();

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] == 'admin') {
        header('Location: admin/dashboard_admin.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

// Messages from URL
$registered = isset($_GET['registered']) ? true : false;
$logout = isset($_GET['logout']) ? true : false;
$timeout = isset($_GET['timeout']) ? true : false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $database->escapeString($_POST['email']);
    $password = $_POST['password'];
    
    // Cek apakah kolom is_active ada
    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
    
    if ($check_column->num_rows > 0) {
        // Kolom is_active ada
        $query = "SELECT * FROM users WHERE email = ? AND is_active = 1";
    } else {
        // Kolom is_active belum ada
        $query = "SELECT * FROM users WHERE email = ?";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            // Update last login jika kolom ada
            if ($check_column->num_rows > 0) {
                $update_query = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();
            }
            
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nama'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            // Log aktivitas login jika tabel log_aktivitas ada
            $check_log_table = $conn->query("SHOW TABLES LIKE 'log_aktivitas'");
            if ($check_log_table->num_rows > 0) {
                $log_query = "INSERT INTO log_aktivitas (id_user, aktivitas, deskripsi, ip_address, user_agent) 
                              VALUES (?, 'login', 'User login', ?, ?)";
                $log_stmt = $conn->prepare($log_query);
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                $log_stmt->bind_param("iss", $user['id'], $ip, $ua);
                $log_stmt->execute();
            }
            
            // Redirect berdasarkan role
            if ($user['role'] == 'admin') {
                header('Location: admin/dashboard_admin.php');
            } else {
                header('Location: dashboard.php');
            }
            exit();
        } else {
            $error = "Password salah!";
        }
    } else {
        $error = "Email tidak ditemukan atau akun tidak aktif!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Perpustakaan Digital Gamifikasi</title>
    
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
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: rgba(255, 255, 255, 0.08);
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
            font-size: 4rem;
            opacity: 0.08;
            animation: floatBook 20s linear infinite;
        }

        .floating-book:nth-child(3) { top: 15%; left: 5%; }
        .floating-book:nth-child(4) { top: 75%; left: 85%; animation-delay: 5s; }
        .floating-book:nth-child(5) { top: 45%; left: 90%; animation-delay: 10s; }

        @keyframes floatShape {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-40px) scale(1.05); }
        }

        @keyframes floatBook {
            0% { transform: translateY(0) rotate(0deg); opacity: 0.08; }
            50% { transform: translateY(-150px) rotate(15deg); opacity: 0.15; }
            100% { transform: translateY(0) rotate(0deg); opacity: 0.08; }
        }

        /* Main Container */
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        /* Login Card */
        .login-card {
            max-width: 450px;
            width: 100%;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 40px;
            padding: 45px 40px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
            animation: cardEntrance 0.8s ease;
            border: 1px solid rgba(255, 255, 255, 0.4);
            transition: all 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 35px 70px rgba(0, 0, 0, 0.35);
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

        /* Header */
        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .logo-wrapper {
            position: relative;
            width: fit-content;
            margin: 0 auto 20px;
        }

        .logo-animation {
            width: 100px;
            height: 100px;
            background: linear-gradient(145deg, #667eea, #764ba2);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            animation: logoPulse 3s infinite;
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
            position: relative;
            z-index: 2;
        }

        .logo-ring {
            position: absolute;
            top: -5px;
            left: -5px;
            right: -5px;
            bottom: -5px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 35px;
            animation: ringPulse 2s infinite;
        }

        @keyframes logoPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes ringPulse {
            0% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.2; }
            100% { transform: scale(1); opacity: 0.5; }
        }

        .logo-animation span {
            font-size: 48px;
            color: white;
            animation: logoBounce 3s infinite;
        }

        @keyframes logoBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .login-header h1 {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(145deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .login-header p {
            color: #4a5568;
            font-size: 15px;
        }

        .school-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(145deg, #f093fb, #f5576c);
            color: white;
            padding: 10px 25px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 18px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideShake 0.5s ease;
            font-size: 14px;
            font-weight: 500;
            border-left: 4px solid transparent;
        }

        .alert-error {
            background: #fff5f5;
            border-left-color: #f56565;
            color: #c53030;
        }

        .alert-success {
            background: #f0fff4;
            border-left-color: #48bb78;
            color: #22543d;
        }

        .alert-info {
            background: #ebf8ff;
            border-left-color: #4299e1;
            color: #2c5282;
        }

        @keyframes slideShake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-8px); }
            40%, 80% { transform: translateX(8px); }
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2d3748;
            font-weight: 600;
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
            color: #a0aec0;
            font-size: 18px;
            transition: all 0.3s;
            z-index: 2;
        }

        .form-control {
            width: 100%;
            padding: 16px 18px 16px 55px;
            border: 2px solid #e2e8f0;
            border-radius: 22px;
            font-size: 15px;
            transition: all 0.3s;
            background: white;
            font-family: 'Poppins', sans-serif;
        }

        .form-control:hover {
            border-color: #a0aec0;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 5px rgba(102, 126, 234, 0.1);
        }

        .form-control:focus + i {
            color: #667eea;
            transform: translateY(-50%) scale(1.1);
        }

        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #a0aec0;
            font-size: 18px;
            transition: color 0.3s;
            z-index: 2;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        /* Form Options */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0 25px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: #4a5568;
            font-size: 14px;
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }

        .forgot-password {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s;
            position: relative;
        }

        .forgot-password::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: #667eea;
            transition: width 0.3s;
        }

        .forgot-password:hover::after {
            width: 100%;
        }

        /* Login Button */
        .btn-login {
            width: 100%;
            padding: 18px;
            background: linear-gradient(145deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-4px);
            box-shadow: 0 25px 40px rgba(102, 126, 234, 0.4);
        }

        .btn-login:active {
            transform: translateY(-2px);
        }

        .btn-login i {
            transition: transform 0.3s;
        }

        .btn-login:hover i {
            transform: translateX(8px);
        }

        /* Register Link */
        .register-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 2px solid rgba(226, 232, 240, 0.6);
        }

        .register-link p {
            color: #718096;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 25px;
            border-radius: 40px;
            background: rgba(102, 126, 234, 0.08);
        }

        .register-link a:hover {
            background: rgba(102, 126, 234, 0.15);
            transform: translateX(8px);
        }

        /* Demo Credentials */
        .demo-credentials {
            margin-top: 25px;
            padding: 15px;
            background: linear-gradient(145deg, rgba(102, 126, 234, 0.08), rgba(118, 75, 162, 0.08));
            border-radius: 20px;
            font-size: 12px;
            color: #4a5568;
            border: 1px dashed #667eea;
        }

        .demo-credentials p {
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
        }

        .demo-credentials .cred-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px 0;
        }

        .demo-credentials i {
            color: #667eea;
            font-size: 14px;
        }

        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
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
            border: 5px solid #e2e8f0;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loading-text {
            color: #2d3748;
            font-size: 18px;
            font-weight: 600;
            animation: pulse 1.5s infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 30px 20px;
            }

            .form-options {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .logo-animation {
                width: 80px;
                height: 80px;
            }

            .logo-animation span {
                font-size: 40px;
            }

            .login-header h1 {
                font-size: 26px;
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
        <div class="floating-book">📕</div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Memproses login...</div>
    </div>

    <!-- Main Container -->
    <div class="login-container">
        <div class="login-card" data-aos="fade-up">
            <!-- Header -->
            <div class="login-header">
                <div class="logo-wrapper">
                    <div class="logo-animation">
                        <span>📚</span>
                    </div>
                    <div class="logo-ring"></div>
                </div>
                <h1>Selamat Datang!</h1>
                <p>Silakan login untuk melanjutkan</p>
                <div class="school-badge">
                    <i class="fas fa-school"></i>
                    SMK Mardi Yuana Cikembar
                </div>
            </div>

            <!-- Messages -->
            <?php if ($registered): ?>
                <div class="alert alert-success" data-aos="fade-up">
                    <i class="fas fa-check-circle"></i>
                    <span>Pendaftaran berhasil! Silakan login dengan akun Anda.</span>
                </div>
            <?php endif; ?>

            <?php if ($logout): ?>
                <div class="alert alert-success" data-aos="fade-up">
                    <i class="fas fa-check-circle"></i>
                    <span>Anda telah berhasil logout.</span>
                </div>
            <?php endif; ?>

            <?php if ($timeout): ?>
                <div class="alert alert-info" data-aos="fade-up">
                    <i class="fas fa-clock"></i>
                    <span>Sesi Anda telah berakhir. Silakan login kembali.</span>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error" data-aos="fade-up">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="" id="loginForm">
                <div class="form-group" data-aos="fade-up" data-aos-delay="100">
                    <label for="email">Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               placeholder="Masukkan email Anda"
                               required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group" data-aos="fade-up" data-aos-delay="200">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Masukkan password Anda"
                               required>
                        <span class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </span>
                    </div>
                </div>

                <div class="form-options" data-aos="fade-up" data-aos-delay="300">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" id="remember">
                        <span>Ingat saya</span>
                    </label>
                    <a href="#" class="forgot-password">Lupa password?</a>
                </div>

                <button type="submit" class="btn-login" id="loginBtn" data-aos="fade-up" data-aos-delay="400">
                    <span>Login</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <!-- Register Link -->
            <div class="register-link" data-aos="fade-up" data-aos-delay="500">
                <p>Belum punya akun?</p>
                <a href="register.php">
                    <span>Daftar Sekarang</span>
                    <i class="fas fa-user-plus"></i>
                </a>
            </div>

            <!-- Demo Credentials -->
            <div class="demo-credentials" data-aos="fade-up" data-aos-delay="600">
                <p><i class="fas fa-info-circle"></i> Akun Demo:</p>
                <div class="cred-item">
                    <i class="fas fa-user-graduate"></i>
                    <span>Siswa: rizki.agung3435@guru.smk.belajar.id / password123</span>
                </div>
                <div class="cred-item">
                    <i class="fas fa-user-tie"></i>
                    <span>Admin: admin@perpus.sch.id / Admin123!</span>
                </div>
            </div>
        </div>
    </div>

    <!-- AOS Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Initialize AOS
        AOS.init({
            once: true,
            duration: 800,
            easing: 'ease-in-out'
        });

        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Form submission with validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Oops...',
                    text: 'Email dan password harus diisi!',
                    confirmButtonColor: '#667eea'
                });
                return;
            }
            
            const btn = document.getElementById('loginBtn');
            btn.innerHTML = '<span>Loading...</span> <i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;
            document.getElementById('loadingOverlay').classList.add('active');
        });

        // Remember me
        document.addEventListener('DOMContentLoaded', function() {
            const rememberCheckbox = document.getElementById('remember');
            const emailInput = document.getElementById('email');
            
            const savedEmail = localStorage.getItem('rememberedEmail');
            if (savedEmail) {
                emailInput.value = savedEmail;
                rememberCheckbox.checked = true;
            }
            
            document.getElementById('loginForm').addEventListener('submit', function() {
                if (rememberCheckbox.checked) {
                    localStorage.setItem('rememberedEmail', emailInput.value);
                } else {
                    localStorage.removeItem('rememberedEmail');
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
                shape.style.transform = `translate(${x}px, ${y}px) rotate(${x * 0.1}deg)`;
            });
        });

        // Auto-hide alerts
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

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + D untuk demo credentials
            if (e.ctrlKey && e.key.toLowerCase() === 'd') {
                e.preventDefault();
                document.getElementById('email').value = 'admin@perpus.sch.id';
                document.getElementById('password').value = 'Admin123!';
                Swal.fire({
                    icon: 'success',
                    title: 'Demo Credentials Loaded',
                    text: 'Akun admin demo telah diisi!',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });

        // Input focus effects
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });
    </script>
</body>
</html>
