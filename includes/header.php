<?php
// includes/header.php - kerangka halaman: <head>, sidebar kiri, dan topbar
// Sebelum di-include, halaman harus mengisi: $judul dan $halaman_aktif

cek_login();
$user = $_SESSION['user'];

// Daftar menu sidebar + role yang boleh melihatnya
$menu = [
    ['file' => 'index.php',        'nama' => 'Dashboard',        'ikon' => 'home',    'role' => ['admin']],
    ['file' => 'anggota.php',      'nama' => 'Data Anggota',     'ikon' => 'users',   'role' => ['admin']],
    ['file' => 'barang.php',       'nama' => 'Data Barang/Stok', 'ikon' => 'package', 'role' => ['admin', 'kasir']],
    ['file' => 'kasir.php',        'nama' => 'Kasir',            'ikon' => 'cart',    'role' => ['admin', 'kasir']],
    ['file' => 'barang_masuk.php', 'nama' => 'Barang Masuk',     'ikon' => 'masuk',   'role' => ['admin']],
    ['file' => 'simpanan.php',     'nama' => 'Simpanan Anggota', 'ikon' => 'uang',    'role' => ['admin']],
    ['file' => 'laporan.php',      'nama' => 'Laporan',          'ikon' => 'laporan', 'role' => ['admin']],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul) ?> - SiKopdes</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- Sidebar kiri -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="logo">SK</div>
        <div>
            <strong>SiKopdes</strong>
            <small>Kopdes Merah Putih Sukowono</small>
        </div>
    </div>

    <nav class="sidebar-menu">
        <?php foreach ($menu as $m): ?>
            <?php if (in_array($user['role'], $m['role'], true)): ?>
                <a href="<?= $m['file'] ?>" class="<?= $halaman_aktif === $m['file'] ? 'aktif' : '' ?>">
                    <?= ikon($m['ikon']) ?> <span><?= e($m['nama']) ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-user">
        <div class="user-info">
            <strong><?= e($user['nama']) ?></strong>
            <span class="badge badge-putih"><?= e(ucfirst($user['role'])) ?></span>
        </div>
        <a href="logout.php" class="btn-keluar"><?= ikon('keluar') ?> Keluar</a>
    </div>
</aside>
<div class="overlay" id="overlay"></div>

<!-- Area konten -->
<div class="konten">
    <header class="topbar">
        <button type="button" class="tombol-menu" id="tombolMenu" aria-label="Buka menu"><?= ikon('menu') ?></button>
        <h1><?= e($judul) ?></h1>
        <span class="tanggal-hari-ini"><?= e(hari_singkat(date('Y-m-d'))) ?>, <?= e(tanggal_indo(date('Y-m-d'))) ?></span>
    </header>

    <main class="isi">
        <?php tampil_flash(); ?>
