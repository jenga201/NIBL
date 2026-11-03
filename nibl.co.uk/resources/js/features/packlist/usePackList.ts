import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import {
    ALL_CHUNK_SIZE,
    buildKeysetUrl,
    fetchKeysetPage,
    type PackListSource,
} from './api';
import type { KeysetPage, SortState } from './types';

type CommonParams = {
    source: PackListSource;
    sort: SortState;
    enabled: boolean;
};

export function sourceKey(source: PackListSource): (string | number | boolean | null)[] {
    return source.mode === 'bot'
        ? [source.mode, source.botId, source.query, source.fuzzy]
        : [source.mode, null, source.query, source.fuzzy];
}

export function useKeysetPacks({
    source,
    sort,
    pageSize,
    cursor,
    enabled,
}: CommonParams & { pageSize: number; cursor: string | null }) {
    const url = buildKeysetUrl(source, sort, pageSize, cursor);

    return useQuery<KeysetPage>({
        queryKey: ['pack-list', 'keyset', ...sourceKey(source), sort.key, sort.direction, pageSize, cursor],
        queryFn: () => fetchKeysetPage(url),
        enabled,
        placeholderData: (previous) => previous,
    });
}

export function useInfiniteKeyset({ source, sort, enabled }: CommonParams) {
    return useInfiniteQuery<KeysetPage>({
        queryKey: ['pack-list', 'infinite', ...sourceKey(source), sort.key, sort.direction],
        queryFn: ({ pageParam }) =>
            fetchKeysetPage(buildKeysetUrl(source, sort, ALL_CHUNK_SIZE, pageParam as string | null)),
        initialPageParam: null,
        getNextPageParam: (lastPage) => lastPage.meta.next ?? undefined,
        enabled,
    });
}
