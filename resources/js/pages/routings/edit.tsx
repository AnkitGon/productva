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

interface RoutingPayload {
    id: number;
    product_id: number;
    version: string;
    is_default: boolean;
    effective_from: string | null;
    effective_to: string | null;
    status: string;
    notes: string | null;
    operations: Array<{
        sequence: number;
        operation_id: number | null;
        work_center_id: number;
        machine_id: number | null;
        setup_time_minutes: string | number;
        run_time_per_unit: string | number;
        labor_time: string | number;
        queue_time: string | number;
        move_time: string | number;
        wait_time: string | number;
        notes: string | null;
    }>;
}

interface Props {
    routing: RoutingPayload;
    products: ProductOption[];
    operations: OperationOption[];
    workCenters: WorkCenterOption[];
    machines: MachineOption[];
    statuses: string[];
}

export default function RoutingsEdit(props: Props) {
    return (
        <>
            <Head title={`Edit Routing ${props.routing.version}`} />
            <div className="flex flex-col gap-6 p-6 w-full max-w-5xl mx-auto">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" className="h-8" onClick={() => router.visit(`/routings/${props.routing.id}`)}>
                        <ArrowLeft className="size-4 mr-2" />
                        Back to Routing
                    </Button>
                </div>
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Edit Routing</h1>
                    <p className="text-sm text-muted-foreground">Update draft routing operations and times.</p>
                </div>
                <RoutingForm
                    routing={props.routing}
                    products={props.products}
                    operations={props.operations}
                    workCenters={props.workCenters}
                    machines={props.machines}
                    statuses={props.statuses}
                    submitUrl={`/routings/${props.routing.id}`}
                    method="put"
                />
            </div>
        </>
    );
}

RoutingsEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Routings', href: '/routings' },
        { title: 'Edit', href: '#' },
    ],
};
