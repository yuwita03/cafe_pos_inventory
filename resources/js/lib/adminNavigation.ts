import {
    dashboard,
    menu,
    categories,
    options,
    suppliers,
    purchases,
    tables,
    orders,
    staff,
    pos,
    kitchen,
} from '@/routes/admin';
import { index as inventory, recipes, history } from '@/routes/admin/inventory';
import type { Auth } from '@/types';

type NavigationItem = {
    label: string;
    capability: keyof Auth['capabilities'];
    description: string;
    icon: string;
    href: typeof dashboard;
    prototype?: boolean;
    placeholder?: boolean;
    ownerOnly?: boolean;
};

export const adminNavigation: {
    label: string;
    icon?: string;
    items: NavigationItem[];
}[] = [
    {
        label: 'Workspace',
        items: [
            {
                label: 'Dashboard',
                capability: 'manage-workspace' as const,
                description: 'Ringkasan operasional',
                icon: 'grid',
                href: dashboard,
                prototype: true,
            },
        ],
    },
    {
        label: 'Kelola Menu',
        icon: 'coffee',
        items: [
            {
                label: 'Produk',
                capability: 'manage-workspace' as const,
                description: 'Menu & varian',
                icon: 'coffee',
                href: menu,
            },
            {
                label: 'Kategori',
                capability: 'manage-workspace' as const,
                description: 'Kelompok menu',
                icon: 'category',
                href: categories,
            },
            {
                label: 'Grup opsi',
                capability: 'manage-workspace' as const,
                description: 'Pilihan pelanggan',
                icon: 'options',
                href: options,
            },
        ],
    },
    {
        label: 'Inventory',
        icon: 'category',
        items: [
            {
                label: 'Stok',
                capability: 'manage-workspace' as const,
                description: 'Bahan & pastry',
                icon: 'category',
                href: inventory,
            },
            {
                label: 'Resep & opsi',
                capability: 'manage-workspace' as const,
                description: 'Per varian & sugar',
                icon: 'coffee',
                href: recipes,
            },
            {
                label: 'Riwayat stok',
                capability: 'manage-workspace' as const,
                description: 'Movement & adjustment',
                icon: 'history',
                href: history,
            },
        ],
    },
    {
        label: 'Pengadaan',
        icon: 'purchase',
        items: [
            {
                label: 'Supplier',
                capability: 'manage-purchasing' as const,
                description: 'Pemasok café',
                icon: 'category',
                href: suppliers,
                ownerOnly: true,
            },
            {
                label: 'Pembelian',
                capability: 'manage-purchasing' as const,
                description: 'Draft & penerimaan',
                icon: 'purchase',
                href: purchases,
                ownerOnly: true,
            },
        ],
    },
    {
        label: 'Operasional',
        icon: 'orders',
        items: [
            {
                label: 'Kasir POS',
                capability: 'use-pos' as const,
                description: 'Dine-in & takeaway',
                icon: 'pos',
                href: pos,
                prototype: false,
            },
            {
                label: 'Kitchen',
                capability: 'use-kitchen' as const,
                description: 'Antrean berbayar',
                icon: 'coffee',
                href: kitchen,
                prototype: false,
            },
            {
                label: 'Pesanan',
                capability: 'use-pos' as const,
                description: 'Order & pembayaran',
                icon: 'orders',
                href: orders,
                prototype: false,
            },
            {
                label: 'Meja & QR',
                capability: 'manage-workspace' as const,
                description: 'Meja dan menu QR',
                icon: 'grid',
                href: tables,
                ownerOnly: true,
            },
        ],
    },
    {
        label: 'Akses staf',
        icon: 'staff',
        items: [
            {
                label: 'Staf & login',
                capability: 'manage-staff' as const,
                description: 'Akun & fixed roles',
                icon: 'staff',
                href: staff,
            },
        ],
    },
];

export const workflowModules = {
    suppliers: {
        title: 'Supplier',
        group: 'Pengadaan',
        stage: '05',
        icon: 'category',
        description: 'Kelola pemasok bahan dan pastry siap jual.',
        steps: [
            'Catat nama dan kontak supplier',
            'Pilih supplier saat membuat draft pembelian',
            'Nonaktifkan supplier tanpa menghapus riwayat',
        ],
        next: purchases,
        nextLabel: 'Lihat alur pembelian',
    },
    purchases: {
        title: 'Pembelian',
        group: 'Pengadaan',
        stage: '05',
        icon: 'purchase',
        description: 'Dari draft pembelian hingga seluruh barang diterima.',
        steps: [
            'Pilih supplier, item, kuantitas dasar dan total biaya baris',
            'Simpan draft; saldo stok belum berubah',
            'Koreksi draft jika jumlah barang datang berbeda',
            'Terima seluruh barang sekali; stok dan movement bertambah',
        ],
        next: inventory,
        nextLabel: 'Buka stok',
    },
    pos: {
        title: 'Kasir POS',
        group: 'Operasional',
        stage: '06',
        icon: 'pos',
        description: 'Pemesanan kasir dengan pembayaran tunai lebih dahulu.',
        steps: [
            'Pilih produk, varian, opsi dan jumlah',
            'Pilih dine-in dengan meja atau takeaway',
            'Konfirmasi cash setelah uang diterima',
            'Order paid/queued memegang reservasi dan masuk kitchen',
        ],
        next: kitchen,
        nextLabel: 'Lihat alur kitchen',
    },
    kitchen: {
        title: 'Kitchen',
        group: 'Operasional',
        stage: '07',
        icon: 'coffee',
        description: 'Satu antrean pesanan berbayar dari kasir dan QR meja.',
        steps: [
            'Queued: pesanan berbayar menunggu dibuat',
            'Preparing: konsumsi reservasi dan potong stok sekali',
            'Ready: tandai pesanan siap',
            'Completed: selesaikan tanpa potong stok lagi',
        ],
        next: history,
        nextLabel: 'Buka riwayat stok',
    },
    orders: {
        title: 'Pesanan',
        group: 'Operasional',
        stage: '06–08',
        icon: 'orders',
        description:
            'Lihat status order dan pembayaran sebagai dua informasi terpisah.',
        steps: [
            'Cari order berdasarkan nomor dan status',
            'Lihat snapshot produk, opsi dan harga',
            'Periksa status payment secara terpisah dari kitchen',
            'Owner menangani payment review melalui proses manual',
        ],
        next: pos,
        nextLabel: 'Lihat alur kasir',
    },
    staff: {
        title: 'Staf & login',
        group: 'Akses staf',
        stage: '04 / 09',
        icon: 'staff',
        description:
            'Login dasar sesudah Inventory, lalu fixed roles sebelum rilis.',
        steps: [
            'Tahap 04: satu akun owner development login/logout',
            'Lindungi semua route manajemen dengan auth',
            'Tahap 09: kelola akun owner, cashier dan kitchen',
            'Batasi akses di backend dan tolak staf nonaktif',
        ],
        next: inventory,
        nextLabel: 'Kembali ke Inventory',
    },
};
