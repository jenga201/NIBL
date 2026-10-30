import { useState, type FormEvent } from 'react';

type Props = {
    page: number;
    lastPage: number;
    total: number;
    from: number | null;
    to: number | null;
    onPageChange: (page: number) => void;
};

type PageItem = number | 'ellipsis-left' | 'ellipsis-right';
type Arrow = 'first' | 'prev' | 'next' | 'last';

const ARROW_PATHS: Record<Arrow, string> = {
    first: 'M11 17l-5-5 5-5M18 17l-5-5 5-5',
    prev: 'M15 18l-6-6 6-6',
    next: 'M9 18l6-6-6-6',
    last: 'M13 17l5-5-5-5M6 17l5-5-5-5',
};

function numberButtonClass(active: boolean): string {
    return [
        'h-9 w-9 shrink-0 rounded-full flex items-center justify-center border',
        active
            ? 'bg-gray-800 text-white border-transparent dark:bg-gray-200 dark:text-gray-900'
            : 'hover:bg-gray-100 dark:hover:bg-gray-700 dark:border-gray-700',
    ].join(' ');
}

function buildPageItems(current: number, last: number): PageItem[] {
    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const start = Math.max(2, current - 2);
    const end = Math.min(last - 1, current + 2);

    const items: PageItem[] = [1];
    if (start > 2) {
        items.push('ellipsis-left');
    }
    for (let page = start; page <= end; page += 1) {
        items.push(page);
    }
    if (end < last - 1) {
        items.push('ellipsis-right');
    }
    items.push(last);

    return items;
}

function ArrowButton({
    arrow,
    disabled,
    onClick,
    ariaLabel,
}: {
    arrow: Arrow;
    disabled: boolean;
    onClick: () => void;
    ariaLabel: string;
}) {
    return (
        <button
            type="button"
            aria-label={ariaLabel}
            disabled={disabled}
            onClick={onClick}
            className="h-10 w-10 shrink-0 flex items-center justify-center rounded-full bg-gray-800 hover:bg-gray-700 text-gray-100 dark:bg-gray-900 dark:hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed"
        >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d={ARROW_PATHS[arrow]} />
            </svg>
        </button>
    );
}

export function Pagination({ page, lastPage, total, from, to, onPageChange }: Props) {
    const [jumpValue, setJumpValue] = useState('');

    const goTo = (target: number) => {
        onPageChange(Math.min(Math.max(1, target), lastPage));
    };

    const handleJump = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const target = Number(jumpValue);
        if (Number.isFinite(target) && target >= 1) {
            goTo(Math.round(target));
            setJumpValue('');
        }
    };

    const items = buildPageItems(page, lastPage);

    return (
        <div className="my-3 mx-auto w-full sm:w-1/2">
                    <nav aria-label="Pagination" className="flex items-center justify-between gap-2">
                        <div className="flex items-center gap-1 shrink-0">
                            <ArrowButton arrow="first" disabled={page <= 1} onClick={() => goTo(1)} ariaLabel="First page" />
                            <ArrowButton arrow="prev" disabled={page <= 1} onClick={() => goTo(page - 1)} ariaLabel="Previous page" />
                        </div>

                        <div className="flex items-center justify-center gap-1 min-w-0 overflow-hidden">
                            {items.map((item, index) =>
                                item === 'ellipsis-left' || item === 'ellipsis-right' ? (
                                    <span
                                        key={`ellipsis-${index}`}
                                        className="h-9 w-9 shrink-0 flex items-center justify-center text-gray-400 dark:text-gray-500"
                                        aria-hidden="true"
                                    >
                                        …
                                    </span>
                                ) : (
                                    <button
                                        key={item}
                                        type="button"
                                        onClick={() => goTo(item)}
                                        aria-current={item === page ? 'page' : undefined}
                                        className={numberButtonClass(item === page)}
                                    >
                                        {item}
                                    </button>
                                ),
                            )}
                        </div>

                        <div className="flex items-center gap-1 shrink-0">
                            <ArrowButton arrow="next" disabled={page >= lastPage} onClick={() => goTo(page + 1)} ariaLabel="Next page" />
                            <ArrowButton arrow="last" disabled={page >= lastPage} onClick={() => goTo(lastPage)} ariaLabel="Last page" />
                        </div>
                    </nav>

                    <div className="mt-2 flex flex-wrap items-center justify-center gap-2">
                        <form onSubmit={handleJump} className="flex items-center gap-1">
                            <input
                                type="number"
                                min={1}
                                max={lastPage}
                                value={jumpValue}
                                onChange={(event) => setJumpValue(event.target.value)}
                                aria-label="Go to page"
                                placeholder={String(page)}
                                className="h-9 w-16 rounded-full border text-center text-sm dark:bg-gray-900 dark:border-gray-700"
                            />
                            <button
                                type="submit"
                                className="h-9 px-3 rounded-full border text-sm hover:bg-gray-100 dark:hover:bg-gray-700 dark:border-gray-700"
                            >
                                Go
                            </button>
                        </form>

                        {from !== null && to !== null && (
                            <span className="text-xs tabular-nums text-gray-500 dark:text-gray-400">
                                {from}–{to} of {total}
                            </span>
                        )}
                    </div>
                </div>
    );
}
