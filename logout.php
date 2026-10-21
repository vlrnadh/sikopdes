<?php
// logout.php - keluar dari sistem
require_once 'includes/fungsi.php';

// Hapus semua data session lalu mulai session baru untuk pesan flash
$_SESSION = [];
session_destroy();
session_start();

set_flash('sukses', 'Anda berhasil keluar.');
redirect('login.php');
