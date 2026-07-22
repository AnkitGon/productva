import { useState, useRef, useEffect } from 'react';
import { Input } from './input';
import { cn } from '@/lib/utils';
import { Check, ChevronsUpDown } from 'lucide-react';

interface Option {
    value: string;
    label: string;
}

interface SearchableSelectProps {
    options: Option[];
    value?: string;
    onChange: (value: string) => void;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
}

export function SearchableSelect({
    options,
    value,
    onChange,
    placeholder = 'Select option...',
    disabled = false,
    className,
}: SearchableSelectProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);

    const selectedOption = options.find((opt) => opt.value === value);

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const filteredOptions = options.filter((opt) =>
        opt.label.toLowerCase().includes(searchQuery.toLowerCase())
    );

    return (
        <div ref={containerRef} className={cn('relative w-full', className)}>
            <button
                type="button"
                onClick={() => !disabled && setIsOpen(!isOpen)}
                disabled={disabled}
                className={cn(
                    "flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50 text-left cursor-pointer",
                    !selectedOption && "text-muted-foreground"
                )}
            >
                <span className="truncate">{selectedOption ? selectedOption.label : placeholder}</span>
                <ChevronsUpDown className="h-4 w-4 shrink-0 opacity-50 ml-2" />
            </button>

            {isOpen && (
                <div className="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-md outline-none animate-in fade-in-80 duration-100">
                    <div className="px-2 py-1.5">
                        <Input
                            placeholder="Search..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="h-8"
                            autoFocus
                        />
                    </div>
                    <div className="border-t border-border/40 my-1" />
                    {filteredOptions.length === 0 ? (
                        <div className="relative flex cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm text-muted-foreground">
                            No results found.
                        </div>
                    ) : (
                        filteredOptions.map((opt) => (
                            <button
                                key={opt.value}
                                type="button"
                                onClick={() => {
                                    onChange(opt.value);
                                    setIsOpen(false);
                                    setSearchQuery('');
                                }}
                                className="relative flex w-full cursor-pointer select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none hover:bg-accent hover:text-accent-foreground text-left"
                            >
                                {opt.value === value && (
                                    <span className="absolute left-2 flex h-3.5 w-3.5 items-center justify-center">
                                        <Check className="h-4 w-4" />
                                    </span>
                                )}
                                <span className="truncate">{opt.label}</span>
                            </button>
                        ))
                    )}
                </div>
            )}
        </div>
    );
}
