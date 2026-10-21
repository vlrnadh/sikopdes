-- ============================================================
-- database.sql  |  SiKopdes - Sistem Informasi Koperasi Desa
-- Koperasi Desa Merah Putih Sukowono
-- Cara pakai: phpMyAdmin > tab Import > pilih file ini > Go
-- Akun contoh  : admin / admin123   dan   kasir / kasir123
-- ============================================================

CREATE DATABASE IF NOT EXISTS db_sikopdes CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE db_sikopdes;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS simpanan;
DROP TABLE IF EXISTS barang_masuk;
DROP TABLE IF EXISTS detail_penjualan;
DROP TABLE IF EXISTS penjualan;
DROP TABLE IF EXISTS barang;
DROP TABLE IF EXISTS anggota;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Tabel pengguna (admin dan kasir)
-- ------------------------------------------------------------
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,              -- disimpan dalam bentuk hash
  role ENUM('admin','kasir') NOT NULL DEFAULT 'kasir',
  dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabel anggota koperasi
-- ------------------------------------------------------------
CREATE TABLE anggota (
  id INT AUTO_INCREMENT PRIMARY KEY,
  no_anggota VARCHAR(20) NOT NULL UNIQUE,      -- otomatis, contoh: AGT-001
  nama VARCHAR(100) NOT NULL,
  alamat VARCHAR(200) NOT NULL,                -- alamat / dusun
  no_hp VARCHAR(20) NOT NULL,
  tgl_bergabung DATE NOT NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabel barang / stok toko koperasi
-- ------------------------------------------------------------
CREATE TABLE barang (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(20) NOT NULL UNIQUE,
  nama VARCHAR(100) NOT NULL,
  kategori ENUM('sembako','pertanian','lainnya') NOT NULL DEFAULT 'sembako',
  satuan VARCHAR(20) NOT NULL,
  harga_beli INT NOT NULL DEFAULT 0,
  harga_jual_umum INT NOT NULL DEFAULT 0,
  harga_jual_anggota INT NOT NULL DEFAULT 0,
  stok INT NOT NULL DEFAULT 0,
  stok_minimum INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabel penjualan (header nota). id_anggota NULL = pembeli umum
-- ------------------------------------------------------------
CREATE TABLE penjualan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  no_nota VARCHAR(30) NOT NULL UNIQUE,
  tanggal DATETIME NOT NULL,
  id_anggota INT NULL,
  id_user INT NOT NULL,                        -- kasir yang melayani
  total INT NOT NULL DEFAULT 0,
  bayar INT NOT NULL DEFAULT 0,
  kembalian INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_penjualan_anggota FOREIGN KEY (id_anggota) REFERENCES anggota(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_penjualan_user FOREIGN KEY (id_user) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabel detail penjualan (isi nota)
-- harga_beli disimpan untuk menghitung laba kotor
-- ------------------------------------------------------------
CREATE TABLE detail_penjualan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_penjualan INT NOT NULL,
  id_barang INT NOT NULL,
  jumlah INT NOT NULL,
  harga_beli INT NOT NULL,
  harga_jual INT NOT NULL,
  subtotal INT NOT NULL,
  CONSTRAINT fk_detail_penjualan FOREIGN KEY (id_penjualan) REFERENCES penjualan(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_detail_barang FOREIGN KEY (id_barang) REFERENCES barang(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabel barang masuk (pasokan yang menambah stok)
-- ------------------------------------------------------------
CREATE TABLE barang_masuk (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATE NOT NULL,
  id_barang INT NOT NULL,
  jumlah INT NOT NULL,
  harga_beli INT NOT NULL,
  pemasok VARCHAR(100) NOT NULL,
  id_user INT NOT NULL,
  CONSTRAINT fk_masuk_barang FOREIGN KEY (id_barang) REFERENCES barang(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_masuk_user FOREIGN KEY (id_user) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabel simpanan anggota (pokok, wajib, sukarela)
-- ------------------------------------------------------------
CREATE TABLE simpanan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_anggota INT NOT NULL,
  jenis ENUM('pokok','wajib','sukarela') NOT NULL,
  jumlah INT NOT NULL,
  tanggal DATE NOT NULL,
  keterangan VARCHAR(150) NULL,
  id_user INT NOT NULL,
  CONSTRAINT fk_simpanan_anggota FOREIGN KEY (id_anggota) REFERENCES anggota(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_simpanan_user FOREIGN KEY (id_user) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- DATA CONTOH
-- ============================================================

-- Pengguna (password: admin123 dan kasir123, sudah di-hash)
INSERT INTO users (id, nama, username, password, role) VALUES
(1, 'Administrator Kopdes', 'admin', '$2y$10$ceiG4gwS.CR.itUSd1ppSuBFAxUYBS09PWZRFARiW/v75RN38zBGi', 'admin'),
(2, 'Petugas Kasir', 'kasir', '$2y$10$bFkCxXToo1UJJJ4tsR9Ikumj6KWvq18qu0WcdjtFsA0EBXwVVnvF6', 'kasir');

-- Anggota
INSERT INTO anggota (id, no_anggota, nama, alamat, no_hp, tgl_bergabung) VALUES
(1, 'AGT-001', 'Slamet Riyadi', 'Dusun Krajan RT 01/RW 01', '081234567801', '2024-01-15'),
(2, 'AGT-002', 'Siti Aminah', 'Dusun Krajan RT 03/RW 01', '081234567802', '2024-01-15'),
(3, 'AGT-003', 'Suparman', 'Dusun Karang Anyar RT 02/RW 02', '081234567803', '2024-02-10'),
(4, 'AGT-004', 'Wahyuni', 'Dusun Karang Anyar RT 04/RW 02', '081234567804', '2024-03-05'),
(5, 'AGT-005', 'Hasan Basri', 'Dusun Mojosari RT 01/RW 03', '081234567805', '2024-04-20'),
(6, 'AGT-006', 'Nur Hidayati', 'Dusun Mojosari RT 02/RW 03', '081234567806', '2024-06-12'),
(7, 'AGT-007', 'Mulyono', 'Dusun Sumberjo RT 01/RW 04', '081234567807', '2024-09-01'),
(8, 'AGT-008', 'Khoirul Anam', 'Dusun Sumberjo RT 03/RW 04', '081234567808', '2025-01-18'),
(9, 'AGT-009', 'Sri Wahyuningsih', 'Dusun Kebonsari RT 02/RW 05', '081234567809', '2025-05-07'),
(10, 'AGT-010', 'Agus Salim', 'Dusun Kebonsari RT 04/RW 05', '081234567810', '2025-08-25');

-- Barang (stok = stok setelah semua transaksi & barang masuk contoh)
INSERT INTO barang (id, kode, nama, kategori, satuan, harga_beli, harga_jual_umum, harga_jual_anggota, stok, stok_minimum) VALUES
(1, 'BRG-001', 'Beras Premium 5 kg', 'sembako', 'pak', 62000, 68000, 65000, 42, 10),
(2, 'BRG-002', 'Minyak Goreng 1 Liter', 'sembako', 'liter', 15500, 17500, 16500, 15, 15),
(3, 'BRG-003', 'Gula Pasir 1 kg', 'sembako', 'kg', 15000, 17000, 16000, 14, 15),
(4, 'BRG-004', 'Telur Ayam', 'sembako', 'kg', 26000, 29000, 27500, 21, 10),
(5, 'BRG-005', 'Tepung Terigu 1 kg', 'sembako', 'kg', 11000, 13000, 12000, 9, 10),
(6, 'BRG-006', 'Mie Instan Goreng', 'sembako', 'bungkus', 2800, 3500, 3200, 165, 40),
(7, 'BRG-007', 'Kopi Bubuk 200 gr', 'sembako', 'bungkus', 14000, 17000, 15500, 29, 10),
(8, 'BRG-008', 'Garam Halus', 'sembako', 'bungkus', 2500, 3500, 3000, 55, 20),
(9, 'BRG-009', 'Pupuk Urea 50 kg', 'pertanian', 'sak', 265000, 290000, 280000, 27, 5),
(10, 'BRG-010', 'Pupuk NPK Phonska 50 kg', 'pertanian', 'sak', 285000, 310000, 300000, 4, 5),
(11, 'BRG-011', 'Pestisida Cair 500 ml', 'pertanian', 'botol', 48000, 58000, 53000, 22, 6),
(12, 'BRG-012', 'Benih Jagung Hibrida 1 kg', 'pertanian', 'kg', 62000, 75000, 69000, 13, 5),
(13, 'BRG-013', 'Sabit Baja', 'pertanian', 'buah', 35000, 45000, 40000, 3, 3),
(14, 'BRG-014', 'Sabun Mandi Batang', 'lainnya', 'buah', 2800, 3800, 3300, 53, 20),
(15, 'BRG-015', 'Deterjen Bubuk 800 gr', 'lainnya', 'bungkus', 15000, 18000, 16500, 27, 10);

-- Penjualan (tanggal dibuat relatif terhadap hari ini supaya grafik dashboard terisi)
INSERT INTO penjualan (id, no_nota, tanggal, id_anggota, id_user, total, bayar, kembalian) VALUES
(1, 'TMP-1', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '08:15:00'), 1, 2, 211500, 215000, 3500),
(2, 'TMP-2', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '10:40:00'), NULL, 2, 42000, 45000, 3000),
(3, 'TMP-3', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:05:00'), 3, 2, 613000, 615000, 2000),
(4, 'TMP-4', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '15:30:00'), 2, 2, 94500, 95000, 500),
(5, 'TMP-5', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '07:50:00'), NULL, 2, 103000, 105000, 2000),
(6, 'TMP-6', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '11:20:00'), 5, 1, 1078000, 1080000, 2000),
(7, 'TMP-7', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '16:10:00'), 6, 2, 127200, 130000, 2800),
(8, 'TMP-8', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:30:00'), NULL, 2, 89400, 90000, 600),
(9, 'TMP-9', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '13:45:00'), 8, 2, 254500, 255000, 500),
(10, 'TMP-10', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:40:00'), 4, 2, 880000, 880000, 0),
(11, 'TMP-11', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '14:15:00'), NULL, 2, 131500, 135000, 3500),
(12, 'TMP-12', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 0 DAY), '08:20:00'), 7, 2, 172000, 175000, 3000),
(13, 'TMP-13', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 0 DAY), '10:05:00'), NULL, 2, 81500, 85000, 3500),
(14, 'TMP-14', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 0 DAY), '11:30:00'), 9, 2, 130000, 130000, 0);
-- Nomor nota: TRX-TanggalBulanTahun-NomorUrut
UPDATE penjualan SET no_nota = CONCAT('TRX-', DATE_FORMAT(tanggal, '%Y%m%d'), '-', LPAD(id, 3, '0'));

-- Detail penjualan
INSERT INTO detail_penjualan (id_penjualan, id_barang, jumlah, harga_beli, harga_jual, subtotal) VALUES
(1, 1, 2, 62000, 65000, 130000),
(1, 2, 3, 15500, 16500, 49500),
(1, 3, 2, 15000, 16000, 32000),
(2, 6, 10, 2800, 3500, 35000),
(2, 8, 2, 2500, 3500, 7000),
(3, 9, 2, 265000, 280000, 560000),
(3, 11, 1, 48000, 53000, 53000),
(4, 4, 2, 26000, 27500, 55000),
(4, 5, 2, 11000, 12000, 24000),
(4, 7, 1, 14000, 15500, 15500),
(5, 1, 1, 62000, 68000, 68000),
(5, 2, 2, 15500, 17500, 35000),
(6, 10, 3, 285000, 300000, 900000),
(6, 12, 2, 62000, 69000, 138000),
(6, 13, 1, 35000, 40000, 40000),
(7, 2, 4, 15500, 16500, 66000),
(7, 3, 3, 15000, 16000, 48000),
(7, 14, 4, 2800, 3300, 13200),
(8, 15, 2, 15000, 18000, 36000),
(8, 14, 3, 2800, 3800, 11400),
(8, 6, 12, 2800, 3500, 42000),
(9, 1, 3, 62000, 65000, 195000),
(9, 3, 2, 15000, 16000, 32000),
(9, 4, 1, 26000, 27500, 27500),
(10, 9, 1, 265000, 280000, 280000),
(10, 10, 2, 285000, 300000, 600000),
(11, 2, 3, 15500, 17500, 52500),
(11, 3, 3, 15000, 17000, 51000),
(11, 6, 8, 2800, 3500, 28000),
(12, 1, 2, 62000, 65000, 130000),
(12, 2, 2, 15500, 16500, 33000),
(12, 8, 3, 2500, 3000, 9000),
(13, 6, 15, 2800, 3500, 52500),
(13, 4, 1, 26000, 29000, 29000),
(14, 3, 4, 15000, 16000, 64000),
(14, 2, 3, 15500, 16500, 49500),
(14, 15, 1, 15000, 16500, 16500);

-- Barang masuk
INSERT INTO barang_masuk (tanggal, id_barang, jumlah, harga_beli, pemasok, id_user) VALUES
(DATE_SUB(CURDATE(), INTERVAL 6 DAY), 1, 20, 62000, 'UD Tani Makmur', 1),
(DATE_SUB(CURDATE(), INTERVAL 5 DAY), 3, 10, 15000, 'CV Sumber Pangan', 1),
(DATE_SUB(CURDATE(), INTERVAL 4 DAY), 9, 10, 265000, 'KUD Sukowono', 1),
(DATE_SUB(CURDATE(), INTERVAL 3 DAY), 6, 60, 2800, 'CV Sumber Pangan', 1),
(DATE_SUB(CURDATE(), INTERVAL 2 DAY), 2, 12, 15500, 'CV Sumber Pangan', 1),
(DATE_SUB(CURDATE(), INTERVAL 1 DAY), 11, 8, 48000, 'UD Tani Makmur', 1);

-- Simpanan anggota
INSERT INTO simpanan (id_anggota, jenis, jumlah, tanggal, keterangan, id_user) VALUES
(1, 'pokok', 100000, '2024-01-15', 'Simpanan pokok awal', 1),
(2, 'pokok', 100000, '2024-01-15', 'Simpanan pokok awal', 1),
(3, 'pokok', 100000, '2024-02-10', 'Simpanan pokok awal', 1),
(4, 'pokok', 100000, '2024-03-05', 'Simpanan pokok awal', 1),
(5, 'pokok', 100000, '2024-04-20', 'Simpanan pokok awal', 1),
(6, 'pokok', 100000, '2024-06-12', 'Simpanan pokok awal', 1),
(7, 'pokok', 100000, '2024-09-01', 'Simpanan pokok awal', 1),
(8, 'pokok', 100000, '2025-01-18', 'Simpanan pokok awal', 1),
(9, 'pokok', 100000, '2025-05-07', 'Simpanan pokok awal', 1),
(10, 'pokok', 100000, '2025-08-25', 'Simpanan pokok awal', 1),
(1, 'wajib', 25000, '2026-08-05', 'Simpanan wajib bulanan', 1),
(1, 'wajib', 25000, '2026-09-05', 'Simpanan wajib bulanan', 1),
(1, 'wajib', 25000, '2026-10-05', 'Simpanan wajib bulanan', 1),
(2, 'wajib', 25000, '2026-08-05', 'Simpanan wajib bulanan', 1),
(2, 'wajib', 25000, '2026-09-05', 'Simpanan wajib bulanan', 1),
(2, 'wajib', 25000, '2026-10-05', 'Simpanan wajib bulanan', 1),
(3, 'wajib', 25000, '2026-08-05', 'Simpanan wajib bulanan', 1),
(3, 'wajib', 25000, '2026-09-05', 'Simpanan wajib bulanan', 1),
(3, 'wajib', 25000, '2026-10-05', 'Simpanan wajib bulanan', 1),
(4, 'wajib', 25000, '2026-08-05', 'Simpanan wajib bulanan', 1),
(4, 'wajib', 25000, '2026-09-05', 'Simpanan wajib bulanan', 1),
(4, 'wajib', 25000, '2026-10-05', 'Simpanan wajib bulanan', 1),
(5, 'wajib', 25000, '2026-08-05', 'Simpanan wajib bulanan', 1),
(5, 'wajib', 25000, '2026-09-05', 'Simpanan wajib bulanan', 1),
(5, 'wajib', 25000, '2026-10-05', 'Simpanan wajib bulanan', 1),
(6, 'wajib', 25000, '2026-08-05', 'Simpanan wajib bulanan', 1),
(6, 'wajib', 25000, '2026-09-05', 'Simpanan wajib bulanan', 1),
(7, 'wajib', 25000, '2026-08-05', 'Simpanan wajib bulanan', 1),
(7, 'wajib', 25000, '2026-09-05', 'Simpanan wajib bulanan', 1),
(8, 'wajib', 25000, '2026-08-05', 'Simpanan wajib bulanan', 1),
(8, 'wajib', 25000, '2026-10-05', 'Simpanan wajib bulanan', 1),
(9, 'wajib', 25000, '2026-08-05', 'Simpanan wajib bulanan', 1),
(9, 'wajib', 25000, '2026-09-05', 'Simpanan wajib bulanan', 1),
(9, 'wajib', 25000, '2026-10-05', 'Simpanan wajib bulanan', 1),
(10, 'wajib', 25000, '2026-08-05', 'Simpanan wajib bulanan', 1),
(10, 'wajib', 25000, '2026-09-05', 'Simpanan wajib bulanan', 1),
(10, 'wajib', 25000, '2026-10-05', 'Simpanan wajib bulanan', 1),
(1, 'sukarela', 150000, '2026-09-12', 'Setoran sukarela', 1),
(3, 'sukarela', 200000, '2026-09-20', 'Setoran sukarela', 1),
(5, 'sukarela', 100000, '2026-10-02', 'Setoran sukarela', 1),
(7, 'sukarela', 50000, '2026-10-08', 'Setoran sukarela', 1),
(9, 'sukarela', 75000, '2026-10-12', 'Setoran sukarela', 1);
