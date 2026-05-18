<?php

// $username = "scsbanja_banjar";             // Default user sistem lokal (XAMPP/Laragon)
// $password = "rutanIMM_2026";                 // Default password sistem lokal (kosong)
// $database = "scsbanja_banjarnegara";  // Pastikan database ini sudah dibuat di phpMyAdmin

// koneksi.php
$host = "localhost";
$username = "root";             // Default XAMPP
$password = "";                 // Default XAMPP (kosong)
$database = "scsbanja_banjarnegara"; // Nama database di phpMyAdmin kamu

// Buat koneksi
$koneksi = new mysqli($host, $username, $password, $database);

// Cek koneksi
if ($koneksi->connect_error) {
    die("Koneksi database gagal! Silakan cek apakah XAMPP (MySQL) sudah menyala dan database '$database' sudah dibuat di phpMyAdmin. Error: " . $koneksi->connect_error);
}

// Set charset agar support karakter khusus
$koneksi->set_charset("utf8mb4");
?>
