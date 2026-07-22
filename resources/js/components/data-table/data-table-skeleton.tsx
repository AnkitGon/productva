import React from 'react';
import { Skeleton } from '@/components/ui/skeleton';

interface DataTableSkeletonProps {
    columns: number;
    rows?: number;
}

export function DataTableSkeleton({ columns, rows = 8 }: DataTableSkeletonProps) {
    return (
        <>
            {Array.from({ length: rows }).map((_, rowIdx) => (
                <tr key={rowIdx} className="border-b border-border/20">
                    {Array.from({ length: columns }).map((_, colIdx) => (
                        <td key={colIdx} className="p-4">
                            <Skeleton
                                className={`h-4 ${
                                    colIdx === 0
                                        ? 'w-8 rounded-full'
                                        : colIdx === columns - 1
                                          ? 'w-16 ml-auto'
                                          : `w-${['3/4', '2/3', '1/2', '1/3', '2/3'][colIdx % 5]}`
                                }`}
                            />
                        </td>
                    ))}
                </tr>
            ))}
        </>
    );
}
