import { Link, usePage } from '@inertiajs/react';
import { BookOpen, FolderGit2, LayoutGrid, Users, Shield, Contact, Building2 } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem, SharedData } from '@/types';

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const isSuperAdmin = auth?.user?.roles?.includes('super-admin');
    const isAdmin = auth?.user?.roles?.includes('admin');

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: isSuperAdmin ? '/admin/dashboard' : '/dashboard',
            icon: LayoutGrid,
        },
        {
            title: 'Employees',
            href: '/employees',
            icon: Contact,
        },
        {
            title: 'Departments',
            href: '/departments',
            icon: Building2,
        },
        ...(isSuperAdmin
            ? [
                  {
                      title: 'Users',
                      href: '/admin/users',
                      icon: Users,
                  },
              ]
            : []),
        ...(isAdmin
            ? [
                  {
                      title: 'Roles & Permissions',
                      href: '/admin/roles',
                      icon: Shield,
                  },
              ]
            : []),
    ];

  

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={isSuperAdmin ? '/admin/dashboard' : '/dashboard'} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>


            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
