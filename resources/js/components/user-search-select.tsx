import { useEffect, useRef, useState } from 'react';
import { Check, ChevronsUpDown, Loader2, X } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type UserSearchOption = {
    id: number;
    name: string;
    email: string;
    label: string;
    employee_id?: number | null;
};

type UserSearchSelectProps = {
    value?: string;
    selectedLabel?: string;
    onChange: (value: string, option: UserSearchOption | null) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    disabled?: boolean;
    className?: string;
    /** When true, search plant employees (login optional) and value uses employee_id. */
    withEmployee?: boolean;
    excludeUserId?: number | null;
    excludeEmployeeId?: number | null;
    clearable?: boolean;
};

export function UserSearchSelect({
    value,
    selectedLabel,
    onChange,
    placeholder = 'Search users…',
    searchPlaceholder = 'Type to search…',
    disabled = false,
    className,
    withEmployee = false,
    excludeUserId = null,
    excludeEmployeeId = null,
    clearable = true,
}: UserSearchSelectProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [options, setOptions] = useState<UserSearchOption[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [displayLabel, setDisplayLabel] = useState(selectedLabel ?? '');
    const containerRef = useRef<HTMLDivElement>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const abortRef = useRef<AbortController | null>(null);

    useEffect(() => {
        setDisplayLabel(selectedLabel ?? '');
    }, [selectedLabel, value]);

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
                setSearchQuery('');
                setOptions([]);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        const query = searchQuery.trim();
        if (query.length < 1) {
            setOptions([]);
            setIsLoading(false);
            return;
        }

        debounceRef.current = setTimeout(async () => {
            abortRef.current?.abort();
            const controller = new AbortController();
            abortRef.current = controller;
            setIsLoading(true);

            try {
                const params = new URLSearchParams({ q: query });
                if (withEmployee) {
                    params.set('with_employee', '1');
                }
                if (excludeUserId) {
                    params.set('exclude_user_id', String(excludeUserId));
                }
                if (excludeEmployeeId) {
                    params.set('exclude_employee_id', String(excludeEmployeeId));
                }

                const response = await fetch(`/organization/users/search?${params.toString()}`, {
                    signal: controller.signal,
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    setOptions([]);
                    return;
                }

                const data = (await response.json()) as UserSearchOption[];
                setOptions(
                    withEmployee
                        ? data.filter((option) => option.employee_id != null)
                        : data,
                );
            } catch (error) {
                if ((error as Error).name !== 'AbortError') {
                    setOptions([]);
                }
            } finally {
                setIsLoading(false);
            }
        }, 250);

        return () => {
            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }
        };
    }, [searchQuery, isOpen, withEmployee, excludeUserId, excludeEmployeeId]);

    const resolveValue = (option: UserSearchOption): string => {
        if (withEmployee) {
            return String(option.employee_id);
        }
        return String(option.id);
    };

    const emptyMessage = withEmployee ? 'No employees found.' : 'No users found.';
    const startTypingMessage = withEmployee
        ? 'Start typing to search employees.'
        : 'Start typing to search users.';

    return (
        <div ref={containerRef} className={cn('relative w-full', className)}>
            <button
                type="button"
                onClick={() => {
                    if (disabled) {
                        return;
                    }
                    setIsOpen((open) => !open);
                }}
                disabled={disabled}
                className={cn(
                    'flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50 text-left cursor-pointer',
                    !displayLabel && 'text-muted-foreground',
                )}
            >
                <span className="truncate">{displayLabel || placeholder}</span>
                <span className="flex items-center gap-1 ml-2 shrink-0">
                    {clearable && value && !disabled && (
                        <span
                            role="button"
                            tabIndex={-1}
                            className="rounded p-0.5 hover:bg-muted"
                            onClick={(e) => {
                                e.stopPropagation();
                                setDisplayLabel('');
                                onChange('', null);
                            }}
                        >
                            <X className="size-3.5 opacity-60" />
                        </span>
                    )}
                    <ChevronsUpDown className="size-4 opacity-50" />
                </span>
            </button>

            {isOpen && (
                <div className="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-md outline-none animate-in fade-in-80 duration-100">
                    <div className="px-2 py-1.5">
                        <Input
                            placeholder={searchPlaceholder}
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="h-8"
                            autoFocus
                        />
                    </div>
                    <div className="border-t border-border/40 my-1" />
                    {isLoading ? (
                        <div className="flex items-center gap-2 px-2 py-2 text-sm text-muted-foreground">
                            <Loader2 className="size-3.5 animate-spin" />
                            Searching…
                        </div>
                    ) : searchQuery.trim().length < 1 ? (
                        <div className="px-2 py-1.5 text-sm text-muted-foreground">
                            {startTypingMessage}
                        </div>
                    ) : options.length === 0 ? (
                        <div className="px-2 py-1.5 text-sm text-muted-foreground">
                            {emptyMessage}
                        </div>
                    ) : (
                        options.map((option) => {
                            const optionValue = resolveValue(option);
                            return (
                                <button
                                    key={`${option.id}-${option.employee_id ?? 'user'}`}
                                    type="button"
                                    onClick={() => {
                                        setDisplayLabel(option.label);
                                        onChange(optionValue, option);
                                        setIsOpen(false);
                                        setSearchQuery('');
                                        setOptions([]);
                                    }}
                                    className="relative flex w-full cursor-pointer select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none hover:bg-accent hover:text-accent-foreground text-left"
                                >
                                    {value === optionValue && (
                                        <span className="absolute left-2 flex size-3.5 items-center justify-center">
                                            <Check className="size-4" />
                                        </span>
                                    )}
                                    <span className="truncate">
                                        <span className="font-medium">{option.name}</span>
                                        <span className="text-muted-foreground"> · {option.email}</span>
                                    </span>
                                </button>
                            );
                        })
                    )}
                </div>
            )}
        </div>
    );
}
