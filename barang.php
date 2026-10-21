<?php
// barang.php - Data Barang/Stok.
// Admin: tambah, ubah, hapus. Kasir: hanya boleh MELIHAT stok.
require_once 'includes/fungsi.php';
wajib_role(['admin', 'kasir']);

$judul = 'Data Barang/Stok';
$halaman_aktif = 'barang.php';
$adalah_admin = ($_SESSION['user']['role'] === 'admin');

// Saran kode barang berikutnya: BRG-016, BRG-017, dst.
function kode_barang_berikutnya() {
    $r = db_satu("SELECT MAX(CAST(SUBSTRING(kode, 5) AS UNSIGNED)) AS maks FROM barang WHERE kode LIKE 'BRG-%'");
    return 'BRG-' . str_pad((int)$r['maks'] + 1, 3, '0', STR_PAD_LEFT);
}

$kategori = daftar_kategori();
$aksi   = $_GET['aksi'] ?? 'daftar';   // daftar | tambah | edit
$errors = [];
$f = ['id' => '', 'kode' => '', 'nama' => '', 'kategori' => 'sembako', 'satuan' => '',
      'harga_beli' => '', 'harga_jual_umum' => '', 'harga_jual_anggota' => '', 'stok' => '0', 'stok_minimum' => '0'];

// Halaman tambah/ubah/hapus hanya untuk admin
if (!$adalah_admin && ($aksi !== 'daftar' || $_SERVER['REQUEST_METHOD'] === 'POST')) {
    set_flash('gagal', 'Kasir hanya boleh melihat data stok.');
    redirect('barang.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        set_flash('gagal', 'Sesi form tidak valid, silakan coba lagi.');
        redirect('barang.php');
    }
    $mode = post('mode');

    /* ----- HAPUS ----- */
    if ($mode === 'hapus') {
        $id = (int) post('id');
        $b  = db_satu("SELECT nama FROM barang WHERE id = $id");
        if (!$b) {
            set_flash('gagal', 'Data barang tidak ditemukan.');
        } else {
            // Barang yang sudah pernah dijual / dipasok tidak boleh dihapus (riwayat bisa rusak)
            $jual  = (int) db_satu("SELECT COUNT(*) AS n FROM detail_penjualan WHERE id_barang = $id")['n'];
            $masuk = (int) db_satu("SELECT COUNT(*) AS n FROM barang_masuk WHERE id_barang = $id")['n'];
            if ($jual > 0 || $masuk > 0) {
                set_flash('gagal', 'Barang "' . $b['nama'] . '" tidak bisa dihapus karena sudah punya riwayat penjualan atau barang masuk.');
            } elseif (db_jalankan('DELETE FROM barang WHERE id = ?', 'i', [$id])) {
                set_flash('sukses', 'Barang "' . $b['nama'] . '" berhasil dihapus.');
            } else {
                set_flash('gagal', 'Gagal menghapus data barang.');
            }
        }
        redirect('barang.php');
    }

    /* ----- SIMPAN (tambah / ubah) ----- */
    if ($mode === 'simpan') {
        $f['id']       = (int) post('id') > 0 ? (int) post('id') : '';
        $f['kode']     = strtoupper(post('kode'));
        $f['nama']     = post('nama');
        $f['kategori'] = post('kategori');
        $f['satuan']   = post('satuan');
        foreach (['harga_beli', 'harga_jual_umum', 'harga_jual_anggota', 'stok', 'stok_minimum'] as $k) {
            $f[$k] = post($k);
        }

        // Validasi input
        if ($f['kode'] === '') {
            $errors[] = 'Kode barang wajib diisi.';
        } elseif (!preg_match('/^[A-Z0-9._-]{1,20}$/', $f['kode'])) {
            $errors[] = 'Kode hanya boleh huruf, angka, titik, strip (maksimal 20 karakter).';
        } else {
            $idcek = $f['id'] === '' ? 0 : (int)$f['id'];
            if (db_satu("SELECT id FROM barang WHERE kode = '" . esc($f['kode']) . "' AND id <> $idcek")) {
                $errors[] = 'Kode barang "' . $f['kode'] . '" sudah dipakai barang lain.';
            }
        }
        if ($f['nama'] === '') { $errors[] = 'Nama barang wajib diisi.'; }
        elseif (mb_strlen($f['nama']) > 100) { $errors[] = 'Nama barang maksimal 100 karakter.'; }
        if (!isset($kategori[$f['kategori']])) { $errors[] = 'Kategori tidak valid.'; }
        if ($f['satuan'] === '') { $errors[] = 'Satuan wajib diisi.'; }
        elseif (mb_strlen($f['satuan']) > 20) { $errors[] = 'Satuan maksimal 20 karakter.'; }

        // Semua angka: wajib diisi, bulat, tidak boleh negatif (angka 0 tetap boleh)
        $label = ['harga_beli' => 'Harga beli', 'harga_jual_umum' => 'Harga jual umum',
                  'harga_jual_anggota' => 'Harga jual anggota', 'stok' => 'Stok', 'stok_minimum' => 'Stok minimum'];
        $angka = [];
        foreach ($label as $k => $nama_label) {
            $angka[$k] = angka_valid($f[$k]);
            if ($angka[$k] === false) {
                $errors[] = "$nama_label harus diisi angka bulat 0 atau lebih (tidak boleh kosong atau negatif).";
            }
        }

        if (count($errors) === 0) {
            if ($f['id'] === '') {
                $ok = db_jalankan('INSERT INTO barang (kode, nama, kategori, satuan, harga_beli, harga_jual_umum, harga_jual_anggota, stok, stok_minimum)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                                  'ssssiiiii', [$f['kode'], $f['nama'], $f['kategori'], $f['satuan'],
                                  $angka['harga_beli'], $angka['harga_jual_umum'], $angka['harga_jual_anggota'], $angka['stok'], $angka['stok_minimum']]);
                set_flash($ok ? 'sukses' : 'gagal', $ok ? 'Barang baru berhasil ditambahkan.' : 'Gagal menyimpan data barang.');
            } else {
                $ok = db_jalankan('UPDATE barang SET kode = ?, nama = ?, kategori = ?, satuan = ?, harga_beli = ?, harga_jual_umum = ?,
                                   harga_jual_anggota = ?, stok = ?, stok_minimum = ? WHERE id = ?',
                                  'ssssiiiiii', [$f['kode'], $f['nama'], $f['kategori'], $f['satuan'],
                                  $angka['harga_beli'], $angka['harga_jual_umum'], $angka['harga_jual_anggota'], $angka['stok'], $angka['stok_minimum'], $f['id']]);
                set_flash($ok ? 'sukses' : 'gagal', $ok ? 'Data barang berhasil diperbarui.' : 'Gagal memperbarui data barang.');
            }
            redirect('barang.php');
        }
        $aksi = $f['id'] === '' ? 'tambah' : 'edit';   // tampilkan form lagi beserta pesan error
    }
} elseif ($aksi === 'edit') {
    $f = db_satu('SELECT * FROM barang WHERE id = ' . (int)($_GET['id'] ?? 0));
    if (!$f) {
        set_flash('gagal', 'Data barang tidak ditemukan.');
        redirect('barang.php');
    }
} elseif ($aksi === 'tambah') {
    $f['kode'] = kode_barang_berikutnya();
}

$tampil_form = ($aksi === 'tambah' || $aksi === 'edit');
$daftar = $tampil_form ? [] : db_semua('SELECT * FROM barang ORDER BY kode ASC');
$jumlah_menipis = 0;
foreach ($daftar as $d) {
    if ((int)$d['stok'] <= (int)$d['stok_minimum']) { $jumlah_menipis++; }
}

include 'includes/header.php';
?>

<?php if ($tampil_form): ?>

    <!-- ===== Form tambah / ubah ===== -->
    <section class="kartu">
        <div class="kartu-judul">
            <h2><?= $aksi === 'edit' ? 'Ubah Data Barang' : 'Tambah Barang Baru' ?></h2>
        </div>
        <?php tampil_error($errors); ?>

        <form method="post" action="barang.php">
            <?= csrf_field() ?>
            <input type="hidden" name="mode" value="simpan">
            <input type="hidden" name="id" value="<?= e($f['id']) ?>">

            <div class="form-grid">
                <div class="form-grup">
                    <label for="kode">Kode Barang</label>
                    <input type="text" id="kode" name="kode" value="<?= e($f['kode']) ?>" maxlength="20" required>
                </div>
                <div class="form-grup">
                    <label for="nama">Nama Barang</label>
                    <input type="text" id="nama" name="nama" value="<?= e($f['nama']) ?>" maxlength="100" required autofocus>
                </div>
                <div class="form-grup">
                    <label for="kategori">Kategori</label>
                    <select id="kategori" name="kategori" required>
                        <?php foreach ($kategori as $nilai => $teks): ?>
                            <option value="<?= e($nilai) ?>" <?= $f['kategori'] === $nilai ? 'selected' : '' ?>><?= e($teks) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grup">
                    <label for="satuan">Satuan</label>
                    <input type="text" id="satuan" name="satuan" value="<?= e($f['satuan']) ?>" maxlength="20" placeholder="kg, liter, pak, sak..." required>
                </div>
                <div class="form-grup">
                    <label for="harga_beli">Harga Beli (Rp)</label>
                    <input type="number" id="harga_beli" name="harga_beli" value="<?= e($f['harga_beli']) ?>" min="0" step="1" required>
                </div>
                <div class="form-grup">
                    <label for="harga_jual_umum">Harga Jual Umum (Rp)</label>
                    <input type="number" id="harga_jual_umum" name="harga_jual_umum" value="<?= e($f['harga_jual_umum']) ?>" min="0" step="1" required>
                </div>
                <div class="form-grup">
                    <label for="harga_jual_anggota">Harga Jual Anggota (Rp)</label>
                    <input type="number" id="harga_jual_anggota" name="harga_jual_anggota" value="<?= e($f['harga_jual_anggota']) ?>" min="0" step="1" required>
                </div>
                <div class="form-grup">
                    <label for="stok">Stok</label>
                    <input type="number" id="stok" name="stok" value="<?= e($f['stok']) ?>" min="0" step="1" required>
                    <small class="teks-abu"><?= $aksi === 'edit' ? 'Mengubah stok di sini = koreksi stok manual. Untuk pasokan baru gunakan menu Barang Masuk.' : 'Stok awal barang.' ?></small>
                </div>
                <div class="form-grup">
                    <label for="stok_minimum">Stok Minimum</label>
                    <input type="number" id="stok_minimum" name="stok_minimum" value="<?= e($f['stok_minimum']) ?>" min="0" step="1" required>
                    <small class="teks-abu">Jika stok &le; batas ini, barang ditandai menipis.</small>
                </div>
            </div>

            <div class="form-tombol">
                <button type="submit" class="btn btn-merah">Simpan</button>
                <a href="barang.php" class="btn btn-abu">Batal</a>
            </div>
        </form>
    </section>

<?php else: ?>

    <!-- ===== Daftar barang ===== -->
    <section class="kartu">
        <div class="toolbar">
            <input type="search" class="input-cari" data-cari="tabelBarang" placeholder="Cari kode, nama, kategori...">
            <?php if ($adalah_admin): ?>
                <a href="barang.php?aksi=tambah" class="btn btn-merah">+ Tambah Barang</a>
            <?php endif; ?>
        </div>

        <?php if ($jumlah_menipis > 0): ?>
            <p class="info-menipis"><?= ikon('alert') ?> <?= $jumlah_menipis ?> barang stoknya menipis atau habis (ditandai warna pada baris).</p>
        <?php endif; ?>

        <div class="tabel-wrap">
            <table class="tabel" id="tabelBarang">
                <thead>
                    <tr>
                        <th>Kode</th><th>Nama Barang</th><th>Kategori</th><th>Satuan</th>
                        <th class="kanan">Harga Beli</th><th class="kanan">Jual Umum</th><th class="kanan">Jual Anggota</th>
                        <th class="kanan">Stok</th><th class="kanan">Min.</th><th>Status</th>
                        <?php if ($adalah_admin): ?><th class="kanan">Aksi</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar as $b): ?>
                        <?php [$teks_status, $kelas_status] = status_stok((int)$b['stok'], (int)$b['stok_minimum']); ?>
                        <tr class="<?= $teks_status !== 'Aman' ? 'baris-menipis' : '' ?>">
                            <td><strong><?= e($b['kode']) ?></strong></td>
                            <td><?= e($b['nama']) ?></td>
                            <td><?= e(ucfirst($b['kategori'])) ?></td>
                            <td><?= e($b['satuan']) ?></td>
                            <td class="kanan"><?= rupiah($b['harga_beli']) ?></td>
                            <td class="kanan"><?= rupiah($b['harga_jual_umum']) ?></td>
                            <td class="kanan"><?= rupiah($b['harga_jual_anggota']) ?></td>
                            <td class="kanan"><strong><?= number_format((int)$b['stok'], 0, ',', '.') ?></strong></td>
                            <td class="kanan"><?= (int)$b['stok_minimum'] ?></td>
                            <td><span class="badge <?= $kelas_status ?>"><?= $teks_status ?></span></td>
                            <?php if ($adalah_admin): ?>
                                <td class="kanan aksi">
                                    <a href="barang.php?aksi=edit&id=<?= (int)$b['id'] ?>" class="btn btn-abu btn-kecil">Ubah</a>
                                    <form method="post" action="barang.php" class="form-inline" data-konfirmasi="Yakin ingin menghapus barang <?= e($b['nama']) ?>?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="mode" value="hapus">
                                        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                        <button type="submit" class="btn btn-hapus btn-kecil">Hapus</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($daftar) === 0): ?>
                        <tr><td colspan="11" class="kosong">Belum ada data barang.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="catatan">Total <?= count($daftar) ?> jenis barang. Kuning = stok menipis, merah = habis.</p>
    </section>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
