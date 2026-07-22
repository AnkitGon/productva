import { Head, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import { Plus, Building2, Edit2, Trash2, HelpCircle } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import { ModalButtons } from '@/components/modal-buttons';
import { DataTable, type ColumnDef, type TableMeta } from '@/components/data-table/data-table';

interface Department {
    id: number;
    name: string;
    employees_count?: number;
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
    filters: Record<string, string>;
}

export default function DepartmentsIndex({ departments, filters }: Props) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingDept, setEditingDept] = useState<Department | null>(null);
    const [deleteConfirmDept, setDeleteConfirmDept] = useState<Department | null>(null);

    const { data, setData, post, put, delete: destroy, processing, errors, reset, clearErrors } = useForm({ name: '' });

    const handleCreateClick = () => {
        setEditingDept(null);
        setData('name', '');
        clearErrors();
        setIsDialogOpen(true);
    };

    const handleEditClick = (dept: Department) => {
        setEditingDept(dept);
        setData('name', dept.name);
        clearErrors();
        setIsDialogOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const config = {
            onSuccess: () => { setIsDialogOpen(false); reset(); },
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

    // ── Column Definitions ───────────────────────────────────────────────────
    const columns: ColumnDef<Department>[] = [
        {
            key: 'name',
            label: 'Department Name',
            sortable: true,
            render: (row) => (
                <span className="font-semibold text-foreground">{row.name}</span>
            ),
        },
        {
            key: 'employees_count',
            label: 'Employees',
            sortable: true,
            className: 'text-muted-foreground',
            render: (row) => {
                const count = row.employees_count ?? 0;
                return `${count} ${count === 1 ? 'employee' : 'employees'}`;
            },
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

    // Normalise filters to string map for DataTable
    const currentParams: Record<string, string> = {};
    Object.entries(filters ?? {}).forEach(([k, v]) => { if (v) currentParams[k] = v; });

    return (
        <>
            <Head title="Departments" />

            <div className="flex flex-col gap-6 p-6">
                {/* Page header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Departments</h1>
                        <p className="text-sm text-muted-foreground">Manage corporate departments and employee assignments.</p>
                    </div>
                </div>

                {/* DataTable */}
                <DataTable
                    tableId="departments"
                    columns={columns}
                    data={departments.data}
                    meta={meta}
                    baseUrl="/departments"
                    currentParams={currentParams}
                    searchPlaceholder="Search departments…"
                    entityLabel="departments"
                    emptyStateIcon={Building2}
                    emptyStateTitle="No departments found"
                    emptyStateDescription="Create your first department to begin mapping your workforce."
                    emptyStateAction={
                        <Button size="sm" onClick={handleCreateClick} className="gap-2">
                            <Plus className="size-3.5" />
                            Create Department
                        </Button>
                    }
                    primaryAction={
                        <Button onClick={handleCreateClick} className="gap-2 h-9 font-semibold text-xs">
                            <Plus className="size-4" />
                            Add Department
                        </Button>
                    }
                    rowActions={(row) => [
                        {
                            label: 'Edit',
                            icon: Edit2,
                            onClick: handleEditClick,
                        },
                        {
                            label: 'Archive',
                            icon: Trash2,
                            onClick: (r) => setDeleteConfirmDept(r),
                            variant: 'destructive',
                            separator: true,
                        },
                    ]}
                />
            </div>

            {/* Create / Edit Dialog */}
            <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-bold">
                            {editingDept ? 'Edit Department' : 'Add Department'}
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            Specify department name mapping details.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="space-y-4 py-2">
                            <div className="space-y-2">
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
                                <div className="min-h-[20px]">
                                    <InputError message={errors.name} />
                                </div>
                            </div>
                        </div>
                        <ModalButtons onCancel={() => setIsDialogOpen(false)} processing={processing} />
                    </form>
                </DialogContent>
            </Dialog>

            {/* Archive Confirmation Dialog */}
            <Dialog open={deleteConfirmDept !== null} onOpenChange={(open) => !open && setDeleteConfirmDept(null)}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-bold text-destructive flex items-center gap-2">
                            <HelpCircle className="size-5 shrink-0" />
                            Archive Department?
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground pt-1.5">
                            Archive <span className="font-semibold text-foreground">{deleteConfirmDept?.name}</span>? Employees assigned will not be removed.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="flex items-center justify-end gap-2 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDeleteConfirmDept(null)}
                            className="font-semibold text-xs"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={handleDeleteConfirm}
                            className="font-semibold text-xs"
                        >
                            Archive Department
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

DepartmentsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Departments', href: '/departments' },
    ],
};
