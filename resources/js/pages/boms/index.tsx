import { Head, Link, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import { Edit2, Eye, Layers, Plus, Trash2, Copy } from 'lucide-react';

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
    type?: string;
}

interface BomRow {
    id: number;
    version: string;
    is_default: boolean;
    status: string;
    effective_from: string | null;
    effective_to: string | null;
    notes: string | null;
    items_count: number;
    product?: ProductOption | null;
}

interface PaginatedBoms {
    data: BomRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    boms: PaginatedBoms;
    products: ProductOption[];
    statuses: string[];
    filters: Record<string, string>;
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }
    return new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}

export default function BomsIndex({ boms, products, statuses, filters }: Props) {
    const { can } = useCan();
    const [deleteConfirm, setDeleteConfirm] = useState<BomRow | null>(null);
    const destroyForm = useForm({});
    const copyForm = useForm({});

    const currentParams: Record<string, string> = {};
    Object.entries(filters ?? {}).forEach(([k, v]) => {
        if (v) {
            currentParams[k] = v;
        }
    });

    const makeFilterChange = (key: string, val: string) => {
        const params = { ...currentParams };
        if (val === 'all' || val === '') {
            delete params[key];
        } else {
            params[key] = val;
        }
        delete params.page;
        router.get('/boms', params, { preserveState: true, replace: true });
    };

    const columns: ColumnDef<BomRow>[] = [
        {
            key: 'product',
            label: 'Product',
            render: (row) => (
                <div>
                    <div className="font-semibold text-foreground">{row.product?.name ?? '—'}</div>
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
                        <span className="rounded bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700">
                            Default
                        </span>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'items_count',
            label: 'Components',
            className: 'text-muted-foreground',
            render: (row) => row.items_count,
        },
        {
            key: 'effective_from',
            label: 'Effective',
            className: 'text-muted-foreground text-sm',
            render: (row) => (
                <span>
                    {formatDate(row.effective_from)}
                    {row.effective_to ? ` → ${formatDate(row.effective_to)}` : ''}
                </span>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (row) => <StatusBadge status={row.status} />,
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
        current_page: boms.current_page,
        last_page: boms.last_page,
        per_page: boms.per_page,
        total: boms.total,
        from: boms.from ?? 1,
        to: boms.to ?? boms.data.length,
    };

    const filterSlot = (
        <>
            <Select value={currentParams.product_id ?? 'all'} onValueChange={(v) => makeFilterChange('product_id', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[160px] border-dashed">
                    <SelectValue placeholder="Product" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Products</SelectItem>
                    {products.map((product) => (
                        <SelectItem key={product.id} value={product.id.toString()}>
                            {product.sku} — {product.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select value={currentParams.status ?? 'all'} onValueChange={(v) => makeFilterChange('status', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[120px] border-dashed">
                    <SelectValue placeholder="Status" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Statuses</SelectItem>
                    {statuses.map((status) => (
                        <SelectItem key={status} value={status}>{status}</SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select value={currentParams.is_default ?? 'all'} onValueChange={(v) => makeFilterChange('is_default', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[120px] border-dashed">
                    <SelectValue placeholder="Default" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All</SelectItem>
                    <SelectItem value="1">Default only</SelectItem>
                    <SelectItem value="0">Non-default</SelectItem>
                </SelectContent>
            </Select>
        </>
    );

    return (
        <>
            <Head title="Bill of Materials" />
            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Bill of Materials</h1>
                    <p className="text-sm text-muted-foreground">
                        Define product structures and component quantities for manufacturing.
                    </p>
                </div>

                <DataTable
                    tableId="boms"
                    columns={columns}
                    data={boms.data}
                    meta={meta}
                    baseUrl="/boms"
                    currentParams={currentParams}
                    searchPlaceholder="Search product SKU, name, version…"
                    entityLabel="BOMs"
                    emptyStateIcon={Layers}
                    emptyStateTitle="No BOMs found"
                    emptyStateDescription="Create a BOM to define what goes into a finished or semi-finished product."
                    primaryAction={
                        can('boms.create') ? (
                            <Button className="gap-2 h-9 font-semibold text-xs" asChild>
                                <Link href="/boms/create">
                                    <Plus className="size-4" />
                                    New BOM
                                </Link>
                            </Button>
                        ) : undefined
                    }
                    filterSlot={filterSlot}
                    rowActions={() => {
                        const actions: RowAction<BomRow>[] = [];
                        if (can('boms.view')) {
                            actions.push({
                                label: 'View',
                                icon: Eye,
                                onClick: (row) => router.visit(`/boms/${row.id}`),
                            });
                        }
                        if (can('boms.create')) {
                            actions.push({
                                label: 'Copy BOM',
                                icon: Copy,
                                onClick: (row) => copyForm.post(`/boms/${row.id}/copy`),
                            });
                        }
                        if (can('boms.update')) {
                            actions.push({
                                label: 'Edit',
                                icon: Edit2,
                                onClick: (row) => router.visit(`/boms/${row.id}/edit`),
                            });
                        }
                        if (can('boms.delete')) {
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
                title="Delete BOM?"
                description={
                    deleteConfirm
                        ? `Delete BOM ${deleteConfirm.product?.sku} v${deleteConfirm.version}? This cannot be undone.`
                        : undefined
                }
                processing={destroyForm.processing}
                onConfirm={() => {
                    if (!deleteConfirm) {
                        return;
                    }
                    destroyForm.delete(`/boms/${deleteConfirm.id}`, {
                        onSuccess: () => setDeleteConfirm(null),
                    });
                }}
            />
        </>
    );
}

BomsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Bill of Materials', href: '/boms' },
    ],
};
