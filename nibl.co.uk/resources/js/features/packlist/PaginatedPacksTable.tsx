import type { PackListSource } from './api';
import { DataTable } from './DataTable';
import { Pagination } from './Pagination';
import type { PackColumn, SortState } from './types';
import { usePaginatedPacks } from './usePackList';

type Props = {
    source: PackListSource;
    sort: SortState;
    page: number;
    pageSize: number;
    enabled: boolean;
    columns: PackColumn[];
    emptyMessage: string;
    onSortChange: (sort: SortState) => void;
    onPageChange: (page: number) => void;
};

export function PaginatedPacksTable({
    source,
    sort,
    page,
    pageSize,
    enabled,
    columns,
    emptyMessage,
    onSortChange,
    onPageChange,
}: Props) {
    const { data, isLoading, isError } = usePaginatedPacks({ source, sort, page, pageSize, enabled });

    if (isLoading) {
        return <div className="my-4 p-8 text-center">Loading…</div>;
    }

    if (isError) {
        return <div className="my-4 p-8 text-center text-red-600">Failed to load packs. Please try again.</div>;
    }

    const rows = data?.data ?? [];
    const meta = data?.meta;

    if (!meta || meta.total === 0) {
        return <div className="my-4 p-8 text-center">{emptyMessage}</div>;
    }

    const pagination = (
        <Pagination
            page={meta.current_page}
            lastPage={meta.last_page}
            total={meta.total}
            from={meta.from}
            to={meta.to}
            onPageChange={onPageChange}
        />
    );

    return (
        <div>
            {pagination}
            <DataTable data={rows} columns={columns} sort={sort} onSortChange={onSortChange} />
            {pagination}
        </div>
    );
}
