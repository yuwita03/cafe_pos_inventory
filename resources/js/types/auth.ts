export type User = {
    id: number;
    name: string;
    email: string;
    role: 'owner' | 'cashier' | 'kitchen';
    is_active: boolean;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
    capabilities: Record<
        | 'manage-workspace'
        | 'manage-purchasing'
        | 'manage-staff'
        | 'use-pos'
        | 'use-kitchen',
        boolean
    >;
};
