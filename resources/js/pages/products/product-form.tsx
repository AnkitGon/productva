import React, { useCallback, useRef, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { router } from '@inertiajs/react';
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
import { AlertCircle, CheckCircle2, ExternalLink, File, FileText, Image as ImageIcon, Package, ShieldCheck, Trash2, Upload, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

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

export interface WarehouseOption {
    id: number;
    code: string;
    name: string;
}

export interface ProductAttachmentRecord {
    id: number;
    product_id: number;
    original_name: string;
    file_path: string;
    file_url: string;
    mime_type: string | null;
    file_size: number;
    type: string;
    sort_order: number;
}

export interface PendingAttachment {
    id: string; // local UUID for React key
    file: File;
    type: string;
    previewUrl: string | null; // object URL for images
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
    // Inventory
    track_inventory: boolean;
    allow_negative_stock: boolean;
    reorder_level: string | number | null;
    minimum_stock: string | number | null;
    maximum_stock: string | number | null;
    safety_stock: string | number | null;
    economic_order_quantity: string | number | null;
    lead_time_days: number | null;
    inventory_valuation_method: string;
    default_warehouse_id: number | null;
    opening_stock: string | number | null;
    opening_cost: string | number | null;
    // Manufacturing
    make_to_stock: boolean;
    make_to_order: boolean;
    bom_required: boolean;
    routing_required: boolean;
    backflush_material: boolean;
    manufacturing_uom_id: number | null;
    // Traceability
    lot_tracking: boolean;
    serial_tracking: boolean;
    expiry_tracking: boolean;
    shelf_life_days: number | null;
    // Purchasing
    preferred_supplier_id: number | null;
    supplier_sku: string | null;
    purchase_uom_id: number | null;
    purchase_price: string | number | null;
    // Sales
    selling_price: string | number | null;
    sales_uom_id: number | null;
    tax_class: string | null;
    hsn_sac_code: string | null;
    default_discount: string | number | null;
    tax_rate: string | number | null;
    weight: string | number | null;
    dimensions: string | null;
    // Media (legacy single-file columns)
    image_path: string | null;
    image_url?: string | null;
    datasheet_path: string | null;
    safety_sheet_path: string | null;
    technical_drawing_path: string | null;
    // Attachments (multi-file)
    attachments?: ProductAttachmentRecord[];
    // Additional
    brand: string | null;
    manufacturer: string | null;
    country_of_origin: string | null;
    abc_classification: string | null;
    xyz_classification: string | null;
    notes: string | null;
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
    // Inventory
    track_inventory: boolean;
    allow_negative_stock: boolean;
    reorder_level: string;
    minimum_stock: string;
    maximum_stock: string;
    safety_stock: string;
    economic_order_quantity: string;
    lead_time_days: string;
    inventory_valuation_method: string;
    default_warehouse_id: string;
    opening_stock: string;
    opening_cost: string;
    // Manufacturing
    make_to_stock: boolean;
    make_to_order: boolean;
    bom_required: boolean;
    routing_required: boolean;
    backflush_material: boolean;
    manufacturing_uom_id: string;
    // Traceability
    lot_tracking: boolean;
    serial_tracking: boolean;
    expiry_tracking: boolean;
    shelf_life_days: string;
    // Purchasing
    preferred_supplier_id: string;
    supplier_sku: string;
    purchase_uom_id: string;
    purchase_price: string;
    // Sales
    selling_price: string;
    sales_uom_id: string;
    tax_class: string;
    hsn_sac_code: string;
    default_discount: string;
    tax_rate: string;
    weight: string;
    dimensions: string;
    // Media
    image: File | null;
    datasheet: File | null;
    safety_sheet: File | null;
    technical_drawing: File | null;
    remove_image: boolean;
    remove_datasheet: boolean;
    remove_safety_sheet: boolean;
    remove_technical_drawing: boolean;
    // Additional
    brand: string;
    manufacturer: string;
    country_of_origin: string;
    abc_classification: string;
    xyz_classification: string;
    notes: string;
};

type TabId = 'general' | 'inventory' | 'purchasing' | 'sales' | 'manufacturing' | 'traceability' | 'attachments' | 'additional';

const TABS: { id: TabId; label: string }[] = [
    { id: 'general', label: 'General' },
    { id: 'inventory', label: 'Inventory' },
    { id: 'purchasing', label: 'Purchasing' },
    { id: 'sales', label: 'Sales' },
    { id: 'manufacturing', label: 'Manufacturing' },
    { id: 'traceability', label: 'Traceability' },
    { id: 'attachments', label: 'Attachments' },
    { id: 'additional', label: 'Additional' },
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
        economic_order_quantity: '',
        lead_time_days: '',
        inventory_valuation_method: 'FIFO',
        default_warehouse_id: '',
        opening_stock: '',
        opening_cost: '',
        make_to_stock: false,
        make_to_order: false,
        bom_required: false,
        routing_required: false,
        backflush_material: false,
        manufacturing_uom_id: '',
        lot_tracking: false,
        serial_tracking: false,
        expiry_tracking: false,
        shelf_life_days: '',
        preferred_supplier_id: '',
        supplier_sku: '',
        purchase_uom_id: '',
        purchase_price: '',
        selling_price: '',
        sales_uom_id: '',
        tax_class: '',
        hsn_sac_code: '',
        default_discount: '',
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
        brand: '',
        manufacturer: '',
        country_of_origin: '',
        abc_classification: '',
        xyz_classification: '',
        notes: '',
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
        economic_order_quantity: num(product.economic_order_quantity),
        lead_time_days: num(product.lead_time_days),
        inventory_valuation_method: product.inventory_valuation_method ?? 'FIFO',
        default_warehouse_id: product.default_warehouse_id?.toString() ?? '',
        opening_stock: num(product.opening_stock),
        opening_cost: num(product.opening_cost),
        make_to_stock: product.make_to_stock,
        make_to_order: product.make_to_order,
        bom_required: product.bom_required,
        routing_required: product.routing_required,
        backflush_material: product.backflush_material,
        manufacturing_uom_id: product.manufacturing_uom_id?.toString() ?? '',
        lot_tracking: product.lot_tracking,
        serial_tracking: product.serial_tracking,
        expiry_tracking: product.expiry_tracking,
        shelf_life_days: num(product.shelf_life_days),
        preferred_supplier_id: product.preferred_supplier_id?.toString() ?? '',
        supplier_sku: product.supplier_sku ?? '',
        purchase_uom_id: product.purchase_uom_id?.toString() ?? '',
        purchase_price: num(product.purchase_price),
        selling_price: num(product.selling_price),
        sales_uom_id: product.sales_uom_id?.toString() ?? '',
        tax_class: product.tax_class ?? '',
        hsn_sac_code: product.hsn_sac_code ?? '',
        default_discount: num(product.default_discount),
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
        brand: product.brand ?? '',
        manufacturer: product.manufacturer ?? '',
        country_of_origin: product.country_of_origin ?? '',
        abc_classification: product.abc_classification ?? '',
        xyz_classification: product.xyz_classification ?? '',
        notes: product.notes ?? '',
    };
}

interface Props {
    initial: ProductFormData;
    categories: CategoryOption[];
    uoms: UomOption[];
    types: string[];
    statuses: string[];
    warehouses: WarehouseOption[];
    valuationMethods: string[];
    taxClasses: string[];
    product?: ProductRecord | null;
    submitLabel: string;
    onSubmit: (
        form: ReturnType<typeof useForm<ProductFormData>>,
        pendingAttachments: PendingAttachment[],
        deleteAttachmentIds: number[]
    ) => void;
    onCancel: () => void;
}

function ToggleField({
    id,
    label,
    description,
    checked,
    onChange,
    error,
    disabled = false,
}: {
    id: string;
    label: string;
    description?: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    error?: string;
    disabled?: boolean;
}) {
    return (
        <div className={`flex items-start gap-3 rounded-lg border border-border/60 px-3 py-2.5 ${disabled ? 'opacity-60' : ''}`}>
            <Checkbox
                id={id}
                checked={checked}
                disabled={disabled}
                onCheckedChange={(value) => onChange(value === true)}
                className="mt-0.5"
            />
            <div className="space-y-0.5">
                <Label htmlFor={id} className="font-medium leading-none">
                    {label}
                </Label>
                {description && <p className="text-xs text-muted-foreground">{description}</p>}
                <InputError message={error} />
            </div>
        </div>
    );
}

function SectionHeading({ children }: { children: React.ReactNode }) {
    return (
        <div className="sm:col-span-2 flex items-center gap-2 pt-1">
            <h3 className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">{children}</h3>
            <div className="flex-1 h-px bg-border/60" />
        </div>
    );
}

const ATTACHMENT_TYPE_OPTIONS = [
    { value: 'image', label: 'Image', color: 'bg-blue-500/10 text-blue-700 dark:text-blue-400' },
    { value: 'datasheet', label: 'Datasheet', color: 'bg-amber-500/10 text-amber-700 dark:text-amber-400' },
    { value: 'safety_sheet', label: 'Safety Sheet', color: 'bg-green-500/10 text-green-700 dark:text-green-400' },
    { value: 'technical_drawing', label: 'Technical Drawing', color: 'bg-purple-500/10 text-purple-700 dark:text-purple-400' },
    { value: 'certificate', label: 'Certificate', color: 'bg-rose-500/10 text-rose-700 dark:text-rose-400' },
    { value: 'manual', label: 'Manual', color: 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-400' },
    { value: 'other', label: 'Other', color: 'bg-muted text-muted-foreground' },
];

function typeBadgeClass(type: string) {
    return ATTACHMENT_TYPE_OPTIONS.find((o) => o.value === type)?.color ?? 'bg-muted text-muted-foreground';
}

function typeLabel(type: string) {
    return ATTACHMENT_TYPE_OPTIONS.find((o) => o.value === type)?.label ?? type;
}

function formatBytes(bytes: number) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
}

function guessType(file: File): string {
    if (file.type.startsWith('image/')) return 'image';
    const name = file.name.toLowerCase();
    if (name.includes('safety') || name.includes('sds') || name.includes('msds')) return 'safety_sheet';
    if (name.includes('datasheet') || name.includes('spec')) return 'datasheet';
    if (name.includes('drawing') || name.includes('blueprint') || name.includes('dxf')) return 'technical_drawing';
    if (name.includes('cert')) return 'certificate';
    if (name.includes('manual') || name.includes('guide')) return 'manual';
    return 'other';
}

function uid() {
    return Math.random().toString(36).slice(2);
}

export function ProductForm({
    initial,
    categories,
    uoms,
    types,
    statuses,
    warehouses,
    valuationMethods,
    taxClasses,
    product,
    submitLabel,
    onSubmit,
    onCancel,
}: Props) {
    const [activeTab, setActiveTab] = useState<TabId>('general');
    const form = useForm<ProductFormData>(initial);

    // ── Attachment state ────────────────────────────────────────────────
    const [pendingAttachments, setPendingAttachments] = useState<PendingAttachment[]>([]);
    const [deleteAttachmentIds, setDeleteAttachmentIds] = useState<number[]>([]);
    const [isDragging, setIsDragging] = useState(false);
    const uploadInputRef = useRef<HTMLInputElement>(null);

    const addFiles = useCallback((files: FileList | File[]) => {
        const newItems: PendingAttachment[] = Array.from(files).map((file) => ({
            id: uid(),
            file,
            type: guessType(file),
            previewUrl: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
        }));
        setPendingAttachments((prev) => [...prev, ...newItems]);
    }, []);

    const removePending = useCallback((id: string) => {
        setPendingAttachments((prev) => {
            const item = prev.find((p) => p.id === id);
            if (item?.previewUrl) URL.revokeObjectURL(item.previewUrl);
            return prev.filter((p) => p.id !== id);
        });
    }, []);

    const updatePendingType = useCallback((id: string, type: string) => {
        setPendingAttachments((prev) => prev.map((p) => (p.id === id ? { ...p, type } : p)));
    }, []);

    const deleteExisting = useCallback(
        (att: ProductAttachmentRecord) => {
            if (!product?.id) return;
            // Immediately delete via Inertia (no form submit needed)
            router.delete(`/products/${product.id}/attachments/${att.id}`, { preserveScroll: true });
        },
        [product]
    );

    const markForDeletion = useCallback((id: number) => {
        setDeleteAttachmentIds((prev) => (prev.includes(id) ? prev : [...prev, id]));
    }, []);

    const handleDrop = useCallback(
        (e: React.DragEvent) => {
            e.preventDefault();
            setIsDragging(false);
            addFiles(e.dataTransfer.files);
        },
        [addFiles]
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onSubmit(form, pendingAttachments, deleteAttachmentIds);
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

            {/* ── GENERAL ─────────────────────────────────────────────── */}
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

            {/* ── INVENTORY ───────────────────────────────────────────── */}
            {activeTab === 'inventory' && (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <SectionHeading>Stock Settings</SectionHeading>
                    <ToggleField
                        id="track_inventory"
                        label="Track Inventory"
                        description="Enable stock level tracking for this product"
                        checked={form.data.track_inventory}
                        onChange={(checked) => {
                            form.setData((data) => ({
                                ...data,
                                track_inventory: checked,
                                allow_negative_stock: checked ? data.allow_negative_stock : false,
                                default_warehouse_id: checked ? data.default_warehouse_id : '',
                                opening_stock: checked ? data.opening_stock : '0',
                                opening_cost: checked ? data.opening_cost : '0',
                            }));
                        }}
                        error={form.errors.track_inventory}
                    />
                    <ToggleField
                        id="allow_negative_stock"
                        label="Allow Negative Stock"
                        description={
                            form.data.track_inventory
                                ? 'Allow stock to go below zero'
                                : 'Requires inventory tracking to be enabled'
                        }
                        checked={form.data.allow_negative_stock}
                        disabled={!form.data.track_inventory}
                        onChange={(checked) => {
                            if (!form.data.track_inventory) {
                                return;
                            }
                            form.setData('allow_negative_stock', checked);
                        }}
                        error={form.errors.allow_negative_stock}
                    />

                    {form.data.track_inventory && (
                        <>
                            <div className="space-y-2">
                                <Label>Default Warehouse</Label>
                                <Select
                                    value={form.data.default_warehouse_id || 'none'}
                                    onValueChange={(value) =>
                                        form.setData('default_warehouse_id', value === 'none' ? '' : value)
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="None selected" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">None</SelectItem>
                                        {warehouses.map((w) => (
                                            <SelectItem key={w.id} value={w.id.toString()}>
                                                {w.code} — {w.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-xs text-muted-foreground">Stock goes here by default on first receipt</p>
                                <InputError message={form.errors.default_warehouse_id} />
                            </div>

                            <div className="space-y-2">
                                <Label>Inventory Valuation Method</Label>
                                <Select
                                    value={form.data.inventory_valuation_method}
                                    onValueChange={(value) => form.setData('inventory_valuation_method', value)}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {valuationMethods.map((method) => (
                                            <SelectItem key={method} value={method}>
                                                {method}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.inventory_valuation_method} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="opening_stock">Opening Stock</Label>
                                <Input
                                    id="opening_stock"
                                    type="number"
                                    min={0}
                                    step="0.0001"
                                    value={form.data.opening_stock}
                                    onChange={(e) => form.setData('opening_stock', e.target.value)}
                                    placeholder="0"
                                />
                                <p className="text-xs text-muted-foreground">Initial on-hand quantity for this product</p>
                                <InputError message={form.errors.opening_stock} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="opening_cost">Opening Cost</Label>
                                <Input
                                    id="opening_cost"
                                    type="number"
                                    min={0}
                                    step="0.0001"
                                    value={form.data.opening_cost}
                                    onChange={(e) => form.setData('opening_cost', e.target.value)}
                                    placeholder="0"
                                />
                                <p className="text-xs text-muted-foreground">Unit cost for opening stock valuation</p>
                                <InputError message={form.errors.opening_cost} />
                            </div>
                        </>
                    )}

                    <SectionHeading>Reorder Settings</SectionHeading>
                    <div className="space-y-2">
                        <Label htmlFor="minimum_stock">Minimum Stock</Label>
                        <Input
                            id="minimum_stock"
                            type="number"
                            min={0}
                            step="0.0001"
                            value={form.data.minimum_stock}
                            onChange={(e) => form.setData('minimum_stock', e.target.value)}
                            placeholder="0"
                        />
                        <InputError message={form.errors.minimum_stock} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="maximum_stock">Maximum Stock</Label>
                        <Input
                            id="maximum_stock"
                            type="number"
                            min={0}
                            step="0.0001"
                            value={form.data.maximum_stock}
                            onChange={(e) => form.setData('maximum_stock', e.target.value)}
                            placeholder="0"
                        />
                        <InputError message={form.errors.maximum_stock} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="reorder_level">Reorder Point</Label>
                        <Input
                            id="reorder_level"
                            type="number"
                            min={0}
                            step="0.0001"
                            value={form.data.reorder_level}
                            onChange={(e) => form.setData('reorder_level', e.target.value)}
                            placeholder="0"
                        />
                        <p className="text-xs text-muted-foreground">Stock level that triggers a reorder</p>
                        <InputError message={form.errors.reorder_level} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="safety_stock">Safety Stock</Label>
                        <Input
                            id="safety_stock"
                            type="number"
                            min={0}
                            step="0.0001"
                            value={form.data.safety_stock}
                            onChange={(e) => form.setData('safety_stock', e.target.value)}
                            placeholder="0"
                        />
                        <p className="text-xs text-muted-foreground">Buffer stock for unexpected demand</p>
                        <InputError message={form.errors.safety_stock} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="economic_order_quantity">
                            Economic Order Quantity{' '}
                            <span className="text-muted-foreground text-xs font-normal">(optional)</span>
                        </Label>
                        <Input
                            id="economic_order_quantity"
                            type="number"
                            min={0}
                            step="0.0001"
                            value={form.data.economic_order_quantity}
                            onChange={(e) => form.setData('economic_order_quantity', e.target.value)}
                            placeholder="0"
                        />
                        <p className="text-xs text-muted-foreground">Optimal order quantity to minimize total costs</p>
                        <InputError message={form.errors.economic_order_quantity} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="lead_time_days">Supplier Lead Time (days)</Label>
                        <Input
                            id="lead_time_days"
                            type="number"
                            min={0}
                            value={form.data.lead_time_days}
                            onChange={(e) => form.setData('lead_time_days', e.target.value)}
                            placeholder="0"
                        />
                        <InputError message={form.errors.lead_time_days} />
                    </div>
                </div>
            )}

            {/* ── PURCHASING ──────────────────────────────────────────── */}
            {activeTab === 'purchasing' && (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {/* Future: multi-supplier */}
                    <div className="sm:col-span-2 rounded-lg border border-dashed border-border bg-muted/20 p-4 flex items-start gap-3">
                        <div className="mt-0.5 rounded-md bg-primary/10 p-1.5">
                            <Package className="size-4 text-primary" />
                        </div>
                        <div>
                            <p className="text-sm font-medium text-foreground">Multiple Product Suppliers</p>
                            <p className="text-xs text-muted-foreground mt-0.5">
                                Full supplier management (Supplier SKU, Lead Time, Purchase Price, MOQ per supplier) will be
                                available when the Suppliers module ships.
                            </p>
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="supplier_sku">Supplier SKU</Label>
                        <Input
                            id="supplier_sku"
                            value={form.data.supplier_sku}
                            onChange={(e) => form.setData('supplier_sku', e.target.value)}
                            placeholder="Supplier's part number"
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
                            placeholder="0.00"
                        />
                        <InputError message={form.errors.purchase_price} />
                    </div>
                </div>
            )}

            {/* ── SALES ───────────────────────────────────────────────── */}
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
                            placeholder="0.00"
                        />
                        <InputError message={form.errors.selling_price} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="default_discount">Default Discount (%)</Label>
                        <Input
                            id="default_discount"
                            type="number"
                            min={0}
                            max={100}
                            step="0.01"
                            value={form.data.default_discount}
                            onChange={(e) => form.setData('default_discount', e.target.value)}
                            placeholder="0"
                        />
                        <InputError message={form.errors.default_discount} />
                    </div>
                    <div className="space-y-2">
                        <Label>Sales UOM</Label>
                        <Select
                            value={form.data.sales_uom_id || 'none'}
                            onValueChange={(value) => form.setData('sales_uom_id', value === 'none' ? '' : value)}
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
                        <InputError message={form.errors.sales_uom_id} />
                    </div>
                    <div className="space-y-2">
                        <Label>Tax Class</Label>
                        <Select
                            value={form.data.tax_class || 'none'}
                            onValueChange={(value) => form.setData('tax_class', value === 'none' ? '' : value)}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="None" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">None</SelectItem>
                                {taxClasses.filter((t) => t !== 'None').map((tc) => (
                                    <SelectItem key={tc} value={tc}>
                                        {tc}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.tax_class} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="hsn_sac_code">HSN / SAC Code</Label>
                        <Input
                            id="hsn_sac_code"
                            value={form.data.hsn_sac_code}
                            onChange={(e) => form.setData('hsn_sac_code', e.target.value)}
                            placeholder="e.g. 8471"
                        />
                        <p className="text-xs text-muted-foreground">Harmonized System / Service Accounting Code</p>
                        <InputError message={form.errors.hsn_sac_code} />
                    </div>
                    <SectionHeading>Shipping</SectionHeading>
                    <div className="space-y-2">
                        <Label htmlFor="weight">Weight</Label>
                        <Input
                            id="weight"
                            type="number"
                            min={0}
                            step="0.0001"
                            value={form.data.weight}
                            onChange={(e) => form.setData('weight', e.target.value)}
                            placeholder="0.00"
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

            {/* ── MANUFACTURING ───────────────────────────────────────── */}
            {activeTab === 'manufacturing' && (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <SectionHeading>Production Strategy</SectionHeading>
                    <ToggleField
                        id="make_to_stock"
                        label="Make to Stock"
                        description="Produce for inventory based on forecast"
                        checked={form.data.make_to_stock}
                        onChange={(checked) => form.setData('make_to_stock', checked)}
                        error={form.errors.make_to_stock}
                    />
                    <ToggleField
                        id="make_to_order"
                        label="Make to Order"
                        description="Produce only when a customer order is placed"
                        checked={form.data.make_to_order}
                        onChange={(checked) => form.setData('make_to_order', checked)}
                        error={form.errors.make_to_order}
                    />
                    <SectionHeading>Requirements</SectionHeading>
                    <ToggleField
                        id="bom_required"
                        label="BOM Required"
                        description="This product requires a Bill of Materials"
                        checked={form.data.bom_required}
                        onChange={(checked) => form.setData('bom_required', checked)}
                        error={form.errors.bom_required}
                    />
                    <ToggleField
                        id="routing_required"
                        label="Routing Required"
                        description="This product requires a production routing"
                        checked={form.data.routing_required}
                        onChange={(checked) => form.setData('routing_required', checked)}
                        error={form.errors.routing_required}
                    />
                    <ToggleField
                        id="backflush_material"
                        label="Backflush Material"
                        description="Automatically consume materials on production completion"
                        checked={form.data.backflush_material}
                        onChange={(checked) => form.setData('backflush_material', checked)}
                        error={form.errors.backflush_material}
                    />
                    <SectionHeading>Units</SectionHeading>
                    <div className="space-y-2">
                        <Label>Manufacturing UOM</Label>
                        <Select
                            value={form.data.manufacturing_uom_id || 'none'}
                            onValueChange={(value) =>
                                form.setData('manufacturing_uom_id', value === 'none' ? '' : value)
                            }
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
                        <InputError message={form.errors.manufacturing_uom_id} />
                    </div>
                </div>
            )}

            {/* ── TRACEABILITY ─────────────────────────────────────────── */}
            {activeTab === 'traceability' && (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <SectionHeading>Batch &amp; Serial Tracking</SectionHeading>
                    <ToggleField
                        id="lot_tracking"
                        label="Lot / Batch Tracking"
                        description="Track stock by lot or batch number"
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
                        label="Serial Number Tracking"
                        description="Track each unit by a unique serial number"
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
                    <p className="sm:col-span-2 text-xs text-muted-foreground">
                        Lot tracking and serial tracking are mutually exclusive.
                    </p>
                    <SectionHeading>Expiry &amp; Shelf Life</SectionHeading>
                    <ToggleField
                        id="expiry_tracking"
                        label="Expiry Tracking"
                        description="Track expiry dates on received stock"
                        checked={form.data.expiry_tracking}
                        onChange={(checked) => form.setData('expiry_tracking', checked)}
                        error={form.errors.expiry_tracking}
                    />
                    <div className="space-y-2">
                        <Label htmlFor="shelf_life_days">Shelf Life (days)</Label>
                        <Input
                            id="shelf_life_days"
                            type="number"
                            min={0}
                            value={form.data.shelf_life_days}
                            onChange={(e) => form.setData('shelf_life_days', e.target.value)}
                            placeholder="e.g. 365"
                        />
                        <p className="text-xs text-muted-foreground">Maximum usable life from manufacturing date</p>
                        <InputError message={form.errors.shelf_life_days} />
                    </div>
                </div>
            )}

            {/* ── ATTACHMENTS ──────────────────────────────────────────── */}
            {activeTab === 'attachments' && (
                <div className="space-y-4">
                    {/* Drop zone */}
                    <div
                        onDragOver={(e) => { e.preventDefault(); setIsDragging(true); }}
                        onDragLeave={() => setIsDragging(false)}
                        onDrop={handleDrop}
                        onClick={() => uploadInputRef.current?.click()}
                        className={`flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed cursor-pointer py-10 transition-all ${
                            isDragging
                                ? 'border-primary bg-primary/5 scale-[1.01]'
                                : 'border-border/60 hover:border-primary/50 hover:bg-muted/30'
                        }`}
                    >
                        <div className={`rounded-full p-3 transition-colors ${ isDragging ? 'bg-primary/10' : 'bg-muted/50' }`}>
                            <Upload className={`size-6 transition-colors ${ isDragging ? 'text-primary' : 'text-muted-foreground' }`} />
                        </div>
                        <div className="text-center">
                            <p className="text-sm font-medium text-foreground">
                                {isDragging ? 'Drop files here' : 'Drag & drop or click to upload'}
                            </p>
                            <p className="text-xs text-muted-foreground mt-1">
                                Images, PDFs, drawings — any file type · No limit per upload
                            </p>
                        </div>
                        <input
                            ref={uploadInputRef}
                            type="file"
                            multiple
                            accept="image/*,.pdf,.dwg,.dxf,.doc,.docx,.xls,.xlsx"
                            className="hidden"
                            onClick={(e) => e.stopPropagation()}
                            onChange={(e) => {
                                if (e.target.files) addFiles(e.target.files);
                                e.target.value = '';
                            }}
                        />
                    </div>

                    {/* Pending files (not yet saved) */}
                    {pendingAttachments.length > 0 && (
                        <div className="space-y-2">
                            <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground flex items-center gap-2">
                                <AlertCircle className="size-3.5 text-amber-500" />
                                Pending: will upload on save ({pendingAttachments.length})
                            </p>
                            <div className="rounded-xl border border-border/60 overflow-hidden">
                                {pendingAttachments.map((p, idx) => (
                                    <div
                                        key={p.id}
                                        className={`flex items-center gap-3 p-3 ${
                                            idx > 0 ? 'border-t border-border/40' : ''
                                        }`}
                                    >
                                        {/* Preview or icon */}
                                        {p.previewUrl ? (
                                            <img
                                                src={p.previewUrl}
                                                alt={p.file.name}
                                                className="size-10 rounded-lg object-cover border border-border/60 flex-shrink-0"
                                            />
                                        ) : (
                                            <div className="size-10 rounded-lg bg-muted/40 border border-border/60 flex items-center justify-center flex-shrink-0">
                                                <FileText className="size-4 text-muted-foreground" />
                                            </div>
                                        )}

                                        {/* Name + size */}
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-foreground truncate">{p.file.name}</p>
                                            <p className="text-xs text-muted-foreground">{formatBytes(p.file.size)}</p>
                                        </div>

                                        {/* Type selector */}
                                        <select
                                            value={p.type}
                                            onChange={(e) => updatePendingType(p.id, e.target.value)}
                                            className="h-7 rounded-md border border-input bg-transparent px-2 text-xs focus:outline-none focus:ring-1 focus:ring-ring"
                                        >
                                            {ATTACHMENT_TYPE_OPTIONS.map((opt) => (
                                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                                            ))}
                                        </select>

                                        {/* Remove pending */}
                                        <button
                                            type="button"
                                            onClick={() => removePending(p.id)}
                                            className="flex-shrink-0 text-muted-foreground hover:text-destructive transition-colors"
                                        >
                                            <X className="size-4" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Existing uploaded attachments */}
                    {product?.attachments && product.attachments.length > 0 && (() => {
                        const visible = product.attachments.filter(
                            (a) => !deleteAttachmentIds.includes(a.id)
                        );
                        const images = visible.filter((a) => a.mime_type?.startsWith('image/'));
                        const docs = visible.filter((a) => !a.mime_type?.startsWith('image/'));

                        return (
                            <div className="space-y-3">
                                <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground flex items-center gap-2">
                                    <CheckCircle2 className="size-3.5 text-green-500" />
                                    Uploaded ({visible.length})
                                </p>

                                {/* Image grid */}
                                {images.length > 0 && (
                                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                                        {images.map((att) => (
                                            <div key={att.id} className="group relative rounded-xl overflow-hidden border border-border/60 bg-muted/20 aspect-square">
                                                <img
                                                    src={att.file_url}
                                                    alt={att.original_name}
                                                    className="w-full h-full object-cover"
                                                />
                                                {/* Overlay */}
                                                <div className="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-all flex items-start justify-end p-1.5 gap-1 opacity-0 group-hover:opacity-100">
                                                    <a
                                                        href={att.file_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="bg-white/90 text-foreground rounded-md p-1 hover:bg-white"
                                                        onClick={(e) => e.stopPropagation()}
                                                    >
                                                        <ExternalLink className="size-3.5" />
                                                    </a>
                                                    <button
                                                        type="button"
                                                        className="bg-white/90 text-destructive rounded-md p-1 hover:bg-white"
                                                        onClick={() => deleteExisting(att)}
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </button>
                                                </div>
                                                {/* Type badge */}
                                                <div className="absolute bottom-1.5 left-1.5">
                                                    <span className={`text-[10px] font-medium px-1.5 py-0.5 rounded-full ${typeBadgeClass(att.type)}`}>
                                                        {typeLabel(att.type)}
                                                    </span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {/* Document list */}
                                {docs.length > 0 && (
                                    <div className="rounded-xl border border-border/60 overflow-hidden divide-y divide-border/40">
                                        {docs.map((att) => (
                                            <div key={att.id} className="flex items-center gap-3 p-3 hover:bg-muted/20 transition-colors">
                                                <div className="size-9 rounded-lg bg-muted/40 border border-border/60 flex items-center justify-center flex-shrink-0">
                                                    {att.mime_type === 'application/pdf'
                                                        ? <FileText className="size-4 text-red-500" />
                                                        : <File className="size-4 text-muted-foreground" />
                                                    }
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <p className="text-sm font-medium text-foreground truncate">{att.original_name}</p>
                                                    <p className="text-xs text-muted-foreground">{formatBytes(att.file_size)}</p>
                                                </div>
                                                <span className={`hidden sm:inline-flex text-[10px] font-medium px-2 py-0.5 rounded-full ${typeBadgeClass(att.type)}`}>
                                                    {typeLabel(att.type)}
                                                </span>
                                                <a
                                                    href={att.file_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="flex-shrink-0 text-muted-foreground hover:text-primary transition-colors"
                                                >
                                                    <ExternalLink className="size-4" />
                                                </a>
                                                <button
                                                    type="button"
                                                    className="flex-shrink-0 text-muted-foreground hover:text-destructive transition-colors"
                                                    onClick={() => deleteExisting(att)}
                                                >
                                                    <Trash2 className="size-4" />
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        );
                    })()}

                    {/* Empty state */}
                    {(!product?.attachments || product.attachments.length === 0) && pendingAttachments.length === 0 && (
                        <div className="text-center py-6 text-muted-foreground">
                            <ImageIcon className="size-8 mx-auto mb-2 opacity-30" />
                            <p className="text-sm">No attachments yet. Upload files above.</p>
                        </div>
                    )}
                </div>
            )}

            {/* ── ADDITIONAL ───────────────────────────────────────────── */}
            {activeTab === 'additional' && (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <SectionHeading>Identity</SectionHeading>
                    <div className="space-y-2">
                        <Label htmlFor="brand">Brand</Label>
                        <Input
                            id="brand"
                            value={form.data.brand}
                            onChange={(e) => form.setData('brand', e.target.value)}
                            placeholder="e.g. Acme Corp"
                        />
                        <InputError message={form.errors.brand} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="manufacturer">Manufacturer</Label>
                        <Input
                            id="manufacturer"
                            value={form.data.manufacturer}
                            onChange={(e) => form.setData('manufacturer', e.target.value)}
                            placeholder="e.g. Acme Manufacturing Ltd"
                        />
                        <InputError message={form.errors.manufacturer} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="country_of_origin">Country of Origin</Label>
                        <Input
                            id="country_of_origin"
                            value={form.data.country_of_origin}
                            onChange={(e) => form.setData('country_of_origin', e.target.value)}
                            placeholder="e.g. India"
                        />
                        <InputError message={form.errors.country_of_origin} />
                    </div>
                    <SectionHeading>Classification</SectionHeading>
                    <div className="space-y-2">
                        <Label>ABC Classification</Label>
                        <Select
                            value={form.data.abc_classification || 'none'}
                            onValueChange={(value) =>
                                form.setData('abc_classification', value === 'none' ? '' : value)
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Not set" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Not set</SelectItem>
                                <SelectItem value="A">A — High value / fast moving</SelectItem>
                                <SelectItem value="B">B — Medium value / moderate movement</SelectItem>
                                <SelectItem value="C">C — Low value / slow moving</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.abc_classification} />
                    </div>
                    <div className="space-y-2">
                        <Label>XYZ Classification</Label>
                        <Select
                            value={form.data.xyz_classification || 'none'}
                            onValueChange={(value) =>
                                form.setData('xyz_classification', value === 'none' ? '' : value)
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Not set" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Not set</SelectItem>
                                <SelectItem value="X">X — Stable / predictable demand</SelectItem>
                                <SelectItem value="Y">Y — Variable demand</SelectItem>
                                <SelectItem value="Z">Z — Irregular / unpredictable demand</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.xyz_classification} />
                    </div>
                    <SectionHeading>Notes</SectionHeading>
                    <div className="space-y-2 sm:col-span-2">
                        <Label htmlFor="notes">Internal Notes</Label>
                        <textarea
                            id="notes"
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            rows={5}
                            placeholder="Any additional notes about this product…"
                            className="flex min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                        />
                        <InputError message={form.errors.notes} />
                    </div>
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
