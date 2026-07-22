import { Head, Link, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import { Plus, Users, Eye, Archive } from 'lucide-react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { ModalButtons } from '@/components/modal-buttons';

import { DataTable, type ColumnDef, type TableMeta } from '@/components/data-table/data-table';
import { StatusBadge } from '@/components/data-table/status-badge';

// ── Types ─────────────────────────────────────────────────────────────────────

interface Employee {
    id: number;
    employee_code: string;
    first_name: string;
    last_name: string;
    display_name: string | null;
    name: string;
    photo_path: string | null;
    job_title: string | null;
    email: string | null;
    phone: string | null;
    mobile: string | null;
    employment_type: string;
    hire_date: string | null;
    status: 'Active' | 'Inactive' | 'On Leave' | 'Terminated';
    user_id: number | null;
    plant: { id: number; name: string } | null;
    department: { id: number; name: string } | null;
    manager: { id: number; first_name: string; last_name: string } | null;
    user: { id: number; name: string; email: string; roles: Array<{ id: number; name: string; slug: string }> } | null;
}

interface PaginatedEmployees {
    data: Employee[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    employees: PaginatedEmployees;
    plants: Array<{ id: number; name: string }>;
    departments: Array<{ id: number; name: string }>;
    managers: Array<{ id: number; first_name: string; last_name: string }>;
    roles: Array<{ id: number; name: string; slug: string }>;
    filters: Record<string, string>;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function getInitials(firstName: string, lastName: string) {
    return `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase();
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function EmployeesIndex({ employees, plants, departments, managers, roles, filters }: Props) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);

    const createForm = useForm({
        employee_code: '',
        first_name: '',
        last_name: '',
        display_name: '',
        department_id: '',
        job_title: '',
        manager_id: '',
        email: '',
        phone: '',
        mobile: '',
        employment_type: 'Full-Time',
        hire_date: '',
        status: 'Active',
        create_login: false,
        login_email: '',
        login_role_id: '',
        login_password: '',
    });

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/employees', {
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
            },
        });
    };

    // Filters that feed into the DataTable toolbar filter slot
    const currentParams: Record<string, string> = {};
    Object.entries(filters ?? {}).forEach(([k, v]) => { if (v) currentParams[k] = String(v); });

    // ── Column Definitions ─────────────────────────────────────────────────
    const columns: ColumnDef<Employee>[] = [
        {
            key: 'photo',
            label: 'Photo',
            defaultVisible: true,
            className: 'w-12',
            render: (row) => (
                <Avatar className="size-8 border border-border/40 shadow-sm">
                    {row.photo_path ? (
                        <AvatarImage src={row.photo_path} alt={row.name} />
                    ) : (
                        <AvatarFallback className="bg-primary/5 text-primary text-xs font-bold">
                            {getInitials(row.first_name, row.last_name)}
                        </AvatarFallback>
                    )}
                </Avatar>
            ),
        },
        {
            key: 'employee_code',
            label: 'Code',
            sortable: true,
            className: 'font-mono font-medium text-xs text-muted-foreground',
        },
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (row) => (
                <Link href={`/employees/${row.id}`} className="font-semibold text-foreground hover:underline hover:text-primary transition-colors">
                    {row.name}
                </Link>
            ),
        },
        {
            key: 'plant',
            label: 'Plant',
            defaultVisible: true,
            className: 'text-muted-foreground',
            render: (row) => row.plant?.name ?? '—',
        },
        {
            key: 'department',
            label: 'Department',
            defaultVisible: true,
            className: 'text-muted-foreground',
            render: (row) => row.department?.name ?? '—',
        },
        {
            key: 'job_title',
            label: 'Job Title',
            defaultVisible: true,
            className: 'text-muted-foreground',
            render: (row) => row.job_title ?? '—',
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (row) => <StatusBadge status={row.status} />,
        },
        {
            key: 'employment_type',
            label: 'Type',
            sortable: true,
            defaultVisible: false,
            className: 'text-muted-foreground text-xs',
        },
        {
            key: 'user_id',
            label: 'System Access',
            defaultVisible: true,
            render: (row) =>
                row.user_id ? (
                    <StatusBadge status="Active" />
                ) : (
                    <span className="text-xs text-muted-foreground">None</span>
                ),
        },
    ];

    const meta: TableMeta = {
        current_page: employees.current_page,
        last_page: employees.last_page,
        per_page: employees.per_page,
        total: employees.total,
        from: employees.from ?? 1,
        to: employees.to ?? employees.data.length,
    };

    // ── Compact inline filter selects ────────────────────────────────────────────
    const makeFilterChange = (key: string, val: string) => {
        const params = { ...currentParams };
        val !== 'all' ? (params[key] = val) : delete params[key];
        delete params.page;
        router.get('/employees', params, { preserveState: true, replace: true });
    };

    const filterSlot = (
        <>
            <Select value={currentParams.department_id ?? 'all'} onValueChange={(v) => makeFilterChange('department_id', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[120px] border-dashed">
                    <SelectValue placeholder="Department" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Departments</SelectItem>
                    {departments.map((d) => (<SelectItem key={d.id} value={d.id.toString()}>{d.name}</SelectItem>))}
                </SelectContent>
            </Select>

            <Select value={currentParams.employment_type ?? 'all'} onValueChange={(v) => makeFilterChange('employment_type', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[90px] border-dashed">
                    <SelectValue placeholder="Type" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Types</SelectItem>
                    <SelectItem value="Full-Time">Full-Time</SelectItem>
                    <SelectItem value="Part-Time">Part-Time</SelectItem>
                    <SelectItem value="Contract">Contract</SelectItem>
                    <SelectItem value="Temporary">Temporary</SelectItem>
                    <SelectItem value="Intern">Intern</SelectItem>
                </SelectContent>
            </Select>

            <Select value={currentParams.status ?? 'all'} onValueChange={(v) => makeFilterChange('status', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[90px] border-dashed">
                    <SelectValue placeholder="Status" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Statuses</SelectItem>
                    <SelectItem value="Active">Active</SelectItem>
                    <SelectItem value="Inactive">Inactive</SelectItem>
                    <SelectItem value="On Leave">On Leave</SelectItem>
                    <SelectItem value="Terminated">Terminated</SelectItem>
                </SelectContent>
            </Select>

            <Select value={currentParams.has_login ?? 'all'} onValueChange={(v) => makeFilterChange('has_login', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[100px] border-dashed">
                    <SelectValue placeholder="Access" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Access</SelectItem>
                    <SelectItem value="yes">Has Login</SelectItem>
                    <SelectItem value="no">No Login</SelectItem>
                </SelectContent>
            </Select>
        </>
    );

    return (
        <>
            <Head title="People Directory" />

            <div className="flex flex-col gap-6 p-6">
                {/* Page header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">People Directory</h1>
                        <p className="text-sm text-muted-foreground">Manage and view employee directory profiles across your plants.</p>
                    </div>
                </div>

                {/* DataTable */}
                <DataTable
                    tableId="employees"
                    columns={columns}
                    data={employees.data}
                    meta={meta}
                    baseUrl="/employees"
                    currentParams={currentParams}
                    searchPlaceholder="Search by name, code, email…"
                    emptyStateIcon={Users}
                    emptyStateTitle="No employees found"
                    emptyStateDescription="Try adjusting your filters or search query."
                    primaryAction={
                        <Button onClick={() => setIsCreateOpen(true)} className="gap-2 h-9 font-semibold text-xs">
                            <Plus className="size-4" />
                            Add Employee
                        </Button>
                    }
                    filterSlot={filterSlot}
                    entityLabel="employees"
                    rowActions={(row) => [
                        {
                            label: 'View Profile',
                            icon: Eye,
                            onClick: (r) => router.visit(`/employees/${r.id}`),
                        },
                        {
                            label: 'Archive',
                            icon: Archive,
                            onClick: (r) => {
                                if (confirm(`Archive ${r.name}?`)) {
                                    router.delete(`/employees/${r.id}`, { preserveScroll: true });
                                }
                            },
                            variant: 'destructive',
                            separator: true,
                        },
                    ]}
                    onRowClick={(row) => router.visit(`/employees/${row.id}`)}
                />
            </div>

            {/* Create Employee Dialog */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent className="max-w-2xl max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-bold">Add Employee</DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            Create a new employee profile and optional system access login.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleCreateSubmit} className="space-y-8 py-2">
                        {/* Section 1: General */}
                        <div className="space-y-4">
                            <h4 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider">General Info</h4>
                            <div className="border-t border-border/40 my-2" />
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="employee_code">Employee Code <span className="text-destructive">*</span></Label>
                                    <Input id="employee_code" placeholder="e.g. EMP-102" value={createForm.data.employee_code} onChange={(e) => createForm.setData('employee_code', e.target.value)} />
                                    <div className="min-h-[20px] mt-1"><InputError message={createForm.errors.employee_code} /></div>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="display_name">Display Name <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Input id="display_name" placeholder="Preferred nickname" value={createForm.data.display_name} onChange={(e) => createForm.setData('display_name', e.target.value)} />
                                    <div className="min-h-[20px] mt-1" />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="first_name">First Name <span className="text-destructive">*</span></Label>
                                    <Input id="first_name" placeholder="First Name" value={createForm.data.first_name} onChange={(e) => createForm.setData('first_name', e.target.value)} />
                                    <div className="min-h-[20px] mt-1"><InputError message={createForm.errors.first_name} /></div>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="last_name">Last Name <span className="text-destructive">*</span></Label>
                                    <Input id="last_name" placeholder="Last Name" value={createForm.data.last_name} onChange={(e) => createForm.setData('last_name', e.target.value)} />
                                    <div className="min-h-[20px] mt-1"><InputError message={createForm.errors.last_name} /></div>
                                </div>
                            </div>
                        </div>

                        {/* Section 2: Organization */}
                        <div className="space-y-4">
                            <h4 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider">Organization</h4>
                            <div className="border-t border-border/40 my-2" />
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div className="space-y-2">
                                    <Label>Department <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Select value={createForm.data.department_id} onValueChange={(val) => createForm.setData('department_id', val)}>
                                        <SelectTrigger><SelectValue placeholder="Select Department" /></SelectTrigger>
                                        <SelectContent>{departments.map((d) => (<SelectItem key={d.id} value={d.id.toString()}>{d.name}</SelectItem>))}</SelectContent>
                                    </Select>
                                    <div className="min-h-[20px] mt-1" />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="job_title">Job Title <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Input id="job_title" placeholder="Job Role / Title" value={createForm.data.job_title} onChange={(e) => createForm.setData('job_title', e.target.value)} />
                                    <div className="min-h-[20px] mt-1" />
                                </div>
                                <div className="space-y-2">
                                    <Label>Reporting Manager <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Select value={createForm.data.manager_id} onValueChange={(val) => createForm.setData('manager_id', val)}>
                                        <SelectTrigger><SelectValue placeholder="Select Manager" /></SelectTrigger>
                                        <SelectContent>{managers.map((m) => (<SelectItem key={m.id} value={m.id.toString()}>{m.first_name} {m.last_name}</SelectItem>))}</SelectContent>
                                    </Select>
                                    <div className="min-h-[20px] mt-1" />
                                </div>
                            </div>
                        </div>

                        {/* Section 3: Contact */}
                        <div className="space-y-4">
                            <h4 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider">Contact</h4>
                            <div className="border-t border-border/40 my-2" />
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="email">Email <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Input id="email" type="email" placeholder="work@example.com" value={createForm.data.email} onChange={(e) => createForm.setData('email', e.target.value)} />
                                    <div className="min-h-[20px] mt-1"><InputError message={createForm.errors.email} /></div>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="phone">Phone <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Input id="phone" placeholder="Office desk phone" value={createForm.data.phone} onChange={(e) => createForm.setData('phone', e.target.value)} />
                                    <div className="min-h-[20px] mt-1" />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="mobile">Mobile <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Input id="mobile" placeholder="Cell phone" value={createForm.data.mobile} onChange={(e) => createForm.setData('mobile', e.target.value)} />
                                    <div className="min-h-[20px] mt-1" />
                                </div>
                            </div>
                        </div>

                        {/* Section 4: Employment */}
                        <div className="space-y-4">
                            <h4 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider">Employment Status</h4>
                            <div className="border-t border-border/40 my-2" />
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div className="space-y-2">
                                    <Label>Employment Type</Label>
                                    <Select value={createForm.data.employment_type} onValueChange={(val) => createForm.setData('employment_type', val)}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Full-Time">Full-Time</SelectItem>
                                            <SelectItem value="Part-Time">Part-Time</SelectItem>
                                            <SelectItem value="Contract">Contract</SelectItem>
                                            <SelectItem value="Temporary">Temporary</SelectItem>
                                            <SelectItem value="Intern">Intern</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <div className="min-h-[20px] mt-1"><InputError message={createForm.errors.employment_type} /></div>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="hire_date">Hire Date <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Input id="hire_date" type="date" value={createForm.data.hire_date} onChange={(e) => createForm.setData('hire_date', e.target.value)} />
                                    <div className="min-h-[20px] mt-1" />
                                </div>
                                <div className="space-y-2">
                                    <Label>Status</Label>
                                    <Select value={createForm.data.status} onValueChange={(val) => createForm.setData('status', val)}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Active">Active</SelectItem>
                                            <SelectItem value="Inactive">Inactive</SelectItem>
                                            <SelectItem value="On Leave">On Leave</SelectItem>
                                            <SelectItem value="Terminated">Terminated</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <div className="min-h-[20px] mt-1" />
                                </div>
                            </div>
                        </div>

                        {/* Section 5: System Access */}
                        <div className="space-y-4 rounded-xl border border-border/40 p-4 bg-muted/10">
                            <div className="flex items-center gap-2">
                                <Checkbox id="create_login" checked={createForm.data.create_login} onCheckedChange={(checked) => createForm.setData('create_login', checked === true)} />
                                <Label htmlFor="create_login" className="text-xs font-semibold cursor-pointer select-none">Create login account</Label>
                            </div>
                            {createForm.data.create_login && (
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-3 pt-3 border-t border-border/40 animate-in fade-in slide-in-from-top-2 duration-200">
                                    <div className="space-y-2">
                                        <Label htmlFor="login_email">Login Email <span className="text-destructive">*</span></Label>
                                        <Input id="login_email" type="email" placeholder="login@example.com" value={createForm.data.login_email} onChange={(e) => createForm.setData('login_email', e.target.value)} />
                                        <div className="min-h-[20px] mt-1"><InputError message={createForm.errors.login_email} /></div>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Role <span className="text-destructive">*</span></Label>
                                        <Select value={createForm.data.login_role_id} onValueChange={(val) => createForm.setData('login_role_id', val)}>
                                            <SelectTrigger><SelectValue placeholder="Select Role" /></SelectTrigger>
                                            <SelectContent>{roles.map((r) => (<SelectItem key={r.id} value={r.id.toString()}>{r.name}</SelectItem>))}</SelectContent>
                                        </Select>
                                        <div className="min-h-[20px] mt-1"><InputError message={createForm.errors.login_role_id} /></div>
                                    </div>
                                    <div className="space-y-2 sm:col-span-2">
                                        <Label htmlFor="login_password">Temporary Password <span className="text-destructive">*</span></Label>
                                        <Input id="login_password" type="password" placeholder="At least 8 characters" value={createForm.data.login_password} onChange={(e) => createForm.setData('login_password', e.target.value)} />
                                        <div className="min-h-[20px] mt-1"><InputError message={createForm.errors.login_password} /></div>
                                    </div>
                                </div>
                            )}
                        </div>

                        <ModalButtons onCancel={() => setIsCreateOpen(false)} processing={createForm.processing} />
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

EmployeesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Employees Directory', href: '/employees' },
    ],
};
