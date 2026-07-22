import React from 'react';
import { Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export interface TableMeta {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number;
    to: number;
}

interface DataTablePaginationProps {
    meta: TableMeta;
    baseUrl: string;
    currentParams: Record<string, string>;
    /** e.g. "employees", "departments" — shown as "Showing 1–10 of 235 employees" */
    entityLabel?: string;
}

const PER_PAGE_OPTIONS = [10, 25, 50, 100];
const MAX_VISIBLE_PAGES = 5;

/** Snap any per_page value to the nearest valid option (handles server default of 15, etc.) */
function normalizePerPage(raw: number): number {
    if (PER_PAGE_OPTIONS.includes(raw)) return raw;
    // pick the closest option
    return PER_PAGE_OPTIONS.reduce((prev, curr) =>
        Math.abs(curr - raw) < Math.abs(prev - raw) ? curr : prev,
    );
}

function buildUrl(baseUrl: string, params: Record<string, string>, overrides: Record<string, string | number>) {
    const merged = { ...params, ...Object.fromEntries(Object.entries(overrides).map(([k, v]) => [k, String(v)])) };
    const qs = new URLSearchParams(merged).toString();
    return qs ? `${baseUrl}?${qs}` : baseUrl;
}

function getPageNumbers(current: number, last: number): (number | '...')[] {
    if (last <= MAX_VISIBLE_PAGES + 2) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }

    const pages: (number | '...')[] = [1];
    let start = Math.max(2, current - 2);
    let end = Math.min(last - 1, current + 2);

    if (current - 1 <= 2) end = Math.min(last - 1, MAX_VISIBLE_PAGES);
    if (last - current <= 2) start = Math.max(2, last - MAX_VISIBLE_PAGES + 1);

    if (start > 2) pages.push('...');
    for (let i = start; i <= end; i++) pages.push(i);
    if (end < last - 1) pages.push('...');
    pages.push(last);

    return pages;
}

export function DataTablePagination({ meta, baseUrl, currentParams, entityLabel }: DataTablePaginationProps) {
    const { current_page, per_page, total, last_page, from, to } = meta;

    const handlePerPageChange = (value: string) => {
        router.get(
            buildUrl(baseUrl, currentParams, { per_page: value, page: 1 }),
            {},
            { preserveState: true, replace: true },
        );
    };

    const pages = getPageNumbers(current_page, last_page);

    // Format "Showing 1–10 of 235 employees"
    const recordLabel = entityLabel ?? 'records';
    const rangeText =
        total === 0
            ? `0 ${recordLabel}`
            : `${from}–${to} of ${total.toLocaleString()} ${recordLabel}`;

    return (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 border-t border-border/40 bg-muted/10">
            {/* Record count — always visible */}
            <span className="text-xs text-muted-foreground whitespace-nowrap order-2 sm:order-1">
                Showing{' '}
                <span className="font-semibold text-foreground">{rangeText}</span>
            </span>

            {/* Right: per page + pagination */}
            <div className="flex items-center gap-3 order-1 sm:order-2">
                {/* Rows per page */}
                <div className="flex items-center gap-2">
                    <Select value={String(normalizePerPage(per_page))} onValueChange={handlePerPageChange}>
                        <SelectTrigger className="h-7 w-[68px] text-xs font-semibold">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent side="top">
                            {PER_PAGE_OPTIONS.map((opt) => (
                                <SelectItem key={opt} value={String(opt)} className="text-xs">
                                    {opt}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <span className="text-xs text-muted-foreground hidden sm:inline">per page</span>
                </div>

                {/* Page navigation */}
                <div className="flex items-center gap-1">
                    {/* Previous */}
                    {current_page > 1 ? (
                        <Button variant="outline" size="sm" className="h-7 px-2 text-xs" asChild>
                            <Link href={buildUrl(baseUrl, currentParams, { page: current_page - 1 })} preserveState>
                                <ChevronLeft className="size-3.5" />
                            </Link>
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" className="h-7 px-2 text-xs opacity-40" disabled>
                            <ChevronLeft className="size-3.5" />
                        </Button>
                    )}

                    {/* Page numbers (only if more than 1 page) */}
                    {last_page > 1 &&
                        pages.map((page, idx) =>
                            page === '...' ? (
                                <span key={`e-${idx}`} className="px-1 text-muted-foreground text-xs select-none">
                                    …
                                </span>
                            ) : (
                                <Button
                                    key={page}
                                    variant={page === current_page ? 'default' : 'outline'}
                                    size="sm"
                                    className="h-7 w-7 p-0 text-xs font-semibold"
                                    asChild={page !== current_page}
                                    disabled={page === current_page}
                                >
                                    {page !== current_page ? (
                                        <Link href={buildUrl(baseUrl, currentParams, { page })} preserveState>
                                            {page}
                                        </Link>
                                    ) : (
                                        <span>{page}</span>
                                    )}
                                </Button>
                            ),
                        )}

                    {/* Single page indicator */}
                    {last_page === 1 && (
                        <Button variant="default" size="sm" className="h-7 w-7 p-0 text-xs font-semibold" disabled>
                            1
                        </Button>
                    )}

                    {/* Next */}
                    {current_page < last_page ? (
                        <Button variant="outline" size="sm" className="h-7 px-2 text-xs" asChild>
                            <Link href={buildUrl(baseUrl, currentParams, { page: current_page + 1 })} preserveState>
                                <ChevronRight className="size-3.5" />
                            </Link>
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" className="h-7 px-2 text-xs opacity-40" disabled>
                            <ChevronRight className="size-3.5" />
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
