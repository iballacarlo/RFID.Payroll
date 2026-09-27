import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function useRealtimeReload(only, intervalMs = 5000, enabled = true) {
    const [refreshing, setRefreshing] = useState(false);
    const propKey = only.join('|');

    useEffect(() => {
        if (!enabled) return undefined;

        let requestInProgress = false;
        const refresh = () => {
            if (document.visibilityState !== 'visible' || requestInProgress) return;

            requestInProgress = true;
            setRefreshing(true);
            router.reload({
                only,
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    requestInProgress = false;
                    setRefreshing(false);
                },
            });
        };
        const timer = window.setInterval(refresh, intervalMs);
        const refreshWhenVisible = () => {
            if (document.visibilityState === 'visible') refresh();
        };

        document.addEventListener('visibilitychange', refreshWhenVisible);
        window.addEventListener('online', refresh);
        return () => {
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', refreshWhenVisible);
            window.removeEventListener('online', refresh);
        };
    }, [enabled, intervalMs, propKey]);

    return refreshing;
}
