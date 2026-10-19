import { useEffect, useRef } from 'react';
import Icon from './Icon';

// "Are you sure?" pop-up. tone: 'success' (green check), 'danger' (red, for deleting) or '' (amber alert)
export default function ConfirmDialog({ open, title, message, confirmLabel = 'Confirm', tone = '', busy = false, onConfirm, onCancel }) {
    const okRef = useRef(null);

    useEffect(() => {
        if (! open) return undefined;
        okRef.current?.focus();
        const onKey = (e) => { if (e.key === 'Escape') onCancel(); };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [open]);

    if (! open) return null;

    return (
        <div className="modal show" onClick={(e) => { if (e.target === e.currentTarget) onCancel(); }}>
            <div className="modal-box" role="alertdialog" aria-modal="true" aria-labelledby="confirmTitle">
                <div className={`modal-icon ${tone}`}>
                    <Icon name={{ success: 'check-circle', danger: 'trash' }[tone] ?? 'alert'} size={22}
                          className={{ success: 'ic-success', danger: 'ic-danger' }[tone] ?? 'ic-default'} />
                </div>
                <h3 id="confirmTitle">{title}</h3>
                <p>{message}</p>
                <div className="actions end">
                    <button type="button" className="btn btn-ghost" onClick={onCancel}>Cancel</button>
                    <button type="button" ref={okRef} className={`btn ${tone === 'danger' ? 'btn-danger-solid' : 'btn-primary'}`} disabled={busy} onClick={onConfirm}>{busy ? 'Working…' : confirmLabel}</button>
                </div>
            </div>
        </div>
    );
}
