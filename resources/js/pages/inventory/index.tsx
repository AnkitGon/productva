import { Head, router, useForm } from '@inertiajs/react';
import React, { useEffect, useMemo, useState } from 'react';
import { ArrowLeftRight, Boxes, Download, History, SlidersHorizontal } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import InputError from '@/components/input-error';
import { ModalButtons } from '@/components/modal-buttons';
import { DataTable, type ColumnDef, type TableMeta } from '@/components/data-table/data-table';
import { StatusBadge } from '@/components/data-table/status-badge';
import type { RowAction } from '@/components/data-table/data-table-row-actions';
import { useCan } from '@/hooks/use-can';

interface UomOption {
    id: number;
    code: string;
    name?: string;
    symbol?: string | null;
}

interface ProductOption {
    id: number;
    sku: string;
    name: string;
    lot_tracking?: boolean;
    serial_tracking?: boolean;
    barcode?: string | null;
    category?: { id: number; code: string; name: string } | null;
    reorder_level?: string | number | null;
    uom?: UomOption | null;
}

interface WarehouseOption {
    id: number;
    code: string;
    name: string;
    warehouse_type_id?: number;
}

interface LocationOption {
    id: number;
    code: string;
    name: string;
    warehouse_id: number;
}

interface InventoryRow {
    id: number;
    lot_number: string;
    serial_number: string;
    quantity_on_hand: string | number;
    quantity_reserved: string | number;
    quantity_available: string | number;
    stock_status: string;
    last_movement_at: string | null;
    product?: ProductOption | null;
    warehouse?: WarehouseOption | null;
    location?: LocationOption | null;
}

interface PaginatedInventories {
    data: InventoryRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Dashboard {
    total_products: number;
    total_stock_value: number;
    out_of_stock: number;
    low_stock: number;
    negative_stock: number;
}

interface Props {
    inventories: PaginatedInventories;
    dashboard: Dashboard;
    warehouses: WarehouseOption[];
    locations: LocationOption[];
    categories: { id: number; code: string; name: string }[];
    warehouseTypes: { id: number; code: string; name: string }[];
    products: ProductOption[];
    stockStatuses: string[];
    plant: { id: number; name: string; code: string } | null;
    filters: Record<string, string>;
}

type AdjustForm = {
    product_id: string;
    warehouse_id: string;
    warehouse_location_id: string;
    new_quantity: string;
    lot_number: string;
    serial_number: string;
    notes: string;
    reference_number: string;
};

type TransferForm = {
    product_id: string;
    from_warehouse_id: string;
    from_location_id: string;
    to_warehouse_id: string;
    to_location_id: string;
    quantity: string;
    lot_number: string;
    serial_number: string;
    notes: string;
    reference_number: string;
};

function emptyAdjust(): AdjustForm {
    return {
        product_id: '',
        warehouse_id: '',
        warehouse_location_id: '',
        new_quantity: '',
        lot_number: '',
        serial_number: '',
        notes: '',
        reference_number: '',
    };
}

function emptyTransfer(): TransferForm {
    return {
        product_id: '',
        from_warehouse_id: '',
        from_location_id: '',
        to_warehouse_id: '',
        to_location_id: '',
        quantity: '',
        lot_number: '',
        serial_number: '',
        notes: '',
        reference_number: '',
    };
}

function formatQty(value: string | number | null | undefined): string {
    const num = Number(value ?? 0);
    return Number.isInteger(num) ? String(num) : num.toFixed(4).replace(/\.?0+$/, '');
}

function formatSignedQty(value: number): string {
    const formatted = formatQty(value);
    return value > 0 ? `+${formatted}` : formatted;
}

function uomLabel(uom?: UomOption | null): string {
    return uom?.symbol || uom?.code || '';
}

function formatLastMovement(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) {
        return 'Just now';
    }
    if (diffMins < 60) {
        return `${diffMins} ${diffMins === 1 ? 'minute' : 'minutes'} ago`;
    }
    if (diffHours < 24) {
        return `${diffHours} ${diffHours === 1 ? 'hour' : 'hours'} ago`;
    }
    if (diffDays === 1) {
        return 'Yesterday';
    }
    if (diffDays < 7) {
        return `${diffDays} days ago`;
    }

    return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
}

function historyUrl(row: InventoryRow): string {
    const params = new URLSearchParams({
        inventory_id: String(row.id),
        product_id: String(row.product?.id ?? ''),
        warehouse_id: String(row.warehouse?.id ?? ''),
        warehouse_location_id: String(row.location?.id ?? ''),
    });
    if (row.lot_number) {
        params.set('lot_number', row.lot_number);
    }
    if (row.serial_number) {
        params.set('serial_number', row.serial_number);
    }
    return `/inventory-transactions?${params.toString()}`;
}

function DashboardCard({ label, value, tone }: { label: string; value: string | number; tone?: string }) {
    return (
        <div className="rounded-xl border border-border/50 bg-card p-4 shadow-sm">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className={`mt-2 text-2xl font-bold tracking-tight ${tone ?? 'text-foreground'}`}>{value}</p>
        </div>
    );
}

export default function InventoryIndex({
    inventories,
    dashboard,
    warehouses,
    locations,
    categories,
    warehouseTypes,
    products,
    stockStatuses,
    plant,
    filters,
}: Props) {
    const { can } = useCan();
    const [adjustOpen, setAdjustOpen] = useState(false);
    const [transferOpen, setTransferOpen] = useState(false);
    const [currentStock, setCurrentStock] = useState(0);
    const [balanceLoading, setBalanceLoading] = useState(false);

    const form = useForm<AdjustForm>(emptyAdjust());
    const transferForm = useForm<TransferForm>(emptyTransfer());

    const currentParams: Record<string, string> = {};
    Object.entries(filters ?? {}).forEach(([k, v]) => {
        if (v) {
            currentParams[k] = v;
        }
    });

    const filteredLocations = useMemo(() => {
        if (!form.data.warehouse_id) {
            return [];
        }
        return locations.filter((loc) => loc.warehouse_id.toString() === form.data.warehouse_id);
    }, [form.data.warehouse_id, locations]);

    const fromLocations = useMemo(() => {
        if (!transferForm.data.from_warehouse_id) {
            return [];
        }
        return locations.filter((loc) => loc.warehouse_id.toString() === transferForm.data.from_warehouse_id);
    }, [transferForm.data.from_warehouse_id, locations]);

    const toLocations = useMemo(() => {
        if (!transferForm.data.to_warehouse_id) {
            return [];
        }
        return locations.filter((loc) => loc.warehouse_id.toString() === transferForm.data.to_warehouse_id);
    }, [transferForm.data.to_warehouse_id, locations]);

    const selectedProduct = products.find((p) => p.id.toString() === form.data.product_id);
    const transferProduct = products.find((p) => p.id.toString() === transferForm.data.product_id);

    const difference = form.data.new_quantity === ''
        ? null
        : Number(form.data.new_quantity) - currentStock;

    useEffect(() => {
        const { product_id, warehouse_id, warehouse_location_id, lot_number, serial_number } = form.data;
        if (!adjustOpen || !product_id || !warehouse_id || !warehouse_location_id) {
            setCurrentStock(0);
            return;
        }

        const product = products.find((p) => p.id.toString() === product_id);
        if (product?.lot_tracking && !lot_number.trim()) {
            setCurrentStock(0);
            return;
        }
        if (product?.serial_tracking && !serial_number.trim()) {
            setCurrentStock(0);
            return;
        }

        const controller = new AbortController();
        const params = new URLSearchParams({
            product_id,
            warehouse_id,
            warehouse_location_id,
        });
        if (lot_number.trim()) {
            params.set('lot_number', lot_number.trim());
        }
        if (serial_number.trim()) {
            params.set('serial_number', serial_number.trim());
        }

        setBalanceLoading(true);
        fetch(`/inventory/balance?${params.toString()}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error('Failed to load balance');
                }
                return response.json();
            })
            .then((data: { quantity_on_hand: number }) => {
                setCurrentStock(Number(data.quantity_on_hand) || 0);
            })
            .catch((error: unknown) => {
                if (error instanceof DOMException && error.name === 'AbortError') {
                    return;
                }
                setCurrentStock(0);
            })
            .finally(() => setBalanceLoading(false));

        return () => controller.abort();
    }, [
        adjustOpen,
        form.data.product_id,
        form.data.warehouse_id,
        form.data.warehouse_location_id,
        form.data.lot_number,
        form.data.serial_number,
        products,
    ]);

    const makeFilterChange = (key: string, val: string) => {
        const params = { ...currentParams };
        if (val === 'all' || val === '') {
            delete params[key];
        } else {
            params[key] = val;
        }
        if (key === 'warehouse_id') {
            delete params.warehouse_location_id;
        }
        delete params.page;
        router.get('/inventory', params, { preserveState: true, replace: true });
    };

    const openAdjustForRow = (row: InventoryRow) => {
        form.setData({
            product_id: row.product?.id?.toString() ?? '',
            warehouse_id: row.warehouse?.id?.toString() ?? '',
            warehouse_location_id: row.location?.id?.toString() ?? '',
            new_quantity: formatQty(row.quantity_on_hand),
            lot_number: row.lot_number || '',
            serial_number: row.serial_number || '',
            notes: '',
            reference_number: '',
        });
        form.clearErrors();
        setCurrentStock(Number(row.quantity_on_hand) || 0);
        setAdjustOpen(true);
    };

    const openTransferForRow = (row: InventoryRow) => {
        transferForm.setData({
            product_id: row.product?.id?.toString() ?? '',
            from_warehouse_id: row.warehouse?.id?.toString() ?? '',
            from_location_id: row.location?.id?.toString() ?? '',
            to_warehouse_id: '',
            to_location_id: '',
            quantity: '',
            lot_number: row.lot_number || '',
            serial_number: row.serial_number || '',
            notes: '',
            reference_number: '',
        });
        transferForm.clearErrors();
        setTransferOpen(true);
    };

    const columns: ColumnDef<InventoryRow>[] = [
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
            key: 'warehouse',
            label: 'Warehouse',
            className: 'text-muted-foreground',
            render: (row) => row.warehouse?.name ?? '—',
        },
        {
            key: 'location',
            label: 'Location',
            className: 'text-muted-foreground',
            render: (row) => row.location?.code ?? '—',
        },
        {
            key: 'lot_number',
            label: 'Lot',
            className: 'text-muted-foreground font-mono text-xs',
            render: (row) => row.lot_number || '—',
        },
        {
            key: 'serial_number',
            label: 'Serial',
            className: 'text-muted-foreground font-mono text-xs',
            render: (row) => row.serial_number || '—',
        },
        {
            key: 'quantity_on_hand',
            label: 'On Hand',
            sortable: true,
            render: (row) => <span className="font-semibold">{formatQty(row.quantity_on_hand)}</span>,
        },
        {
            key: 'quantity_reserved',
            label: 'Reserved',
            sortable: true,
            className: 'text-muted-foreground',
            render: (row) => formatQty(row.quantity_reserved),
        },
        {
            key: 'quantity_available',
            label: 'Available',
            render: (row) => <span className="font-semibold">{formatQty(row.quantity_available)}</span>,
        },
        {
            key: 'stock_status',
            label: 'Status',
            render: (row) => <StatusBadge status={row.stock_status} />,
        },
        {
            key: 'last_movement_at',
            label: 'Last Movement',
            sortable: true,
            className: 'text-muted-foreground text-sm',
            render: (row) => formatLastMovement(row.last_movement_at),
        },
    ];

    const meta: TableMeta = {
        current_page: inventories.current_page,
        last_page: inventories.last_page,
        per_page: inventories.per_page,
        total: inventories.total,
        from: inventories.from ?? 1,
        to: inventories.to ?? inventories.data.length,
    };

    const filterLocations = currentParams.warehouse_id
        ? locations.filter((loc) => loc.warehouse_id.toString() === currentParams.warehouse_id)
        : locations;

    const filterSlot = (
        <>
            <Select value={currentParams.warehouse_id ?? 'all'} onValueChange={(v) => makeFilterChange('warehouse_id', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[130px] border-dashed">
                    <SelectValue placeholder="Warehouse" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Warehouses</SelectItem>
                    {warehouses.map((wh) => (
                        <SelectItem key={wh.id} value={wh.id.toString()}>{wh.code} — {wh.name}</SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select value={currentParams.warehouse_location_id ?? 'all'} onValueChange={(v) => makeFilterChange('warehouse_location_id', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[120px] border-dashed">
                    <SelectValue placeholder="Location" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Locations</SelectItem>
                    {filterLocations.map((loc) => (
                        <SelectItem key={loc.id} value={loc.id.toString()}>{loc.code} — {loc.name}</SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select value={currentParams.category_id ?? 'all'} onValueChange={(v) => makeFilterChange('category_id', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[120px] border-dashed">
                    <SelectValue placeholder="Category" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Categories</SelectItem>
                    {categories.map((cat) => (
                        <SelectItem key={cat.id} value={cat.id.toString()}>{cat.name}</SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select value={currentParams.warehouse_type_id ?? 'all'} onValueChange={(v) => makeFilterChange('warehouse_type_id', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[120px] border-dashed">
                    <SelectValue placeholder="WH Type" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All WH Types</SelectItem>
                    {warehouseTypes.map((type) => (
                        <SelectItem key={type.id} value={type.id.toString()}>{type.code}</SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select value={currentParams.stock_status ?? 'all'} onValueChange={(v) => makeFilterChange('stock_status', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[120px] border-dashed">
                    <SelectValue placeholder="Status" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Statuses</SelectItem>
                    {stockStatuses.map((status) => (
                        <SelectItem key={status} value={status}>{status}</SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </>
    );

    const exportUrl = `/inventory/export${Object.keys(currentParams).length ? `?${new URLSearchParams(currentParams)}` : ''}`;
    const unit = uomLabel(selectedProduct?.uom);

    return (
        <>
            <Head title="Inventory" />
            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Inventory</h1>
                    <p className="text-sm text-muted-foreground">
                        Current balances for the active plant{plant ? ` (${plant.name})` : ''}. Movements live in Inventory Transactions.
                    </p>
                </div>

                <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <DashboardCard label="Total Products" value={dashboard.total_products} />
                    <DashboardCard label="Total Stock Value" value={dashboard.total_stock_value.toLocaleString()} />
                    <DashboardCard label="Out of Stock" value={dashboard.out_of_stock} tone="text-rose-600" />
                    <DashboardCard label="Low Stock" value={dashboard.low_stock} tone="text-amber-600" />
                    <DashboardCard label="Negative Stock" value={dashboard.negative_stock} tone="text-rose-700" />
                </div>

                <DataTable
                    tableId="inventory"
                    columns={columns}
                    data={inventories.data}
                    meta={meta}
                    baseUrl="/inventory"
                    currentParams={currentParams}
                    searchPlaceholder="Search SKU, name, barcode, lot, serial…"
                    entityLabel="inventory balances"
                    emptyStateIcon={Boxes}
                    emptyStateTitle="No inventory balances found"
                    emptyStateDescription="Balances appear automatically after the first stock receipt or adjustment."
                    primaryAction={
                        <div className="flex items-center gap-2 flex-wrap">
                            {can('inventory.export') && (
                                <Button variant="outline" className="gap-2 h-9 font-semibold text-xs" asChild>
                                    <a href={exportUrl}>
                                        <Download className="size-4" />
                                        Export
                                    </a>
                                </Button>
                            )}
                            {can('inventory.adjust') && (
                                <Button
                                    className="gap-2 h-9 font-semibold text-xs"
                                    onClick={() => {
                                        form.setData(emptyAdjust());
                                        form.clearErrors();
                                        setCurrentStock(0);
                                        setAdjustOpen(true);
                                    }}
                                >
                                    <SlidersHorizontal className="size-4" />
                                    Adjust Stock
                                </Button>
                            )}
                        </div>
                    }
                    filterSlot={filterSlot}
                    rowActions={() => {
                        const actions: RowAction<InventoryRow>[] = [];
                        if (can('inventory.view') || can('inventory.history')) {
                            actions.push({
                                label: 'History',
                                icon: History,
                                onClick: (row) => router.visit(historyUrl(row)),
                            });
                        }
                        if (can('inventory.adjust')) {
                            actions.push({
                                label: 'Adjust',
                                icon: SlidersHorizontal,
                                onClick: (row) => openAdjustForRow(row),
                            });
                            actions.push({
                                label: 'Transfer',
                                icon: ArrowLeftRight,
                                onClick: (row) => openTransferForRow(row),
                            });
                        }
                        return actions;
                    }}
                />
            </div>

            <Dialog open={adjustOpen} onOpenChange={(open) => { setAdjustOpen(open); if (!open) form.reset(); }}>
                <DialogContent className="sm:max-w-lg max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Stock Adjustment</DialogTitle>
                        <DialogDescription className="text-xs">
                            Set the new on-hand quantity. Difference is calculated automatically and posted as ADJ-######.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/inventory/adjust', {
                                onSuccess: () => {
                                    setAdjustOpen(false);
                                    form.reset();
                                },
                            });
                        }}
                        className="space-y-4"
                    >
                        <div className="space-y-2">
                            <Label>Product <span className="text-destructive">*</span></Label>
                            <Select
                                value={form.data.product_id || undefined}
                                onValueChange={(v) => form.setData((d) => ({
                                    ...d,
                                    product_id: v,
                                    lot_number: '',
                                    serial_number: '',
                                }))}
                            >
                                <SelectTrigger className="w-full"><SelectValue placeholder="Select product" /></SelectTrigger>
                                <SelectContent>
                                    {products.map((product) => (
                                        <SelectItem key={product.id} value={product.id.toString()}>
                                            {product.sku} — {product.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.product_id} />
                        </div>
                        <div className="space-y-2">
                            <Label>Warehouse <span className="text-destructive">*</span></Label>
                            <Select
                                value={form.data.warehouse_id || undefined}
                                onValueChange={(v) => form.setData((d) => ({ ...d, warehouse_id: v, warehouse_location_id: '' }))}
                            >
                                <SelectTrigger className="w-full"><SelectValue placeholder="Select warehouse" /></SelectTrigger>
                                <SelectContent>
                                    {warehouses.map((wh) => (
                                        <SelectItem key={wh.id} value={wh.id.toString()}>{wh.code} — {wh.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.warehouse_id} />
                        </div>
                        <div className="space-y-2">
                            <Label>Location <span className="text-destructive">*</span></Label>
                            <Select
                                value={form.data.warehouse_location_id || undefined}
                                onValueChange={(v) => form.setData('warehouse_location_id', v)}
                                disabled={!form.data.warehouse_id}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder={form.data.warehouse_id ? 'Select location' : 'Select warehouse first'} />
                                </SelectTrigger>
                                <SelectContent>
                                    {filteredLocations.map((loc) => (
                                        <SelectItem key={loc.id} value={loc.id.toString()}>{loc.code} — {loc.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.warehouse_location_id} />
                        </div>
                        {selectedProduct?.lot_tracking && (
                            <div className="space-y-2">
                                <Label>Lot Number <span className="text-destructive">*</span></Label>
                                <Input value={form.data.lot_number} onChange={(e) => form.setData('lot_number', e.target.value)} required />
                                <InputError message={form.errors.lot_number} />
                            </div>
                        )}
                        {selectedProduct?.serial_tracking && (
                            <div className="space-y-2">
                                <Label>Serial Number <span className="text-destructive">*</span></Label>
                                <Input value={form.data.serial_number} onChange={(e) => form.setData('serial_number', e.target.value)} required />
                                <InputError message={form.errors.serial_number} />
                            </div>
                        )}
                        <div className="rounded-lg border border-border/60 bg-muted/30 p-3 space-y-3">
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">Current Stock</span>
                                <span className="font-semibold">
                                    {balanceLoading ? '…' : `${formatQty(currentStock)}${unit ? ` ${unit}` : ''}`}
                                </span>
                            </div>
                            <div className="space-y-2">
                                <Label>New Quantity <span className="text-destructive">*</span></Label>
                                <div className="flex items-center gap-2">
                                    <Input
                                        type="number"
                                        step="0.0001"
                                        value={form.data.new_quantity}
                                        onChange={(e) => form.setData('new_quantity', e.target.value)}
                                        placeholder="Enter target quantity"
                                        required
                                    />
                                    {unit ? <span className="text-sm text-muted-foreground shrink-0">{unit}</span> : null}
                                </div>
                                <InputError message={form.errors.new_quantity} />
                            </div>
                            <div className="flex items-center justify-between text-sm border-t border-border/50 pt-3">
                                <span className="text-muted-foreground">Difference</span>
                                <span className={`font-semibold ${difference === null ? '' : difference < 0 ? 'text-rose-600' : difference > 0 ? 'text-emerald-600' : 'text-muted-foreground'}`}>
                                    {difference === null ? '—' : formatSignedQty(difference)}
                                </span>
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label>External Reference</Label>
                            <Input value={form.data.reference_number} onChange={(e) => form.setData('reference_number', e.target.value)} placeholder="Optional document ref" />
                            <InputError message={form.errors.reference_number} />
                        </div>
                        <div className="space-y-2">
                            <Label>Notes</Label>
                            <textarea
                                value={form.data.notes}
                                onChange={(e) => form.setData('notes', e.target.value)}
                                rows={3}
                                className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            />
                            <InputError message={form.errors.notes} />
                        </div>
                        <ModalButtons onCancel={() => setAdjustOpen(false)} processing={form.processing} />
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={transferOpen} onOpenChange={(open) => { setTransferOpen(open); if (!open) transferForm.reset(); }}>
                <DialogContent className="sm:max-w-lg max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Stock Transfer</DialogTitle>
                        <DialogDescription className="text-xs">
                            Move quantity between locations. Creates Transfer Out and Transfer In under one TRF-###### number.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            transferForm.post('/inventory/transfer', {
                                onSuccess: () => {
                                    setTransferOpen(false);
                                    transferForm.reset();
                                },
                            });
                        }}
                        className="space-y-4"
                    >
                        <div className="space-y-2">
                            <Label>Product <span className="text-destructive">*</span></Label>
                            <Select
                                value={transferForm.data.product_id || undefined}
                                onValueChange={(v) => transferForm.setData((d) => ({
                                    ...d,
                                    product_id: v,
                                    lot_number: '',
                                    serial_number: '',
                                }))}
                            >
                                <SelectTrigger className="w-full"><SelectValue placeholder="Select product" /></SelectTrigger>
                                <SelectContent>
                                    {products.map((product) => (
                                        <SelectItem key={product.id} value={product.id.toString()}>
                                            {product.sku} — {product.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={transferForm.errors.product_id} />
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>From Warehouse <span className="text-destructive">*</span></Label>
                                <Select
                                    value={transferForm.data.from_warehouse_id || undefined}
                                    onValueChange={(v) => transferForm.setData((d) => ({ ...d, from_warehouse_id: v, from_location_id: '' }))}
                                >
                                    <SelectTrigger className="w-full"><SelectValue placeholder="Source" /></SelectTrigger>
                                    <SelectContent>
                                        {warehouses.map((wh) => (
                                            <SelectItem key={wh.id} value={wh.id.toString()}>{wh.code}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={transferForm.errors.from_warehouse_id} />
                            </div>
                            <div className="space-y-2">
                                <Label>From Location <span className="text-destructive">*</span></Label>
                                <Select
                                    value={transferForm.data.from_location_id || undefined}
                                    onValueChange={(v) => transferForm.setData('from_location_id', v)}
                                    disabled={!transferForm.data.from_warehouse_id}
                                >
                                    <SelectTrigger className="w-full"><SelectValue placeholder="Source bin" /></SelectTrigger>
                                    <SelectContent>
                                        {fromLocations.map((loc) => (
                                            <SelectItem key={loc.id} value={loc.id.toString()}>{loc.code}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={transferForm.errors.from_location_id} />
                            </div>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>To Warehouse <span className="text-destructive">*</span></Label>
                                <Select
                                    value={transferForm.data.to_warehouse_id || undefined}
                                    onValueChange={(v) => transferForm.setData((d) => ({ ...d, to_warehouse_id: v, to_location_id: '' }))}
                                >
                                    <SelectTrigger className="w-full"><SelectValue placeholder="Destination" /></SelectTrigger>
                                    <SelectContent>
                                        {warehouses.map((wh) => (
                                            <SelectItem key={wh.id} value={wh.id.toString()}>{wh.code}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={transferForm.errors.to_warehouse_id} />
                            </div>
                            <div className="space-y-2">
                                <Label>To Location <span className="text-destructive">*</span></Label>
                                <Select
                                    value={transferForm.data.to_location_id || undefined}
                                    onValueChange={(v) => transferForm.setData('to_location_id', v)}
                                    disabled={!transferForm.data.to_warehouse_id}
                                >
                                    <SelectTrigger className="w-full"><SelectValue placeholder="Dest bin" /></SelectTrigger>
                                    <SelectContent>
                                        {toLocations.map((loc) => (
                                            <SelectItem key={loc.id} value={loc.id.toString()}>{loc.code}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={transferForm.errors.to_location_id ?? transferForm.errors.to_warehouse_location_id} />
                            </div>
                        </div>
                        {transferProduct?.lot_tracking && (
                            <div className="space-y-2">
                                <Label>Lot Number <span className="text-destructive">*</span></Label>
                                <Input value={transferForm.data.lot_number} onChange={(e) => transferForm.setData('lot_number', e.target.value)} required />
                                <InputError message={transferForm.errors.lot_number} />
                            </div>
                        )}
                        {transferProduct?.serial_tracking && (
                            <div className="space-y-2">
                                <Label>Serial Number <span className="text-destructive">*</span></Label>
                                <Input value={transferForm.data.serial_number} onChange={(e) => transferForm.setData('serial_number', e.target.value)} required />
                                <InputError message={transferForm.errors.serial_number} />
                            </div>
                        )}
                        <div className="space-y-2">
                            <Label>Quantity <span className="text-destructive">*</span></Label>
                            <Input
                                type="number"
                                min={0}
                                step="0.0001"
                                value={transferForm.data.quantity}
                                onChange={(e) => transferForm.setData('quantity', e.target.value)}
                                required
                            />
                            <InputError message={transferForm.errors.quantity} />
                        </div>
                        <div className="space-y-2">
                            <Label>Notes</Label>
                            <textarea
                                value={transferForm.data.notes}
                                onChange={(e) => transferForm.setData('notes', e.target.value)}
                                rows={3}
                                className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            />
                            <InputError message={transferForm.errors.notes} />
                        </div>
                        <ModalButtons onCancel={() => setTransferOpen(false)} processing={transferForm.processing} />
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

InventoryIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Inventory', href: '/inventory' },
    ],
};
