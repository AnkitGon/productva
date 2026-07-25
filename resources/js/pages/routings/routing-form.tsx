import { useForm } from '@inertiajs/react';
import React, { useMemo } from 'react';
import { ArrowDown, ArrowUp, GripVertical, Plus, Trash2 } from 'lucide-react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export interface ProductOption {
    id: number;
    sku: string;
    name: string;
    type: string;
}

export interface OperationOption {
    id: number;
    code: string;
    name: string;
    type?: string;
}

export interface WorkCenterOption {
    id: number;
    code: string;
    name: string;
}

export interface MachineOption {
    id: number;
    code: string;
    name: string;
    work_center_id: number;
}

export interface RoutingOpForm {
    sequence: string;
    operation_id: string;
    work_center_id: string;
    machine_id: string;
    setup_time_minutes: string;
    run_time_per_unit: string;
    labor_time: string;
    queue_time: string;
    move_time: string;
    wait_time: string;
    notes: string;
}

export interface RoutingFormData {
    product_id: string;
    version: string;
    is_default: boolean;
    effective_from: string;
    effective_to: string;
    status: string;
    notes: string;
    operations: RoutingOpForm[];
}

interface RoutingPayload {
    id: number;
    product_id: number;
    version: string;
    is_default: boolean;
    effective_from: string | null;
    effective_to: string | null;
    status: string;
    notes: string | null;
    operations: Array<{
        sequence: number;
        operation_id: number | null;
        work_center_id: number;
        machine_id: number | null;
        setup_time_minutes: string | number;
        run_time_per_unit: string | number;
        labor_time: string | number;
        queue_time: string | number;
        move_time: string | number;
        wait_time: string | number;
        notes: string | null;
    }>;
}

interface Props {
    products: ProductOption[];
    operations: OperationOption[];
    workCenters: WorkCenterOption[];
    machines: MachineOption[];
    statuses: string[];
    routing?: RoutingPayload | null;
    preselectedProductId?: number | null;
    submitUrl: string;
    method?: 'post' | 'put';
}

function emptyOp(sequence = 10): RoutingOpForm {
    return {
        sequence: String(sequence),
        operation_id: '',
        work_center_id: '',
        machine_id: '',
        setup_time_minutes: '0',
        run_time_per_unit: '',
        labor_time: '0',
        queue_time: '0',
        move_time: '0',
        wait_time: '0',
        notes: '',
    };
}

function toFormData(routing?: RoutingPayload | null, preselectedProductId?: number | null): RoutingFormData {
    if (routing) {
        return {
            product_id: String(routing.product_id),
            version: routing.version,
            is_default: !!routing.is_default,
            effective_from: routing.effective_from ? routing.effective_from.slice(0, 10) : '',
            effective_to: routing.effective_to ? routing.effective_to.slice(0, 10) : '',
            status: routing.status === 'Released' || routing.status === 'Obsolete' ? 'Draft' : routing.status,
            notes: routing.notes ?? '',
            operations: routing.operations.map((op) => ({
                sequence: String(op.sequence),
                operation_id: op.operation_id ? String(op.operation_id) : '',
                work_center_id: String(op.work_center_id),
                machine_id: op.machine_id ? String(op.machine_id) : '',
                setup_time_minutes: String(op.setup_time_minutes ?? 0),
                run_time_per_unit: String(op.run_time_per_unit),
                labor_time: String(op.labor_time ?? 0),
                queue_time: String(op.queue_time ?? 0),
                move_time: String(op.move_time ?? 0),
                wait_time: String(op.wait_time ?? 0),
                notes: op.notes ?? '',
            })),
        };
    }

    return {
        product_id: preselectedProductId ? String(preselectedProductId) : '',
        version: '1.0',
        is_default: true,
        effective_from: new Date().toISOString().slice(0, 10),
        effective_to: '',
        status: 'Draft',
        notes: '',
        operations: [emptyOp(10)],
    };
}

function resequence(ops: RoutingOpForm[]): RoutingOpForm[] {
    return ops.map((op, i) => ({ ...op, sequence: String((i + 1) * 10) }));
}

export function RoutingForm({
    products,
    operations,
    workCenters,
    machines,
    statuses,
    routing,
    preselectedProductId,
    submitUrl,
    method = 'post',
}: Props) {
    const form = useForm<RoutingFormData>(toFormData(routing, preselectedProductId));

    const totals = useMemo(() => {
        const setup = form.data.operations.reduce((sum, op) => sum + (Number(op.setup_time_minutes) || 0), 0);
        const run = form.data.operations.reduce((sum, op) => sum + (Number(op.run_time_per_unit) || 0), 0);
        return { setup, run, total: setup + run };
    }, [form.data.operations]);

    const updateOp = (index: number, patch: Partial<RoutingOpForm>) => {
        form.setData('operations', form.data.operations.map((op, i) => (i === index ? { ...op, ...patch } : op)));
    };

    const onOperationMasterChange = (index: number, operationId: string) => {
        updateOp(index, { operation_id: operationId });
    };

    const addOp = () => {
        form.setData('operations', [...form.data.operations, emptyOp((form.data.operations.length + 1) * 10)]);
    };

    const removeOp = (index: number) => {
        if (form.data.operations.length <= 1) {
            return;
        }
        form.setData('operations', resequence(form.data.operations.filter((_, i) => i !== index)));
    };

    const moveOp = (index: number, direction: -1 | 1) => {
        const target = index + direction;
        if (target < 0 || target >= form.data.operations.length) {
            return;
        }
        const next = [...form.data.operations];
        const [item] = next.splice(index, 1);
        next.splice(target, 0, item);
        form.setData('operations', resequence(next));
    };

    const onDragStart = (e: React.DragEvent, index: number) => {
        e.dataTransfer.setData('text/plain', String(index));
        e.dataTransfer.effectAllowed = 'move';
    };

    const onDrop = (e: React.DragEvent, targetIndex: number) => {
        e.preventDefault();
        const fromIndex = Number(e.dataTransfer.getData('text/plain'));
        if (Number.isNaN(fromIndex) || fromIndex === targetIndex) {
            return;
        }
        const next = [...form.data.operations];
        const [item] = next.splice(fromIndex, 1);
        next.splice(targetIndex, 0, item);
        form.setData('operations', resequence(next));
    };

    const machinesFor = (workCenterId: string) =>
        machines.filter((m) => m.work_center_id.toString() === workCenterId);

    const opError = (index: number, field: string) =>
        (form.errors as Record<string, string>)[`operations.${index}.${field}`];

    const editableStatuses = statuses.filter((s) => s === 'Draft' || s === 'Released');

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const payload = {
            ...form.data,
            operations: form.data.operations.map((op) => ({
                ...op,
                machine_id: op.machine_id || null,
                operation_id: op.operation_id || null,
            })),
        };
        form.transform(() => payload);
        if (method === 'put') {
            form.put(submitUrl);
        } else {
            form.post(submitUrl);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-8">
            <section className="rounded-xl border border-border/50 bg-card p-5 space-y-4">
                <div>
                    <h2 className="text-lg font-semibold tracking-tight">Routing Header</h2>
                    <p className="text-sm text-muted-foreground">Product, version, and effectivity — same pattern as BOM.</p>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-2 md:col-span-2">
                        <Label>Product <span className="text-destructive">*</span></Label>
                        <Select
                            value={form.data.product_id || undefined}
                            onValueChange={(v) => form.setData('product_id', v)}
                            disabled={!!routing}
                        >
                            <SelectTrigger className="w-full"><SelectValue placeholder="Select finished / semi-finished product" /></SelectTrigger>
                            <SelectContent>
                                {products.map((product) => (
                                    <SelectItem key={product.id} value={product.id.toString()}>
                                        {product.sku} — {product.name} ({product.type})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.product_id} />
                    </div>
                    <div className="space-y-2">
                        <Label>Version <span className="text-destructive">*</span></Label>
                        <Input value={form.data.version} onChange={(e) => form.setData('version', e.target.value)} required />
                        <InputError message={form.errors.version} />
                    </div>
                    <div className="space-y-2">
                        <Label>Status <span className="text-destructive">*</span></Label>
                        <Select value={form.data.status} onValueChange={(v) => form.setData('status', v)}>
                            <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                {editableStatuses.map((status) => (
                                    <SelectItem key={status} value={status}>{status}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.status} />
                    </div>
                    <div className="space-y-2">
                        <Label>Effective From</Label>
                        <Input type="date" value={form.data.effective_from} onChange={(e) => form.setData('effective_from', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label>Effective To</Label>
                        <Input type="date" value={form.data.effective_to} onChange={(e) => form.setData('effective_to', e.target.value)} />
                        <InputError message={form.errors.effective_to} />
                    </div>
                    <div className="flex items-center gap-2 md:col-span-2">
                        <input
                            id="is_default_routing"
                            type="checkbox"
                            checked={form.data.is_default}
                            onChange={(e) => form.setData('is_default', e.target.checked)}
                            className="size-4 rounded border-input"
                        />
                        <Label htmlFor="is_default_routing" className="font-normal">Default routing for this product</Label>
                    </div>
                    <div className="space-y-2 md:col-span-2">
                        <Label>Notes</Label>
                        <textarea
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            rows={3}
                            className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                        />
                    </div>
                </div>
            </section>

            <section className="rounded-xl border border-border/50 bg-card p-5 space-y-4">
                <div className="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <h2 className="text-lg font-semibold tracking-tight">Operations</h2>
                        <p className="text-sm text-muted-foreground">
                            Drag rows or use arrows to reorder. Sequences auto-update (10, 20, 30…).
                        </p>
                    </div>
                    <Button type="button" variant="outline" size="sm" className="gap-1.5" onClick={addOp}>
                        <Plus className="size-4" />
                        Add Operation
                    </Button>
                </div>
                <InputError message={form.errors.operations} />

                <div className="space-y-3">
                    {form.data.operations.map((op, index) => (
                        <div
                            key={index}
                            draggable
                            onDragStart={(e) => onDragStart(e, index)}
                            onDragOver={(e) => e.preventDefault()}
                            onDrop={(e) => onDrop(e, index)}
                            className="rounded-lg border border-border/60 p-4 space-y-3 bg-muted/20"
                        >
                            <div className="flex items-center justify-between gap-2">
                                <div className="flex items-center gap-2 text-xs uppercase tracking-wide text-muted-foreground">
                                    <GripVertical className="size-4 cursor-grab" />
                                    <span>Seq {op.sequence}</span>
                                </div>
                                <div className="flex items-center gap-1">
                                    <Button type="button" variant="ghost" size="sm" className="h-8" onClick={() => moveOp(index, -1)} disabled={index === 0}>
                                        <ArrowUp className="size-4" />
                                    </Button>
                                    <Button type="button" variant="ghost" size="sm" className="h-8" onClick={() => moveOp(index, 1)} disabled={index === form.data.operations.length - 1}>
                                        <ArrowDown className="size-4" />
                                    </Button>
                                    <Button type="button" variant="ghost" size="sm" className="h-8 text-rose-600" onClick={() => removeOp(index)} disabled={form.data.operations.length <= 1}>
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-12 gap-3">
                                <div className="space-y-2 col-span-12 sm:col-span-6 lg:col-span-3">
                                    <Label>Operation <span className="text-destructive">*</span></Label>
                                    <Select
                                        value={op.operation_id || undefined}
                                        onValueChange={(v) => onOperationMasterChange(index, v)}
                                    >
                                        <SelectTrigger className="w-full"><SelectValue placeholder="Select from master" /></SelectTrigger>
                                        <SelectContent>
                                            {operations.map((master) => (
                                                <SelectItem key={master.id} value={master.id.toString()}>
                                                    {master.code} - {master.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={opError(index, 'operation_id')} />
                                </div>
                                <div className="space-y-2 col-span-12 sm:col-span-6 lg:col-span-3">
                                    <Label>Work Center <span className="text-destructive">*</span></Label>
                                    <Select
                                        value={op.work_center_id || undefined}
                                        onValueChange={(v) => updateOp(index, { work_center_id: v, machine_id: '' })}
                                    >
                                        <SelectTrigger className="w-full"><SelectValue placeholder="Select WC" /></SelectTrigger>
                                        <SelectContent>
                                            {workCenters.map((wc) => (
                                                <SelectItem key={wc.id} value={wc.id.toString()}>{wc.code} — {wc.name}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={opError(index, 'work_center_id')} />
                                </div>
                                <div className="space-y-2 col-span-12 sm:col-span-4 lg:col-span-2">
                                    <Label>Machine</Label>
                                    <Select
                                        value={op.machine_id || undefined}
                                        onValueChange={(v) => updateOp(index, { machine_id: v === 'none' ? '' : v })}
                                        disabled={!op.work_center_id}
                                    >
                                        <SelectTrigger className="w-full"><SelectValue placeholder="Optional" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">— None —</SelectItem>
                                            {machinesFor(op.work_center_id).map((machine) => (
                                                <SelectItem key={machine.id} value={machine.id.toString()}>
                                                    {machine.code} — {machine.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={opError(index, 'machine_id')} />
                                </div>
                                <div className="space-y-2 col-span-12 sm:col-span-4 lg:col-span-2">
                                    <Label>Setup Time</Label>
                                    <Input
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={op.setup_time_minutes}
                                        onChange={(e) => updateOp(index, { setup_time_minutes: e.target.value })}
                                    />
                                    <p className="text-[11px] text-muted-foreground">min</p>
                                </div>
                                <div className="space-y-2 col-span-12 sm:col-span-4 lg:col-span-2">
                                    <Label>Run Time <span className="text-destructive">*</span></Label>
                                    <Input
                                        type="number"
                                        min={0}
                                        step="0.0001"
                                        value={op.run_time_per_unit}
                                        onChange={(e) => updateOp(index, { run_time_per_unit: e.target.value })}
                                        required
                                    />
                                    <p className="text-[11px] text-muted-foreground">min / unit</p>
                                    <InputError message={opError(index, 'run_time_per_unit')} />
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                <div className="rounded-lg border border-border/50 bg-muted/30 p-4 space-y-3 text-sm">
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div>
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Operations</p>
                            <p className="font-semibold mt-1">{form.data.operations.length}</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Setup Time</p>
                            <p className="font-semibold mt-1">{totals.setup.toLocaleString()} min</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Run Time</p>
                            <p className="font-semibold mt-1">{totals.run.toLocaleString()} min / unit</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Estimated Cycle Time</p>
                            <p className="font-semibold mt-1">{totals.total.toLocaleString()} min / unit</p>
                        </div>
                    </div>
                    <p className="text-xs text-muted-foreground border-t border-border/40 pt-3">
                        Example (100 units): {totals.setup.toLocaleString()} + ({totals.run.toLocaleString()} × 100) ={' '}
                        <span className="font-semibold text-foreground">
                            {(totals.setup + totals.run * 100).toLocaleString()} min
                        </span>
                    </p>
                </div>
            </section>

            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing} className="min-w-36">
                    {form.processing ? 'Saving…' : routing ? 'Update Routing' : 'Save Routing'}
                </Button>
            </div>
        </form>
    );
}
