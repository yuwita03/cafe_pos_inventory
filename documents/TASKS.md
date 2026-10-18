# TASKS — Café POS V1 (Human Tracker)

> **Ini tracker hasil fitur, bukan instruksi coding per file.**  
> Status: **Todo → Doing → Done**. Semua masih **Todo** sampai diverifikasi di repository.  
> **Fokus sekarang:** `01 — Project Foundation`. Kerjakan **satu tahap aktif** pada satu waktu.

## 1. Progress

| No. | ID      | Tahap / hasil yang diharapkan                   | Status |
| --- | ------- | ----------------------------------------------- | ------ |
| 01  | F00     | Laravel + Vue/Inertia + PostgreSQL berjalan     | Todo   |
| 02  | F02     | Menu kopi/pastry + variants/options berfungsi   | Todo   |
| 03  | F03     | Ingredient inventory + coffee recipes berfungsi | Todo   |
| 04  | FAUTH   | Login/logout dasar untuk staff                  | Todo   |
| 05  | F07     | Supplier, purchasing, receiving sederhana       | Todo   |
| 06  | F04     | Cashier POS cash dine-in/takeaway               | Todo   |
| 07  | F06     | Kitchen queue + potong stok sekali              | Todo   |
| 08  | F05     | Table QR + QRIS verified (+ QRIS cashier)       | Doing  |
| 09  | F01     | Employees + fixed roles authorization           | Todo   |
| 10  | F09/F10 | Dashboard, testing & release hardening          | Todo   |
|     |         |                                                 |        |
|     |         |                                                 |        |

### Cara update

1. Pilih tahap **Todo** pertama yang dependency-nya terpenuhi; ubah menjadi **Doing**.
2. Centang checklist setelah perilakunya dicoba di UI **dan** test relevan dijalankan; jangan hanya karena AI menulis kode.
3. Tahap **Done** bila semua checklist terpenuhi dan acceptance di bawahnya lulus; jika gagal, tulis blocker dan tetap **Doing**.
4. **Catatan blocker** hanya hal yang menghambat _tahap aktif_; jangan membuat backlog teknis panjang di sini.

---

## 01 — Project Foundation

- [x] Laravel + Vue/Inertia bisa dibuka di browser lokal.
- [x] PostgreSQL terhubung; migration dan test dasar berhasil.
- [x] Project tidak terbuka ke internet/tunnel; belum perlu authentication.

**Done jika:** aplikasi berjalan, DB connected, baseline test lulus.

## 02 — Menu Management

- [x] Bisa tambah/edit/nonaktifkan kategori dan produk coffee/pastry.
- [x] Bisa membuat varian Hot/Iced atau Regular beserta harga.
- [x] Bisa mengatur Sugar Level per produk; Laravel menolak opsi tidak valid dan menghitung harga sendiri.

**Done jika:** Iced Americano + Low Sugar dan Croissant Regular muncul dengan harga tepat.

## 03 — Inventory & Recipes

- [x] Bisa mengelola bahan (g/ml) dan pastry jadi (pcs), melihat saldo serta low-stock warning.
- [x] Bisa mengatur resep per variant coffee dan penambahan syrup berdasarkan Sugar Level.
- [x] Bisa adjustment dengan alasan; semua perubahan saldo tercatat, tidak negatif, dan bisa dites.

**Done jika:** kebutuhan stok Americano dapat dihitung dan riwayat perubahan stok konsisten. **Belum perlu penjualan untuk menyelesaikan tahap ini.**

## 04 — Minimal Authentication

- [x] Satu akun owner development bisa login dan logout.
- [x] Semua route management Menu/Inventory dan yang baru dilindungi `auth`.
- [x] Test guest ditolak dari staff routes; customer QR nantinya tetap tidak butuh login.

**Done jika:** staf bisa login dan akses management terlindungi. **Belum perlu employee CRUD / full roles.**

## 05 — Suppliers & Purchasing

- [ ] Bisa mengelola supplier serta mencatat draft pembelian dan item/biayanya.
- [ ] Owner bisa menerima **seluruh** pembelian sehingga stok bertambah; draft tidak mengubah stok.
- [ ] Receiving kedua kali tidak menambah stok; penerima dan riwayat tercatat.

**Done jika:** beli 30 croissant dari supplier → receive → stok bertambah 30 pcs **tepat sekali**.

## 06 — Cashier POS

- [x] Kasir (sementara owner dev) bisa memilih produk, varian, opsi, qty dan dine-in/meja atau takeaway.
- [x] Pembayaran **cash** membuat paid order dan memegang reservasi stok untuk kitchen.
- [x] Harga dihitung di backend; stok habis, harga manipulasi dan duplicate checkout ditolak/dihandle aman.

**Done jika:** transaksi cash menghasilkan satu pesanan paid/queued yang terlihat pada data order. **QRIS cashier ditambahkan di F05.**

## 07 — Kitchen Display

- [x] Kitchen melihat pesanan berbayar dari kasir beserta item/opsi/meja.
- [x] Status `queued → preparing → ready → completed` bisa diperbarui secara benar.
- [x] Saat `preparing`, stok kopi berkurang sesuai resep dan pastry sesuai pcs **sekali**; cancel sebelum preparing melepas reservasi.

**Done jika:** satu order cash kasir berhasil diproses hingga completed tanpa double stock deduction.

## 08 — Table QR + QRIS

- [ ] Owner bisa tambah/edit/aktif/nonaktifkan meja dan melihat/unduh SVG/cetak QR berisi `/menu?table={id}`, tanpa token meja; ID dan riwayat order tetap dipertahankan.
- [ ] UI pelanggan untuk menu dan checkout tanpa login dikerjakan pengguna secara terpisah pada tahap berikutnya. Saat ini tujuan QR hanya teks belum tersedia, tanpa katalog atau UI pelanggan; ID invalid/meja nonaktif ditolak.
- [ ] QRIS dinamis dibuat melalui provider sandbox/fake lokal; hanya notifikasi **server-verified** mengirim order ke kitchen.
- [ ] Tangani callback duplikat, gagal, expired, late paid (`payment_review`), dan status order privat.
- [ ] Cashier bisa memilih QRIS memakai mekanisme pembayaran yang sama.

**Done jika:** Table QR → Iced Americano → verified QRIS → satu kitchen order; unpaid tidak tampil. **Live payment belum siap sampai provider asli diuji.**

**Irisan admin Meja & QR:** UI admin dan backend terintegrasi; UI pelanggan sudah dihapus sesuai koreksi scope pengguna. Status irisan: **Awaiting Approval**. QA independen setelah koreksi scope: **PASS** (inspeksi statis); review **9/10 — APPROVE**, tanpa temuan penghambat. Verifikasi eksekusi: TableQr 23 test / 202 assertions, PHPStan, Vue types, lint, Pint, dan build lulus. Belum ada pemeriksaan browser/scan/cetak fisik; checklist UI dan tahap QRIS belum dinyatakan selesai. Receipt, akses guest berbasis session, dan history customer tetap di `note`.

**Irisan customer menu + cart:** halaman menu meja mobile-first, selector varian/options dan cart lokal selesai diimplementasikan. TableQr: 23 test / 232 assertions; Vue types, page lint, Pint dan production build lulus. Checkout, QRIS, order status, dan browser visual review belum termasuk irisan ini. Status keseluruhan F05 tetap **Doing**.

**Revisi customer navigation — Awaiting Approval:** pilihan produk dan keranjang sekarang berupa halaman penuh pada `/menu/products/{product}?table={id}` dan `/menu/cart?table={id}`, dengan tautan kembali ke menu. Dialog kasir tetap memakai komponen existing. Cart disimpan di session storage per meja untuk navigasi/refresh dalam tab yang sama. Verifikasi revisi ini: Vue types, lint tiga komponen guest, Pint, production build, route list dan diff check lulus. Test aplikasi dan pemeriksaan visual browser belum dijalankan untuk revisi ini; checkout/pembayaran tetap belum tersedia. Status keseluruhan F05 tetap **Doing**.

**Revisi tampilan keranjang — Awaiting Approval:** mengikuti struktur referensi pengguna dengan gaya oranye existing: header Pesanan, konteks meja/dine-in, saran menu dari katalog, tambah/ubah/hapus item, kontrol jumlah dan total estimasi tetap di bawah. Memilih saran menu kembali ke keranjang setelah disimpan. Tombol pembayaran dinonaktifkan dengan keterangan belum tersedia. Vue types, lint, production build dan diff check lulus; visual browser belum diperiksa karena tidak ada browser terhubung. Status keseluruhan F05 tetap **Doing**.

**Scroll kategori dan saran menu — Awaiting Approval:** kategori dan baris “Lengkapi pesananmu” mempertahankan swipe/trackpad native, ditambah drag mouse dengan pencegahan klik setelah drag serta fokus keyboard. Handler drag digunakan bersama; klik kategori tetap menavigasi ke bagian menu dan saran menu tetap memakai scroll snap horizontal. Vue types, lint, production build dan diff check lulus; pemeriksaan browser/perangkat mobile belum dilakukan. F05 tetap **Doing**.

**Koreksi scope keranjang — Awaiting Approval:** sesuai instruksi terbaru, bagian “Lengkapi pesananmu” beserta logika dan CSS khusus saran menu dihapus. Scroll kategori tetap tersedia. Vue types, lint, build dan diff check lulus; pemeriksaan visual belum dilakukan. F05 tetap **Doing**.

**Kepadatan UI mobile — Awaiting Approval:** menu, pilihan produk dan keranjang dibuat lebih ringkas pada lebar hingga 767px melalui pengurangan padding, gap, tinggi gambar, header dan footer. Nama produk tetap tebal, target sentuh interaktif minimal 44px dan input jumlah 16px. Formatting CSS, build dan diff check lulus; pemeriksaan visual perangkat belum dilakukan. F05 tetap **Doing**.

**Lebar customer — Awaiting Approval:** sesuai referensi terbaru, halaman customer dibatasi maksimal 500px dan dipusatkan; tab kategori serta cart bar/footer mengikuti lebar tersebut. Perubahan hanya pada batas lebar dan posisi horizontal; gaya existing dipertahankan. Formatting CSS, build dan diff check lulus. F05 tetap **Doing**.

**Detail pilihan produk — Awaiting Approval:** CustomerProductPage mengikuti struktur referensi pengguna: gambar penuh dengan tombol kembali, nama/harga/deskripsi produk, pilihan varian/options satu kolom dengan kontrol di kanan, serta footer tetap berisi jumlah dan tombol tambah/simpan beserta total estimasi. Palet dan lebar 500px mengikuti gaya existing. Validasi pilihan wajib tetap aktif. Vue types, lint, formatting, build dan diff check lulus; pemeriksaan visual browser belum dilakukan. F05 tetap **Doing**.

**Penataan migration lokal:** `create_cafe_tables_table` dipisah sebelum migration order, langsung tanpa token meja; migration penghapusan token dihapus. Test rollback disesuaikan untuk urutan foreign key. TableQr + CashOrderFlow: **49 passed / 564 assertions**, Pint dan PHPStan lulus. Atas persetujuan pengguna, database lokal `cafe_pos` direset dan owner dari `.env`, menu awal, item stok awal, serta 8 meja di-seed ulang; order lama kosong dan saldo stok awal 0.

## 09 — Employees & Fixed Roles

- [ ] Owner bisa tambah/nonaktifkan akun pegawai; role `owner/cashier/kitchen` tetap.
- [ ] Policy/Gate/backend membatasi owner management, cashier POS dan kitchen operations.
- [ ] Test unauthorized API/request ditolak, bukan cuma menyembunyikan tombol UI.

**Done jika:** tiap role hanya bisa melakukan tugasnya. Wajib sebelum akses publik.

## 10 — Dashboard & Final QA

- [ ] Dashboard penjualan terkonfirmasi, order count, top products, dan low-stock warning.
- [ ] Uji 4 alur: Cashier → Kitchen; QR → QRIS → Kitchen; Purchase → Stock; Recipe → Consumption.
- [ ] Uji failure path: no stock, duplicate checkout/webhook, expiry/late pay, double prepare, unauthorized access.
- [ ] Review konfigurasi keamanan, backup/restore, environment dan integrasi QRIS nyata sebelum rilis.

**Done jika:** success criteria `PRD.md` terpenuhi, semua critical tests lulus, dan tidak ada management routes publik. Jika provider nyata belum tersedia, **status belum siap production** walaupun local tests lulus.

---

## 2. Current work / blocker

| Field             | Isi                                                   |
| ----------------- | ----------------------------------------------------- |
| Current stage     | **08 — F05 Table QR + QRIS**                          |
| Status            | **Doing**                                             |
| Next outcome      | QRIS provider dan callback terverifikasi masih tersisa |
| Blocker           | Provider QRIS belum dipilih                       |
| Last verification | Guest cash + cashier + TableQr: 57 tests / 695 assertions; Vue types dan lint lulus |

**Pemisahan UI pemesan — Awaiting Approval:** Pesanan tetap di CustomerMenuCart.vue; formulir Data pemesan dipindahkan ke CustomerDetailsForm.vue. Navigasi, fokus, field wajib/opsional dan placeholder dipertahankan. Vue types sebelum/sesudah, lint/format kedua komponen, build dan diff check lulus; browser belum diperiksa. F05 tetap Doing.

**Form data pemesan — Awaiting UI Approval (placeholder):** tombol “Lanjut ke pembayaran” membuka formulir nama, email dan nomor telepon dengan konteks meja, total estimasi dan kembali ke keranjang. Nama pemesan diberi atribut required dan label wajib; email dan telepon tetap opsional. UI saja; tidak ada penyimpanan, pengiriman data atau pembayaran. Revisi kewajiban field: lint/format dan build lulus. Vue types, lint/format, production build dan diff check lulus. Pemeriksaan visual browser belum dilakukan. F05 tetap Doing.

**Pemisahan pilihan varian — Awaiting Approval:** selector varian dipindahkan dari CustomerProductPage.vue ke CustomerVariantSelector.vue; pilihan, harga, status unavailable, error/fokus dan pengecualian Regular tetap dipertahankan. Vue types sebelum/sesudah, lint/format kedua komponen, build dan diff check lulus; browser belum diperiksa. F05 tetap Doing.

**Pilihan metode pembayaran — Awaiting UI Approval:** form Data pemesan menampilkan pilihan radio QRIS dan CASH sesuai instruksi pengguna, berdampingan horizontal dalam dua kolom sama lebar, dengan ikon QR untuk QRIS, ikon uang untuk CASH, petunjuk singkat dan penanda pilihan. Revisi ikon: lint/format, build dan diff check lulus. Revisi layout: formatting CSS, build dan diff check lulus. UI placeholder saja; belum memproses pembayaran atau mengirim data. Lint/format dan production build lulus; browser belum diperiksa. F05 tetap Doing.

**Petunjuk pembayaran — Awaiting UI Approval:** manual bernomor 1, 2, 3 tanpa ikon hanya ditampilkan sesuai metode yang dipilih, dan tersembunyi sebelum pilihan dibuat. Revisi: Vue types, lint/format, build dan diff check lulus. Instruksi QRIS (buka aplikasi bank/dompet, scan, periksa merchant/nominal, konfirmasi) dan CASH (tunjukkan struk ke kasir, bayar, tunggu konfirmasi) ditambahkan di bawah pilihan metode. Struk dan pembayaran tetap ditandai belum tersedia pada prototype. Lint/format, build dan diff check lulus; browser belum diperiksa. F05 tetap Doing.

**Ringkasan CASH — Awaiting UI Approval:** pilih CASH lalu Lanjut membuka CashReceipt.vue dengan contoh Order ID #DEMO-001 besar/tebal di atas dan label meja kecil tepat di bawahnya, nama/kontak opsional, meja, item/varian/opsi, qty, harga satuan, total estimasi dan label belum dibayar. Nama wajib termasuk penolakan whitespace; validasi email native bila diisi. Kembali mempertahankan data form dan cart. Tidak membuat order/ID nyata, menyimpan data, mengonfirmasi pembayaran atau mengirim ke dapur; QRIS tetap dinonaktifkan. Vue types, lint/format tiga komponen, build dan diff check lulus; browser belum diperiksa. F05 tetap Doing.

**Konfirmasi CASH — Awaiting UI Approval:** setelah validasi form, Lanjut membuka AppDialog sebagai bottom sheet maksimal 500px dengan animasi naik 240ms (menghormati reduced motion), safe-area dan pertanyaan konfirmasi; Cek lagi menutup dialog dan mempertahankan form, Bayar sekarang membuka ringkasan tunai. Escape/tutup membatalkan konfirmasi. Tetap prototype tanpa pembayaran nyata. Vue types, lint/format, build dan diff check lulus; browser belum diperiksa. F05 tetap Doing.

**Tombol PDF struk — Awaiting UI Approval:** CashReceipt.vue menampilkan tombol disabled Unduh struk PDF dengan keterangan belum tersedia. Placeholder saja tanpa handler/generasi PDF. Lint/format, build dan diff check lulus. F05 tetap Doing.

**Integrasi guest CASH — menunggu verifikasi UI:** checkout pelanggan membuat order `awaiting_payment` dengan nama dan email opsional, tanpa nomor telepon. Struk dan status memakai token privat; kasir mengonfirmasi uang tunai sebelum order masuk kitchen. Reservasi unpaid kedaluwarsa setelah 30 menit dan order yang kedaluwarsa harus dibuat ulang. QRIS ditandai belum tersedia; PDF tetap disabled. Focused tests 57 / 695 assertions, Vue types dan lint lulus; browser visual dan suite lengkap belum dijalankan. F05 tetap Doing.

**Perbaikan checkout kosong — Awaiting Approval:** wrapper HTML `<template>` yang menyembunyikan formulir pelanggan dihapus. Pemeriksaan compiler memastikan field dan kontrol checkout tetap dirender tanpa wrapper inert; Vue types, lint/format komponen dan build lulus. Verifikasi interaksi browser belum tersedia karena tidak ada browser terhubung; F05 tetap Doing.

**Validasi resep & ketersediaan menu — Awaiting Approval:** varian baru wajib memiliki pengaturan stok; mode bahan/resep wajib minimal satu bahan aktif g/ml dengan jumlah positif per porsi, walaupun saldo stok nol. Form memvalidasi varian yang baru/diubah dan memilih varian bermasalah; edit metadata existing mempertahankan pengaturan stok. Katalog customer dan kasir menonaktifkan konfigurasi yang belum lengkap; checkout tetap memeriksa saldo/reservasi. Data seed dan saldo existing tidak diubah. Verifikasi: 194 tests / 1945 assertions, Vue types, lint/format, PHPStan dan build lulus; pemeriksaan runtime komponen lulus. Review independen menemukan masalah kompatibilitas edit metadata yang telah diperbaiki dan diperiksa ulang tanpa temuan tersisa. Browser visual belum tersedia; F05 tetap Doing.

**Stok habis di menu — Awaiting Approval:** katalog kasir dan `/menu` menonaktifkan serta mengabukan varian bila stok tidak cukup untuk satu porsi/pcs setelah reservasi aktif yang belum kedaluwarsa. Ketersediaan diperbarui saat halaman dimuat; checkout tetap memvalidasi perubahan stok dan memberi pesan nama produk. Sesuai revisi pengguna, notifikasi menjadi satu alert berlatar merah di bawah judul Data pemesan; judul dan alert berada dalam satu area sticky di atas agar tetap terlihat saat scroll. Dialog ditutup dan fokus diarahkan ke alert saat gagal, tanpa tombol tambahan kembali ke keranjang. Pengaturan resep tetap mengizinkan saldo nol. Verifikasi backend: 117 tests / 1470 assertions, Pint dan PHPStan lulus; review independen tanpa temuan. Revisi notifikasi dan posisi sticky: Vue types, lint/format dan build lulus; runtime komponen error sebelumnya lulus. Pemeriksaan visual browser belum tersedia; F05 tetap Doing.

**Tutup notifikasi checkout — Awaiting Approval:** alert sticky dapat ditutup lewat tombol × atau swipe kanan minimal 64px, dengan animasi geser kanan 180ms dan dukungan reduced motion. Scroll vertikal, swipe kiri/pendek dan multitouch tidak menutup alert. Menutup pesan mempertahankan recovery checkout; error baru tetap muncul. Runtime komponen, Vue types, lint/format dan build lulus; visual browser belum tersedia. F05 tetap Doing.

**Keputusan tertunda:** provider QRIS, resep bahan riil, tax/service, hosting — dicatat di PRD; baru dianggap blocker jika sudah menghambat tahap aktif.

## 3. Aturan untuk AI agent

- Rujuk `PRD.md` (behavior), `DATABASE.md` (schema), `ARCHITECTURE.md` (cara kerja), `AGENTS.md` (workflow).
- Untuk tiap checklist, agent boleh memecah kerja teknis **secara internal** (migration, controller, Vue, test). Jangan menambahkan puluhan baris ke tracker ini.
- Laporkan: **yang berubah**, **yang diuji**, **hasil pass/fail**, **yang belum selesai**. Owner/manusia menyetujui `Done`.
- Jangan mengimplementasikan fitur berikutnya sebelum fitur aktif selesai, kecuali dependency kecil yang benar-benar dibutuhkan.
