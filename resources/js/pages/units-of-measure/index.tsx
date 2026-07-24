import { Head, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import { Edit2, Plus, Ruler, Trash2 } from 'lucide-react';

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
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { ModalButtons } from '@/components/modal-buttons';
import { DataTable, type ColumnDef, type TableMeta } from '@/components/data-table/data-table';
import { StatusBadge } from '@/components/data-table/status-badge';
import type { RowAction } from '@/components/data-table/data-table-row-actions';
import { useCan } from '@/hooks/use-can';

interface UnitOfMeasure {
    id: number;
    code: string;
    name: string;
    symbol: string;
    type: string;
    decimal_places: number;
    status: 'Active' | 'Inactive';
    description: string | null;
    products_count?: number;
}

interface PaginatedUnitsOfMeasure {
    data: UnitOfMeasure[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    unitsOfMeasure: PaginatedUnitsOfMeasure;
    types: string[];
    statuses: string[];
    filters: Record<string, string>;
}

type UnitOfMeasureFormData = {
    code: string;
    name: string;
    symbol: string;
    type: string;
    decimal_places: string;
    status: string;
    description: string;
};

function emptyForm(): UnitOfMeasureFormData {
    return {
        code: '',
        name: '',
        symbol: '',
        type: 'Count',
        decimal_places: '0',
        status: 'Active',
        description: '',
    };
}

function formFromUnit(unit: UnitOfMeasure): UnitOfMeasureFormData {
    return {
        code: unit.code,
        name: unit.name,
        symbol: unit.symbol,
        type: unit.type,
        decimal_places: String(unit.decimal_places),
        status: unit.status,
        description: unit.description ?? '',
    };
}

export default function UnitsOfMeasureIndex({ unitsOfMeasure, types, statuses, filters }: Props) {
    const { can } = useCan();
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingUnit, setEditingUnit] = useState<UnitOfMeasure | null>(null);
    const [deleteConfirmUnit, setDeleteConfirmUnit] = useState<UnitOfMeasure | null>(null);

    const form = useForm<UnitOfMeasureFormData>(emptyForm());

    const currentParams: Record<string, string> = {};
    Object.entries(filters ?? {}).forEach(([k, v]) => {
        if (v) {
            currentParams[k] = v;
        }
    });

    const openCreate = () => {
        setEditingUnit(null);
        form.setData(emptyForm());
        form.clearErrors();
        setIsDialogOpen(true);
    };

    const openEdit = (unit: UnitOfMeasure) => {
        setEditingUnit(unit);
        form.setData(formFromUnit(unit));
        form.clearErrors();
        setIsDialogOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const config = {
            onSuccess: () => {
                setIsDialogOpen(false);
                form.reset();
            },
        };

        if (editingUnit) {
            form.put(`/units-of-measure/${editingUnit.id}`, config);
        } else {
            form.post('/units-of-measure', config);
        }
    };

    const makeFilterChange = (key: string, val: string) => {
        const params = { ...currentParams };
        if (val === 'all' || val === '') {
            delete params[key];
        } else {
            params[key] = val;
        }
        delete params.page;
        router.get('/units-of-measure', params, { preserveState: true, replace: true });
    };

    const columns: ColumnDef<UnitOfMeasure>[] = [
        {
            key: 'code',
            label: 'Code',
            sortable: true,
            render: (row) => <span className="font-mono text-xs font-semibold">{row.code}</span>,
        },
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (row) => <span className="font-semibold text-foreground">{row.name}</span>,
        },
        {
            key: 'symbol',
            label: 'Symbol',
            sortable: true,
            className: 'text-muted-foreground',
            render: (row) => <span className="font-mono text-xs">{row.symbol}</span>,
        },
        {
            key: 'type',
            label: 'Type',
            sortable: true,
            className: 'text-muted-foreground',
        },
        {
            key: 'products_count',
            label: 'Products',
            className: 'text-muted-foreground',
            render: (row) => {
                const count = row.products_count ?? 0;
                const label = `${count} ${count === 1 ? 'product' : 'products'}`;

                if (count === 0) {
                    return label;
                }

                return (
                    <button
                        type="button"
                        className="cursor-pointer font-medium text-foreground hover:underline"
                        onClick={() => router.visit(`/products?uom_id=${row.id}`)}
                    >
                        {label}
                    </button>
                );
            },
        },
        {
            key: 'decimal_places',
            label: 'Decimals',
            sortable: true,
            className: 'text-muted-foreground',
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (row) => <StatusBadge status={row.status} />,
        },
        {
            key: 'description',
            label: 'Description',
            defaultVisible: false,
            className: 'text-muted-foreground',
            render: (row) => row.description ?? '—',
        },
    ];

    const meta: TableMeta = {
        current_page: unitsOfMeasure.current_page,
        last_page: unitsOfMeasure.last_page,
        per_page: unitsOfMeasure.per_page,
        total: unitsOfMeasure.total,
        from: unitsOfMeasure.from ?? 1,
        to: unitsOfMeasure.to ?? unitsOfMeasure.data.length,
    };

    const filterSlot = (
        <>
            <Select value={currentParams.type ?? 'all'} onValueChange={(v) => makeFilterChange('type', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[120px] border-dashed">
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
        </>
    );

    return (
        <>
            <Head title="Units of Measure" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Units of Measure</h1>
                        <p className="text-sm text-muted-foreground">
                            Shared organization-wide units used by products, inventory, BOM, and production.
                        </p>
                    </div>
                </div>

                <DataTable
                    tableId="units-of-measure"
                    columns={columns}
                    data={unitsOfMeasure.data}
                    meta={meta}
                    baseUrl="/units-of-measure"
                    currentParams={currentParams}
                    searchPlaceholder="Search by code, name, symbol, description…"
                    entityLabel="units of measure"
                    emptyStateIcon={Ruler}
                    emptyStateTitle="No units of measure found"
                    emptyStateDescription={
                        can('uom.create')
                            ? 'Create your first unit of measure for this organization.'
                            : 'No units of measure are available for this organization.'
                    }
                    emptyStateAction={
                        can('uom.create') ? (
                            <Button size="sm" onClick={openCreate} className="gap-2">
                                <Plus className="size-3.5" />
                                Create Unit
                            </Button>
                        ) : undefined
                    }
                    primaryAction={
                        can('uom.create') ? (
                            <Button onClick={openCreate} className="gap-2 h-9 font-semibold text-xs">
                                <Plus className="size-4" />
                                Add Unit
                            </Button>
                        ) : undefined
                    }
                    filterSlot={filterSlot}
                    rowActions={(row) => {
                        const actions: RowAction<UnitOfMeasure>[] = [];

                        if (can('uom.update')) {
                            actions.push({
                                label: 'Edit',
                                icon: Edit2,
                                onClick: openEdit,
                            });
                        }

                        if (can('uom.delete')) {
                            actions.push({
                                label: 'Archive',
                                icon: Trash2,
                                onClick: (r) => setDeleteConfirmUnit(r),
                                variant: 'destructive',
                                separator: true,
                            });
                        }

                        return actions;
                    }}
                />
            </div>

            <Dialog
                open={isDialogOpen}
                onOpenChange={(open) => {
                    setIsDialogOpen(open);
                    if (!open) {
                        setEditingUnit(null);
                        form.reset();
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-bold">
                            {editingUnit ? 'Edit Unit of Measure' : 'Add Unit of Measure'}
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            Units of measure are shared across all plants in the organization.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">


                            <div className="space-y-2">
                                <Label htmlFor="symbol">
                                    Symbol <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="symbol"
                                    value={form.data.symbol}
                                    onChange={(e) => form.setData('symbol', e.target.value)}
                                    placeholder="pcs"
                                    maxLength={20}
                                    required
                                />
                                <InputError message={form.errors.symbol} />
                            </div>

                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="name">
                                    Name <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    placeholder="Pieces"
                                    required
                                    autoFocus
                                />
                                <InputError message={form.errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label>
                                    Type <span className="text-destructive">*</span>
                                </Label>
                                <Select
                                    value={form.data.type}
                                    onValueChange={(val) => form.setData('type', val)}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {types.map((type) => (
                                            <SelectItem key={type} value={type}>
                                                {type}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.type} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="decimal_places">
                                    Decimal Places <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="decimal_places"
                                    type="number"
                                    min={0}
                                    max={6}
                                    value={form.data.decimal_places}
                                    onChange={(e) => form.setData('decimal_places', e.target.value)}
                                    required
                                />
                                <InputError message={form.errors.decimal_places} />
                            </div>

                            <div className="space-y-2">
                                <Label>Status</Label>
                                <Select
                                    value={form.data.status}
                                    onValueChange={(val) => form.setData('status', val)}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {statuses.map((status) => (
                                            <SelectItem key={status} value={status}>
                                                {status}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.status} />
                            </div>

                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="description">Description</Label>
                                <textarea
                                    id="description"
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                    rows={3}
                                    placeholder="Optional notes about this unit"
                                    className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <InputError message={form.errors.description} />
                            </div>
                        </div>

                        <ModalButtons onCancel={() => setIsDialogOpen(false)} processing={form.processing} />
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDeleteDialog
                open={deleteConfirmUnit !== null}
                onOpenChange={(open) => !open && setDeleteConfirmUnit(null)}
                title="Archive Unit of Measure?"
                description={
                    <>
                        Archive <span className="font-semibold text-foreground">{deleteConfirmUnit?.name}</span>? Units referenced by products cannot be archived.
                    </>
                }
                confirmLabel="Archive Unit"
                onConfirm={() => {
                    if (!deleteConfirmUnit) {
                        return;
                    }
                    form.delete(`/units-of-measure/${deleteConfirmUnit.id}`, {
                        onSuccess: () => setDeleteConfirmUnit(null),
                    });
                }}
                processing={form.processing}
            />
        </>
    );
}

UnitsOfMeasureIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Units of Measure', href: '/units-of-measure' },
    ],
};
