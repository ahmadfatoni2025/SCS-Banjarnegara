<?php
session_start();
include 'koneksi.php'; // Hubungkan ke database Anda

// Set header sebagai JSON
header('Content-Type: application/json');

// Siapkan respons default
$response = ['success' => false, 'message' => 'Terjadi kesalahan.'];

// --- Keamanan (Sangat Penting) ---
// Pastikan hanya admin yang bisa mengakses ini
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $response['message'] = 'Akses ditolak. Anda bukan admin.';
    echo json_encode($response);
    exit;
}

$id_barang = $_POST['id_barang'] ?? 0;
$current_state = $_POST['current_pin_state'] ?? '0';

if (empty($id_barang)) {
    $response['message'] = 'ID barang tidak valid.';
    echo json_encode($response);
    exit;
}

// Tentukan state baru (toggle)
// Ganti 'is_pinned' dengan nama kolom Anda jika berbeda
$new_state = ($current_state == '1') ? 0 : 1; 

try {
    // Ganti 'gudang' dan 'id_barang' jika nama tabel/kolom Anda berbeda
    $stmt = $koneksi->prepare("UPDATE gudang SET is_pinned = ? WHERE id_barang = ?");
    
    if (!$stmt) {
        throw new Exception("Gagal prepare statement: " . $koneksi->error);
    }
    
    $stmt->bind_param("ii", $new_state, $id_barang);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Sukses
            $response['success'] = true;
            $response['message'] = 'Status pin berhasil diubah.';
            $response['new_pin_state'] = $new_state; // Kirim status baru kembali ke JavaScript
        } else {
            // ID barang tidak ditemukan atau status sudah sama
            throw new Exception("Item tidak ditemukan atau status sudah sama (ID: $id_barang)");
        }
    } else {
        throw new Exception("Gagal eksekusi update: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    // Tangkap jika ada error database
    $response['message'] = $e->getMessage();
    error_log("Error Pin AJAX: " . $e->getMessage()); // Log error ke server
}

// Kembalikan respons sebagai JSON
echo json_encode($response);
?>
