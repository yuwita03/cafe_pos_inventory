# UI/UX — Café POS V1

> Panduan UI/UX Café POS V1. Gambar pengguna menjadi referensi visual umum untuk membangun bahasa desain yang konsisten. Struktur halaman, komponen, dan alur disesuaikan dengan PRD serta kebutuhan pengguna aplikasi. Letakkan sebagai `documents/UI-UX.md` dan gunakan nama/path ini secara konsisten pada instruksi agent.

## 1. Otoritas dan batas desain

- `PRD.md` menentukan scope dan aturan bisnis; `DATABASE.md` menentukan data dan constraints; `ARCHITECTURE.md` menentukan implementasi. UI/UX menerjemahkannya menjadi pengalaman pengguna.
- Jika gambar bertentangan dengan PRD, ikuti PRD. Jika keputusan bisnis belum final, laporkan dan jangan mengarang requirement.
- Satu cabang, satu lokasi stok, IDR, online-first. Laravel + Vue 3 + Inertia; Laravel menangani route dan otorisasi.
- Nama café dan logo final belum ditentukan. Gunakan placeholder teks “Café POS”; jangan menyalin merek D.CC.
- Desain halaman yang tidak ada pada screenshot merupakan turunan dari sistem visual yang sama, bukan klaim bahwa screenshot tersebut tersedia.
- Builder tidak mengubah panduan ini, PRD, atau scope tanpa instruksi pengguna. Implementasi dilakukan per fitur sesuai TASKS, bukan membangun semua layar sekaligus.

## 2. Cara menggunakan referensi visual

**Semua gambar adalah inspirasi umum, bukan mockup final atau spesifikasi yang harus disalin persis.** Referensi membantu menentukan karakter aplikasi: ringan, bersih, terstruktur, kartu membulat, serta aksen oranye yang konsisten.

Builder boleh mengubah susunan panel, jumlah kolom, ukuran komponen, posisi tindakan, jenis daftar, dan cara membuka detail agar sesuai dengan data, perangkat, dan pekerjaan tiap role. Penyesuaian presentasi biasa dalam arah visual ini tidak memerlukan izin untuk setiap detail. Perubahan scope, aturan bisnis, atau arsitektur tetap mengikuti aturan proyek.

### 2.1 Koleksi referensi

Simpan file berikut di `documents/references/`. Nama file memudahkan menemukan gambar; tidak menetapkan pasangan wajib satu gambar untuk satu halaman.

| File               | Inspirasi yang dapat dipakai lintas aplikasi                             |
| ------------------ | ------------------------------------------------------------------------ |
| dashboard.png      | Hierarki informasi, shell navigasi, komposisi ringkasan dan ruang kosong |
| orders.png         | Daftar yang mudah dipindai, pencarian/filter dan penanda status          |
| orders_detil.png   | Pengelompokan informasi detail dan hierarki tindakan                     |
| tables.png         | Pengulangan kartu yang rapi dan identitas item yang jelas                |
| menus.png          | Penyajian foto produk, harga dan kontrol pemilihan                       |
| menus_form.png     | Form berkelompok, upload gambar dan pemisahan informasi                  |
| handling_menus.png | Hubungan navigasi kategori dengan daftar konten                          |
| delete_modal.png   | Dialog ringkas, penjelasan dampak dan tindakan konfirmasi                |

### 2.2 Prinsip adaptasi

- Ambil kualitas visual dan keterbacaan gambar sebagai satu kumpulan referensi. Pola kartu dari satu gambar boleh digunakan pada modul lain.
- Tentukan layout dari tugas pengguna: owner mengelola data, kasir bertransaksi cepat, kitchen membaca tiket, customer memesan melalui ponsel.
- Pilih tabel, kartu, list, dialog, drawer, atau halaman penuh sesuai kompleksitas. Tidak wajib mempertahankan bentuk komponen pada screenshot.
- Jumlah panel, kartu statistik, tab, tombol, teks, angka, label status, serta field pada gambar tidak menjadi requirement.
- Jangan menyalin merek, isi menu, nilai transaksi, error desain, atau fungsi di luar PRD. Frame di luar aplikasi pada gambar bukan elemen aplikasi.
- Halaman tanpa contoh tetap dirancang menggunakan bahasa visual yang sama. Tidak perlu mencari padanan screenshot untuk setiap layar.
- Evaluasi kesesuaian melalui konsistensi gaya, kemudahan penggunaan dan pemenuhan PRD. Tidak ada target pixel-perfect atau skor kemiripan screenshot.

### 2.3 Prioritas keputusan

1. Scope, business rules, data dan akses dalam dokumentasi proyek.
2. Kejelasan alur, aksesibilitas dan kenyamanan perangkat sasaran.
3. Konsistensi bahasa visual aplikasi.
4. Inspirasi dari gambar referensi.

Bagian berikut membedakan kebutuhan perilaku yang wajib dari contoh solusi visual. **Angka ukuran, komposisi dan pilihan komponen merupakan baseline yang dapat disesuaikan**, selama hasilnya konsisten, mudah digunakan dan memenuhi batas bisnis.

## 3. Arah visual

Tampilan terang, bersih, ringkas, dominan putih dan abu sangat muda. Oranye menandai navigasi aktif dan aksi utama. Foto coffee/pastry memberi karakter; hindari ornamen dekoratif yang mengganggu pekerjaan kasir.

### 3.1 Design tokens

Nilai berikut adalah baseline implementasi untuk menjaga konsistensi, bukan warna/font yang wajib identik dengan gambar. Builder dapat menyesuaikan token secara terpusat untuk kontras dan keterbacaan; hindari nilai berbeda-beda tanpa alasan pada tiap halaman.

| Token          | Nilai             | Penggunaan                           |
| -------------- | ----------------- | ------------------------------------ |
| canvas         | #F5F5F5           | Latar aplikasi                       |
| surface        | #FFFFFF           | Sidebar, kartu, dialog               |
| text           | #171717           | Teks utama                           |
| text-muted     | #616161           | Deskripsi, metadata                  |
| border         | #E5E5E5           | Pemisah, input, tabel                |
| accent         | #F4511E           | Ikon, grafik, aksen visual           |
| primary        | #C63C0C           | Tombol dengan teks putih, link aktif |
| primary-hover  | #A93209           | Hover tombol utama                   |
| primary-soft   | #FFF1E9           | Background navigasi/filter aktif     |
| success / soft | #166534 / #ECFDF3 | Pembayaran berhasil, completed       |
| warning / soft | #854D0E / #FEF9C3 | Pending, stok rendah                 |
| info / soft    | #1D4ED8 / #EFF6FF | Preparing, informasi                 |
| danger / soft  | #B91C1C / #FEF2F2 | Gagal, batal, tindakan destruktif    |

- Font: Inter jika tersedia, fallback `system-ui, sans-serif`. Body 14px/1.5; customer 16px; metadata minimal 12px; judul halaman 24px/600; judul section 16px/600; KPI 30–32px/600.
- Angka harga memakai tabular numerals, rata kanan di tabel. Format `Rp25.000`; integer IDR, tanpa cents atau currency switcher.
- Spacing: 4, 8, 12, 16, 24, 32px. Card padding 16–24px; gap utama 16px.
- Radius: input/tombol 8px; kartu 14px; dialog 20px; badge pill. Shadow sangat halus; jangan menumpuk shadow pada setiap elemen.
- Ikon outline satu keluarga, ukuran 18–20px; tombol ikon punya accessible name. Jangan menambah library jika project sudah memiliki set yang sesuai.
- Screenshot memakai oranye terang: pertahankan karakternya untuk dekorasi, tetapi gunakan primary lebih gelap untuk keterbacaan teks putih. Uji kontras aktual sebelum final.

### 3.2 Rekomendasi struktur layout

| Area     | Desktop ≥1280px                    | Tablet 768–1279px                               | Mobile <768px                                    |
| -------- | ---------------------------------- | ----------------------------------------------- | ------------------------------------------------ |
| Owner    | Sidebar 224px, konten fleksibel    | Sidebar drawer; tabel boleh scroll internal     | Drawer; form satu kolom; daftar ringkas          |
| Cashier  | Katalog + cart kanan 360px         | Cart 320px jika ruang cukup; jika tidak, drawer | Katalog + tombol cart sticky; cart halaman/sheet |
| Kitchen  | Sidebar ringkas/drawer; grid tiket | Grid 2 kolom bila muat                          | Tiket satu kolom, filter status                  |
| Customer | Konten terpusat max 720px          | Layout customer yang sama                       | Mobile-first, padding 16px, CTA bawah            |

Staff shell: sidebar putih, logo atas, navigasi tengah, akun+role+logout bawah. Header konten berisi judul, deskripsi singkat dan aksi kontekstual. Konten memakai sisa viewport; jangan meniru margin screenshot sebagai bingkai besar tetap.

Customer tidak memakai sidebar staff. Header berisi nama café dan label meja. Footer CTA menghormati safe-area; beri padding konten agar item terakhir tidak tertutup. Tidak boleh ada horizontal scroll pada seluruh halaman di lebar 360px.

## 4. Navigasi dan akses

| Aktor    | Navigasi                                                                                |
| -------- | --------------------------------------------------------------------------------------- |
| Owner    | Dashboard, Menu, Inventory, Supplier, Pembelian, Meja & QR, Pesanan, POS, Kitchen, Staf |
| Cashier  | POS, transaksi terkait, akun/logout                                                     |
| Kitchen  | Antrean kitchen, akun/logout                                                            |
| Customer | Menu meja, keranjang, pembayaran, status order miliknya                                 |

Owner adalah role data resmi; `/admin` hanya nama area antarmuka, bukan role tambahan. Menu disembunyikan sesuai akses, dan Laravel tetap menolak request tanpa izin.

Route berikut adalah rancangan navigasi, boleh mengikuti nama route existing selama perilaku sama:

| Route usulan      | Layar                                    |
| ----------------- | ---------------------------------------- |
| /login            | Login staff                              |
| /admin            | Dashboard owner                          |
| /admin/menu       | Produk, kategori, option groups          |
| /admin/inventory  | Stok, detail movement, adjustment, resep |
| /admin/suppliers  | Supplier                                 |
| /admin/purchases  | Daftar/detail/form pembelian             |
| /admin/tables     | Meja & QR                                |
| /admin/orders     | Pesanan dan payment review owner         |
| /admin/staff      | Akun staff                               |
| /cashier          | POS kasir                                |
| /cashier/orders   | Transaksi dalam cakupan akses kasir      |
| /kitchen          | Antrean berbayar                         |
| /t/{qr_token}     | Menu meja publik                         |
| /o/{public_token} | Pembayaran/status privat order           |

Keranjang dapat menjadi state pada menu, bukan wajib route terpisah. Token meja tidak memberi akses ke daftar order pelanggan lain. Token order tidak ditampilkan pada daftar publik.

## 5. Kebutuhan layar owner dan rekomendasi penyajian

Informasi dan perilaku bisnis di bagian ini mengikuti PRD. Susunan visualnya dapat diadaptasi; deskripsi panel/kartu bukan kewajiban meniru screenshot.

### 5.1 Dashboard

- Header “Dashboard”, filter periode sederhana dan keterangan periode aktif.
- Kartu: total penjualan terkonfirmasi, jumlah order dibayar, jumlah item stok rendah. Jangan tampilkan jumlah pelanggan unik karena guest tidak punya akun.
- Grafik penjualan harian sederhana, daftar produk terlaris dan warning stok. Seluruh panel mengikuti periode yang ditampilkan; warning stok menggambarkan kondisi saat ini dan diberi label demikian.
- Total mengikuti aturan pembayaran/refund PRD; payment_review ditampilkan terpisah sebagai kasus perlu pemeriksaan, tidak otomatis dihitung sebagai penjualan sukses.
- Tanpa growth percentage, occupancy, atau revenue palsu. Saat belum ada transaksi, tampilkan Rp0 dan empty state, bukan data contoh.

### 5.2 Menu

- Kategori dapat berupa panel, tab, chips, atau filter di atas daftar. Pilih pola yang memudahkan pencarian produk; tidak wajib memakai panel kiri seperti gambar.
- Toolbar: pencarian, filter aktif/nonaktif, “Tambah produk”, akses pengelolaan kategori dan opsi. Urut alfabetis; tanpa drag sort.
- Baris produk: thumbnail, nama, kategori, harga “Mulai Rp…” jika bervariasi, status aktif, “Edit”. Preview customer terpisah boleh dipakai untuk mengecek tampilan; tidak membuat proses publish tambahan.
- Form produk: kategori, nama, deskripsi opsional, gambar opsional, aktif/nonaktif; section varian minimal satu dan section assignment option groups.
- Varian berisi nama, harga IDR, tersedia/tidak. Coffee memakai Hot/Iced sesuai konfigurasi; pastry sederhana Regular. Harga tidak ditaruh sebagai satu harga produk jika varian berbeda.
- Assignment opsi per produk. Single menggunakan radio; multiple checkbox. Required diberi label. Sugar Level wajib satu pilihan; jangan memilih diam-diam tanpa konfigurasi default yang disetujui.
- Resep per varian berada pada section terkait Inventory. Pastry dihubungkan ke finished stock pcs, tanpa form resep produksi.
- Upload: preview, ganti, hapus gambar opsional, feedback error sesuai batas backend. Jangan mengarang ukuran/format yang berbeda dari validasi server.
- Nonaktifkan lewat konfirmasi yang menjelaskan dampak terhadap penjualan baru. Riwayat transaksi tidak terhapus. Jangan menyediakan cascade delete kategori beserta produknya.

### 5.3 Inventory dan resep

- Tabel: nama, unit dasar, stok fisik, direservasi, tersedia, reorder point, indikator rendah. Bedakan ketiganya dengan header jelas, bukan satu angka “Stock”.
- Label status rendah mengikuti aturan backend; jangan membuat threshold berbeda di Vue. Jika basis warning belum ditetapkan implementasi, konfirmasikan sebelum menyamakan available dengan on_hand.
- Detail: ringkasan saldo, riwayat movement berisi waktu, delta bersatuan, alasan/sumber, aktor bila tersedia. Movement read-only.
- Adjustment: item, delta bertanda atau arah tambah/kurang yang eksplisit, jumlah, alasan wajib; ringkasan saldo setelah perubahan dari validasi server. Tolak hasil negatif dan pertahankan input saat gagal.
- Resep: pilih varian coffee, baris bahan + jumlah unit dasar. Opsi sugar dapat menambah bahan. Jangan menawarkan substitusi atau konversi satuan otomatis.
- Pastry: stok pcs dan hubungan Regular → stock item; tidak ada batch baking.

### 5.4 Supplier dan pembelian

- Supplier: nama, kontak opsional, status aktif, edit/nonaktifkan.
- Daftar pembelian: referensi, supplier, tanggal, total, status draft/received/cancelled.
- Form draft: supplier, stok item, kuantitas dalam g/ml/pcs sesuai item, total biaya tiap baris IDR. Bedakan biaya total baris dari biaya per unit. Cegah item duplikat.
- Total pembelian dihitung dari biaya baris. Simpan draft tidak menaikkan stok.
- Detail draft: “Edit”, “Terima seluruh barang”, “Batalkan”. Dialog penerimaan merangkum barang/jumlah dan menjelaskan stok akan bertambah sekali.
- Jika barang berbeda jumlah, arahkan edit draft dulu. Tanpa partial receiving/approval PO.
- Received: read-only, tampilkan penerima dan waktu. Refresh/retry tidak menambah stok lagi. Cancelled tidak memiliki aksi receive.

### 5.5 Meja & QR

- Meja dapat disajikan sebagai grid kartu atau daftar: label meja, aktif/nonaktif, lihat/unduh/cetak QR pemesanan. Print/download merupakan penyajian QR yang sama, bukan modul laporan baru.
- QR membawa link `/t/{qr_token}`, bukan kode pembayaran. Beri caption “Scan untuk pesan dari meja ini”.
- Tidak tampilkan status reserved/occupied atau kapasitas fiktif. Meja aktif berarti bisa menerima akses menu, bukan pasti sedang kosong.
- Tidak ada reservasi meja, floor plan, split bill, atau session meja. Banyak order terpisah dari meja yang sama tetap sah.
- Rotasi token dan pengaruh terhadap link yang sudah dicetak perlu keputusan eksplisit jika ingin ditambahkan; jangan membuat aksi itu diam-diam.

### 5.6 Staf dan login

- Login: email, password, show/hide password, error generik, loading. Tidak ada register publik staff atau sosial login.
- Form staff: nama, email, password saat perlu, role tetap owner/cashier/kitchen, status aktif. Jangan buat role editor/dynamic permissions.
- Deactivated account ditolak backend; ketika sesi tidak berlaku, tampilkan pesan dan arahkan login. Tampilkan 403 jika role tidak berhak.

## 6. Kasir POS

### 6.1 Komposisi

Rekomendasi desktop: area katalog dan cart yang mudah dijangkau bersamaan. Katalog memuat pencarian, kategori, foto, nama dan harga; cart memuat nama, varian, opsi, qty, subtotal, jenis layanan, meja bila dine-in, total dan checkout. Posisi dan proporsi panel dapat disesuaikan dengan perangkat serta kemudahan transaksi.

- Produk dengan pilihan membuka dialog “Pilih pesanan”. Varian Regular tunggal tidak perlu selector; opsi wajib tetap harus dipilih.
- Item konfigurasi berbeda menjadi baris terpisah. Item sama dapat digabung; edit tidak membuang pilihan lain.
- Quantity minimal 1; menghapus lewat aksi terpisah yang jelas. Tidak menjual stok unavailable atau melebihi hasil validasi backend.
- Dine-in wajib meja; takeaway tidak membawa table_id. Mengubah layanan memperbarui form secara jelas.

### 6.2 Cash

1. Kasir memeriksa ringkasan dan memilih Cash.
2. Tampilkan total dan, sebagai bantuan hitung lokal, uang diterima/kembalian. Nilai ini tidak menjadi janji field akuntansi baru; penyimpanan membutuhkan keputusan data terpisah.
3. “Konfirmasi pembayaran tunai” hanya setelah uang diterima. Jika bantuan nominal digunakan, nilai kurang dari total memblokir konfirmasi.
4. Tombol loading+disabled selama request. Sukses hanya setelah server menyimpan paid dan queued.
5. Ringkasan sukses menampilkan nomor order, total, layanan/meja dan “Pesanan baru”. Jangan menghapus cart sebelum server mengonfirmasi.

QRIS hanya tersedia setelah modul provider siap. Alurnya sama dengan guest; tidak ada tombol kasir “Tandai QRIS lunas”. Format struk/pajak belum final: jangan menambah pajak, service charge atau template struk komersial sendiri.

## 7. Customer QR — mobile-first

### 7.1 Menu dan konfigurasi

- Scan QR valid langsung membuka menu dengan konteks “Meja A01”; tidak membuat order atau meminta akun.
- Kategori chips, search sederhana, grid 1–2 kolom sesuai ruang, foto proporsional, nama dan harga jelas. Urut alfabetis mengikuti data.
- Foto kosong menggunakan placeholder netral; jangan mengambil foto tidak terkait. Nama panjang wrap, tidak menghilangkan harga.
- Detail produk berupa bottom sheet mobile/dialog desktop: foto, deskripsi, varian, option groups, qty, harga estimasi dan “Tambah ke keranjang”.
- Opsi wajib diberi label dan error dekat group; jika ada satu Regular, tidak perlu menampilkan kata Regular pada selector.
- Menu nonaktif tidak ditawarkan. Stok habis/perubahan ketersediaan diberi label dan tombol tambah disabled sesuai informasi server; checkout tetap memvalidasi ulang.

### 7.2 Keranjang dan checkout

- Ringkasan berisi semua varian/opsi, qty, harga, meja dan total. Edit/hapus tersedia sebelum checkout.
- Tombol sticky “Lihat keranjang · 3 item · Rp75.000”. Di checkout gunakan “Lanjut ke pembayaran”.
- Total resmi dari server. Jika harga atau stok berubah, jelaskan baris terdampak dan minta pengguna meninjau ulang; jangan mengganti opsi atau menaikkan harga tanpa informasi.
- Checkout memakai key yang stabil saat retry. Double tap tidak menghasilkan dua order. Request gagal yang hasilnya belum diketahui harus direkonsiliasi sebelum menawarkan checkout baru.

### 7.3 Pembayaran QRIS

- Halaman: nomor order, meja, total final, QRIS dinamis, waktu expiry dari server, instruksi bayar dan status “Menunggu pembayaran”.
- Jelaskan perbedaan QR meja untuk membuka menu dengan QRIS untuk membayar order ini.
- Pembayaran di ponsel yang sama mengikuti fasilitas provider yang benar-benar tersedia; jangan menjanjikan deeplink/download QR sebelum diuji. Selalu beri instruksi provider yang relevan.
- Browser redirect, screenshot, dan tombol refresh tidak bisa mengesahkan pembayaran. Jika ada “Cek status”, tombol hanya meminta status backend.
- Expired/failed: status jelas, bukan spinner tanpa akhir. Tawarkan kembali ke menu atau percobaan baru hanya setelah backend memastikan keadaan sebelumnya. Jangan otomatis membuat order baru pada refresh.
- Setelah paid terverifikasi, ganti ke status order. Jangan memaksa pelanggan login.
- Jika late-paid: “Pembayaran perlu diperiksa. Hubungi kasir dengan nomor pesanan ini.” Tidak masuk kitchen, tidak menjanjikan refund otomatis, tidak meminta bayar lagi.

### 7.4 Status privat

- Timeline: Menunggu pembayaran → Dalam antrean → Sedang dibuat → Siap → Selesai. Pembatalan dan payment review ditampilkan sebagai cabang kondisi, bukan langkah normal.
- Detail snapshot item/opsi, meja, total, payment status. Halaman hanya berisi order token tersebut.
- “Siap” tidak diartikan “sudah diantar”; jangan menciptakan state served atau pickup online.
- Tampilkan pembaruan terakhir dan indikator reconnect saat jaringan putus. Tidak ada estimasi menit kecuali sumber perhitungannya disetujui.
- QR/token invalid: pesan singkat dan arahan hubungi kasir; jangan membocorkan order lain atau membiarkan pemilihan meja sembarang.

## 8. Kitchen display

- Satu antrean untuk cashier dan table QR. Hanya order berbayar yang sah; awaiting_payment/payment_review tidak muncul sebagai tiket kerja.
- Filter/tab Queued, Preparing, Ready, Completed sesuai kebutuhan layar. Default fokus order aktif, oldest first agar tidak terlewat.
- Tiket: nomor order, meja/takeaway, sumber, waktu masuk/durasi aktual, qty besar, produk, varian dan opsi jelas. Tampilkan notes jika memang tersedia dari order.
- Aksi tunggal sesuai state: “Mulai buat” → “Tandai siap” → “Selesaikan”. Tidak ada skip bebas atau tombol rollback status.
- Mulai buat mengonsumsi stok sekali. Jika konflik, pertahankan tiket dan jelaskan hasil server; jangan mengubah UI menjadi preparing sebelum berhasil.
- Gunakan polling sederhana sesuai arsitektur; interval awal usulan 5 detik, dapat disesuaikan. Tampilkan terakhir diperbarui, loading awal, reconnect dan refresh manual.
- Jangan reset fokus/posisi scroll setiap polling. Saat staf lain memproses tiket, tampilkan status terkini tanpa menduplikasi tiket.
- Tombol sentuh minimal 44px; informasi varian/sugar tidak boleh tersembunyi dalam tooltip.

## 9. Pesanan, pembayaran dan tindakan berisiko

### 9.1 Daftar dan detail

- Daftar order dapat berupa kartu, tabel, atau list sesuai volume dan perangkat. Pilih satu pola utama yang mudah dipindai; jumlah kolom tidak ditentukan gambar dan toggle view tidak wajib.
- Filter status order dan status payment terpisah, search nomor order, periode bila relevan. Filter menyatakan cakupannya dan bertahan saat kembali dari detail.
- Kartu: nomor, sumber, layanan/meja, waktu, total dan dua badge berlabel. Summary counts harus menjelaskan periode/cakupan; jangan memberi angka total berbeda tanpa konteks.
- Detail dapat berupa dialog, drawer, atau halaman sendiri. Untuk dialog, baseline lebar 720px dan tinggi maksimal 90dvh dengan body scroll; sesuaikan dengan isi. Pada mobile utamakan ruang baca yang cukup.
- Section: informasi, payment, item snapshot dan total, tindakan relevan. Jangan membuat operation history generik karena audit log tidak tersedia pada data V1; tampilkan hanya timestamp/aktor yang nyata.

### 9.2 Status resmi

| Domain  | Nilai data       | Label UI            | Semantik                                            |
| ------- | ---------------- | ------------------- | --------------------------------------------------- |
| Order   | awaiting_payment | Menunggu pembayaran | Belum boleh dikerjakan kitchen                      |
| Order   | queued           | Dalam antrean       | Berbayar, menunggu dibuat                           |
| Order   | preparing        | Sedang dibuat       | Stok sudah dikonsumsi                               |
| Order   | ready            | Siap                | Siap ditindaklanjuti operasional                    |
| Order   | completed        | Selesai             | Pemrosesan selesai                                  |
| Order   | cancelled        | Dibatalkan          | Bukan otomatis refund                               |
| Order   | payment_review   | Perlu pemeriksaan   | Owner menangani exception pembayaran                |
| Payment | pending          | Menunggu            | Belum terverifikasi                                 |
| Payment | paid             | Dibayar             | Terverifikasi                                       |
| Payment | failed           | Gagal               | Jangan masuk kitchen                                |
| Payment | expired          | Kedaluwarsa         | Hold unpaid dibebaskan backend                      |
| Payment | refunded         | Dikembalikan        | Hanya jika tercatat sah; bukan aksi refund otomatis |

Selalu gunakan teks, tidak hanya warna. Paid dan Completed adalah fakta berbeda.

### 9.3 Cancel dan payment review

- PRD menetapkan dampak pembatalan stok tetapi belum merinci siapa boleh membatalkan, batas waktunya, dan mekanisme pencatatan penyelesaian payment review. Konfirmasikan hak aksi/field sebelum mengaktifkan kontrol final.
- Jika cancel diizinkan: dialog menjelaskan unpaid/paid dan state kitchen. Sebelum preparing hold dilepas; setelah preparing stok tidak otomatis kembali. Pembatalan tidak otomatis mengembalikan uang.
- Jangan menampilkan “Refund otomatis” atau “Kembalikan stok” sebagai satu paket cancel.
- Payment review terlihat owner dengan nomor order, nominal, reference/status yang aman dan waktu tersedia. Penyelesaian manual mengikuti keputusan/provider; tidak ada tombol “Lunas” yang melewati verifikasi.

## 10. Komponen dan perilaku bersama

| Komponen              | Aturan                                                                           |
| --------------------- | -------------------------------------------------------------------------------- |
| Primary button        | Satu aksi utama per konteks; teks berupa tindakan; loading mencegah submit ulang |
| Secondary button      | Outline/netral untuk batal/kembali; tidak bersaing dengan primary                |
| Destructive button    | Merah, bukan oranye brand; konfirmasi dampak konkret                             |
| Input                 | Label permanen, helper seperlunya, error inline; placeholder bukan label         |
| Select/radio/checkbox | Sesuai single/multiple dan required; navigasi keyboard berfungsi                 |
| Search/filter         | Search icon opsional, clear filter, empty result berbeda dari dataset kosong     |
| Status badge          | Label konsisten dan kontras; bukan tombol kecuali memang filter                  |
| Dialog                | Judul jelas, close, fokus terperangkap, Esc jika aman, fokus kembali ke pemicu   |
| Toast                 | Feedback sukses singkat; error kritis juga tampil inline, tidak hilang sendirian |
| Data table            | Header jelas, angka rata kanan, unit eksplisit; scroll hanya pada wrapper        |
| Product image         | Rasio konsisten, object-fit cover, alt bermakna bila informatif                  |

- Form tidak kehilangan isi karena error validasi. Field error pertama mendapat fokus; form panjang punya error summary bila bermanfaat.
- Dirty form meminta konfirmasi saat pengguna sengaja pergi. Jangan tampilkan modal untuk setiap perubahan kecil.
- Nonaktifkan tidak boleh berupa switch optimistis yang tetap terlihat sukses ketika request gagal. Jelaskan dampak dan kembalikan state jika ditolak.
- Aksi receive/payment/prepare memerlukan hasil server. Disabled button adalah bantuan UX; idempotency backend tetap wajib.

## 11. Loading, empty, error dan koneksi

| Kondisi                | Tampilan/tindakan                                                                |
| ---------------------- | -------------------------------------------------------------------------------- |
| Loading awal           | Skeleton mengikuti layout; hindari angka demo                                    |
| Submit berjalan        | Spinner dan label “Menyimpan…”/“Memproses…”; satu request aktif                  |
| Data kosong            | Penjelasan singkat + CTA sesuai izin, mis. Tambah produk                         |
| Filter kosong          | “Tidak ada hasil untuk filter ini” + Reset filter                                |
| Stok berubah           | Sebut item terdampak, pertahankan cart dan arahkan edit                          |
| Jaringan putus         | Banner persistent; status terakhir diberi waktu; jangan klaim offline POS        |
| Request timeout        | Hasil belum diketahui; cek hasil server sebelum mengulang operasi keuangan       |
| 403                    | Akses ditolak, link kembali ke area role                                         |
| Sesi berakhir          | Pesan login kembali; jangan mengulang pembayaran otomatis                        |
| Payment provider error | Status gagal membuat QR dibedakan dari gagal bayar; retry aman mengikuti backend |
| QR invalid/nonaktif    | Arahan hubungi kasir, tanpa daftar order/meja sensitif                           |

## 12. Accessibility dan bahasa

- Bahasa utama Indonesia, istilah konsisten; gunakan “Pesanan”, “Pembelian”, “Stok tersedia”, bukan campuran istilah acak. Nama varian tetap sesuai katalog.
- Target kontras WCAG AA: teks biasa 4.5:1, teks besar 3:1, batas/fokus kontrol 3:1. Verifikasi pasangan warna aktual.
- Semua aksi keyboard-accessible; focus ring jelas; struktur heading dan label semantik.
- Target sentuh minimal 44×44px; jangan meniru teks terlalu kecil pada screenshot. Zoom 200% tetap usable.
- Status dinamis diumumkan secara sopan melalui live region; polling tidak membacakan ulang seluruh layar.
- Respect reduced motion; transisi singkat 120–180ms, tanpa animasi dekoratif pada POS/KDS.
- Tanggal dan waktu mengikuti konfigurasi café, diberi konteks zona waktu bila perlu. Jangan hardcode timezone browser sebagai waktu transaksi resmi.

## 13. Bukan bagian UI V1

Tidak ada multi-store switcher, menu musiman bertingkat, AI generate/recognition, featured products, custom ordering drag-and-drop, reservasi/occupancy meja, kapasitas/area meja, customer accounts, loyalty, online pickup, delivery, split bill, pastry production, PO approval/partial receipt, advanced analytics, payroll, atau refund otomatis.

Jangan memasang menu kosong “Coming soon”, bell notifikasi, Help/Settings generik, tombol dekoratif, angka statistik palsu, atau tab dari screenshot jika tidak ada fungsi V1 di belakangnya.

## 14. Implementasi dan handoff agent

1. Baca AGENTS, PRD, ARCHITECTURE, DATABASE, TASKS dan panduan ini dari lokasi sebenarnya.
2. Inspeksi UI existing; pertahankan komponen yang sudah sesuai. Jangan mengganti starter kit demi meniru screenshot.
3. Bangun token/shell bersama dan satu halaman Menu sebagai kalibrasi visual pada task yang sesuai. Tampilkan hasil untuk review visual sebelum menyebarkan pola ke banyak halaman.
4. Implementasikan fitur secara vertikal mengikuti backlog: UI, validasi server, aksi dan feedback yang benar. Jangan membuat seluruh backend/halaman di luar task aktif.
5. Pisahkan OwnerLayout, CashierLayout, KitchenLayout dan CustomerLayout hanya sejauh memang diperlukan; gunakan komponen bersama untuk button, badge, dialog dan input. Hindari generic form builder/design framework baru.
6. Builder melaporkan hasil, tes aktual dan alasan pilihan layout yang penting. Tidak perlu membuat daftar selisih pixel terhadap gambar. QA memeriksa alur dan layar; Reviewer memeriksa scope, otorisasi dan perubahan docs. Jangan menandai task selesai tanpa workflow proyek.

### Checklist penerimaan UI/UX

- [ ] Bahasa visual bersih, ringan dan konsisten dengan inspirasi referensi; layout cocok untuk tugas pengguna tanpa kewajiban meniru susunan gambar.
- [ ] Layout diuji pada 360, 768, 1024 dan 1440px; tidak ada konten/CTA tertutup.
- [ ] Keyboard, fokus dialog, label, kontras dan zoom diperiksa.
- [ ] Produk Regular tidak memaksa pemilihan; varian dan sugar wajib tervalidasi; total server benar.
- [ ] Cashier dine-in wajib meja; takeaway tanpa meja; cart dipertahankan saat gagal.
- [ ] QR meja berbeda dengan QRIS; pending/expired/late-paid jelas dan tidak masuk kitchen.
- [ ] Queued → preparing → ready → completed mengikuti server; retry tidak mengonsumsi stok dua kali.
- [ ] Fisik/reservasi/tersedia ditampilkan berbeda; unit g/ml/pcs tidak ambigu.
- [ ] Draft pembelian tidak menambah stok; receive seluruhnya hanya sekali; received read-only.
- [ ] Payment dan order status terpisah; snapshot dan total konsisten.
- [ ] Role tidak dapat mengakses aksi terlarang lewat URL/request langsung; guest hanya melihat order sendiri.
- [ ] Empty/loading/error/reconnect ditinjau; tidak ada mock stats atau kontrol tanpa aksi.
- [ ] Tidak ada fitur di luar V1 yang tersalin dari gambar.

## 15. Keputusan terbuka sebelum implementasi terkait

Brand/logo final; provider QRIS dan kemampuan bayar pada ponsel yang sama; resep nyata; pajak/service charge dan struk; hak dan batas pembatalan order; basis threshold warning jika belum ditetapkan kode; serta penyimpanan penyelesaian manual payment review. Keputusan ini tidak menghambat implementasi shell, kategori dan menu dasar.

## 16. Arahan singkat untuk Builder

Gunakan seluruh gambar di `documents/references/` sebagai inspirasi visual umum. Rancang UI berdasarkan PRD dan kebutuhan tiap role. Pertahankan karakter terang, rapi, kartu membulat dan aksen oranye, tetapi sesuaikan layout, ukuran serta komponen agar nyaman dipakai. Tidak perlu menyalin screenshot secara persis. Jangan menganggap fitur atau field dalam gambar sebagai requirement baru. Gunakan komponen existing jika sesuai dan laporkan hanya keputusan bisnis yang benar-benar belum jelas.
