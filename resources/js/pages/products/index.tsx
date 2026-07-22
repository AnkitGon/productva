import { Head, Link, router, useForm } from '@inertiajs/react';
import React, { useRef, useState } from 'react';
import { Download, Edit2, Eye, Package, Plus, Trash2, Upload } from 'lucide-react';

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

interface CategoryOption {
    id: number;
    code: string;
    name: string;
}

interface ProductRow {
    id: number;
    sku: string;
    name: string;
    type: string;
    status: string;
    image_url?: string | null;
    image_path: string | null;
    category?: CategoryOption | null;
    uom?: { id: number; code: string; name: string; symbol: string } | null;
}

interface PaginatedProducts {
    data: ProductRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    products: PaginatedProducts;
    categories: CategoryOption[];
    types: string[];
    statuses: string[];
    filters: Record<string, string>;
}

export default function ProductsIndex({ products, categories, types, statuses, filters }: Props) {
    const { can } = useCan();
    const [deleteConfirm, setDeleteConfirm] = useState<ProductRow | null>(null);
    const importInputRef = useRef<HTMLInputElement>(null);
    const destroyForm = useForm({});
    const importForm = useForm<{ file: File | null }>({ file: null });

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
        router.get('/products', params, { preserveState: true, replace: true });
    };

    const columns: ColumnDef<ProductRow>[] = [
        {
            key: 'image',
            label: 'Image',
            render: (row) =>
                row.image_url || row.image_path ? (
                    <img
                        src={row.image_url || `/storage/${row.image_path}`}
                        alt={row.name}
                        className="size-9 rounded object-cover border border-border/50"
                    />
                ) : (
                    <div className="size-9 rounded bg-muted flex items-center justify-center">
                        <Package className="size-4 text-muted-foreground" />
                    </div>
                ),
        },
        {
            key: 'sku',
            label: 'SKU',
            sortable: true,
            render: (row) => <span className="font-mono text-xs font-semibold">{row.sku}</span>,
        },
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (row) =>
                <span
                    className="font-semibold text-foreground hover:underline cursor-pointer"
                    onClick={() => router.visit(`/products/${row.id}`)}
                >
                    {row.name}
                </span>,
        },
        {
            key: 'category',
            label: 'Category',
            className: 'text-muted-foreground',
            render: (row) => row.category?.name ?? '—',
        },
        {
            key: 'type',
            label: 'Type',
            sortable: true,
            className: 'text-muted-foreground',
        },
        {
            key: 'uom',
            label: 'UOM',
            className: 'text-muted-foreground',
            render: (row) => row.uom?.code ?? '—',
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (row) => <StatusBadge status={row.status} />,
        },
        {
            key: 'stock',
            label: 'Stock',
            className: 'text-muted-foreground',
            render: () => '—',
        },
    ];

    const meta: TableMeta = {
        current_page: products.current_page,
        last_page: products.last_page,
        per_page: products.per_page,
        total: products.total,
        from: products.from ?? 1,
        to: products.to ?? products.data.length,
    };

    const filterSlot = (
        <>
            <Select value={currentParams.category_id ?? 'all'} onValueChange={(v) => makeFilterChange('category_id', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[140px] border-dashed">
                    <SelectValue placeholder="Category" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Categories</SelectItem>
                    {categories.map((category) => (
                        <SelectItem key={category.id} value={category.id.toString()}>
                            {category.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select value={currentParams.type ?? 'all'} onValueChange={(v) => makeFilterChange('type', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[130px] border-dashed">
                    <SelectValue placeholder="Type" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Types</SelectItem>
                    {types.map((type) => (
                        <SelectItem key={type} value={type}>
                            {type}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select value={currentParams.status ?? 'all'} onValueChange={(v) => makeFilterChange('status', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[110px] border-dashed">
                    <SelectValue placeholder="Status" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Statuses</SelectItem>
                    {statuses.map((status) => (
                        <SelectItem key={status} value={status}>
                            {status}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select value={currentParams.track_inventory ?? 'all'} onValueChange={(v) => makeFilterChange('track_inventory', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[130px] border-dashed">
                    <SelectValue placeholder="Inventory" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Track Inventory</SelectItem>
                    <SelectItem value="1">Tracked</SelectItem>
                    <SelectItem value="0">Not tracked</SelectItem>
                </SelectContent>
            </Select>

            <Select value={currentParams.lot_tracking ?? 'all'} onValueChange={(v) => makeFilterChange('lot_tracking', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[110px] border-dashed">
                    <SelectValue placeholder="Lot" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Lot Tracking</SelectItem>
                    <SelectItem value="1">Lot on</SelectItem>
                    <SelectItem value="0">Lot off</SelectItem>
                </SelectContent>
            </Select>

            <Select value={currentParams.serial_tracking ?? 'all'} onValueChange={(v) => makeFilterChange('serial_tracking', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[120px] border-dashed">
                    <SelectValue placeholder="Serial" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Serial Tracking</SelectItem>
                    <SelectItem value="1">Serial on</SelectItem>
                    <SelectItem value="0">Serial off</SelectItem>
                </SelectContent>
            </Select>
        </>
    );

    return (
        <>
            <Head title="Products" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Products</h1>
                        <p className="text-sm text-muted-foreground">
                            Organization-wide product master data for inventory, purchasing, and production.
                        </p>
                    </div>
                </div>

                <DataTable
                    tableId="products"
                    columns={columns}
                    data={products.data}
                    meta={meta}
                    baseUrl="/products"
                    currentParams={currentParams}
                    searchPlaceholder="Search SKU, barcode, name, supplier SKU…"
                    entityLabel="products"
                    emptyStateIcon={Package}
                    emptyStateTitle="No products found"
                    emptyStateDescription={
                        can('products.create')
                            ? 'Create your first product for this organization.'
                            : 'No products are available for this organization.'
                    }
                    emptyStateAction={
                        can('products.create') ? (
                            <Button size="sm" asChild className="gap-2">
                                <Link href="/products/create">
                                    <Plus className="size-3.5" />
                                    Create Product
                                </Link>
                            </Button>
                        ) : undefined
                    }
                    primaryAction={
                        <div className="flex items-center gap-2 flex-wrap">
                            {can('products.import') && (
                                <>
                                    <input
                                        ref={importInputRef}
                                        type="file"
                                        accept=".csv,text/csv"
                                        className="hidden"
                                        onChange={(e) => {
                                            const file = e.target.files?.[0] ?? null;
                                            if (!file) {
                                                return;
                                            }
                                            importForm.setData('file', file);
                                            importForm.post('/products/import', {
                                                forceFormData: true,
                                                onFinish: () => {
                                                    importForm.reset();
                                                    if (importInputRef.current) {
                                                        importInputRef.current.value = '';
                                                    }
                                                },
                                            });
                                        }}
                                    />
                                    <Button
                                        variant="outline"
                                        className="gap-2 h-9 font-semibold text-xs"
                                        onClick={() => importInputRef.current?.click()}
                                        disabled={importForm.processing}
                                    >
                                        <Upload className="size-4" />
                                        Import
                                    </Button>
                                </>
                            )}
                            {can('products.export') && (
                                <Button variant="outline" className="gap-2 h-9 font-semibold text-xs" asChild>
                                    <a href="/products/export">
                                        <Download className="size-4" />
                                        Export
                                    </a>
                                </Button>
                            )}
                            {can('products.create') && (
                                <Button asChild className="gap-2 h-9 font-semibold text-xs">
                                    <Link href="/products/create">
                                        <Plus className="size-4" />
                                        Add Product
                                    </Link>
                                </Button>
                            )}
                        </div>
                    }
                    filterSlot={filterSlot}
                    rowActions={(row) => {
                        const actions: RowAction<ProductRow>[] = [
                            {
                                label: 'View',
                                icon: Eye,
                                onClick: (product) => router.visit(`/products/${product.id}`),
                            },
                        ];

                        if (can('products.update')) {
                            actions.push({
                                label: 'Edit',
                                icon: Edit2,
                                onClick: (product) => router.visit(`/products/${product.id}/edit`),
                            });
                        }

                        if (can('products.delete')) {
                            actions.push({
                                label: 'Archive',
                                icon: Trash2,
                                onClick: (product) => setDeleteConfirm(product),
                                variant: 'destructive',
                                separator: true,
                            });
                        }

                        return actions;
                    }}
                />
            </div>

            <ConfirmDeleteDialog
                open={deleteConfirm !== null}
                onOpenChange={(open) => !open && setDeleteConfirm(null)}
                title="Archive Product?"
                description={
                    <>
                        Archive <span className="font-semibold text-foreground">{deleteConfirm?.name}</span>? Products with inventory or history are marked inactive instead.
                    </>
                }
                confirmLabel="Archive Product"
                onConfirm={() => {
                    if (!deleteConfirm) {
                        return;
                    }
                    destroyForm.delete(`/products/${deleteConfirm.id}`, {
                        onSuccess: () => setDeleteConfirm(null),
                    });
                }}
                processing={destroyForm.processing}
            />
        </>
    );
}

ProductsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Products', href: '/products' },
    ],
};
