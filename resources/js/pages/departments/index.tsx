import { Head, Link, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import { Plus, Building2, Edit2, Eye, Trash2 } from 'lucide-react';

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
import { UserSearchSelect } from '@/components/user-search-select';
import { useCan } from '@/hooks/use-can';
import type { RowAction } from '@/components/data-table/data-table-row-actions';

interface DepartmentManager {
    id: number;
    name: string;
    email: string;
}

interface Department {
    id: number;
    name: string;
    code: string;
    description: string | null;
    status: string;
    manager_id: number | null;
    manager?: DepartmentManager | null;
    employees_count?: number;
    work_centers_count?: number;
}

interface DepartmentReports {
    active: number;
    inactive: number;
}

interface PaginatedDepartments {
    data: Department[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    departments: PaginatedDepartments;
    statuses: string[];
    reports: DepartmentReports;
    filters: Record<string, string>;
}

type DepartmentFormData = {
    name: string;
    code: string;
    description: string;
    status: string;
    manager_id: string;
};

const emptyForm = (): DepartmentFormData => ({
    name: '',
    code: '',
    description: '',
    status: 'Active',
    manager_id: '',
});

function isInUse(row: Department): boolean {
    return (row.employees_count ?? 0) > 0 || (row.work_centers_count ?? 0) > 0;
}

function ReportCard({
    label,
    value,
    onClick,
    active,
}: {
    label: string;
    value: number;
    onClick?: () => void;
    active?: boolean;
}) {
    const className = `rounded-xl border bg-card p-4 shadow-sm text-left transition-colors ${
        active ? 'border-primary/50 ring-1 ring-primary/20' : 'border-border/50'
    } ${onClick ? 'hover:border-primary/40 hover:bg-accent/30 cursor-pointer' : ''}`;

    if (onClick) {
        return (
            <button type="button" onClick={onClick} className={className}>
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
                <p className="mt-2 text-2xl font-bold tracking-tight text-foreground">{value}</p>
            </button>
        );
    }

    return (
        <div className={className}>
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="mt-2 text-2xl font-bold tracking-tight text-foreground">{value}</p>
        </div>
    );
}

export default function DepartmentsIndex({ departments, statuses, reports, filters }: Props) {
    const { can } = useCan();
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingDept, setEditingDept] = useState<Department | null>(null);
    const [deleteConfirmDept, setDeleteConfirmDept] = useState<Department | null>(null);
    const [managerLabel, setManagerLabel] = useState('');

    const { data, setData, post, put, delete: destroy, processing, errors, reset, clearErrors } = useForm(emptyForm());

    const handleCreateClick = () => {
        setEditingDept(null);
        reset();
        setData(emptyForm());
        setManagerLabel('');
        clearErrors();
        setIsDialogOpen(true);
    };

    const handleEditClick = (dept: Department) => {
        setEditingDept(dept);
        setData({
            name: dept.name,
            code: dept.code,
            description: dept.description ?? '',
            status: dept.status,
            manager_id: dept.manager_id ? String(dept.manager_id) : '',
        });
        setManagerLabel(
            dept.manager ? `${dept.manager.name} (${dept.manager.email})` : '',
        );
        clearErrors();
        setIsDialogOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const config = {
            onSuccess: () => {
                setIsDialogOpen(false);
                reset();
                setManagerLabel('');
            },
        };
        if (editingDept) {
            put(`/departments/${editingDept.id}`, config);
        } else {
            post('/departments', config);
        }
    };

    const handleDeleteConfirm = () => {
        if (!deleteConfirmDept) return;
        destroy(`/departments/${deleteConfirmDept.id}`, {
            onSuccess: () => setDeleteConfirmDept(null),
        });
    };

    const currentParams: Record<string, string> = {};
    Object.entries(filters ?? {}).forEach(([k, v]) => {
        if (v) currentParams[k] = v;
    });

    const makeFilterChange = (key: string, val: string) => {
        const params = { ...currentParams };
        if (val !== 'all') {
            params[key] = val;
        } else {
            delete params[key];
        }
        delete params.page;
        router.get('/departments', params, { preserveState: true, replace: true });
    };

    const columns: ColumnDef<Department>[] = [
        {
            key: 'name',
            label: 'Department',
            sortable: true,
            render: (row) => (
                <Link
                    href={`/departments/${row.id}`}
                    className="font-semibold text-foreground hover:underline hover:text-primary transition-colors"
                >
                    {row.name}
                </Link>
            ),
        },
        {
            key: 'code',
            label: 'Code',
            sortable: true,
            render: (row) => <span className="font-mono text-sm font-semibold">{row.code}</span>,
        },
        {
            key: 'manager',
            label: 'Manager',
            sortable: false,
            className: 'text-muted-foreground',
            render: (row) => row.manager?.name ?? '—',
        },
        {
            key: 'employees_count',
            label: 'Employees',
            sortable: true,
            className: 'text-muted-foreground',
            render: (row) => {
                const count = row.employees_count ?? 0;
                const label = `${count} ${count === 1 ? 'employee' : 'employees'}`;
                if (!can('employees.view')) {
                    return label;
                }
                return (
                    <Link
                        href={`/employees?department_id=${row.id}`}
                        className="hover:underline hover:text-primary transition-colors"
                    >
                        {label}
                    </Link>
                );
            },
        },
        {
            key: 'work_centers_count',
            label: 'Work Centers',
            sortable: true,
            className: 'text-muted-foreground',
            render: (row) => {
                const count = row.work_centers_count ?? 0;
                const label = `${count} ${count === 1 ? 'work center' : 'work centers'}`;
                if (!can('work-centers.view')) {
                    return label;
                }
                return (
                    <Link
                        href={`/work-centers?department_id=${row.id}`}
                        className="hover:underline hover:text-primary transition-colors"
                    >
                        {label}
                    </Link>
                );
            },
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
        current_page: departments.current_page,
        last_page: departments.last_page,
        per_page: departments.per_page,
        total: departments.total,
        from: departments.from ?? 1,
        to: departments.to ?? departments.data.length,
    };

    const filterSlot = (
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
    );

    return (
        <>
            <Head title="Departments" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Departments</h1>
                        <p className="text-sm text-muted-foreground">Manage departments for the active plant and employee assignments.</p>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4 max-w-xl">
                    <ReportCard
                        label="Active Departments"
                        value={reports.active}
                        active={currentParams.status === 'Active'}
                        onClick={() => makeFilterChange('status', currentParams.status === 'Active' ? 'all' : 'Active')}
                    />
                    <ReportCard
                        label="Inactive Departments"
                        value={reports.inactive}
                        active={currentParams.status === 'Inactive'}
                        onClick={() => makeFilterChange('status', currentParams.status === 'Inactive' ? 'all' : 'Inactive')}
                    />
                </div>

                <DataTable
                    tableId="departments"
                    columns={columns}
                    data={departments.data}
                    meta={meta}
                    baseUrl="/departments"
                    currentParams={currentParams}
                    searchPlaceholder="Search department name or code…"
                    filterSlot={filterSlot}
                    entityLabel="departments"
                    emptyStateIcon={Building2}
                    emptyStateTitle="No departments found"
                    emptyStateDescription={
                        can('departments.create')
                            ? 'Create your first department to begin mapping your workforce.'
                            : 'No departments are available for the active plant.'
                    }
                    emptyStateAction={
                        can('departments.create') ? (
                            <Button size="sm" onClick={handleCreateClick} className="gap-2">
                                <Plus className="size-3.5" />
                                Create Department
                            </Button>
                        ) : undefined
                    }
                    primaryAction={
                        can('departments.create') ? (
                            <Button onClick={handleCreateClick} className="gap-2 h-9 font-semibold text-xs">
                                <Plus className="size-4" />
                                Add Department
                            </Button>
                        ) : undefined
                    }
                    rowActions={(row) => {
                        const actions: RowAction<Department>[] = [
                            {
                                label: 'View',
                                icon: Eye,
                                onClick: (r) => router.visit(`/departments/${r.id}`),
                            },
                        ];

                        if (can('departments.update')) {
                            actions.push({
                                label: 'Edit',
                                icon: Edit2,
                                onClick: handleEditClick,
                            });
                        }

                        if (can('departments.delete')) {
                            actions.push({
                                label: 'Archive',
                                icon: Trash2,
                                onClick: (r) => setDeleteConfirmDept(r),
                                variant: 'destructive',
                                separator: true,
                            });
                        }

                        return actions;
                    }}
                />
            </div>

            <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-bold">
                            {editingDept ? 'Edit Department' : 'Add Department'}
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            Codes should be short identifiers (e.g. PROD, WH, QC).
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="name">
                                    Department Name <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    placeholder="e.g. Quality Assurance"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label>
                                    Status <span className="text-destructive">*</span>
                                </Label>
                                <Select value={data.status} onValueChange={(v) => setData('status', v)}>
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
                                <InputError message={errors.status} />
                            </div>

                            <div className="space-y-2 sm:col-span-2">
                                <Label>
                                    Department Manager{' '}
                                    <span className="text-[10px] text-muted-foreground/80 font-normal">(Optional)</span>
                                </Label>
                                <UserSearchSelect
                                    value={data.manager_id}
                                    selectedLabel={managerLabel}
                                    placeholder="Search users…"
                                    onChange={(val, option) => {
                                        setData('manager_id', val);
                                        setManagerLabel(option?.label ?? '');
                                    }}
                                />
                                <InputError message={errors.manager_id} />
                            </div>

                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="description">
                                    Description{' '}
                                    <span className="text-[10px] text-muted-foreground/80 font-normal">(Optional)</span>
                                </Label>
                                <Input
                                    id="description"
                                    placeholder="Internal notes about this department"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                />
                                <InputError message={errors.description} />
                            </div>
                        </div>

                        <ModalButtons onCancel={() => setIsDialogOpen(false)} processing={processing} />
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDeleteDialog
                open={deleteConfirmDept !== null}
                onOpenChange={(open) => !open && setDeleteConfirmDept(null)}
                title={deleteConfirmDept && isInUse(deleteConfirmDept) ? 'Cannot Archive Department' : 'Archive Department?'}
                description={
                    deleteConfirmDept && isInUse(deleteConfirmDept) ? (
                        <>This department is currently in use.</>
                    ) : (
                        <>
                            Archive <span className="font-semibold text-foreground">{deleteConfirmDept?.name}</span>? This can only be done when no employees or work centers are assigned.
                        </>
                    )
                }
                confirmLabel={deleteConfirmDept && isInUse(deleteConfirmDept) ? 'OK' : 'Archive Department'}
                onConfirm={() => {
                    if (deleteConfirmDept && isInUse(deleteConfirmDept)) {
                        setDeleteConfirmDept(null);
                        return;
                    }
                    handleDeleteConfirm();
                }}
                processing={processing}
            />
        </>
    );
}

DepartmentsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Departments', href: '/departments' },
    ],
};
