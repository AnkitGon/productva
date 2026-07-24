import { Head, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import { Edit2, ListOrdered, Plus, Trash2 } from 'lucide-react';

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

interface OperationRow {
    id: number;
    code: string;
    name: string;
    type: string;
    description: string | null;
    status: string;
}

interface PaginatedOperations {
    data: OperationRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    operations: PaginatedOperations;
    statuses: string[];
    types: string[];
    filters: Record<string, string>;
}

type FormData = {
    code: string;
    name: string;
    type: string;
    description: string;
    status: string;
};

function emptyForm(): FormData {
    return { code: '', name: '', type: 'Manufacturing', description: '', status: 'Active' };
}

export default function OperationsIndex({ operations, statuses, types, filters }: Props) {
    const { can } = useCan();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<OperationRow | null>(null);
    const [deleteConfirm, setDeleteConfirm] = useState<OperationRow | null>(null);
    const form = useForm<FormData>(emptyForm());
    const destroyForm = useForm({});

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
        router.get('/operations', params, { preserveState: true, replace: true });
    };

    const openCreate = () => {
        setEditing(null);
        form.setData(emptyForm());
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (row: OperationRow) => {
        setEditing(row);
        form.setData({
            code: row.code,
            name: row.name,
            type: row.type || 'Manufacturing',
            description: row.description ?? '',
            status: row.status,
        });
        form.clearErrors();
        setOpen(true);
    };

    const columns: ColumnDef<OperationRow>[] = [
        {
            key: 'code',
            label: 'Code',
            sortable: true,
            render: (row) => <span className="font-mono text-sm font-semibold">{row.code}</span>,
        },
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (row) => (
                <div>
                    <span className="font-semibold">{row.code} - {row.name}</span>
                </div>
            ),
        },
        {
            key: 'type',
            label: 'Type',
            sortable: true,
            className: 'text-muted-foreground text-sm',
            render: (row) => row.type,
        },
        {
            key: 'description',
            label: 'Description',
            className: 'text-muted-foreground text-sm',
            render: (row) => row.description || '—',
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (row) => <StatusBadge status={row.status} />,
        },
    ];

    const meta: TableMeta = {
        current_page: operations.current_page,
        last_page: operations.last_page,
        per_page: operations.per_page,
        total: operations.total,
        from: operations.from ?? 1,
        to: operations.to ?? operations.data.length,
    };

    return (
        <>
            <Head title="Operations" />
            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Operations</h1>
                    <p className="text-sm text-muted-foreground">
                        Shared manufacturing operations used when building product routings.
                    </p>
                </div>

                <DataTable
                    tableId="operations"
                    columns={columns}
                    data={operations.data}
                    meta={meta}
                    baseUrl="/operations"
                    currentParams={currentParams}
                    searchPlaceholder="Search code or name…"
                    entityLabel="operations"
                    emptyStateIcon={ListOrdered}
                    emptyStateTitle="No operations yet"
                    emptyStateDescription="Create masters like CUT, WELD, PAINT for consistent routing lines."
                    primaryAction={
                        can('operations.create') ? (
                            <Button className="gap-2 h-9 font-semibold text-xs" onClick={openCreate}>
                                <Plus className="size-4" />
                                New Operation
                            </Button>
                        ) : undefined
                    }
                    filterSlot={
                        <>
                            <Select value={currentParams.type ?? 'all'} onValueChange={(v) => makeFilterChange('type', v)}>
                                <SelectTrigger className="h-8 text-xs min-w-[130px] border-dashed">
                                    <SelectValue placeholder="Type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Types</SelectItem>
                                    {types.map((type) => (
                                        <SelectItem key={type} value={type}>{type}</SelectItem>
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
                        </>
                    }
                    rowActions={() => {
                        const actions: RowAction<OperationRow>[] = [];
                        if (can('operations.update')) {
                            actions.push({ label: 'Edit', icon: Edit2, onClick: (row) => openEdit(row) });
                        }
                        if (can('operations.delete')) {
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

            <Dialog open={open} onOpenChange={(v) => { setOpen(v); if (!v) form.reset(); }}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Edit Operation' : 'New Operation'}</DialogTitle>
                        <DialogDescription className="text-xs">
                            Codes should be short and unique (e.g. CUT, WELD, PACK).
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            if (editing) {
                                form.put(`/operations/${editing.id}`, { onSuccess: () => setOpen(false) });
                            } else {
                                form.post('/operations', { onSuccess: () => { setOpen(false); form.reset(); } });
                            }
                        }}
                        className="space-y-4"
                    >

                        <div className="space-y-2">
                            <Label>Name <span className="text-destructive">*</span></Label>
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="space-y-2">
                            <Label>Type <span className="text-destructive">*</span></Label>
                            <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                                <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {types.map((type) => (
                                        <SelectItem key={type} value={type}>{type}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.type} />
                        </div>
                        <div className="space-y-2">
                            <Label>Description</Label>
                            <Input value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                            <InputError message={form.errors.description} />
                        </div>
                        <div className="space-y-2">
                            <Label>Status <span className="text-destructive">*</span></Label>
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
                        <ModalButtons onCancel={() => setOpen(false)} processing={form.processing} />
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDeleteDialog
                open={!!deleteConfirm}
                onOpenChange={(openState) => { if (!openState) setDeleteConfirm(null); }}
                title="Delete operation?"
                description={deleteConfirm ? `Delete ${deleteConfirm.code} — ${deleteConfirm.name}?` : ''}
                processing={destroyForm.processing}
                onConfirm={() => {
                    if (!deleteConfirm) return;
                    destroyForm.delete(`/operations/${deleteConfirm.id}`, {
                        onSuccess: () => setDeleteConfirm(null),
                    });
                }}
            />
        </>
    );
}

OperationsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Operations', href: '/operations' },
    ],
};
