import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';

export default function AdminDashboard() {
    return (
        <>
            <Head title="Admin Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <h1 className="text-2xl font-bold">Admin Dashboard</h1>
                <p className="text-gray-600 dark:text-gray-400">Welcome to the Admin Dashboard (Requires admin-dashboard permission).</p>
                <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
        </>
    );
}

AdminDashboard.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }]}>{page}</AppLayout>
);


