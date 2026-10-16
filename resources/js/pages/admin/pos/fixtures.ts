import type { Category, Product, Variant } from '@/types/menu';

export interface PosProduct extends Omit<Product, 'variants'> {
    variants: (Variant & { id: number })[];
}

export interface PosGroup {
    id: number;
    name: string;
    selection: 'single' | 'multiple';
    options: { id: number; name: string; price: number }[];
}

export interface CartLine {
    key: string;
    productId: number;
    variantId: number;
    optionIds: number[];
    productName: string;
    variantName: string;
    optionNames: string[];
    unitPrice: number;
    quantity: number;
}

// Isolated prototype data. No real availability, stock, prices, or payments.
export const categories: Category[] = [
    { id: 1, name: 'Coffee', active: true },
    { id: 2, name: 'Pastry', active: true },
    { id: 3, name: 'Non-coffee', active: true },
];

export const groups: PosGroup[] = [
    {
        id: 1,
        name: 'Sugar Level',
        selection: 'single',
        options: [
            { id: 1, name: 'Zero', price: 0 },
            { id: 2, name: 'Low', price: 0 },
            { id: 3, name: 'Medium', price: 0 },
            { id: 4, name: 'High', price: 0 },
        ],
    },
    {
        id: 2,
        name: 'Tambahan',
        selection: 'multiple',
        options: [
            { id: 5, name: 'Extra shot', price: 5000 },
            { id: 6, name: 'Caramel syrup', price: 3000 },
        ],
    },
];

export const products: PosProduct[] = [
    {
        id: 1,
        name: 'Americano',
        description:
            'Espresso dan air, ringan dengan karakter kopi yang bersih.',
        categoryId: 1,
        active: true,
        image: '',
        variants: [
            { id: 11, name: 'Hot', price: 22000, available: true },
            { id: 12, name: 'Iced', price: 25000, available: true },
        ],
        groups: [
            { id: 1, required: true },
            { id: 2, required: false },
        ],
    },
    {
        id: 2,
        name: 'Butter Croissant',
        description:
            'Pastry berlapis dengan rasa butter dan bagian luar renyah.',
        categoryId: 2,
        active: true,
        image: '',
        variants: [{ id: 21, name: 'Regular', price: 23000, available: true }],
        groups: [],
    },
    {
        id: 3,
        name: 'Cappuccino',
        description: 'Espresso, susu, dan foam lembut dalam porsi seimbang.',
        categoryId: 1,
        active: true,
        image: '',
        variants: [
            { id: 31, name: 'Hot', price: 28000, available: true },
            { id: 32, name: 'Iced', price: 30000, available: true },
        ],
        groups: [
            { id: 1, required: true },
            { id: 2, required: false },
        ],
    },
    {
        id: 4,
        name: 'Chocolate Croissant',
        description: 'Croissant butter dengan isian cokelat.',
        categoryId: 2,
        active: true,
        image: '',
        variants: [{ id: 41, name: 'Regular', price: 28000, available: false }],
        groups: [],
    },
    {
        id: 5,
        name: 'Cookies',
        description: 'Cookies cokelat untuk teman minum kopi.',
        categoryId: 2,
        active: true,
        image: '',
        variants: [{ id: 51, name: 'Regular', price: 18000, available: true }],
        groups: [],
    },
    {
        id: 6,
        name: 'Latte',
        description: 'Espresso dengan susu yang lembut dan creamy.',
        categoryId: 1,
        active: true,
        image: '',
        variants: [
            { id: 61, name: 'Hot', price: 28000, available: false },
            { id: 62, name: 'Iced', price: 30000, available: true },
        ],
        groups: [
            { id: 1, required: true },
            { id: 2, required: false },
        ],
    },
    {
        id: 7,
        name: 'Mocha',
        description: 'Espresso, cokelat, dan susu untuk rasa yang lebih kaya.',
        categoryId: 1,
        active: true,
        image: '',
        variants: [
            { id: 71, name: 'Hot', price: 32000, available: true },
            { id: 72, name: 'Iced', price: 35000, available: true },
        ],
        groups: [{ id: 1, required: true }],
    },
    {
        id: 8,
        name: 'Tea',
        description: 'Teh dengan aroma ringan, tersedia hangat atau dingin.',
        categoryId: 3,
        active: true,
        image: '',
        variants: [
            { id: 81, name: 'Hot', price: 15000, available: true },
            { id: 82, name: 'Iced', price: 18000, available: true },
        ],
        groups: [{ id: 1, required: true }],
    },
];

export const tables = Array.from({ length: 8 }, (_, index) => ({
    id: index + 1,
    label: `A${String(index + 1).padStart(2, '0')}`,
}));
