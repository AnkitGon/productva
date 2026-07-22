import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';
import { ArrowLeft, Edit2, FileText, Package } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/data-table/status-badge';
import { useCan } from '@/hooks/use-can';

interface RelatedCode {
    id: number;
    code: string;
    name: string;
    symbol?: string;
    status?: string;
}

interface ProductDetail {
    id: number;
    sku: string;
    barcode: string | null;
    name: string;
    description: string | null;
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
    purchase_price: string | number | null;
    selling_price: string | number | null;
    tax_rate: string | number | null;
    weight: string | number | null;
    dimensions: string | null;
    image_url: string | null;
    image_path: string | null;
    datasheet_url: string | null;
    safety_sheet_url: string | null;
    technical_drawing_url: string | null;
    category?: RelatedCode | null;
    uom?: RelatedCode | null;
    purchase_uom?: RelatedCode | null;
    creator?: { id: number; name: string } | null;
    updater?: { id: number; name: string } | null;
    created_at?: string;
    updated_at?: string;
}

interface Props {
    product: ProductDetail;
}

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

function display(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value);
}

function yesNo(value: boolean): string {
    return value ? 'Yes' : 'No';
}

function Field({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="space-y-1">
            <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</dt>
            <dd className="text-sm text-foreground">{value}</dd>
        </div>
    );
}

export default function ProductsShow({ product }: Props) {
    const { can } = useCan();
    const [activeTab, setActiveTab] = useState<TabId>('general');

    return (
        <>
            <Head title={`${product.name} - Product`} />

            <div className="flex flex-col gap-6 p-6 w-full max-w-7xl mx-auto">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" asChild className="h-8">
                        <Link href="/products">
                            <ArrowLeft className="size-4 mr-2" />
                            Back to Products
                        </Link>
                    </Button>
                </div>

                <div className="bg-card border border-border/40 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                    <div className="flex items-center gap-5 min-w-0">
                        {product.image_url || product.image_path ? (
                            <img
                                src={product.image_url || `/storage/${product.image_path}`}
                                alt={product.name}
                                className="size-20 rounded-xl object-cover border border-border/60 shrink-0"
                            />
                        ) : (
                            <div className="size-20 rounded-xl bg-muted flex items-center justify-center border border-border/60 shrink-0">
                                <Package className="size-8 text-muted-foreground" />
                            </div>
                        )}
                        <div className="space-y-1.5 min-w-0">
                            <div className="flex items-center gap-2.5 flex-wrap">
                                <h1 className="text-2xl font-bold tracking-tight truncate">{product.name}</h1>
                                <StatusBadge status={product.status} />
                                <Badge variant="outline" className="font-mono text-xs">
                                    {product.sku}
                                </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground font-medium">
                                {product.type}
                                {product.category ? ` · ${product.category.name}` : ''}
                                {product.uom ? ` · ${product.uom.code}` : ''}
                            </p>
                        </div>
                    </div>

                    {can('products.update') && (
                        <Button className="gap-2 shrink-0" onClick={() => router.visit(`/products/${product.id}/edit`)}>
                            <Edit2 className="size-4" />
                            Edit Product
                        </Button>
                    )}
                </div>

                <div className="rounded-xl border border-border/50 bg-card">
                    <div className="flex gap-1 overflow-x-auto border-b border-border/60 px-4 pt-2">
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

                    <div className="p-4 sm:p-6">
                        {activeTab === 'general' && (
                            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <Field label="SKU" value={<span className="font-mono font-semibold">{product.sku}</span>} />
                                <Field label="Barcode" value={display(product.barcode)} />
                                <Field label="Name" value={product.name} />
                                <Field label="Type" value={product.type} />
                                <Field
                                    label="Category"
                                    value={product.category ? `${product.category.code} — ${product.category.name}` : '—'}
                                />
                                <Field
                                    label="UOM"
                                    value={product.uom ? `${product.uom.code} — ${product.uom.name}` : '—'}
                                />
                                <Field label="Status" value={<StatusBadge status={product.status} />} />
                                <div className="sm:col-span-2">
                                    <Field label="Description" value={display(product.description)} />
                                </div>
                            </dl>
                        )}

                        {activeTab === 'inventory' && (
                            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <Field label="Track Inventory" value={yesNo(product.track_inventory)} />
                                <Field label="Allow Negative Stock" value={yesNo(product.allow_negative_stock)} />
                                <Field label="Reorder Level" value={display(product.reorder_level)} />
                                <Field label="Minimum Stock" value={display(product.minimum_stock)} />
                                <Field label="Maximum Stock" value={display(product.maximum_stock)} />
                                <Field label="Safety Stock" value={display(product.safety_stock)} />
                                <Field label="Lead Time (days)" value={display(product.lead_time_days)} />
                            </dl>
                        )}

                        {activeTab === 'purchasing' && (
                            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <Field label="Preferred Supplier ID" value={display(product.preferred_supplier_id)} />
                                <Field label="Supplier SKU" value={display(product.supplier_sku)} />
                                <Field
                                    label="Purchase UOM"
                                    value={
                                        product.purchase_uom
                                            ? `${product.purchase_uom.code} — ${product.purchase_uom.name}`
                                            : 'Same as stock UOM'
                                    }
                                />
                                <Field label="Purchase Price" value={display(product.purchase_price)} />
                            </dl>
                        )}

                        {activeTab === 'sales' && (
                            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <Field label="Selling Price" value={display(product.selling_price)} />
                                <Field label="Tax Rate (%)" value={display(product.tax_rate)} />
                                <Field label="Weight" value={display(product.weight)} />
                                <Field label="Dimensions" value={display(product.dimensions)} />
                            </dl>
                        )}

                        {activeTab === 'manufacturing' && (
                            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <Field label="Make to Stock" value={yesNo(product.make_to_stock)} />
                                <Field label="Make to Order" value={yesNo(product.make_to_order)} />
                                <Field label="BOM Required" value={yesNo(product.bom_required)} />
                                <Field label="Routing Required" value={yesNo(product.routing_required)} />
                            </dl>
                        )}

                        {activeTab === 'traceability' && (
                            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <Field label="Lot Tracking" value={yesNo(product.lot_tracking)} />
                                <Field label="Serial Tracking" value={yesNo(product.serial_tracking)} />
                                <Field label="Expiry Tracking" value={yesNo(product.expiry_tracking)} />
                            </dl>
                        )}

                        {activeTab === 'attachments' && (
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div className="space-y-2">
                                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Product Image</p>
                                    {product.image_url || product.image_path ? (
                                        <img
                                            src={product.image_url || `/storage/${product.image_path}`}
                                            alt={product.name}
                                            className="max-h-48 rounded-lg border border-border/60 object-contain bg-muted/30"
                                        />
                                    ) : (
                                        <p className="text-sm text-muted-foreground">No image uploaded.</p>
                                    )}
                                </div>
                                {([
                                    ['Datasheet', product.datasheet_url],
                                    ['Safety Sheet', product.safety_sheet_url],
                                    ['Technical Drawing', product.technical_drawing_url],
                                ] as const).map(([label, url]) => (
                                    <div key={label} className="space-y-2">
                                        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
                                        {url ? (
                                            <a
                                                href={url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex items-center gap-2 text-sm text-primary underline"
                                            >
                                                <FileText className="size-4" />
                                                View file
                                            </a>
                                        ) : (
                                            <p className="text-sm text-muted-foreground">No file uploaded.</p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

ProductsShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Products', href: '/products' },
        { title: 'Details', href: '#' },
    ],
};
