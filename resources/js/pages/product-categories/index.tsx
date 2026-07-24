import { Head, router, useForm } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';
import { Edit2, FolderTree, Plus, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
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

interface ParentOption {
    id: number;
    code: string;
    name: string;
    parent_id: number | null;
}

interface ProductCategory {
    id: number;
    code: string;
    name: string;
    description: string | null;
    sort_order: number;
    status: 'Active' | 'Inactive';
    parent_id: number | null;
    products_count?: number;
    children_count?: number;
    parent?: Pick<ParentOption, 'id' | 'code' | 'name'> | null;
}

interface PaginatedCategories {
    data: ProductCategory[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    categories: PaginatedCategories;
    parentOptions: ParentOption[];
    statuses: string[];
    filters: Record<string, string>;
}

type CategoryFormData = {
    parent_id: string;
    code: string;
    name: string;
    description: string;
    sort_order: string;
    status: string;
};

function emptyForm(): CategoryFormData {
    return {
        parent_id: '',
        code: '',
        name: '',
        description: '',
        sort_order: '0',
        status: 'Active',
    };
}

function formFromCategory(category: ProductCategory): CategoryFormData {
    return {
        parent_id: category.parent_id?.toString() ?? '',
        code: category.code,
        name: category.name,
        description: category.description ?? '',
        sort_order: String(category.sort_order ?? 0),
        status: category.status,
    };
}

/** Candidate is invalid if it is the category itself or a descendant (would cycle). */
function isInvalidParentCandidate(
    candidateId: number,
    editingId: number,
    options: ParentOption[],
): boolean {
    if (candidateId === editingId) {
        return true;
    }

    const byId = new Map(options.map((option) => [option.id, option]));
    let current = byId.get(candidateId);
    const visited = new Set<number>();

    while (current?.parent_id) {
        if (current.parent_id === editingId) {
            return true;
        }
        if (visited.has(current.parent_id)) {
            break;
        }
        visited.add(current.parent_id);
        current = byId.get(current.parent_id);
    }

    return false;
}

export default function ProductCategoriesIndex({
    categories,
    parentOptions,
    statuses,
    filters,
}: Props) {
    const { can } = useCan();
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingCategory, setEditingCategory] = useState<ProductCategory | null>(null);
    const [deleteConfirmCategory, setDeleteConfirmCategory] = useState<ProductCategory | null>(null);

    const form = useForm<CategoryFormData>(emptyForm());

    const currentParams: Record<string, string> = {};
    Object.entries(filters ?? {}).forEach(([k, v]) => {
        if (v) {
            currentParams[k] = v;
        }
    });

    const selectableParents = useMemo(() => {
        if (!editingCategory) {
            return parentOptions;
        }

        return parentOptions.filter(
            (option) => !isInvalidParentCandidate(option.id, editingCategory.id, parentOptions),
        );
    }, [editingCategory, parentOptions]);

    const openCreate = () => {
        setEditingCategory(null);
        form.setData(emptyForm());
        form.clearErrors();
        setIsDialogOpen(true);
    };

    const openEdit = (category: ProductCategory) => {
        setEditingCategory(category);
        form.setData(formFromCategory(category));
        form.clearErrors();
        setIsDialogOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const config = {
            onSuccess: () => {
                setIsDialogOpen(false);
                form.reset();
            },
        };

        if (editingCategory) {
            form.put(`/product-categories/${editingCategory.id}`, config);
        } else {
            form.post('/product-categories', config);
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
        router.get('/product-categories', params, { preserveState: true, replace: true });
    };

    const columns: ColumnDef<ProductCategory>[] = [
        {
            key: 'code',
            label: 'Code',
            sortable: true,
            render: (row) => <span className="font-mono text-xs font-semibold">{row.code}</span>,
        },
        {
            key: 'name',
            label: 'Category',
            sortable: true,
            render: (row) => (
                <button
                    type="button"
                    className="font-semibold text-foreground hover:underline cursor-pointer text-left"
                    onClick={() => router.visit(`/products?category_id=${row.id}`)}
                >
                    {row.name}
                </button>
            ),
        },
        {
            key: 'parent',
            label: 'Parent',
            className: 'text-muted-foreground',
            render: (row) => (row.parent ? `${row.parent.code} — ${row.parent.name}` : '—'),
        },
        {
            key: 'products_count',
            label: 'Products',
            className: 'text-muted-foreground',
            render: (row) => {
                const count = row.products_count ?? 0;
                const label = `${count} ${count === 1 ? 'product' : 'products'}`;

                if (count === 0) {
                    return label;
                }

                return (
                    <button
                        type="button"
                        className="text-foreground hover:underline cursor-pointer font-medium"
                        onClick={() => router.visit(`/products?category_id=${row.id}`)}
                    >
                        {label}
                    </button>
                );
            },
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (row) => <StatusBadge status={row.status} />,
        },
        {
            key: 'description',
            label: 'Description',
            defaultVisible: false,
            className: 'text-muted-foreground',
            render: (row) => row.description ?? '—',
        },
        {
            key: 'sort_order',
            label: 'Sort Order',
            defaultVisible: false,
            className: 'text-muted-foreground',
            render: (row) => row.sort_order ?? 0,
        },
    ];

    const meta: TableMeta = {
        current_page: categories.current_page,
        last_page: categories.last_page,
        per_page: categories.per_page,
        total: categories.total,
        from: categories.from ?? 1,
        to: categories.to ?? categories.data.length,
    };

    const filterSlot = (
        <>
            <Select value={currentParams.parent_id ?? 'all'} onValueChange={(v) => makeFilterChange('parent_id', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[150px] border-dashed">
                    <SelectValue placeholder="Parent" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Parents</SelectItem>
                    <SelectItem value="root">Root only</SelectItem>
                    {parentOptions.map((option) => (
                        <SelectItem key={option.id} value={option.id.toString()}>
                            {option.code} — {option.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select value={currentParams.status ?? 'all'} onValueChange={(v) => makeFilterChange('status', v)}>
                <SelectTrigger className="h-8 text-xs min-w-[110px] border-dashed">
                    <SelectValue placeholder="Status" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Statuses</SelectItem>
                    {statuses.map((status) => (
                        <SelectItem key={status} value={status}>
                            {status}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </>
    );

    return (
        <>
            <Head title="Product Categories" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Product Categories</h1>
                        <p className="text-sm text-muted-foreground">
                            Organization-wide hierarchy for grouping products before catalog setup.
                        </p>
                    </div>
                </div>

                <DataTable
                    tableId="product-categories"
                    columns={columns}
                    data={categories.data}
                    meta={meta}
                    baseUrl="/product-categories"
                    currentParams={currentParams}
                    searchPlaceholder="Search by code or name…"
                    entityLabel="categories"
                    emptyStateIcon={FolderTree}
                    emptyStateTitle="No product categories found"
                    emptyStateDescription={
                        can('product-category.create')
                            ? 'Create your first category for this organization.'
                            : 'No product categories are available for this organization.'
                    }
                    emptyStateAction={
                        can('product-category.create') ? (
                            <Button size="sm" onClick={openCreate} className="gap-2">
                                <Plus className="size-3.5" />
                                Create Category
                            </Button>
                        ) : undefined
                    }
                    primaryAction={
                        can('product-category.create') ? (
                            <Button onClick={openCreate} className="gap-2 h-9 font-semibold text-xs">
                                <Plus className="size-4" />
                                Add Category
                            </Button>
                        ) : undefined
                    }
                    filterSlot={filterSlot}
                    rowActions={(row) => {
                        const actions: RowAction<ProductCategory>[] = [];

                        if (can('product-category.update')) {
                            actions.push({
                                label: 'Edit',
                                icon: Edit2,
                                onClick: openEdit,
                            });
                        }

                        if (
                            can('product-category.delete')
                            && (row.products_count ?? 0) === 0
                            && (row.children_count ?? 0) === 0
                        ) {
                            actions.push({
                                label: 'Archive',
                                icon: Trash2,
                                onClick: (r) => setDeleteConfirmCategory(r),
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
                        setEditingCategory(null);
                        form.reset();
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-bold">
                            {editingCategory ? 'Edit Product Category' : 'Add Product Category'}
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            Categories are shared across all plants. Nest under a parent when needed.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="space-y-2 sm:col-span-2">
                                <Label>Parent Category</Label>
                                <Select
                                    value={form.data.parent_id || 'none'}
                                    onValueChange={(val) => form.setData('parent_id', val === 'none' ? '' : val)}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="None (root category)" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">None (root category)</SelectItem>
                                        {selectableParents.map((option) => (
                                            <SelectItem key={option.id} value={option.id.toString()}>
                                                {option.code} — {option.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.parent_id} />
                            </div>



                            <div className="space-y-2">
                                <Label htmlFor="sort_order">Sort Order</Label>
                                <Input
                                    id="sort_order"
                                    type="number"
                                    min={0}
                                    value={form.data.sort_order}
                                    onChange={(e) => form.setData('sort_order', e.target.value)}
                                />
                                <InputError message={form.errors.sort_order} />
                            </div>

                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="name">
                                    Category Name <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    placeholder="Raw Materials"
                                    required
                                    autoFocus
                                />
                                <InputError message={form.errors.name} />
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
                                    rows={3}
                                    placeholder="Optional notes about this category"
                                    className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <InputError message={form.errors.description} />
                            </div>
                        </div>

                        <ModalButtons onCancel={() => setIsDialogOpen(false)} processing={form.processing} />
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDeleteDialog
                open={deleteConfirmCategory !== null}
                onOpenChange={(open) => !open && setDeleteConfirmCategory(null)}
                title="Archive Product Category?"
                description={
                    <>
                        Archive <span className="font-semibold text-foreground">{deleteConfirmCategory?.name}</span>? Categories with products or child categories cannot be deleted.
                    </>
                }
                confirmLabel="Archive Category"
                onConfirm={() => {
                    if (!deleteConfirmCategory) {
                        return;
                    }
                    form.delete(`/product-categories/${deleteConfirmCategory.id}`, {
                        onSuccess: () => setDeleteConfirmCategory(null),
                    });
                }}
                processing={form.processing}
            />
        </>
    );
}

ProductCategoriesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Product Categories', href: '/product-categories' },
    ],
};
