import { Link } from '@inertiajs/react';
import { Building2, Contact, Clock3, LayoutGrid, Shield, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
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
import { useCan } from '@/hooks/use-can';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { can, isSuperAdmin } = useCan();
    const dashboardHref = isSuperAdmin ? '/admin/dashboard' : '/dashboard';

    const mainNavItems: NavItem[] = [
        ...(can(isSuperAdmin ? 'super-admin-dashboard' : 'admin-dashboard')
            ? [
                  {
                      title: 'Dashboard',
                      href: dashboardHref,
                      icon: LayoutGrid,
                  },
              ]
            : []),
        ...(can('employees.view')
            ? [
                  {
                      title: 'Employees',
                      href: '/employees',
                      icon: Contact,
                  },
              ]
            : []),
        ...(can('departments.view')
            ? [
                  {
                      title: 'Departments',
                      href: '/departments',
                      icon: Building2,
                  },
              ]
            : []),
        ...(can('shift.view')
            ? [
                  {
                      title: 'Shifts',
                      href: '/shifts',
                      icon: Clock3,
                  },
              ]
            : []),
        ...(can('super-admin-dashboard')
            ? [
                  {
                      title: 'Users',
                      href: '/admin/users',
                      icon: Users,
                  },
              ]
            : []),
        ...(can('roles.view')
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
                            <Link href={dashboardHref} prefetch>
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
