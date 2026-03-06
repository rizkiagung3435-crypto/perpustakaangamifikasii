<?php
/**
 * Script untuk update database
 * Menambahkan kolom yang diperlukan
 * Jalankan file ini sekali untuk memperbaiki database
 */

require_once 'config/koneksi.php';

$database = new Database();
$conn = $database->getConnection();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Update Database</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 30px;
            padding: 40px;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .success {
            color: #48bb78;
            padding: 10px;
            border-left: 4px solid #48bb78;
            background: #f0fff4;
            margin: 10px 0;
        }
        .error {
            color: #f56565;
            padding: 10px;
            border-left: 4px solid #f56565;
            background: #fff5f5;
            margin: 10px 0;
        }
        .info {
            color: #4299e1;
            padding: 10px;
            border-left: 4px solid #4299e1;
            background: #ebf8ff;
            margin: 10px 0;
        }
        .btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 15px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class='container'>";

// Cek dan tambah kolom last_login
$check_last_login = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
if ($check_last_login->num_rows == 0) {
    $sql = "ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL AFTER created_at";
    if ($conn->query($sql)) {
        echo "<div class='success'>✅ Kolom 'last_login' berhasil ditambahkan!</div>";
    } else {
        echo "<div class='error'>❌ Gagal menambah kolom 'last_login': " . $conn->error . "</div>";
    }
} else {
    echo "<div class='info'>ℹ️ Kolom 'last_login' sudah ada.</div>";
}

// Cek dan tambah kolom is_active
$check_is_active = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
if ($check_is_active->num_rows == 0) {
    $sql = "ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER last_login";
    if ($conn->query($sql)) {
        echo "<div class='success'>✅ Kolom 'is_active' berhasil ditambahkan!</div>";
        
        // Set default active untuk semua user
        $conn->query("UPDATE users SET is_active = 1 WHERE is_active IS NULL");
        echo "<div class='success'>✅ Semua user diaktifkan!</div>";
    } else {
        echo "<div class='error'>❌ Gagal menambah kolom 'is_active': " . $conn->error . "</div>";
    }
} else {
    echo "<div class='info'>ℹ️ Kolom 'is_active' sudah ada.</div>";
}

// Cek dan buat tabel log_aktivitas jika belum ada
$check_log_table = $conn->query("SHOW TABLES LIKE 'log_aktivitas'");
if ($check_log_table->num_rows == 0) {
    $sql = "CREATE TABLE IF NOT EXISTS log_aktivitas (
        id_log INT(11) NOT NULL AUTO_INCREMENT,
        id_user INT(11) DEFAULT NULL,
        aktivitas VARCHAR(255) NOT NULL,
        deskripsi TEXT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id_log),
        KEY id_user (id_user)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "<div class='success'>✅ Tabel 'log_aktivitas' berhasil dibuat!</div>";
    } else {
        echo "<div class='error'>❌ Gagal membuat tabel 'log_aktivitas': " . $conn->error . "</div>";
    }
} else {
    echo "<div class='info'>ℹ️ Tabel 'log_aktivitas' sudah ada.</div>";
}

// Cek apakah admin sudah ada
$check_admin = $conn->query("SELECT id FROM users WHERE email = 'admin@perpus.sch.id'");
if ($check_admin->num_rows == 0) {
    $nama = "Super Administrator";
    $email = "admin@perpus.sch.id";
    $password = password_hash("Admin123!", PASSWORD_DEFAULT);
    $role = "admin";
    
    $query = "INSERT INTO users (nama, email, password, role, avatar, created_at, is_active) 
              VALUES (?, ?, ?, ?, 'default-avatar.png', NOW(), 1)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssss", $nama, $email, $password, $role);
    
    if ($stmt->execute()) {
        echo "<div class='success'>✅ Akun admin berhasil dibuat! Email: admin@perpus.sch.id, Password: Admin123!</div>";
        
        // Cek dan buat tabel admin_details
        $check_admin_details = $conn->query("SHOW TABLES LIKE 'admin_details'");
        if ($check_admin_details->num_rows > 0) {
            $user_id = $conn->insert_id;
            $nip = "199001012022011001";
            $jabatan = "Super Administrator";
            $no_telp = "081234567890";
            $alamat = "SMK Mardi Yuana Cikembar";
            $akses_level = "super_admin";
            
            $query_details = "INSERT INTO admin_details (user_id, nip, jabatan, no_telp, alamat, akses_level, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt_details = $conn->prepare($query_details);
            $stmt_details->bind_param("isssss", $user_id, $nip, $jabatan, $no_telp, $alamat, $akses_level);
            
            if ($stmt_details->execute()) {
                echo "<div class='success'>✅ Data admin details berhasil ditambahkan!</div>";
            }
        }
    } else {
        echo "<div class='error'>❌ Gagal membuat akun admin: " . $conn->error . "</div>";
    }
} else {
    echo "<div class='info'>ℹ️ Akun admin sudah ada.</div>";
}

echo "<a href='login.php' class='btn'>Go to Login Page</a>";
echo "</div></body></html>";
?>