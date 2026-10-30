import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { createRoot } from 'react-dom/client';
import { ErrorBoundary } from './features/packlist/ErrorBoundary';
import { PackApp } from './features/packlist/PackApp';

function mount(): void {
    document.querySelectorAll<HTMLElement>('[data-pack-table]').forEach((element) => {
        const botIdRaw = element.dataset.botId;
        const botId = botIdRaw ? Number(botIdRaw) : undefined;
        const botName = element.dataset.botName;
        const batchEnabled = element.dataset.batchEnabled === 'true';

        const queryClient = new QueryClient({
            defaultOptions: {
                queries: {
                    staleTime: 30_000,
                    retry: 1,
                    refetchOnWindowFocus: false,
                },
            },
        });

        createRoot(element).render(
            <QueryClientProvider client={queryClient}>
                            <ErrorBoundary>
                                <PackApp botId={botId} botName={botName} batchEnabled={batchEnabled} />
                            </ErrorBoundary>
                        </QueryClientProvider>,
        );
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
} else {
    mount();
}
