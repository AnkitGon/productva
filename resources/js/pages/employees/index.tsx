import { Head, Link, router, useForm } from '@inertiajs/react';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
    Plus,
    Users,
    Eye,
    Edit2,
    Archive,
    Camera,
    Trash2,
    UserCheck,
    UserX,
    Clock3,
    Building2,
    Download,
} from 'lucide-react';
import { toast } from 'sonner';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { useCan } from '@/hooks/use-can';
import { StatusBadge } from '@/components/data-table/status-badge';
// ── Types ─────────────────────────────────────────────────────────────────────

interface Employee {
    id: number;
    employee_code: string;
    first_name: string;
    last_name: string;
    display_name: string | null;
    gender: string | null;
    date_of_birth: string | null;
    name: string;
    photo_path: string | null;
    photo_url?: string | null;
    job_title: string | null;
    email: string | null;
    phone: string | null;
    mobile: string | null;
    address: string | null;
    employment_type: string;
    hire_date: string | null;
    status: 'Active' | 'Inactive' | 'On Leave' | 'Terminated';
    user_id: number | null;
    department: { id: number; name: string; code?: string } | null;
    role: { id: number; name: string; slug: string } | null;
    shift: { id: number; name: string; code: string; status: string } | null;
    manager: {
        id: number;
        first_name: string;
        last_name: string;
        user?: { id: number; name: string; email: string } | null;
    } | null;
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
    departments: Array<{ id: number; name: string; code?: string; status?: string }>;
    shifts: Array<{ id: number; name: string; code: string; status: string }>;
    roles: Array<{ id: number; name: string; slug: string }>;
    editEmployee?: Employee | null;
    filters: Record<string, string>;
}

type EmployeeFormData = {
    employee_code: string;
    first_name: string;
    last_name: string;
    display_name: string;
    gender: string;
    date_of_birth: string;
    department_id: string;
    role_id: string;
    shift_id: string;
    job_title: string;
    manager_id: string;
    email: string;
    phone: string;
    mobile: string;
    address: string;
    employment_type: string;
    hire_date: string;
    status: string;
    create_login: boolean;
    login_email: string;
    login_password: string;
    photo: File | null;
    remove_photo: boolean;
};

const emptyForm = (): EmployeeFormData => ({
    employee_code: '',
    first_name: '',
    last_name: '',
    display_name: '',
    gender: '',
    date_of_birth: '',
    department_id: '',
    role_id: '',
    shift_id: '',
    job_title: '',
    manager_id: '',
    email: '',
    phone: '',
    mobile: '',
    address: '',
    employment_type: 'Full-Time',
    hire_date: '',
    status: 'Active',
    create_login: false,
    login_email: '',
    login_password: '',
    photo: null,
    remove_photo: false,
});

function formFromEmployee(employee: Employee): EmployeeFormData {
    return {
        employee_code: employee.employee_code,
        first_name: employee.first_name,
        last_name: employee.last_name,
        display_name: employee.display_name ?? '',
        gender: employee.gender ?? '',
        date_of_birth: employee.date_of_birth ? employee.date_of_birth.slice(0, 10) : '',
        department_id: employee.department?.id?.toString() ?? '',
        role_id: employee.role?.id?.toString() ?? '',
        shift_id: employee.shift?.id?.toString() ?? '',
        job_title: employee.job_title ?? '',
        manager_id: employee.manager?.id?.toString() ?? '',
        email: employee.email ?? '',
        phone: employee.phone ?? '',
        mobile: employee.mobile ?? '',
        address: employee.address ?? '',
        employment_type: employee.employment_type,
        hire_date: employee.hire_date ? employee.hire_date.slice(0, 10) : '',
        status: employee.status,
        create_login: !!employee.user_id,
        login_email: employee.user?.email ?? '',
        login_password: '',
        photo: null,
        remove_photo: false,
    };
}

function managerLabel(employee: Employee | null): string {
    if (!employee?.manager) {
        return '';
    }

    if (employee.manager.user) {
        return `${employee.manager.user.name} (${employee.manager.user.email})`;
    }

    const name = `${employee.manager.first_name} ${employee.manager.last_name}`.trim();
    return name;
}

const MAX_PHOTO_BYTES = 2 * 1024 * 1024;
const ALLOWED_PHOTO_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

function validatePhotoFile(file: File): string | null {
    if (!ALLOWED_PHOTO_TYPES.includes(file.type)) {
        return 'Photo must be a JPG, PNG, or WebP image.';
    }

    if (file.size > MAX_PHOTO_BYTES) {
        return 'Photo must be 2MB or smaller.';
    }

    return null;
}

function photoErrorMessage(error: unknown): string | undefined {
    if (!error) {
        return undefined;
    }

    if (typeof error === 'string') {
        return error;
    }

    if (Array.isArray(error) && typeof error[0] === 'string') {
        return error[0];
    }

    return undefined;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function getInitials(firstName: string, lastName: string) {
    return `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase();
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function EmployeesIndex({ employees, departments, shifts, roles, editEmployee = null, filters }: Props) {
    const { can } = useCan();
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingEmployee, setEditingEmployee] = useState<Employee | null>(null);
    const openedEditFromQuery = useRef(false);
    const [archiveEmployee, setArchiveEmployee] = useState<Employee | null>(null);
    const [isArchiving, setIsArchiving] = useState(false);
    const [selectedManagerLabel, setSelectedManagerLabel] = useState('');
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);
    const photoInputRef = useRef<HTMLInputElement>(null);
    const photoSectionRef = useRef<HTMLDivElement>(null);
    const [bulkIds, setBulkIds] = useState<number[]>([]);
    const [bulkClearSelection, setBulkClearSelection] = useState<(() => void) | null>(null);
    const [bulkAssignShiftOpen, setBulkAssignShiftOpen] = useState(false);
    const [bulkAssignDepartmentOpen, setBulkAssignDepartmentOpen] = useState(false);
    const [bulkDeleteOpen, setBulkDeleteOpen] = useState(false);
    const [bulkShiftId, setBulkShiftId] = useState('');
    const [bulkDepartmentId, setBulkDepartmentId] = useState('');
    const [bulkProcessing, setBulkProcessing] = useState(false);

    const form = useForm<EmployeeFormData>(emptyForm());

    const activeDepartments = useMemo(
        () => departments.filter((d) => !d.status || d.status === 'Active'),
        [departments],
    );

    const submitBulk = (
        payload: Record<string, unknown>,
        clearSelection?: () => void,
        options?: { onFinish?: () => void },
    ) => {
        setBulkProcessing(true);
        router.post('/employees/bulk', payload, {
            preserveScroll: true,
            onSuccess: () => {
                clearSelection?.();
                setBulkAssignShiftOpen(false);
                setBulkAssignDepartmentOpen(false);
                setBulkDeleteOpen(false);
                setBulkShiftId('');
                setBulkDepartmentId('');
                setBulkIds([]);
            },
            onFinish: () => {
                setBulkProcessing(false);
                options?.onFinish?.();
            },
        });
    };

    const exportBulk = async (ids: number[], clearSelection?: () => void) => {
        setBulkProcessing(true);
        try {
            const xsrf = document.cookie
                .split('; ')
                .find((row) => row.startsWith('XSRF-TOKEN='))
                ?.split('=')[1];

            const response = await fetch('/employees/bulk', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/csv',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf) } : {}),
                },
                body: JSON.stringify({ action: 'export', ids }),
            });

            if (!response.ok) {
                toast.error('Unable to export employees.');
                return;
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = `employees-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.csv`;
            anchor.click();
            URL.revokeObjectURL(url);
            clearSelection?.();
            toast.success('Employees exported.');
        } catch {
            toast.error('Unable to export employees.');
        } finally {
            setBulkProcessing(false);
        }
    };

    const shiftOptions = useMemo(() => {
        const list = [...shifts];
        if (
            editingEmployee?.shift
            && !list.some((shift) => shift.id === editingEmployee.shift?.id)
        ) {
            list.push(editingEmployee.shift);
        }

        return list;
    }, [shifts, editingEmployee]);

    useEffect(() => {
        if (!form.data.photo) {
            return;
        }

        const objectUrl = URL.createObjectURL(form.data.photo);
        setPhotoPreview(objectUrl);

        return () => URL.revokeObjectURL(objectUrl);
    }, [form.data.photo]);

    const currentPhotoSrc = useMemo(() => {
        if (form.data.remove_photo) {
            return null;
        }
        if (photoPreview) {
            return photoPreview;
        }
        if (editingEmployee?.photo_url) {
            return editingEmployee.photo_url;
        }
        if (editingEmployee?.photo_path) {
            return `/storage/${editingEmployee.photo_path}`;
        }
        return null;
    }, [form.data.remove_photo, photoPreview, editingEmployee]);

    const showPhotoError = (message: string) => {
        form.setError('photo', message);
        toast.error(message);
        requestAnimationFrame(() => {
            photoSectionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    };

    const openCreate = () => {
        setEditingEmployee(null);
        setSelectedManagerLabel('');
        setPhotoPreview(null);
        form.setData(emptyForm());
        form.clearErrors();
        setIsDialogOpen(true);
    };

    const openEdit = (employee: Employee) => {
        setEditingEmployee(employee);
        setSelectedManagerLabel(managerLabel(employee));
        setPhotoPreview(null);
        form.setData(formFromEmployee(employee));
        form.clearErrors();
        setIsDialogOpen(true);
    };

    useEffect(() => {
        if (!editEmployee || openedEditFromQuery.current) {
            return;
        }

        openedEditFromQuery.current = true;
        openEdit(editEmployee);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- open once from ?edit= query
    }, [editEmployee]);

    const closeDialog = () => {
        setIsDialogOpen(false);
        setEditingEmployee(null);
        setSelectedManagerLabel('');
        setPhotoPreview(null);
        form.reset();
        form.clearErrors();
        if (photoInputRef.current) {
            photoInputRef.current.value = '';
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (form.data.photo) {
            const photoError = validatePhotoFile(form.data.photo);
            if (photoError) {
                showPhotoError(photoError);
                return;
            }
        }

        const payload = {
            ...form.data,
            photo: form.data.photo ?? undefined,
            ...(editingEmployee ? { _method: 'put' as const } : {}),
        };

        const url = editingEmployee
            ? `/employees/${editingEmployee.id}`
            : '/employees';

        form.transform(() => payload);
        form.post(url, {
            forceFormData: true,
            onSuccess: () => closeDialog(),
            onError: (errors) => {
                const message = photoErrorMessage(errors.photo);
                if (message) {
                    toast.error(message);
                    requestAnimationFrame(() => {
                        photoSectionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    });
                }
            },
            onFinish: () => {
                form.transform((data) => data);
            },
        });
    };

    const handlePhotoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;

        if (!file) {
            form.setData('photo', null);
            form.clearErrors('photo');
            setPhotoPreview(null);
            return;
        }

        const photoError = validatePhotoFile(file);
        if (photoError) {
            e.target.value = '';
            form.setData((data) => ({
                ...data,
                photo: null,
                remove_photo: false,
            }));
            setPhotoPreview(null);
            showPhotoError(photoError);
            return;
        }

        form.clearErrors('photo');
        form.setData((data) => ({
            ...data,
            photo: file,
            remove_photo: false,
        }));
    };

    const clearPhoto = () => {
        form.clearErrors('photo');
        form.setData((data) => ({
            ...data,
            photo: null,
            remove_photo: !!editingEmployee?.photo_url || !!editingEmployee?.photo_path,
        }));
        setPhotoPreview(null);
        if (photoInputRef.current) {
            photoInputRef.current.value = '';
        }
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
                    {(row.photo_url || row.photo_path) && (
                        <AvatarImage src={row.photo_url || `/storage/${row.photo_path}`} alt={row.name} />
                    )}
                    <AvatarFallback className="bg-primary/5 text-primary text-xs font-bold">
                        {getInitials(row.first_name, row.last_name)}
                    </AvatarFallback>
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
            key: 'department',
            label: 'Department',
            defaultVisible: true,
            className: 'text-muted-foreground',
            render: (row) =>
                row.department ? (
                    can('departments.view') ? (
                        <Link
                            href={`/departments/${row.department.id}`}
                            className="hover:underline hover:text-primary transition-colors"
                        >
                            {row.department.name}
                        </Link>
                    ) : (
                        row.department.name
                    )
                ) : (
                    '—'
                ),
        },
        {
            key: 'shift',
            label: 'Shift',
            defaultVisible: true,
            className: 'text-muted-foreground',
            render: (row) =>
                row.shift ? (
                    <span>
                        {row.shift.name}
                        <span className="ml-1 font-mono text-[10px] text-muted-foreground/80">({row.shift.code})</span>
                    </span>
                ) : (
                    '—'
                ),
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
        {
            key: 'email',
            label: 'Email',
            defaultVisible: false,
            className: 'text-muted-foreground text-xs',
            render: (row) => row.email ?? '—',
        },
        {
            key: 'phone',
            label: 'Phone',
            defaultVisible: false,
            className: 'text-muted-foreground text-xs',
            render: (row) => row.phone ?? '—',
        },
        {
            key: 'mobile',
            label: 'Mobile',
            defaultVisible: false,
            className: 'text-muted-foreground text-xs',
            render: (row) => row.mobile ?? '—',
        },
        {
            key: 'display_name',
            label: 'Display Name',
            defaultVisible: false,
            className: 'text-muted-foreground',
            render: (row) => row.display_name ?? '—',
        },
        {
            key: 'hire_date',
            label: 'Hire Date',
            sortable: true,
            defaultVisible: false,
            className: 'text-muted-foreground text-xs',
            render: (row) => (row.hire_date ? row.hire_date.slice(0, 10) : '—'),
        },
        {
            key: 'role',
            label: 'Role',
            defaultVisible: false,
            className: 'text-muted-foreground',
            render: (row) => row.role?.name ?? '—',
        },
        {
            key: 'manager',
            label: 'Manager',
            defaultVisible: false,
            className: 'text-muted-foreground',
            render: (row) =>
                row.manager
                    ? `${row.manager.first_name} ${row.manager.last_name}`.trim()
                    : '—',
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

            <Select value={currentParams.shift_id ?? 'all'} onValueChange={(v) => makeFilterChange('shift_id', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[120px] border-dashed">
                    <SelectValue placeholder="Shift" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Shifts</SelectItem>
                    {shifts.map((s) => (
                        <SelectItem key={s.id} value={s.id.toString()}>
                            {s.name}
                        </SelectItem>
                    ))}
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
                        <p className="text-sm text-muted-foreground">Manage and view employee directory profiles for the active plant.</p>
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
                        can('employees.create') ? (
                            <Button onClick={openCreate} className="gap-2 h-9 font-semibold text-xs">
                                <Plus className="size-4" />
                                Add Employee
                            </Button>
                        ) : undefined
                    }
                    filterSlot={filterSlot}
                    entityLabel="employees"
                    selectable={
                        can('employees.update')
                        || can('employees.export')
                        || can('employees.delete')
                    }
                    bulkActions={({ selectedIds, clearSelection }) => {
                        const ids = selectedIds.map(Number);
                        return (
                            <>
                                {can('employees.update') && (
                                    <>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-7 gap-1.5 text-xs"
                                            disabled={bulkProcessing}
                                            onClick={() => submitBulk({ action: 'activate', ids }, clearSelection)}
                                        >
                                            <UserCheck className="size-3.5" />
                                            Activate
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-7 gap-1.5 text-xs"
                                            disabled={bulkProcessing}
                                            onClick={() => submitBulk({ action: 'deactivate', ids }, clearSelection)}
                                        >
                                            <UserX className="size-3.5" />
                                            Deactivate
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-7 gap-1.5 text-xs"
                                            disabled={bulkProcessing}
                                            onClick={() => {
                                                setBulkIds(ids);
                                                setBulkClearSelection(() => clearSelection);
                                                setBulkAssignShiftOpen(true);
                                            }}
                                        >
                                            <Clock3 className="size-3.5" />
                                            Assign Shift
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-7 gap-1.5 text-xs"
                                            disabled={bulkProcessing}
                                            onClick={() => {
                                                setBulkIds(ids);
                                                setBulkClearSelection(() => clearSelection);
                                                setBulkAssignDepartmentOpen(true);
                                            }}
                                        >
                                            <Building2 className="size-3.5" />
                                            Assign Department
                                        </Button>
                                    </>
                                )}
                                {can('employees.export') && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-7 gap-1.5 text-xs"
                                        disabled={bulkProcessing}
                                        onClick={() => exportBulk(ids, clearSelection)}
                                    >
                                        <Download className="size-3.5" />
                                        Export
                                    </Button>
                                )}
                                {can('employees.delete') && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-7 gap-1.5 text-xs text-destructive hover:text-destructive"
                                        disabled={bulkProcessing}
                                        onClick={() => {
                                            setBulkIds(ids);
                                            setBulkClearSelection(() => clearSelection);
                                            setBulkDeleteOpen(true);
                                        }}
                                    >
                                        <Trash2 className="size-3.5" />
                                        Delete
                                    </Button>
                                )}
                            </>
                        );
                    }}
                    rowActions={(row) => [
                        {
                            label: 'View Profile',
                            icon: Eye,
                            onClick: (r) => router.visit(`/employees/${r.id}`),
                        },
                        ...(can('employees.update')
                            ? [
                                  {
                                      label: 'Edit',
                                      icon: Edit2,
                                      onClick: openEdit,
                                  },
                              ]
                            : []),
                        ...(can('employees.delete')
                            ? [
                                  {
                                      label: 'Archive',
                                      icon: Archive,
                                      onClick: setArchiveEmployee,
                                      variant: 'destructive' as const,
                                      separator: true,
                                  },
                              ]
                            : []),
                    ]}
                    onRowClick={(row) => router.visit(`/employees/${row.id}`)}
                />
            </div>

            <ConfirmDeleteDialog
                open={archiveEmployee !== null}
                onOpenChange={(open) => !open && setArchiveEmployee(null)}
                title="Archive Employee?"
                description={
                    <>
                        Archive <span className="font-semibold text-foreground">{archiveEmployee?.name}</span>? This will soft-delete the employee profile.
                    </>
                }
                confirmLabel="Archive Employee"
                onConfirm={() => {
                    if (!archiveEmployee) return;
                    setIsArchiving(true);
                    router.delete(`/employees/${archiveEmployee.id}`, {
                        preserveScroll: true,
                        onFinish: () => {
                            setIsArchiving(false);
                            setArchiveEmployee(null);
                        },
                    });
                }}
                processing={isArchiving}
            />

            <ConfirmDeleteDialog
                open={bulkDeleteOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setBulkDeleteOpen(false);
                        setBulkIds([]);
                    }
                }}
                title="Archive selected employees?"
                description={
                    <>
                        Archive <span className="font-semibold text-foreground">{bulkIds.length}</span> selected employee
                        {bulkIds.length === 1 ? '' : 's'}? This will soft-delete their profiles.
                    </>
                }
                confirmLabel="Archive Selected"
                onConfirm={() => submitBulk({ action: 'delete', ids: bulkIds }, bulkClearSelection ?? undefined)}
                processing={bulkProcessing}
            />

            <Dialog open={bulkAssignShiftOpen} onOpenChange={setBulkAssignShiftOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Assign Shift</DialogTitle>
                        <DialogDescription>
                            Assign a shift to {bulkIds.length} selected employee{bulkIds.length === 1 ? '' : 's'}.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-2">
                        <div className="space-y-2">
                            <Label>Shift</Label>
                            <Select value={bulkShiftId} onValueChange={setBulkShiftId}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select shift" />
                                </SelectTrigger>
                                <SelectContent>
                                    {shifts.map((shift) => (
                                        <SelectItem key={shift.id} value={shift.id.toString()}>
                                            {shift.name} ({shift.code})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <DialogFooter className="flex items-center justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="font-semibold text-xs"
                                disabled={bulkProcessing}
                                onClick={() => setBulkAssignShiftOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                className="font-semibold text-xs"
                                disabled={bulkProcessing || !bulkShiftId}
                                onClick={() => {
                                    if (!bulkShiftId) {
                                        toast.error('Select a shift.');
                                        return;
                                    }
                                    submitBulk(
                                        { action: 'assign_shift', ids: bulkIds, shift_id: Number(bulkShiftId) },
                                        bulkClearSelection ?? undefined,
                                    );
                                }}
                            >
                                {bulkProcessing ? 'Assigning…' : 'Assign Shift'}
                            </Button>
                        </DialogFooter>
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog open={bulkAssignDepartmentOpen} onOpenChange={setBulkAssignDepartmentOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Assign Department</DialogTitle>
                        <DialogDescription>
                            Assign a department to {bulkIds.length} selected employee{bulkIds.length === 1 ? '' : 's'}.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-2">
                        <div className="space-y-2">
                            <Label>Department</Label>
                            <Select value={bulkDepartmentId} onValueChange={setBulkDepartmentId}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select department" />
                                </SelectTrigger>
                                <SelectContent>
                                    {activeDepartments.map((department) => (
                                        <SelectItem key={department.id} value={department.id.toString()}>
                                            {department.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <DialogFooter className="flex items-center justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="font-semibold text-xs"
                                disabled={bulkProcessing}
                                onClick={() => setBulkAssignDepartmentOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                className="font-semibold text-xs"
                                disabled={bulkProcessing || !bulkDepartmentId}
                                onClick={() => {
                                    if (!bulkDepartmentId) {
                                        toast.error('Select a department.');
                                        return;
                                    }
                                    submitBulk(
                                        {
                                            action: 'assign_department',
                                            ids: bulkIds,
                                            department_id: Number(bulkDepartmentId),
                                        },
                                        bulkClearSelection ?? undefined,
                                    );
                                }}
                            >
                                {bulkProcessing ? 'Assigning…' : 'Assign Department'}
                            </Button>
                        </DialogFooter>
                    </div>
                </DialogContent>
            </Dialog>

            {/* Create / Edit Employee Dialog */}
            <Dialog open={isDialogOpen} onOpenChange={(open) => { if (!open) closeDialog(); }}>
                <DialogContent className="sm:max-w-3xl max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-bold">
                            {editingEmployee ? 'Edit Employee' : 'Add Employee'}
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            {editingEmployee
                                ? 'Update employee profile details and system access.'
                                : 'Create a new employee profile and optional system access login.'}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="space-y-8 py-2" encType="multipart/form-data">
                        {/* Section 1: General */}
                        <div className="space-y-4">
                            <h4 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider">General Info</h4>
                            <div className="border-t border-border/40 my-2" />

                            <div ref={photoSectionRef} className="flex flex-col sm:flex-row sm:items-center gap-4">
                                <Avatar className="size-20 border border-border/40 shadow-sm">
                                    {currentPhotoSrc && (
                                        <AvatarImage src={currentPhotoSrc} alt="Employee photo" />
                                    )}
                                    <AvatarFallback className="bg-primary/5 text-primary text-lg font-bold">
                                        {form.data.first_name || form.data.last_name
                                            ? getInitials(form.data.first_name || '?', form.data.last_name || '?')
                                            : <Camera className="size-6 opacity-50" />}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="space-y-2 min-w-0">
                                    <Label htmlFor="photo">Photo <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Input
                                            ref={photoInputRef}
                                            id="photo"
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            className={`max-w-xs cursor-pointer ${form.errors.photo ? 'border-destructive focus-visible:ring-destructive/40' : ''}`}
                                            onChange={handlePhotoChange}
                                        />
                                        {(currentPhotoSrc || form.data.photo) && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="gap-1.5 text-xs"
                                                onClick={clearPhoto}
                                            >
                                                <Trash2 className="size-3.5" />
                                                Remove
                                            </Button>
                                        )}
                                    </div>
                                    <p className="text-[11px] text-muted-foreground">JPG, PNG, or WebP. Max 2MB.</p>
                                    <div className="min-h-[20px]">
                                        <InputError message={photoErrorMessage(form.errors.photo)} />
                                    </div>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4">
                                <div className="min-w-0 space-y-2">
                                    <Label htmlFor="display_name">Display Name <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Input id="display_name" placeholder="Preferred nickname" value={form.data.display_name} onChange={(e) => form.setData('display_name', e.target.value)} />
                                    <div className="min-h-[20px] mt-1" />
                                </div>
                                <div className="min-w-0 space-y-2">
                                    <Label htmlFor="first_name">First Name <span className="text-destructive">*</span></Label>
                                    <Input id="first_name" placeholder="First Name" value={form.data.first_name} onChange={(e) => form.setData('first_name', e.target.value)} />
                                    <div className="min-h-[20px] mt-1"><InputError message={form.errors.first_name} /></div>
                                </div>
                                <div className="min-w-0 space-y-2">
                                    <Label htmlFor="last_name">Last Name <span className="text-destructive">*</span></Label>
                                    <Input id="last_name" placeholder="Last Name" value={form.data.last_name} onChange={(e) => form.setData('last_name', e.target.value)} />
                                    <div className="min-h-[20px] mt-1"><InputError message={form.errors.last_name} /></div>
                                </div>
                                <div className="min-w-0 space-y-2">
                                    <Label>Gender <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Select
                                        value={form.data.gender || undefined}
                                        onValueChange={(val) => form.setData('gender', val)}
                                    >
                                        <SelectTrigger className="w-full"><SelectValue placeholder="Select gender" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Male">Male</SelectItem>
                                            <SelectItem value="Female">Female</SelectItem>
                                            <SelectItem value="Other">Other</SelectItem>
                                            <SelectItem value="Prefer not to say">Prefer not to say</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <div className="min-h-[20px] mt-1"><InputError message={form.errors.gender} /></div>
                                </div>
                                <div className="min-w-0 space-y-2">
                                    <Label htmlFor="date_of_birth">Date of Birth <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Input id="date_of_birth" type="date" value={form.data.date_of_birth} onChange={(e) => form.setData('date_of_birth', e.target.value)} />
                                    <div className="min-h-[20px] mt-1"><InputError message={form.errors.date_of_birth} /></div>
                                </div>
                            </div>
                        </div>

                        {/* Section 2: Organization */}
                        <div className="space-y-4">
                            <h4 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider">Organization</h4>
                            <div className="border-t border-border/40 my-2" />
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-4">
                                <div className="min-w-0 space-y-2">
                                    <Label>Department <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Select value={form.data.department_id || undefined} onValueChange={(val) => form.setData('department_id', val)}>
                                        <SelectTrigger className="w-full"><SelectValue placeholder="Select Department" /></SelectTrigger>
                                        <SelectContent>
                                            {departments
                                                .filter((d) => d.status === 'Active' || d.id.toString() === form.data.department_id)
                                                .map((d) => (
                                                    <SelectItem key={d.id} value={d.id.toString()}>
                                                        {d.name}{d.status && d.status !== 'Active' ? ' (Inactive)' : ''}
                                                    </SelectItem>
                                                ))}
                                        </SelectContent>
                                    </Select>
                                    <div className="min-h-[20px] mt-1" />
                                </div>
                                <div className="min-w-0 space-y-2">
                                    <Label>Role <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Select
                                        value={form.data.role_id || undefined}
                                        onValueChange={(val) => form.setData('role_id', val === 'none' ? '' : val)}
                                    >
                                        <SelectTrigger className="w-full"><SelectValue placeholder="Select Role" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">No role</SelectItem>
                                            {roles.map((r) => (
                                                <SelectItem key={r.id} value={r.id.toString()}>{r.name}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <div className="min-h-[20px] mt-1"><InputError message={form.errors.role_id} /></div>
                                </div>
                                <div className="min-w-0 space-y-2">
                                    <Label>Shift <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Select
                                        value={form.data.shift_id || undefined}
                                        onValueChange={(val) => form.setData('shift_id', val === 'none' ? '' : val)}
                                    >
                                        <SelectTrigger className="w-full"><SelectValue placeholder="Select Shift" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">No shift</SelectItem>
                                            {shiftOptions.map((s) => (
                                                <SelectItem key={s.id} value={s.id.toString()}>
                                                    {s.name} ({s.code})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <div className="min-h-[20px] mt-1"><InputError message={form.errors.shift_id} /></div>
                                </div>
                                <div className="min-w-0 space-y-2">
                                    <Label htmlFor="job_title">Job Title <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Input id="job_title" placeholder="Job Role / Title" value={form.data.job_title} onChange={(e) => form.setData('job_title', e.target.value)} />
                                    <div className="min-h-[20px] mt-1" />
                                </div>
                                <div className="min-w-0 space-y-2 sm:col-span-2 lg:col-span-1">
                                    <Label>Reporting Manager <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <UserSearchSelect
                                        value={form.data.manager_id}
                                        selectedLabel={selectedManagerLabel}
                                        placeholder="Search employees…"
                                        searchPlaceholder="Type to search employees…"
                                        withEmployee
                                        excludeEmployeeId={editingEmployee?.id ?? null}
                                        onChange={(val, option) => {
                                            form.setData('manager_id', val);
                                            setSelectedManagerLabel(option?.label ?? '');
                                        }}
                                    />
                                    <div className="min-h-[20px] mt-1"><InputError message={form.errors.manager_id} /></div>
                                </div>
                            </div>
                        </div>

                        {/* Section 3: Contact */}
                        <div className="space-y-4">
                            <h4 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider">Contact</h4>
                            <div className="border-t border-border/40 my-2" />
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-4">
                                <div className="min-w-0 space-y-2">
                                    <Label htmlFor="email">Email <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Input id="email" type="email" placeholder="work@example.com" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                                    <div className="min-h-[20px] mt-1"><InputError message={form.errors.email} /></div>
                                </div>
                                <div className="min-w-0 space-y-2">
                                    <Label htmlFor="phone">Phone <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Input id="phone" placeholder="Office desk phone" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                                    <div className="min-h-[20px] mt-1" />
                                </div>
                                <div className="min-w-0 space-y-2 sm:col-span-2 lg:col-span-1">
                                    <Label htmlFor="mobile">Mobile <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Input id="mobile" placeholder="Cell phone" value={form.data.mobile} onChange={(e) => form.setData('mobile', e.target.value)} />
                                    <div className="min-h-[20px] mt-1" />
                                </div>
                                <div className="min-w-0 space-y-2 sm:col-span-2 lg:col-span-3">
                                    <Label htmlFor="address">Address <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <textarea
                                        id="address"
                                        rows={2}
                                        placeholder="Street, city, postal code"
                                        value={form.data.address}
                                        onChange={(e) => form.setData('address', e.target.value)}
                                        className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
                                    />
                                    <div className="min-h-[20px] mt-1"><InputError message={form.errors.address} /></div>
                                </div>
                            </div>
                        </div>

                        {/* Section 4: Employment */}
                        <div className="space-y-4">
                            <h4 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider">Employment Status</h4>
                            <div className="border-t border-border/40 my-2" />
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-4">
                                <div className="min-w-0 space-y-2">
                                    <Label>Employment Type</Label>
                                    <Select value={form.data.employment_type} onValueChange={(val) => form.setData('employment_type', val)}>
                                        <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Full-Time">Full-Time</SelectItem>
                                            <SelectItem value="Part-Time">Part-Time</SelectItem>
                                            <SelectItem value="Contract">Contract</SelectItem>
                                            <SelectItem value="Temporary">Temporary</SelectItem>
                                            <SelectItem value="Intern">Intern</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <div className="min-h-[20px] mt-1"><InputError message={form.errors.employment_type} /></div>
                                </div>
                                <div className="min-w-0 space-y-2">
                                    <Label htmlFor="hire_date">Hire Date <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Input id="hire_date" type="date" value={form.data.hire_date} onChange={(e) => form.setData('hire_date', e.target.value)} />
                                    <div className="min-h-[20px] mt-1" />
                                </div>
                                <div className="min-w-0 space-y-2 sm:col-span-2 lg:col-span-1">
                                    <Label>Status</Label>
                                    <Select value={form.data.status} onValueChange={(val) => form.setData('status', val)}>
                                        <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
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
                                <Checkbox
                                    id="create_login"
                                    checked={form.data.create_login}
                                    onCheckedChange={(checked) => form.setData('create_login', checked === true)}
                                    disabled={!!editingEmployee?.user_id}
                                />
                                <Label htmlFor="create_login" className="text-xs font-semibold cursor-pointer select-none">
                                    {editingEmployee?.user_id ? 'System login linked' : 'Create login account'}
                                </Label>
                            </div>
                            {form.data.create_login && (
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4 mt-3 pt-3 border-t border-border/40 animate-in fade-in slide-in-from-top-2 duration-200">
                                    <div className="min-w-0 space-y-2">
                                        <Label htmlFor="login_email">Login Email <span className="text-destructive">*</span></Label>
                                        <Input id="login_email" type="email" placeholder="login@example.com" value={form.data.login_email} onChange={(e) => form.setData('login_email', e.target.value)} />
                                        <div className="min-h-[20px] mt-1"><InputError message={form.errors.login_email} /></div>
                                    </div>
                                    <div className="min-w-0 space-y-2">
                                        <Label htmlFor="login_password">
                                            {editingEmployee?.user_id ? 'New Password' : 'Temporary Password'}{' '}
                                            {editingEmployee?.user_id
                                                ? <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span>
                                                : <span className="text-destructive">*</span>}
                                        </Label>
                                        <Input id="login_password" type="password" placeholder="At least 8 characters" value={form.data.login_password} onChange={(e) => form.setData('login_password', e.target.value)} />
                                        <div className="min-h-[20px] mt-1"><InputError message={form.errors.login_password} /></div>
                                    </div>
                                </div>
                            )}
                        </div>

                        <ModalButtons onCancel={closeDialog} processing={form.processing} />
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
