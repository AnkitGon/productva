import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    Boxes,
    Contact,
    Clock3,
    Factory,
    Layers,
    LayoutGrid,
    Package,
    Shield,
    Sparkles,
    Users,
    Warehouse,
} from 'lucide-react';
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
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavGroup, SharedData } from '@/types';

export function AppSidebar() {
    const { can, isSuperAdmin } = useCan();
    const { showGettingStarted } = usePage<SharedData>().props;
    const { isCurrentUrl } = useCurrentUrl();
    const dashboardHref = isSuperAdmin ? '/admin/dashboard' : '/dashboard';

    const navGroups: NavGroup[] = [
        {
            items: [
                ...(can(isSuperAdmin ? 'super-admin-dashboard' : 'admin-dashboard')
                    ? [
                          {
                              title: 'Dashboard',
                              href: dashboardHref,
                              icon: LayoutGrid,
                          },
                      ]
                    : []),
            ],
        },
        {
            title: 'Administration',
            items: [
                ...(!isSuperAdmin && can('employees.view')
                    ? [{ title: 'Employees', href: '/employees', icon: Contact }]
                    : []),
                ...(!isSuperAdmin && can('departments.view')
                    ? [{ title: 'Departments', href: '/departments', icon: Building2 }]
                    : []),
                ...(!isSuperAdmin && can('shift.view')
                    ? [{ title: 'Shifts', href: '/shifts', icon: Clock3 }]
                    : []),
                ...(can('super-admin-dashboard')
                    ? [{ title: 'Users', href: '/admin/users', icon: Users }]
                    : []),
                ...(!isSuperAdmin && can('roles.view')
                    ? [{ title: 'Roles & Permissions', href: '/admin/roles', icon: Shield }]
                    : []),
            ],
        },
        {
            title: 'Products',
            collapsible: true,
            icon: Package,
            items: [
                ...(!isSuperAdmin && can('products.view')
                    ? [{ title: 'Products', href: '/products' }]
                    : []),
                ...(!isSuperAdmin && can('product-category.view')
                    ? [{ title: 'Categories', href: '/product-categories' }]
                    : []),
                ...(!isSuperAdmin && can('uom.view')
                    ? [{ title: 'Units', href: '/units-of-measure' }]
                    : []),
            ],
        },
        {
            title: 'Manufacturing',
            collapsible: true,
            icon: Layers,
            items: [
                ...(!isSuperAdmin && can('boms.view')
                    ? [{ title: 'BOM', href: '/boms' }]
                    : []),
                ...(!isSuperAdmin && can('operations.view')
                    ? [{ title: 'Operations', href: '/operations' }]
                    : []),
                ...(!isSuperAdmin && can('routing.view')
                    ? [{ title: 'Routing', href: '/routings' }]
                    : []),
            ],
        },
        {
            title: 'Resources',
            collapsible: true,
            icon: Factory,
            items: [
                ...(!isSuperAdmin && can('work-centers.view')
                    ? [{ title: 'Work Centers', href: '/work-centers' }]
                    : []),
                ...(!isSuperAdmin && can('machine.view')
                    ? [{ title: 'Machines', href: '/machines' }]
                    : []),
            ],
        },
        {
            title: 'Warehouse',
            collapsible: true,
            icon: Warehouse,
            items: [
                ...(!isSuperAdmin && can('warehouses.view')
                    ? [
                          { title: 'Warehouses', href: '/warehouses' },
                          { title: 'Locations', href: '/warehouse-locations' },
                          { title: 'Types', href: '/warehouse-types' },
                      ]
                    : []),
            ],
        },
        {
            title: 'Inventory',
            collapsible: true,
            icon: Boxes,
            items: [
                ...(!isSuperAdmin && can('inventory.view')
                    ? [
                          { title: 'Inventory', href: '/inventory' },
                          { title: 'Transactions', href: '/inventory-transactions' },
                          ]
                    : []),
            ],
        },
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
                <NavMain groups={navGroups} />
            </SidebarContent>

            <SidebarFooter>
                {!isSuperAdmin && showGettingStarted && (
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentUrl('/setup')}
                                tooltip={{ children: 'Getting Started' }}
                            >
                                <Link href="/setup" prefetch>
                                    <Sparkles />
                                    <span>Getting Started</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                )}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
