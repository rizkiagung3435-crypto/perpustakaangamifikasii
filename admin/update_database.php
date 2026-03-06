<?php
/**
 * Script untuk update struktur tabel buku
 * Jalankan file ini sekali untuk menambahkan kolom yang diperlukan
 */

require_once '../config/koneksi.php';

$database = new Database();
$conn = $database->getConnection();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Update Database</title>
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
            max-width: 600px;
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
        <h1>Update Struktur Tabel Buku</h1>";

// Cek dan tambah kolom penerbit
$check_penerbit = $conn->query("SHOW COLUMNS FROM buku LIKE 'penerbit'");
if ($check_penerbit->num_rows == 0) {
    $sql = "ALTER TABLE buku ADD COLUMN penerbit VARCHAR(100) DEFAULT NULL AFTER isbn";
    if ($conn->query($sql)) {
        echo "<div class='success'>✅ Kolom 'penerbit' berhasil ditambahkan!</div>";
    } else {
        echo "<div class='error'>❌ Gagal menambah kolom 'penerbit': " . $conn->error . "</div>";
    }
} else {
    echo "<div class='info'>ℹ️ Kolom 'penerbit' sudah ada.</div>";
}

// Cek dan tambah kolom lokasi_rak
$check_lokasi_rak = $conn->query("SHOW COLUMNS FROM buku LIKE 'lokasi_rak'");
if ($check_lokasi_rak->num_rows == 0) {
    $sql = "ALTER TABLE buku ADD COLUMN lokasi_rak VARCHAR(20) DEFAULT NULL AFTER penerbit";
    if ($conn->query($sql)) {
        echo "<div class='success'>✅ Kolom 'lokasi_rak' berhasil ditambahkan!</div>";
    } else {
        echo "<div class='error'>❌ Gagal menambah kolom 'lokasi_rak': " . $conn->error . "</div>";
    }
} else {
    echo "<div class='info'>ℹ️ Kolom 'lokasi_rak' sudah ada.</div>";
}

// Cek dan tambah kolom updated_at
$check_updated_at = $conn->query("SHOW COLUMNS FROM buku LIKE 'updated_at'");
if ($check_updated_at->num_rows == 0) {
    $sql = "ALTER TABLE buku ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at";
    if ($conn->query($sql)) {
        echo "<div class='success'>✅ Kolom 'updated_at' berhasil ditambahkan!</div>";
    } else {
        echo "<div class='error'>❌ Gagal menambah kolom 'updated_at': " . $conn->error . "</div>";
    }
} else {
    echo "<div class='info'>ℹ️ Kolom 'updated_at' sudah ada.</div>";
}

// Tampilkan struktur tabel
$result = $conn->query("DESCRIBE buku");
echo "<h3 style='margin-top: 30px; color: white;'>Struktur Tabel Buku Saat Ini:</h3>";
echo "<table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>";
echo "<tr style='background: rgba(255,255,255,0.1);'><th style='padding: 10px; text-align: left;'>Field</th><th style='padding: 10px; text-align: left;'>Type</th><th style='padding: 10px; text-align: left;'>Null</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr style='border-bottom: 1px solid rgba(255,255,255,0.1);'>";
    echo "<td style='padding: 8px;'>" . $row['Field'] . "</td>";
    echo "<td style='padding: 8px;'>" . $row['Type'] . "</td>";
    echo "<td style='padding: 8px;'>" . $row['Null'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<a href='tambah_buku.php' class='btn'>Kembali ke Tambah Buku</a>";
echo "</div></body></html>";
?>