<?php
// index.php - Dashboard (khusus admin)
require_once 'includes/fungsi.php';
wajib_role(['admin']);

$judul = 'Dashboard';
$halaman_aktif = 'index.php';

/* ---------- Data kartu ringkasan ---------- */
$total_anggota = (int) db_satu('SELECT COUNT(*) AS n FROM anggota')['n'];
$jenis_barang  = (int) db_satu('SELECT COUNT(*) AS n FROM barang')['n'];
$hari_ini      = db_satu('SELECT COALESCE(SUM(total), 0) AS jumlah, COUNT(*) AS trx FROM penjualan WHERE DATE(tanggal) = CURDATE()');
$total_simpan  = (int) db_satu('SELECT COALESCE(SUM(jumlah), 0) AS n FROM simpanan')['n'];

/* ---------- Barang stok menipis (stok <= batas minimum) ---------- */
$stok_menipis = db_semua('SELECT kode, nama, kategori, satuan, stok, stok_minimum
                          FROM barang WHERE stok <= stok_minimum
                          ORDER BY stok ASC, nama ASC');

/* ---------- Grafik penjualan 7 hari terakhir ---------- */
// Ambil total penjualan per hari
$baris = db_semua('SELECT DATE(tanggal) AS tgl, SUM(total) AS jumlah
                   FROM penjualan
                   WHERE DATE(tanggal) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                   GROUP BY DATE(tanggal)');
$per_hari = [];
foreach ($baris as $b) {
    $per_hari[$b['tgl']] = (int) $b['jumlah'];
}

// Susun 7 hari (hari yang tidak ada transaksi diisi 0)
$grafik = [];
$maks = 0;
for ($i = 6; $i >= 0; $i--) {
    $tgl = date('Y-m-d', strtotime("-$i day"));
    $nilai = $per_hari[$tgl] ?? 0;
    $grafik[] = ['tgl' => $tgl, 'nilai' => $nilai];
    if ($nilai > $maks) {
        $maks = $nilai;
    }
}
$total_7_hari = array_sum(array_column($grafik, 'nilai'));

include 'includes/header.php';
?>

<!-- Kartu ringkasan -->
<section class="kartu-grid">
    <div class="kartu-stat">
        <div class="stat-ikon"><?= ikon('users') ?></div>
        <div>
            <span class="stat-label">Total Anggota</span>
            <strong class="stat-nilai"><?= number_format($total_anggota, 0, ',', '.') ?></strong>
        </div>
    </div>
    <div class="kartu-stat">
        <div class="stat-ikon"><?= ikon('package') ?></div>
        <div>
            <span class="stat-label">Jenis Barang</span>
            <strong class="stat-nilai"><?= number_format($jenis_barang, 0, ',', '.') ?></strong>
        </div>
    </div>
    <div class="kartu-stat">
        <div class="stat-ikon"><?= ikon('cart') ?></div>
        <div>
            <span class="stat-label">Penjualan Hari Ini</span>
            <strong class="stat-nilai"><?= rupiah($hari_ini['jumlah']) ?></strong>
            <small class="stat-ket"><?= (int) $hari_ini['trx'] ?> transaksi</small>
        </div>
    </div>
    <div class="kartu-stat">
        <div class="stat-ikon"><?= ikon('uang') ?></div>
        <div>
            <span class="stat-label">Total Simpanan</span>
            <strong class="stat-nilai"><?= rupiah($total_simpan) ?></strong>
        </div>
    </div>
</section>

<div class="dua-kolom">
    <!-- Grafik batang 7 hari (dibuat dengan CSS, tanpa library) -->
    <section class="kartu">
        <div class="kartu-judul">
            <h2>Penjualan 7 Hari Terakhir</h2>
            <span class="teks-abu">Total: <?= rupiah($total_7_hari) ?></span>
        </div>
        <div class="grafik">
            <?php foreach ($grafik as $g): ?>
                <?php
                    $persen = $maks > 0 ? round($g['nilai'] / $maks * 100) : 0;
                    $ini_hari_ini = ($g['tgl'] === date('Y-m-d'));
                ?>
                <div class="grafik-kolom" title="<?= e(tanggal_indo($g['tgl'])) ?>: <?= e(rupiah($g['nilai'])) ?>">
                    <span class="grafik-nilai"><?= $g['nilai'] > 0 ? e(number_format($g['nilai'] / 1000, 0, ',', '.')) . 'rb' : '0' ?></span>
                    <div class="grafik-area">
                        <div class="grafik-batang <?= $ini_hari_ini ? 'hari-ini' : '' ?>" style="height: <?= max($persen, 2) ?>%"></div>
                    </div>
                    <span class="grafik-label"><?= e(hari_singkat($g['tgl'])) ?><br><small><?= e(date('d/m', strtotime($g['tgl']))) ?></small></span>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="catatan">Angka di atas batang dalam ribuan rupiah (rb). Batang merah tua = hari ini.</p>
    </section>

    <!-- Daftar stok menipis -->
    <section class="kartu">
        <div class="kartu-judul">
            <h2>Stok Menipis</h2>
            <span class="badge badge-merah"><?= count($stok_menipis) ?> barang</span>
        </div>

        <?php if (count($stok_menipis) === 0): ?>
            <p class="kosong">Semua stok barang masih aman.</p>
        <?php else: ?>
            <div class="tabel-wrap">
                <table class="tabel">
                    <thead>
                        <tr><th>Barang</th><th class="kanan">Stok</th><th class="kanan">Min.</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stok_menipis as $s): ?>
                            <tr class="baris-menipis">
                                <td>
                                    <strong><?= e($s['nama']) ?></strong><br>
                                    <small class="teks-abu"><?= e($s['kode']) ?> &middot; <?= e(ucfirst($s['kategori'])) ?></small>
                                </td>
                                <td class="kanan"><strong><?= (int) $s['stok'] ?></strong> <small><?= e($s['satuan']) ?></small></td>
                                <td class="kanan"><?= (int) $s['stok_minimum'] ?></td>
                                <td>
                                    <?php if ((int) $s['stok'] === 0): ?>
                                        <span class="badge badge-merah">Habis</span>
                                    <?php else: ?>
                                        <span class="badge badge-kuning">Menipis</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
