import { Head, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import { CornerDownRight, Edit2, MapPin, Plus, Trash2 } from 'lucide-react';

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

export interface WarehouseOption {
    id: number;
    code: string;
    name: string;
}

export interface LocationParentOption {
    id: number;
    warehouse_id: number;
    parent_id: number | null;
    type: string;
    code: string;
    name: string;
}

export interface WarehouseLocationItem {
    id: number;
    warehouse_id: number;
    parent_id: number | null;
    type: string;
    code: string;
    name: string;
    barcode: string | null;
    status: 'Active' | 'Inactive';
    full_path: string;
    warehouse: {
        id: number;
        code: string;
        name: string;
    };
    parent?: {
        id: number;
        code: string;
        name: string;
        type: string;
    } | null;
    children?: { id: number; parent_id: number | null }[];
    depth?: number;
}

interface PaginatedLocations {
    data: WarehouseLocationItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface Props {
    locations: PaginatedLocations;
    warehouses: WarehouseOption[];
    parentOptions: LocationParentOption[];
    types: string[];
    statuses: string[];
    plant?: { id: number; name: string; code: string } | null;
    filters: Record<string, string>;
}

type FormData = {
    warehouse_id: string;
    parent_id: string;
    type: string;
    code: string;
    name: string;
    barcode: string;
    status: string;
};

function emptyForm(defaultWarehouseId: string = ''): FormData {
    return {
        warehouse_id: defaultWarehouseId,
        parent_id: 'none',
        type: 'Zone',
        code: '',
        name: '',
        barcode: '',
        status: 'Active',
    };
}

function typeBadgeStyles(type: string): string {
    switch (type) {
        case 'Zone':
            return 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-800';
        case 'Aisle':
            return 'bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-200 dark:border-purple-800';
        case 'Rack':
            return 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800';
        case 'Shelf':
            return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800';
        case 'Bin':
            return 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800';
        default:
            return 'bg-muted text-muted-foreground';
    }
}

export default function WarehouseLocationsIndex({
    locations,
    warehouses,
    parentOptions,
    types,
    statuses,
    plant,
    filters,
}: Props) {
    const { can } = useCan();
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editing, setEditing] = useState<WarehouseLocationItem | null>(null);
    const [deleteConfirm, setDeleteConfirm] = useState<WarehouseLocationItem | null>(null);

    const defaultWarehouseId = warehouses[0]?.id.toString() ?? '';
    const form = useForm<FormData>(emptyForm(defaultWarehouseId));

    const currentParams: Record<string, string> = {};
    Object.entries(filters ?? {}).forEach(([k, v]) => {
        if (v) {
            currentParams[k] = v;
        }
    });

    const openCreate = (warehouseId?: number, parentId?: number) => {
        setEditing(null);
        const targetWarehouse = warehouseId?.toString() ?? defaultWarehouseId;
        const targetParent = parentId?.toString() ?? 'none';

        // Auto suggest next child type if parent is chosen
        let targetType = 'Zone';
        if (parentId) {
            const parentLoc = parentOptions.find((p) => p.id === parentId);
            if (parentLoc) {
                if (parentLoc.type === 'Zone') targetType = 'Aisle';
                else if (parentLoc.type === 'Aisle') targetType = 'Rack';
                else if (parentLoc.type === 'Rack') targetType = 'Shelf';
                else if (parentLoc.type === 'Shelf') targetType = 'Bin';
                else targetType = 'Bin';
            }
        }

        form.setData({
            ...emptyForm(targetWarehouse),
            parent_id: targetParent,
            type: targetType,
        });
        form.clearErrors();
        setIsDialogOpen(true);
    };

    const openEdit = (row: WarehouseLocationItem) => {
        setEditing(row);
        form.setData({
            warehouse_id: row.warehouse_id.toString(),
            parent_id: row.parent_id?.toString() ?? 'none',
            type: row.type,
            code: row.code,
            name: row.name,
            barcode: row.barcode ?? '',
            status: row.status,
        });
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
        if (editing) {
            form.put(`/warehouse-locations/${editing.id}`, config);
        } else {
            form.post('/warehouse-locations', config);
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
        router.get('/warehouse-locations', params, { preserveState: true, replace: true });
    };

    // Filter available parent options based on selected warehouse in modal, excluding self if editing
    const selectedWarehouseIdNum = parseInt(form.data.warehouse_id, 10);
    const validParentOptions = parentOptions.filter((p) => {
        if (p.warehouse_id !== selectedWarehouseIdNum) return false;
        if (editing && p.id === editing.id) return false;
        return true;
    });

    const columns: ColumnDef<WarehouseLocationItem>[] = [
        {
            key: 'code',
            label: 'Code',
            sortable: true,
            render: (row) => (
                <div 
                    className="flex items-center gap-2" 
                    style={{ paddingLeft: `${(row.depth ?? 0) * 16}px` }}
                >
                    {row.parent_id && <CornerDownRight className="size-3.5 text-muted-foreground/60 shrink-0" />}
                    <span className="font-mono text-xs font-semibold">{row.code}</span>
                </div>
            ),
        },
        {
            key: 'name',
            label: 'Location Name',
            sortable: true,
            render: (row) => (
                <div>
                    <span className="font-semibold text-foreground">{row.name}</span>
                    <p className="text-xs text-muted-foreground">{row.full_path}</p>
                </div>
            ),
        },
        {
            key: 'type',
            label: 'Type',
            sortable: true,
            render: (row) => (
                <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${typeBadgeStyles(row.type)}`}>
                    {row.type}
                </span>
            ),
        },
        {
            key: 'warehouse',
            label: 'Warehouse',
            render: (row) => (
                <span className="text-sm font-medium">
                    {row.warehouse?.name} <span className="text-xs text-muted-foreground">({row.warehouse?.code})</span>
                </span>
            ),
        },
        {
            key: 'barcode',
            label: 'Barcode',
            render: (row) => (row.barcode ? <span className="font-mono text-xs text-muted-foreground">{row.barcode}</span> : '—'),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (row) => <StatusBadge status={row.status} />,
        },
        {
            key: 'parent',
            label: 'Parent',
            defaultVisible: false,
            className: 'text-muted-foreground',
            render: (row) =>
                row.parent ? `${row.parent.code} — ${row.parent.name}` : '—',
        },
        {
            key: 'full_path',
            label: 'Full Path',
            defaultVisible: false,
            className: 'text-muted-foreground text-xs',
            render: (row) => row.full_path || '—',
        },
    ];

    const meta: TableMeta = {
        current_page: locations.current_page,
        last_page: locations.last_page,
        per_page: locations.per_page,
        total: locations.total,
        from: locations.from ?? 1,
        to: locations.to ?? locations.data.length,
    };

    return (
        <>
            <Head title="Warehouse Locations" />
            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Warehouse Locations</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage zones, aisles, racks, shelves, and bins for {plant ? <span className="font-medium text-foreground">{plant.name}</span> : 'your active plant'}.
                    </p>
                </div>

                <DataTable
                    tableId="warehouse-locations"
                    columns={columns}
                    data={locations.data}
                    meta={meta}
                    baseUrl="/warehouse-locations"
                    currentParams={currentParams}
                    searchPlaceholder="Search location code, name, barcode…"
                    entityLabel="warehouse locations"
                    emptyStateIcon={MapPin}
                    emptyStateTitle="No warehouse locations found"
                    emptyStateDescription={
                        can('warehouses.create')
                            ? 'Create storage zones, aisles, racks, shelves, or bins to manage inventory locations.'
                            : 'No warehouse locations are available.'
                    }
                    emptyStateAction={
                        can('warehouses.create') ? (
                            <Button size="sm" onClick={() => openCreate()} className="gap-2">
                                <Plus className="size-3.5" />
                                Create Location
                            </Button>
                        ) : undefined
                    }
                    primaryAction={
                        can('warehouses.create') ? (
                            <Button onClick={() => openCreate()} className="gap-2 h-9 font-semibold text-xs">
                                <Plus className="size-4" />
                                Add Location
                            </Button>
                        ) : undefined
                    }
                    filterSlot={
                        <div className="flex flex-wrap items-center gap-2">
                            {/* Warehouse filter */}
                            <Select value={currentParams.warehouse_id ?? 'all'} onValueChange={(v) => makeFilterChange('warehouse_id', v)}>
                                <SelectTrigger className="h-8 text-xs min-w-[130px] border-dashed">
                                    <SelectValue placeholder="Warehouse" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Warehouses</SelectItem>
                                    {warehouses.map((w) => (
                                        <SelectItem key={w.id} value={w.id.toString()}>
                                            {w.code} — {w.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            {/* Location Type filter */}
                            <Select value={currentParams.type ?? 'all'} onValueChange={(v) => makeFilterChange('type', v)}>
                                <SelectTrigger className="h-8 text-xs min-w-[110px] border-dashed">
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

                            {/* Status filter */}
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
                        </div>
                    }
                    rowActions={(row) => {
                        const actions: RowAction<WarehouseLocationItem>[] = [];
                        if (can('warehouses.create')) {
                            actions.push({
                                label: 'Add Sub-location',
                                icon: Plus,
                                onClick: (r) => openCreate(r.warehouse_id, r.id),
                            });
                        }
                        if (can('warehouses.update')) {
                            actions.push({ label: 'Edit', icon: Edit2, onClick: openEdit });
                        }
                        if (can('warehouses.delete')) {
                            actions.push({
                                label: 'Delete',
                                icon: Trash2,
                                onClick: (r) => setDeleteConfirm(r),
                                variant: 'destructive',
                                separator: true,
                            });
                        }
                        return actions;
                    }}
                />
            </div>

            {/* Create / Edit Modal */}
            <Dialog open={isDialogOpen} onOpenChange={(open) => { setIsDialogOpen(open); if (!open) { setEditing(null); form.reset(); } }}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Edit Location' : 'Add Location'}</DialogTitle>
                        <DialogDescription className="text-xs">
                            Define storage locations (Zone, Aisle, Rack, Shelf, Bin) with unlimited depth.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="warehouse_id">
                                Warehouse <span className="text-destructive">*</span>
                            </Label>
                            <Select
                                value={form.data.warehouse_id || undefined}
                                onValueChange={(val) => {
                                    form.setData((prev) => ({
                                        ...prev,
                                        warehouse_id: val,
                                        parent_id: 'none', // reset parent when warehouse changes
                                    }));
                                }}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Select warehouse" />
                                </SelectTrigger>
                                <SelectContent>
                                    {warehouses.map((w) => (
                                        <SelectItem key={w.id} value={w.id.toString()}>
                                            {w.code} — {w.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.warehouse_id} />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-2">
                                <Label htmlFor="type">
                                    Location Type <span className="text-destructive">*</span>
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
                                <Label htmlFor="parent_id">Parent Location</Label>
                                <Select
                                    value={form.data.parent_id}
                                    onValueChange={(val) => form.setData('parent_id', val)}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="None (Root)" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">None (Top Level)</SelectItem>
                                        {validParentOptions.map((p) => (
                                            <SelectItem key={p.id} value={p.id.toString()}>
                                                {p.code} — {p.name} ({p.type})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.parent_id} />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">


                            <div className="space-y-2">
                                <Label htmlFor="status">Status</Label>
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
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="name">
                                Name <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                placeholder="e.g. Zone A (Receiving Area)"
                                required
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="barcode">Barcode / Scan Code</Label>
                            <Input
                                id="barcode"
                                value={form.data.barcode}
                                onChange={(e) => form.setData('barcode', e.target.value)}
                                placeholder="e.g. LOC-ZONEA-001"
                            />
                            <InputError message={form.errors.barcode} />
                        </div>

                        <ModalButtons
                            onCancel={() => setIsDialogOpen(false)}
                            processing={form.processing}
                            submitText={editing ? 'Save Changes' : 'Create Location'}
                        />
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDeleteDialog
                open={!!deleteConfirm}
                onOpenChange={(open) => { if (!open) setDeleteConfirm(null); }}
                onConfirm={() => {
                    if (deleteConfirm) {
                        router.delete(`/warehouse-locations/${deleteConfirm.id}`, {
                            onSuccess: () => setDeleteConfirm(null),
                        });
                    }
                }}
                title="Delete Location"
                description={`Are you sure you want to delete location "${deleteConfirm?.code}" (${deleteConfirm?.name})?`}
            />
        </>
    );
}

WarehouseLocationsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Warehouses', href: '/warehouses' },
        { title: 'Locations', href: '/warehouse-locations' },
    ],
};
