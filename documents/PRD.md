# PRD — Café POS V1

> **Status:** Approved scope, implementation not started.  
> **Purpose:** Menjelaskan **apa** yang dibuat dan **mengapa**. Desain tabel ada di `DATABASE.md`; detail teknis ada di `ARCHITECTURE.md`.

## 1. Overview

Aplikasi operasional **satu cabang café** dengan coffee sebagai menu utama dan pastry siap jual sebagai menu tambahan. Pelanggan bisa memesan dari QR di meja tanpa akun; kasir juga bisa membuat pesanan. Semua pesanan yang **sudah dibayar** masuk ke satu kitchen queue. Stok kopi mengikuti resep bahan, sedangkan stok pastry dihitung per pcs. Pembelian dari supplier menambah stok saat barang diterima.

**Masalah yang diselesaikan:** antrean kasir untuk dine-in; pesanan dari dua channel yang terpisah; stok bahan dan pastry tidak terpantau; pencatatan pembelian manual.

**Batas V1:** satu cabang, satu lokasi stok, mata uang IDR, online-first (tidak ada offline POS), web responsive. Prototype awal dikembangkan secara lokal.

## 2. Aktor dan akses

| Aktor | Kebutuhan | Akses V1 |
|---|---|---|
| Customer (guest) | Pesan dari QR meja, QRIS, cek status sendiri | Tanpa login |
| Owner | Kelola menu, stok, supplier/pembelian, staf, laporan | Login, full management |
| Cashier | Buat dine-in/takeaway order, pembayaran, lihat transaksi terkait | Login, terbatas ke kasir |
| Kitchen | Lihat dan proses pesanan berbayar | Login, terbatas ke kitchen |

Di V1 **fixed roles** (`owner`, `cashier`, `kitchen`), bukan dynamic RBAC. `users` juga berperan sebagai akun pegawai.

## 3. Scope V1 (core)

| ID | Modul | Kebutuhan utama |
|---|---|---|
| MENU | Menu | CRUD/nonaktifkan kategori & produk; Hot/Iced atau Regular; option group seperti Sugar Level; harga & ketersediaan |
| STOCK | Inventory | Bahan g/ml dan pastry pcs; resep per varian kopi; pengaruh opsi sugar; stock movements, adjustment dengan alasan, low-stock warning |
| BUY | Purchasing | CRUD supplier; catat pembelian dan item/cost; terima seluruh pembelian untuk menambah stok |
| POS | Cashier | Dine-in (pilih meja) atau takeaway; cash dulu, QRIS lewat payment flow yang sama setelah modul QRIS siap |
| KDS | Kitchen | Satu antrean untuk semua channel; queued → preparing → ready → completed |
| QR | Table QR | QR token unik per meja; menu publik tanpa login; checkout dengan QRIS dinamis; status pesanan privat |
| STAFF | Staff | Login/logout, akun staf, tiga role tetap, server-side authorization |
| DASH | Dashboard | Total penjualan terkonfirmasi, jumlah order, produk terlaris, peringatan stok |

### Bukan V1 (roadmap)

- **V2:** homemade pastry production (batch baking), online pickup, delivery, konfigurasi role/permission dinamis, purchase order approval/partial receiving, refund otomatis/terintegrasi.
- **Later:** multi-branch, loyalty/customer account, table session/split bill, marketplace delivery integration, expiry batch/warehouse, advanced analytics, accounting/payroll.

**Catatan penting:** *Cashier takeaway* adalah V1; *online pickup* melalui website adalah V2. Semua pastry V1 dibeli siap jual dari supplier.

## 4. Alur end-to-end

### J1 — Table QR (prioritas utama)

1. Guest scan QR meja → sistem mengenali meja dan menampilkan menu aktif.
2. Guest pilih varian (mis. Iced Americano), Sugar Level (Low), pastry, dan jumlah.
3. Laravel validasi pilihan, hitung harga, cek stok tersedia; buat order `awaiting_payment` dan hold stok sementara.
4. Sistem minta **dynamic QRIS** dari provider; pembayaran hanya sukses setelah notifikasi provider **terverifikasi di backend**.
5. Order `queued` muncul sekali di kitchen; staf mulai `preparing` → stok yang di-hold dikonsumsi sekali.
6. Kitchen tandai `ready` lalu `completed`; guest melihat status melalui token order privat.
7. Pembayaran gagal/expired tidak masuk kitchen dan hold dibebaskan. Pembayaran terlambat setelah expiry masuk `payment_review`, bukan kitchen.

### J2 — Cashier

1. Kasir login, memilih produk/varian/opsi dan kuantitas.
2. Pilih `dine_in` (wajib meja) atau `takeaway` (tanpa meja).
3. Untuk **cash**, kasir menerima uang dan mengonfirmasi pembayaran; sistem memvalidasi stok dan harga, kemudian membuat paid order + hold stok. QRIS kasir memakai verifikasi provider saat modul QRIS tersedia.
4. Pesanan masuk kitchen, diproses dan diselesaikan seperti J1.

### J3 — Stock & purchasing

1. Owner mendaftarkan beans (g), milk (ml), syrup (ml), croissant (pcs).
2. Owner mengatur resep per varian (mis. Iced Americano = 18 g beans + bahan lain) dan tambahan syrup dari opsi sugar.
3. Owner membuat pembelian ke supplier: item, kuantitas **dalam satuan dasar stok**, total harga.
4. Membuat draft pembelian **belum** mengubah stok; saat seluruh barang diterima, stok naik dan movement tercatat satu kali.
5. Saat order mulai `preparing`, kopi mengurangi ingredient sesuai resep+opsi, pastry mengurangi 1 pcs per unit. Adjustment manual butuh alasan.

## 5. Aturan bisnis wajib

### Menu dan harga
- Satu produk punya **minimal satu varian**. Pastry sederhana memakai varian `Regular` (tidak perlu pilihan di UI).
- Opsi seperti Sugar Level merupakan option group yang dapat dipakai lebih dari satu produk; hanya pilihan yang ditugaskan ke produk tersebut yang valid.
- V1: option group ditugaskan **per produk**, bukan per varian. Sugar Level satu pilihan wajib. Extra additive option boleh opsional bila dibuat; substitusi bahan kompleks di luar scope.
- Harga dalam **integer IDR**. Server menghitung `(harga varian + jumlah tambahan opsi) × qty`, tidak mempercayai harga dari browser.
- Order menyimpan snapshot nama, varian, opsi, dan harga agar riwayat tak berubah saat menu diedit.

### Pembayaran, order, stok
- QR di meja adalah **link pemesanan**, bukan QRIS pembayaran. QRIS dinamis dibuat pada checkout.
- Pembayaran dan kitchen status terpisah. Order hanya `queued` sesudah cash terkonfirmasi atau QRIS verified.
- Multiple order terpisah dari meja yang sama boleh; scanning QR tidak otomatis membuat order; tanpa akun pelanggan.
- Stok tersedia = stok fisik `on_hand` dikurangi reservasi aktif. Reservasi order QRIS belum dibayar punya expiry; reservasi order berbayar bertahan sampai dikonsumsi atau dibatalkan.
- Stok fisik berkurang **satu kali** saat order pertama kali masuk `preparing`. Order dibatalkan sebelum preparing melepaskan hold; setelah preparing stok **tidak otomatis dikembalikan**.
- Stok tidak boleh negatif; semua perubahan saldo harus punya movement dalam transaksi database yang sama. Cegah double checkout/receipt/webhook/prepare.
- Pastry V1 adalah **finished item**, bukan resep produksi. Penjualan pastry langsung mengurangi pcs.
- Pembelian V1 hanya status `draft`, `received`, `cancelled`, tanpa penerimaan parsial. Jika kuantitas aktual berbeda, edit draft sebelum terima.
- Jika QRIS terbayar terlambat/terjadi exception, owner menyelesaikan melalui proses manual dengan provider dan mencatat penyelesaian; **refund otomatis bukan V1**.

### Keamanan dan akses
- Development F00–F03 boleh tanpa auth selama **localhost saja**, tanpa tunnel/port forwarding/data nyata.
- Minimal staff login ditambahkan **sebelum Purchasing dan POS**. Full fixed-role authorization selesai sebelum publik/deploy.
- Halaman manajemen menggunakan auth; pembatasan role diterapkan oleh Laravel middleware/policies/gates, bukan sekadar menyembunyikan tombol Vue.
- Guest order status memakai token terpisah yang sulit ditebak, bukan `order_id` biasa. Jangan percaya screenshot pembayaran/redirect browser.

## 6. Non-functional requirements

- UI responsive untuk ponsel customer, desktop/tablet cashier & kitchen.
- Validasi input server-side; CSRF/session untuk staff; throttle endpoint publik penting.
- Database transactions untuk checkout, reservasi, pembayaran, receiving, dan stok; idempotent pada retry.
- Testing otomatis pada business rules kritis, ditambah pengujian UI manual tiap fitur.
- Tidak ada secrets/API keys di repository. Fake provider hanya local/test, **tidak boleh aktif di production**.
- Aplikasi lokal dulu; rilis produksi butuh HTTPS, provider QRIS nyata, backup, dan security review.

## 7. Success criteria V1

- [ ] Americano Hot/Iced + Sugar Level dan Croissant Regular tampil dan dihitung benar.
- [ ] Owner bisa mencatat stok, resep, adjustment, pembelian supplier dan receiving; saldo & riwayat konsisten.
- [ ] Pesanan cash kasir (dine-in/takeaway) diproses dari checkout sampai completed.
- [ ] QR meja → QRIS verified → kitchen berhasil tanpa login pelanggan; unpaid order tidak masuk kitchen.
- [ ] Stok tidak negatif dan tidak terpotong dua kali meski request/callback duplikat.
- [ ] Owner/Cashier/Kitchen hanya bisa melakukan tindakan sesuai role; guest tidak mengakses management.
- [ ] Dashboard menghitung hanya transaksi dibayar (sesuaikan refund) dan menampilkan warning stok.
- [ ] Test dan pemeriksaan keamanan penting lulus sebelum deployment.

## 8. Keputusan yang belum final (tidak menghambat Menu/Inventory)

1. Provider QRIS nyata dan akun sandbox; **jangan mengklaim QRIS siap produksi sebelum integrasi diuji**.
2. Resep nyata dan satuan/konversi untuk setiap bahan café.
3. Pajak/service charge dan format struk untuk transaksi komersial.
4. Deployment, backup, dan kebijakan refund operasional jika akan digunakan sungguhan.

Dokumen ini adalah sumber kebenaran untuk scope dan business rules. Tambah fitur V2 hanya setelah V1 diuji.
