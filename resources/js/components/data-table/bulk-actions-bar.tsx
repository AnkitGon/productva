import React from 'react';
import { X } from 'lucide-react';
import { Button } from '@/components/ui/button';

type BulkActionsBarProps = {
    selectedCount: number;
    onClear: () => void;
    children: React.ReactNode;
};

export function BulkActionsBar({ selectedCount, onClear, children }: BulkActionsBarProps) {
    if (selectedCount < 1) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center gap-2 border-b border-border/40 bg-muted/30 px-4 py-2.5">
            <span className="text-xs font-medium text-foreground">
                {selectedCount} selected
            </span>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                className="h-7 px-2 text-xs text-muted-foreground"
                onClick={onClear}
            >
                <X className="size-3.5 mr-1" />
                Clear
            </Button>
            <div className="mx-1 h-4 w-px bg-border/60" />
            <div className="flex flex-wrap items-center gap-1.5">{children}</div>
        </div>
    );
}
