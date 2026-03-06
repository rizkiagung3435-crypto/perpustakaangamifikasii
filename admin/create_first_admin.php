<?php
/**
 * Script untuk membuat admin pertama
 * Jalankan file ini sekali untuk membuat akun admin
 * Hapus setelah digunakan!
 */

require_once '../config/koneksi.php';

$database = new Database();
$conn = $database->getConnection();

// Konfigurasi admin pertama
$admin_data = [
    'nama' => 'Super Administrator',
    'email' => 'admin@perpus.sch.id',
    'password' => 'Admin123!', // Ganti dengan password yang kuat
    'nip' => '199001012022011001',
    'jabatan' => 'Kepala Perpustakaan',
    'no_telp' => '081234567890',
    'alamat' => 'Jl. Pendidikan No. 123, Cikembar',
    'akses_level' => 'super_admin'
];

// Hash password
$hashed_password = password_hash($admin_data['password'], PASSWORD_DEFAULT);

// Mulai transaksi
$conn->begin_transaction();

try {
    // Insert ke tabel users
    $query_user = "INSERT INTO users (nama, email, password, role, avatar, created_at) 
                   VALUES (?, ?, ?, 'admin', 'default-avatar.png', NOW())";
    $stmt = $conn->prepare($query_user);
    $stmt->bind_param("sss", $admin_data['nama'], $admin_data['email'], $hashed_password);
    $stmt->execute();
    
    $user_id = $conn->insert_id;
    
    // Insert ke tabel admin_details
    $query_admin = "INSERT INTO admin_details (user_id, nip, jabatan, no_telp, alamat, akses_level, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($query_admin);
    $stmt->bind_param("isssss", $user_id, $admin_data['nip'], $admin_data['jabatan'], 
                      $admin_data['no_telp'], $admin_data['alamat'], $admin_data['akses_level']);
    $stmt->execute();
    
    // Commit transaksi
    $conn->commit();
    
    echo "<div style='font-family: Arial; max-width: 600px; margin: 50px auto; padding: 30px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);'>";
    echo "<h1 style='margin-bottom: 20px;'>✅ Admin Berhasil Dibuat!</h1>";
    echo "<p><strong>Email:</strong> " . $admin_data['email'] . "</p>";
    echo "<p><strong>Password:</strong> " . $admin_data['password'] . "</p>";
    echo "<p><strong>NIP:</strong> " . $admin_data['nip'] . "</p>";
    echo "<p><strong>Jabatan:</strong> " . $admin_data['jabatan'] . "</p>";
    echo "<p style='margin-top: 20px; color: #ffeaa7; font-size: 14px;'>⚠️ Catat informasi ini dan hapus file ini setelah digunakan!</p>";
    echo "</div>";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "<div style='font-family: Arial; max-width: 600px; margin: 50px auto; padding: 30px; background: linear-gradient(135deg, #f56565, #c53030); color: white; border-radius: 20px;'>";
    echo "<h1>❌ Gagal Membuat Admin</h1>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "</div>";
}
?>