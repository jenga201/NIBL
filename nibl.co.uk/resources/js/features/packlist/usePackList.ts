import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import {
    ALL_CHUNK_SIZE,
    buildPacksUrl,
    fetchPackPage,
    type PackListSource,
} from './api';
import type { PackListPage, SortState } from './types';

type CommonParams = {
    source: PackListSource;
    sort: SortState;
    enabled: boolean;
};

function sourceKey(source: PackListSource): (string | number | null)[] {
    return source.mode === 'bot'
        ? [source.mode, source.botId, source.query]
        : [source.mode, null, source.query];
}

export function usePaginatedPacks({
    source,
    sort,
    page,
    pageSize,
    enabled,
}: CommonParams & { page: number; pageSize: number }) {
    const url = buildPacksUrl(source, sort, page, pageSize);

    return useQuery<PackListPage>({
        queryKey: ['pack-list', 'paged', ...sourceKey(source), sort.key, sort.direction, page, pageSize],
        queryFn: () => fetchPackPage(url),
        enabled,
        placeholderData: (previous) => previous,
    });
}

export function useInfinitePacks({ source, sort, enabled }: CommonParams) {
    return useInfiniteQuery<PackListPage>({
        queryKey: ['pack-list', 'infinite', ...sourceKey(source), sort.key, sort.direction],
        queryFn: ({ pageParam }) =>
            fetchPackPage(buildPacksUrl(source, sort, Number(pageParam), ALL_CHUNK_SIZE)),
        initialPageParam: 1,
        getNextPageParam: (lastPage) =>
            lastPage.meta.current_page < lastPage.meta.last_page
                ? lastPage.meta.current_page + 1
                : undefined,
        enabled,
    });
}
