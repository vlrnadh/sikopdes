<?php
// login.php - halaman login
require_once 'includes/fungsi.php';

// Kalau sudah login, langsung ke halaman awal sesuai role
if (sudah_login()) {
    redirect(halaman_awal($_SESSION['user']['role']));
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!csrf_valid()) {
        $error = 'Sesi form tidak valid, silakan coba lagi.';
    } elseif ($username === '' || $password === '') {
        // Validasi: tidak boleh kosong
        $error = 'Username dan password wajib diisi.';
    } else {
        // Cari user (prepared statement supaya aman dari SQL injection)
        $stmt = mysqli_prepare($koneksi, 'SELECT id, nama, username, password, role FROM users WHERE username = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        // Cocokkan password dengan hash di database
        if ($data && password_verify($password, $data['password'])) {
            session_regenerate_id(true); // ganti ID session demi keamanan
            $_SESSION['user'] = [
                'id'       => $data['id'],
                'nama'     => $data['nama'],
                'username' => $data['username'],
                'role'     => $data['role'],
            ];
            set_flash('sukses', 'Selamat datang, ' . $data['nama'] . '!');
            redirect(halaman_awal($data['role']));
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - SiKopdes</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="halaman-login">

<div class="login-kotak">
    <div class="login-kepala">
        <div class="logo logo-besar">SK</div>
        <h1>SiKopdes</h1>
        <p>Sistem Informasi Koperasi Desa<br>Merah Putih Sukowono</p>
    </div>

    <div class="login-badan">
        <?php tampil_flash(); ?>
        <?php if ($error !== ''): ?>
            <div class="flash flash-gagal"><span><?= e($error) ?></span></div>
        <?php endif; ?>

        <form method="post" action="login.php" novalidate>
            <?= csrf_field() ?>
            <div class="form-grup">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?= e($username) ?>" autofocus autocomplete="username">
            </div>
            <div class="form-grup">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-merah btn-penuh">Masuk</button>
        </form>

        <!-- Info akun contoh (hapus bagian ini kalau sudah dipakai sungguhan) -->
        <div class="akun-contoh">
            <strong>Akun contoh</strong>
            <div>Admin: <code>admin</code> / <code>admin123</code></div>
            <div>Kasir: <code>kasir</code> / <code>kasir123</code></div>
        </div>
    </div>
</div>

<script src="assets/js/script.js"></script>
</body>
</html>
