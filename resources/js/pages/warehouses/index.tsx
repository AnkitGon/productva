import { Head, router, useForm } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';
import { Edit2, Plus, Trash2, Warehouse as WarehouseIcon } from 'lucide-react';

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
import { Checkbox } from '@/components/ui/checkbox';
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
import { UserSearchSelect } from '@/components/user-search-select';
import { DataTable, type ColumnDef, type TableMeta } from '@/components/data-table/data-table';
import { StatusBadge } from '@/components/data-table/status-badge';
import type { RowAction } from '@/components/data-table/data-table-row-actions';
import { useCan } from '@/hooks/use-can';

interface TypeOption {
    id: number;
    code: string;
    name: string;
    status: string;
}

interface ManagerOption {
    id: number;
    first_name: string;
    last_name: string;
    display_name: string | null;
    employee_code: string;
    user?: { id: number; name: string; email: string } | null;
}

interface WarehouseRow {
    id: number;
    code: string;
    name: string;
    phone: string | null;
    email: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    state: string | null;
    postal_code: string | null;
    country: string | null;
    allow_negative_stock: boolean;
    is_default: boolean;
    notes: string | null;
    status: string;
    warehouse_type_id: number;
    manager_employee_id: number | null;
    plant?: { id: number; name: string; code: string } | null;
    warehouse_type?: TypeOption | null;
    manager?: ManagerOption | null;
}

interface PaginatedWarehouses {
    data: WarehouseRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    warehouses: PaginatedWarehouses;
    warehouseTypes: TypeOption[];
    managers: ManagerOption[];
    statuses: string[];
    plant: { id: number; name: string; code: string } | null;
    filters: Record<string, string>;
}

type FormData = {
    warehouse_type_id: string;
    code: string;
    name: string;
    manager_employee_id: string;
    phone: string;
    email: string;
    address_line_1: string;
    address_line_2: string;
    city: string;
    state: string;
    postal_code: string;
    country: string;
    allow_negative_stock: boolean;
    is_default: boolean;
    notes: string;
    status: string;
};

function managerLabel(manager: ManagerOption): string {
    if (manager.user) {
        return `${manager.user.name} (${manager.user.email})`;
    }
    const name = manager.display_name
        || `${manager.first_name} ${manager.last_name}`.trim()
        || 'Employee';
    return `${name} (${manager.employee_code})`;
}

function emptyForm(): FormData {
    return {
        warehouse_type_id: '',
        code: '',
        name: '',
        manager_employee_id: '',
        phone: '',
        email: '',
        address_line_1: '',
        address_line_2: '',
        city: '',
        state: '',
        postal_code: '',
        country: '',
        allow_negative_stock: false,
        is_default: false,
        notes: '',
        status: 'Active',
    };
}

function formFromWarehouse(row: WarehouseRow): FormData {
    return {
        warehouse_type_id: row.warehouse_type_id?.toString() ?? '',
        code: row.code,
        name: row.name,
        manager_employee_id: row.manager_employee_id?.toString() ?? '',
        phone: row.phone ?? '',
        email: row.email ?? '',
        address_line_1: row.address_line_1 ?? '',
        address_line_2: row.address_line_2 ?? '',
        city: row.city ?? '',
        state: row.state ?? '',
        postal_code: row.postal_code ?? '',
        country: row.country ?? '',
        allow_negative_stock: row.allow_negative_stock,
        is_default: row.is_default,
        notes: row.notes ?? '',
        status: row.status,
    };
}

export default function WarehousesIndex({
    warehouses,
    warehouseTypes,
    managers,
    statuses,
    plant,
    filters,
}: Props) {
    const { can } = useCan();
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editing, setEditing] = useState<WarehouseRow | null>(null);
    const [deleteConfirm, setDeleteConfirm] = useState<WarehouseRow | null>(null);
    const [selectedManagerLabel, setSelectedManagerLabel] = useState('');
    const form = useForm<FormData>(emptyForm());

    const currentParams: Record<string, string> = {};
    Object.entries(filters ?? {}).forEach(([k, v]) => {
        if (v) {
            currentParams[k] = v;
        }
    });

    const selectableTypes = useMemo(() => {
        return warehouseTypes.filter(
            (type) => type.status === 'Active' || type.id === editing?.warehouse_type_id,
        );
    }, [warehouseTypes, editing]);

    const openCreate = () => {
        setEditing(null);
        form.setData(emptyForm());
        setSelectedManagerLabel('');
        form.clearErrors();
        setIsDialogOpen(true);
    };

    const openEdit = (row: WarehouseRow) => {
        setEditing(row);
        form.setData(formFromWarehouse(row));
        setSelectedManagerLabel(row.manager ? managerLabel(row.manager) : '');
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
            form.put(`/warehouses/${editing.id}`, config);
        } else {
            form.post('/warehouses', config);
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
        router.get('/warehouses', params, { preserveState: true, replace: true });
    };

    const columns: ColumnDef<WarehouseRow>[] = [
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
            render: (row) => <span className="font-semibold">{row.name}</span>,
        },
        {
            key: 'plant',
            label: 'Plant',
            className: 'text-muted-foreground',
            render: (row) => row.plant?.name ?? plant?.name ?? '—',
        },
        {
            key: 'warehouse_type',
            label: 'Type',
            className: 'text-muted-foreground',
            render: (row) => row.warehouse_type?.name ?? '—',
        },
        {
            key: 'manager',
            label: 'Manager',
            className: 'text-muted-foreground',
            render: (row) => (row.manager ? managerLabel(row.manager) : '—'),
        },
        {
            key: 'is_default',
            label: 'Default',
            sortable: true,
            render: (row) => (row.is_default ? <span className="text-xs font-semibold text-emerald-600">Yes</span> : <span className="text-muted-foreground">—</span>),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (row) => <StatusBadge status={row.status} />,
        },
    ];

    const meta: TableMeta = {
        current_page: warehouses.current_page,
        last_page: warehouses.last_page,
        per_page: warehouses.per_page,
        total: warehouses.total,
        from: warehouses.from ?? 1,
        to: warehouses.to ?? warehouses.data.length,
    };

    const filterSlot = (
        <>
            <Select value={currentParams.warehouse_type_id ?? 'all'} onValueChange={(v) => makeFilterChange('warehouse_type_id', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[140px] border-dashed">
                    <SelectValue placeholder="Type" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Types</SelectItem>
                    {warehouseTypes.map((type) => (
                        <SelectItem key={type.id} value={type.id.toString()}>
                            {type.code} — {type.name}
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
                        <SelectItem key={status} value={status}>{status}</SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select value={currentParams.manager_employee_id ?? 'all'} onValueChange={(v) => makeFilterChange('manager_employee_id', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[140px] border-dashed">
                    <SelectValue placeholder="Manager" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Managers</SelectItem>
                    {managers.map((manager) => (
                        <SelectItem key={manager.id} value={manager.id.toString()}>
                            {managerLabel(manager)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </>
    );

    return (
        <>
            <Head title="Warehouses" />
            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Warehouses</h1>
                    <p className="text-sm text-muted-foreground">
                        Storage areas for the active plant{plant ? ` (${plant.name})` : ''}.
                    </p>
                </div>

                <DataTable
                    tableId="warehouses"
                    columns={columns}
                    data={warehouses.data}
                    meta={meta}
                    baseUrl="/warehouses"
                    currentParams={currentParams}
                    searchPlaceholder="Search code, name, manager, email…"
                    entityLabel="warehouses"
                    emptyStateIcon={WarehouseIcon}
                    emptyStateTitle="No warehouses found"
                    emptyStateDescription={
                        can('warehouses.create')
                            ? 'Create your first warehouse for the active plant.'
                            : 'No warehouses are available for the active plant.'
                    }
                    emptyStateAction={
                        can('warehouses.create') ? (
                            <Button size="sm" onClick={openCreate} className="gap-2">
                                <Plus className="size-3.5" />
                                Create Warehouse
                            </Button>
                        ) : undefined
                    }
                    primaryAction={
                        can('warehouses.create') ? (
                            <Button onClick={openCreate} className="gap-2 h-9 font-semibold text-xs">
                                <Plus className="size-4" />
                                Add Warehouse
                            </Button>
                        ) : undefined
                    }
                    filterSlot={filterSlot}
                    rowActions={() => {
                        const actions: RowAction<WarehouseRow>[] = [];
                        if (can('warehouses.update')) {
                            actions.push({ label: 'Edit', icon: Edit2, onClick: openEdit });
                        }
                        if (can('warehouses.delete')) {
                            actions.push({
                                label: 'Archive',
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

            <Dialog
                open={isDialogOpen}
                onOpenChange={(open) => {
                    setIsDialogOpen(open);
                    if (!open) {
                        setEditing(null);
                        setSelectedManagerLabel('');
                        form.reset();
                    }
                }}
            >
                <DialogContent className="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Edit Warehouse' : 'Add Warehouse'}</DialogTitle>
                        <DialogDescription className="text-xs">
                            Warehouses are scoped to the active plant{plant ? ` (${plant.name})` : ''}. Plant cannot be changed after creation.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="space-y-3">
                            <h3 className="text-sm font-semibold">General</h3>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-2 sm:col-span-2">
                                    <Label>Plant</Label>
                                    <Input value={plant ? `${plant.code} — ${plant.name}` : 'Active plant'} disabled />
                                </div>
                                <div className="space-y-2">
                                    <Label>Warehouse Type <span className="text-destructive">*</span></Label>
                                    <Select
                                        value={form.data.warehouse_type_id || undefined}
                                        onValueChange={(v) => form.setData('warehouse_type_id', v)}
                                    >
                                        <SelectTrigger className="w-full"><SelectValue placeholder="Select type" /></SelectTrigger>
                                        <SelectContent>
                                            {selectableTypes.map((type) => (
                                                <SelectItem key={type.id} value={type.id.toString()}>
                                                    {type.code} — {type.name}
                                                    {type.status !== 'Active' ? ' (Inactive)' : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={form.errors.warehouse_type_id} />
                                </div>
                                <div className="space-y-2">
                                    <Label>Status</Label>
                                    <Select value={form.data.status} onValueChange={(v) => form.setData('status', v)}>
                                        <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            {statuses.map((status) => (
                                                <SelectItem key={status} value={status}>{status}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={form.errors.status} />
                                </div>
                                <div className="space-y-2">
                                    <Label>Warehouse Code <span className="text-destructive">*</span></Label>
                                    <Input
                                        value={form.data.code}
                                        onChange={(e) => form.setData('code', e.target.value.toUpperCase())}
                                        maxLength={50}
                                        required
                                    />
                                    <InputError message={form.errors.code} />
                                </div>
                                <div className="space-y-2">
                                    <Label>Warehouse Name <span className="text-destructive">*</span></Label>
                                    <Input
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        required
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <Label>Warehouse Manager</Label>
                                    <UserSearchSelect
                                        value={form.data.manager_employee_id}
                                        selectedLabel={selectedManagerLabel}
                                        placeholder="Search users…"
                                        withEmployee
                                        onChange={(val, option) => {
                                            form.setData('manager_employee_id', val);
                                            setSelectedManagerLabel(option?.label ?? '');
                                        }}
                                    />
                                    <InputError message={form.errors.manager_employee_id} />
                                </div>
                                <div className="flex items-start gap-3 rounded-lg border border-border/60 px-3 py-2.5">
                                    <Checkbox
                                        id="allow_negative_stock"
                                        checked={form.data.allow_negative_stock}
                                        onCheckedChange={(c) => form.setData('allow_negative_stock', c === true)}
                                    />
                                    <Label htmlFor="allow_negative_stock">Allow Negative Stock</Label>
                                </div>
                                <div className="flex items-start gap-3 rounded-lg border border-border/60 px-3 py-2.5">
                                    <Checkbox
                                        id="is_default"
                                        checked={form.data.is_default}
                                        onCheckedChange={(c) => form.setData('is_default', c === true)}
                                    />
                                    <Label htmlFor="is_default">Default Warehouse</Label>
                                </div>
                            </div>
                        </div>

                        <div className="space-y-3">
                            <h3 className="text-sm font-semibold">Contact</h3>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label>Phone</Label>
                                    <Input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} maxLength={30} />
                                    <InputError message={form.errors.phone} />
                                </div>
                                <div className="space-y-2">
                                    <Label>Email</Label>
                                    <Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                                    <InputError message={form.errors.email} />
                                </div>
                            </div>
                        </div>

                        <div className="space-y-3">
                            <h3 className="text-sm font-semibold">Address</h3>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-2 sm:col-span-2">
                                    <Label>Address Line 1</Label>
                                    <Input value={form.data.address_line_1} onChange={(e) => form.setData('address_line_1', e.target.value)} />
                                    <InputError message={form.errors.address_line_1} />
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <Label>Address Line 2</Label>
                                    <Input value={form.data.address_line_2} onChange={(e) => form.setData('address_line_2', e.target.value)} />
                                    <InputError message={form.errors.address_line_2} />
                                </div>
                                <div className="space-y-2">
                                    <Label>City</Label>
                                    <Input value={form.data.city} onChange={(e) => form.setData('city', e.target.value)} />
                                    <InputError message={form.errors.city} />
                                </div>
                                <div className="space-y-2">
                                    <Label>State</Label>
                                    <Input value={form.data.state} onChange={(e) => form.setData('state', e.target.value)} />
                                    <InputError message={form.errors.state} />
                                </div>
                                <div className="space-y-2">
                                    <Label>Postal Code</Label>
                                    <Input value={form.data.postal_code} onChange={(e) => form.setData('postal_code', e.target.value)} />
                                    <InputError message={form.errors.postal_code} />
                                </div>
                                <div className="space-y-2">
                                    <Label>Country</Label>
                                    <Input value={form.data.country} onChange={(e) => form.setData('country', e.target.value)} />
                                    <InputError message={form.errors.country} />
                                </div>
                            </div>
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

                        <ModalButtons onCancel={() => setIsDialogOpen(false)} processing={form.processing} />
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDeleteDialog
                open={deleteConfirm !== null}
                onOpenChange={(open) => !open && setDeleteConfirm(null)}
                title="Archive Warehouse?"
                description={
                    <>
                        Archive <span className="font-semibold">{deleteConfirm?.name}</span>? Warehouses with inventory or history are marked inactive instead.
                    </>
                }
                confirmLabel="Archive Warehouse"
                onConfirm={() => {
                    if (!deleteConfirm) return;
                    form.delete(`/warehouses/${deleteConfirm.id}`, { onSuccess: () => setDeleteConfirm(null) });
                }}
                processing={form.processing}
            />
        </>
    );
}

WarehousesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Warehouses', href: '/warehouses' },
    ],
};
