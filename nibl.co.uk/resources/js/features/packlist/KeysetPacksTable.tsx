import { useEffect, useState } from 'react';
import type { PackListSource } from './api';
import { DataTable } from './DataTable';
import type { PackColumn, SortState } from './types';
import { useKeysetPacks } from './usePackList';

type Props = {
    source: PackListSource;
    sort: SortState;
    pageSize: number;
    enabled: boolean;
    columns: PackColumn[];
    emptyMessage: string;
    onSortChange: (sort: SortState) => void;
};

function NavButton({
    disabled,
    onClick,
    label,
}: {
    disabled: boolean;
    onClick: () => void;
    label: string;
}) {
    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onClick}
            className="h-10 px-4 shrink-0 flex items-center justify-center rounded-full bg-gray-800 hover:bg-gray-700 text-gray-100 dark:bg-gray-900 dark:hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed"
        >
            {label}
        </button>
    );
}

export function KeysetPacksTable({ source, sort, pageSize, enabled, columns, emptyMessage, onSortChange }: Props) {
    const [cursor, setCursor] = useState<string | null>(null);
    const botId = source.mode === 'bot' ? source.botId : null;
    const query = source.query;
    const fuzzy = source.fuzzy;

    useEffect(() => {
        setCursor(null);
    }, [botId, query, fuzzy, sort.key, sort.direction, pageSize]);

    const { data, isLoading, isError } = useKeysetPacks({ source, sort, pageSize, cursor, enabled });

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
        <div className="my-3 mx-auto w-full sm:w-1/2 flex items-center justify-between gap-4">
            <NavButton disabled={!meta.prev} onClick={() => setCursor(null)} label="« First" />
            <NavButton disabled={!meta.prev} onClick={() => meta.prev && setCursor(meta.prev)} label="← Previous" />
            <span className="text-xs tabular-nums text-gray-500 dark:text-gray-400">
                {meta.total.toLocaleString()} packs
            </span>
            <NavButton disabled={!meta.next} onClick={() => meta.next && setCursor(meta.next)} label="Next →" />
            <NavButton disabled={!meta.next} onClick={() => setCursor('last')} label="Last »" />
        </div>
    );

    return (
        <div>
            {pagination}
            <DataTable data={rows} columns={columns} sort={sort} onSortChange={onSortChange} />
            {pagination}
        </div>
    );
}
