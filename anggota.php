<?php
// anggota.php - Data Anggota (CRUD: tambah, lihat, ubah, hapus). Khusus admin.
require_once 'includes/fungsi.php';
wajib_role(['admin']);

$judul = 'Data Anggota';
$halaman_aktif = 'anggota.php';

// Nomor anggota otomatis: AGT-001, AGT-002, dst.
function no_anggota_berikutnya() {
    $r = db_satu("SELECT MAX(CAST(SUBSTRING(no_anggota, 5) AS UNSIGNED)) AS maks FROM anggota");
    return 'AGT-' . str_pad((int)$r['maks'] + 1, 3, '0', STR_PAD_LEFT);
}

$aksi   = $_GET['aksi'] ?? 'daftar';   // daftar | tambah | edit
$errors = [];
$f = ['id' => '', 'no_anggota' => '', 'nama' => '', 'alamat' => '', 'no_hp' => '', 'tgl_bergabung' => date('Y-m-d')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        set_flash('gagal', 'Sesi form tidak valid, silakan coba lagi.');
        redirect('anggota.php');
    }
    $mode = post('mode');

    /* ----- HAPUS ----- */
    if ($mode === 'hapus') {
        $id = (int) post('id');
        $a  = db_satu("SELECT nama FROM anggota WHERE id = $id");
        if (!$a) {
            set_flash('gagal', 'Data anggota tidak ditemukan.');
        } else {
            // Anggota yang sudah punya simpanan / transaksi tidak boleh dihapus
            $simpan = (int) db_satu("SELECT COUNT(*) AS n FROM simpanan WHERE id_anggota = $id")['n'];
            $jual   = (int) db_satu("SELECT COUNT(*) AS n FROM penjualan WHERE id_anggota = $id")['n'];
            if ($simpan > 0 || $jual > 0) {
                set_flash('gagal', 'Anggota "' . $a['nama'] . '" tidak bisa dihapus karena sudah punya riwayat simpanan atau transaksi.');
            } elseif (db_jalankan('DELETE FROM anggota WHERE id = ?', 'i', [$id])) {
                set_flash('sukses', 'Anggota "' . $a['nama'] . '" berhasil dihapus.');
            } else {
                set_flash('gagal', 'Gagal menghapus data anggota.');
            }
        }
        redirect('anggota.php');
    }

    /* ----- SIMPAN (tambah / ubah) ----- */
    if ($mode === 'simpan') {
        $f['id']            = (int) post('id') > 0 ? (int) post('id') : '';
        $f['nama']          = post('nama');
        $f['alamat']        = post('alamat');
        $f['no_hp']         = post('no_hp');
        $f['tgl_bergabung'] = post('tgl_bergabung');

        // Validasi input
        if ($f['nama'] === '')   { $errors[] = 'Nama wajib diisi.'; }
        elseif (mb_strlen($f['nama']) > 100) { $errors[] = 'Nama maksimal 100 karakter.'; }
        if ($f['alamat'] === '') { $errors[] = 'Alamat/Dusun wajib diisi.'; }
        elseif (mb_strlen($f['alamat']) > 200) { $errors[] = 'Alamat maksimal 200 karakter.'; }
        if ($f['no_hp'] === '')  { $errors[] = 'No. HP wajib diisi.'; }
        elseif (!preg_match('/^[0-9+]{8,15}$/', $f['no_hp'])) { $errors[] = 'No. HP hanya boleh angka (8-15 digit).'; }
        if (!tanggal_valid($f['tgl_bergabung'])) { $errors[] = 'Tanggal bergabung tidak valid.'; }

        if (count($errors) === 0) {
            if ($f['id'] === '') {
                $no = no_anggota_berikutnya();
                $ok = db_jalankan('INSERT INTO anggota (no_anggota, nama, alamat, no_hp, tgl_bergabung) VALUES (?, ?, ?, ?, ?)',
                                  'sssss', [$no, $f['nama'], $f['alamat'], $f['no_hp'], $f['tgl_bergabung']]);
                set_flash($ok ? 'sukses' : 'gagal', $ok ? "Anggota baru berhasil ditambahkan dengan nomor $no." : 'Gagal menyimpan data anggota.');
            } else {
                $ok = db_jalankan('UPDATE anggota SET nama = ?, alamat = ?, no_hp = ?, tgl_bergabung = ? WHERE id = ?',
                                  'ssssi', [$f['nama'], $f['alamat'], $f['no_hp'], $f['tgl_bergabung'], $f['id']]);
                set_flash($ok ? 'sukses' : 'gagal', $ok ? 'Data anggota berhasil diperbarui.' : 'Gagal memperbarui data anggota.');
            }
            redirect('anggota.php');
        }
        // Kalau ada error, form ditampilkan lagi dengan isian sebelumnya
        $aksi = $f['id'] === '' ? 'tambah' : 'edit';
        if ($aksi === 'edit') {
            $f['no_anggota'] = db_satu('SELECT no_anggota FROM anggota WHERE id = ' . (int)$f['id'])['no_anggota'] ?? '';
        }
    }
} elseif ($aksi === 'edit') {
    $f = db_satu('SELECT * FROM anggota WHERE id = ' . (int)($_GET['id'] ?? 0));
    if (!$f) {
        set_flash('gagal', 'Data anggota tidak ditemukan.');
        redirect('anggota.php');
    }
}

$tampil_form = ($aksi === 'tambah' || $aksi === 'edit');
if ($tampil_form && $f['no_anggota'] === '') {
    $f['no_anggota'] = no_anggota_berikutnya();   // hanya tampilan, nomor final dibuat saat simpan
}
$daftar = $tampil_form ? [] : db_semua('SELECT * FROM anggota ORDER BY no_anggota ASC');

include 'includes/header.php';
?>

<?php if ($tampil_form): ?>

    <!-- ===== Form tambah / ubah ===== -->
    <section class="kartu">
        <div class="kartu-judul">
            <h2><?= $aksi === 'edit' ? 'Ubah Data Anggota' : 'Tambah Anggota Baru' ?></h2>
        </div>
        <?php tampil_error($errors); ?>

        <form method="post" action="anggota.php">
            <?= csrf_field() ?>
            <input type="hidden" name="mode" value="simpan">
            <input type="hidden" name="id" value="<?= e($f['id']) ?>">

            <div class="form-grid">
                <div class="form-grup">
                    <label>No. Anggota (otomatis)</label>
                    <input type="text" value="<?= e($f['no_anggota']) ?>" readonly class="input-readonly">
                </div>
                <div class="form-grup">
                    <label for="tgl_bergabung">Tanggal Bergabung</label>
                    <input type="date" id="tgl_bergabung" name="tgl_bergabung" value="<?= e($f['tgl_bergabung']) ?>" required>
                </div>
                <div class="form-grup">
                    <label for="nama">Nama Lengkap</label>
                    <input type="text" id="nama" name="nama" value="<?= e($f['nama']) ?>" maxlength="100" required autofocus>
                </div>
                <div class="form-grup">
                    <label for="no_hp">No. HP</label>
                    <input type="text" id="no_hp" name="no_hp" value="<?= e($f['no_hp']) ?>" maxlength="15" inputmode="numeric" placeholder="08xxxxxxxxxx" required>
                </div>
                <div class="form-grup form-penuh">
                    <label for="alamat">Alamat / Dusun</label>
                    <input type="text" id="alamat" name="alamat" value="<?= e($f['alamat']) ?>" maxlength="200" placeholder="Contoh: Dusun Krajan RT 01/RW 01" required>
                </div>
            </div>

            <div class="form-tombol">
                <button type="submit" class="btn btn-merah">Simpan</button>
                <a href="anggota.php" class="btn btn-abu">Batal</a>
            </div>
        </form>
    </section>

<?php else: ?>

    <!-- ===== Daftar anggota ===== -->
    <section class="kartu">
        <div class="toolbar">
            <input type="search" class="input-cari" data-cari="tabelAnggota" placeholder="Cari nama, no. anggota, dusun...">
            <a href="anggota.php?aksi=tambah" class="btn btn-merah">+ Tambah Anggota</a>
        </div>

        <div class="tabel-wrap">
            <table class="tabel" id="tabelAnggota">
                <thead>
                    <tr>
                        <th>No. Anggota</th><th>Nama</th><th>Alamat / Dusun</th><th>No. HP</th><th>Bergabung</th><th class="kanan">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar as $a): ?>
                        <tr>
                            <td><strong><?= e($a['no_anggota']) ?></strong></td>
                            <td><?= e($a['nama']) ?></td>
                            <td><?= e($a['alamat']) ?></td>
                            <td><?= e($a['no_hp']) ?></td>
                            <td><?= e(tanggal_indo($a['tgl_bergabung'])) ?></td>
                            <td class="kanan aksi">
                                <a href="anggota.php?aksi=edit&id=<?= (int)$a['id'] ?>" class="btn btn-abu btn-kecil">Ubah</a>
                                <form method="post" action="anggota.php" class="form-inline" data-konfirmasi="Yakin ingin menghapus anggota <?= e($a['nama']) ?>?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="mode" value="hapus">
                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                    <button type="submit" class="btn btn-hapus btn-kecil">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($daftar) === 0): ?>
                        <tr><td colspan="6" class="kosong">Belum ada data anggota.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="catatan">Total <?= count($daftar) ?> anggota.</p>
    </section>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
