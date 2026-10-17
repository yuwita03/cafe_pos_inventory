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
| 08  | F05     | Table QR + QRIS verified (+ QRIS cashier)       | Todo   |
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
| Current stage     | **01 — F00 Project Foundation**                       |
| Status            | **Todo**                                              |
| Next outcome      | Laravel + Vue/Inertia + PostgreSQL jalan di local     |
| Blocker           | Tidak ada yang dikonfirmasi; inspeksi repository dulu |
| Last verification | Belum ada                                             |

**Keputusan tertunda:** provider QRIS, resep bahan riil, tax/service, hosting — dicatat di PRD; baru dianggap blocker jika sudah menghambat tahap aktif.

## 3. Aturan untuk AI agent

- Rujuk `PRD.md` (behavior), `DATABASE.md` (schema), `ARCHITECTURE.md` (cara kerja), `AGENTS.md` (workflow).
- Untuk tiap checklist, agent boleh memecah kerja teknis **secara internal** (migration, controller, Vue, test). Jangan menambahkan puluhan baris ke tracker ini.
- Laporkan: **yang berubah**, **yang diuji**, **hasil pass/fail**, **yang belum selesai**. Owner/manusia menyetujui `Done`.
- Jangan mengimplementasikan fitur berikutnya sebelum fitur aktif selesai, kecuali dependency kecil yang benar-benar dibutuhkan.
