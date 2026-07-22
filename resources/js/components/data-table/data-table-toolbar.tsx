import React, { useEffect, useRef, useState, useCallback } from 'react';
import { router } from '@inertiajs/react';
import { Search, X, Columns, SlidersHorizontal } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { ColumnDef } from './data-table';

interface DataTableToolbarProps<T> {
    columns: ColumnDef<T>[];
    visibleColumns: Record<string, boolean>;
    onToggleColumn: (key: string) => void;
    density: 'comfortable' | 'compact' | 'spacious';
    onDensityChange: (d: 'comfortable' | 'compact' | 'spacious') => void;
    baseUrl: string;
    currentParams: Record<string, string>;
    searchPlaceholder?: string;
    primaryAction?: React.ReactNode;
    secondaryActions?: React.ReactNode;
    /** Compact inline filter controls (Select dropdowns without labels). Renders inline next to search. */
    filterSlot?: React.ReactNode;
}

const DEBOUNCE_MS = 400;

export function DataTableToolbar<T>({
    columns,
    visibleColumns,
    onToggleColumn,
    density,
    onDensityChange,
    baseUrl,
    currentParams,
    searchPlaceholder = 'Search…',
    primaryAction,
    secondaryActions,
    filterSlot,
}: DataTableToolbarProps<T>) {
    const [search, setSearch] = useState(currentParams.search ?? '');
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        setSearch(currentParams.search ?? '');
    }, [currentParams.search]);

    const handleSearch = useCallback(
        (value: string) => {
            setSearch(value);
            if (debounceRef.current) clearTimeout(debounceRef.current);
            debounceRef.current = setTimeout(() => {
                const params: Record<string, string> = { ...currentParams };
                if (value) {
                    params.search = value;
                } else {
                    delete params.search;
                }
                delete params.page;
                router.get(baseUrl, params, { preserveState: true, replace: true });
            }, DEBOUNCE_MS);
        },
        [baseUrl, currentParams],
    );

    const handleClearSearch = () => handleSearch('');

    const densityLabels: Record<string, string> = {
        comfortable: 'Comfortable',
        compact: 'Compact',
        spacious: 'Spacious',
    };

    const sortableColumns = columns.filter((c) => c.key !== '_actions');

    return (
        <div className="flex flex-col gap-2 px-4 py-3 border-b border-border/40 sm:flex-row sm:items-start sm:justify-between">
            {/* Search + filters: wrap independently so action buttons stay right */}
            <div className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                <div className="relative w-full shrink-0 sm:w-52">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground pointer-events-none" />
                    <Input
                        value={search}
                        onChange={(e) => handleSearch(e.target.value)}
                        placeholder={searchPlaceholder}
                        className="pl-9 pr-8 h-8 text-xs"
                    />
                    {search && (
                        <button
                            onClick={handleClearSearch}
                            className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                        >
                            <X className="size-3.5" />
                        </button>
                    )}
                </div>

                {filterSlot}
            </div>

            {/* Actions always stay on the right (or end on mobile) */}
            <div className="flex shrink-0 items-center gap-1.5 self-end sm:self-start">
                {secondaryActions}

                {/* Column Visibility */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="sm" className="h-8 px-2.5 gap-1.5 font-medium text-xs text-muted-foreground hover:text-foreground">
                            <Columns className="size-3.5" />
                            <span className="hidden sm:inline">Columns</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-48">
                        <DropdownMenuLabel className="text-xs">Toggle Columns</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {sortableColumns.map((col) => (
                            <DropdownMenuCheckboxItem
                                key={col.key}
                                checked={visibleColumns[col.key] !== false}
                                onCheckedChange={() => onToggleColumn(col.key)}
                                className="text-xs"
                            >
                                {col.label}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                {/* Density */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="sm" className="h-8 px-2.5 gap-1.5 font-medium text-xs text-muted-foreground hover:text-foreground">
                            <SlidersHorizontal className="size-3.5" />
                            <span className="hidden sm:inline">{densityLabels[density]}</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-40">
                        <DropdownMenuLabel className="text-xs">Row Density</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {(['comfortable', 'compact', 'spacious'] as const).map((d) => (
                            <DropdownMenuCheckboxItem
                                key={d}
                                checked={density === d}
                                onCheckedChange={() => onDensityChange(d)}
                                className="text-xs capitalize"
                            >
                                {densityLabels[d]}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                {/* Separator */}
                {primaryAction && <div className="w-px h-5 bg-border/60 mx-0.5" />}

                {primaryAction}
            </div>
        </div>
    );
}
