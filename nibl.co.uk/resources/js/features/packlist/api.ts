import type { KeysetPage, SortState } from './types';

export const ALL_CHUNK_SIZE = 200;

const MIN_QUERY_LENGTH = 3;

export type PackListSource =
    | { mode: 'bot'; botId: number; query: string }
    | { mode: 'search'; query: string };

export function buildKeysetUrl(
    source: PackListSource,
    sort: SortState,
    pageSize: number,
    cursor: string | null,
): string {
    const params = new URLSearchParams({
        sort: sort.key,
        direction: sort.direction,
        size: String(pageSize),
    });

    if (cursor) {
        params.set('cursor', cursor);
    }

    let path: string;
    if (source.mode === 'bot') {
        if (source.query.trim().length >= MIN_QUERY_LENGTH) {
            path = `/api/search/${source.botId}/page`;
            params.set('query', source.query);
        } else {
            path = `/api/packs/${source.botId}/page`;
        }
    } else {
        path = '/api/search/page';
        params.set('query', source.query);
    }

    return `${path}?${params.toString()}`;
}

export async function fetchKeysetPage(url: string): Promise<KeysetPage> {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error(`Pack request failed with status ${response.status}`);
    }

    return (await response.json()) as KeysetPage;
}
