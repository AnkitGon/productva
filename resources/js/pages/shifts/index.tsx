import { Head, router, useForm } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';
import { Clock3, Edit2, Moon, Plus, Trash2 } from 'lucide-react';

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
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { ModalButtons } from '@/components/modal-buttons';
import { DataTable, type ColumnDef, type TableMeta } from '@/components/data-table/data-table';
import { StatusBadge } from '@/components/data-table/status-badge';
import type { RowAction } from '@/components/data-table/data-table-row-actions';
import { useCan } from '@/hooks/use-can';

interface ShiftTemplate {
    name: string;
    code: string;
    start_time: string;
    end_time: string;
    overnight: boolean;
    break_minutes: number;
}

interface Shift {
    id: number;
    code: string;
    name: string;
    start_time: string;
    end_time: string;
    break_minutes: number;
    grace_in_minutes: number;
    grace_out_minutes: number;
    overnight: boolean;
    working_minutes: number;
    hours_label: string;
    status: 'Active' | 'Inactive';
    notes: string | null;
    is_currently_active?: boolean;
    employees_count?: number;
}

interface PaginatedShifts {
    data: Shift[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    shifts: PaginatedShifts;
    filters: Record<string, string>;
    templates: ShiftTemplate[];
}

type ShiftFormData = {
    code: string;
    name: string;
    start_time: string;
    end_time: string;
    overnight: boolean;
    break_minutes: string;
    grace_in_minutes: string;
    grace_out_minutes: string;
    status: string;
    notes: string;
};

function formatTime(value: string): string {
    return value?.length >= 5 ? value.slice(0, 5) : value;
}

function formatTimeDisplay(value: string): string {
    const normalized = formatTime(value);
    const match = normalized.match(/^(\d{1,2}):(\d{2})$/);
    if (!match) {
        return normalized || '—';
    }

    let hours = Number(match[1]);
    const minutes = match[2];
    const suffix = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;

    return `${hours}:${minutes} ${suffix}`;
}

function emptyForm(): ShiftFormData {
    return {
        code: '',
        name: '',
        start_time: '06:00',
        end_time: '14:00',
        overnight: false,
        break_minutes: '60',
        grace_in_minutes: '10',
        grace_out_minutes: '5',
        status: 'Active',
        notes: '',
    };
}

function formFromShift(shift: Shift): ShiftFormData {
    return {
        code: shift.code,
        name: shift.name,
        start_time: formatTime(shift.start_time),
        end_time: formatTime(shift.end_time),
        overnight: shift.overnight,
        break_minutes: String(shift.break_minutes ?? 0),
        grace_in_minutes: String(shift.grace_in_minutes ?? 0),
        grace_out_minutes: String(shift.grace_out_minutes ?? 0),
        status: shift.status,
        notes: shift.notes ?? '',
    };
}

export default function ShiftsIndex({ shifts, filters, templates }: Props) {
    const { can } = useCan();

    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingShift, setEditingShift] = useState<Shift | null>(null);
    const [deleteConfirmShift, setDeleteConfirmShift] = useState<Shift | null>(null);

    const form = useForm<ShiftFormData>(emptyForm());

    const currentParams: Record<string, string> = {};
    Object.entries(filters ?? {}).forEach(([k, v]) => {
        if (v) {
            currentParams[k] = v;
        }
    });

    const previewHours = useMemo(() => {
        const start = form.data.start_time;
        const end = form.data.end_time;
        if (!start || !end || start === end) {
            return '—';
        }

        const [sh, sm] = start.split(':').map(Number);
        const [eh, em] = end.split(':').map(Number);
        let startMins = sh * 60 + sm;
        let endMins = eh * 60 + em;
        if (form.data.overnight || endMins <= startMins) {
            endMins += 24 * 60;
        }
        const duration = endMins - startMins;
        const breakMins = Number(form.data.break_minutes || 0);
        const working = Math.max(0, duration - breakMins);
        const hours = Math.floor(working / 60);
        const mins = working % 60;
        return mins === 0 ? `${hours}h` : `${hours}h ${String(mins).padStart(2, '0')}m`;
    }, [form.data.start_time, form.data.end_time, form.data.overnight, form.data.break_minutes]);

    const openCreate = () => {
        setEditingShift(null);
        form.setData(emptyForm());
        form.clearErrors();
        setIsDialogOpen(true);
    };

    const openEdit = (shift: Shift) => {
        setEditingShift(shift);
        form.setData(formFromShift(shift));
        form.clearErrors();
        setIsDialogOpen(true);
    };

    const applyTemplate = (template: ShiftTemplate) => {
        form.setData({
            ...form.data,
            name: template.name,
            code: template.code,
            start_time: template.start_time,
            end_time: template.end_time,
            overnight: template.overnight,
            break_minutes: String(template.break_minutes),
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const config = {
            onSuccess: () => {
                setIsDialogOpen(false);
                form.reset();
            },
        };

        if (editingShift) {
            form.put(`/shifts/${editingShift.id}`, config);
        } else {
            form.post('/shifts', config);
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
        router.get('/shifts', params, { preserveState: true, replace: true });
    };

    const columns: ColumnDef<Shift>[] = [
        {
            key: 'code',
            label: 'Code',
            sortable: true,
            render: (row) => <span className="font-mono text-xs font-semibold">{row.code}</span>,
        },
        {
            key: 'name',
            label: 'Shift',
            sortable: true,
            render: (row) => (
                <div className="flex flex-col gap-0.5">
                    <span className="font-semibold text-foreground">{row.name}</span>
                    {row.is_currently_active && (
                        <span className="text-[10px] font-medium text-emerald-600 dark:text-emerald-400">Currently active</span>
                    )}
                </div>
            ),
        },
        {
            key: 'start_time',
            label: 'Start',
            sortable: true,
            render: (row) => formatTimeDisplay(row.start_time),
        },
        {
            key: 'end_time',
            label: 'End',
            sortable: true,
            render: (row) => (
                <span className="flex items-center gap-1.5">
                    <span>{formatTimeDisplay(row.end_time)}</span>
                    {row.overnight && <Moon className="size-3 shrink-0 text-muted-foreground" />}
                </span>
            ),
        },
        {
            key: 'working_minutes',
            label: 'Hours',
            sortable: true,
            render: (row) => row.hours_label,
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
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (row) => <StatusBadge status={row.status} />,
        },
    ];

    const meta: TableMeta = {
        current_page: shifts.current_page,
        last_page: shifts.last_page,
        per_page: shifts.per_page,
        total: shifts.total,
        from: shifts.from ?? 1,
        to: shifts.to ?? shifts.data.length,
    };

    const filterSlot = (
        <>
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

            <Select value={currentParams.overnight ?? 'all'} onValueChange={(v) => makeFilterChange('overnight', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[120px] border-dashed">
                    <SelectValue placeholder="Overnight" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Any Schedule</SelectItem>
                    <SelectItem value="1">Overnight</SelectItem>
                    <SelectItem value="0">Same day</SelectItem>
                </SelectContent>
            </Select>

            <Input
                type="time"
                className="h-8 w-[130px] text-xs border-dashed"
                value={currentParams.start_time ?? ''}
                onChange={(e) => makeFilterChange('start_time', e.target.value)}
                title="Filter by start time"
            />
        </>
    );

    return (
        <>
            <Head title="Shifts" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Shifts</h1>
                        <p className="text-sm text-muted-foreground">
                            Define when work happens at the active plant, including breaks and grace periods.
                        </p>
                    </div>
                </div>

                <DataTable
                    tableId="shifts"
                    columns={columns}
                    data={shifts.data}
                    meta={meta}
                    baseUrl="/shifts"
                    currentParams={currentParams}
                    searchPlaceholder="Search by code, name, notes…"
                    entityLabel="shifts"
                    emptyStateIcon={Clock3}
                    emptyStateTitle="No shifts found"
                    emptyStateDescription={
                        can('shift.create')
                            ? 'Create your first shift or start from a template.'
                            : 'No shifts are available for this plant.'
                    }
                    emptyStateAction={
                        can('shift.create') ? (
                            <Button size="sm" onClick={openCreate} className="gap-2">
                                <Plus className="size-3.5" />
                                Create Shift
                            </Button>
                        ) : undefined
                    }
                    primaryAction={
                        can('shift.create') ? (
                            <Button onClick={openCreate} className="gap-2 h-9 font-semibold text-xs">
                                <Plus className="size-4" />
                                Add Shift
                            </Button>
                        ) : undefined
                    }
                    filterSlot={filterSlot}
                    rowActions={(row) => {
                        const actions: RowAction<Shift>[] = [];

                        if (can('shift.update')) {
                            actions.push({
                                label: 'Edit',
                                icon: Edit2,
                                onClick: openEdit,
                            });
                        }

                        if (can('shift.delete') && (row.employees_count ?? 0) === 0) {
                            actions.push({
                                label: 'Archive',
                                icon: Trash2,
                                onClick: (r) => setDeleteConfirmShift(r),
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
                        setEditingShift(null);
                        form.reset();
                    }
                }}
            >
                <DialogContent className="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-bold">
                            {editingShift ? 'Edit Shift' : 'Add Shift'}
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            Set working hours, overnight flag, and grace periods for the active plant.
                        </DialogDescription>
                    </DialogHeader>

                    {!editingShift && (
                        <div className="flex flex-wrap gap-2 pb-2">
                            {templates.map((template) => (
                                <Button
                                    key={template.code}
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="h-8 text-xs"
                                    onClick={() => applyTemplate(template)}
                                >
                                    {template.name}
                                </Button>
                            ))}
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">
                                    Shift Name <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    placeholder="Morning Shift"
                                    required
                                    autoFocus
                                />
                                <InputError message={form.errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="code">
                                    Shift Code <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="code"
                                    value={form.data.code}
                                    onChange={(e) => form.setData('code', e.target.value.toUpperCase())}
                                    placeholder="MORN"
                                    maxLength={20}
                                    required
                                />
                                <InputError message={form.errors.code} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="start_time">
                                    Start Time <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="start_time"
                                    type="time"
                                    value={form.data.start_time}
                                    onChange={(e) => form.setData('start_time', e.target.value)}
                                    required
                                />
                                <InputError message={form.errors.start_time} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="end_time">
                                    End Time <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="end_time"
                                    type="time"
                                    value={form.data.end_time}
                                    onChange={(e) => form.setData('end_time', e.target.value)}
                                    required
                                />
                                <InputError message={form.errors.end_time} />
                            </div>

                            <div className="flex items-center gap-2 sm:col-span-2 pt-1">
                                <Checkbox
                                    id="overnight"
                                    checked={form.data.overnight}
                                    onCheckedChange={(checked) => form.setData('overnight', checked === true)}
                                />
                                <Label htmlFor="overnight" className="cursor-pointer">
                                    Overnight Shift
                                </Label>
                            </div>
                            <InputError message={form.errors.overnight} />

                            <div className="space-y-2">
                                <Label htmlFor="break_minutes">Break (minutes)</Label>
                                <Input
                                    id="break_minutes"
                                    type="number"
                                    min={0}
                                    value={form.data.break_minutes}
                                    onChange={(e) => form.setData('break_minutes', e.target.value)}
                                />
                                <InputError message={form.errors.break_minutes} />
                            </div>

                            <div className="space-y-2">
                                <Label>Net working hours</Label>
                                <div className="h-9 px-3 rounded-md border border-border/60 bg-muted/30 text-sm flex items-center font-medium">
                                    {previewHours}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="grace_in_minutes">Grace In</Label>
                                <Input
                                    id="grace_in_minutes"
                                    type="number"
                                    min={0}
                                    value={form.data.grace_in_minutes}
                                    onChange={(e) => form.setData('grace_in_minutes', e.target.value)}
                                />
                                <InputError message={form.errors.grace_in_minutes} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="grace_out_minutes">Grace Out</Label>
                                <Input
                                    id="grace_out_minutes"
                                    type="number"
                                    min={0}
                                    value={form.data.grace_out_minutes}
                                    onChange={(e) => form.setData('grace_out_minutes', e.target.value)}
                                />
                                <InputError message={form.errors.grace_out_minutes} />
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
                                <Label htmlFor="notes">Notes</Label>
                                <textarea
                                    id="notes"
                                    value={form.data.notes}
                                    onChange={(e) => form.setData('notes', e.target.value)}
                                    rows={3}
                                    placeholder="Optional notes about this shift"
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
                open={deleteConfirmShift !== null}
                onOpenChange={(open) => !open && setDeleteConfirmShift(null)}
                title="Archive Shift?"
                description={
                    <>
                        Archive <span className="font-semibold text-foreground">{deleteConfirmShift?.name}</span>? This can only be done when no employees are assigned.
                    </>
                }
                confirmLabel="Archive Shift"
                onConfirm={() => {
                    if (!deleteConfirmShift) {
                        return;
                    }
                    form.delete(`/shifts/${deleteConfirmShift.id}`, {
                        onSuccess: () => setDeleteConfirmShift(null),
                    });
                }}
                processing={form.processing}
            />
        </>
    );
}

ShiftsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Shifts', href: '/shifts' },
    ],
};
