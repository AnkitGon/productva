import { Head, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    CategoryOption,
    emptyProductForm,
    ProductForm,
    UomOption,
    WarehouseOption,
} from '@/pages/products/product-form';

interface Props {
    categories: CategoryOption[];
    uoms: UomOption[];
    types: string[];
    statuses: string[];
    warehouses: WarehouseOption[];
    valuationMethods: string[];
    taxClasses: string[];
}

export default function ProductsCreate({ categories, uoms, types, statuses, warehouses, valuationMethods, taxClasses }: Props) {
    return (
        <>
            <Head title="Create Product" />

            <div className="flex flex-col gap-6 p-6 w-full max-w-7xl mx-auto">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" className="h-8" onClick={() => router.visit('/products')}>
                        <ArrowLeft className="size-4 mr-2" />
                        Back to Products
                    </Button>
                </div>

                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Create Product</h1>
                    <p className="text-sm text-muted-foreground">
                        Capture general, inventory, purchasing, sales, manufacturing, and traceability settings.
                    </p>
                </div>

                <div className="rounded-xl border border-border/50 bg-card p-4 sm:p-6">
                    <ProductForm
                        initial={emptyProductForm(types)}
                        categories={categories}
                        uoms={uoms}
                        types={types}
                        statuses={statuses}
                        warehouses={warehouses}
                        valuationMethods={valuationMethods}
                        taxClasses={taxClasses}
                        submitLabel="Create Product"
                        onCancel={() => router.visit('/products')}
                        onSubmit={(form, pending, deleteIds) => {
                            form.transform((data) => ({
                                ...data,
                                new_attachments: pending.map((p) => p.file),
                                new_attachment_types: pending.map((p) => p.type),
                                delete_attachment_ids: deleteIds,
                            }));
                            form.post('/products', {
                                forceFormData: true,
                            });
                        }}
                    />
                </div>
            </div>
        </>
    );
}

ProductsCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Products', href: '/products' },
        { title: 'Create', href: '/products/create' },
    ],
};
