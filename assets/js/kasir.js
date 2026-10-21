// assets/js/kasir.js - keranjang belanja di halaman Kasir
// Data barang dikirim PHP lewat window.DATA_BARANG. Server tetap memvalidasi ulang saat simpan.

document.addEventListener('DOMContentLoaded', function () {

    var produk = {};      // id -> data barang
    var keranjang = {};   // id -> jumlah yang dibeli

    (window.DATA_BARANG || []).forEach(function (b) { produk[b.id] = b; });

    // Ambil elemen yang dibutuhkan
    var selectPembeli = document.getElementById('pembeli');
    var isiKeranjang = document.getElementById('isiKeranjang');
    var teksTotal = document.getElementById('teksTotal');
    var teksKembalian = document.getElementById('teksKembalian');
    var inputBayar = document.getElementById('bayar');
    var inputKeranjang = document.getElementById('inputKeranjang');
    var pesan = document.getElementById('pesanKasir');
    var form = document.getElementById('formKasir');

    // Format angka ke rupiah: 15000 -> Rp 15.000
    function rupiah(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); }

    // Harga satuan mengikuti jenis pembeli (anggota atau umum)
    function harga(p) { return selectPembeli.value !== '0' ? p.anggota : p.umum; }

    function tampilPesan(teks) {
        pesan.textContent = teks;
        pesan.style.display = teks ? 'flex' : 'none';
    }

    // Isi keranjang awal (dipakai saat halaman dimuat ulang karena error di server)
    var awal = window.KERANJANG_AWAL || {};
    Object.keys(awal).forEach(function (id) {
        var p = produk[id];
        var qty = Math.min(parseInt(awal[id], 10) || 0, p ? p.stok : 0);
        if (p && qty >= 1) { keranjang[id] = qty; }
    });

    // Buat elemen sederhana: el('td', 'teks', 'kelas')
    function el(tag, teks, kelas) {
        var x = document.createElement(tag);
        if (teks !== undefined && teks !== null) { x.textContent = teks; }
        if (kelas) { x.className = kelas; }
        return x;
    }

    // Tombol kecil di dalam keranjang
    function tombol(teks, kelas, aksi) {
        var t = el('button', teks, kelas);
        t.type = 'button';
        t.addEventListener('click', aksi);
        return t;
    }

    // Ubah jumlah barang, dibatasi 1 sampai stok
    function setJumlah(id, qty) {
        var p = produk[id];
        qty = parseInt(qty, 10);
        if (isNaN(qty) || qty < 1) { qty = 1; }
        if (qty > p.stok) {
            qty = p.stok;
            tampilPesan('Stok ' + p.nama + ' hanya ' + p.stok + ' ' + p.satuan + '.');
        } else {
            tampilPesan('');
        }
        keranjang[id] = qty;
        render();
    }

    function tambah(id) {
        var p = produk[id];
        if (!p || p.stok < 1) { return; }
        setJumlah(id, (keranjang[id] || 0) + 1);
    }

    // Gambar ulang keranjang + hitung total & kembalian
    function render() {
        isiKeranjang.innerHTML = '';
        var total = 0;
        var daftar = [];
        var ids = Object.keys(keranjang);

        if (ids.length === 0) {
            var kosong = el('tr');
            var td = el('td', 'Keranjang masih kosong. Klik "+ Tambah" pada barang.', 'kosong');
            td.colSpan = 4;
            kosong.appendChild(td);
            isiKeranjang.appendChild(kosong);
        }

        ids.forEach(function (id) {
            var p = produk[id];
            var qty = keranjang[id];
            var sub = harga(p) * qty;
            total += sub;
            daftar.push({ id: p.id, qty: qty });

            var tr = el('tr');

            // Nama + harga satuan
            var tdNama = el('td');
            tdNama.appendChild(el('strong', p.nama));
            tdNama.appendChild(el('br'));
            tdNama.appendChild(el('small', rupiah(harga(p)) + ' / ' + p.satuan, 'teks-abu'));
            tr.appendChild(tdNama);

            // Pengatur jumlah: - [angka] +
            var tdQty = el('td');
            var box = el('div', null, 'qty-box');
            box.appendChild(tombol('-', 'qty-tombol', function () { setJumlah(id, keranjang[id] - 1); }));
            var input = el('input', null, 'qty-input');
            input.type = 'number'; input.min = 1; input.max = p.stok; input.value = qty;
            input.addEventListener('change', function () { setJumlah(id, input.value); });
            box.appendChild(input);
            box.appendChild(tombol('+', 'qty-tombol', function () { setJumlah(id, keranjang[id] + 1); }));
            tdQty.appendChild(box);
            tr.appendChild(tdQty);

            tr.appendChild(el('td', rupiah(sub), 'kanan'));

            var tdHapus = el('td', null, 'kanan');
            tdHapus.appendChild(tombol('×', 'btn btn-hapus btn-kecil', function () {
                delete keranjang[id];
                tampilPesan('');
                render();
            }));
            tr.appendChild(tdHapus);

            isiKeranjang.appendChild(tr);
        });

        teksTotal.textContent = rupiah(total);
        inputKeranjang.value = JSON.stringify(daftar);
        hitungKembalian(total);
        tampilHargaProduk();
    }

    // Kembalian = uang bayar - total
    function hitungKembalian(total) {
        if (total === undefined) { total = totalSekarang(); }
        var bayar = parseInt(inputBayar.value, 10);
        teksKembalian.className = '';
        if (isNaN(bayar)) {
            teksKembalian.textContent = '-';
        } else if (bayar < total) {
            teksKembalian.textContent = 'Kurang ' + rupiah(total - bayar);
            teksKembalian.className = 'teks-merah';
        } else {
            teksKembalian.textContent = rupiah(bayar - total);
        }
    }

    function totalSekarang() {
        var t = 0;
        Object.keys(keranjang).forEach(function (id) { t += harga(produk[id]) * keranjang[id]; });
        return t;
    }

    // Harga di daftar barang ikut berubah sesuai jenis pembeli
    function tampilHargaProduk() {
        document.querySelectorAll('.harga-produk').forEach(function (td) {
            var p = produk[td.dataset.id];
            if (p) { td.textContent = rupiah(harga(p)); }
        });
    }

    /* ----- Event ----- */

    document.querySelectorAll('.btn-tambah').forEach(function (b) {
        b.addEventListener('click', function () { tambah(b.dataset.id); });
    });

    selectPembeli.addEventListener('change', render);

    // Uang bayar: hanya angka
    inputBayar.addEventListener('input', function () {
        inputBayar.value = inputBayar.value.replace(/\D/g, '').slice(0, 9);
        hitungKembalian();
    });

    document.getElementById('btnPas').addEventListener('click', function () {
        inputBayar.value = totalSekarang();
        hitungKembalian();
    });

    document.getElementById('btnKosongkan').addEventListener('click', function () {
        keranjang = {};
        inputBayar.value = '';
        tampilPesan('');
        render();
    });

    // Validasi sebelum kirim ke server
    form.addEventListener('submit', function (e) {
        var total = totalSekarang();
        var bayar = parseInt(inputBayar.value, 10);
        if (Object.keys(keranjang).length === 0) {
            e.preventDefault(); tampilPesan('Keranjang masih kosong. Pilih barang terlebih dahulu.');
        } else if (isNaN(bayar)) {
            e.preventDefault(); tampilPesan('Uang bayar wajib diisi.');
        } else if (bayar < total) {
            e.preventDefault(); tampilPesan('Uang bayar kurang ' + rupiah(total - bayar) + '.');
        }
    });

    render();
});
