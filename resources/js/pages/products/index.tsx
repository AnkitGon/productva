import { Head, Link, router, useForm } from '@inertiajs/react';
import React, { useRef, useState } from 'react';
import {
    Download,
    Edit2,
    Eye,
    Package,
    Plus,
    Tags,
    Trash2,
    Upload,
    UserCheck,
    UserX,
    Warehouse,
} from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { ModalButtons } from '@/components/modal-buttons';
import { DataTable, type ColumnDef, type TableMeta } from '@/components/data-table/data-table';
import { StatusBadge } from '@/components/data-table/status-badge';
import type { RowAction } from '@/components/data-table/data-table-row-actions';
import { useCan } from '@/hooks/use-can';

interface CategoryOption {
    id: number;
    code: string;
    name: string;
    status?: string;
}

interface WarehouseOption {
    id: number;
    code: string;
    name: string;
    status?: string;
}

interface ProductRow {
    id: number;
    sku: string;
    name: string;
    type: string;
    status: string;
    barcode: string | null;
    track_inventory: boolean;
    lot_tracking: boolean;
    serial_tracking: boolean;
    brand: string | null;
    manufacturer: string | null;
    supplier_sku: string | null;
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
    warehouses: WarehouseOption[];
    manufacturers: string[];
    brands: string[];
    uoms?: Array<{ id: number; code: string; name: string }>;
    types: string[];
    statuses: string[];
    filters: Record<string, string>;
}

export default function ProductsIndex({
    products,
    categories,
    warehouses,
    manufacturers,
    brands,
    uoms = [],
    types,
    statuses,
    filters,
}: Props) {
    const { can } = useCan();
    const [deleteConfirm, setDeleteConfirm] = useState<ProductRow | null>(null);
    const importInputRef = useRef<HTMLInputElement>(null);
    const destroyForm = useForm({});
    const importForm = useForm<{ file: File | null }>({ file: null });

    const [bulkIds, setBulkIds] = useState<number[]>([]);
    const [bulkClearSelection, setBulkClearSelection] = useState<(() => void) | null>(null);
    const [bulkAssignCategoryOpen, setBulkAssignCategoryOpen] = useState(false);
    const [bulkAssignWarehouseOpen, setBulkAssignWarehouseOpen] = useState(false);
    const [bulkDeleteOpen, setBulkDeleteOpen] = useState(false);
    const [bulkCategoryId, setBulkCategoryId] = useState('');
    const [bulkWarehouseId, setBulkWarehouseId] = useState('');
    const [bulkProcessing, setBulkProcessing] = useState(false);

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

    const submitBulk = (
        payload: Record<string, unknown>,
        clearSelection?: () => void,
        options?: { onFinish?: () => void },
    ) => {
        setBulkProcessing(true);
        router.post('/products/bulk', payload, {
            preserveScroll: true,
            onSuccess: () => {
                clearSelection?.();
                setBulkAssignCategoryOpen(false);
                setBulkAssignWarehouseOpen(false);
                setBulkDeleteOpen(false);
                setBulkCategoryId('');
                setBulkWarehouseId('');
                setBulkIds([]);
            },
            onFinish: () => {
                setBulkProcessing(false);
                options?.onFinish?.();
            },
        });
    };

    const exportBulk = async (ids: number[], clearSelection?: () => void) => {
        setBulkProcessing(true);
        try {
            const xsrf = document.cookie
                .split('; ')
                .find((row) => row.startsWith('XSRF-TOKEN='))
                ?.split('=')[1];

            const response = await fetch('/products/bulk', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/csv',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf) } : {}),
                },
                body: JSON.stringify({ action: 'export', ids }),
            });

            if (!response.ok) {
                toast.error('Unable to export products.');
                return;
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = `products-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.csv`;
            anchor.click();
            URL.revokeObjectURL(url);
            clearSelection?.();
            toast.success('Products exported.');
        } catch {
            toast.error('Unable to export products.');
        } finally {
            setBulkProcessing(false);
        }
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
        {
            key: 'barcode',
            label: 'Barcode',
            defaultVisible: false,
            className: 'text-muted-foreground font-mono text-xs',
            render: (row) => row.barcode ?? '—',
        },
        {
            key: 'track_inventory',
            label: 'Track Inventory',
            defaultVisible: false,
            className: 'text-muted-foreground',
            render: (row) => (row.track_inventory ? 'Yes' : 'No'),
        },
        {
            key: 'lot_tracking',
            label: 'Lot Tracking',
            defaultVisible: false,
            className: 'text-muted-foreground',
            render: (row) => (row.lot_tracking ? 'Yes' : 'No'),
        },
        {
            key: 'serial_tracking',
            label: 'Serial Tracking',
            defaultVisible: false,
            className: 'text-muted-foreground',
            render: (row) => (row.serial_tracking ? 'Yes' : 'No'),
        },
        {
            key: 'brand',
            label: 'Brand',
            defaultVisible: false,
            className: 'text-muted-foreground',
            render: (row) => row.brand ?? '—',
        },
        {
            key: 'manufacturer',
            label: 'Manufacturer',
            defaultVisible: false,
            className: 'text-muted-foreground',
            render: (row) => row.manufacturer ?? '—',
        },
        {
            key: 'supplier_sku',
            label: 'Supplier SKU',
            defaultVisible: false,
            className: 'text-muted-foreground font-mono text-xs',
            render: (row) => row.supplier_sku ?? '—',
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

    const activeCategories = categories.filter((c) => !c.status || c.status === 'Active');
    const activeWarehouses = warehouses.filter((w) => !w.status || w.status === 'Active');

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

            <Select value={currentParams.uom_id ?? 'all'} onValueChange={(v) => makeFilterChange('uom_id', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[120px] border-dashed">
                    <SelectValue placeholder="UOM" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All UOMs</SelectItem>
                    {uoms.map((uom) => (
                        <SelectItem key={uom.id} value={uom.id.toString()}>
                            {uom.code} — {uom.name}
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

            <Select
                value={currentParams.default_warehouse_id ?? 'all'}
                onValueChange={(v) => makeFilterChange('default_warehouse_id', v)}
            >
                <SelectTrigger className="h-8 text-xs min-w-[140px] border-dashed">
                    <SelectValue placeholder="Warehouse" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Warehouses</SelectItem>
                    {warehouses.map((warehouse) => (
                        <SelectItem key={warehouse.id} value={warehouse.id.toString()}>
                            {warehouse.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select value={currentParams.manufacturer ?? 'all'} onValueChange={(v) => makeFilterChange('manufacturer', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[140px] border-dashed">
                    <SelectValue placeholder="Manufacturer" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Manufacturers</SelectItem>
                    {manufacturers.map((manufacturer) => (
                        <SelectItem key={manufacturer} value={manufacturer}>
                            {manufacturer}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select value={currentParams.brand ?? 'all'} onValueChange={(v) => makeFilterChange('brand', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[120px] border-dashed">
                    <SelectValue placeholder="Brand" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Brands</SelectItem>
                    {brands.map((brand) => (
                        <SelectItem key={brand} value={brand}>
                            {brand}
                        </SelectItem>
                    ))}
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
                    selectable={
                        can('products.update')
                        || can('products.export')
                        || can('products.delete')
                    }
                    bulkActions={({ selectedIds, clearSelection }) => {
                        const ids = selectedIds.map(Number);

                        return (
                            <>
                                {can('products.update') && (
                                    <>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-7 gap-1.5 text-xs"
                                            disabled={bulkProcessing}
                                            onClick={() => submitBulk({ action: 'activate', ids }, clearSelection)}
                                        >
                                            <UserCheck className="size-3.5" />
                                            Activate
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-7 gap-1.5 text-xs"
                                            disabled={bulkProcessing}
                                            onClick={() => submitBulk({ action: 'deactivate', ids }, clearSelection)}
                                        >
                                            <UserX className="size-3.5" />
                                            Deactivate
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-7 gap-1.5 text-xs"
                                            disabled={bulkProcessing}
                                            onClick={() => {
                                                setBulkIds(ids);
                                                setBulkClearSelection(() => clearSelection);
                                                setBulkAssignCategoryOpen(true);
                                            }}
                                        >
                                            <Tags className="size-3.5" />
                                            Assign Category
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-7 gap-1.5 text-xs"
                                            disabled={bulkProcessing}
                                            onClick={() => {
                                                setBulkIds(ids);
                                                setBulkClearSelection(() => clearSelection);
                                                setBulkAssignWarehouseOpen(true);
                                            }}
                                        >
                                            <Warehouse className="size-3.5" />
                                            Assign Warehouse
                                        </Button>
                                    </>
                                )}
                                {can('products.export') && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-7 gap-1.5 text-xs"
                                        disabled={bulkProcessing}
                                        onClick={() => exportBulk(ids, clearSelection)}
                                    >
                                        <Download className="size-3.5" />
                                        Export Selected
                                    </Button>
                                )}
                                {can('products.delete') && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-7 gap-1.5 text-xs text-destructive hover:text-destructive"
                                        disabled={bulkProcessing}
                                        onClick={() => {
                                            setBulkIds(ids);
                                            setBulkClearSelection(() => clearSelection);
                                            setBulkDeleteOpen(true);
                                        }}
                                    >
                                        <Trash2 className="size-3.5" />
                                        Delete
                                    </Button>
                                )}
                            </>
                        );
                    }}
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

            <Dialog open={bulkAssignCategoryOpen} onOpenChange={setBulkAssignCategoryOpen}>
                <DialogContent className="sm:max-w-md">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            if (!bulkCategoryId) {
                                return;
                            }
                            submitBulk(
                                { action: 'assign_category', ids: bulkIds, category_id: Number(bulkCategoryId) },
                                bulkClearSelection ?? undefined,
                            );
                        }}
                    >
                        <DialogHeader>
                            <DialogTitle>Assign Category</DialogTitle>
                            <DialogDescription>
                                Apply a category to {bulkIds.length} selected product{bulkIds.length === 1 ? '' : 's'}.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-2 py-4">
                            <Label>Category</Label>
                            <Select value={bulkCategoryId || undefined} onValueChange={setBulkCategoryId}>
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Select category" />
                                </SelectTrigger>
                                <SelectContent>
                                    {activeCategories.map((category) => (
                                        <SelectItem key={category.id} value={category.id.toString()}>
                                            {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <ModalButtons
                            onCancel={() => setBulkAssignCategoryOpen(false)}
                            saveLabel="Assign"
                            processing={bulkProcessing}
                        />
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={bulkAssignWarehouseOpen} onOpenChange={setBulkAssignWarehouseOpen}>
                <DialogContent className="sm:max-w-md">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            if (!bulkWarehouseId) {
                                return;
                            }
                            submitBulk(
                                {
                                    action: 'assign_warehouse',
                                    ids: bulkIds,
                                    default_warehouse_id: Number(bulkWarehouseId),
                                },
                                bulkClearSelection ?? undefined,
                            );
                        }}
                    >
                        <DialogHeader>
                            <DialogTitle>Assign Warehouse</DialogTitle>
                            <DialogDescription>
                                Set the default warehouse for {bulkIds.length} selected product{bulkIds.length === 1 ? '' : 's'}.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-2 py-4">
                            <Label>Warehouse</Label>
                            <Select value={bulkWarehouseId || undefined} onValueChange={setBulkWarehouseId}>
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Select warehouse" />
                                </SelectTrigger>
                                <SelectContent>
                                    {activeWarehouses.map((warehouse) => (
                                        <SelectItem key={warehouse.id} value={warehouse.id.toString()}>
                                            {warehouse.code} — {warehouse.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <ModalButtons
                            onCancel={() => setBulkAssignWarehouseOpen(false)}
                            saveLabel="Assign"
                            processing={bulkProcessing}
                        />
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDeleteDialog
                open={bulkDeleteOpen}
                onOpenChange={setBulkDeleteOpen}
                title="Archive selected products?"
                description={
                    <>
                        Archive <span className="font-semibold text-foreground">{bulkIds.length}</span> product
                        {bulkIds.length === 1 ? '' : 's'}? Products with inventory or history are marked inactive instead.
                    </>
                }
                confirmLabel="Archive"
                processing={bulkProcessing}
                onConfirm={() => submitBulk({ action: 'delete', ids: bulkIds }, bulkClearSelection ?? undefined)}
            />

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
