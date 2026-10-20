# TASKS — Café POS V1 (Human Tracker)

> **Ini tracker hasil fitur, bukan instruksi coding per file.**  
> Status: **Todo → Doing → Awaiting Approval → Done**.
>
> **Konfirmasi pengguna:** seluruh pekerjaan yang sudah diimplementasikan telah diuji dan disetujui; integrasi payment gateway/QRIS ditunda untuk dikerjakan nanti.
>
> **Fokus sekarang:** tidak ada implementasi aktif; sisa tahap 08 dan finalisasi tahap 10 menunggu integrasi payment gateway.

## 1. Progress

| No. | ID      | Tahap / hasil yang diharapkan                   | Status |
| --- | ------- | ----------------------------------------------- | ------ |
| 01  | F00     | Laravel + Vue/Inertia + PostgreSQL berjalan     | Done   |
| 02  | F02     | Menu kopi/pastry + variants/options berfungsi   | Done   |
| 03  | F03     | Ingredient inventory + coffee recipes berfungsi | Done   |
| 04  | FAUTH   | Login/logout dasar untuk staff                  | Done   |
| 05  | F07     | Supplier, purchasing, receiving sederhana       | Done   |
| 06  | F04     | Cashier POS cash dine-in/takeaway               | Done   |
| 07  | F06     | Kitchen queue + potong stok sekali              | Done   |
| 08  | F05     | Table QR + QRIS verified (+ QRIS cashier)       | Doing  |
| 09  | F01     | Employees + fixed roles authorization           | Done   |
| 10  | F09/F10 | Dashboard, testing & release hardening          | Doing  |
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

**Status: Done — implementasi dan pengujian dikonfirmasi pengguna.** Supplier, form/list/detail draft pembelian, pembatalan, owner-only access, dan receiving atomik terhubung ke backend. Migration purchasing sudah diterapkan pada database lokal; draft tidak mengubah stok dan receiving berulang tidak menggandakan saldo/movement.

**Riwayat verifikasi:** catatan berikut merekam hasil dan keterbatasan sesi sebelumnya; status menunggu UI/persetujuan di riwayat sudah digantikan oleh konfirmasi pengguna di atas.

**Verifikasi (2026-10-19):** `php artisan test --compact tests/Feature/PurchasingTest.php tests/Feature/InventoryManagementTest.php tests/Feature/AuthenticationTest.php` lulus **74 tests / 764 assertions**, termasuk rollback multi-item, supplier/item nonaktif, terminal states, otorisasi, dan dua proses receiving yang benar-benar menunggu lock PostgreSQL. `composer types:check -- --memory-limit=512M`, `npm.cmd run types:check`, `npm.cmd run build`, pemeriksaan frontend terarah dengan `vp.cmd check`, `php vendor/bin/pint --dirty --format agent`, serta `git diff --check` lulus. Independent review kode stok/otorisasi dan concurrency test: tidak ada temuan blocking.

**Batas verifikasi:** browser tidak tersedia pada sesi ini, sehingga navigasi, dialog penerimaan, dan layout responsive belum diperiksa secara visual. Checklist tetap menunggu UI manual; setelah pemeriksaan tersebut lulus, pindahkan ke Awaiting Approval. Full suite belum dijalankan; jalankan `php artisan test --compact` sebelum persetujuan akhir.

**Perbaikan tampilan kuantitas (2026-10-19):** kuantitas database `500.000` berarti 500 unit, bukan 500.000 unit. Detail dan dialog penerimaan kini memakai format Indonesia tanpa nol desimal tambahan; input edit menampilkan `500`. Regression test membuktikan input `500` dan `500.125` tersimpan serta diterima tanpa perkalian. Purchasing tests lulus **28 tests / 291 assertions**; frontend check, TypeScript, build, dan Pint lulus. Data pembelian lokal tidak diubah.

**Konfirmasi draft (2026-10-19):** create dan edit meminta konfirmasi sebelum menyimpan, dengan ringkasan barang, kuantitas, dan total biaya. Tombol Batal/kembali dari form meminta konfirmasi membuang perubahan; menutup dialog mempertahankan input. Konfirmasi pembatalan pembelian yang sudah ada tetap dipakai. Frontend check terarah, TypeScript, build, dan diff check lulus; UI manual tetap menunggu browser tersedia. Status tetap Doing.

**Banyak kontak supplier (2026-10-19):** form supplier mendukung Tambah kontak/Hapus dan menampilkan setiap kontak di daftar supplier, maksimal 10 entri opsional. Kontak lama dipertahankan; migration memperluas kolom contact menjadi text dan sudah diterapkan lokal. Purchasing tests lulus **33 tests / 334 assertions**, termasuk penyimpanan/edit/penghapusan beberapa kontak, data lama saat migration, validasi per kontak, dan total teks melampaui batas lama. PHPStan, TypeScript, frontend check, build, Pint, dan diff check lulus. UI manual dan full suite tetap belum dijalankan; status Doing.

**Konfirmasi supplier (2026-10-19):** create/edit supplier menampilkan modal konfirmasi berisi nama, semua kontak, dan status sebelum request simpan. Membatalkan form yang memiliki perubahan juga memakai modal Buang perubahan, menggantikan browser confirm. Kembali ke form mempertahankan input; konfirmasi buang hanya menutup form tanpa mengubah data tersimpan. Error validasi menutup konfirmasi dan tetap menampilkan form. Frontend check, TypeScript, build, dan diff check lulus; UI manual tetap belum tersedia. Status Doing.

**Jenis kontak supplier (2026-10-19):** setiap kontak kini memiliki pilihan Telepon/Email saja, dengan input dan validasi sesuai jenis. Jenis dan nilai tampil di tabel supplier serta modal konfirmasi. Data lama dikonversi tanpa menghapus nilai kontak; migration lokal sudah diterapkan. Purchasing tests lulus **39 tests / 352 assertions**, termasuk migrasi kontak lama, edit/simpan beberapa kontak, penolakan jenis/email/telepon tidak valid, serta preservasi awalan +62 dan nol awal. PHPStan, TypeScript, frontend check, build, Pint, dan diff check lulus. UI manual dan full suite masih menunggu; status Doing.

**Kontak default (2026-10-19):** form supplier dimulai dengan dua input tetap Telepon dan Email. Dropdown pada +Tambah kontak menawarkan Telepon/Email; input tambahan baru muncul setelah jenis dipilih, dengan tombol Hapus. Dropdown jenis tidak lagi ditampilkan pada setiap baris. Saat edit, kontak yang tersimpan mengisi input sesuai jenisnya. Kedua input default tetap opsional. Frontend check, TypeScript, build, dan diff check lulus; status Doing, UI manual belum tersedia.

- [x] Bisa mengelola supplier serta mencatat draft pembelian dan item/biayanya.
- [x] Owner bisa menerima **seluruh** pembelian sehingga stok bertambah; draft tidak mengubah stok.
- [x] Receiving kedua kali tidak menambah stok; penerima dan riwayat tercatat.

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

**Status: Doing — hanya integrasi payment gateway/QRIS yang ditunda.** Admin meja/QR, menu dan checkout pelanggan, pembayaran cash melalui konfirmasi kasir, serta status order privat sudah diimplementasikan dan diuji sesuai konfirmasi pengguna. Integrasi gateway dan pengujian provider belum dinyatakan selesai.

- [x] Owner bisa tambah/edit/aktif/nonaktifkan meja dan melihat/unduh SVG/cetak QR berisi `/menu?table={id}`, tanpa token meja; ID dan riwayat order tetap dipertahankan.
- [x] UI pelanggan untuk menu dan checkout tanpa login tersedia; ID invalid/meja nonaktif ditolak.
- [x] Status order privat tersedia; guest cash menunggu konfirmasi kasir sebelum masuk kitchen.
- [ ] QRIS dinamis dibuat melalui provider sandbox/fake lokal; hanya notifikasi **server-verified** mengirim order ke kitchen.
- [ ] Tangani callback gateway duplikat, gagal, expired, dan late paid (`payment_review`).
- [ ] Cashier bisa memilih QRIS memakai mekanisme pembayaran yang sama.

**Done jika:** Table QR → Iced Americano → verified QRIS → satu kitchen order; unpaid tidak tampil. **Live payment belum siap sampai provider asli diuji.**

**Riwayat implementasi:** status Awaiting Approval/Doing dan keterangan fitur belum tersedia di bawah merekam tahap sebelumnya; gunakan status terbaru di atas. Pekerjaan non-gateway telah dikonfirmasi pengguna.

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

**Status: Done — implementasi dan pengujian dikonfirmasi pengguna.**

**Riwayat verifikasi sebelum konfirmasi pengguna:** halaman staf tersambung ke tabel users existing: tambah/edit akun, pencarian/filter/pagination, role tetap, aktif/nonaktif, reset password dan sesi. Gate membatasi seluruh management/POS/kitchen routes; sidebar dan tautan kitchen mengikuti capability server, login menuju area sesuai role. Owner tidak dapat demote/nonaktifkan diri; update mengunci akun berurutan dan memeriksa ulang actor untuk menjaga owner aktif saat request bersamaan. Tidak ada migration/dependency baru. Verifikasi: `php artisan test --compact tests/Feature tests/StaffConcurrencyTest.php` **311 passed / 3373 assertions**, termasuk dua worker PostgreSQL yang benar-benar menunggu row lock untuk demotion/deactivation dan regresi reset password dengan session database. Vue types, PHPStan, lint/format, production build dan diff check lulus. Review independen: masalah self-session reset dan tautan kitchen telah diperbaiki; snapshot statistik polling test juga diperbaiki dan diuji ulang. Browser tidak terhubung pada sesi tersebut, sehingga tampilan desktop/mobile belum diperiksa visual oleh agent. Suite lengkap `php artisan test --compact` diminta pada handoff saat itu.

- [x] Owner bisa tambah/nonaktifkan akun pegawai; role `owner/cashier/kitchen` tetap.
- [x] Policy/Gate/backend membatasi owner management, cashier POS dan kitchen operations.
- [x] Test unauthorized API/request ditolak, bukan cuma menyembunyikan tombol UI.

**Done jika:** tiap role hanya bisa melakukan tugasnya. Wajib sebelum akses publik.

## 10 — Dashboard & Final QA

**Status: Doing — dashboard dan QA pekerjaan yang sudah diimplementasikan dikonfirmasi selesai oleh pengguna; finalisasi integrasi gateway dan kesiapan rilis menunggu tahap 08.**

**Dashboard calendar filter — Done:** presets and native custom calendars support up to 366 inclusive days, WIB reporting with UTC storage, elapsed-period comparisons, and daily/weekly/monthly chart grouping. Recorded agent verification: dashboard and role-access tests 45 / 831 assertions, PHPStan, Vue types, dashboard lint/format, Pint, production build, and diff check passed. Independent review found a retained chart-selection error after invalid filters; fixed and re-reviewed with no remaining findings. Browser/calendar-click and mobile visual checks were unavailable and the full suite was not run in that agent session; the user subsequently confirmed implemented work was tested and approved.

- [x] Dashboard penjualan terkonfirmasi, order count, top products, dan low-stock warning.
- [x] Uji alur yang tersedia: Cashier → Kitchen; guest cash → konfirmasi kasir → Kitchen; Purchase → Stock; Recipe → Consumption.
- [x] Uji failure path yang tersedia: no stock, duplicate checkout, expiry guest cash, double prepare, unauthorized access.
- [x] Review pekerjaan non-gateway yang sudah diimplementasikan, sesuai konfirmasi pengguna.
- [ ] Uji QR → QRIS → Kitchen dan webhook duplikat/gagal/expired/late paid setelah integrasi gateway.
- [ ] Finalisasi review konfigurasi keamanan, backup/restore, environment dan integrasi QRIS nyata sebelum rilis.

**Done jika:** success criteria `PRD.md` terpenuhi, semua critical tests lulus, dan tidak ada management routes publik. Jika provider nyata belum tersedia, **status belum siap production** walaupun local tests lulus.

---

## 2. Current work / blocker

| Field             | Isi                                                                                                                                           |
| ----------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| Current stage     | **08 — F05: sisa integrasi payment gateway/QRIS**                                                                                             |
| Status            | **Doing — ditunda sesuai keputusan pengguna**                                                                                                 |
| Next outcome      | Implementasi provider QRIS dan callback terverifikasi saat pekerjaan dilanjutkan                                                              |
| Blocker           | Tidak ada blocker pada pekerjaan selesai; integrasi gateway sengaja ditunda                                                                   |
| Last verification | Pengguna mengonfirmasi seluruh pekerjaan yang sudah diimplementasikan telah diuji; bukti hasil agent sebelumnya dipertahankan di setiap tahap |

**Status terbaru:** tahap 01–07 dan 09 Done. Dashboard dan kalender Done. Tahap 08 serta finalisasi rilis tahap 10 tetap terbuka untuk gateway dan verifikasi integrasinya. Tidak ada test baru dijalankan untuk pembaruan tracker ini.

**Riwayat checkpoint:** catatan Awaiting Approval/Awaiting UI Approval/Doing di bawah adalah riwayat sesi sebelumnya dan telah digantikan oleh konfirmasi pengguna untuk pekerjaan non-gateway; bukan pekerjaan aktif yang masih menunggu persetujuan.

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
