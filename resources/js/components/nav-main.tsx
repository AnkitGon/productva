import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import type { NavGroup } from '@/types';

function groupHasActiveItem(group: NavGroup, isCurrentUrl: (url: string) => boolean): boolean {
    return group.items.some((item) => isCurrentUrl(item.href));
}

export function NavMain({ groups = [] }: { groups: NavGroup[] }) {
    const { isCurrentUrl } = useCurrentUrl();
    const visibleGroups = groups.filter((group) => group.items.length > 0);

    return (
        <>
            {visibleGroups.map((group, index) => {
                const isActiveGroup = groupHasActiveItem(group, isCurrentUrl);

                if (group.collapsible && group.title) {
                    return (
                        <SidebarGroup
                            key={group.title}
                            className={cn('px-2 py-0', index === 0 ? 'pt-1' : 'pt-0')}
                        >
                            {index > 0 ? (
                                <div className="mx-2.5 mb-1 mt-1 h-px bg-sidebar-border/70" />
                            ) : null}
                            <SidebarMenu className="gap-0">
                                <Collapsible
                                    asChild
                                    defaultOpen={isActiveGroup}
                                    className="group/collapsible"
                                >
                                    <SidebarMenuItem>
                                        <CollapsibleTrigger asChild>
                                            <SidebarMenuButton
                                                tooltip={{ children: group.title }}
                                                className="font-medium"
                                            >
                                                {group.icon ? <group.icon /> : null}
                                                <span>{group.title}</span>
                                                <ChevronRight className="ml-auto size-4 text-sidebar-foreground/45 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                            </SidebarMenuButton>
                                        </CollapsibleTrigger>
                                        <CollapsibleContent>
                                            <SidebarMenuSub className="mx-2 mr-0 border-sidebar-border/80">
                                                {group.items.map((item) => (
                                                    <SidebarMenuSubItem key={item.title}>
                                                        <SidebarMenuSubButton
                                                            asChild
                                                            isActive={isCurrentUrl(item.href)}
                                                        >
                                                            <Link href={item.href} prefetch>
                                                                <span>{item.title}</span>
                                                            </Link>
                                                        </SidebarMenuSubButton>
                                                    </SidebarMenuSubItem>
                                                ))}
                                            </SidebarMenuSub>
                                        </CollapsibleContent>
                                    </SidebarMenuItem>
                                </Collapsible>
                            </SidebarMenu>
                        </SidebarGroup>
                    );
                }

                return (
                    <SidebarGroup
                        key={group.title ?? `group-${index}`}
                        className={cn('px-2 py-0', index === 0 ? 'pt-1' : 'pt-0')}
                    >
                        {group.title ? (
                            <SidebarGroupLabel
                                className={cn(
                                    'mb-1.5 h-auto px-2.5 py-0 text-[11px] font-medium tracking-wide text-sidebar-foreground/55',
                                    index > 0 ? 'mt-3' : 'mt-1.5',
                                )}
                            >
                                {group.title}
                            </SidebarGroupLabel>
                        ) : null}
                        <SidebarMenu className="gap-0">
                            {group.items.map((item) => (
                                <SidebarMenuItem key={`${group.title ?? 'main'}-${item.title}`}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isCurrentUrl(item.href)}
                                        tooltip={{ children: item.title }}
                                    >
                                        <Link href={item.href} prefetch>
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                );
            })}
        </>
    );
}
