import { usePage } from '@inertiajs/react';
import type { SharedData } from '@/types';

export function useCan() {
    const { auth } = usePage<SharedData>().props;
    const isSuperAdmin = auth.user?.roles?.includes('super-admin') ?? false;
    const permissions = auth.user?.permissions ?? [];

    const can = (permission: string): boolean =>
        isSuperAdmin || permissions.includes(permission);

    const canAny = (...required: string[]): boolean => required.some(can);

    return { can, canAny, isSuperAdmin };
}
