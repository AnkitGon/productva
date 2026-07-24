import React, { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { AlertTriangle, RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { BulkActionsBar } from './bulk-actions-bar';
import { DataTableToolbar } from './data-table-toolbar';
import { DataTableColumnHeader } from './data-table-column-header';
import { DataTablePagination, type TableMeta } from './data-table-pagination';
import { DataTableSkeleton } from './data-table-skeleton';
import type { RowAction } from './data-table-row-actions';
import { DataTableRowActions } from './data-table-row-actions';

export type { TableMeta, RowAction };

// ─── Column Definition ────────────────────────────────────────────────────────

export interface ColumnDef<T> {
    key: string;
    label: string;
    sortable?: boolean;
    defaultVisible?: boolean;
    className?: string;
    headerClassName?: string;
    /** Set to true to pin column to the left (sticky, for wide tables) */
    sticky?: boolean;
    render?: (row: T) => React.ReactNode;
}

export type BulkActionsContext<T extends { id: number | string }> = {
    selectedIds: Array<T['id']>;
    selectedRows: T[];
    clearSelection: () => void;
};

// ─── DataTable Props ──────────────────────────────────────────────────────────

export interface DataTableProps<T extends { id: number | string }> {
    tableId: string;
    columns: ColumnDef<T>[];
    data: T[];
    meta: TableMeta;
    baseUrl: string;
    currentParams?: Record<string, string>;
    isLoading?: boolean;
    error?: string | null;
    searchPlaceholder?: string;
    primaryAction?: React.ReactNode;
    secondaryActions?: React.ReactNode;
    /** Compact inline filter controls rendered next to the search bar. */
    filterSlot?: React.ReactNode;
    /** Label for the entity type, e.g. "employees". Used in footer: "Showing 1–10 of 235 employees". */
    entityLabel?: string;
    rowActions?: (row: T) => RowAction<T>[];
    onRowClick?: (row: T) => void;
    emptyStateIcon?: React.ElementType;
    emptyStateTitle?: string;
    emptyStateDescription?: string;
    emptyStateAction?: React.ReactNode;
    /** Enable row selection checkboxes and bulk actions bar. */
    selectable?: boolean;
    bulkActions?: (ctx: BulkActionsContext<T>) => React.ReactNode;
}

const DENSITY_CELL_CLASS: Record<string, string> = {
    comfortable: 'px-4 py-3',
    compact: 'px-4 py-1.5',
    spacious: 'px-4 py-5',
};

function mergeColumnVisibility(
    defaults: Record<string, boolean>,
    stored: Record<string, boolean> | null,
): Record<string, boolean> {
    if (!stored) {
        return defaults;
    }

    const next: Record<string, boolean> = { ...defaults };
    for (const key of Object.keys(defaults)) {
        if (Object.prototype.hasOwnProperty.call(stored, key)) {
            next[key] = Boolean(stored[key]);
        }
    }

    return next;
}

// ─── DataTable ────────────────────────────────────────────────────────────────

export function DataTable<T extends { id: number | string }>({
    tableId,
    columns,
    data,
    meta,
    baseUrl,
    currentParams = {},
    isLoading = false,
    error = null,
    searchPlaceholder,
    primaryAction,
    secondaryActions,
    filterSlot,
    entityLabel,
    rowActions,
    onRowClick,
    emptyStateIcon: EmptyIcon,
    emptyStateTitle = 'No records found',
    emptyStateDescription = 'Try adjusting your filters or search query.',
    emptyStateAction,
    selectable = false,
    bulkActions,
}: DataTableProps<T>) {
    const sortBy = currentParams.sort_by ?? '';
    const sortDir = (currentParams.sort_dir ?? 'asc') as 'asc' | 'desc';

    const handleSort = (key: string, dir: 'asc' | 'desc') => {
        const params: Record<string, string> = { ...currentParams };
        if (key) {
            params.sort_by = key;
            params.sort_dir = dir;
        } else {
            delete params.sort_by;
            delete params.sort_dir;
        }
        delete params.page;
        router.get(baseUrl, params, { preserveState: true, replace: true });
    };

    const storageKey = `dt:${tableId}:cols`;
    const defaultVisible = useMemo(() => {
        const defaults: Record<string, boolean> = {};
        columns.forEach((c) => {
            defaults[c.key] = c.defaultVisible !== false;
        });
        return defaults;
    }, [columns]);

    const [visibleColumns, setVisibleColumns] = useState<Record<string, boolean>>(() => {
        try {
            const stored = localStorage.getItem(storageKey);
            return mergeColumnVisibility(defaultVisible, stored ? JSON.parse(stored) : null);
        } catch {
            return defaultVisible;
        }
    });

    useEffect(() => {
        setVisibleColumns((prev) => {
            const next = mergeColumnVisibility(defaultVisible, prev);
            try {
                localStorage.setItem(storageKey, JSON.stringify(next));
            } catch {
                // ignore
            }
            return next;
        });
    }, [defaultVisible, storageKey]);

    const toggleColumn = (key: string) => {
        setVisibleColumns((prev) => {
            const next = { ...prev, [key]: !prev[key] };
            try {
                localStorage.setItem(storageKey, JSON.stringify(next));
            } catch {
                // ignore
            }
            return next;
        });
    };

    const densityKey = `dt:${tableId}:density`;
    type Density = 'comfortable' | 'compact' | 'spacious';
    const [density, setDensity] = useState<Density>(() => {
        try {
            return (localStorage.getItem(densityKey) as Density) ?? 'comfortable';
        } catch {
            return 'comfortable';
        }
    });

    const handleDensityChange = (d: Density) => {
        setDensity(d);
        try {
            localStorage.setItem(densityKey, d);
        } catch {
            // ignore
        }
    };

    const cellPadding = DENSITY_CELL_CLASS[density];

    const [selectedIds, setSelectedIds] = useState<Array<T['id']>>([]);

    useEffect(() => {
        setSelectedIds((prev) => prev.filter((id) => data.some((row) => row.id === id)));
    }, [data]);

    const clearSelection = () => setSelectedIds([]);

    const pageIds = data.map((row) => row.id);
    const allPageSelected = pageIds.length > 0 && pageIds.every((id) => selectedIds.includes(id));
    const somePageSelected = pageIds.some((id) => selectedIds.includes(id));

    const toggleRow = (id: T['id']) => {
        setSelectedIds((prev) => (prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]));
    };

    const togglePage = () => {
        if (allPageSelected) {
            setSelectedIds((prev) => prev.filter((id) => !pageIds.includes(id)));
            return;
        }
        setSelectedIds((prev) => Array.from(new Set([...prev, ...pageIds])));
    };

    const selectedRows = useMemo(
        () => data.filter((row) => selectedIds.includes(row.id)),
        [data, selectedIds],
    );

    const visibleCols = useMemo(
        () => columns.filter((c) => visibleColumns[c.key] !== false),
        [columns, visibleColumns],
    );

    const totalColumns = visibleCols.length + (rowActions ? 1 : 0) + (selectable ? 1 : 0);

    return (
        <div className="border border-border/40 rounded-xl shadow-sm bg-card">
            <DataTableToolbar
                columns={columns}
                visibleColumns={visibleColumns}
                onToggleColumn={toggleColumn}
                density={density}
                onDensityChange={handleDensityChange}
                baseUrl={baseUrl}
                currentParams={currentParams}
                searchPlaceholder={searchPlaceholder}
                primaryAction={primaryAction}
                secondaryActions={secondaryActions}
                filterSlot={filterSlot}
            />

            {selectable && bulkActions && (
                <BulkActionsBar selectedCount={selectedIds.length} onClear={clearSelection}>
                    {bulkActions({
                        selectedIds,
                        selectedRows,
                        clearSelection,
                    })}
                </BulkActionsBar>
            )}

            <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse" style={{ minWidth: 'max-content' }}>
                    <thead className="sticky top-0 z-10">
                        <tr className="bg-card border-b border-border/40">
                            {selectable && (
                                <th className={`${cellPadding} bg-muted/40 w-10`}>
                                    <Checkbox
                                        checked={allPageSelected ? true : somePageSelected ? 'indeterminate' : false}
                                        onCheckedChange={() => togglePage()}
                                        aria-label="Select all on page"
                                    />
                                </th>
                            )}
                            {visibleCols.map((col) => (
                                <th
                                    key={col.key}
                                    className={`${cellPadding} bg-muted/40 ${col.headerClassName ?? col.className ?? ''}`}
                                >
                                    {col.sortable ? (
                                        <DataTableColumnHeader
                                            label={col.label}
                                            sortKey={col.key}
                                            currentSortBy={sortBy}
                                            currentSortDir={sortDir}
                                            onSort={handleSort}
                                        />
                                    ) : (
                                        <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                            {col.label}
                                        </span>
                                    )}
                                </th>
                            ))}
                            {rowActions && (
                                <th className={`${cellPadding} bg-muted/40 text-right w-20`}>
                                    <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                        Actions
                                    </span>
                                </th>
                            )}
                        </tr>
                    </thead>

                    <tbody className="divide-y divide-border/20 text-sm">
                        {isLoading && <DataTableSkeleton columns={totalColumns} />}

                        {!isLoading && error && (
                            <tr>
                                <td colSpan={totalColumns} className="p-10 text-center">
                                    <div className="flex flex-col items-center gap-3 text-muted-foreground">
                                        <AlertTriangle className="size-8 text-destructive/60" />
                                        <p className="font-semibold text-sm text-foreground">{error}</p>
                                        <Button variant="outline" size="sm" className="gap-2" onClick={() => router.reload()}>
                                            <RefreshCw className="size-3.5" />
                                            Retry
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        )}

                        {!isLoading && !error && data.length === 0 && (
                            <tr>
                                <td colSpan={totalColumns} className="p-12 text-center">
                                    <div className="flex flex-col items-center gap-3">
                                        {EmptyIcon && <EmptyIcon className="size-10 text-muted-foreground/30" />}
                                        <p className="font-semibold text-sm text-foreground">{emptyStateTitle}</p>
                                        <p className="text-xs text-muted-foreground max-w-sm">{emptyStateDescription}</p>
                                        {emptyStateAction && <div className="mt-1">{emptyStateAction}</div>}
                                    </div>
                                </td>
                            </tr>
                        )}

                        {!isLoading &&
                            !error &&
                            data.map((row) => (
                                <tr
                                    key={row.id}
                                    className={`group/row hover:bg-primary/[0.035] dark:hover:bg-primary/[0.06] transition-colors ${
                                        onRowClick ? 'cursor-pointer' : ''
                                    } ${selectedIds.includes(row.id) ? 'bg-primary/[0.04]' : ''}`}
                                    onClick={onRowClick ? () => onRowClick(row) : undefined}
                                >
                                    {selectable && (
                                        <td
                                            className={`${cellPadding} w-10`}
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            <Checkbox
                                                checked={selectedIds.includes(row.id)}
                                                onCheckedChange={() => toggleRow(row.id)}
                                                aria-label={`Select row ${row.id}`}
                                            />
                                        </td>
                                    )}
                                    {visibleCols.map((col) => (
                                        <td
                                            key={col.key}
                                            className={`${cellPadding} ${col.className ?? ''} ${
                                                col.sticky
                                                    ? 'sticky left-0 bg-card z-[1] shadow-[1px_0_0_0_hsl(var(--border)/0.3)]'
                                                    : ''
                                            }`}
                                        >
                                            {col.render
                                                ? col.render(row)
                                                : String((row as Record<string, unknown>)[col.key] ?? '—')}
                                        </td>
                                    ))}
                                    {rowActions && (
                                        <td
                                            className={`${cellPadding} text-right`}
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            <DataTableRowActions row={row} actions={rowActions(row)} />
                                        </td>
                                    )}
                                </tr>
                            ))}
                    </tbody>
                </table>
            </div>

            <DataTablePagination
                meta={meta}
                baseUrl={baseUrl}
                currentParams={currentParams}
                entityLabel={entityLabel}
            />
        </div>
    );
}
