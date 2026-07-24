import { Head, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    RoutingForm,
    type MachineOption,
    type OperationOption,
    type ProductOption,
    type WorkCenterOption,
} from '@/pages/routings/routing-form';

interface Props {
    products: ProductOption[];
    operations: OperationOption[];
    workCenters: WorkCenterOption[];
    machines: MachineOption[];
    statuses: string[];
    preselectedProductId?: number | null;
    plant: { id: number; name: string; code: string } | null;
}

export default function RoutingsCreate(props: Props) {
    return (
        <>
            <Head title="Create Routing" />
            <div className="flex flex-col gap-6 p-6 w-full max-w-5xl mx-auto">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" className="h-8" onClick={() => router.visit('/routings')}>
                        <ArrowLeft className="size-4 mr-2" />
                        Back to Routings
                    </Button>
                </div>
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Create Routing</h1>
                    <p className="text-sm text-muted-foreground">
                        Define the operation sequence for manufacturing
                        {props.plant ? ` in ${props.plant.name}` : ''}.
                    </p>
                </div>
                <RoutingForm
                    products={props.products}
                    operations={props.operations}
                    workCenters={props.workCenters}
                    machines={props.machines}
                    statuses={props.statuses}
                    preselectedProductId={props.preselectedProductId}
                    submitUrl="/routings"
                />
            </div>
        </>
    );
}

RoutingsCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Routings', href: '/routings' },
        { title: 'Create', href: '/routings/create' },
    ],
};
