import { useState, useMemo } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { Plus, Pencil, Trash2, Search, ChevronDown, ChevronRight, AlertTriangle, ShieldAlert } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useCan } from '@/hooks/use-can';

interface Permission {
    id: number;
    name: string;
    slug: string;
}

interface Role {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    permissions: Permission[];
}

interface Props {
    roles: Role[];
    permissions: Permission[];
}

export default function RolesIndex({ roles, permissions }: Props) {
    const { can } = useCan();
    const canCreateRole = can('roles.create');
    const canUpdateRole = can('roles.update');
    const canDeleteRole = can('roles.delete');
    const [selectedRole, setSelectedRole] = useState<Role | null>(roles[0] || null);
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [deleteConfirmRoleId, setDeleteConfirmRoleId] = useState<number | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [collapsedGroups, setCollapsedGroups] = useState<Record<string, boolean>>({});

    const createForm = useForm({
        name: '',
        description: '',
    });

    const editForm = useForm({
        name: selectedRole?.name || '',
        description: selectedRole?.description || '',
        permissions: selectedRole?.permissions.map(p => p.id) || [] as number[],
    });

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/admin/roles', {
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
            },
        });
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedRole || !canUpdateRole) return;
        editForm.put(`/admin/roles/${selectedRole.id}`, {
            onSuccess: () => {
                // Refresh local states if needed
            },
        });
    };

    const handleRoleSelect = (role: Role) => {
        setSelectedRole(role);
        editForm.setData({
            name: role.name,
            description: role.description || '',
            permissions: role.permissions.map(p => p.id),
        });
        editForm.clearErrors();
    };

    // Filter permissions based on search query
    const filteredPermissions = useMemo(() => {
        if (!searchQuery) return permissions;
        const q = searchQuery.toLowerCase();
        return permissions.filter(p => 
            p.name.toLowerCase().includes(q) || 
            p.slug.toLowerCase().includes(q)
        );
    }, [permissions, searchQuery]);

    // Parse and group permissions dynamically from the database permissions array
    const { permissionGroups, dangerZonePermissions } = useMemo(() => {
        const groups: Record<string, { label: string; permissions: Permission[] }> = {};
        const danger: Permission[] = [];

        const formatLabel = (str: string) => {
            return str
                .split('-')
                .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
        };

        const isDangerous = (slug: string) => {
            return slug.includes('.delete') || slug.startsWith('system.') || slug === 'settings.manage';
        };

        permissions.forEach((p) => {
            if (isDangerous(p.slug)) {
                danger.push(p);
            } else {
                const parts = p.slug.split('.');
                let groupKey = 'general';
                let groupLabel = 'General';

                if (parts.length > 1) {
                    groupKey = parts[0];
                    groupLabel = formatLabel(parts[0]);
                } else if (p.slug.endsWith('-dashboard')) {
                    groupKey = 'general';
                    groupLabel = 'General';
                } else {
                    groupKey = p.slug;
                    groupLabel = formatLabel(p.slug);
                }

                if (!groups[groupKey]) {
                    groups[groupKey] = {
                        label: groupLabel,
                        permissions: [],
                    };
                }
                groups[groupKey].permissions.push(p);
            }
        });

        return { permissionGroups: groups, dangerZonePermissions: danger };
    }, [permissions]);

    // Check if permission group is visible after query filtering
    const isGroupVisible = (groupPerms: Permission[]) => {
        return groupPerms.some(gp => filteredPermissions.some(fp => fp.id === gp.id));
    };

    const toggleGroupCollapse = (groupKey: string) => {
        setCollapsedGroups(prev => ({ ...prev, [groupKey]: !prev[groupKey] }));
    };

    // Group level checkboxes toggle logic
    const handleGroupToggle = (groupPerms: Permission[], checked: boolean) => {
        const groupPermIds = groupPerms.map(p => p.id);
        let current = [...editForm.data.permissions];
        if (checked) {
            groupPermIds.forEach(id => {
                if (!current.includes(id)) current.push(id);
            });
        } else {
            current = current.filter(id => !groupPermIds.includes(id));
        }
        editForm.setData('permissions', current);
    };

    const isGroupFullyChecked = (groupPerms: Permission[]) => {
        if (groupPerms.length === 0) return false;
        return groupPerms.every(p => editForm.data.permissions.includes(p.id));
    };

    const isGroupPartiallyChecked = (groupPerms: Permission[]) => {
        const checkedCount = groupPerms.filter(p => editForm.data.permissions.includes(p.id)).length;
        return checkedCount > 0 && checkedCount < groupPerms.length;
    };

    // Global "Select All" logic
    const handleSelectAllToggle = (checked: boolean) => {
        if (checked) {
            editForm.setData('permissions', permissions.map(p => p.id));
        } else {
            editForm.setData('permissions', []);
        }
    };

    const isAllChecked = useMemo(() => {
        return permissions.length > 0 && editForm.data.permissions.length === permissions.length;
    }, [permissions, editForm.data.permissions]);

    const isAllPartiallyChecked = useMemo(() => {
        return editForm.data.permissions.length > 0 && editForm.data.permissions.length < permissions.length;
    }, [permissions, editForm.data.permissions]);

    // Handle individual permission select
    const handlePermissionToggle = (id: number, checked: boolean) => {
        let current = [...editForm.data.permissions];
        if (checked) {
            if (!current.includes(id)) current.push(id);
        } else {
            current = current.filter(item => item !== id);
        }
        editForm.setData('permissions', current);
    };

    return (
        <>
            <Head title="Roles & Permissions Management" />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 p-6 h-[calc(100vh-80px)] items-stretch">
                {/* Left side list of roles */}
                <div className="lg:col-span-1 border border-border/60 bg-white dark:bg-sidebar rounded-xl shadow-sm flex flex-col min-h-[300px]">
                    <div className="p-4 border-b border-border/60 flex items-center justify-between">
                        <div>
                            <h2 className="text-lg font-semibold">Roles</h2>
                            <p className="text-xs text-muted-foreground">Manage user access groups</p>
                        </div>
                        {canCreateRole && (
                            <Button 
                                onClick={() => setIsCreateOpen(true)}
                                size="sm"
                                className="flex items-center gap-1.5"
                            >
                                <Plus className="size-4" />
                                Create Role
                            </Button>
                        )}
                    </div>
                    <div className="flex-1 overflow-y-auto p-2 space-y-1">
                        {roles.map(role => (
                            <button
                                key={role.id}
                                onClick={() => handleRoleSelect(role)}
                                className={cn(
                                    "w-full text-left px-3 py-2.5 rounded-lg text-sm transition-colors flex items-center justify-between group",
                                    selectedRole?.id === role.id
                                        ? "bg-primary text-primary-foreground font-medium"
                                        : "hover:bg-accent text-foreground"
                                )}
                            >
                                <span className="truncate">{role.name}</span>
                                {canDeleteRole && role.slug !== 'super-admin' && role.slug !== 'admin' && (
                                    <button
                                        type="button"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            setDeleteConfirmRoleId(role.id);
                                        }}
                                        className={cn(
                                            "opacity-0 group-hover:opacity-100 p-1 hover:bg-destructive/20 rounded transition-opacity",
                                            selectedRole?.id === role.id ? "text-primary-foreground" : "text-muted-foreground hover:text-destructive"
                                        )}
                                        title="Delete Role"
                                    >
                                        <Trash2 className="size-3.5" />
                                    </button>
                                )}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Right side role details */}
                <div className="lg:col-span-2 border border-border/60 bg-white dark:bg-sidebar rounded-xl shadow-sm flex flex-col min-h-[400px]">
                    {selectedRole ? (
                        <form onSubmit={handleEditSubmit} className="flex flex-col h-full">
                            <div className="p-4 border-b border-border/60 flex items-center justify-between">
                                <div>
                                    <h2 className="text-lg font-semibold">Role Details</h2>
                                    <p className="text-xs text-muted-foreground">
                                        {canUpdateRole ? 'Edit metadata and permissions' : 'View role metadata and permissions'}
                                    </p>
                                </div>
                                {canUpdateRole && (
                                    <Button 
                                        type="submit" 
                                        disabled={editForm.processing}
                                        size="sm"
                                        className="min-w-[100px]"
                                    >
                                        {editForm.processing ? "Saving..." : "Save Changes"}
                                    </Button>
                                )}
                            </div>

                            <div className="flex-1 overflow-y-auto p-6 space-y-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="role-name">Role Name</Label>
                                        <Input
                                            id="role-name"
                                            value={editForm.data.name}
                                            onChange={(e) => editForm.setData('name', e.target.value)}
                                            disabled={!canUpdateRole || selectedRole.slug === 'super-admin' || selectedRole.slug === 'admin'}
                                            required
                                        />
                                        {editForm.errors.name && (
                                            <p className="text-xs text-red-500">{editForm.errors.name}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="role-desc">Description</Label>
                                        <Input
                                            id="role-desc"
                                            value={editForm.data.description}
                                            onChange={(e) => editForm.setData('description', e.target.value)}
                                            placeholder="Enter brief role description"
                                            disabled={!canUpdateRole || selectedRole.slug === 'super-admin' || selectedRole.slug === 'admin'}
                                        />
                                        {editForm.errors.description && (
                                            <p className="text-xs text-red-500">{editForm.errors.description}</p>
                                        )}
                                    </div>
                                </div>

                                <div className="space-y-4 pt-4 border-t border-border/40">
                                    <div className="flex items-center justify-between gap-4 flex-wrap">
                                        <div>
                                            <h3 className="text-md font-semibold">Permissions</h3>
                                            <p className="text-xs text-muted-foreground">
                                                {canUpdateRole ? 'Assign permissions to this role' : 'Permissions assigned to this role'}
                                            </p>
                                        </div>

                                        <div className="flex items-center gap-4">
                                            <div className="relative w-48 sm:w-60">
                                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground/70" />
                                                <Input
                                                    type="search"
                                                    placeholder="Search permissions..."
                                                    value={searchQuery}
                                                    onChange={(e) => setSearchQuery(e.target.value)}
                                                    className="pl-8 h-9"
                                                />
                                            </div>
                                            
                                            {canUpdateRole && (
                                                <div className="flex items-center gap-2 border border-border/60 px-3 py-1.5 rounded-lg bg-muted/40">
                                                    <Checkbox
                                                        id="select-all-global"
                                                        checked={isAllChecked ? true : isAllPartiallyChecked ? 'indeterminate' : false}
                                                        onCheckedChange={(checked) => handleSelectAllToggle(checked === true)}
                                                        disabled={selectedRole.slug === 'super-admin'}
                                                    />
                                                    <Label htmlFor="select-all-global" className="text-xs font-semibold cursor-pointer select-none">
                                                        Select All
                                                    </Label>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    {selectedRole.slug === 'super-admin' && (
                                        <div className="bg-primary/10 border border-primary/20 text-primary p-3 rounded-lg flex items-start gap-2.5 text-xs">
                                            <AlertTriangle className="size-4 shrink-0 mt-0.5" />
                                            <p>The **Super Admin** role automatically bypasses all permission checks and retains full system privileges.</p>
                                        </div>
                                    )}

                                    {/* Collapsible Groups */}
                                    <div className="space-y-2.5">
                                        {Object.entries(permissionGroups).map(([key, group]) => {
                                            if (!isGroupVisible(group.permissions)) return null;
                                            const isCollapsed = !!collapsedGroups[key];
                                            
                                            return (
                                                <div key={key} className="border border-border/40 rounded-lg overflow-hidden transition-all bg-muted/10">
                                                    <div 
                                                        onClick={() => toggleGroupCollapse(key)}
                                                        className="flex items-center justify-between p-3 bg-muted/40 cursor-pointer select-none border-b border-border/40 hover:bg-muted/60 transition-colors"
                                                    >
                                                        <div className="flex items-center gap-2.5" onClick={(e) => e.stopPropagation()}>
                                                            <Checkbox
                                                                id={`group-${key}`}
                                                                checked={isGroupFullyChecked(group.permissions) ? true : isGroupPartiallyChecked(group.permissions) ? 'indeterminate' : false}
                                                                onCheckedChange={(checked) => handleGroupToggle(group.permissions, checked === true)}
                                                                disabled={!canUpdateRole || selectedRole.slug === 'super-admin'}
                                                            />
                                                            <Label htmlFor={`group-${key}`} className="text-sm font-semibold cursor-pointer select-none">
                                                                {group.label}
                                                            </Label>
                                                        </div>
                                                        <div className="flex items-center text-muted-foreground">
                                                            {isCollapsed ? <ChevronRight className="size-4" /> : <ChevronDown className="size-4" />}
                                                        </div>
                                                    </div>
                                                    
                                                    {!isCollapsed && (
                                                        <div className="p-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 bg-white dark:bg-sidebar">
                                                            {group.permissions.map(p => {
                                                                if (searchQuery && !p.name.toLowerCase().includes(searchQuery.toLowerCase()) && !p.slug.toLowerCase().includes(searchQuery.toLowerCase())) {
                                                                    return null;
                                                                }
                                                                return (
                                                                    <div key={p.id} className="flex items-start gap-2">
                                                                        <Checkbox
                                                                            id={`perm-${p.id}`}
                                                                            checked={editForm.data.permissions.includes(p.id)}
                                                                            onCheckedChange={(checked) => handlePermissionToggle(p.id, checked === true)}
                                                                            disabled={!canUpdateRole || selectedRole.slug === 'super-admin'}
                                                                            className="mt-0.5"
                                                                        />
                                                                        <div className="grid gap-0.5 leading-none">
                                                                            <Label htmlFor={`perm-${p.id}`} className="text-xs font-medium cursor-pointer leading-normal select-none">
                                                                                {p.name}
                                                                            </Label>
                                                                            <span className="text-[10px] text-muted-foreground">{p.slug}</span>
                                                                        </div>
                                                                    </div>
                                                                );
                                                            })}
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>

                                    {/* Danger Zone Permissions (Isolated) */}
                                    <div className="border border-destructive/20 rounded-lg overflow-hidden bg-destructive/[0.02] mt-6">
                                        <div className="p-3 bg-destructive/[0.06] border-b border-destructive/10 flex items-center gap-2 text-destructive">
                                            <ShieldAlert className="size-4 shrink-0" />
                                            <span className="text-sm font-semibold uppercase tracking-wider">Danger Zone Permissions</span>
                                        </div>
                                        <div className="p-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                            {dangerZonePermissions.map(p => {
                                                if (searchQuery && !p.name.toLowerCase().includes(searchQuery.toLowerCase()) && !p.slug.toLowerCase().includes(searchQuery.toLowerCase())) {
                                                    return null;
                                                }
                                                return (
                                                    <div key={p.id} className="flex items-start gap-2">
                                                        <Checkbox
                                                            id={`perm-${p.id}`}
                                                            checked={editForm.data.permissions.includes(p.id)}
                                                            onCheckedChange={(checked) => handlePermissionToggle(p.id, checked === true)}
                                                            disabled={selectedRole.slug === 'super-admin'}
                                                            className="mt-0.5 border-destructive/50 data-[state=checked]:bg-destructive data-[state=checked]:text-destructive-foreground"
                                                        />
                                                        <div className="grid gap-0.5 leading-none">
                                                            <Label htmlFor={`perm-${p.id}`} className="text-xs font-semibold text-destructive cursor-pointer leading-normal select-none">
                                                                {p.name}
                                                            </Label>
                                                            <span className="text-[10px] text-muted-foreground/80">{p.slug}</span>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    ) : (
                        <div className="flex-1 flex flex-col items-center justify-center text-muted-foreground p-6">
                            <ShieldAlert className="size-10 mb-2 opacity-50" />
                            <p className="text-sm font-medium">No roles available.</p>
                            <p className="text-xs">Create your first role to manage user permissions.</p>
                        </div>
                    )}
                </div>
            </div>

            {/* Create Dialog */}
            <Dialog open={isCreateOpen} onOpenChange={(open) => !open && setIsCreateOpen(false)}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Create New Role</DialogTitle>
                        <DialogDescription>
                            Create a custom role access group.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleCreateSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="create-name">Role Name</Label>
                            <Input
                                id="create-name"
                                value={createForm.data.name}
                                onChange={(e) => createForm.setData('name', e.target.value)}
                                placeholder="Production Manager"
                                required
                            />
                            {createForm.errors.name && (
                                <p className="text-xs text-red-500 mt-1">{createForm.errors.name}</p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="create-desc">Description</Label>
                            <Input
                                id="create-desc"
                                value={createForm.data.description}
                                onChange={(e) => createForm.setData('description', e.target.value)}
                                placeholder="Manages production line and quality control logs"
                            />
                            {createForm.errors.description && (
                                <p className="text-xs text-red-500 mt-1">{createForm.errors.description}</p>
                            )}
                        </div>
                        <DialogFooter className="pt-4">
                            <Button type="button" variant="outline" onClick={() => setIsCreateOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={createForm.processing}>
                                Create Role
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDeleteDialog
                open={deleteConfirmRoleId !== null}
                onOpenChange={(open) => !open && setDeleteConfirmRoleId(null)}
                title="Delete Role?"
                description="Are you sure you want to delete this role? Any users assigned to this role will lose their associated permissions. This action cannot be undone."
                confirmLabel="Delete Role"
                onConfirm={() => {
                    if (!deleteConfirmRoleId) return;
                    router.delete(`/admin/roles/${deleteConfirmRoleId}`, {
                        onSuccess: () => {
                            const deletedId = deleteConfirmRoleId;
                            setDeleteConfirmRoleId(null);
                            if (selectedRole?.id === deletedId) {
                                const next = roles.find((r) => r.id !== deletedId) || null;
                                setSelectedRole(next);
                                if (next) {
                                    editForm.setData({
                                        name: next.name,
                                        description: next.description || '',
                                        permissions: next.permissions.map((p) => p.id),
                                    });
                                }
                            }
                        },
                    });
                }}
            />
        </>
    );
}

RolesIndex.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={[{ title: 'Roles', href: '/admin/roles' }]}>{page}</AppLayout>
);
