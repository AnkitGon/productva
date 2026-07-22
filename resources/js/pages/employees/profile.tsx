import { Head, Link, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import { 
    User, Mail, Phone, Calendar, Shield, Award, ClipboardList, 
    FileText, ArrowLeft, Building2, MapPin, Edit3, Trash2, Key, CheckCircle, Clock3, Moon
} from 'lucide-react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';

interface Employee {
    id: number;
    employee_code: string;
    first_name: string;
    last_name: string;
    display_name: string | null;
    name: string;
    photo_path: string | null;
    photo_url?: string | null;
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
    shift: {
        id: number;
        name: string;
        code: string;
        start_time: string;
        end_time: string;
        break_minutes: number;
        grace_in_minutes: number;
        grace_out_minutes: number;
        overnight: boolean;
        working_minutes: number;
        hours_label: string;
        status: string;
        notes: string | null;
        is_currently_active?: boolean;
    } | null;
    manager: { id: number; first_name: string; last_name: string; name?: string } | null;
    user: { id: number; name: string; email: string; roles: Array<{ id: number; name: string; slug: string }> } | null;
}

interface Props {
    employee: Employee;
}

export default function EmployeeProfile({ employee }: Props) {
    const [activeTab, setActiveTab] = useState<'overview' | 'assignments' | 'permissions' | 'activity' | 'documents'>('overview');
    const [isEditOpen, setIsEditOpen] = useState(false);

    const editForm = useForm({
        employee_code: employee.employee_code,
        first_name: employee.first_name,
        last_name: employee.last_name,
        display_name: employee.display_name || '',
        plant_id: employee.plant?.id.toString() || '',
        department_id: employee.department?.id.toString() || '',
        job_title: employee.job_title || '',
        manager_id: employee.manager?.id.toString() || '',
        email: employee.email || '',
        phone: employee.phone || '',
        mobile: employee.mobile || '',
        employment_type: employee.employment_type,
        hire_date: employee.hire_date || '',
        status: employee.status,

        create_login: !!employee.user_id,
        login_email: employee.user?.email || '',
        login_role_id: employee.user?.roles?.[0]?.id.toString() || '',
        login_password: '',
    });

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'Active':
                return 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20';
            case 'Inactive':
                return 'bg-neutral-500/10 text-neutral-500 border border-neutral-500/20';
            case 'On Leave':
                return 'bg-amber-500/10 text-amber-500 border border-amber-500/20';
            case 'Terminated':
                return 'bg-rose-500/10 text-rose-500 border border-rose-500/20';
            default:
                return 'bg-primary/10 text-primary';
        }
    };

    const getInitials = (firstName: string, lastName: string) => {
        return `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase();
    };

    const formatTime = (value?: string | null) => {
        if (!value) {
            return '—';
        }

        const match = value.match(/^(\d{1,2}):(\d{2})/);
        if (!match) {
            return value;
        }

        let hours = Number(match[1]);
        const minutes = match[2];
        const suffix = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;

        return `${hours}:${minutes} ${suffix}`;
    };

    return (
        <>
            <Head title={`${employee.name} - Profile`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Back Link */}
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" asChild className="h-8">
                        <Link href="/employees">
                            <ArrowLeft className="size-4 mr-2" />
                            Back to Directory
                        </Link>
                    </Button>
                </div>

                {/* Profile Header Card */}
                <div className="bg-card border border-border/40 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                    <div className="flex items-center gap-5">
                        <Avatar className="size-20 border-2 border-border shadow-sm">
                            {(employee.photo_url || employee.photo_path) && (
                                <AvatarImage
                                    src={employee.photo_url || `/storage/${employee.photo_path}`}
                                    alt={employee.name}
                                />
                            )}
                            <AvatarFallback className="bg-primary/5 text-primary text-xl font-bold">
                                {getInitials(employee.first_name, employee.last_name)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="space-y-1.5">
                            <div className="flex items-center gap-2.5 flex-wrap">
                                <h1 className="text-2xl font-bold tracking-tight">{employee.name}</h1>
                                <Badge className={`shadow-none font-medium px-2 py-0.5 rounded ${getStatusColor(employee.status)}`}>
                                    {employee.status}
                                </Badge>
                                <Badge variant="outline" className="font-mono text-xs">
                                    {employee.employee_code}
                                </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground font-medium flex items-center gap-1.5 flex-wrap">
                                <Building2 className="size-4 shrink-0" />
                                {employee.job_title || 'No Job Title'} &bull; {employee.department?.name || 'Unassigned Department'}
                                {employee.shift && (
                                    <>
                                        &bull;
                                        <span className="inline-flex items-center gap-1">
                                            <Clock3 className="size-3.5 shrink-0" />
                                            {employee.shift.name}
                                        </span>
                                    </>
                                )}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Tab Navigation */}
                <div className="flex border-b border-border/40 gap-4 overflow-x-auto">
                    {[
                        { id: 'overview', label: 'Overview', icon: User },
                        { id: 'assignments', label: 'Assignments', icon: ClipboardList },
                        { id: 'permissions', label: 'Permissions', icon: Shield },
                        { id: 'activity', label: 'Activity', icon: Award },
                        { id: 'documents', label: 'Documents', icon: FileText },
                    ].map(tab => {
                        const Icon = tab.icon;
                        const active = activeTab === tab.id;
                        return (
                            <button
                                key={tab.id}
                                onClick={() => setActiveTab(tab.id as any)}
                                className={`flex items-center gap-2 py-3 px-1 border-b-2 font-semibold text-sm transition-all focus:outline-none whitespace-nowrap ${
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

                {/* Tab Content */}
                <div className="min-h-[400px]">
                    {activeTab === 'overview' && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <Card className="border-border/40 shadow-sm">
                                <CardHeader>
                                    <CardTitle className="text-md font-bold">General Information</CardTitle>
                                    <CardDescription className="text-xs">Basic profile and administrative info</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <span className="text-xs font-semibold text-muted-foreground uppercase">Employee Code</span>
                                            <p className="font-semibold text-sm mt-1">{employee.employee_code}</p>
                                        </div>
                                        <div>
                                            <span className="text-xs font-semibold text-muted-foreground uppercase">Display Name</span>
                                            <p className="text-sm mt-1">{employee.display_name || '—'}</p>
                                        </div>
                                        <div>
                                            <span className="text-xs font-semibold text-muted-foreground uppercase">First Name</span>
                                            <p className="text-sm mt-1">{employee.first_name}</p>
                                        </div>
                                        <div>
                                            <span className="text-xs font-semibold text-muted-foreground uppercase">Last Name</span>
                                            <p className="text-sm mt-1">{employee.last_name}</p>
                                        </div>
                                        <div>
                                            <span className="text-xs font-semibold text-muted-foreground uppercase">Employment Type</span>
                                            <p className="text-sm mt-1">{employee.employment_type}</p>
                                        </div>
                                        <div>
                                            <span className="text-xs font-semibold text-muted-foreground uppercase">Hire Date</span>
                                            <p className="text-sm mt-1">{employee.hire_date || '—'}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border-border/40 shadow-sm">
                                <CardHeader>
                                    <CardTitle className="text-md font-bold">Contact Details</CardTitle>
                                    <CardDescription className="text-xs">Communication routes and numbers</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="space-y-3">
                                        <div className="flex items-center gap-3">
                                            <Mail className="size-4 text-muted-foreground" />
                                            <div>
                                                <span className="text-xs font-semibold text-muted-foreground uppercase">Email</span>
                                                <p className="text-sm mt-0.5">{employee.email || '—'}</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Phone className="size-4 text-muted-foreground" />
                                            <div>
                                                <span className="text-xs font-semibold text-muted-foreground uppercase">Phone</span>
                                                <p className="text-sm mt-0.5">{employee.phone || '—'}</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Phone className="size-4 text-muted-foreground" />
                                            <div>
                                                <span className="text-xs font-semibold text-muted-foreground uppercase">Mobile</span>
                                                <p className="text-sm mt-0.5">{employee.mobile || '—'}</p>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    )}

                    {activeTab === 'assignments' && (
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <Card className="border-border/40 shadow-sm">
                                <CardHeader>
                                    <CardTitle className="text-md font-bold">Organizational Assignments</CardTitle>
                                    <CardDescription className="text-xs">Supervisor hierarchy and reporting</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div className="space-y-4">
                                            <div>
                                                <span className="text-xs font-semibold text-muted-foreground uppercase">Plant Location</span>
                                                <p className="text-sm font-semibold mt-1 flex items-center gap-1.5">
                                                    <MapPin className="size-4 text-muted-foreground" />
                                                    {employee.plant?.name || '—'}
                                                </p>
                                            </div>
                                            <div>
                                                <span className="text-xs font-semibold text-muted-foreground uppercase">Department</span>
                                                <p className="text-sm font-semibold mt-1">{employee.department?.name || '—'}</p>
                                            </div>
                                        </div>
                                        <div className="space-y-4">
                                            <div>
                                                <span className="text-xs font-semibold text-muted-foreground uppercase">Reporting Manager</span>
                                                <p className="text-sm font-semibold mt-1 flex items-center gap-2">
                                                    <Avatar className="size-6">
                                                        <AvatarFallback className="text-[10px] bg-primary/5 text-primary">
                                                            {employee.manager ? getInitials(employee.manager.first_name, employee.manager.last_name) : '—'}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    {employee.manager ? `${employee.manager.first_name} ${employee.manager.last_name}` : 'No assigned manager'}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border-border/40 shadow-sm">
                                <CardHeader>
                                    <CardTitle className="text-md font-bold">Shift Details</CardTitle>
                                    <CardDescription className="text-xs">Assigned working hours for the active plant</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {employee.shift ? (
                                        <div className="space-y-4">
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <p className="text-sm font-semibold">{employee.shift.name}</p>
                                                    <p className="text-xs font-mono text-muted-foreground mt-0.5">{employee.shift.code}</p>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    {employee.shift.is_currently_active && (
                                                        <Badge className="shadow-none bg-emerald-500/10 text-emerald-600 border border-emerald-500/20">
                                                            Active now
                                                        </Badge>
                                                    )}
                                                    <Badge variant="outline">{employee.shift.status}</Badge>
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <span className="text-xs font-semibold text-muted-foreground uppercase block">Start</span>
                                                    <p className="text-sm font-medium mt-1">{formatTime(employee.shift.start_time)}</p>
                                                </div>
                                                <div>
                                                    <span className="text-xs font-semibold text-muted-foreground uppercase block">End</span>
                                                    <p className="text-sm font-medium mt-1 flex items-center gap-1.5">
                                                        <span>{formatTime(employee.shift.end_time)}</span>
                                                        {employee.shift.overnight && (
                                                            <Moon className="size-3.5 shrink-0 text-muted-foreground" />
                                                        )}
                                                    </p>
                                                </div>
                                                <div>
                                                    <span className="text-xs font-semibold text-muted-foreground uppercase">Net Hours</span>
                                                    <p className="text-sm font-medium mt-1">{employee.shift.hours_label}</p>
                                                </div>
                                                <div>
                                                    <span className="text-xs font-semibold text-muted-foreground uppercase">Break</span>
                                                    <p className="text-sm font-medium mt-1">{employee.shift.break_minutes} min</p>
                                                </div>
                                                <div>
                                                    <span className="text-xs font-semibold text-muted-foreground uppercase">Grace In</span>
                                                    <p className="text-sm font-medium mt-1">{employee.shift.grace_in_minutes} min</p>
                                                </div>
                                                <div>
                                                    <span className="text-xs font-semibold text-muted-foreground uppercase">Grace Out</span>
                                                    <p className="text-sm font-medium mt-1">{employee.shift.grace_out_minutes} min</p>
                                                </div>
                                            </div>

                                            {employee.shift.notes && (
                                                <div>
                                                    <span className="text-xs font-semibold text-muted-foreground uppercase">Notes</span>
                                                    <p className="text-sm mt-1 text-muted-foreground">{employee.shift.notes}</p>
                                                </div>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="flex flex-col items-center justify-center p-8 text-center text-muted-foreground gap-2">
                                            <Clock3 className="size-8 opacity-30" />
                                            <p className="font-semibold text-sm">No shift assigned</p>
                                            <p className="text-xs">Assign a shift from the employee edit form.</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    )}

                    {activeTab === 'permissions' && (
                        <Card className="border-border/40 shadow-sm">
                            <CardHeader>
                                <CardTitle className="text-md font-bold">System Access & Permissions</CardTitle>
                                <CardDescription className="text-xs">Security context and logged-in user credentials</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {employee.user_id ? (
                                    <div className="space-y-4">
                                        <div className="bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-500 p-4 rounded-xl flex items-start gap-3">
                                            <CheckCircle className="size-5 shrink-0 mt-0.5" />
                                            <div>
                                                <h4 className="font-bold text-sm">Linked User Account Enabled</h4>
                                                <p className="text-xs mt-1">This employee can log in to Productva using their organizational credentials.</p>
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                                            <div>
                                                <span className="text-xs font-semibold text-muted-foreground uppercase">Login Email Address</span>
                                                <p className="text-sm font-medium mt-1">{employee.user?.email}</p>
                                            </div>
                                            <div>
                                                <span className="text-xs font-semibold text-muted-foreground uppercase">Assigned Security Role</span>
                                                <p className="text-sm font-semibold mt-1 text-primary">
                                                    {employee.user?.roles?.[0]?.name || 'User'}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex flex-col items-center justify-center p-8 text-center text-muted-foreground gap-2">
                                        <Key className="size-8 opacity-30" />
                                        <p className="font-semibold text-sm">No System Access Account</p>
                                        <p className="text-xs">This employee does not currently have permissions to log in to the Productva interface.</p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {activeTab === 'activity' && (
                        <Card className="border-border/40 shadow-sm">
                            <CardHeader>
                                <CardTitle className="text-md font-bold">Operational History</CardTitle>
                                <CardDescription className="text-xs">Work orders and manufacturing interactions</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-col items-center justify-center p-12 text-center text-muted-foreground gap-2">
                                    <ClipboardList className="size-10 opacity-30" />
                                    <p className="font-semibold text-sm">No recorded operational history</p>
                                    <p className="text-xs">Historical tasks and work logs will be shown once production commences.</p>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {activeTab === 'documents' && (
                        <Card className="border-border/40 shadow-sm">
                            <CardHeader>
                                <CardTitle className="text-md font-bold">Certifications & Documents</CardTitle>
                                <CardDescription className="text-xs">Professional licenses, logs, and compliance records</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-col items-center justify-center p-12 text-center text-muted-foreground gap-2">
                                    <Award className="size-10 opacity-30" />
                                    <p className="font-semibold text-sm">No documents uploaded</p>
                                    <p className="text-xs">Upload professional certifications, training modules, or health checklists.</p>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}

EmployeeProfile.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'Employees Directory',
            href: '/employees',
        },
        {
            title: 'Employee Profile',
            href: '#',
        },
    ],
};
