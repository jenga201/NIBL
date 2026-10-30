import type { ColumnDef } from '@tanstack/react-table';

export type Pack = {
    id: number;
    bot_id: number;
    number: number;
    name: string;
    size: string;
    sizekbits: number;
    episode_number: number | null;
    last_modified: string;
    bot_name?: string;
    bot_batch_enabled?: boolean;
};

export type PackListMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type PackListPage = {
    data: Pack[];
    meta: PackListMeta;
};

export const SORTABLE_KEYS = ['number', 'name', 'sizekbits', 'bot_name'] as const;

export type SortKey = (typeof SORTABLE_KEYS)[number];
export type SortDirection = 'asc' | 'desc';

export type SortState = {
    key: SortKey;
    direction: SortDirection;
};

export type PageSize = 100 | 1000 | 'all';

export type PackColumn = ColumnDef<Pack, any>;

export function isSortKey(value: string): value is SortKey {
    return (SORTABLE_KEYS as readonly string[]).includes(value);
}
