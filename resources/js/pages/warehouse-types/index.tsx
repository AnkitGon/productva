import { Head, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import { Edit2, Plus, Tags, Trash2 } from 'lucide-react';

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

interface WarehouseType {
    id: number;
    code: string;
    name: string;
    description: string | null;
    status: 'Active' | 'Inactive';
    warehouses_count?: number;
}

interface PaginatedTypes {
    data: WarehouseType[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    warehouseTypes: PaginatedTypes;
    statuses: string[];
    filters: Record<string, string>;
}

type FormData = {
    code: string;
    name: string;
    description: string;
    status: string;
};

function emptyForm(): FormData {
    return { code: '', name: '', description: '', status: 'Active' };
}

export default function WarehouseTypesIndex({ warehouseTypes, statuses, filters }: Props) {
    const { can } = useCan();
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editing, setEditing] = useState<WarehouseType | null>(null);
    const [deleteConfirm, setDeleteConfirm] = useState<WarehouseType | null>(null);
    const form = useForm<FormData>(emptyForm());

    const currentParams: Record<string, string> = {};
    Object.entries(filters ?? {}).forEach(([k, v]) => {
        if (v) {
            currentParams[k] = v;
        }
    });

    const openCreate = () => {
        setEditing(null);
        form.setData(emptyForm());
        form.clearErrors();
        setIsDialogOpen(true);
    };

    const openEdit = (row: WarehouseType) => {
        setEditing(row);
        form.setData({
            code: row.code,
            name: row.name,
            description: row.description ?? '',
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
            form.put(`/warehouse-types/${editing.id}`, config);
        } else {
            form.post('/warehouse-types', config);
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
        router.get('/warehouse-types', params, { preserveState: true, replace: true });
    };

    const columns: ColumnDef<WarehouseType>[] = [
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
            key: 'description',
            label: 'Description',
            className: 'text-muted-foreground',
            render: (row) => row.description ?? '—',
        },
        {
            key: 'warehouses_count',
            label: 'Warehouses',
            className: 'text-muted-foreground',
            render: (row) => String(row.warehouses_count ?? 0),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (row) => <StatusBadge status={row.status} />,
        },
    ];

    const meta: TableMeta = {
        current_page: warehouseTypes.current_page,
        last_page: warehouseTypes.last_page,
        per_page: warehouseTypes.per_page,
        total: warehouseTypes.total,
        from: warehouseTypes.from ?? 1,
        to: warehouseTypes.to ?? warehouseTypes.data.length,
    };

    return (
        <>
            <Head title="Warehouse Types" />
            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Warehouse Types</h1>
                    <p className="text-sm text-muted-foreground">
                        Organization-wide types used when creating warehouses (RAW, FG, Quarantine, etc.).
                    </p>
                </div>

                <DataTable
                    tableId="warehouse-types"
                    columns={columns}
                    data={warehouseTypes.data}
                    meta={meta}
                    baseUrl="/warehouse-types"
                    currentParams={currentParams}
                    searchPlaceholder="Search by code, name, description…"
                    entityLabel="warehouse types"
                    emptyStateIcon={Tags}
                    emptyStateTitle="No warehouse types found"
                    emptyStateDescription={
                        can('warehouses.create')
                            ? 'Create a warehouse type for this organization.'
                            : 'No warehouse types are available.'
                    }
                    emptyStateAction={
                        can('warehouses.create') ? (
                            <Button size="sm" onClick={openCreate} className="gap-2">
                                <Plus className="size-3.5" />
                                Create Type
                            </Button>
                        ) : undefined
                    }
                    primaryAction={
                        can('warehouses.create') ? (
                            <Button onClick={openCreate} className="gap-2 h-9 font-semibold text-xs">
                                <Plus className="size-4" />
                                Add Type
                            </Button>
                        ) : undefined
                    }
                    filterSlot={
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
                    }
                    rowActions={(row) => {
                        const actions: RowAction<WarehouseType>[] = [];
                        if (can('warehouses.update')) {
                            actions.push({ label: 'Edit', icon: Edit2, onClick: openEdit });
                        }
                        if (can('warehouses.delete') && (row.warehouses_count ?? 0) === 0) {
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

            <Dialog open={isDialogOpen} onOpenChange={(open) => { setIsDialogOpen(open); if (!open) { setEditing(null); form.reset(); } }}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Edit Warehouse Type' : 'Add Warehouse Type'}</DialogTitle>
                        <DialogDescription className="text-xs">Shared across all plants in the organization.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">

                        <div className="space-y-2">
                            <Label>Name <span className="text-destructive">*</span></Label>
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                            <InputError message={form.errors.name} />
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
                            <Label>Description</Label>
                            <textarea
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                                rows={3}
                                className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            />
                            <InputError message={form.errors.description} />
                        </div>
                        <ModalButtons onCancel={() => setIsDialogOpen(false)} processing={form.processing} />
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDeleteDialog
                open={deleteConfirm !== null}
                onOpenChange={(open) => !open && setDeleteConfirm(null)}
                title="Archive Warehouse Type?"
                description={<>Archive <span className="font-semibold">{deleteConfirm?.name}</span>? Types with warehouses cannot be archived.</>}
                confirmLabel="Archive Type"
                onConfirm={() => {
                    if (!deleteConfirm) return;
                    form.delete(`/warehouse-types/${deleteConfirm.id}`, { onSuccess: () => setDeleteConfirm(null) });
                }}
                processing={form.processing}
            />
        </>
    );
}

WarehouseTypesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Warehouse Types', href: '/warehouse-types' },
    ],
};
