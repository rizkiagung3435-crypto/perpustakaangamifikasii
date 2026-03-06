<?php
/**
 * Script untuk update struktur tabel users
 * Jalankan file ini sekali untuk menambahkan kolom yang diperlukan
 */

require_once '../config/koneksi.php';

$database = new Database();
$conn = $database->getConnection();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Update Database Users</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            max-width: 800px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        h1 {
            color: #fff;
            margin-bottom: 20px;
        }
        .success {
            color: #48bb78;
            padding: 10px;
            border-left: 4px solid #48bb78;
            background: rgba(72, 187, 120, 0.1);
            margin: 10px 0;
        }
        .error {
            color: #f56565;
            padding: 10px;
            border-left: 4px solid #f56565;
            background: rgba(245, 101, 101, 0.1);
            margin: 10px 0;
        }
        .info {
            color: #4299e1;
            padding: 10px;
            border-left: 4px solid #4299e1;
            background: rgba(66, 153, 225, 0.1);
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
        }
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Update Struktur Tabel Users</h1>";

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

// Tampilkan struktur tabel
$result = $conn->query("DESCRIBE users");
echo "<h3 style='margin-top: 30px; color: white;'>Struktur Tabel Users Saat Ini:</h3>";
echo "<div style='overflow-x: auto;'>";
echo "<table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>";
echo "<tr style='background: rgba(255,255,255,0.1);'><th style='padding: 10px; text-align: left;'>Field</th><th style='padding: 10px; text-align: left;'>Type</th><th style='padding: 10px; text-align: left;'>Null</th><th style='padding: 10px; text-align: left;'>Default</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr style='border-bottom: 1px solid rgba(255,255,255,0.1);'>";
    echo "<td style='padding: 8px;'>" . $row['Field'] . "</td>";
    echo "<td style='padding: 8px;'>" . $row['Type'] . "</td>";
    echo "<td style='padding: 8px;'>" . $row['Null'] . "</td>";
    echo "<td style='padding: 8px;'>" . ($row['Default'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

echo "<a href='data_user.php' class='btn'>Kembali ke Data User</a>";
echo "</div></body></html>";
?>