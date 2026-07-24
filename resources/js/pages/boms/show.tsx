import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Copy, Edit2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { StatusBadge } from '@/components/data-table/status-badge';
import { useCan } from '@/hooks/use-can';

interface ProductSummary {
    id: number;
    sku: string;
    name: string;
    type?: string;
    uom?: { id: number; code: string; symbol?: string | null } | null;
}

interface BomItemRow {
    id: number;
    quantity: string | number;
    scrap_percentage: string | number;
    sequence: number;
    notes: string | null;
    component?: ProductSummary | null;
    uom?: { id: number; code: string; name: string; symbol?: string | null } | null;
}

interface Bom {
    id: number;
    version: string;
    is_default: boolean;
    status: string;
    effective_from: string | null;
    effective_to: string | null;
    notes: string | null;
    product?: ProductSummary | null;
    items: BomItemRow[];
    creator?: { id: number; name: string } | null;
    updater?: { id: number; name: string } | null;
}

interface Summary {
    version: string;
    status: string;
    is_default: boolean;
    components_count: number;
    estimated_material_cost: number | null;
}

interface Props {
    bom: Bom;
    summary: Summary;
}

function formatQty(value: string | number | null | undefined): string {
    const num = Number(value ?? 0);
    return Number.isInteger(num) ? String(num) : num.toFixed(4).replace(/\.?0+$/, '');
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }
    return new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}

function TypeBadge({ type }: { type?: string | null }) {
    if (!type) {
        return null;
    }

    return (
        <Badge variant="outline" className="font-normal text-[10px] px-1.5 py-0 h-5 text-muted-foreground">
            {type}
        </Badge>
    );
}

export default function BomsShow({ bom, summary }: Props) {
    const { can } = useCan();
    const copyForm = useForm({});

    return (
        <>
            <Head title={`BOM ${bom.product?.sku ?? ''} v${bom.version}`} />
            <div className="flex flex-col gap-6 p-6 w-full max-w-5xl mx-auto">
                <div className="flex items-center justify-between gap-3 flex-wrap">
                    <Button variant="ghost" size="sm" className="h-8" asChild>
                        <Link href="/boms">
                            <ArrowLeft className="size-4 mr-2" />
                            Back to BOMs
                        </Link>
                    </Button>
                    <div className="flex items-center gap-2">
                        {can('boms.create') && (
                            <Button
                                size="sm"
                                variant="outline"
                                className="gap-2"
                                disabled={copyForm.processing}
                                onClick={() => copyForm.post(`/boms/${bom.id}/copy`)}
                            >
                                <Copy className="size-4" />
                                Copy BOM
                            </Button>
                        )}
                        {can('boms.update') && (
                            <Button size="sm" className="gap-2" onClick={() => router.visit(`/boms/${bom.id}/edit`)}>
                                <Edit2 className="size-4" />
                                Edit
                            </Button>
                        )}
                    </div>
                </div>

                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{bom.product?.name ?? 'BOM'}</h1>
                    <p className="text-sm text-muted-foreground font-mono">{bom.product?.sku}</p>
                </div>

                <div className="rounded-xl border border-border/50 bg-card p-5">
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground mb-4">
                        BOM Summary
                    </h2>
                    <div className="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                        <div>
                            <p className="text-muted-foreground text-xs uppercase tracking-wide">Version</p>
                            <p className="font-semibold mt-1 font-mono">{summary.version}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-xs uppercase tracking-wide">Status</p>
                            <div className="mt-1"><StatusBadge status={summary.status} /></div>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-xs uppercase tracking-wide">Default</p>
                            <p className="font-semibold mt-1">{summary.is_default ? 'Yes' : 'No'}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-xs uppercase tracking-wide">Components</p>
                            <p className="font-semibold mt-1">{summary.components_count}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-xs uppercase tracking-wide">Est. Material Cost</p>
                            <p className="font-semibold mt-1 text-muted-foreground">
                                {summary.estimated_material_cost === null
                                    ? '—'
                                    : summary.estimated_material_cost.toLocaleString()}
                            </p>
                            <p className="text-[10px] text-muted-foreground mt-0.5">Coming with costing</p>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm mt-5 pt-4 border-t border-border/50">
                        <div>
                            <p className="text-muted-foreground text-xs uppercase tracking-wide">Effective From</p>
                            <p className="font-medium mt-1">{formatDate(bom.effective_from)}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-xs uppercase tracking-wide">Effective To</p>
                            <p className="font-medium mt-1">{formatDate(bom.effective_to)}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-xs uppercase tracking-wide">Product Type</p>
                            <p className="font-medium mt-1">{bom.product?.type ?? '—'}</p>
                        </div>
                    </div>

                    {bom.notes ? (
                        <p className="text-sm text-muted-foreground border-t border-border/50 pt-3 mt-4">{bom.notes}</p>
                    ) : null}
                </div>

                <div className="rounded-xl border border-border/50 bg-card overflow-hidden">
                    <div className="px-5 py-4 border-b border-border/50">
                        <h2 className="font-semibold tracking-tight">Components</h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Seq</th>
                                    <th className="px-4 py-3 font-medium">SKU</th>
                                    <th className="px-4 py-3 font-medium">Component</th>
                                    <th className="px-4 py-3 font-medium">Qty</th>
                                    <th className="px-4 py-3 font-medium">UOM</th>
                                    <th className="px-4 py-3 font-medium">Scrap</th>
                                    <th className="px-4 py-3 font-medium">Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                {bom.items.map((item) => (
                                    <tr key={item.id} className="border-t border-border/40">
                                        <td className="px-4 py-3 text-muted-foreground">{item.sequence}</td>
                                        <td className="px-4 py-3 font-mono text-xs font-semibold">
                                            {item.component?.sku ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <span className="font-semibold">{item.component?.name ?? '—'}</span>
                                                <TypeBadge type={item.component?.type} />
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 font-semibold">{formatQty(item.quantity)}</td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {item.uom?.symbol || item.uom?.code || '—'}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {formatQty(item.scrap_percentage)}%
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">{item.notes || '—'}</td>
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

BomsShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Bill of Materials', href: '/boms' },
        { title: 'View', href: '#' },
    ],
};
