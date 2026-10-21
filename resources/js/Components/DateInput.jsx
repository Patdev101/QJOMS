import { useEffect, useRef, useState } from 'react';
import Icon from './Icon';

// value is "YYYY-MM-DD" (what Laravel receives), shown as "July 2, 2026"
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const DAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
const pad = (n) => String(n).padStart(2, '0');
const toIso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const fromIso = (s) => {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
    return m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : null;
};
const sameDay = (a, b) => a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
export const formatDate = (s) => {
    const d = fromIso(s);
    return d ? `${MONTHS[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}` : '';
};
// in the box: "Oct 21, 2026" — fits even the narrow boxes in pop-up windows
const shortDate = (s) => {
    const d = fromIso(s);
    return d ? `${MONTHS[d.getMonth()].slice(0, 3)} ${d.getDate()}, ${d.getFullYear()}` : '';
};

/**
 * A date box with the QJOMS calendar instead of the browser's.
 * Click (or Enter / Space / ↓) to open · arrows move a day or a week · Enter picks · Esc closes.
 * The month title switches to a month-and-year view for dates far away.
 */
export default function DateInput({ id, value, onChange, invalid = false, placeholder = 'Pick a date', min, max, clearable = true }) {
    const [open, setOpen] = useState(false);
    const [view, setView] = useState('days'); // days | months
    const [up, setUp] = useState(false); // open above the box when there is no room below
    const selected = fromIso(value);
    const [cursor, setCursor] = useState(() => selected ?? new Date()); // the highlighted day / shown month
    const wrap = useRef(null);
    const button = useRef(null);
    const grid = useRef(null);
    const today = new Date();
    const minDate = fromIso(min);
    const maxDate = fromIso(max);
    const blocked = (d) => (minDate && d < minDate) || (maxDate && d > maxDate);

    function show() {
        const rect = button.current.getBoundingClientRect();
        setUp(window.innerHeight - rect.bottom < 360 && rect.top > 360);
        setCursor(selected ?? new Date());
        setView('days');
        setOpen(true);
    }
    function close(focusBack = true) {
        setOpen(false);
        if (focusBack) button.current?.focus();
    }
    function pick(d) {
        if (blocked(d)) return;
        onChange(toIso(d));
        close();
    }

    // a click outside closes it
    useEffect(() => {
        if (! open) return undefined;
        const onDown = (e) => { if (! wrap.current?.contains(e.target)) close(false); };
        document.addEventListener('mousedown', onDown);
        return () => document.removeEventListener('mousedown', onDown);
    }, [open]);

    // keep the keyboard on the calendar while it is open
    useEffect(() => {
        if (open && view === 'days') grid.current?.focus({ preventScroll: true });
    }, [open, view, cursor]);

    function onGridKey(e) {
        const step = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key];
        if (step) {
            e.preventDefault();
            setCursor(new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + step));
        } else if (e.key === 'PageUp' || e.key === 'PageDown') {
            e.preventDefault();
            setCursor(new Date(cursor.getFullYear(), cursor.getMonth() + (e.key === 'PageUp' ? -1 : 1), 1));
        } else if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            pick(cursor);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            close();
        }
    }

    const year = cursor.getFullYear();
    const month = cursor.getMonth();
    const first = new Date(year, month, 1);
    const start = new Date(year, month, 1 - first.getDay()); // the Sunday on or before the 1st
    const days = Array.from({ length: 42 }, (_, i) => new Date(start.getFullYear(), start.getMonth(), start.getDate() + i));
    const shift = (n) => setCursor(new Date(year, month + n, 1));

    return (
        <div className={`date-input${open ? ' open' : ''}`} ref={wrap}>
            <button type="button" id={id} ref={button} className={`date-field${invalid ? ' is-invalid' : ''}${value ? '' : ' date-blank'}`}
                    aria-haspopup="dialog" aria-expanded={open}
                    onClick={() => (open ? close() : show())}
                    onKeyDown={(e) => { if (e.key === 'ArrowDown' && ! open) { e.preventDefault(); show(); } }}>
                <Icon name="calendar" size={16} className="date-ic" />
                <span className="date-text" title={value ? formatDate(value) : undefined}>{value ? shortDate(value) : placeholder}</span>
            </button>
            {clearable && value && (
                <button type="button" className="date-clear" aria-label="Clear date" title="Clear"
                        onClick={() => { onChange(''); button.current?.focus(); }}>
                    <Icon name="x" size={14} />
                </button>
            )}

            {open && (
                <div className={`date-pop${up ? ' up' : ''}`} role="dialog" aria-label="Choose a date">
                    <div className="date-head">
                        <button type="button" className="date-nav" aria-label={view === 'days' ? 'Previous month' : 'Previous year'}
                                onClick={() => (view === 'days' ? shift(-1) : setCursor(new Date(year - 1, month, 1)))}>
                            <Icon name="chevron-left" size={16} />
                        </button>
                        <button type="button" className="date-title" onClick={() => setView(view === 'days' ? 'months' : 'days')}
                                title={view === 'days' ? 'Pick a month and year' : 'Back to days'}>
                            {view === 'days' ? `${MONTHS[month]} ${year}` : year}
                            <Icon name="chevron-down" size={14} className={view === 'months' ? 'flip' : ''} />
                        </button>
                        <button type="button" className="date-nav" aria-label={view === 'days' ? 'Next month' : 'Next year'}
                                onClick={() => (view === 'days' ? shift(1) : setCursor(new Date(year + 1, month, 1)))}>
                            <Icon name="chevron-right" size={16} />
                        </button>
                    </div>

                    {view === 'days' ? (
                        <div className="date-grid" role="grid" tabIndex={0} ref={grid} onKeyDown={onGridKey}
                             aria-activedescendant={`${id}-${toIso(cursor)}`}>
                            {DAYS.map((d) => <span key={d} className="date-dow">{d}</span>)}
                            {days.map((d) => {
                                const iso = toIso(d);
                                const cls = ['date-day',
                                    d.getMonth() !== month && 'other',
                                    sameDay(d, today) && 'today',
                                    sameDay(d, selected) && 'selected',
                                    sameDay(d, cursor) && 'cursor',
                                    blocked(d) && 'blocked'].filter(Boolean).join(' ');
                                return (
                                    <button key={iso} id={`${id}-${iso}`} type="button" tabIndex={-1} className={cls} disabled={blocked(d)}
                                            aria-selected={sameDay(d, selected)} aria-label={formatDate(iso)} onClick={() => pick(d)}>
                                        {d.getDate()}
                                    </button>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="date-months">
                            {MONTHS.map((m, i) => (
                                <button key={m} type="button"
                                        className={`date-month${i === month ? ' cursor' : ''}${selected && selected.getFullYear() === year && selected.getMonth() === i ? ' selected' : ''}`}
                                        onClick={() => { setCursor(new Date(year, i, 1)); setView('days'); }}>
                                    {m.slice(0, 3)}
                                </button>
                            ))}
                        </div>
                    )}

                    <div className="date-foot">
                        <button type="button" className="date-link" onClick={() => pick(new Date(today.getFullYear(), today.getMonth(), today.getDate()))}
                                disabled={blocked(new Date(today.getFullYear(), today.getMonth(), today.getDate()))}>Today</button>
                        {clearable && <button type="button" className="date-link muted" onClick={() => { onChange(''); close(); }}>Clear</button>}
                    </div>
                </div>
            )}
        </div>
    );
}
