import { Head, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { BomForm, type ProductOption, type UomOption } from '@/pages/boms/bom-form';

interface Props {
    parentProducts: ProductOption[];
    componentProducts: ProductOption[];
    uoms: UomOption[];
    statuses: string[];
    preselectedProductId?: number | null;
}

export default function BomsCreate({
    parentProducts,
    componentProducts,
    uoms,
    statuses,
    preselectedProductId,
}: Props) {
    return (
        <>
            <Head title="Create BOM" />
            <div className="flex flex-col gap-6 p-6 w-full max-w-5xl mx-auto">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" className="h-8" onClick={() => router.visit('/boms')}>
                        <ArrowLeft className="size-4 mr-2" />
                        Back to BOMs
                    </Button>
                </div>

                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Create BOM</h1>
                    <p className="text-sm text-muted-foreground">
                        Define the component structure for a finished or semi-finished product.
                    </p>
                </div>

                <BomForm
                    parentProducts={parentProducts}
                    componentProducts={componentProducts}
                    uoms={uoms}
                    statuses={statuses}
                    preselectedProductId={preselectedProductId}
                    submitUrl="/boms"
                />
            </div>
        </>
    );
}

BomsCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Bill of Materials', href: '/boms' },
        { title: 'Create', href: '/boms/create' },
    ],
};
