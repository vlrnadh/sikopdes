<?php
// config/koneksi.php - koneksi ke database MySQL memakai mysqli

// Pengaturan database (default XAMPP: user root, password kosong)
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_nama = 'db_sikopdes';

// Samakan zona waktu PHP dan MySQL (WIB)
date_default_timezone_set('Asia/Jakarta');

// Nonaktifkan exception otomatis mysqli, error query ditangani lewat nilai balik (true/false)
mysqli_report(MYSQLI_REPORT_OFF);

// Error SQL tidak membuat halaman berhenti; kita cek hasilnya sendiri di kode
mysqli_report(MYSQLI_REPORT_OFF);

// Membuat koneksi
$koneksi = mysqli_connect($db_host, $db_user, $db_pass, $db_nama);

// Jika gagal, tampilkan pesan yang mudah dipahami
if (!$koneksi) {
    die('<h3>Koneksi database gagal</h3>'
      . '<p>Pastikan MySQL di XAMPP sudah Start dan database <b>db_sikopdes</b> sudah diimport dari file database.sql.</p>'
      . '<p><small>' . htmlspecialchars(mysqli_connect_error()) . '</small></p>');
}

mysqli_set_charset($koneksi, 'utf8mb4');
mysqli_query($koneksi, "SET time_zone = '+07:00'");
