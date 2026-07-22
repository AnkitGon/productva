import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';

export interface CategoryOption {
    id: number;
    code: string;
    name: string;
    status: string;
}

export interface UomOption {
    id: number;
    code: string;
    name: string;
    symbol: string;
    status: string;
}

export interface ProductRecord {
    id: number;
    sku: string;
    barcode: string | null;
    name: string;
    description: string | null;
    category_id: number;
    uom_id: number;
    type: string;
    status: string;
    track_inventory: boolean;
    allow_negative_stock: boolean;
    reorder_level: string | number | null;
    minimum_stock: string | number | null;
    maximum_stock: string | number | null;
    safety_stock: string | number | null;
    lead_time_days: number | null;
    make_to_stock: boolean;
    make_to_order: boolean;
    bom_required: boolean;
    routing_required: boolean;
    lot_tracking: boolean;
    serial_tracking: boolean;
    expiry_tracking: boolean;
    preferred_supplier_id: number | null;
    supplier_sku: string | null;
    purchase_uom_id: number | null;
    purchase_price: string | number | null;
    selling_price: string | number | null;
    tax_rate: string | number | null;
    weight: string | number | null;
    dimensions: string | null;
    image_path: string | null;
    image_url?: string | null;
    datasheet_path: string | null;
    safety_sheet_path: string | null;
    technical_drawing_path: string | null;
}

export type ProductFormData = {
    sku: string;
    barcode: string;
    name: string;
    description: string;
    category_id: string;
    uom_id: string;
    type: string;
    status: string;
    track_inventory: boolean;
    allow_negative_stock: boolean;
    reorder_level: string;
    minimum_stock: string;
    maximum_stock: string;
    safety_stock: string;
    lead_time_days: string;
    make_to_stock: boolean;
    make_to_order: boolean;
    bom_required: boolean;
    routing_required: boolean;
    lot_tracking: boolean;
    serial_tracking: boolean;
    expiry_tracking: boolean;
    preferred_supplier_id: string;
    supplier_sku: string;
    purchase_uom_id: string;
    purchase_price: string;
    selling_price: string;
    tax_rate: string;
    weight: string;
    dimensions: string;
    image: File | null;
    datasheet: File | null;
    safety_sheet: File | null;
    technical_drawing: File | null;
    remove_image: boolean;
    remove_datasheet: boolean;
    remove_safety_sheet: boolean;
    remove_technical_drawing: boolean;
};

type TabId = 'general' | 'inventory' | 'purchasing' | 'sales' | 'manufacturing' | 'traceability' | 'attachments';

const TABS: { id: TabId; label: string }[] = [
    { id: 'general', label: 'General' },
    { id: 'inventory', label: 'Inventory' },
    { id: 'purchasing', label: 'Purchasing' },
    { id: 'sales', label: 'Sales' },
    { id: 'manufacturing', label: 'Manufacturing' },
    { id: 'traceability', label: 'Traceability' },
    { id: 'attachments', label: 'Attachments' },
];

function num(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    return String(value);
}

export function emptyProductForm(types: string[]): ProductFormData {
    return {
        sku: '',
        barcode: '',
        name: '',
        description: '',
        category_id: '',
        uom_id: '',
        type: types[0] ?? 'Finished Good',
        status: 'Active',
        track_inventory: true,
        allow_negative_stock: false,
        reorder_level: '',
        minimum_stock: '',
        maximum_stock: '',
        safety_stock: '',
        lead_time_days: '',
        make_to_stock: false,
        make_to_order: false,
        bom_required: false,
        routing_required: false,
        lot_tracking: false,
        serial_tracking: false,
        expiry_tracking: false,
        preferred_supplier_id: '',
        supplier_sku: '',
        purchase_uom_id: '',
        purchase_price: '',
        selling_price: '',
        tax_rate: '',
        weight: '',
        dimensions: '',
        image: null,
        datasheet: null,
        safety_sheet: null,
        technical_drawing: null,
        remove_image: false,
        remove_datasheet: false,
        remove_safety_sheet: false,
        remove_technical_drawing: false,
    };
}

export function formFromProduct(product: ProductRecord): ProductFormData {
    return {
        sku: product.sku,
        barcode: product.barcode ?? '',
        name: product.name,
        description: product.description ?? '',
        category_id: product.category_id.toString(),
        uom_id: product.uom_id.toString(),
        type: product.type,
        status: product.status,
        track_inventory: product.track_inventory,
        allow_negative_stock: product.allow_negative_stock,
        reorder_level: num(product.reorder_level),
        minimum_stock: num(product.minimum_stock),
        maximum_stock: num(product.maximum_stock),
        safety_stock: num(product.safety_stock),
        lead_time_days: num(product.lead_time_days),
        make_to_stock: product.make_to_stock,
        make_to_order: product.make_to_order,
        bom_required: product.bom_required,
        routing_required: product.routing_required,
        lot_tracking: product.lot_tracking,
        serial_tracking: product.serial_tracking,
        expiry_tracking: product.expiry_tracking,
        preferred_supplier_id: product.preferred_supplier_id?.toString() ?? '',
        supplier_sku: product.supplier_sku ?? '',
        purchase_uom_id: product.purchase_uom_id?.toString() ?? '',
        purchase_price: num(product.purchase_price),
        selling_price: num(product.selling_price),
        tax_rate: num(product.tax_rate),
        weight: num(product.weight),
        dimensions: product.dimensions ?? '',
        image: null,
        datasheet: null,
        safety_sheet: null,
        technical_drawing: null,
        remove_image: false,
        remove_datasheet: false,
        remove_safety_sheet: false,
        remove_technical_drawing: false,
    };
}

interface Props {
    initial: ProductFormData;
    categories: CategoryOption[];
    uoms: UomOption[];
    types: string[];
    statuses: string[];
    product?: ProductRecord | null;
    submitLabel: string;
    onSubmit: (form: ReturnType<typeof useForm<ProductFormData>>) => void;
    onCancel: () => void;
}

function ToggleField({
    id,
    label,
    checked,
    onChange,
    error,
}: {
    id: string;
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    error?: string;
}) {
    return (
        <div className="flex items-start gap-3 rounded-lg border border-border/60 px-3 py-2.5">
            <Checkbox id={id} checked={checked} onCheckedChange={(value) => onChange(value === true)} />
            <div className="space-y-1">
                <Label htmlFor={id} className="font-medium leading-none">
                    {label}
                </Label>
                <InputError message={error} />
            </div>
        </div>
    );
}

export function ProductForm({
    initial,
    categories,
    uoms,
    types,
    statuses,
    product,
    submitLabel,
    onSubmit,
    onCancel,
}: Props) {
    const [activeTab, setActiveTab] = useState<TabId>('general');
    const form = useForm<ProductFormData>(initial);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onSubmit(form);
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div className="flex gap-1 overflow-x-auto border-b border-border/60 pb-px">
                {TABS.map((tab) => (
                    <button
                        key={tab.id}
                        type="button"
                        onClick={() => setActiveTab(tab.id)}
                        className={`px-3 py-2 text-sm font-medium whitespace-nowrap border-b-2 transition-colors ${
                            activeTab === tab.id
                                ? 'border-primary text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            {activeTab === 'general' && (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="space-y-2">
                        <Label htmlFor="sku">
                            SKU <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="sku"
                            value={form.data.sku}
                            onChange={(e) => form.setData('sku', e.target.value.toUpperCase())}
                            required
                        />
                        <InputError message={form.errors.sku} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="barcode">Barcode</Label>
                        <Input
                            id="barcode"
                            value={form.data.barcode}
                            onChange={(e) => form.setData('barcode', e.target.value)}
                        />
                        <InputError message={form.errors.barcode} />
                    </div>
                    <div className="space-y-2 sm:col-span-2">
                        <Label htmlFor="name">
                            Name <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="space-y-2">
                        <Label>
                            Category <span className="text-destructive">*</span>
                        </Label>
                        <Select
                            value={form.data.category_id || undefined}
                            onValueChange={(value) => form.setData('category_id', value)}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Select category" />
                            </SelectTrigger>
                            <SelectContent>
                                {categories.map((category) => (
                                    <SelectItem key={category.id} value={category.id.toString()}>
                                        {category.code} — {category.name}
                                        {category.status !== 'Active' ? ' (Inactive)' : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.category_id} />
                    </div>
                    <div className="space-y-2">
                        <Label>
                            UOM <span className="text-destructive">*</span>
                        </Label>
                        <Select
                            value={form.data.uom_id || undefined}
                            onValueChange={(value) => form.setData('uom_id', value)}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Select UOM" />
                            </SelectTrigger>
                            <SelectContent>
                                {uoms.map((uom) => (
                                    <SelectItem key={uom.id} value={uom.id.toString()}>
                                        {uom.code} — {uom.name}
                                        {uom.status !== 'Active' ? ' (Inactive)' : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.uom_id} />
                    </div>
                    <div className="space-y-2">
                        <Label>
                            Type <span className="text-destructive">*</span>
                        </Label>
                        <Select value={form.data.type} onValueChange={(value) => form.setData('type', value)}>
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {types.map((type) => (
                                    <SelectItem key={type} value={type}>
                                        {type}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.type} />
                    </div>
                    <div className="space-y-2">
                        <Label>Status</Label>
                        <Select value={form.data.status} onValueChange={(value) => form.setData('status', value)}>
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {statuses.map((status) => (
                                    <SelectItem key={status} value={status}>
                                        {status}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.status} />
                    </div>
                    <div className="space-y-2 sm:col-span-2">
                        <Label htmlFor="description">Description</Label>
                        <textarea
                            id="description"
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            rows={4}
                            className="flex min-h-[96px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                        />
                        <InputError message={form.errors.description} />
                    </div>
                </div>
            )}

            {activeTab === 'inventory' && (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <ToggleField
                        id="track_inventory"
                        label="Track inventory"
                        checked={form.data.track_inventory}
                        onChange={(checked) => form.setData('track_inventory', checked)}
                        error={form.errors.track_inventory}
                    />
                    <ToggleField
                        id="allow_negative_stock"
                        label="Allow negative stock"
                        checked={form.data.allow_negative_stock}
                        onChange={(checked) => form.setData('allow_negative_stock', checked)}
                        error={form.errors.allow_negative_stock}
                    />
                    {(['reorder_level', 'minimum_stock', 'maximum_stock', 'safety_stock'] as const).map((field) => (
                        <div key={field} className="space-y-2">
                            <Label htmlFor={field}>{field.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</Label>
                            <Input
                                id={field}
                                type="number"
                                min={0}
                                step="0.0001"
                                value={form.data[field]}
                                onChange={(e) => form.setData(field, e.target.value)}
                            />
                            <InputError message={form.errors[field]} />
                        </div>
                    ))}
                    <div className="space-y-2">
                        <Label htmlFor="lead_time_days">Lead Time (days)</Label>
                        <Input
                            id="lead_time_days"
                            type="number"
                            min={0}
                            value={form.data.lead_time_days}
                            onChange={(e) => form.setData('lead_time_days', e.target.value)}
                        />
                        <InputError message={form.errors.lead_time_days} />
                    </div>
                </div>
            )}

            {activeTab === 'purchasing' && (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="space-y-2">
                        <Label htmlFor="preferred_supplier_id">Preferred Supplier ID</Label>
                        <Input
                            id="preferred_supplier_id"
                            type="number"
                            min={1}
                            value={form.data.preferred_supplier_id}
                            onChange={(e) => form.setData('preferred_supplier_id', e.target.value)}
                            placeholder="Available when Suppliers module ships"
                        />
                        <InputError message={form.errors.preferred_supplier_id} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="supplier_sku">Supplier SKU</Label>
                        <Input
                            id="supplier_sku"
                            value={form.data.supplier_sku}
                            onChange={(e) => form.setData('supplier_sku', e.target.value)}
                        />
                        <InputError message={form.errors.supplier_sku} />
                    </div>
                    <div className="space-y-2">
                        <Label>Purchase UOM</Label>
                        <Select
                            value={form.data.purchase_uom_id || 'none'}
                            onValueChange={(value) => form.setData('purchase_uom_id', value === 'none' ? '' : value)}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Same as stock UOM" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Same as stock UOM</SelectItem>
                                {uoms.map((uom) => (
                                    <SelectItem key={uom.id} value={uom.id.toString()}>
                                        {uom.code} — {uom.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.purchase_uom_id} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="purchase_price">Purchase Price</Label>
                        <Input
                            id="purchase_price"
                            type="number"
                            min={0}
                            step="0.0001"
                            value={form.data.purchase_price}
                            onChange={(e) => form.setData('purchase_price', e.target.value)}
                        />
                        <InputError message={form.errors.purchase_price} />
                    </div>
                </div>
            )}

            {activeTab === 'sales' && (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="space-y-2">
                        <Label htmlFor="selling_price">Selling Price</Label>
                        <Input
                            id="selling_price"
                            type="number"
                            min={0}
                            step="0.0001"
                            value={form.data.selling_price}
                            onChange={(e) => form.setData('selling_price', e.target.value)}
                        />
                        <InputError message={form.errors.selling_price} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="tax_rate">Tax Rate (%)</Label>
                        <Input
                            id="tax_rate"
                            type="number"
                            min={0}
                            max={100}
                            step="0.0001"
                            value={form.data.tax_rate}
                            onChange={(e) => form.setData('tax_rate', e.target.value)}
                        />
                        <InputError message={form.errors.tax_rate} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="weight">Weight</Label>
                        <Input
                            id="weight"
                            type="number"
                            min={0}
                            step="0.0001"
                            value={form.data.weight}
                            onChange={(e) => form.setData('weight', e.target.value)}
                        />
                        <InputError message={form.errors.weight} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="dimensions">Dimensions</Label>
                        <Input
                            id="dimensions"
                            value={form.data.dimensions}
                            onChange={(e) => form.setData('dimensions', e.target.value)}
                            placeholder="e.g. 10x20x30 cm"
                        />
                        <InputError message={form.errors.dimensions} />
                    </div>
                </div>
            )}

            {activeTab === 'manufacturing' && (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <ToggleField
                        id="make_to_stock"
                        label="Make to stock"
                        checked={form.data.make_to_stock}
                        onChange={(checked) => form.setData('make_to_stock', checked)}
                        error={form.errors.make_to_stock}
                    />
                    <ToggleField
                        id="make_to_order"
                        label="Make to order"
                        checked={form.data.make_to_order}
                        onChange={(checked) => form.setData('make_to_order', checked)}
                        error={form.errors.make_to_order}
                    />
                    <ToggleField
                        id="bom_required"
                        label="BOM required"
                        checked={form.data.bom_required}
                        onChange={(checked) => form.setData('bom_required', checked)}
                        error={form.errors.bom_required}
                    />
                    <ToggleField
                        id="routing_required"
                        label="Routing required"
                        checked={form.data.routing_required}
                        onChange={(checked) => form.setData('routing_required', checked)}
                        error={form.errors.routing_required}
                    />
                </div>
            )}

            {activeTab === 'traceability' && (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <ToggleField
                        id="lot_tracking"
                        label="Lot tracking"
                        checked={form.data.lot_tracking}
                        onChange={(checked) => {
                            form.setData((data) => ({
                                ...data,
                                lot_tracking: checked,
                                serial_tracking: checked ? false : data.serial_tracking,
                            }));
                        }}
                        error={form.errors.lot_tracking}
                    />
                    <ToggleField
                        id="serial_tracking"
                        label="Serial tracking"
                        checked={form.data.serial_tracking}
                        onChange={(checked) => {
                            form.setData((data) => ({
                                ...data,
                                serial_tracking: checked,
                                lot_tracking: checked ? false : data.lot_tracking,
                            }));
                        }}
                        error={form.errors.serial_tracking}
                    />
                    <ToggleField
                        id="expiry_tracking"
                        label="Expiry tracking"
                        checked={form.data.expiry_tracking}
                        onChange={(checked) => form.setData('expiry_tracking', checked)}
                        error={form.errors.expiry_tracking}
                    />
                    <p className="sm:col-span-2 text-xs text-muted-foreground">
                        Lot tracking and serial tracking are mutually exclusive.
                    </p>
                </div>
            )}

            {activeTab === 'attachments' && (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="space-y-2 sm:col-span-2">
                        <Label htmlFor="image">Product Image</Label>
                        {(product?.image_url || product?.image_path) && !form.data.remove_image && (
                            <div className="flex items-center gap-3 mb-2">
                                <img
                                    src={product.image_url || `/storage/${product.image_path}`}
                                    alt={product.name}
                                    className="size-16 rounded-md object-cover border border-border/60"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => form.setData('remove_image', true)}
                                >
                                    Remove image
                                </Button>
                            </div>
                        )}
                        <Input
                            id="image"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={(e) => {
                                form.setData('image', e.target.files?.[0] ?? null);
                                form.setData('remove_image', false);
                            }}
                        />
                        <InputError message={form.errors.image} />
                    </div>
                    {([
                        ['datasheet', 'Datasheet (PDF)', 'datasheet_path', 'remove_datasheet'],
                        ['safety_sheet', 'Safety Sheet (PDF)', 'safety_sheet_path', 'remove_safety_sheet'],
                        ['technical_drawing', 'Technical Drawing', 'technical_drawing_path', 'remove_technical_drawing'],
                    ] as const).map(([field, label, pathKey, removeKey]) => (
                        <div key={field} className="space-y-2">
                            <Label htmlFor={field}>{label}</Label>
                            {product?.[pathKey] && !form.data[removeKey] && (
                                <div className="flex items-center gap-2 text-xs">
                                    <a
                                        href={`/storage/${product[pathKey]}`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-primary underline"
                                    >
                                        View current file
                                    </a>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="h-7"
                                        onClick={() => form.setData(removeKey, true)}
                                    >
                                        Remove
                                    </Button>
                                </div>
                            )}
                            <Input
                                id={field}
                                type="file"
                                accept={field === 'technical_drawing' ? '.pdf,image/jpeg,image/png' : '.pdf'}
                                onChange={(e) => {
                                    form.setData(field, e.target.files?.[0] ?? null);
                                    form.setData(removeKey, false);
                                }}
                            />
                            <InputError message={form.errors[field]} />
                        </div>
                    ))}
                </div>
            )}

            <div className="flex items-center justify-end gap-2 border-t border-border/60 pt-4">
                <Button type="button" variant="outline" onClick={onCancel} disabled={form.processing}>
                    Cancel
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Saving…' : submitLabel}
                </Button>
            </div>
        </form>
    );
}
