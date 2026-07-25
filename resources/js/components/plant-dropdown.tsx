import { router, usePage, useForm } from '@inertiajs/react';
import { Factory, Plus, Loader2, Pencil, Trash2, ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/input-error';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { UserSearchSelect } from '@/components/user-search-select';
import { activate, store } from '@/routes/plants';
import { useCan } from '@/hooks/use-can';
import type { SharedData } from '@/types';
import { cn } from '@/lib/utils';
import { CountrySelect, StateSelect } from '@/components/location-selector';
import { Country } from 'country-state-city';

const generateCode = (name: string): string => {
    const clean = name.trim().toUpperCase().replace(/[^A-Z0-9\s]/g, '');
    const words = clean.split(/\s+/).filter(Boolean);
    if (words.length >= 3) {
        return (words[0][0] + words[1][0] + words[2][0]).slice(0, 5);
    } else if (words.length === 2) {
        return (words[0][0] + words[1].slice(0, 2)).slice(0, 5);
    } else if (words.length === 1) {
        return words[0].slice(0, 3);
    }
    return '';
};

export function PlantDropdown() {
    const { auth } = usePage<SharedData>().props;
    const { can } = useCan();
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [isSlugEdited, setIsSlugEdited] = useState(false);
    const [showSlugInput, setShowSlugInput] = useState(false);
    const [isCodeEdited, setIsCodeEdited] = useState(false);
    const [selectedCountryCode, setSelectedCountryCode] = useState('');
    const [editingPlant, setEditingPlant] = useState<any>(null);
    const [deleteConfirmPlantId, setDeleteConfirmPlantId] = useState<number | null>(null);
    const [managerLabel, setManagerLabel] = useState('');
    const [showInactive, setShowInactive] = useState(false);

    if (!auth?.user || !auth.user.plants || auth.user.plants.length === 0) {
        return null;
    }

    const activePlantId = auth.user.active_plant_id ? String(auth.user.active_plant_id) : undefined;
    const plants = auth.user.plants.filter((p) => p.status === 'Active' || String(p.id) === activePlantId || showInactive);
    const currentDefaultPlant = plants.find((p) => p.is_default);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        code: '',
        slug: '',
        description: '',
        address_line_1: '',
        address_line_2: '',
        city: '',
        state: '',
        postal_code: '',
        country: '',
        phone: '',
        email: '',
        manager_id: '',
        status: 'Active',
        is_default: false,
    });

    const handleCountryChange = (countryName: string, countryCode: string) => {
        setSelectedCountryCode(countryCode);
        setData((prev) => ({
            ...prev,
            country: countryName,
            state: '',
        }));
    };

    const isCodeAvailable = !data.code || !plants.some((p) => p.code.trim().toUpperCase() === data.code.trim().toUpperCase() && p.id !== editingPlant?.id);

    const handleNameChange = (val: string) => {
        setData((prev) => {
            const updated = { ...prev, name: val };
            if (!isSlugEdited) {
                updated.slug = val
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/(^-|-$)/g, '');
            }
            if (!isCodeEdited) {
                updated.code = generateCode(val);
            }
            return updated;
        });
    };

    const handleSlugChange = (val: string) => {
        setIsSlugEdited(true);
        setData('slug', val);
    };

    const handleValueChange = (value: string) => {
        router.post(activate.url(Number(value)), {}, {
            onSuccess: () => {
                window.location.reload();
            }
        });
    };

    const handleEdit = (plant: any) => {
        setEditingPlant(plant);
        setData({
            name: plant.name,
            code: plant.code,
            slug: plant.slug,
            description: plant.description || '',
            address_line_1: plant.address_line_1 || '',
            address_line_2: plant.address_line_2 || '',
            city: plant.city || '',
            state: plant.state || '',
            postal_code: plant.postal_code || '',
            country: plant.country || '',
            phone: plant.phone || '',
            email: plant.email || '',
            manager_id: plant.manager_id ? String(plant.manager_id) : '',
            status: plant.status,
            is_default: !!plant.is_default,
        });
        setManagerLabel(
            plant.manager
                ? plant.manager.user
                    ? `${plant.manager.user.name} (${plant.manager.user.email})`
                    : `${plant.manager.first_name} ${plant.manager.last_name}`.trim()
                : '',
        );

        const foundCountry = Country.getAllCountries().find((c) => c.name === plant.country);
        setSelectedCountryCode(foundCountry ? foundCountry.isoCode : '');

        setIsSlugEdited(true);
        setIsCodeEdited(true);
        setIsDialogOpen(true);
    };

    const handleDelete = (id: number) => {
        setDeleteConfirmPlantId(id);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const config = {
            onSuccess: () => {
                setIsDialogOpen(false);
                setIsSlugEdited(false);
                setShowSlugInput(false);
                setIsCodeEdited(false);
                setSelectedCountryCode('');
                setEditingPlant(null);
                setManagerLabel('');
                reset();
            },
        };

        if (editingPlant) {
            put(`/plants/${editingPlant.id}`, config);
        } else {
            post(store.url(), config);
        }
    };

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline" className="w-[200px] h-9 text-sm justify-between px-3" size="sm">
                        <span className="flex items-center truncate">
                            <Factory className="mr-2 h-4 w-4 shrink-0 text-muted-foreground" />
                            <span className="truncate">{auth.user.plants.find(p => String(p.id) === activePlantId)?.name || "Select plant..."}</span>
                        </span>
                        <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent className="w-[240px]">
                    {plants.map((plant) => (
                        <div key={plant.id} className="flex items-center justify-between p-1 hover:bg-accent rounded-sm text-sm group">
                            <button
                                type="button"
                                disabled={plant.status !== 'Active'}
                                onClick={() => handleValueChange(String(plant.id))}
                                className={cn(
                                    "flex-1 text-left px-2 py-1 truncate font-medium",
                                    plant.status === 'Active' ? "cursor-pointer" : "cursor-not-allowed text-muted-foreground opacity-60",
                                    String(plant.id) === activePlantId && "text-primary font-semibold"
                                )}
                            >
                                <span className={cn(plant.status !== 'Active' && "line-through opacity-70")}>
                                    {plant.name}
                                </span>
                                {plant.status !== 'Active' && (
                                    <span className="text-[10px] text-muted-foreground/80 ml-1 font-normal italic">(Inactive)</span>
                                )}
                            </button>
                            <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity pr-1">
                                {can('plants.update') && (
                                    <button
                                        type="button"
                                        onClick={() => handleEdit(plant)}
                                        className="p-1 hover:bg-muted rounded text-muted-foreground hover:text-foreground cursor-pointer"
                                        title="Edit plant"
                                    >
                                        <Pencil className="size-3" />
                                    </button>
                                )}
                                {can('plants.delete') && auth.user.plants.length > 1 && (
                                    <button
                                        type="button"
                                        onClick={() => handleDelete(plant.id)}
                                        className="p-1 hover:bg-destructive/10 rounded text-muted-foreground hover:text-destructive cursor-pointer"
                                        title="Delete plant"
                                    >
                                        <Trash2 className="size-3" />
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                    {can('plants.create') && (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem 
                                onClick={() => {
                                    setEditingPlant(null);
                                    setManagerLabel('');
                                    reset();
                                    setIsDialogOpen(true);
                                }}
                                className="text-primary focus:text-primary font-medium cursor-pointer"
                            >
                                <Plus className="mr-2 size-4" />
                                Create new plant
                            </DropdownMenuItem>
                        </>
                    )}
                    {auth.user.plants.some((p) => p.status !== 'Active') && (
                        <>
                            <DropdownMenuSeparator />
                            <div 
                                className="flex items-center gap-2 px-3 py-2 text-xs text-muted-foreground hover:bg-accent/40 rounded-sm cursor-pointer select-none"
                                onClick={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    setShowInactive(!showInactive);
                                }}
                            >
                                <Checkbox
                                    id="show-inactive-plants"
                                    checked={showInactive}
                                    onCheckedChange={(checked) => setShowInactive(checked === true)}
                                    onClick={(e) => e.stopPropagation()}
                                />
                                <label 
                                    htmlFor="show-inactive-plants" 
                                    className="cursor-pointer font-medium"
                                    onClick={(e) => e.stopPropagation()}
                                >
                                    Show inactive plants
                                </label>
                            </div>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog open={isDialogOpen} onOpenChange={(open) => {
                setIsDialogOpen(open);
                if (!open) {
                    setIsSlugEdited(false);
                    setShowSlugInput(false);
                    setIsCodeEdited(false);
                    setSelectedCountryCode('');
                    setEditingPlant(null);
                    setManagerLabel('');
                    reset();
                }
            }}>
                <DialogContent className="sm:max-w-3xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{editingPlant ? "Edit Plant" : "Create New Plant"}</DialogTitle>
                        <DialogDescription>
                            {editingPlant ? "Modify the details of your plant." : "Create a new plant for your organization."}
                        </DialogDescription>
                    </DialogHeader>

                    <form 
                        onSubmit={handleSubmit} 
                        className="space-y-8 py-2"
                        onKeyDown={(e) => {
                            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                                e.preventDefault();
                                handleSubmit(e);
                            }
                        }}
                    >
                        {/* General Section */}
                        <div className="space-y-4">
                            <h4 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider">General</h4>
                            <div className="border-t border-border/40 my-2" />
                            
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="name">Plant Name <span className="text-destructive">*</span></Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => handleNameChange(e.target.value)}
                                        placeholder="e.g. Surat Plant"
                                        required
                                        autoFocus
                                    />
                                    <div className="min-h-[20px] mt-1 text-xs">
                                        {errors.name ? (
                                            <InputError message={errors.name} />
                                        ) : data.name && !showSlugInput ? (
                                            <div className="text-muted-foreground flex items-center gap-1.5">
                                                <span>URL slug: <strong className="font-semibold text-foreground">{data.slug}</strong></span>
                                                <button
                                                    type="button"
                                                    onClick={() => setShowSlugInput(true)}
                                                    className="text-primary hover:text-primary/80 inline-flex items-center gap-0.5 cursor-pointer font-medium"
                                                    title="Edit slug"
                                                >
                                                    <Pencil className="size-3" />
                                                    <span>Edit</span>
                                                </button>
                                            </div>
                                        ) : null}
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="code">Plant Code <span className="text-destructive">*</span></Label>
                                    <Input
                                        id="code"
                                        value={data.code}
                                        onChange={(e) => {
                                            setIsCodeEdited(true);
                                            setData('code', e.target.value);
                                        }}
                                        placeholder="e.g. SUR"
                                        required
                                    />
                                    <div className="min-h-[20px] mt-1 text-xs">
                                        {errors.code ? (
                                            <InputError message={errors.code} />
                                        ) : data.code ? (
                                            isCodeAvailable ? (
                                                <span className="text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                                                    {data.code} <span className="text-[10px]">✔</span> Available
                                                </span>
                                            ) : (
                                                <span className="text-destructive font-semibold flex items-center gap-1">
                                                    {data.code} <span className="text-[10px]">✖</span> Already exists
                                                </span>
                                            )
                                        ) : (
                                            <p className="text-muted-foreground/90 font-medium leading-normal">
                                                Used in reports. Example: SUR, AMD, PUN.
                                            </p>
                                        )}
                                    </div>
                                </div>

                                {showSlugInput && (
                                    <div className="space-y-2 md:col-span-2">
                                        <Label htmlFor="slug">Slug <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                        <Input
                                            id="slug"
                                            value={data.slug}
                                            onChange={(e) => handleSlugChange(e.target.value)}
                                            placeholder="e.g. surat-plant"
                                        />
                                        <div className="min-h-[20px] mt-1">
                                            <InputError message={errors.slug} />
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Description <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                <textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value.slice(0, 500))}
                                    placeholder="Describe this plant operations..."
                                    rows={4}
                                    maxLength={500}
                                    className="flex min-h-[100px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <div className="flex justify-between items-start min-h-[20px] mt-1 text-xs">
                                    <InputError message={errors.description} />
                                    <span className="text-muted-foreground ml-auto">
                                        {data.description.length} / 500
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Contact Section */}
                        <div className="space-y-4 pt-4">
                            <h4 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider">Contact</h4>
                            <div className="border-t border-border/40 my-2" />
                            
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label>Manager <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <UserSearchSelect
                                        value={data.manager_id}
                                        selectedLabel={managerLabel}
                                        placeholder="Search employees…"
                                        searchPlaceholder="Type to search employees…"
                                        withEmployee
                                        onChange={(val, option) => {
                                            setData('manager_id', val);
                                            setManagerLabel(option?.label ?? '');
                                        }}
                                    />
                                    <div className="min-h-[20px] mt-1">
                                        <InputError message={errors.manager_id} />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="phone">Phone Number <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Input
                                        id="phone"
                                        type="tel"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        placeholder="e.g. +1 (555) 019-2834"
                                    />
                                    <div className="min-h-[20px] mt-1">
                                        <InputError message={errors.phone} />
                                    </div>
                                </div>

                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="email">Email Address <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder="e.g. dallas@company.com"
                                    />
                                    <div className="min-h-[20px] mt-1">
                                        <InputError message={errors.email} />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Address Section */}
                        <div className="space-y-4 pt-4">
                            <h4 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider">Address</h4>
                            <div className="border-t border-border/40 my-2" />
                            
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="address_line_1">Address Line 1 <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Input
                                        id="address_line_1"
                                        value={data.address_line_1}
                                        onChange={(e) => setData('address_line_1', e.target.value)}
                                        placeholder="Street Address"
                                    />
                                    <div className="min-h-[20px] mt-1">
                                        <InputError message={errors.address_line_1} />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="address_line_2">Address Line 2 <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Input
                                        id="address_line_2"
                                        value={data.address_line_2}
                                        onChange={(e) => setData('address_line_2', e.target.value)}
                                        placeholder="Suite, Building, Unit"
                                    />
                                    <div className="min-h-[20px] mt-1">
                                        <InputError message={errors.address_line_2} />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="country">Country <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <CountrySelect
                                        value={data.country}
                                        onChange={handleCountryChange}
                                    />
                                    <div className="min-h-[20px] mt-1">
                                        <InputError message={errors.country} />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="state">State / Province <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <StateSelect
                                        countryCode={selectedCountryCode}
                                        value={data.state}
                                        onChange={(stateName) => setData('state', stateName)}
                                    />
                                    <div className="min-h-[20px] mt-1">
                                        <InputError message={errors.state} />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="city">City <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Input
                                        id="city"
                                        value={data.city}
                                        onChange={(e) => setData('city', e.target.value)}
                                        placeholder="City"
                                    />
                                    <div className="min-h-[20px] mt-1">
                                        <InputError message={errors.city} />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="postal_code">Postal / Zip Code <span className="text-[10px] text-muted-foreground/80 font-normal ml-1">(Optional)</span></Label>
                                    <Input
                                        id="postal_code"
                                        value={data.postal_code}
                                        onChange={(e) => setData('postal_code', e.target.value)}
                                        placeholder="Postal Code"
                                    />
                                    <div className="min-h-[20px] mt-1">
                                        <InputError message={errors.postal_code} />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Settings Section */}
                        <div className="space-y-4 pt-4">
                            <h4 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider">Settings</h4>
                            <div className="border-t border-border/40 my-2" />
                            
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                                <div className="space-y-2">
                                    <Label htmlFor="status" className="block text-sm font-medium">Status</Label>
                                    <div className="flex items-center gap-6">
                                        <button
                                            type="button"
                                            onClick={() => setData('status', data.status === 'Active' ? 'Inactive' : 'Active')}
                                            className={cn(
                                                "relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2",
                                                data.status === 'Active' ? "bg-primary" : "bg-muted"
                                            )}
                                        >
                                            <span
                                                className={cn(
                                                    "pointer-events-none inline-block h-5 w-5 transform rounded-full bg-background shadow-lg ring-0 transition duration-200 ease-in-out",
                                                    data.status === 'Active' ? "translate-x-5" : "translate-x-0"
                                                )}
                                            />
                                        </button>
                                        <span className="text-sm font-medium text-muted-foreground">
                                            {data.status === 'Active' ? 'Active' : 'Inactive'}
                                        </span>
                                    </div>
                                    <div className="min-h-[20px] mt-1">
                                        <InputError message={errors.status} />
                                    </div>
                                </div>

                                <div className="flex items-start space-x-2 pt-2 md:pt-4">
                                    <Checkbox
                                        id="is_default"
                                        checked={data.is_default}
                                        onCheckedChange={(checked) => setData('is_default', checked === true)}
                                        className="mt-0.5"
                                    />
                                    <div className="grid gap-1.5 leading-none">
                                        <label
                                            htmlFor="is_default"
                                            className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                                        >
                                            {currentDefaultPlant ? "Set this as the default plant?" : "Set as default plant"}
                                        </label>
                                        <p className="text-xs text-muted-foreground max-w-xs leading-normal">
                                            {data.is_default && currentDefaultPlant
                                                ? `This will replace "${currentDefaultPlant.name}" as the organization's default.`
                                                : "Only one default plant is allowed per organization."}
                                        </p>
                                    </div>
                                    <div className="min-h-[20px] mt-1">
                                        <InputError message={errors.is_default} />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <DialogFooter className="border-t border-border/40 pt-4 flex items-center justify-end gap-3">
                            <Button 
                                type="button" 
                                variant="outline" 
                                onClick={() => setIsDialogOpen(false)}
                                disabled={processing}
                                className="h-10 px-6 border-border/60 hover:bg-muted"
                            >
                                Cancel
                            </Button>
                            <Button 
                                type="submit" 
                                disabled={processing}
                                className="h-10 px-8 min-w-[140px]"
                            >
                                {processing ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        {editingPlant ? "Saving..." : "Creating..."}
                                    </>
                                ) : (
                                    editingPlant ? "Save Changes" : "Create Plant"
                                )}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDeleteDialog
                open={deleteConfirmPlantId !== null}
                onOpenChange={(open) => !open && setDeleteConfirmPlantId(null)}
                title="Delete Plant?"
                description={
                    <>
                        Are you sure you want to delete{' '}
                        <span className="font-semibold text-foreground">
                            {plants.find((p) => p.id === deleteConfirmPlantId)?.name ?? 'this plant'}
                        </span>
                        ? This action cannot be undone.
                    </>
                }
                confirmLabel="Delete Plant"
                onConfirm={() => {
                    if (!deleteConfirmPlantId) return;
                    router.delete(`/plants/${deleteConfirmPlantId}`, {
                        onSuccess: () => setDeleteConfirmPlantId(null),
                    });
                }}
            />
        </>
    );
}
