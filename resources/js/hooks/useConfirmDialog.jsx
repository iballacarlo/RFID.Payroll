import { useCallback, useEffect, useRef, useState } from 'react';
import ConfirmDialog from '../components/ConfirmDialog';

export default function useConfirmDialog() {
    const [options, setOptions] = useState(null);
    const resolver = useRef(null);

    const ask = useCallback((nextOptions) => new Promise((resolve) => {
        resolver.current = resolve;
        setOptions(nextOptions);
    }), []);

    const close = useCallback((answer) => {
        resolver.current?.(answer);
        resolver.current = null;
        setOptions(null);
    }, []);

    useEffect(() => () => resolver.current?.(false), []);

    const dialog = <ConfirmDialog
        open={Boolean(options)}
        {...options}
        onConfirm={() => close(true)}
        onCancel={() => close(false)}
    />;

    return { ask, dialog };
}
