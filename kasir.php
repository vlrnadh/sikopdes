<?php
// kasir.php - Kasir toko koperasi (admin & kasir).
// Keranjang dikelola JavaScript (assets/js/kasir.js); halaman ini memvalidasi ulang
// semuanya di server, lalu menyimpan transaksi dan mengurangi stok.
require_once 'includes/fungsi.php';
wajib_role(['admin', 'kasir']);

$judul = 'Kasir';
$halaman_aktif = 'kasir.php';

$keranjang_awal = [];   // dipakai agar keranjang tidak hilang kalau ada error
$anggota_awal   = 0;
$bayar_awal     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        set_flash('gagal', 'Sesi form tidak valid, silakan coba lagi.');
        redirect('kasir.php');
    }

    $anggota_awal = (int) post('id_anggota');
    $bayar_awal   = preg_replace('/\D/', '', post('bayar'));
    $mulai_transaksi = false;

    try {
        // 1. Baca & rapikan isi keranjang (gabungkan barang yang sama)
        $item = json_decode($_POST['keranjang'] ?? '[]', true);
        if (!is_array($item) || count($item) === 0) {
            throw new Exception('Keranjang masih kosong. Pilih barang terlebih dahulu.');
        }
        $gabung = [];
        foreach ($item as $it) {
            $id  = (int) ($it['id'] ?? 0);
            $qty = (int) ($it['qty'] ?? 0);
            if ($id <= 0 || $qty < 1) {
                throw new Exception('Data keranjang tidak valid (jumlah minimal 1).');
            }
            $gabung[$id] = ($gabung[$id] ?? 0) + $qty;
        }
        $keranjang_awal = $gabung;

        // 2. Cek pembeli & uang bayar
        if ($anggota_awal > 0 && !db_satu("SELECT id FROM anggota WHERE id = $anggota_awal")) {
            throw new Exception('Anggota yang dipilih tidak ditemukan.');
        }
        $bayar = angka_valid($bayar_awal);
        if ($bayar === false) {
            throw new Exception('Uang bayar wajib diisi dengan angka (tidak boleh kosong atau negatif).');
        }

        // 3. Mulai transaksi database: semua langkah berhasil, atau dibatalkan semua
        mysqli_begin_transaction($koneksi);
        $mulai_transaksi = true;

        $baris_detail = [];
        $total = 0;
        foreach ($gabung as $id => $qty) {
            // FOR UPDATE mengunci baris barang supaya stok tidak bentrok
            $b = db_satu("SELECT id, nama, stok, harga_beli, harga_jual_umum, harga_jual_anggota FROM barang WHERE id = $id FOR UPDATE");
            if (!$b) {
                throw new Exception('Ada barang di keranjang yang tidak ditemukan.');
            }
            if ($qty > (int)$b['stok']) {
                throw new Exception('Stok "' . $b['nama'] . '" tidak cukup (sisa ' . (int)$b['stok'] . ', diminta ' . $qty . ').');
            }
            // Harga dipilih di server (tidak percaya harga dari browser)
            $harga = $anggota_awal > 0 ? (int)$b['harga_jual_anggota'] : (int)$b['harga_jual_umum'];
            $sub   = $harga * $qty;
            $total += $sub;
            $baris_detail[] = ['id' => $id, 'qty' => $qty, 'beli' => (int)$b['harga_beli'], 'jual' => $harga, 'sub' => $sub];
        }

        if ($bayar < $total) {
            throw new Exception('Uang bayar kurang. Total belanja ' . rupiah($total) . ', uang bayar ' . rupiah($bayar) . '.');
        }
        $kembalian = $bayar - $total;

        // 4. Simpan nota (id_anggota boleh NULL untuk pembeli umum)
        $id_anggota_sql = $anggota_awal > 0 ? $anggota_awal : null;
        $nota_sementara = 'TMP' . uniqid();
        $ok = db_jalankan('INSERT INTO penjualan (no_nota, tanggal, id_anggota, id_user, total, bayar, kembalian) VALUES (?, NOW(), ?, ?, ?, ?, ?)',
                          'siiiii', [$nota_sementara, $id_anggota_sql, $_SESSION['user']['id'], $total, $bayar, $kembalian]);
        if (!$ok) {
            throw new Exception('Gagal menyimpan transaksi.');
        }
        $id_jual = db_id_terakhir();
        $no_nota = 'TRX-' . date('Ymd') . '-' . str_pad($id_jual, 3, '0', STR_PAD_LEFT);
        db_jalankan('UPDATE penjualan SET no_nota = ? WHERE id = ?', 'si', [$no_nota, $id_jual]);

        // 5. Simpan detail barang & kurangi stok
        foreach ($baris_detail as $d) {
            $ok1 = db_jalankan('INSERT INTO detail_penjualan (id_penjualan, id_barang, jumlah, harga_beli, harga_jual, subtotal) VALUES (?, ?, ?, ?, ?, ?)',
                               'iiiiii', [$id_jual, $d['id'], $d['qty'], $d['beli'], $d['jual'], $d['sub']]);
            $ok2 = db_jalankan('UPDATE barang SET stok = stok - ? WHERE id = ?', 'ii', [$d['qty'], $d['id']]);
            if (!$ok1 || !$ok2) {
                throw new Exception('Gagal menyimpan detail transaksi.');
            }
        }

        mysqli_commit($koneksi);
        set_flash('sukses', "Transaksi $no_nota berhasil disimpan. Stok sudah diperbarui.");
        redirect('struk.php?id=' . $id_jual);

    } catch (Exception $ex) {
        if ($mulai_transaksi) {
            mysqli_rollback($koneksi);   // batalkan semua perubahan
        }
        set_flash('gagal', $ex->getMessage());
        // tidak redirect: halaman tampil lagi dengan keranjang yang sama
    }
}

// Data untuk halaman
$barang  = db_semua('SELECT id, kode, nama, kategori, satuan, harga_jual_umum, harga_jual_anggota, stok FROM barang ORDER BY nama ASC');
$anggota = db_semua('SELECT id, no_anggota, nama FROM anggota ORDER BY nama ASC');
$terakhir = db_semua('SELECT p.id, p.no_nota, p.tanggal, p.total, a.nama AS nama_anggota
                      FROM penjualan p LEFT JOIN anggota a ON a.id = p.id_anggota
                      WHERE DATE(p.tanggal) = CURDATE() ORDER BY p.id DESC LIMIT 8');

// Data barang untuk JavaScript (angka dipastikan bertipe int)
$data_js = [];
foreach ($barang as $b) {
    $data_js[] = ['id' => (int)$b['id'], 'kode' => $b['kode'], 'nama' => $b['nama'], 'satuan' => $b['satuan'],
                  'umum' => (int)$b['harga_jual_umum'], 'anggota' => (int)$b['harga_jual_anggota'], 'stok' => (int)$b['stok']];
}
$opsi_json = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;

include 'includes/header.php';
?>

<div class="kasir-grid">

    <!-- ===== Kiri: pilih barang ===== -->
    <section class="kartu">
        <div class="kartu-judul"><h2>1. Pilih Barang</h2></div>
        <input type="search" class="input-cari input-lebar" data-cari="tabelProduk" placeholder="Cari nama / kode barang..." autofocus>

        <div class="tabel-wrap produk-scroll">
            <table class="tabel" id="tabelProduk">
                <thead><tr><th>Barang</th><th class="kanan">Harga</th><th class="kanan">Stok</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($barang as $b): ?>
                        <tr>
                            <td><strong><?= e($b['nama']) ?></strong><br><small class="teks-abu"><?= e($b['kode']) ?> &middot; <?= e($b['satuan']) ?></small></td>
                            <td class="kanan harga-produk" data-id="<?= (int)$b['id'] ?>"><?= rupiah($b['harga_jual_umum']) ?></td>
                            <td class="kanan"><?= (int)$b['stok'] ?></td>
                            <td class="kanan">
                                <?php if ((int)$b['stok'] > 0): ?>
                                    <button type="button" class="btn btn-merah btn-kecil btn-tambah" data-id="<?= (int)$b['id'] ?>">+ Tambah</button>
                                <?php else: ?>
                                    <span class="badge badge-merah">Habis</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ===== Kanan: keranjang & pembayaran ===== -->
    <section class="kartu panel-keranjang">
        <div class="kartu-judul"><h2>2. Keranjang &amp; Pembayaran</h2></div>

        <form method="post" action="kasir.php" id="formKasir">
            <?= csrf_field() ?>
            <input type="hidden" name="keranjang" id="inputKeranjang" value="[]">

            <div class="form-grup">
                <label for="pembeli">Pembeli</label>
                <select id="pembeli" name="id_anggota">
                    <option value="0">Umum (bukan anggota) - harga umum</option>
                    <?php foreach ($anggota as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= $anggota_awal === (int)$a['id'] ? 'selected' : '' ?>>
                            <?= e($a['no_anggota']) ?> - <?= e($a['nama']) ?> (harga anggota)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="tabel-wrap">
                <table class="tabel tabel-keranjang">
                    <thead><tr><th>Barang</th><th>Jumlah</th><th class="kanan">Subtotal</th><th></th></tr></thead>
                    <tbody id="isiKeranjang"></tbody>
                </table>
            </div>

            <div class="ringkasan">
                <div class="ringkasan-baris"><span>Total belanja</span><strong id="teksTotal">Rp 0</strong></div>
                <div class="form-grup">
                    <label for="bayar">Uang bayar (Rp)</label>
                    <div class="bayar-baris">
                        <input type="text" id="bayar" name="bayar" inputmode="numeric" autocomplete="off" placeholder="0" value="<?= e($bayar_awal) ?>">
                        <button type="button" class="btn btn-abu" id="btnPas">Uang pas</button>
                    </div>
                </div>
                <div class="ringkasan-baris"><span>Kembalian</span><strong id="teksKembalian">-</strong></div>
            </div>

            <div id="pesanKasir" class="flash flash-gagal" style="display:none"></div>

            <div class="form-tombol">
                <button type="submit" class="btn btn-merah btn-besar">Simpan Transaksi</button>
                <button type="button" class="btn btn-abu" id="btnKosongkan">Kosongkan</button>
            </div>
        </form>
    </section>
</div>

<!-- ===== Transaksi hari ini ===== -->
<section class="kartu">
    <div class="kartu-judul"><h2>Transaksi Hari Ini</h2><span class="teks-abu">8 terakhir</span></div>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>No. Nota</th><th>Jam</th><th>Pembeli</th><th class="kanan">Total</th><th class="kanan">Struk</th></tr></thead>
            <tbody>
                <?php foreach ($terakhir as $t): ?>
                    <tr>
                        <td><strong><?= e($t['no_nota']) ?></strong></td>
                        <td><?= e(date('H:i', strtotime($t['tanggal']))) ?></td>
                        <td><?= $t['nama_anggota'] ? e($t['nama_anggota']) : '<span class="teks-abu">Umum</span>' ?></td>
                        <td class="kanan"><?= rupiah($t['total']) ?></td>
                        <td class="kanan"><a href="struk.php?id=<?= (int)$t['id'] ?>" class="btn btn-abu btn-kecil">Lihat / Cetak</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (count($terakhir) === 0): ?>
                    <tr><td colspan="5" class="kosong">Belum ada transaksi hari ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
    // Data dari PHP untuk kasir.js
    window.DATA_BARANG = <?= json_encode($data_js, $opsi_json) ?>;
    window.KERANJANG_AWAL = <?= json_encode((object)$keranjang_awal) ?>;
</script>
<script src="assets/js/kasir.js"></script>

<?php include 'includes/footer.php'; ?>
