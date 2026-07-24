import { Head, Link, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import { Copy, Edit2, Eye, Plus, Route, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { DataTable, type ColumnDef, type TableMeta } from '@/components/data-table/data-table';
import { StatusBadge } from '@/components/data-table/status-badge';
import type { RowAction } from '@/components/data-table/data-table-row-actions';
import { useCan } from '@/hooks/use-can';

interface ProductOption {
    id: number;
    sku: string;
    name: string;
}

interface RoutingRow {
    id: number;
    version: string;
    status: string;
    is_default: boolean;
    is_editable: boolean;
    effective_from: string | null;
    effective_to: string | null;
    notes: string | null;
    operations_count: number;
    product?: ProductOption | null;
}

interface PaginatedRoutings {
    data: RoutingRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    routings: PaginatedRoutings;
    products: ProductOption[];
    statuses: string[];
    plant: { id: number; name: string; code: string } | null;
    filters: Record<string, string>;
}

function formatDate(value: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}

export default function RoutingsIndex({ routings, products, statuses, plant, filters }: Props) {
    const { can } = useCan();
    const [deleteConfirm, setDeleteConfirm] = useState<RoutingRow | null>(null);
    const destroyForm = useForm({});
    const copyForm = useForm({});

    const currentParams: Record<string, string> = {};
    Object.entries(filters ?? {}).forEach(([k, v]) => {
        if (v) currentParams[k] = v;
    });

    const makeFilterChange = (key: string, val: string) => {
        const params = { ...currentParams };
        if (val === 'all' || val === '') delete params[key];
        else params[key] = val;
        delete params.page;
        router.get('/routings', params, { preserveState: true, replace: true });
    };

    const columns: ColumnDef<RoutingRow>[] = [
        {
            key: 'product',
            label: 'Product',
            render: (row) => (
                <div>
                    <div className="font-semibold">{row.product?.name ?? '—'}</div>
                    <div className="font-mono text-xs text-muted-foreground">{row.product?.sku}</div>
                </div>
            ),
        },
        {
            key: 'version',
            label: 'Version',
            sortable: true,
            render: (row) => (
                <div className="flex items-center gap-2">
                    <span className="font-mono text-sm font-semibold">{row.version}</span>
                    {row.is_default ? (
                        <span className="rounded bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-emerald-700">Default</span>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (row) => <StatusBadge status={row.status} />,
        },
        {
            key: 'operations_count',
            label: 'Operations',
            className: 'text-muted-foreground',
            render: (row) => row.operations_count,
        },
        {
            key: 'effective_from',
            label: 'Effective From',
            className: 'text-muted-foreground text-sm',
            render: (row) => formatDate(row.effective_from),
        },
        {
            key: 'is_default',
            label: 'Default',
            defaultVisible: false,
            className: 'text-muted-foreground',
            render: (row) => (row.is_default ? 'Yes' : 'No'),
        },
        {
            key: 'effective_to',
            label: 'Effective To',
            defaultVisible: false,
            className: 'text-muted-foreground text-sm',
            render: (row) => formatDate(row.effective_to),
        },
        {
            key: 'notes',
            label: 'Notes',
            defaultVisible: false,
            className: 'text-muted-foreground',
            render: (row) => row.notes ?? '—',
        },
    ];

    const meta: TableMeta = {
        current_page: routings.current_page,
        last_page: routings.last_page,
        per_page: routings.per_page,
        total: routings.total,
        from: routings.from ?? 1,
        to: routings.to ?? routings.data.length,
    };

    return (
        <>
            <Head title="Routings" />
            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Routings</h1>
                    <p className="text-sm text-muted-foreground">
                        How products are manufactured in the active plant{plant ? ` (${plant.name})` : ''}.
                    </p>
                </div>

                <DataTable
                    tableId="routings"
                    columns={columns}
                    data={routings.data}
                    meta={meta}
                    baseUrl="/routings"
                    currentParams={currentParams}
                    searchPlaceholder="Search product SKU, name, version…"
                    entityLabel="routings"
                    emptyStateIcon={Route}
                    emptyStateTitle="No routings found"
                    emptyStateDescription="Define the operation sequence for finished and semi-finished products."
                    primaryAction={
                        can('routing.create') ? (
                            <Button className="gap-2 h-9 font-semibold text-xs" asChild>
                                <Link href="/routings/create"><Plus className="size-4" />New Routing</Link>
                            </Button>
                        ) : undefined
                    }
                    filterSlot={
                        <>
                            <Select value={currentParams.product_id ?? 'all'} onValueChange={(v) => makeFilterChange('product_id', v)}>
                                <SelectTrigger className="h-8 text-xs min-w-[150px] border-dashed"><SelectValue placeholder="Product" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Products</SelectItem>
                                    {products.map((p) => (
                                        <SelectItem key={p.id} value={p.id.toString()}>{p.sku} — {p.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select value={currentParams.status ?? 'all'} onValueChange={(v) => makeFilterChange('status', v)}>
                                <SelectTrigger className="h-8 text-xs min-w-[120px] border-dashed"><SelectValue placeholder="Status" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Statuses</SelectItem>
                                    {statuses.map((s) => <SelectItem key={s} value={s}>{s}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </>
                    }
                    rowActions={() => {
                        const actions: RowAction<RoutingRow>[] = [];
                        if (can('routing.view')) {
                            actions.push({ label: 'View', icon: Eye, onClick: (row) => router.visit(`/routings/${row.id}`) });
                        }
                        if (can('routing.create')) {
                            actions.push({ label: 'Copy', icon: Copy, onClick: (row) => copyForm.post(`/routings/${row.id}/copy`) });
                        }
                        if (can('routing.update')) {
                            actions.push({
                                label: 'Edit',
                                icon: Edit2,
                                onClick: (row) => {
                                    if (!row.is_editable) {
                                        return;
                                    }
                                    router.visit(`/routings/${row.id}/edit`);
                                },
                            });
                        }
                        if (can('routing.delete')) {
                            actions.push({
                                label: 'Delete',
                                icon: Trash2,
                                onClick: (row) => setDeleteConfirm(row),
                                variant: 'destructive',
                            });
                        }
                        return actions;
                    }}
                />
            </div>

            <ConfirmDeleteDialog
                open={!!deleteConfirm}
                onOpenChange={(open) => { if (!open) setDeleteConfirm(null); }}
                title="Delete routing?"
                description={deleteConfirm ? `Delete ${deleteConfirm.product?.sku} v${deleteConfirm.version}?` : ''}
                processing={destroyForm.processing}
                onConfirm={() => {
                    if (!deleteConfirm) return;
                    destroyForm.delete(`/routings/${deleteConfirm.id}`, { onSuccess: () => setDeleteConfirm(null) });
                }}
            />
        </>
    );
}

RoutingsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Routings', href: '/routings' },
    ],
};
