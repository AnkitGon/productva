import React, { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { AlertTriangle, RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
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
}

const DENSITY_CELL_CLASS: Record<string, string> = {
    comfortable: 'px-4 py-3',
    compact: 'px-4 py-1.5',
    spacious: 'px-4 py-5',
};

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
}: DataTableProps<T>) {
    // ── Sorting ──────────────────────────────────────────────────────────────
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

    // ── Column Visibility ─────────────────────────────────────────────────────
    const storageKey = `dt:${tableId}:cols`;
    const defaultVisible = useMemo(() => {
        const defaults: Record<string, boolean> = {};
        columns.forEach((c) => { defaults[c.key] = c.defaultVisible !== false; });
        return defaults;
    }, [columns]);

    const [visibleColumns, setVisibleColumns] = useState<Record<string, boolean>>(() => {
        try {
            const stored = localStorage.getItem(storageKey);
            return stored ? { ...defaultVisible, ...JSON.parse(stored) } : defaultVisible;
        } catch {
            return defaultVisible;
        }
    });

    const toggleColumn = (key: string) => {
        setVisibleColumns((prev) => {
            const next = { ...prev, [key]: !prev[key] };
            try { localStorage.setItem(storageKey, JSON.stringify(next)); } catch {}
            return next;
        });
    };

    // ── Density ───────────────────────────────────────────────────────────────
    const densityKey = `dt:${tableId}:density`;
    type Density = 'comfortable' | 'compact' | 'spacious';
    const [density, setDensity] = useState<Density>(() => {
        try { return (localStorage.getItem(densityKey) as Density) ?? 'comfortable'; }
        catch { return 'comfortable'; }
    });

    const handleDensityChange = (d: Density) => {
        setDensity(d);
        try { localStorage.setItem(densityKey, d); } catch {}
    };

    const cellPadding = DENSITY_CELL_CLASS[density];

    // ── Visible columns ───────────────────────────────────────────────────────
    const visibleCols = useMemo(
        () => columns.filter((c) => visibleColumns[c.key] !== false),
        [columns, visibleColumns],
    );

    const totalColumns = visibleCols.length + (rowActions ? 1 : 0);

    return (
        /*
         * Outer card: no overflow-hidden so sticky thead can escape to viewport.
         * Rounded border is maintained purely by the border-radius on this div.
         */
        <div className="border border-border/40 rounded-xl shadow-sm bg-card">
            {/* Toolbar */}
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


            {/* Table — horizontal scroll container */}
            <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse" style={{ minWidth: 'max-content' }}>
                    {/*
                     * Sticky header: position sticky + z-index + explicit bg so rows don't
                     * bleed through during vertical scroll. Works because the outer card
                     * has no overflow-y constraint.
                     */}
                    <thead className="sticky top-0 z-10">
                        <tr className="bg-card border-b border-border/40">
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
                        {/* Loading */}
                        {isLoading && <DataTableSkeleton columns={totalColumns} />}

                        {/* Error */}
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

                        {/* Empty */}
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

                        {/* Rows */}
                        {!isLoading &&
                            !error &&
                            data.map((row) => (
                                <tr
                                    key={row.id}
                                    className={`group/row hover:bg-primary/[0.035] dark:hover:bg-primary/[0.06] transition-colors ${
                                        onRowClick ? 'cursor-pointer' : ''
                                    }`}
                                    onClick={onRowClick ? () => onRowClick(row) : undefined}
                                >
                                    {visibleCols.map((col) => (
                                        <td
                                            key={col.key}
                                            className={`${cellPadding} ${col.className ?? ''} ${
                                                col.sticky ? 'sticky left-0 bg-card z-[1] shadow-[1px_0_0_0_hsl(var(--border)/0.3)]' : ''
                                            }`}
                                            onClick={
                                                onRowClick && col.key === '_actions'
                                                    ? (e) => e.stopPropagation()
                                                    : undefined
                                            }
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

            {/* Pagination — always visible */}
            <DataTablePagination
                meta={meta}
                baseUrl={baseUrl}
                currentParams={currentParams}
                entityLabel={entityLabel}
            />
        </div>
    );
}
