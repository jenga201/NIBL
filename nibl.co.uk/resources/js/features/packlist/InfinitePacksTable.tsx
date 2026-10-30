import { useEffect, useMemo, useRef } from 'react';
import type { PackListSource } from './api';
import { DataTable } from './DataTable';
import type { PackColumn, SortState } from './types';
import { useInfinitePacks } from './usePackList';

type Props = {
    source: PackListSource;
    sort: SortState;
    enabled: boolean;
    columns: PackColumn[];
    emptyMessage: string;
    onSortChange: (sort: SortState) => void;
};

export function InfinitePacksTable({
    source,
    sort,
    enabled,
    columns,
    emptyMessage,
    onSortChange,
}: Props) {
    const { data, isLoading, isError, fetchNextPage, hasNextPage, isFetchingNextPage } =
        useInfinitePacks({ source, sort, enabled });

    const rows = useMemo(() => data?.pages.flatMap((page) => page.data) ?? [], [data]);
    const total = data?.pages[0]?.meta.total ?? 0;
    const sentinelRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const sentinel = sentinelRef.current;
        if (!sentinel || !hasNextPage || isFetchingNextPage) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0]?.isIntersecting) {
                    fetchNextPage();
                }
            },
            { rootMargin: '400px' },
        );

        observer.observe(sentinel);
        return () => observer.disconnect();
    }, [hasNextPage, isFetchingNextPage, fetchNextPage]);

    if (isLoading) {
        return <div className="my-4 p-8 text-center">Loading…</div>;
    }

    if (isError) {
        return <div className="my-4 p-8 text-center text-red-600">Failed to load packs. Please try again.</div>;
    }

    if (total === 0) {
        return <div className="my-4 p-8 text-center">{emptyMessage}</div>;
    }

    return (
        <div>
            <div className="flex justify-between items-center px-3 py-2 text-sm">
                <span>
                    Showing {rows.length} of {total} packs
                </span>
                {isFetchingNextPage && <span>Loading more…</span>}
            </div>

            <DataTable data={rows} columns={columns} sort={sort} onSortChange={onSortChange} />

            <div ref={sentinelRef} aria-hidden="true" />

            {isFetchingNextPage && <div className="p-4 text-center text-sm">Loading more…</div>}
        </div>
    );
}
