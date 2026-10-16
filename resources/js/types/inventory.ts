export type StockUnit = 'g' | 'ml' | 'pcs';
export interface StockItem {
    id: number;
    name: string;
    unit: StockUnit;
    onHand: number;
    reserved: number;
    threshold: number;
    active: boolean;
    quickAdd: boolean;
    available: number;
    lowStock: boolean;
}
export interface Ingredient {
    stockId: number;
    quantity: number;
}
export interface VariantRecipe {
    kind: 'ingredients' | 'finished';
    stockId: number;
    ingredients: Ingredient[];
}
export interface RecipeCatalog {
    products: {
        id: number;
        name: string;
        active: boolean;
        variants: { id: number; name: string; available: boolean }[];
        groups: { id: number; required: boolean }[];
    }[];
    groups: {
        id: number;
        name: string;
        active: boolean;
        selection: 'single' | 'multiple';
        options: { id: number; name: string; active: boolean }[];
    }[];
}
export interface Movement {
    id: number;
    stockId: number;
    time: string;
    delta: number;
    reason: string;
    source: string;
    actor: string;
}
