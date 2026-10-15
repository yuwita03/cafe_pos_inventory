export interface Category {
    productCount?: number;
    id: number;
    name: string;
    active: boolean;
}
export interface Variant {
    id?: number;
    name: string;
    price: number;
    available: boolean;
}
export interface Option {
    id?: number;
    name: string;
    price: number;
    active: boolean;
}
export interface OptionGroup {
    productCount?: number;
    id: number;
    name: string;
    selection: 'single' | 'multiple';
    active: boolean;
    options: Option[];
}
export interface Product {
    id: number;
    name: string;
    description: string;
    categoryId: number;
    active: boolean;
    image: string;
    variants: Variant[];
    groups: { id: number; required: boolean }[];
}
export interface Catalog {
    categories: Category[];
    groups: OptionGroup[];
    products: Product[];
}

export interface MenuFilters {
    q: string;
    category_id: number;
    status: string;
    per_page: number;
}
export interface TablePagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
}
