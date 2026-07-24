import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Building2, Cog, Factory, Users } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/data-table/status-badge';
import { useCan } from '@/hooks/use-can';

interface DepartmentDetail {
    id: number;
    name: string;
    code: string;
    description: string | null;
    status: string;
    manager?: { id: number; name: string; email: string } | null;
    employees_count: number;
    work_centers_count: number;
    machines_count: number;
}

interface Props {
    department: DepartmentDetail;
}

function MetricLink({
    href,
    label,
    value,
    icon: Icon,
    enabled,
}: {
    href: string;
    label: string;
    value: number;
    icon: typeof Users;
    enabled: boolean;
}) {
    const content = (
        <>
            <div className="flex items-center justify-between gap-3">
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
                <Icon className="size-4 text-muted-foreground" />
            </div>
            <p className="mt-3 text-3xl font-bold tracking-tight text-foreground">{value}</p>
        </>
    );

    if (!enabled) {
        return (
            <div className="rounded-xl border border-border/50 bg-card p-5 shadow-sm">
                {content}
            </div>
        );
    }

    return (
        <Link
            href={href}
            className="rounded-xl border border-border/50 bg-card p-5 shadow-sm transition-colors hover:border-primary/40 hover:bg-accent/30"
        >
            {content}
        </Link>
    );
}

export default function DepartmentsShow({ department }: Props) {
    const { can } = useCan();

    return (
        <>
            <Head title={`${department.name} - Department`} />

            <div className="flex flex-col gap-6 p-6 w-full max-w-5xl mx-auto">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" asChild className="h-8">
                        <Link href="/departments">
                            <ArrowLeft className="size-4 mr-2" />
                            Back to Departments
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-2 min-w-0">
                        <div className="flex items-center gap-3 flex-wrap">
                            <h1 className="text-2xl font-bold tracking-tight">{department.name}</h1>
                            <StatusBadge status={department.status} />
                        </div>
                        <p className="text-sm text-muted-foreground font-mono">{department.code}</p>
                        {department.manager && (
                            <p className="text-sm text-muted-foreground">
                                Manager: <span className="text-foreground font-medium">{department.manager.name}</span>
                            </p>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <MetricLink
                        href={`/employees?department_id=${department.id}`}
                        label="Employees"
                        value={department.employees_count}
                        icon={Users}
                        enabled={can('employees.view')}
                    />
                    <MetricLink
                        href={`/work-centers?department_id=${department.id}`}
                        label="Work Centers"
                        value={department.work_centers_count}
                        icon={Factory}
                        enabled={can('work-centers.view')}
                    />
                    <MetricLink
                        href={`/machines?department_id=${department.id}`}
                        label="Machines"
                        value={department.machines_count}
                        icon={Cog}
                        enabled={can('machine.view')}
                    />
                </div>

                <div className="rounded-xl border border-border/50 bg-card p-5 shadow-sm space-y-3">
                    <div className="flex items-center gap-2 text-muted-foreground">
                        <Building2 className="size-4" />
                        <h2 className="text-sm font-semibold uppercase tracking-wide">Description</h2>
                    </div>
                    <p className="text-sm text-foreground whitespace-pre-wrap">
                        {department.description?.trim() ? department.description : 'No description provided.'}
                    </p>
                </div>
            </div>
        </>
    );
}

DepartmentsShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Departments', href: '/departments' },
        { title: 'Details', href: '#' },
    ],
};
