export type Plant = {
    id: number;
    name: string;
    code: string;
    slug: string;
    status: string;
    is_default: boolean;
};

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    roles?: string[];
    organization_id?: number | null;
    active_plant_id?: number | null;
    plants?: Plant[];
    active_plant?: Partial<Plant> | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};


export type Auth = {
    user: User;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
