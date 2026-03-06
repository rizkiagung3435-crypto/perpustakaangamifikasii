<?php
/**
 * Halaman Register - Perpustakaan Digital Gamifikasi
 * SMK Mardi Yuana Cikembar
 * Mendukung multi-user (Siswa & Admin) tanpa kode rahasia
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

// Determine which registration form to show
$show_admin_form = isset($_GET['type']) && $_GET['type'] == 'admin';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $database->escapeString($_POST['nama']);
    $email = $database->escapeString($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = isset($_POST['role']) ? $database->escapeString($_POST['role']) : 'siswa';
    
    // Data for students
    if ($role == 'siswa') {
        $kelas = $database->escapeString($_POST['kelas']);
        $jurusan = $database->escapeString($_POST['jurusan']);
    } else {
        // Admin doesn't need kelas and jurusan
        $kelas = null;
        $jurusan = null;
    }
    
    // Check if email already exists
    $check_query = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $check_result = $stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error = "Email sudah terdaftar!";
    } else {
        // Insert user
        $query = "INSERT INTO users (nama, email, kelas, jurusan, password, role, avatar, xp, level) 
                  VALUES (?, ?, ?, ?, ?, ?, 'default-avatar.png', 0, 1)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssss", $nama, $email, $kelas, $jurusan, $password, $role);
        
        if ($stmt->execute()) {
            $_SESSION['register_success'] = true;
            if ($role == 'admin') {
                header('Location: login.php?registered=admin');
            } else {
                header('Location: login.php?registered=1');
            }
            exit();
        } else {
            $error = "Pendaftaran gagal: " . $conn->error;
        }
    }
}

$page_title = $show_admin_form ? "Register Admin" : "Daftar - Perpustakaan Digital";
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        /* CSS Variables for consistent theming */
        :root {
            --primary-gradient: linear-gradient(145deg, #667eea, #764ba2);
            --secondary-gradient: linear-gradient(145deg, #f093fb, #f5576c);
            --admin-gradient: linear-gradient(145deg, #fbbf24, #f59e0b);
            --success-color: #48bb78;
            --error-color: #f56565;
            --warning-color: #ecc94b;
            --text-primary: #2d3748;
            --text-secondary: #4a5568;
            --text-muted: #718096;
            --border-color: #e2e8f0;
            --card-bg: rgba(255, 255, 255, 0.98);
            --shadow-sm: 0 10px 25px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 20px 40px rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 25px 60px rgba(0, 0, 0, 0.25);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

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

        /* Enhanced Animated Background */
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
            animation-delay: 0s;
        }

        .bg-shape:nth-child(2) {
            width: 400px;
            height: 400px;
            bottom: -200px;
            left: -200px;
            background: linear-gradient(135deg, #48dbfb, #1dd1a1);
            animation-delay: 2s;
        }

        .bg-shape:nth-child(3) {
            width: 300px;
            height: 300px;
            top: 30%;
            right: 10%;
            background: linear-gradient(135deg, #f368e0, #ff9f43);
            animation-delay: 4s;
        }

        .floating-book {
            position: absolute;
            font-size: 4rem;
            opacity: 0.08;
            animation: floatBook 20s linear infinite;
            user-select: none;
            pointer-events: none;
        }

        .floating-book:nth-child(6) { top: 15%; left: 5%; animation-delay: 0s; }
        .floating-book:nth-child(7) { top: 75%; left: 85%; animation-delay: 5s; }
        .floating-book:nth-child(8) { top: 45%; left: 92%; animation-delay: 10s; }
        .floating-book:nth-child(9) { top: 85%; left: 15%; animation-delay: 15s; }
        .floating-book:nth-child(10) { top: 25%; left: 75%; animation-delay: 7s; }

        @keyframes floatShape {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-40px) scale(1.05); }
        }

        @keyframes floatBook {
            0% { transform: translateY(0) rotate(0deg); opacity: 0.08; }
            50% { transform: translateY(-150px) rotate(15deg); opacity: 0.15; }
            100% { transform: translateY(0) rotate(0deg); opacity: 0.08; }
        }

        /* Main Container with Flexbox centering */
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        /* Enhanced Register Card */
        .register-card {
            width: 100%;
            max-width: <?php echo $show_admin_form ? '500px' : '620px'; ?>;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 40px;
            padding: 45px 40px;
            box-shadow: var(--shadow-lg);
            animation: cardEntrance 0.8s cubic-bezier(0.23, 1, 0.32, 1);
            border: 1px solid rgba(255, 255, 255, 0.4);
            transition: var(--transition);
        }

        .register-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 35px 70px rgba(0, 0, 0, 0.3);
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

        /* Enhanced Header */
        .register-header {
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
            background: <?php echo $show_admin_form ? 'var(--admin-gradient)' : 'var(--primary-gradient)'; ?>;
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
            color: <?php echo $show_admin_form ? '#1a1a2e' : 'white'; ?>;
            animation: logoBounce 3s infinite;
        }

        @keyframes logoBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .register-header h1 {
            font-size: 32px;
            font-weight: 800;
            background: <?php echo $show_admin_form ? 'var(--admin-gradient)' : 'var(--primary-gradient)'; ?>;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .register-header p {
            color: var(--text-muted);
            font-size: 15px;
            font-weight: 400;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: <?php echo $show_admin_form ? 'var(--admin-gradient)' : 'var(--secondary-gradient)'; ?>;
            color: <?php echo $show_admin_form ? '#1a1a2e' : 'white'; ?>;
            padding: 10px 25px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 18px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Back Link */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 25px;
            padding: 10px 18px;
            border-radius: 40px;
            background: rgba(102, 126, 234, 0.08);
            transition: var(--transition);
            border: 1px solid transparent;
        }

        .back-link:hover {
            background: rgba(102, 126, 234, 0.15);
            transform: translateX(-8px);
            color: #667eea;
            border-color: rgba(102, 126, 234, 0.2);
        }

        .back-link i {
            font-size: 14px;
            transition: var(--transition);
        }

        .back-link:hover i {
            transform: translateX(-3px);
        }

        /* Enhanced Role Selector */
        .role-selector {
            display: flex;
            gap: 20px;
            margin-bottom: 35px;
            justify-content: center;
        }

        .role-option {
            flex: 1;
            max-width: 180px;
            text-align: center;
            padding: 20px 15px;
            border: 2px solid var(--border-color);
            border-radius: 24px;
            cursor: pointer;
            transition: var(--transition);
            background: white;
            position: relative;
            overflow: hidden;
        }

        .role-option::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(145deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.5s ease;
        }

        .role-option:hover::before {
            transform: scaleX(1);
        }

        .role-option:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 30px rgba(102, 126, 234, 0.2);
            border-color: #667eea;
        }

        .role-option.student.active {
            border-color: #667eea;
            background: linear-gradient(145deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.25);
        }

        .role-option.admin.active {
            border-color: #fbbf24;
            background: linear-gradient(145deg, rgba(251, 191, 36, 0.1), rgba(245, 158, 11, 0.1));
            box-shadow: 0 10px 25px rgba(251, 191, 36, 0.25);
        }

        .role-icon {
            font-size: 36px;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .role-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            position: relative;
            z-index: 1;
        }

        .role-desc {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 5px;
            position: relative;
            z-index: 1;
        }

        /* Info Box */
        .info-box {
            background: linear-gradient(145deg, rgba(102, 126, 234, 0.08), rgba(118, 75, 162, 0.08));
            border-radius: 24px;
            padding: 18px 20px;
            margin-bottom: 25px;
            font-size: 14px;
            color: var(--text-secondary);
            border: 1px dashed #667eea;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .info-box i {
            color: #667eea;
            font-size: 24px;
        }

        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideShake 0.5s cubic-bezier(0.36, 0.07, 0.19, 0.97);
            font-size: 14px;
            font-weight: 500;
            border-left: 4px solid transparent;
        }

        .alert-error {
            background: linear-gradient(145deg, #fff5f5, #fed7d7);
            border-left-color: var(--error-color);
            color: #c53030;
        }

        .alert-success {
            background: linear-gradient(145deg, #f0fff4, #c6f6d5);
            border-left-color: var(--success-color);
            color: #22543d;
        }

        @keyframes slideShake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-8px); }
            40%, 80% { transform: translateX(8px); }
        }

        /* Enhanced Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 18px;
            position: relative;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 18px;
            transition: var(--transition);
            z-index: 2;
        }

        .form-control {
            width: 100%;
            padding: 16px 18px 16px 55px;
            border: 2px solid var(--border-color);
            border-radius: 22px;
            font-size: 15px;
            transition: var(--transition);
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

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%234a5568' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 18px center;
            background-size: 20px;
            padding-right: 50px;
        }

        /* Password Strength Indicator */
        .password-strength {
            margin-top: 10px;
        }

        .strength-bar {
            height: 6px;
            background: #edf2f7;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 6px;
        }

        .strength-fill {
            height: 100%;
            width: 0;
            transition: width 0.3s ease, background-color 0.3s ease;
            border-radius: 10px;
        }

        .strength-text {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Password Match Indicator */
        #passwordMatch {
            font-size: 12px;
            margin-top: 6px;
            display: block;
            font-weight: 500;
        }

        /* Terms and Conditions */
        .terms-check {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 25px 0 20px;
            cursor: pointer;
            color: var(--text-secondary);
            font-size: 14px;
            padding: 8px 12px;
            border-radius: 16px;
            transition: var(--transition);
        }

        .terms-check:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .terms-check input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #667eea;
            margin: 0;
        }

        .terms-check a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            position: relative;
        }

        .terms-check a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: #667eea;
            transition: width 0.3s ease;
        }

        .terms-check a:hover::after {
            width: 100%;
        }

        /* Enhanced Register Button */
        .btn-register {
            width: 100%;
            padding: 18px 24px;
            background: <?php echo $show_admin_form ? 'var(--admin-gradient)' : 'var(--primary-gradient)'; ?>;
            color: <?php echo $show_admin_form ? '#1a1a2e' : 'white'; ?>;
            border: none;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.3);
        }

        .btn-register::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s ease;
        }

        .btn-register:hover::before {
            left: 100%;
        }

        .btn-register:hover {
            transform: translateY(-4px);
            box-shadow: 0 25px 40px rgba(102, 126, 234, 0.4);
        }

        .btn-register:active {
            transform: translateY(-2px);
        }

        .btn-register i {
            font-size: 18px;
            transition: transform 0.3s ease;
        }

        .btn-register:hover i {
            transform: translateX(8px) scale(1.1);
        }

        /* Login Link */
        .login-link {
            text-align: center;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid rgba(226, 232, 240, 0.6);
        }

        .login-link p {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 10px;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 25px;
            border-radius: 40px;
            background: rgba(102, 126, 234, 0.08);
            border: 1px solid transparent;
        }

        .login-link a:hover {
            background: rgba(102, 126, 234, 0.15);
            transform: translateX(8px);
            border-color: rgba(102, 126, 234, 0.2);
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
            color: var(--text-primary);
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

        /* Success Modal */
        .success-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 40px;
            padding: 50px 40px;
            text-align: center;
            box-shadow: var(--shadow-lg);
            z-index: 10000;
            max-width: 400px;
            width: 90%;
            animation: modalPop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .success-modal.active {
            display: block;
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(145deg, #48bb78, #38a169);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            animation: successPulse 2s infinite;
        }

        .success-icon i {
            font-size: 50px;
            color: white;
        }

        @keyframes successPulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(72, 187, 120, 0.5); }
            50% { transform: scale(1.05); box-shadow: 0 0 30px 0 rgba(72, 187, 120, 0.5); }
        }

        .success-modal h2 {
            color: var(--text-primary);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .success-modal p {
            color: var(--text-muted);
            margin-bottom: 25px;
        }

        @keyframes modalPop {
            from {
                opacity: 0;
                transform: translate(-50%, -50%) scale(0.8);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .register-card {
                padding: 35px 25px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .form-group.full-width {
                grid-column: 1;
            }

            .role-selector {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }

            .role-option {
                max-width: 100%;
            }

            .logo-animation {
                width: 80px;
                height: 80px;
            }

            .logo-animation span {
                font-size: 40px;
            }

            .register-header h1 {
                font-size: 26px;
            }
        }

        @media (max-width: 480px) {
            .register-card {
                padding: 25px 20px;
            }

            .form-control {
                padding: 14px 16px 14px 48px;
                font-size: 14px;
            }

            .btn-register {
                padding: 16px 20px;
                font-size: 15px;
            }

            .role-badge {
                padding: 8px 18px;
                font-size: 12px;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(145deg, #667eea, #764ba2);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(145deg, #764ba2, #667eea);
        }

        /* Focus Visible for Accessibility */
        :focus-visible {
            outline: 3px solid #667eea;
            outline-offset: 3px;
        }

        /* Selection Style */
        ::selection {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="animated-bg">
        <div class="bg-shape"></div>
        <div class="bg-shape"></div>
        <div class="bg-shape"></div>
        <div class="floating-book">📚</div>
        <div class="floating-book">📖</div>
        <div class="floating-book">📕</div>
        <div class="floating-book">📗</div>
        <div class="floating-book">📘</div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Memproses pendaftaran...</div>
    </div>

    <!-- Success Modal -->
    <div class="success-modal" id="successModal">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        <h2>Pendaftaran Berhasil!</h2>
        <p>Silakan login dengan akun Anda</p>
        <div style="width: 30px; height: 30px; margin: 0 auto;">
            <div class="loading-spinner" style="width: 30px; height: 30px;"></div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="register-container">
        <div class="register-card" data-aos="fade-up" data-aos-duration="800">
            <?php if(!$show_admin_form): ?>
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                <span>Kembali ke Beranda</span>
            </a>
            <?php endif; ?>

            <!-- Header -->
            <div class="register-header">
                <div class="logo-wrapper">
                    <div class="logo-animation">
                        <span><?php echo $show_admin_form ? '⚡' : '📚'; ?></span>
                    </div>
                    <div class="logo-ring"></div>
                </div>
                <h1><?php echo $show_admin_form ? 'Register Admin' : 'Buat Akun Baru'; ?></h1>
                <p><?php echo $show_admin_form ? 'Buat akun administrator perpustakaan' : 'Bergabunglah dengan perpustakaan digital gamifikasi'; ?></p>
                <div class="role-badge">
                    <i class="fas fa-<?php echo $show_admin_form ? 'crown' : 'school'; ?>"></i>
                    <span><?php echo $show_admin_form ? 'Admin Registration' : 'SMK Mardi Yuana Cikembar'; ?></span>
                </div>
            </div>

            <?php if(!$show_admin_form): ?>
            <!-- Role Selector -->
            <div class="role-selector" data-aos="fade-up" data-aos-delay="100">
                <div class="role-option student active" onclick="selectRole('student')">
                    <div class="role-icon">📚</div>
                    <div class="role-name">Siswa</div>
                    <div class="role-desc">Akses ke perpustakaan</div>
                </div>
                <div class="role-option admin" onclick="window.location.href='register.php?type=admin'">
                    <div class="role-icon">⚡</div>
                    <div class="role-name">Admin</div>
                    <div class="role-desc">Manajemen sistem</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Info Box untuk Admin -->
            <?php if($show_admin_form): ?>
            <div class="info-box" data-aos="fade-up" data-aos-delay="100">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Perhatian:</strong> Halaman ini khusus untuk registrasi administrator.
                </div>
            </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if (isset($error)): ?>
                <div class="alert alert-error" data-aos="fade-up">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <form method="POST" action="" id="registerForm">
                <input type="hidden" name="role" value="<?php echo $show_admin_form ? 'admin' : 'siswa'; ?>">

                <div class="form-grid">
                    <!-- Nama Lengkap -->
                    <div class="form-group full-width" data-aos="fade-up" data-aos-delay="200">
                        <label for="nama">Nama Lengkap</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" 
                                   class="form-control" 
                                   id="nama" 
                                   name="nama" 
                                   placeholder="Masukkan nama lengkap"
                                   required
                                   value="<?php echo isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : ''; ?>">
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="form-group full-width" data-aos="fade-up" data-aos-delay="250">
                        <label for="email">Email</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   placeholder="Masukkan email"
                                   required
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                    </div>

                    <?php if(!$show_admin_form): ?>
                    <!-- Kelas -->
                    <div class="form-group" data-aos="fade-up" data-aos-delay="300">
                        <label for="kelas">Kelas</label>
                        <div class="input-wrapper">
                            <i class="fas fa-graduation-cap"></i>
                            <input type="text" 
                                   class="form-control" 
                                   id="kelas" 
                                   name="kelas" 
                                   placeholder="Contoh: X RPL 1"
                                   required
                                   value="<?php echo isset($_POST['kelas']) ? htmlspecialchars($_POST['kelas']) : ''; ?>">
                        </div>
                    </div>

                    <!-- Jurusan -->
                    <div class="form-group" data-aos="fade-up" data-aos-delay="350">
                        <label for="jurusan">Jurusan</label>
                        <div class="input-wrapper">
                            <i class="fas fa-code"></i>
                            <select class="form-control" id="jurusan" name="jurusan" required>
                                <option value="">Pilih Jurusan</option>
                                <option value="Rekayasa Perangkat Lunak">Rekayasa Perangkat Lunak</option>
                                <option value="DPB / Tata Busana">DPB / Tata Busana</option>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Password -->
                    <div class="form-group" data-aos="fade-up" data-aos-delay="400">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Min. 6 karakter"
                                   required
                                   minlength="6">
                            <span class="password-toggle" onclick="togglePassword('password', 'toggleIcon')">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </span>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                            <span class="strength-text" id="strengthText">Kekuatan password</span>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="form-group" data-aos="fade-up" data-aos-delay="450">
                        <label for="confirm_password">Konfirmasi Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   placeholder="Ulangi password"
                                   required>
                            <span class="password-toggle" onclick="togglePassword('confirm_password', 'toggleConfirmIcon')">
                                <i class="fas fa-eye" id="toggleConfirmIcon"></i>
                            </span>
                        </div>
                        <small id="passwordMatch"></small>
                    </div>
                </div>

                <!-- Terms and Conditions -->
                <label class="terms-check" data-aos="fade-up" data-aos-delay="500">
                    <input type="checkbox" name="terms" id="terms" required>
                    <span>Saya setuju dengan <a href="#">Syarat & Ketentuan</a> dan <a href="#">Kebijakan Privasi</a></span>
                </label>

                <!-- Register Button -->
                <button type="submit" class="btn-register" id="registerBtn" data-aos="fade-up" data-aos-delay="550">
                    <span><?php echo $show_admin_form ? 'Daftar sebagai Admin' : 'Daftar Sekarang'; ?></span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <!-- Login Link -->
            <div class="login-link" data-aos="fade-up" data-aos-delay="600">
                <p>Sudah punya akun?</p>
                <a href="login.php">
                    <span>Login di sini</span>
                    <i class="fas fa-sign-in-alt"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- AOS Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <!-- SweetAlert2 for beautiful alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Initialize AOS
        AOS.init({
            once: true,
            duration: 800,
            easing: 'ease-in-out'
        });

        // Toggle password visibility
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            const criteria = {
                length: password.length >= 6,
                medium: password.length >= 8,
                number: /\d/.test(password),
                special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
            };
            
            if (criteria.length) strength += 25;
            if (criteria.medium) strength += 25;
            if (criteria.number) strength += 25;
            if (criteria.special) strength += 25;
            
            strengthFill.style.width = strength + '%';
            
            if (strength <= 25) {
                strengthFill.style.background = '#f56565';
                strengthText.textContent = 'Password lemah';
            } else if (strength <= 50) {
                strengthFill.style.background = '#ed8936';
                strengthText.textContent = 'Password sedang';
            } else if (strength <= 75) {
                strengthFill.style.background = '#ecc94b';
                strengthText.textContent = 'Password baik';
            } else {
                strengthFill.style.background = '#48bb78';
                strengthText.textContent = 'Password kuat';
            }
        });

        // Password match checker
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirm.length === 0) {
                matchText.textContent = '';
            } else if (password === confirm) {
                matchText.textContent = '✓ Password cocok';
                matchText.style.color = '#48bb78';
            } else {
                matchText.textContent = '✗ Password tidak cocok';
                matchText.style.color = '#f56565';
            }
        });

        // Role selection
        function selectRole(role) {
            const studentOption = document.querySelector('.role-option.student');
            const adminOption = document.querySelector('.role-option.admin');
            
            if (role === 'student') {
                studentOption.classList.add('active');
                adminOption.classList.remove('active');
                window.location.href = 'register.php';
            }
        }

        // Form submission with validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            // Password match validation
            if (password !== confirm) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Password Tidak Cocok',
                    text: 'Password dan konfirmasi password harus sama!',
                    confirmButtonColor: '#667eea',
                    background: 'white',
                    backdrop: 'rgba(102,126,234,0.2)'
                });
                return;
            }
            
            // Password length validation
            if (password.length < 6) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Password Terlalu Pendek',
                    text: 'Password minimal 6 karakter!',
                    confirmButtonColor: '#667eea',
                    background: 'white'
                });
                return;
            }
            
            // Terms validation
            if (!document.getElementById('terms').checked) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Syarat & Ketentuan',
                    text: 'Anda harus menyetujui Syarat & Ketentuan!',
                    confirmButtonColor: '#667eea',
                    background: 'white'
                });
                return;
            }
            
            // Email validation
            const email = document.getElementById('email').value;
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Email Tidak Valid',
                    text: 'Format email tidak valid!',
                    confirmButtonColor: '#667eea',
                    background: 'white'
                });
                return;
            }
            
            // Show loading
            const btn = document.getElementById('registerBtn');
            btn.innerHTML = '<span>Mendaftar...</span> <i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;
            document.getElementById('loadingOverlay').classList.add('active');
        });

        // Email validation on blur
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value;
            const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !pattern.test(email)) {
                this.style.borderColor = '#f56565';
                this.style.boxShadow = '0 0 0 3px rgba(245,101,101,0.1)';
            } else {
                this.style.borderColor = '#e2e8f0';
                this.style.boxShadow = 'none';
            }
        });

        // Character counter for nama
        document.getElementById('nama').addEventListener('input', function() {
            const maxLength = 100;
            const currentLength = this.value.length;
            
            if (currentLength > maxLength) {
                this.value = this.value.slice(0, maxLength);
            }
        });

        // Auto-hide alerts with animation
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                alert.style.transition = 'all 0.5s ease';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 500);
            }, 5000);
        });

        // Parallax effect on mouse move
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

        // Escape key to clear form
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (confirm('Hapus semua data yang diisi?')) {
                    document.getElementById('registerForm').reset();
                }
            }
        });

        // Prevent double submission
        let formSubmitted = false;
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            if (formSubmitted) {
                e.preventDefault();
                return;
            }
            formSubmitted = true;
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

        // Smooth scroll to top
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    </script>
</body>
</html>