import { Head, router } from '@inertiajs/react';
import { ScrollText } from 'lucide-react';
import React from 'react';

import { DataTable, type ColumnDef, type TableMeta } from '@/components/data-table/data-table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface ProductOption {
    id: number;
    sku: string;
    name: string;
    uom?: { id: number; code: string; symbol?: string | null } | null;
}

interface PlaceOption {
    id: number;
    code: string;
    name: string;
    warehouse_id?: number;
}

interface TransactionRow {
    id: number;
    transaction_no: string | null;
    transaction_type: string;
    reference_number: string | null;
    lot_number: string | null;
    serial_number: string | null;
    quantity: string | number;
    quantity_before: string | number;
    quantity_after: string | number;
    notes: string | null;
    transacted_at: string;
    product?: ProductOption | null;
    warehouse?: PlaceOption | null;
    location?: PlaceOption | null;
    from_warehouse?: PlaceOption | null;
    from_location?: PlaceOption | null;
    to_warehouse?: PlaceOption | null;
    to_location?: PlaceOption | null;
    creator?: { id: number; name: string } | null;
}

interface PaginatedTransactions {
    data: TransactionRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    transactions: PaginatedTransactions;
    warehouses: PlaceOption[];
    locations: PlaceOption[];
    products: ProductOption[];
    transactionTypes: string[];
    plant: { id: number; name: string; code: string } | null;
    filters: Record<string, string>;
}

function formatQty(value: string | number | null | undefined): string {
    const num = Number(value ?? 0);
    return Number.isInteger(num) ? String(num) : num.toFixed(4).replace(/\.?0+$/, '');
}

function placeLabel(
    warehouse?: PlaceOption | null,
    location?: PlaceOption | null,
): string {
    if (!warehouse && !location) {
        return '—';
    }
    const parts = [warehouse?.code, location?.code].filter(Boolean);
    return parts.join(' / ') || '—';
}

export default function InventoryTransactionsIndex({
    transactions,
    warehouses,
    locations,
    products,
    transactionTypes,
    plant,
    filters,
}: Props) {
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
        if (key === 'warehouse_id') {
            delete params.warehouse_location_id;
        }
        delete params.page;
        router.get('/inventory-transactions', params, { preserveState: true, replace: true });
    };

    const filterLocations = currentParams.warehouse_id
        ? locations.filter((loc) => loc.warehouse_id?.toString() === currentParams.warehouse_id)
        : locations;

    const columns: ColumnDef<TransactionRow>[] = [
        {
            key: 'transacted_at',
            label: 'Date',
            render: (row) => (
                <span className="text-sm whitespace-nowrap">
                    {row.transacted_at ? new Date(row.transacted_at).toLocaleString() : '—'}
                </span>
            ),
        },
        {
            key: 'transaction_no',
            label: 'Transaction No',
            render: (row) => (
                <span className="font-mono text-xs font-semibold">{row.transaction_no || '—'}</span>
            ),
        },
        {
            key: 'transaction_type',
            label: 'Type',
            render: (row) => <span className="font-medium">{row.transaction_type}</span>,
        },
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
            key: 'warehouse',
            label: 'Warehouse',
            className: 'text-muted-foreground text-sm',
            render: (row) => row.warehouse?.code ?? '—',
        },
        {
            key: 'location',
            label: 'Location',
            className: 'text-muted-foreground text-sm',
            render: (row) => row.location?.code ?? '—',
        },
        {
            key: 'from',
            label: 'From',
            className: 'text-muted-foreground text-xs',
            render: (row) => placeLabel(row.from_warehouse, row.from_location),
        },
        {
            key: 'to',
            label: 'To',
            className: 'text-muted-foreground text-xs',
            render: (row) => placeLabel(row.to_warehouse, row.to_location),
        },
        {
            key: 'quantity',
            label: 'Qty',
            render: (row) => {
                const qty = Number(row.quantity);
                return (
                    <span className={qty < 0 ? 'text-rose-600 font-semibold' : 'text-emerald-600 font-semibold'}>
                        {qty > 0 ? '+' : ''}{formatQty(row.quantity)}
                    </span>
                );
            },
        },
        {
            key: 'quantity_after',
            label: 'Balance',
            render: (row) => <span className="font-semibold">{formatQty(row.quantity_after)}</span>,
        },
        {
            key: 'creator',
            label: 'User',
            className: 'text-muted-foreground',
            render: (row) => row.creator?.name ?? '—',
        },
        {
            key: 'reference_number',
            label: 'Reference',
            defaultVisible: false,
            className: 'text-muted-foreground font-mono text-xs',
            render: (row) => row.reference_number ?? '—',
        },
        {
            key: 'lot_number',
            label: 'Lot',
            defaultVisible: false,
            className: 'text-muted-foreground font-mono text-xs',
            render: (row) => row.lot_number ?? '—',
        },
        {
            key: 'serial_number',
            label: 'Serial',
            defaultVisible: false,
            className: 'text-muted-foreground font-mono text-xs',
            render: (row) => row.serial_number ?? '—',
        },
        {
            key: 'notes',
            label: 'Notes',
            defaultVisible: false,
            className: 'text-muted-foreground',
            render: (row) => row.notes ?? '—',
        },
        {
            key: 'quantity_before',
            label: 'Qty Before',
            defaultVisible: false,
            className: 'text-muted-foreground',
            render: (row) => formatQty(row.quantity_before),
        },
    ];

    const meta: TableMeta = {
        current_page: transactions.current_page,
        last_page: transactions.last_page,
        per_page: transactions.per_page,
        total: transactions.total,
        from: transactions.from ?? 1,
        to: transactions.to ?? transactions.data.length,
    };

    const filterSlot = (
        <>
            <Select value={currentParams.transaction_type ?? 'all'} onValueChange={(v) => makeFilterChange('transaction_type', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[140px] border-dashed">
                    <SelectValue placeholder="Type" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Types</SelectItem>
                    {transactionTypes.map((type) => (
                        <SelectItem key={type} value={type}>{type}</SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select value={currentParams.product_id ?? 'all'} onValueChange={(v) => makeFilterChange('product_id', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[150px] border-dashed">
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
        </>
    );

    const filteredHint = currentParams.inventory_id || currentParams.product_id
        ? 'Filtered to a specific inventory balance.'
        : 'Complete stock movement ledger for the active plant.';

    return (
        <>
            <Head title="Inventory Transactions" />
            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Inventory Transactions</h1>
                    <p className="text-sm text-muted-foreground">
                        {filteredHint}
                        {plant ? ` (${plant.name})` : ''}
                    </p>
                </div>

                <DataTable
                    tableId="inventory-transactions"
                    columns={columns}
                    data={transactions.data}
                    meta={meta}
                    baseUrl="/inventory-transactions"
                    currentParams={currentParams}
                    searchPlaceholder="Search txn no, SKU, reference, lot…"
                    entityLabel="transactions"
                    emptyStateIcon={ScrollText}
                    emptyStateTitle="No transactions yet"
                    emptyStateDescription="Adjustments, transfers, receipts, and issues will appear here."
                    filterSlot={filterSlot}
                />
            </div>
        </>
    );
}

InventoryTransactionsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Inventory', href: '/inventory' },
        { title: 'Transactions', href: '/inventory-transactions' },
    ],
};
