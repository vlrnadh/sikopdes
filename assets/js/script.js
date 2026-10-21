// assets/js/script.js - JavaScript sederhana untuk SiKopdes

document.addEventListener('DOMContentLoaded', function () {

    // 1. Buka/tutup sidebar di HP
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('overlay');
    var tombolMenu = document.getElementById('tombolMenu');

    function toggleSidebar() {
        sidebar.classList.toggle('terbuka');
        overlay.classList.toggle('tampil');
    }
    if (tombolMenu) { tombolMenu.addEventListener('click', toggleSidebar); }
    if (overlay) { overlay.addEventListener('click', toggleSidebar); }

    // 2. Tombol tutup pada pesan flash, dan hilang otomatis setelah 6 detik
    document.querySelectorAll('.flash').forEach(function (kotak) {
        var tutup = kotak.querySelector('.flash-tutup');
        if (tutup) { tutup.addEventListener('click', function () { kotak.remove(); }); }
        setTimeout(function () { kotak.remove(); }, 6000);
    });

    // 3. Pencarian tabel: <input data-cari="idTabel"> akan menyaring baris tabel tersebut
    document.querySelectorAll('[data-cari]').forEach(function (input) {
        input.addEventListener('input', function () {
            var kata = input.value.toLowerCase();
            var baris = document.querySelectorAll('#' + input.dataset.cari + ' tbody tr');
            baris.forEach(function (tr) {
                tr.style.display = tr.textContent.toLowerCase().indexOf(kata) > -1 ? '' : 'none';
            });
        });
    });

    // 4. Konfirmasi sebelum hapus: <form data-konfirmasi="Yakin hapus?"> atau <a data-konfirmasi="...">
    document.querySelectorAll('form[data-konfirmasi]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm(form.dataset.konfirmasi)) { e.preventDefault(); }
        });
    });
    document.querySelectorAll('a[data-konfirmasi]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            if (!confirm(link.dataset.konfirmasi)) { e.preventDefault(); }
        });
    });
});
