import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

export default function useDebouncedFilters(path, filters, options = {}) {
    const { delay = 350, enabled = true } = options;
    const serialized = JSON.stringify(filters);
    const lastSubmitted = useRef(serialized);

    useEffect(() => {
        if (!enabled || serialized === lastSubmitted.current) return undefined;

        const timer = window.setTimeout(() => {
            const values = JSON.parse(serialized);
            const params = Object.fromEntries(
                Object.entries(values).filter(([, value]) => value !== '' && value !== null && value !== undefined),
            );
            lastSubmitted.current = serialized;
            router.get(path, params, {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            });
        }, delay);

        return () => window.clearTimeout(timer);
    }, [delay, enabled, path, serialized]);
}
