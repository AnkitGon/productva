import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            const options = {
                success: { duration: 3000 },
                info: { duration: 4000 },
                warning: { duration: 5000 },
                error: { duration: 8000 },
            };

            const type = data.type as keyof typeof options;
            const toastFn = toast[type] || toast;
            toastFn(data.message, options[type] || { duration: 4000 });
        });
    }, []);
}
