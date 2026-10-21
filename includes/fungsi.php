<?php
// includes/fungsi.php - fungsi bantu yang dipakai di semua halaman

// Mulai session (dipakai untuk menyimpan data login)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sambungkan ke database ($koneksi)
require_once __DIR__ . '/../config/koneksi.php';

/* ---------- Tampilan & format ---------- */

// Mengamankan teks sebelum ditampilkan (mencegah XSS)
function e($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

// Format angka ke rupiah: 15000 -> Rp 15.000
function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

// Format tanggal: 2026-10-19 -> 19 Okt 2026
function tanggal_indo($tgl) {
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($tgl);
    return date('j', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y', $t);
}

// Nama hari singkat: Sen, Sel, Rab, ...
function hari_singkat($tgl) {
    $hari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    return $hari[(int)date('w', strtotime($tgl))];
}

/* ---------- Database ---------- */

// Ambil semua baris hasil query (array of array)
function db_semua($sql) {
    global $koneksi;
    $hasil = mysqli_query($koneksi, $sql);
    return $hasil ? mysqli_fetch_all($hasil, MYSQLI_ASSOC) : [];
}

// Ambil satu baris saja
function db_satu($sql) {
    global $koneksi;
    $hasil = mysqli_query($koneksi, $sql);
    return $hasil ? mysqli_fetch_assoc($hasil) : null;
}

// Ambil banyak baris dengan prepared statement.
// Contoh: db_ambil('SELECT * FROM barang WHERE kategori = ?', 's', ['sembako'])
function db_ambil($sql, $tipe = '', $param = []) {
    global $koneksi;
    $stmt = mysqli_prepare($koneksi, $sql);
    if (!$stmt) {
        return [];
    }
    if ($tipe !== '') {
        mysqli_stmt_bind_param($stmt, $tipe, ...$param);
    }
    mysqli_stmt_execute($stmt);
    $hasil = mysqli_stmt_get_result($stmt);
    $baris = $hasil ? mysqli_fetch_all($hasil, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
    return $baris;
}

// Ambil satu baris (null jika tidak ada)
function db_ambil_satu($sql, $tipe = '', $param = []) {
    $baris = db_ambil($sql, $tipe, $param);
    return $baris[0] ?? null;
}

// Jalankan INSERT / UPDATE / DELETE dengan prepared statement. Hasil: true jika berhasil
// Tipe parameter: s = teks, i = angka bulat
function db_jalankan($sql, $tipe = '', $param = []) {
    global $koneksi;
    $stmt = mysqli_prepare($koneksi, $sql);
    if (!$stmt) {
        return false;
    }
    if ($tipe !== '') {
        mysqli_stmt_bind_param($stmt, $tipe, ...$param);
    }
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/* ---------- Validasi input ---------- */

// Cek angka bulat >= 0. Hasil: angkanya (int) atau false jika tidak valid.
// PENTING: bandingkan dengan === false, karena angka 0 juga valid.
function angka_valid($nilai) {
    $nilai = trim((string)$nilai);
    if ($nilai === '' || !ctype_digit($nilai) || strlen($nilai) > 9) {
        return false;
    }
    return (int)$nilai;
}

// Cek format tanggal YYYY-MM-DD yang benar-benar ada di kalender
function tanggal_valid($tgl) {
    $d = DateTime::createFromFormat('Y-m-d', (string)$tgl);
    return $d && $d->format('Y-m-d') === $tgl;
}

// Status stok untuk badge: [teks, kelas CSS]
function status_stok($stok, $minimum) {
    if ($stok <= 0) {
        return ['Habis', 'badge-merah'];
    }
    if ($stok <= $minimum) {
        return ['Menipis', 'badge-kuning'];
    }
    return ['Aman', 'badge-hijau'];
}

/* ---------- Pesan flash (sukses / gagal) ---------- */

// Simpan pesan untuk ditampilkan di halaman berikutnya. $tipe: 'sukses' atau 'gagal'
function set_flash($tipe, $pesan) {
    $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
}

// Tampilkan pesan flash lalu hapus
function tampil_flash() {
    if (empty($_SESSION['flash'])) {
        return;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    echo '<div class="flash flash-' . e($f['tipe']) . '">'
       . '<span>' . e($f['pesan']) . '</span>'
       . '<button type="button" class="flash-tutup" aria-label="Tutup">&times;</button></div>';
}

/* ---------- Login & hak akses ---------- */

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function sudah_login() {
    return !empty($_SESSION['user']);
}

// Halaman pertama setelah login, sesuai role
function halaman_awal($role) {
    return $role === 'admin' ? 'index.php' : 'kasir.php';
}

// Wajib login, kalau belum diarahkan ke halaman login
function cek_login() {
    if (!sudah_login()) {
        set_flash('gagal', 'Silakan login terlebih dahulu.');
        redirect('login.php');
    }
}

// Batasi halaman hanya untuk role tertentu. Contoh: wajib_role(['admin'])
function wajib_role($role_boleh) {
    cek_login();
    if (!in_array($_SESSION['user']['role'], $role_boleh, true)) {
        set_flash('gagal', 'Anda tidak punya akses ke halaman tersebut.');
        redirect(halaman_awal($_SESSION['user']['role']));
    }
}

/* ---------- CSRF (pengaman form) ---------- */

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

// Cetak input tersembunyi di dalam <form method="post">
function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

// Cek token saat form dikirim
function csrf_valid() {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

/* ---------- Ikon (SVG sederhana, tanpa library) ---------- */

function ikon($nama) {
    $path = [
        'home'    => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'users'   => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'package' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        'cart'    => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
        'masuk'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'uang'    => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'laporan' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'keluar'  => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'menu'    => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'alert'   => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    ];
    return '<svg class="ikon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . ($path[$nama] ?? '') . '</svg>';
}

/* ---------- Bantuan untuk form & query (Tahap 2-3) ---------- */

// Ambil input POST sebagai teks yang sudah dirapikan
function post($nama) {
    return trim((string)($_POST[$nama] ?? ''));
}

// Amankan teks untuk dimasukkan ke query SQL biasa
function esc($teks) {
    global $koneksi;
    return mysqli_real_escape_string($koneksi, (string)$teks);
}

// ID baris yang baru saja di-INSERT
function db_id_terakhir() {
    global $koneksi;
    return (int) mysqli_insert_id($koneksi);
}

// Tampilkan daftar pesan error validasi (kotak merah)
function tampil_error($daftar) {
    if (count($daftar) === 0) {
        return;
    }
    echo '<div class="flash flash-gagal"><div><strong>Data belum bisa disimpan:</strong><ul class="daftar-error">';
    foreach ($daftar as $pesan) {
        echo '<li>' . e($pesan) . '</li>';
    }
    echo '</ul></div></div>';
}

// Data untuk dropdown kategori barang
function daftar_kategori() {
    return ['sembako' => 'Sembako', 'pertanian' => 'Pertanian', 'lainnya' => 'Lainnya'];
}
