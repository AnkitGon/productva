import React from 'react';
import { Badge } from '@/components/ui/badge';

type StatusVariant =
    | 'Active'
    | 'Inactive'
    | 'On Leave'
    | 'Terminated'
    | 'Pending'
    | 'Archived'
    | 'Draft'
    | 'Published'
    | 'Approved'
    | 'Rejected'
    | string;

interface StatusBadgeProps {
    status: StatusVariant;
    className?: string;
}

const STATUS_CONFIG: Record<string, string> = {
    Active: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20',
    Inactive: 'bg-neutral-500/10 text-neutral-600 dark:text-neutral-400 border border-neutral-500/20 hover:bg-neutral-500/20',
    'On Leave': 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 hover:bg-amber-500/20',
    Terminated: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 hover:bg-rose-500/20',
    Pending: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 hover:bg-blue-500/20',
    Archived: 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/20 hover:bg-slate-500/20',
    Draft: 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/20 hover:bg-slate-500/20',
    Published: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20',
    Approved: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20',
    Rejected: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 hover:bg-rose-500/20',
    Released: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20',
    Obsolete: 'bg-neutral-500/10 text-neutral-600 dark:text-neutral-400 border border-neutral-500/20 hover:bg-neutral-500/20',
    Idle: 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/20 hover:bg-slate-500/20',
    Running: 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20 hover:bg-sky-500/20',
    Maintenance: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 hover:bg-amber-500/20',
    Breakdown: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 hover:bg-rose-500/20',
    Retired: 'bg-neutral-500/10 text-neutral-600 dark:text-neutral-400 border border-neutral-500/20 hover:bg-neutral-500/20',
    Negative: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 hover:bg-rose-500/20',
    'In Stock': 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20',
    'Low Stock': 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 hover:bg-amber-500/20',
    'Out Of Stock': 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 hover:bg-rose-500/20',
};

export function StatusBadge({ status, className }: StatusBadgeProps) {
    const colorClass = STATUS_CONFIG[status] ?? 'bg-primary/10 text-primary border border-primary/20';

    return (
        <Badge className={`shadow-none font-medium text-xs px-2 py-0.5 rounded ${colorClass} ${className ?? ''}`}>
            {status}
        </Badge>
    );
}
