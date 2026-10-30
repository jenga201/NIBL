import { useCallback, useEffect, useState } from 'react';
import { isSortKey, type PageSize, type SortDirection, type SortKey } from './types';

export type PackListUrlState = {
    query: string;
    sort: SortKey;
    direction: SortDirection;
    page: number;
    pageSize: PageSize;
};

function readUrlState(): PackListUrlState {
    const params = new URLSearchParams(window.location.search);

    const sizeRaw = params.get('size');
    const pageSize: PageSize = sizeRaw === '1000' ? 1000 : sizeRaw === 'all' ? 'all' : 100;

    const sortRaw = params.get('sort');
        const sort: SortKey = sortRaw !== null && isSortKey(sortRaw) ? sortRaw : 'number';

    return {
        query: params.get('query') ?? '',
        sort,
        direction: params.get('direction') === 'desc' ? 'desc' : 'asc',
        page: Math.max(1, Number(params.get('page')) || 1),
        pageSize,
    };
}

function writeUrlState(state: PackListUrlState): void {
    const url = new URL(window.location.href);
    const params = url.searchParams;

    if (state.query) {
        params.set('query', state.query);
    } else {
        params.delete('query');
    }

    if (state.sort !== 'number' || state.direction !== 'asc') {
        params.set('sort', state.sort);
        params.set('direction', state.direction);
    } else {
        params.delete('sort');
        params.delete('direction');
    }

    if (state.pageSize === 'all') {
        params.set('size', 'all');
    } else {
        params.set('size', String(state.pageSize));
    }

    if (state.pageSize === 'all' || state.page === 1) {
        params.delete('page');
    } else {
        params.set('page', String(state.page));
    }

    window.history.pushState({}, '', `${url.pathname}${url.search}`);
}

export function usePackListState() {
    const [state, setState] = useState<PackListUrlState>(readUrlState);

    const update = useCallback((patch: Partial<PackListUrlState>) => {
        setState((previous) => {
            const next = { ...previous, ...patch };
            writeUrlState(next);
            return next;
        });
    }, []);

    useEffect(() => {
        const handlePopState = () => setState(readUrlState());
        window.addEventListener('popstate', handlePopState);
        return () => window.removeEventListener('popstate', handlePopState);
    }, []);

    return { state, update };
}
