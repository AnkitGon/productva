import { Head, router, useForm } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';
import { Cog, Edit2, Plus, Trash2 } from 'lucide-react';

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

interface DepartmentOption {
    id: number;
    name: string;
}

interface WorkCenterOption {
    id: number;
    name: string;
    code: string;
    department_id: number;
}

interface Machine {
    id: number;
    code: string;
    name: string;
    manufacturer: string | null;
    model: string | null;
    serial_number: string | null;
    asset_tag: string | null;
    installation_date: string | null;
    purchase_date: string | null;
    capacity: string | number | null;
    capacity_unit: string | null;
    status: string;
    notes: string | null;
    department?: DepartmentOption | null;
    work_center?: WorkCenterOption | null;
}

interface PaginatedMachines {
    data: Machine[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    machines: PaginatedMachines;
    departments: DepartmentOption[];
    workCenters: WorkCenterOption[];
    manufacturers: string[];
    statuses: string[];
    filters: Record<string, string>;
}

type MachineFormData = {
    department_id: string;
    work_center_id: string;
    code: string;
    name: string;
    manufacturer: string;
    model: string;
    serial_number: string;
    asset_tag: string;
    purchase_date: string;
    installation_date: string;
    capacity: string;
    capacity_unit: string;
    status: string;
    notes: string;
};

function emptyForm(): MachineFormData {
    return {
        department_id: '',
        work_center_id: '',
        code: '',
        name: '',
        manufacturer: '',
        model: '',
        serial_number: '',
        asset_tag: '',
        purchase_date: '',
        installation_date: '',
        capacity: '',
        capacity_unit: '',
        status: 'Active',
        notes: '',
    };
}

function dateInputValue(value: string | null): string {
    if (!value) {
        return '';
    }

    return value.slice(0, 10);
}

function formFromMachine(machine: Machine): MachineFormData {
    return {
        department_id: machine.department?.id?.toString() ?? '',
        work_center_id: machine.work_center?.id?.toString() ?? '',
        code: machine.code,
        name: machine.name,
        manufacturer: machine.manufacturer ?? '',
        model: machine.model ?? '',
        serial_number: machine.serial_number ?? '',
        asset_tag: machine.asset_tag ?? '',
        purchase_date: dateInputValue(machine.purchase_date),
        installation_date: dateInputValue(machine.installation_date),
        capacity: machine.capacity !== null && machine.capacity !== undefined
            ? String(machine.capacity)
            : '',
        capacity_unit: machine.capacity_unit ?? '',
        status: machine.status,
        notes: machine.notes ?? '',
    };
}

export default function MachinesIndex({
    machines,
    departments,
    workCenters,
    manufacturers,
    statuses,
    filters,
}: Props) {
    const { can } = useCan();
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingMachine, setEditingMachine] = useState<Machine | null>(null);
    const [deleteConfirmMachine, setDeleteConfirmMachine] = useState<Machine | null>(null);

    const form = useForm<MachineFormData>(emptyForm());

    const currentParams: Record<string, string> = {};
    Object.entries(filters ?? {}).forEach(([k, v]) => {
        if (v) {
            currentParams[k] = v;
        }
    });

    const filteredWorkCenters = useMemo(() => {
        if (!form.data.department_id) {
            return [];
        }

        return workCenters.filter(
            (workCenter) => workCenter.department_id.toString() === form.data.department_id,
        );
    }, [form.data.department_id, workCenters]);

    const filterWorkCenters = useMemo(() => {
        if (!currentParams.department_id) {
            return workCenters;
        }

        return workCenters.filter(
            (workCenter) => workCenter.department_id.toString() === currentParams.department_id,
        );
    }, [currentParams.department_id, workCenters]);

    const openCreate = () => {
        setEditingMachine(null);
        form.setData(emptyForm());
        form.clearErrors();
        setIsDialogOpen(true);
    };

    const openEdit = (machine: Machine) => {
        setEditingMachine(machine);
        form.setData(formFromMachine(machine));
        form.clearErrors();
        setIsDialogOpen(true);
    };

    const handleDepartmentChange = (departmentId: string) => {
        form.setData((data) => ({
            ...data,
            department_id: departmentId,
            work_center_id: '',
        }));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const config = {
            onSuccess: () => {
                setIsDialogOpen(false);
                form.reset();
            },
        };

        if (editingMachine) {
            form.put(`/machines/${editingMachine.id}`, config);
        } else {
            form.post('/machines', config);
        }
    };

    const makeFilterChange = (key: string, val: string) => {
        const params = { ...currentParams };
        if (val === 'all' || val === '') {
            delete params[key];
        } else {
            params[key] = val;
        }

        if (key === 'department_id') {
            delete params.work_center_id;
        }

        delete params.page;
        router.get('/machines', params, { preserveState: true, replace: true });
    };

    const columns: ColumnDef<Machine>[] = [
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
            key: 'work_center',
            label: 'Work Center',
            className: 'text-muted-foreground',
            render: (row) => row.work_center?.name ?? '—',
        },
        {
            key: 'manufacturer',
            label: 'Manufacturer',
            sortable: true,
            className: 'text-muted-foreground',
            render: (row) => row.manufacturer ?? '—',
        },
        {
            key: 'model',
            label: 'Model',
            sortable: true,
            className: 'text-muted-foreground',
            render: (row) => row.model ?? '—',
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (row) => <StatusBadge status={row.status} />,
        },
    ];

    const meta: TableMeta = {
        current_page: machines.current_page,
        last_page: machines.last_page,
        per_page: machines.per_page,
        total: machines.total,
        from: machines.from ?? 1,
        to: machines.to ?? machines.data.length,
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

            <Select value={currentParams.work_center_id ?? 'all'} onValueChange={(v) => makeFilterChange('work_center_id', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[140px] border-dashed">
                    <SelectValue placeholder="Work Center" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Work Centers</SelectItem>
                    {filterWorkCenters.map((workCenter) => (
                        <SelectItem key={workCenter.id} value={workCenter.id.toString()}>
                            {workCenter.name}
                        </SelectItem>
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
                        <SelectItem key={status} value={status}>
                            {status}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select value={currentParams.manufacturer ?? 'all'} onValueChange={(v) => makeFilterChange('manufacturer', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[130px] border-dashed">
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
        </>
    );

    return (
        <>
            <Head title="Machines" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Machines</h1>
                        <p className="text-sm text-muted-foreground">
                            Manage equipment assigned to work centers in the active plant.
                        </p>
                    </div>
                </div>

                <DataTable
                    tableId="machines"
                    columns={columns}
                    data={machines.data}
                    meta={meta}
                    baseUrl="/machines"
                    currentParams={currentParams}
                    searchPlaceholder="Search code, name, manufacturer, model, asset tag, serial…"
                    entityLabel="machines"
                    emptyStateIcon={Cog}
                    emptyStateTitle="No machines found"
                    emptyStateDescription={
                        can('machine.create')
                            ? 'Create your first machine for the active plant.'
                            : 'No machines are available for the active plant.'
                    }
                    emptyStateAction={
                        can('machine.create') ? (
                            <Button size="sm" onClick={openCreate} className="gap-2">
                                <Plus className="size-3.5" />
                                Create Machine
                            </Button>
                        ) : undefined
                    }
                    primaryAction={
                        can('machine.create') ? (
                            <Button onClick={openCreate} className="gap-2 h-9 font-semibold text-xs">
                                <Plus className="size-4" />
                                Add Machine
                            </Button>
                        ) : undefined
                    }
                    filterSlot={filterSlot}
                    rowActions={(row) => {
                        const actions: RowAction<Machine>[] = [];

                        if (can('machine.update')) {
                            actions.push({
                                label: 'Edit',
                                icon: Edit2,
                                onClick: openEdit,
                            });
                        }

                        if (can('machine.delete')) {
                            actions.push({
                                label: 'Archive',
                                icon: Trash2,
                                onClick: (r) => setDeleteConfirmMachine(r),
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
                        setEditingMachine(null);
                        form.reset();
                    }
                }}
            >
                <DialogContent className="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-bold">
                            {editingMachine ? 'Edit Machine' : 'Add Machine'}
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            Machines are scoped to the active plant. Choose a department, then a work center in that department.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>
                                    Department <span className="text-destructive">*</span>
                                </Label>
                                <Select
                                    value={form.data.department_id || undefined}
                                    onValueChange={handleDepartmentChange}
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
                                <Label>
                                    Work Center <span className="text-destructive">*</span>
                                </Label>
                                <Select
                                    value={form.data.work_center_id || undefined}
                                    onValueChange={(val) => form.setData('work_center_id', val)}
                                    disabled={!form.data.department_id}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder={form.data.department_id ? 'Select work center' : 'Select department first'} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {filteredWorkCenters.map((workCenter) => (
                                            <SelectItem key={workCenter.id} value={workCenter.id.toString()}>
                                                {workCenter.code} — {workCenter.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.work_center_id} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="code">
                                    Machine Code <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="code"
                                    value={form.data.code}
                                    onChange={(e) => form.setData('code', e.target.value.toUpperCase())}
                                    placeholder="MCH-01"
                                    maxLength={20}
                                    required
                                />
                                <InputError message={form.errors.code} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="name">
                                    Machine Name <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    placeholder="Haas VF-2"
                                    required
                                    autoFocus
                                />
                                <InputError message={form.errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="manufacturer">Manufacturer</Label>
                                <Input
                                    id="manufacturer"
                                    value={form.data.manufacturer}
                                    onChange={(e) => form.setData('manufacturer', e.target.value)}
                                    placeholder="Haas, Fanuc…"
                                />
                                <InputError message={form.errors.manufacturer} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="model">Model</Label>
                                <Input
                                    id="model"
                                    value={form.data.model}
                                    onChange={(e) => form.setData('model', e.target.value)}
                                    placeholder="VF-2"
                                />
                                <InputError message={form.errors.model} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="serial_number">Serial Number</Label>
                                <Input
                                    id="serial_number"
                                    value={form.data.serial_number}
                                    onChange={(e) => form.setData('serial_number', e.target.value)}
                                    placeholder="Optional, unique in org"
                                />
                                <InputError message={form.errors.serial_number} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="asset_tag">Asset Tag</Label>
                                <Input
                                    id="asset_tag"
                                    value={form.data.asset_tag}
                                    onChange={(e) => form.setData('asset_tag', e.target.value)}
                                    placeholder="Internal asset ID"
                                />
                                <InputError message={form.errors.asset_tag} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="purchase_date">Purchase Date</Label>
                                <Input
                                    id="purchase_date"
                                    type="date"
                                    value={form.data.purchase_date}
                                    onChange={(e) => form.setData('purchase_date', e.target.value)}
                                />
                                <InputError message={form.errors.purchase_date} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="installation_date">Installation Date</Label>
                                <Input
                                    id="installation_date"
                                    type="date"
                                    value={form.data.installation_date}
                                    onChange={(e) => form.setData('installation_date', e.target.value)}
                                />
                                <InputError message={form.errors.installation_date} />
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
                                    placeholder="e.g. 50"
                                />
                                <InputError message={form.errors.capacity} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="capacity_unit">Capacity Unit</Label>
                                <Input
                                    id="capacity_unit"
                                    value={form.data.capacity_unit}
                                    onChange={(e) => form.setData('capacity_unit', e.target.value)}
                                    placeholder="pcs/hr, kg/hr…"
                                />
                                <InputError message={form.errors.capacity_unit} />
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
                                <Label htmlFor="notes">Notes</Label>
                                <textarea
                                    id="notes"
                                    value={form.data.notes}
                                    onChange={(e) => form.setData('notes', e.target.value)}
                                    rows={3}
                                    placeholder="Optional notes about this machine"
                                    className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <InputError message={form.errors.notes} />
                            </div>
                        </div>

                        <ModalButtons onCancel={() => setIsDialogOpen(false)} processing={form.processing} />
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDeleteDialog
                open={deleteConfirmMachine !== null}
                onOpenChange={(open) => !open && setDeleteConfirmMachine(null)}
                title="Archive Machine?"
                description={
                    <>
                        Archive <span className="font-semibold text-foreground">{deleteConfirmMachine?.name}</span>? Machines with production history cannot be archived.
                    </>
                }
                confirmLabel="Archive Machine"
                onConfirm={() => {
                    if (!deleteConfirmMachine) {
                        return;
                    }
                    form.delete(`/machines/${deleteConfirmMachine.id}`, {
                        onSuccess: () => setDeleteConfirmMachine(null),
                    });
                }}
                processing={form.processing}
            />
        </>
    );
}

MachinesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Machines', href: '/machines' },
    ],
};
