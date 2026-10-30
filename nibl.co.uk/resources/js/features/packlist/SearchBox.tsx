import { useEffect, useState, type FormEvent } from 'react';

type Props = {
    heading: string;
    value: string;
    onSubmit: (query: string) => void;
};

export function SearchBox({ heading, value, onSubmit }: Props) {
    const [draft, setDraft] = useState(value);

    useEffect(() => {
        setDraft(value);
    }, [value]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        onSubmit(draft);
    };

    return (
        <div className="my-4 bg-white rounded-sm shadow-sm p-4 dark:bg-gray-800">
            <h3 className="font-bold text-xl">{heading}</h3>
            <form
                onSubmit={handleSubmit}
                className="w-full mt-2 h-10 pl-3 pr-2 bg-white border rounded-sm flex justify-between items-center relative dark:bg-gray-900 dark:border-gray-900"
            >
                <label htmlFor="pack-search-input" className="sr-only">
                    Search input
                </label>
                <input
                    id="pack-search-input"
                    type="search"
                    name="query"
                    placeholder="Type your text here"
                    minLength={3}
                    maxLength={250}
                    value={draft}
                    onChange={(event) => setDraft(event.target.value)}
                    className="appearance-none w-full outline-none focus:outline-none dark:bg-gray-900"
                />
                <button type="submit" className="ml-1 outline-none focus:outline-none" aria-label="Search">
                    <svg
                        fill="none"
                        stroke="currentColor"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="2"
                        viewBox="0 0 24 24"
                        className="w-6 h-6"
                    >
                        <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </button>
            </form>
        </div>
    );
}
