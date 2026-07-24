import { useForm } from '@inertiajs/react';
import React from 'react';
import { Plus, Trash2 } from 'lucide-react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
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
    uom_id?: number;
    uom?: { id: number; code: string; name: string; symbol?: string | null } | null;
}

export interface UomOption {
    id: number;
    code: string;
    name: string;
    symbol?: string | null;
}

export interface BomItemForm {
    component_product_id: string;
    quantity: string;
    uom_id: string;
    scrap_percentage: string;
    sequence: string;
    notes: string;
}

export interface BomFormData {
    product_id: string;
    version: string;
    is_default: boolean;
    effective_from: string;
    effective_to: string;
    status: string;
    notes: string;
    items: BomItemForm[];
}

interface BomHeaderPayload {
    id: number;
    product_id: number;
    version: string;
    is_default: boolean;
    effective_from: string | null;
    effective_to: string | null;
    status: string;
    notes: string | null;
    items: Array<{
        component_product_id: number;
        quantity: string | number;
        uom_id: number;
        scrap_percentage: string | number;
        sequence: number;
        notes: string | null;
    }>;
}

interface Props {
    parentProducts: ProductOption[];
    componentProducts: ProductOption[];
    uoms: UomOption[];
    statuses: string[];
    bom?: BomHeaderPayload | null;
    preselectedProductId?: number | null;
    submitUrl: string;
    method?: 'post' | 'put';
}

function emptyItem(sequence = 10): BomItemForm {
    return {
        component_product_id: '',
        quantity: '',
        uom_id: '',
        scrap_percentage: '0',
        sequence: String(sequence),
        notes: '',
    };
}

function toFormData(bom?: BomHeaderPayload | null, preselectedProductId?: number | null): BomFormData {
    if (bom) {
        return {
            product_id: String(bom.product_id),
            version: bom.version,
            is_default: !!bom.is_default,
            effective_from: bom.effective_from ? bom.effective_from.slice(0, 10) : '',
            effective_to: bom.effective_to ? bom.effective_to.slice(0, 10) : '',
            status: bom.status,
            notes: bom.notes ?? '',
            items: bom.items.map((item) => ({
                component_product_id: String(item.component_product_id),
                quantity: String(item.quantity),
                uom_id: String(item.uom_id),
                scrap_percentage: String(item.scrap_percentage ?? 0),
                sequence: String(item.sequence ?? 10),
                notes: item.notes ?? '',
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
        items: [emptyItem(10)],
    };
}

export function BomForm({
    parentProducts,
    componentProducts,
    uoms,
    statuses,
    bom,
    preselectedProductId,
    submitUrl,
    method = 'post',
}: Props) {
    const form = useForm<BomFormData>(toFormData(bom, preselectedProductId));

    const addItem = () => {
        const nextSequence = (form.data.items.length + 1) * 10;
        form.setData('items', [...form.data.items, emptyItem(nextSequence)]);
    };

    const removeItem = (index: number) => {
        if (form.data.items.length <= 1) {
            return;
        }
        form.setData('items', form.data.items.filter((_, i) => i !== index));
    };

    const updateItem = (index: number, patch: Partial<BomItemForm>) => {
        form.setData('items', form.data.items.map((item, i) => (i === index ? { ...item, ...patch } : item)));
    };

    const onComponentChange = (index: number, productId: string) => {
        const alreadyUsed = form.data.items.some(
            (item, i) => i !== index && item.component_product_id === productId,
        );
        if (alreadyUsed) {
            return;
        }

        const product = componentProducts.find((p) => p.id.toString() === productId);
        updateItem(index, {
            component_product_id: productId,
            uom_id: product?.uom_id ? String(product.uom_id) : product?.uom?.id ? String(product.uom.id) : '',
        });
    };

    const selectedComponent = (productId: string) =>
        componentProducts.find((p) => p.id.toString() === productId);

    const availableComponentsFor = (index: number) =>
        componentProducts.filter((product) => {
            if (product.id.toString() === form.data.product_id) {
                return false;
            }
            const selectedElsewhere = form.data.items.some(
                (item, i) => i !== index && item.component_product_id === product.id.toString(),
            );
            return !selectedElsewhere;
        });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (method === 'put') {
            form.put(submitUrl);
        } else {
            form.post(submitUrl);
        }
    };

    const itemError = (index: number, field: string): string | undefined => {
        const key = `items.${index}.${field}`;
        return (form.errors as Record<string, string>)[key];
    };

    return (
        <form onSubmit={submit} className="space-y-8">
            <section className="rounded-xl border border-border/50 bg-card p-5 space-y-4">
                <div>
                    <h2 className="text-lg font-semibold tracking-tight">BOM Header</h2>
                    <p className="text-sm text-muted-foreground">Parent product, version, and effectivity.</p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-2 md:col-span-2">
                        <Label>Finished / Semi-Finished Product <span className="text-destructive">*</span></Label>
                        <Select
                            value={form.data.product_id || undefined}
                            onValueChange={(v) => form.setData('product_id', v)}
                            disabled={!!bom}
                        >
                            <SelectTrigger className="w-full"><SelectValue placeholder="Select parent product" /></SelectTrigger>
                            <SelectContent>
                                {parentProducts.map((product) => (
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
                        <Input
                            value={form.data.version}
                            onChange={(e) => form.setData('version', e.target.value)}
                            placeholder="1.0"
                            required
                        />
                        <InputError message={form.errors.version} />
                    </div>

                    <div className="space-y-2">
                        <Label>Status <span className="text-destructive">*</span></Label>
                        <Select value={form.data.status} onValueChange={(v) => form.setData('status', v)}>
                            <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                {statuses.map((status) => (
                                    <SelectItem key={status} value={status}>{status}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.status} />
                    </div>

                    <div className="space-y-2">
                        <Label>Effective From</Label>
                        <Input
                            type="date"
                            value={form.data.effective_from}
                            onChange={(e) => form.setData('effective_from', e.target.value)}
                        />
                        <InputError message={form.errors.effective_from} />
                    </div>

                    <div className="space-y-2">
                        <Label>Effective To</Label>
                        <Input
                            type="date"
                            value={form.data.effective_to}
                            onChange={(e) => form.setData('effective_to', e.target.value)}
                        />
                        <InputError message={form.errors.effective_to} />
                    </div>

                    <div className="flex items-center gap-2 md:col-span-2 pt-1">
                        <input
                            id="is_default"
                            type="checkbox"
                            checked={form.data.is_default}
                            onChange={(e) => form.setData('is_default', e.target.checked)}
                            className="size-4 rounded border-input"
                        />
                        <Label htmlFor="is_default" className="font-normal">
                            Default BOM for this product (only one default allowed)
                        </Label>
                    </div>
                    <InputError message={form.errors.is_default} />

                    <div className="space-y-2 md:col-span-2">
                        <Label>Notes</Label>
                        <textarea
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            rows={3}
                            className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                </div>
            </section>

            <section className="rounded-xl border border-border/50 bg-card p-5 space-y-4">
                <div className="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <h2 className="text-lg font-semibold tracking-tight">Components</h2>
                        <p className="text-sm text-muted-foreground">
                            Materials and sub-assemblies required to make one parent unit.
                        </p>
                    </div>
                    <Button type="button" variant="outline" size="sm" className="gap-1.5" onClick={addItem}>
                        <Plus className="size-4" />
                        Add Component
                    </Button>
                </div>
                <InputError message={form.errors.items} />

                <div className="space-y-3">
                    {form.data.items.map((item, index) => (
                        <div key={index} className="rounded-lg border border-border/60 p-4 space-y-3 bg-muted/20">
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Line {index + 1}
                                </p>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-8 text-rose-600"
                                    onClick={() => removeItem(index)}
                                    disabled={form.data.items.length <= 1}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-12 gap-3">
                                <div className="space-y-2 md:col-span-5">
                                    <Label>Component <span className="text-destructive">*</span></Label>
                                    <Select
                                        value={item.component_product_id || undefined}
                                        onValueChange={(v) => onComponentChange(index, v)}
                                    >
                                        <SelectTrigger className="w-full"><SelectValue placeholder="Select component" /></SelectTrigger>
                                        <SelectContent>
                                            {availableComponentsFor(index).map((product) => (
                                                <SelectItem key={product.id} value={product.id.toString()}>
                                                    {product.sku} — {product.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {item.component_product_id ? (
                                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                            <span className="font-mono font-semibold text-foreground">
                                                {selectedComponent(item.component_product_id)?.sku}
                                            </span>
                                            {selectedComponent(item.component_product_id)?.type ? (
                                                <Badge variant="outline" className="font-normal text-[10px] px-1.5 py-0 h-5">
                                                    {selectedComponent(item.component_product_id)?.type}
                                                </Badge>
                                            ) : null}
                                        </div>
                                    ) : null}
                                    <InputError message={itemError(index, 'component_product_id')} />
                                </div>

                                <div className="space-y-2 md:col-span-2">
                                    <Label>Qty <span className="text-destructive">*</span></Label>
                                    <Input
                                        type="number"
                                        min={0}
                                        step="0.0001"
                                        value={item.quantity}
                                        onChange={(e) => updateItem(index, { quantity: e.target.value })}
                                        required
                                    />
                                    <InputError message={itemError(index, 'quantity')} />
                                </div>

                                <div className="space-y-2 md:col-span-2">
                                    <Label>UOM <span className="text-destructive">*</span></Label>
                                    <Select
                                        value={item.uom_id || undefined}
                                        onValueChange={(v) => updateItem(index, { uom_id: v })}
                                    >
                                        <SelectTrigger className="w-full"><SelectValue placeholder="UOM" /></SelectTrigger>
                                        <SelectContent>
                                            {uoms.map((uom) => (
                                                <SelectItem key={uom.id} value={uom.id.toString()}>
                                                    {uom.code}{uom.symbol ? ` (${uom.symbol})` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={itemError(index, 'uom_id')} />
                                </div>

                                <div className="space-y-2 md:col-span-1">
                                    <Label>Scrap %</Label>
                                    <Input
                                        type="number"
                                        min={0}
                                        max={100}
                                        step="0.01"
                                        value={item.scrap_percentage}
                                        onChange={(e) => updateItem(index, { scrap_percentage: e.target.value })}
                                    />
                                    <InputError message={itemError(index, 'scrap_percentage')} />
                                </div>

                                <div className="space-y-2 md:col-span-2">
                                    <Label>Seq</Label>
                                    <Input
                                        type="number"
                                        min={1}
                                        value={item.sequence}
                                        onChange={(e) => updateItem(index, { sequence: e.target.value })}
                                    />
                                    <InputError message={itemError(index, 'sequence')} />
                                </div>

                                <div className="space-y-2 md:col-span-12">
                                    <Label>Notes</Label>
                                    <Input
                                        value={item.notes}
                                        onChange={(e) => updateItem(index, { notes: e.target.value })}
                                        placeholder="Optional"
                                    />
                                    <InputError message={itemError(index, 'notes')} />
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </section>

            <div className="flex justify-end gap-2">
                <Button type="submit" disabled={form.processing} className="min-w-32">
                    {form.processing ? 'Saving…' : bom ? 'Update BOM' : 'Create BOM'}
                </Button>
            </div>
        </form>
    );
}
