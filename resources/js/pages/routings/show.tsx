import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { ArrowLeft, Ban, CheckCircle2, Copy, Edit2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { StatusBadge } from '@/components/data-table/status-badge';
import { useCan } from '@/hooks/use-can';

interface RoutingOp {
    id: number;
    sequence: number;
    setup_time_minutes: string | number;
    run_time_per_unit: string | number;
    operation?: { id: number; code: string; name: string; type?: string } | null;
    work_center?: { id: number; code: string; name: string } | null;
    machine?: { id: number; code: string; name: string } | null;
}

interface Routing {
    id: number;
    version: string;
    status: string;
    is_default: boolean;
    is_editable: boolean;
    effective_from: string | null;
    effective_to: string | null;
    notes: string | null;
    product?: { id: number; sku: string; name: string; type?: string } | null;
    operations: RoutingOp[];
}

interface Summary {
    version: string;
    status: string;
    is_default: boolean;
    operations_count: number;
    estimated_setup_time: number;
    estimated_run_time: number;
    estimated_cycle_time: number;
}

interface Props {
    routing: Routing;
    summary: Summary;
}

function formatQty(value: string | number | null | undefined): string {
    const num = Number(value ?? 0);
    return Number.isInteger(num) ? String(num) : num.toFixed(4).replace(/\.?0+$/, '');
}

function formatDate(value: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}

export default function RoutingsShow({ routing, summary }: Props) {
    const { can } = useCan();
    const actionForm = useForm({});
    const [exampleUnits, setExampleUnits] = useState('100');

    const batchTotal = useMemo(() => {
        const units = Math.max(Number(exampleUnits) || 0, 0);
        return summary.estimated_setup_time + summary.estimated_run_time * units;
    }, [exampleUnits, summary.estimated_run_time, summary.estimated_setup_time]);

    return (
        <>
            <Head title={`Routing ${routing.product?.sku ?? ''} v${routing.version}`} />
            <div className="flex flex-col gap-6 p-6 w-full max-w-5xl mx-auto">
                <div className="flex items-center justify-between gap-3 flex-wrap">
                    <Button variant="ghost" size="sm" className="h-8" asChild>
                        <Link href="/routings"><ArrowLeft className="size-4 mr-2" />Back to Routings</Link>
                    </Button>
                    <div className="flex items-center gap-2 flex-wrap">
                        {can('routing.create') && (
                            <Button size="sm" variant="outline" className="gap-2" disabled={actionForm.processing} onClick={() => actionForm.post(`/routings/${routing.id}/copy`)}>
                                <Copy className="size-4" />Copy
                            </Button>
                        )}
                        {can('routing.update') && routing.status === 'Draft' && (
                            <Button size="sm" variant="outline" className="gap-2" disabled={actionForm.processing} onClick={() => actionForm.post(`/routings/${routing.id}/release`)}>
                                <CheckCircle2 className="size-4" />Release
                            </Button>
                        )}
                        {can('routing.update') && routing.status !== 'Obsolete' && (
                            <Button size="sm" variant="outline" className="gap-2" disabled={actionForm.processing} onClick={() => actionForm.post(`/routings/${routing.id}/obsolete`)}>
                                <Ban className="size-4" />Obsolete
                            </Button>
                        )}
                        {can('routing.update') && routing.is_editable && (
                            <Button size="sm" className="gap-2" onClick={() => router.visit(`/routings/${routing.id}/edit`)}>
                                <Edit2 className="size-4" />Edit
                            </Button>
                        )}
                    </div>
                </div>

                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{routing.product?.name ?? 'Routing'}</h1>
                    <p className="text-sm text-muted-foreground font-mono">{routing.product?.sku}</p>
                </div>

                <div className="rounded-xl border border-border/50 bg-card p-5 space-y-5">
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Routing Summary</h2>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Version</p>
                            <p className="font-semibold mt-1 font-mono">{summary.version}</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Status</p>
                            <div className="mt-1"><StatusBadge status={summary.status} /></div>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Default</p>
                            <p className="font-semibold mt-1">{summary.is_default ? 'Yes' : 'No'}</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Operations</p>
                            <p className="font-semibold mt-1">{summary.operations_count}</p>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm pt-4 border-t border-border/50">
                        <div>
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Setup Time</p>
                            <p className="font-semibold mt-1">{summary.estimated_setup_time.toLocaleString()} min</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Run Time</p>
                            <p className="font-semibold mt-1">{summary.estimated_run_time.toLocaleString()} min / unit</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Estimated Cycle Time</p>
                            <p className="font-semibold mt-1">{summary.estimated_cycle_time.toLocaleString()} min / unit</p>
                        </div>
                    </div>

                    <div className="rounded-lg border border-border/50 bg-muted/20 p-4 space-y-3">
                        <div className="flex items-end gap-3 flex-wrap">
                            <div className="space-y-1">
                                <Label htmlFor="example_units" className="text-xs uppercase tracking-wide text-muted-foreground">Example batch size</Label>
                                <Input
                                    id="example_units"
                                    type="number"
                                    min={1}
                                    className="w-28 h-9"
                                    value={exampleUnits}
                                    onChange={(e) => setExampleUnits(e.target.value)}
                                />
                            </div>
                            <p className="text-sm text-muted-foreground pb-1">
                                {summary.estimated_setup_time.toLocaleString()} + ({summary.estimated_run_time.toLocaleString()} × {Number(exampleUnits) || 0}) ={' '}
                                <span className="font-semibold text-foreground">{batchTotal.toLocaleString()} min</span>
                            </p>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4 text-sm pt-4 border-t border-border/50">
                        <div>
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Effective From</p>
                            <p className="font-medium mt-1">{formatDate(routing.effective_from)}</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Effective To</p>
                            <p className="font-medium mt-1">{formatDate(routing.effective_to)}</p>
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border border-border/50 bg-card overflow-hidden">
                    <div className="px-5 py-4 border-b border-border/50">
                        <h2 className="font-semibold tracking-tight">Operations</h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Seq</th>
                                    <th className="px-4 py-3 font-medium">Operation</th>
                                    <th className="px-4 py-3 font-medium">Work Center</th>
                                    <th className="px-4 py-3 font-medium">Machine</th>
                                    <th className="px-4 py-3 font-medium">Setup</th>
                                    <th className="px-4 py-3 font-medium">Run</th>
                                </tr>
                            </thead>
                            <tbody>
                                {routing.operations.map((op) => (
                                    <tr key={op.id} className="border-t border-border/40">
                                        <td className="px-4 py-3 text-muted-foreground">{op.sequence}</td>
                                        <td className="px-4 py-3">
                                            <div className="font-semibold">
                                                {op.operation ? `${op.operation.code} - ${op.operation.name}` : '—'}
                                            </div>
                                            {op.operation?.type ? (
                                                <div className="text-xs text-muted-foreground mt-0.5">{op.operation.type}</div>
                                            ) : null}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="font-medium">{op.work_center?.name ?? '—'}</div>
                                            <div className="font-mono text-xs text-muted-foreground">{op.work_center?.code}</div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {op.machine ? (
                                                <>
                                                    <div className="font-medium">{op.machine.name}</div>
                                                    <div className="font-mono text-xs text-muted-foreground">{op.machine.code}</div>
                                                </>
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">{formatQty(op.setup_time_minutes)} min</td>
                                        <td className="px-4 py-3 font-semibold">{formatQty(op.run_time_per_unit)} min</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

RoutingsShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Routings', href: '/routings' },
        { title: 'View', href: '#' },
    ],
};
