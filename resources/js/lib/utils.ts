import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function formatRupiah(value: number): string {
    return 'Rp' + new Intl.NumberFormat('id-ID').format(value);
}

export function formatQuantity(value: string | number): string {
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(
        Number(value),
    );
}
