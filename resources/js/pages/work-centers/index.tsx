import { Head, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import { Edit2, Factory, Plus, Trash2 } from 'lucide-react';

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
import { UserSearchSelect } from '@/components/user-search-select';
import { DataTable, type ColumnDef, type TableMeta } from '@/components/data-table/data-table';
import { StatusBadge } from '@/components/data-table/status-badge';
import type { RowAction } from '@/components/data-table/data-table-row-actions';
import { useCan } from '@/hooks/use-can';

interface DepartmentOption {
    id: number;
    name: string;
}

interface SupervisorOption {
    id: number;
    first_name: string;
    last_name: string;
    display_name: string | null;
    employee_code: string;
    name?: string;
    user?: { id: number; name: string; email: string } | null;
}

interface WorkCenter {
    id: number;
    code: string;
    name: string;
    description: string | null;
    capacity: string | number | null;
    capacity_uom: string | null;
    status: 'Active' | 'Inactive';
    machines_count?: number;
    department?: DepartmentOption | null;
    supervisor?: SupervisorOption | null;
}

interface PaginatedWorkCenters {
    data: WorkCenter[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    workCenters: PaginatedWorkCenters;
    departments: DepartmentOption[];
    filters: Record<string, string>;
}

type WorkCenterFormData = {
    department_id: string;
    code: string;
    name: string;
    description: string;
    supervisor_employee_id: string;
    capacity: string;
    capacity_uom: string;
    status: string;
};

function supervisorLabel(supervisor: SupervisorOption): string {
    if (supervisor.user) {
        return `${supervisor.user.name} (${supervisor.user.email})`;
    }

    const name = supervisor.display_name
        || `${supervisor.first_name} ${supervisor.last_name}`.trim()
        || supervisor.name
        || 'Employee';

    return `${name} (${supervisor.employee_code})`;
}

function emptyForm(): WorkCenterFormData {
    return {
        department_id: '',
        code: '',
        name: '',
        description: '',
        supervisor_employee_id: '',
        capacity: '',
        capacity_uom: '',
        status: 'Active',
    };
}

function formFromWorkCenter(workCenter: WorkCenter): WorkCenterFormData {
    return {
        department_id: workCenter.department?.id?.toString() ?? '',
        code: workCenter.code,
        name: workCenter.name,
        description: workCenter.description ?? '',
        supervisor_employee_id: workCenter.supervisor?.id?.toString() ?? '',
        capacity: workCenter.capacity !== null && workCenter.capacity !== undefined
            ? String(workCenter.capacity)
            : '',
        capacity_uom: workCenter.capacity_uom ?? '',
        status: workCenter.status,
    };
}

export default function WorkCentersIndex({ workCenters, departments, filters }: Props) {
    const { can } = useCan();
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingWorkCenter, setEditingWorkCenter] = useState<WorkCenter | null>(null);
    const [deleteConfirmWorkCenter, setDeleteConfirmWorkCenter] = useState<WorkCenter | null>(null);
    const [selectedSupervisorLabel, setSelectedSupervisorLabel] = useState('');

    const form = useForm<WorkCenterFormData>(emptyForm());

    const currentParams: Record<string, string> = {};
    Object.entries(filters ?? {}).forEach(([k, v]) => {
        if (v) {
            currentParams[k] = v;
        }
    });

    const openCreate = () => {
        setEditingWorkCenter(null);
        form.setData(emptyForm());
        setSelectedSupervisorLabel('');
        form.clearErrors();
        setIsDialogOpen(true);
    };

    const openEdit = (workCenter: WorkCenter) => {
        setEditingWorkCenter(workCenter);
        form.setData(formFromWorkCenter(workCenter));
        setSelectedSupervisorLabel(workCenter.supervisor ? supervisorLabel(workCenter.supervisor) : '');
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

        if (editingWorkCenter) {
            form.put(`/work-centers/${editingWorkCenter.id}`, config);
        } else {
            form.post('/work-centers', config);
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
        router.get('/work-centers', params, { preserveState: true, replace: true });
    };

    const columns: ColumnDef<WorkCenter>[] = [
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
            key: 'department',
            label: 'Department',
            className: 'text-muted-foreground',
            render: (row) => row.department?.name ?? '—',
        },
        {
            key: 'supervisor',
            label: 'Supervisor',
            className: 'text-muted-foreground',
            render: (row) => (row.supervisor ? supervisorLabel(row.supervisor) : '—'),
        },
        {
            key: 'machines_count',
            label: 'Machines',
            className: 'text-muted-foreground',
            render: (row) => {
                const count = row.machines_count ?? 0;
                return `${count} ${count === 1 ? 'machine' : 'machines'}`;
            },
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (row) => <StatusBadge status={row.status} />,
        },
    ];

    const meta: TableMeta = {
        current_page: workCenters.current_page,
        last_page: workCenters.last_page,
        per_page: workCenters.per_page,
        total: workCenters.total,
        from: workCenters.from ?? 1,
        to: workCenters.to ?? workCenters.data.length,
    };

    const filterSlot = (
        <>
            <Select value={currentParams.department_id ?? 'all'} onValueChange={(v) => makeFilterChange('department_id', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[140px] border-dashed">
                    <SelectValue placeholder="Department" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Departments</SelectItem>
                    {departments.map((department) => (
                        <SelectItem key={department.id} value={department.id.toString()}>
                            {department.name}
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
                    <SelectItem value="Active">Active</SelectItem>
                    <SelectItem value="Inactive">Inactive</SelectItem>
                </SelectContent>
            </Select>
        </>
    );

    return (
        <>
            <Head title="Work Centers" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Work Centers</h1>
                        <p className="text-sm text-muted-foreground">
                            Define production areas for the active plant where operations are performed.
                        </p>
                    </div>
                </div>

                <DataTable
                    tableId="work-centers"
                    columns={columns}
                    data={workCenters.data}
                    meta={meta}
                    baseUrl="/work-centers"
                    currentParams={currentParams}
                    searchPlaceholder="Search by code, name, description…"
                    entityLabel="work centers"
                    emptyStateIcon={Factory}
                    emptyStateTitle="No work centers found"
                    emptyStateDescription={
                        can('work-centers.create')
                            ? 'Create your first work center for the active plant.'
                            : 'No work centers are available for the active plant.'
                    }
                    emptyStateAction={
                        can('work-centers.create') ? (
                            <Button size="sm" onClick={openCreate} className="gap-2">
                                <Plus className="size-3.5" />
                                Create Work Center
                            </Button>
                        ) : undefined
                    }
                    primaryAction={
                        can('work-centers.create') ? (
                            <Button onClick={openCreate} className="gap-2 h-9 font-semibold text-xs">
                                <Plus className="size-4" />
                                Add Work Center
                            </Button>
                        ) : undefined
                    }
                    filterSlot={filterSlot}
                    rowActions={(row) => {
                        const actions: RowAction<WorkCenter>[] = [];

                        if (can('work-centers.update')) {
                            actions.push({
                                label: 'Edit',
                                icon: Edit2,
                                onClick: openEdit,
                            });
                        }

                        if (can('work-centers.delete') && (row.machines_count ?? 0) === 0) {
                            actions.push({
                                label: 'Archive',
                                icon: Trash2,
                                onClick: (r) => setDeleteConfirmWorkCenter(r),
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
                        setEditingWorkCenter(null);
                        setSelectedSupervisorLabel('');
                        form.reset();
                    }
                }}
            >
                <DialogContent className="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-bold">
                            {editingWorkCenter ? 'Edit Work Center' : 'Add Work Center'}
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            Work centers are scoped to the active plant. Choose a department from that plant.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="space-y-2 sm:col-span-2">
                                <Label>
                                    Department <span className="text-destructive">*</span>
                                </Label>
                                <Select
                                    value={form.data.department_id || undefined}
                                    onValueChange={(val) => form.setData('department_id', val)}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select department" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {departments.map((department) => (
                                            <SelectItem key={department.id} value={department.id.toString()}>
                                                {department.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.department_id} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="code">
                                    Code <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="code"
                                    value={form.data.code}
                                    onChange={(e) => form.setData('code', e.target.value.toUpperCase())}
                                    placeholder="CNC"
                                    maxLength={20}
                                    required
                                />
                                <InputError message={form.errors.code} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="name">
                                    Name <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    placeholder="CNC Machining"
                                    required
                                    autoFocus
                                />
                                <InputError message={form.errors.name} />
                            </div>

                            <div className="space-y-2 sm:col-span-2">
                                <Label>Supervisor</Label>
                                <UserSearchSelect
                                    value={form.data.supervisor_employee_id}
                                    selectedLabel={selectedSupervisorLabel}
                                    placeholder="Search users…"
                                    withEmployee
                                    onChange={(val, option) => {
                                        form.setData('supervisor_employee_id', val);
                                        setSelectedSupervisorLabel(option?.label ?? '');
                                    }}
                                />
                                <InputError message={form.errors.supervisor_employee_id} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="capacity">Capacity</Label>
                                <Input
                                    id="capacity"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={form.data.capacity}
                                    onChange={(e) => form.setData('capacity', e.target.value)}
                                    placeholder="e.g. 100"
                                />
                                <InputError message={form.errors.capacity} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="capacity_uom">Capacity Unit</Label>
                                <Input
                                    id="capacity_uom"
                                    value={form.data.capacity_uom}
                                    onChange={(e) => form.setData('capacity_uom', e.target.value)}
                                    placeholder="e.g. pcs/hour"
                                />
                                <InputError message={form.errors.capacity_uom} />
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
                                        <SelectItem value="Active">Active</SelectItem>
                                        <SelectItem value="Inactive">Inactive</SelectItem>
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
                                    placeholder="Optional notes about this work center"
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
                open={deleteConfirmWorkCenter !== null}
                onOpenChange={(open) => !open && setDeleteConfirmWorkCenter(null)}
                title="Archive Work Center?"
                description={
                    <>
                        Archive <span className="font-semibold text-foreground">{deleteConfirmWorkCenter?.name}</span>? This can only be done when no machines are assigned.
                    </>
                }
                confirmLabel="Archive Work Center"
                onConfirm={() => {
                    if (!deleteConfirmWorkCenter) {
                        return;
                    }
                    form.delete(`/work-centers/${deleteConfirmWorkCenter.id}`, {
                        onSuccess: () => setDeleteConfirmWorkCenter(null),
                    });
                }}
                processing={form.processing}
            />
        </>
    );
}

WorkCentersIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Work Centers', href: '/work-centers' },
    ],
};
