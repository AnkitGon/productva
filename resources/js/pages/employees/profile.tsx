import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Award,
    ClipboardList,
    FileText,
    Shield,
    User,
} from 'lucide-react';
import { useState } from 'react';

import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { ModalButtons } from '@/components/modal-buttons';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCan } from '@/hooks/use-can';

interface Employee {
    id: number;
    employee_code: string;
    first_name: string;
    last_name: string;
    display_name: string | null;
    name: string;
    gender: string | null;
    date_of_birth: string | null;
    photo_path: string | null;
    photo_url?: string | null;
    job_title: string | null;
    email: string | null;
    phone: string | null;
    mobile: string | null;
    address: string | null;
    employment_type: string;
    hire_date: string | null;
    years_of_service: number | null;
    status: 'Active' | 'Inactive' | 'On Leave' | 'Terminated';
    user_id: number | null;
    department: { id: number; name: string; code?: string } | null;
    role: { id: number; name: string; slug: string } | null;
    shift: { id: number; name: string; code: string } | null;
    plant: { id: number; name: string; code?: string } | null;
    manager: { id: number; first_name: string; last_name: string; name?: string } | null;
    user: { id: number; name: string; email: string; roles: Array<{ id: number; name: string; slug: string }> } | null;
}

interface Props {
    employee: Employee;
    departments: Array<{ id: number; name: string; code?: string; status?: string }>;
    shifts: Array<{ id: number; name: string; code: string; status: string }>;
}

type TabId = 'overview' | 'assignments' | 'permissions' | 'activity' | 'documents';

function getInitials(firstName: string, lastName: string) {
    return `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase();
}

function formatDate(value?: string | null) {
    if (!value) {
        return '—';
    }

    return value.slice(0, 10);
}

function statusDotClass(status: string) {
    switch (status) {
        case 'Active':
            return 'bg-emerald-500';
        case 'On Leave':
            return 'bg-amber-500';
        case 'Terminated':
            return 'bg-rose-500';
        default:
            return 'bg-neutral-400';
    }
}

function InfoItem({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="min-w-0 space-y-1">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
            <div className="text-sm font-medium text-foreground break-words">{value ?? '—'}</div>
        </div>
    );
}

export default function EmployeeProfile({ employee, departments, shifts }: Props) {
    const { can } = useCan();
    const [activeTab, setActiveTab] = useState<TabId>('overview');
    const [assignOpen, setAssignOpen] = useState(false);
    const [deactivateOpen, setDeactivateOpen] = useState(false);
    const [isDeactivating, setIsDeactivating] = useState(false);

    const assignForm = useForm({
        department_id: employee.department?.id?.toString() ?? '',
        shift_id: employee.shift?.id?.toString() ?? '',
    });

    const managerName = employee.manager
        ? (employee.manager.name ?? `${employee.manager.first_name} ${employee.manager.last_name}`)
        : '—';

    const joinedLabel = formatDate(employee.hire_date);
    const yearsLabel =
        employee.years_of_service === null || employee.years_of_service === undefined
            ? '—'
            : `${employee.years_of_service} ${employee.years_of_service === 1 ? 'year' : 'years'}`;

    const buildUpdatePayload = (overrides: Record<string, unknown> = {}) => ({
        employee_code: employee.employee_code,
        first_name: employee.first_name,
        last_name: employee.last_name,
        display_name: employee.display_name ?? '',
        gender: employee.gender ?? '',
        date_of_birth: employee.date_of_birth ? employee.date_of_birth.slice(0, 10) : '',
        department_id: employee.department?.id ?? null,
        role_id: employee.role?.id ?? null,
        shift_id: employee.shift?.id ?? null,
        job_title: employee.job_title ?? '',
        manager_id: employee.manager?.id ?? null,
        email: employee.email ?? '',
        phone: employee.phone ?? '',
        mobile: employee.mobile ?? '',
        address: employee.address ?? '',
        employment_type: employee.employment_type,
        hire_date: employee.hire_date ? employee.hire_date.slice(0, 10) : '',
        status: employee.status,
        create_login: !!employee.user_id,
        login_email: employee.user?.email ?? '',
        ...overrides,
    });

    const submitAssign = (e: React.FormEvent) => {
        e.preventDefault();
        router.put(
            `/employees/${employee.id}`,
            buildUpdatePayload({
                department_id: assignForm.data.department_id || null,
                shift_id: assignForm.data.shift_id || null,
            }),
            {
                preserveScroll: true,
                onSuccess: () => setAssignOpen(false),
            },
        );
    };

    const confirmDeactivate = () => {
        setIsDeactivating(true);
        router.put(`/employees/${employee.id}`, buildUpdatePayload({ status: 'Inactive' }), {
            preserveScroll: true,
            onFinish: () => {
                setIsDeactivating(false);
                setDeactivateOpen(false);
            },
        });
    };

    const activateEmployee = () => {
        router.put(`/employees/${employee.id}`, buildUpdatePayload({ status: 'Active' }), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={`${employee.name} - Profile`} />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" asChild className="h-8">
                        <Link href="/employees">
                            <ArrowLeft className="mr-2 size-4" />
                            Back to Directory
                        </Link>
                    </Button>
                </div>

                <div className="rounded-2xl border border-border/40 bg-card p-6 shadow-sm">
                    <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                        <div className="flex items-start gap-5">
                            <Avatar className="size-20 border-2 border-border shadow-sm">
                                {(employee.photo_url || employee.photo_path) && (
                                    <AvatarImage
                                        src={employee.photo_url || `/storage/${employee.photo_path}`}
                                        alt={employee.name}
                                    />
                                )}
                                <AvatarFallback className="bg-primary/5 text-xl font-bold text-primary">
                                    {getInitials(employee.first_name, employee.last_name)}
                                </AvatarFallback>
                            </Avatar>

                            <div className="space-y-3">
                                <div className="space-y-1">
                                    <h1 className="text-2xl font-bold tracking-tight">{employee.name}</h1>
                                    <p className="font-mono text-sm text-muted-foreground">{employee.employee_code}</p>
                                    <p className="text-sm font-medium text-foreground">
                                        {employee.job_title || employee.role?.name || 'No job title'}
                                    </p>
                                    <div className="flex items-center gap-2 pt-0.5 text-sm">
                                        <span className={`inline-block size-2 rounded-full ${statusDotClass(employee.status)}`} />
                                        <span className="font-medium">{employee.status}</span>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-x-8 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                    <div>
                                        <span className="text-muted-foreground">Department</span>
                                        <p className="font-medium">{employee.department?.name ?? '—'}</p>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Shift</span>
                                        <p className="font-medium">{employee.shift?.name ?? '—'}</p>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Reporting Manager</span>
                                        <p className="font-medium">{managerName}</p>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Joined</span>
                                        <p className="font-medium">{joinedLabel}</p>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Years of Service</span>
                                        <p className="font-medium">{yearsLabel}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {can('employees.update') && (
                                <>
                                    <Button variant="outline" asChild>
                                        <Link href={`/employees?edit=${employee.id}`}>Edit</Link>
                                    </Button>
                                    <Button variant="outline" onClick={() => setAssignOpen(true)}>
                                        Assign
                                    </Button>
                                    {employee.status === 'Active' ? (
                                        <Button variant="destructive" onClick={() => setDeactivateOpen(true)}>
                                            Deactivate
                                        </Button>
                                    ) : (
                                        <Button onClick={activateEmployee}>Activate</Button>
                                    )}
                                </>
                            )}
                        </div>
                    </div>
                </div>

                <div className="flex gap-4 overflow-x-auto border-b border-border/40">
                    {[
                        { id: 'overview' as const, label: 'Overview', icon: User },
                        { id: 'assignments' as const, label: 'Assignments', icon: ClipboardList },
                        { id: 'permissions' as const, label: 'Permissions', icon: Shield },
                        { id: 'activity' as const, label: 'Activity', icon: Award },
                        { id: 'documents' as const, label: 'Documents', icon: FileText },
                    ].map((tab) => {
                        const Icon = tab.icon;
                        const active = activeTab === tab.id;

                        return (
                            <button
                                key={tab.id}
                                type="button"
                                onClick={() => setActiveTab(tab.id)}
                                className={`flex items-center gap-2 whitespace-nowrap border-b-2 px-1 py-3 text-sm font-semibold transition-all focus:outline-none ${
                                    active
                                        ? 'border-primary text-primary'
                                        : 'border-transparent text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                <Icon className="size-4 shrink-0" />
                                {tab.label}
                            </button>
                        );
                    })}
                </div>

                <div className="min-h-[360px]">
                    {activeTab === 'overview' && (
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <Card className="border-border/40 shadow-sm">
                                <CardHeader>
                                    <CardTitle className="text-base font-bold">Employment</CardTitle>
                                </CardHeader>
                                <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <InfoItem label="Employee Code" value={employee.employee_code} />
                                    <InfoItem label="Department" value={employee.department?.name ?? '—'} />
                                    <InfoItem label="Role" value={employee.role?.name ?? '—'} />
                                    <InfoItem label="Shift" value={employee.shift?.name ?? '—'} />
                                    <InfoItem label="Plant" value={employee.plant?.name ?? '—'} />
                                    <InfoItem label="Employment Type" value={employee.employment_type} />
                                    <InfoItem label="Reporting Manager" value={managerName} />
                                    <InfoItem label="Join Date" value={joinedLabel} />
                                    <InfoItem
                                        label="Current Status"
                                        value={
                                            <Badge variant="outline" className="gap-1.5 font-medium">
                                                <span className={`size-1.5 rounded-full ${statusDotClass(employee.status)}`} />
                                                {employee.status}
                                            </Badge>
                                        }
                                    />
                                </CardContent>
                            </Card>

                            <Card className="border-border/40 shadow-sm">
                                <CardHeader>
                                    <CardTitle className="text-base font-bold">Personal</CardTitle>
                                </CardHeader>
                                <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <InfoItem label="Phone" value={employee.phone || employee.mobile || '—'} />
                                    <InfoItem label="Email" value={employee.email ?? '—'} />
                                    <InfoItem label="Address" value={employee.address ?? '—'} />
                                    <InfoItem label="DOB" value={formatDate(employee.date_of_birth)} />
                                    <InfoItem label="Gender" value={employee.gender ?? '—'} />
                                </CardContent>
                            </Card>
                        </div>
                    )}

                    {activeTab !== 'overview' && (
                        <Card className="border-border/40 shadow-sm">
                            <CardContent className="flex min-h-[240px] items-center justify-center p-8 text-sm text-muted-foreground">
                                {activeTab === 'assignments' && 'Assignment history will appear here once production modules are connected.'}
                                {activeTab === 'permissions' && 'System permissions are managed through the linked login role.'}
                                {activeTab === 'activity' && 'Attendance and activity history will appear here.'}
                                {activeTab === 'documents' && 'Employee documents will appear here.'}
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>

            <Dialog open={assignOpen} onOpenChange={setAssignOpen}>
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={submitAssign}>
                        <DialogHeader>
                            <DialogTitle>Assign</DialogTitle>
                            <DialogDescription>Update department and shift for this employee.</DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 py-4">
                            <div className="space-y-2">
                                <Label>Department</Label>
                                <Select
                                    value={assignForm.data.department_id || undefined}
                                    onValueChange={(value) => assignForm.setData('department_id', value)}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select department" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {departments
                                            .filter((d) => d.status === 'Active' || d.id === employee.department?.id)
                                            .map((department) => (
                                                <SelectItem key={department.id} value={department.id.toString()}>
                                                    {department.name}
                                                </SelectItem>
                                            ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Shift</Label>
                                <Select
                                    value={assignForm.data.shift_id || undefined}
                                    onValueChange={(value) => assignForm.setData('shift_id', value)}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select shift" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {shifts
                                            .filter((s) => s.status === 'Active' || s.id === employee.shift?.id)
                                            .map((shift) => (
                                                <SelectItem key={shift.id} value={shift.id.toString()}>
                                                    {shift.name}
                                                </SelectItem>
                                            ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <ModalButtons
                            cancelLabel="Cancel"
                            saveLabel="Save"
                            onCancel={() => setAssignOpen(false)}
                            processing={false}
                        />
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDeleteDialog
                open={deactivateOpen}
                onOpenChange={setDeactivateOpen}
                title="Deactivate employee?"
                description={`${employee.name} will be marked Inactive and will not be able to sign in or appear in assignment dropdowns.`}
                confirmLabel="Deactivate"
                processing={isDeactivating}
                onConfirm={confirmDeactivate}
            />
        </>
    );
}
