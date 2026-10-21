<?php
// struk.php - struk belanja yang bisa dicetak (admin & kasir)
require_once 'includes/fungsi.php';
wajib_role(['admin', 'kasir']);

$judul = 'Struk Belanja';
$halaman_aktif = 'kasir.php';

$id = (int) ($_GET['id'] ?? 0);
$p = db_satu("SELECT p.*, a.nama AS nama_anggota, a.no_anggota, u.nama AS nama_kasir
              FROM penjualan p
              LEFT JOIN anggota a ON a.id = p.id_anggota
              LEFT JOIN users u ON u.id = p.id_user
              WHERE p.id = $id");
if (!$p) {
    set_flash('gagal', 'Transaksi tidak ditemukan.');
    redirect('kasir.php');
}
$detail = db_semua("SELECT d.jumlah, d.harga_jual, d.subtotal, b.nama, b.satuan
                    FROM detail_penjualan d JOIN barang b ON b.id = d.id_barang
                    WHERE d.id_penjualan = $id ORDER BY d.id ASC");

include 'includes/header.php';
?>

<div class="tidak-dicetak toolbar">
    <a href="kasir.php" class="btn btn-abu">&larr; Transaksi Baru</a>
    <button type="button" class="btn btn-merah" onclick="window.print()">Cetak Struk</button>
</div>

<div class="struk">
    <div class="struk-kepala">
        <strong>KOPERASI DESA MERAH PUTIH</strong>
        <span>SUKOWONO</span>
        <small>SiKopdes - Toko Koperasi</small>
    </div>

    <div class="struk-info">
        <div><span>No. Nota</span><span><?= e($p['no_nota']) ?></span></div>
        <div><span>Tanggal</span><span><?= e(date('d/m/Y H:i', strtotime($p['tanggal']))) ?></span></div>
        <div><span>Kasir</span><span><?= e($p['nama_kasir']) ?></span></div>
        <div><span>Pembeli</span><span><?= $p['nama_anggota'] ? e($p['nama_anggota']) . ' (' . e($p['no_anggota']) . ')' : 'Umum' ?></span></div>
        <?php if ($p['nama_anggota']): ?>
            <div><span>Jenis harga</span><span>Harga anggota</span></div>
        <?php endif; ?>
    </div>

    <div class="struk-garis"></div>
    <?php foreach ($detail as $d): ?>
        <div class="struk-item">
            <div><?= e($d['nama']) ?></div>
            <div class="struk-baris">
                <span><?= (int)$d['jumlah'] ?> <?= e($d['satuan']) ?> x <?= number_format($d['harga_jual'], 0, ',', '.') ?></span>
                <span><?= number_format($d['subtotal'], 0, ',', '.') ?></span>
            </div>
        </div>
    <?php endforeach; ?>
    <div class="struk-garis"></div>

    <div class="struk-baris struk-tebal"><span>TOTAL</span><span><?= rupiah($p['total']) ?></span></div>
    <div class="struk-baris"><span>Bayar</span><span><?= rupiah($p['bayar']) ?></span></div>
    <div class="struk-baris"><span>Kembali</span><span><?= rupiah($p['kembalian']) ?></span></div>

    <div class="struk-garis"></div>
    <p class="struk-akhir">Terima kasih telah berbelanja<br>di Koperasi Desa Merah Putih Sukowono</p>
</div>

<?php include 'includes/footer.php'; ?>
