import React from 'react';
import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface QuickFilter {
    label: string;
    /** The filter params to apply when this chip is clicked. Empty = "All" (clear filters). */
    params: Record<string, string>;
}

interface DataTableQuickFiltersProps {
    quickFilters: QuickFilter[];
    currentParams: Record<string, string>;
    /** The param keys that are considered "filter" keys (not search/sort/pagination). */
    filterParamKeys: string[];
    baseUrl: string;
}

function isChipActive(
    chip: QuickFilter,
    currentParams: Record<string, string>,
    filterParamKeys: string[],
): boolean {
    const chipKeys = Object.keys(chip.params);
    if (chipKeys.length === 0) {
        // "All" chip is active when no filter params are present
        return filterParamKeys.every((k) => !currentParams[k]);
    }
    // Active when every param in the chip matches current params exactly
    // AND no other filter params (outside this chip) are set
    const chipsParamsMatch = Object.entries(chip.params).every(([k, v]) => currentParams[k] === v);
    const noExtraFilters = filterParamKeys
        .filter((k) => !chipKeys.includes(k))
        .every((k) => !currentParams[k]);
    return chipsParamsMatch && noExtraFilters;
}

export function DataTableQuickFilters({
    quickFilters,
    currentParams,
    filterParamKeys,
    baseUrl,
}: DataTableQuickFiltersProps) {
    const handleChipClick = (chip: QuickFilter) => {
        // Preserve non-filter params (search, sort, per_page); replace filter params
        const params: Record<string, string> = {};
        Object.entries(currentParams).forEach(([k, v]) => {
            if (!filterParamKeys.includes(k) && k !== 'page') {
                params[k] = v;
            }
        });
        Object.assign(params, chip.params);
        router.get(baseUrl, params, { preserveState: true, replace: true });
    };

    // Count how many filter params are active (for "X filters active" display)
    const activeFilterCount = filterParamKeys.filter((k) => !!currentParams[k]).length;

    return (
        <div className="flex items-center gap-1.5 px-4 py-2 border-b border-border/40 bg-muted/5 flex-wrap">
            {quickFilters.map((chip, idx) => {
                const active = isChipActive(chip, currentParams, filterParamKeys);
                return (
                    <button
                        key={idx}
                        onClick={() => handleChipClick(chip)}
                        className={cn(
                            'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium border transition-all duration-150 whitespace-nowrap',
                            active
                                ? 'bg-primary text-primary-foreground border-primary shadow-sm'
                                : 'bg-transparent text-muted-foreground border-border/60 hover:border-primary/40 hover:text-foreground hover:bg-muted/40',
                        )}
                    >
                        {chip.label}
                        {active && Object.keys(chip.params).length > 0 && (
                            <X className="size-3 opacity-70" />
                        )}
                    </button>
                );
            })}

            {/* Clear all filters (when something is active and there's no explicit "All" chip) */}
            {activeFilterCount > 0 && !quickFilters.some((c) => isChipActive(c, currentParams, filterParamKeys)) && (
                <button
                    onClick={() => handleChipClick({ label: 'Clear', params: {} })}
                    className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium text-muted-foreground hover:text-destructive transition-colors"
                >
                    <X className="size-3" />
                    Clear filters
                </button>
            )}
        </div>
    );
}
