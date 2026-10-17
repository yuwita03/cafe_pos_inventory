import type { Product, Variant } from '@/types/menu';

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
