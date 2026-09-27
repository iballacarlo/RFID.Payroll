import { AlertTriangle, Info, X } from 'lucide-react';
import { useEffect, useRef } from 'react';

export default function ConfirmDialog({ open, title, message, confirmLabel = 'Confirm', cancelLabel = 'Cancel', tone = 'default', onConfirm, onCancel }) {
    const confirmRef = useRef(null);

    useEffect(() => {
        if (!open) return undefined;
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        confirmRef.current?.focus();
        const closeOnEscape = (event) => {
            if (event.key === 'Escape') onCancel();
        };
        window.addEventListener('keydown', closeOnEscape);
        return () => {
            document.body.style.overflow = previousOverflow;
            window.removeEventListener('keydown', closeOnEscape);
        };
    }, [onCancel, open]);

    if (!open) return null;

    const Icon = tone === 'danger' || tone === 'warning' ? AlertTriangle : Info;
    return <div className="dialog-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onCancel(); }}>
        <section className={`confirm-dialog is-${tone}`} role="dialog" aria-modal="true" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-message">
            <button className="dialog-close" type="button" onClick={onCancel} aria-label="Close dialog"><X size={18} /></button>
            <span className="dialog-icon"><Icon size={23} /></span>
            <div className="dialog-copy"><h2 id="confirm-dialog-title">{title}</h2><p id="confirm-dialog-message">{message}</p></div>
            <div className="dialog-actions">
                {cancelLabel && <button className="dialog-cancel" type="button" onClick={onCancel}>{cancelLabel}</button>}
                <button ref={confirmRef} className="dialog-confirm" type="button" onClick={onConfirm}>{confirmLabel}</button>
            </div>
        </section>
    </div>;
}
