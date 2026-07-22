import React from 'react';
import { MoreHorizontal } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export interface RowAction<T = unknown> {
    label: string;
    icon?: React.ElementType;
    onClick: (row: T) => void;
    variant?: 'default' | 'destructive';
    separator?: boolean; // renders a separator before this item
}

interface DataTableRowActionsProps<T> {
    row: T;
    actions: RowAction<T>[];
}

export function DataTableRowActions<T>({ row, actions }: DataTableRowActionsProps<T>) {
    if (actions.length === 0) return null;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8"
                >
                    <MoreHorizontal className="size-4 text-muted-foreground" />
                    <span className="sr-only">Open actions</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-[160px]">
                {actions.map((action, idx) => (
                    <React.Fragment key={idx}>
                        {action.separator && <DropdownMenuSeparator />}
                        <DropdownMenuItem
                            onClick={() => action.onClick(row)}
                            variant={action.variant === 'destructive' ? 'destructive' : 'default'}
                        >
                            {action.icon && (
                                <action.icon className="size-3.5 mr-2 shrink-0" />
                            )}
                            {action.label}
                        </DropdownMenuItem>
                    </React.Fragment>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
