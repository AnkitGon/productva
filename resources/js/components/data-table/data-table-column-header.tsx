import React from 'react';
import { ArrowUp, ArrowDown, ChevronsUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface DataTableColumnHeaderProps {
    label: string;
    sortKey: string;
    currentSortBy?: string;
    currentSortDir?: 'asc' | 'desc';
    onSort: (key: string, dir: 'asc' | 'desc') => void;
    className?: string;
}

export function DataTableColumnHeader({
    label,
    sortKey,
    currentSortBy,
    currentSortDir,
    onSort,
    className,
}: DataTableColumnHeaderProps) {
    const isActive = currentSortBy === sortKey;
    const isAsc = isActive && currentSortDir === 'asc';
    const isDesc = isActive && currentSortDir === 'desc';

    const handleClick = () => {
        if (!isActive) {
            onSort(sortKey, 'asc');
        } else if (isAsc) {
            onSort(sortKey, 'desc');
        } else {
            // desc -> clear (sort by default)
            onSort('', 'asc');
        }
    };

    return (
        <Button
            variant="ghost"
            size="sm"
            onClick={handleClick}
            className={cn(
                'h-auto p-0 font-semibold text-xs text-muted-foreground uppercase tracking-wider hover:text-foreground hover:bg-transparent gap-1',
                isActive && 'text-foreground',
                className,
            )}
        >
            {label}
            {isAsc ? (
                <ArrowUp className="size-3.5 shrink-0" />
            ) : isDesc ? (
                <ArrowDown className="size-3.5 shrink-0" />
            ) : (
                <ChevronsUpDown className="size-3.5 shrink-0 opacity-40" />
            )}
        </Button>
    );
}
