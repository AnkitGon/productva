import { Head, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { BomForm, type ProductOption, type UomOption } from '@/pages/boms/bom-form';

interface BomPayload {
    id: number;
    product_id: number;
    version: string;
    is_default: boolean;
    effective_from: string | null;
    effective_to: string | null;
    status: string;
    notes: string | null;
    items: Array<{
        component_product_id: number;
        quantity: string | number;
        uom_id: number;
        scrap_percentage: string | number;
        sequence: number;
        notes: string | null;
    }>;
}

interface Props {
    bom: BomPayload;
    parentProducts: ProductOption[];
    componentProducts: ProductOption[];
    uoms: UomOption[];
    statuses: string[];
}

export default function BomsEdit({
    bom,
    parentProducts,
    componentProducts,
    uoms,
    statuses,
}: Props) {
    return (
        <>
            <Head title={`Edit BOM ${bom.version}`} />
            <div className="flex flex-col gap-6 p-6 w-full max-w-5xl mx-auto">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" className="h-8" onClick={() => router.visit(`/boms/${bom.id}`)}>
                        <ArrowLeft className="size-4 mr-2" />
                        Back to BOM
                    </Button>
                </div>

                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Edit BOM</h1>
                    <p className="text-sm text-muted-foreground">
                        Update version details and component lines.
                    </p>
                </div>

                <BomForm
                    bom={bom}
                    parentProducts={parentProducts}
                    componentProducts={componentProducts}
                    uoms={uoms}
                    statuses={statuses}
                    submitUrl={`/boms/${bom.id}`}
                    method="put"
                />
            </div>
        </>
    );
}

BomsEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Bill of Materials', href: '/boms' },
        { title: 'Edit', href: '#' },
    ],
};
