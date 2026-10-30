import { Component, type ReactNode } from 'react';

type Props = {
    children: ReactNode;
};

type State = {
    error: Error | null;
};

// Error boundaries must be class components — React has no hook equivalent.
export class ErrorBoundary extends Component<Props, State> {
    state: State = { error: null };

    static getDerivedStateFromError(error: Error): State {
        return { error };
    }

    render() {
        if (this.state.error !== null) {
            return (
                <div className="my-4 p-8 bg-white rounded-sm shadow-sm text-center text-red-600 dark:bg-gray-800">
                    Something went wrong rendering the pack list.
                    <div className="mt-2 text-xs font-mono">{this.state.error.message}</div>
                </div>
            );
        }

        return this.props.children;
    }
}
