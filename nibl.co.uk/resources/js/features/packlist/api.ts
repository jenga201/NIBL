import type { PackListPage, SortState } from './types';

export const ALL_CHUNK_SIZE = 200;

const MIN_QUERY_LENGTH = 3;

export type PackListSource =
    | { mode: 'bot'; botId: number; query: string }
    | { mode: 'search'; query: string };

function hasSearchQuery(source: PackListSource): boolean {
    return source.query.trim().length >= MIN_QUERY_LENGTH;
}

export function buildPacksUrl(
    source: PackListSource,
    sort: SortState,
    page: number,
    perPage: number,
): string {
    const params = new URLSearchParams({
        sort: sort.key,
        direction: sort.direction,
        page: String(page),
        size: String(perPage),
    });

    let path: string;

    if (source.mode === 'bot') {
        if (hasSearchQuery(source)) {
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

export async function fetchPackPage(url: string): Promise<PackListPage> {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error(`Pack request failed with status ${response.status}`);
    }

    return (await response.json()) as PackListPage;
}
