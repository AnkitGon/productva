import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { Plus, Edit, Trash2 } from 'lucide-react';
import type { User } from '@/types/auth';
import { useCan } from '@/hooks/use-can';

interface PaginatedUsers {
    data: User[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface Props {
    users: PaginatedUsers;
}

export default function UserIndex({ users }: Props) {
    const { can } = useCan();
    const canInviteUser = can('users.invite');
    const canUpdateUser = can('users.update');
    const canDeleteUser = can('users.delete');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<User | null>(null);
    const [deleteUser, setDeleteUser] = useState<User | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const createForm = useForm({
        name: '',
        email: '',
        password: '',
    });

    const editForm = useForm({
        name: '',
        email: '',
        password: '',
    });

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/admin/users', {
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
            },
        });
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingUser) {
            return;
        }
        editForm.put(`/admin/users/${editingUser.id}`, {
            onSuccess: () => {
                setEditingUser(null);
                editForm.reset();
            },
        });
    };

    const openEditModal = (user: User) => {
        setEditingUser(user);
        editForm.setData({
            name: user.name,
            email: user.email,
            password: '',
        });
    };

    return (
        <>
            <Head title="Admin Users Management" />

            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold">Admin Users</h1>
                        <p className="text-sm text-gray-500">Manage all users with Admin role.</p>
                    </div>
                    {canInviteUser && (
                        <Button onClick={() => setIsCreateOpen(true)} className="flex items-center gap-2">
                            <Plus className="size-4" />
                        </Button>
                    )}
                </div>

                <div className="overflow-x-auto rounded-lg border border-sidebar-border bg-white dark:bg-sidebar">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-sidebar-border bg-gray-50 dark:bg-sidebar-accent text-xs uppercase text-gray-700 dark:text-gray-300">
                            <tr>
                                <th className="px-6 py-3">ID</th>
                                <th className="px-6 py-3">Name</th>
                                <th className="px-6 py-3">Email</th>
                                <th className="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-sidebar-border">
                            {users.data.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="px-6 py-4 text-center text-gray-500">
                                        No admin users found.
                                    </td>
                                </tr>
                            ) : (
                                users.data.map((user) => (
                                    <tr key={user.id} className="hover:bg-gray-50/50 dark:hover:bg-sidebar-accent/50">
                                        <td className="px-6 py-4 font-mono text-xs">{user.id}</td>
                                        <td className="px-6 py-4 font-medium">{user.name}</td>
                                        <td className="px-6 py-4 text-gray-500">{user.email}</td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                {canUpdateUser && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => openEditModal(user)}
                                                    >
                                                        <Edit className="size-3.5" />
                                                    </Button>
                                                )}
                                                {canDeleteUser && (
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() => setDeleteUser(user)}
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <ConfirmDeleteDialog
                open={deleteUser !== null}
                onOpenChange={(open) => !open && setDeleteUser(null)}
                title="Delete Admin User?"
                description={
                    <>
                        Are you sure you want to delete{' '}
                        <span className="font-semibold text-foreground">{deleteUser?.name}</span>? This action cannot be undone.
                    </>
                }
                confirmLabel="Delete User"
                onConfirm={() => {
                    if (!deleteUser) {
                        return;
                    }
                    setIsDeleting(true);
                    router.delete(`/admin/users/${deleteUser.id}`, {
                        onFinish: () => {
                            setIsDeleting(false);
                            setDeleteUser(null);
                        },
                    });
                }}
                processing={isDeleting}
            />

            {/* Create Dialog */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create Admin User</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleCreateSubmit} className="space-y-4">
                        <div>
                            <Label htmlFor="create-name">Name</Label>
                            <Input
                                id="create-name"
                                value={createForm.data.name}
                                onChange={(e) => createForm.setData('name', e.target.value)}
                                required
                            />
                            {createForm.errors.name && (
                                <p className="text-xs text-red-500 mt-1">{createForm.errors.name}</p>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="create-email">Email</Label>
                            <Input
                                id="create-email"
                                type="email"
                                value={createForm.data.email}
                                onChange={(e) => createForm.setData('email', e.target.value)}
                                required
                            />
                            {createForm.errors.email && (
                                <p className="text-xs text-red-500 mt-1">{createForm.errors.email}</p>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="create-password">Password</Label>
                            <Input
                                id="create-password"
                                type="password"
                                value={createForm.data.password}
                                onChange={(e) => createForm.setData('password', e.target.value)}
                                required
                            />
                            {createForm.errors.password && (
                                <p className="text-xs text-red-500 mt-1">{createForm.errors.password}</p>
                            )}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsCreateOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={createForm.processing}>
                                Create User
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={!!editingUser} onOpenChange={(open) => !open && setEditingUser(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Admin User</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleEditSubmit} className="space-y-4">
                        <div>
                            <Label htmlFor="edit-name">Name</Label>
                            <Input
                                id="edit-name"
                                value={editForm.data.name}
                                onChange={(e) => editForm.setData('name', e.target.value)}
                                required
                            />
                            {editForm.errors.name && (
                                <p className="text-xs text-red-500 mt-1">{editForm.errors.name}</p>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="edit-email">Email</Label>
                            <Input
                                id="edit-email"
                                type="email"
                                value={editForm.data.email}
                                onChange={(e) => editForm.setData('email', e.target.value)}
                                required
                            />
                            {editForm.errors.email && (
                                <p className="text-xs text-red-500 mt-1">{editForm.errors.email}</p>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="edit-password">New Password (leave blank to keep current)</Label>
                            <Input
                                id="edit-password"
                                type="password"
                                value={editForm.data.password}
                                onChange={(e) => editForm.setData('password', e.target.value)}
                            />
                            {editForm.errors.password && (
                                <p className="text-xs text-red-500 mt-1">{editForm.errors.password}</p>
                            )}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEditingUser(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={editForm.processing}>
                                Update User
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

UserIndex.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={[{ title: 'Users', href: '/admin/users' }]}>{page}</AppLayout>
);
