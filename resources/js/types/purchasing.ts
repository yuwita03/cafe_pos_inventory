export type SupplierContact = {
    type: 'phone' | 'email';
    value: string;
};

export type Supplier = {
    id: number;
    name: string;
    contacts?: SupplierContact[];
    is_active: boolean;
};

export type PurchaseStockItem = {
    id: number;
    name: string;
    unit: string;
    active: boolean;
};

export type PurchaseLine = {
    stock_item_id: number;
    name: string;
    unit: string;
    active: boolean;
    quantity: string;
    line_cost_idr: number;
};

export type PurchaseSummary = {
    id: number;
    reference: string;
    supplier: string;
    date: string;
    status: 'draft' | 'received' | 'cancelled';
    total: number;
};

export type Purchase = PurchaseSummary & {
    supplier_id: number;
    supplierActive: boolean;
    creator: string;
    receiver: string | null;
    receivedAt: string | null;
    items: PurchaseLine[];
};

export type PurchaseEditorProps = {
    suppliers: Supplier[];
    stockItems: PurchaseStockItem[];
};
