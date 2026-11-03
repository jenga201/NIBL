const FUZZY_COOKIE = 'nibl_fuzzy';
const ONE_YEAR_SECONDS = 60 * 60 * 24 * 365;

export function readFuzzyPreference(): boolean {
    if (typeof document === 'undefined') {
        return true;
    }

    const entry = document.cookie
        .split('; ')
        .find((part) => part.startsWith(`${FUZZY_COOKIE}=`));

    if (entry === undefined) {
        return true;
    }

    const value = entry.slice(FUZZY_COOKIE.length + 1);

    return value !== '0' && value.toLowerCase() !== 'false';
}

export function writeFuzzyPreference(fuzzy: boolean): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.cookie = `${FUZZY_COOKIE}=${fuzzy ? '1' : '0'}; path=/; max-age=${ONE_YEAR_SECONDS}; SameSite=Lax`;
}
