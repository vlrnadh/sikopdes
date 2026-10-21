<?php
// barang_masuk.php - catat pasokan barang yang menambah stok. Khusus admin.
require_once 'includes/fungsi.php';
wajib_role(['admin']);

$judul = 'Barang Masuk';
$halaman_aktif = 'barang_masuk.php';

$errors = [];
$f = ['tanggal' => date('Y-m-d'), 'id_barang' => '', 'jumlah' => '', 'harga_beli' => '', 'pemasok' => '', 'update_harga' => '1'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        set_flash('gagal', 'Sesi form tidak valid, silakan coba lagi.');
        redirect('barang_masuk.php');
    }
    $f['tanggal']      = post('tanggal');
    $f['id_barang']    = post('id_barang');
    $f['jumlah']       = post('jumlah');
    $f['harga_beli']   = post('harga_beli');
    $f['pemasok']      = post('pemasok');
    $f['update_harga'] = isset($_POST['update_harga']) ? '1' : '0';

    // Validasi input
    if (!tanggal_valid($f['tanggal'])) { $errors[] = 'Tanggal tidak valid.'; }
    $id_barang = (int) $f['id_barang'];
    $barang = $id_barang > 0 ? db_satu("SELECT id, nama FROM barang WHERE id = $id_barang") : null;
    if (!$barang) { $errors[] = 'Pilih barang terlebih dahulu.'; }

    $jumlah = angka_valid($f['jumlah']);
    if ($jumlah === false || $jumlah < 1) { $errors[] = 'Jumlah harus angka bulat minimal 1.'; }
    $harga_beli = angka_valid($f['harga_beli']);
    if ($harga_beli === false) { $errors[] = 'Harga beli harus diisi angka bulat 0 atau lebih (tidak boleh kosong atau negatif).'; }
    if ($f['pemasok'] === '') { $errors[] = 'Nama pemasok wajib diisi.'; }
    elseif (mb_strlen($f['pemasok']) > 100) { $errors[] = 'Nama pemasok maksimal 100 karakter.'; }

    if (count($errors) === 0) {
        // Transaksi database: catat barang masuk DAN tambah stok, atau batal semuanya
        mysqli_begin_transaction($koneksi);
        $ok1 = db_jalankan('INSERT INTO barang_masuk (tanggal, id_barang, jumlah, harga_beli, pemasok, id_user) VALUES (?, ?, ?, ?, ?, ?)',
                           'siiisi', [$f['tanggal'], $id_barang, $jumlah, $harga_beli, $f['pemasok'], $_SESSION['user']['id']]);
        if ($f['update_harga'] === '1') {
            $ok2 = db_jalankan('UPDATE barang SET stok = stok + ?, harga_beli = ? WHERE id = ?', 'iii', [$jumlah, $harga_beli, $id_barang]);
        } else {
            $ok2 = db_jalankan('UPDATE barang SET stok = stok + ? WHERE id = ?', 'ii', [$jumlah, $id_barang]);
        }
        if ($ok1 && $ok2) {
            mysqli_commit($koneksi);
            set_flash('sukses', "Barang masuk dicatat: {$barang['nama']} +$jumlah. Stok sudah bertambah.");
            redirect('barang_masuk.php');
        }
        mysqli_rollback($koneksi);
        $errors[] = 'Terjadi kesalahan database, data tidak disimpan.';
    }
}

$daftar_barang = db_semua('SELECT id, kode, nama, satuan, stok, harga_beli FROM barang ORDER BY nama ASC');
$riwayat = db_semua('SELECT m.*, b.kode, b.nama, b.satuan, u.nama AS petugas
                     FROM barang_masuk m
                     JOIN barang b ON b.id = m.id_barang
                     JOIN users u ON u.id = m.id_user
                     ORDER BY m.tanggal DESC, m.id DESC LIMIT 200');

include 'includes/header.php';
?>

<section class="kartu">
    <div class="kartu-judul"><h2>Catat Barang Masuk</h2></div>
    <?php tampil_error($errors); ?>

    <form method="post" action="barang_masuk.php">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="form-grup">
                <label for="tanggal">Tanggal Masuk</label>
                <input type="date" id="tanggal" name="tanggal" value="<?= e($f['tanggal']) ?>" required>
            </div>
            <div class="form-grup">
                <label for="id_barang">Barang</label>
                <select id="id_barang" name="id_barang" required>
                    <option value="">-- Pilih barang --</option>
                    <?php foreach ($daftar_barang as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" data-beli="<?= (int)$b['harga_beli'] ?>" <?= (string)$b['id'] === $f['id_barang'] ? 'selected' : '' ?>>
                            <?= e($b['kode']) ?> - <?= e($b['nama']) ?> (stok <?= (int)$b['stok'] ?> <?= e($b['satuan']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-grup">
                <label for="jumlah">Jumlah Masuk</label>
                <input type="number" id="jumlah" name="jumlah" value="<?= e($f['jumlah']) ?>" min="1" step="1" required>
            </div>
            <div class="form-grup">
                <label for="harga_beli">Harga Beli Satuan (Rp)</label>
                <input type="number" id="harga_beli" name="harga_beli" value="<?= e($f['harga_beli']) ?>" min="0" step="1" required>
            </div>
            <div class="form-grup form-penuh">
                <label for="pemasok">Pemasok</label>
                <input type="text" id="pemasok" name="pemasok" value="<?= e($f['pemasok']) ?>" maxlength="100" placeholder="Contoh: CV Sumber Pangan" required>
            </div>
            <div class="form-grup form-penuh">
                <label class="cek-baris">
                    <input type="checkbox" name="update_harga" value="1" <?= $f['update_harga'] === '1' ? 'checked' : '' ?>>
                    Perbarui harga beli di Data Barang dengan harga ini (dipakai untuk menghitung laba transaksi berikutnya)
                </label>
            </div>
        </div>
        <div class="form-tombol">
            <button type="submit" class="btn btn-merah">Simpan &amp; Tambah Stok</button>
        </div>
    </form>
</section>

<section class="kartu">
    <div class="toolbar">
        <h2 class="judul-toolbar">Riwayat Barang Masuk</h2>
        <input type="search" class="input-cari" data-cari="tabelMasuk" placeholder="Cari barang / pemasok...">
    </div>
    <div class="tabel-wrap">
        <table class="tabel" id="tabelMasuk">
            <thead>
                <tr><th>Tanggal</th><th>Barang</th><th class="kanan">Jumlah</th><th class="kanan">Harga Beli</th><th class="kanan">Total</th><th>Pemasok</th><th>Dicatat Oleh</th></tr>
            </thead>
            <tbody>
                <?php foreach ($riwayat as $r): ?>
                    <tr>
                        <td><?= e(tanggal_indo($r['tanggal'])) ?></td>
                        <td><strong><?= e($r['nama']) ?></strong><br><small class="teks-abu"><?= e($r['kode']) ?></small></td>
                        <td class="kanan"><?= number_format($r['jumlah'], 0, ',', '.') ?> <small><?= e($r['satuan']) ?></small></td>
                        <td class="kanan"><?= rupiah($r['harga_beli']) ?></td>
                        <td class="kanan"><?= rupiah($r['harga_beli'] * $r['jumlah']) ?></td>
                        <td><?= e($r['pemasok']) ?></td>
                        <td><?= e($r['petugas']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (count($riwayat) === 0): ?>
                    <tr><td colspan="7" class="kosong">Belum ada data barang masuk.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="catatan">Menampilkan maksimal 200 catatan terbaru.</p>
</section>

<script>
    // Saat barang dipilih, isi otomatis kolom harga beli dengan harga beli terakhir
    (function () {
        var pilih = document.getElementById('id_barang');
        var harga = document.getElementById('harga_beli');
        pilih.addEventListener('change', function () {
            var opsi = pilih.options[pilih.selectedIndex];
            if (opsi && opsi.dataset.beli && harga.value === '') { harga.value = opsi.dataset.beli; }
        });
    })();
</script>

<?php include 'includes/footer.php'; ?>
