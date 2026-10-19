import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import Icon from '../../Components/Icon';
import { peso, plain } from '../../Components/format';
import AppLayout from '../../Layouts/AppLayout';

let nextKey = 1;
const newLine = (type = 'service') => ({ key: nextKey++, type, tariff_id: '', qty: '1', unit_price: '' });

/* ---------- code search: type a code or description, pick with the mouse or the arrow keys ---------- */
function CodePicker({ tariffs, type, value, invalid, onInput, onPick }) {
    const inputRef = useRef(null);
    const current = tariffs.find((t) => t.id === Number(value));
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [highlight, setHighlight] = useState(0);
    const [pos, setPos] = useState(null);
    const listRef = useRef(null);

    // while the box still shows the chosen code, list everything of that type
    const listFor = (text) => {
        const filter = current && text === current.code ? '' : text.trim().toLowerCase();
        return tariffs.filter((t) => t.type === type && (! filter
            || t.code.toLowerCase().includes(filter)
            || t.description.toLowerCase().includes(filter))).slice(0, 80);
    };
    const options = useMemo(() => listFor(query), [tariffs, type, query, current]);

    function openList(text) {
        setQuery(text);
        setOpen(true);
        setHighlight(Math.max(0, listFor(text).findIndex((t) => t.id === current?.id)));
    }

    function choose(i, moveFocus) {
        const t = options[i];
        if (! t) return;
        setOpen(false);
        onPick(t, moveFocus);
    }

    // the list floats above the page, so the table's scroll area never cuts it off
    useLayoutEffect(() => {
        if (! open) return undefined;

        const place = () => {
            const box = inputRef.current.getBoundingClientRect();
            const width = Math.max(box.width, 380);
            const below = window.innerHeight - box.bottom;
            const up = below < 240 && box.top > below;

            setPos({
                width,
                left: Math.max(8, Math.min(box.left, window.innerWidth - width - 8)),
                top: up ? undefined : box.bottom + 4,
                bottom: up ? window.innerHeight - box.top + 4 : undefined,
            });
        };

        place();
        window.addEventListener('scroll', place, true);
        window.addEventListener('resize', place);

        return () => {
            window.removeEventListener('scroll', place, true);
            window.removeEventListener('resize', place);
        };
    }, [open]);

    useEffect(() => {
        listRef.current?.querySelector('.is-active')?.scrollIntoView({ block: 'nearest' });
    }, [highlight, open]);

    function onKeyDown(event) {
        if (! open) {
            if (event.key === 'ArrowDown' || event.key === 'Enter') {
                event.preventDefault();
                openList(current?.code ?? '');
            }
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            setHighlight((h) => Math.min(Math.max(h + (event.key === 'ArrowDown' ? 1 : -1), 0), options.length - 1));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            choose(highlight, true);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            setOpen(false);
        } else if (event.key === 'Tab' && query.trim() && query !== current?.code) {
            choose(highlight, false); // typed something: Tab takes the highlighted match
        }
    }

    return (
        <>
            <input ref={(el) => { inputRef.current = el; onInput(el); }} type="text" className={`picker-input${invalid ? ' is-invalid' : ''}`} placeholder="Search…"
                   autoComplete="off" role="combobox" aria-expanded={open} aria-label="Code"
                   value={open ? query : (current?.code ?? '')}
                   onFocus={() => openList(current?.code ?? '')}
                   onClick={() => ! open && openList(current?.code ?? '')}
                   onChange={(e) => { setQuery(e.target.value); setOpen(true); setHighlight(0); }}
                   onBlur={() => setOpen(false)}
                   onKeyDown={onKeyDown} />

            {open && pos && (
                <div className="picker-list" role="listbox" ref={listRef} style={pos} onMouseDown={(e) => e.preventDefault()}>
                    {options.length ? options.map((t, i) => (
                        <div key={t.id} role="option" aria-selected={i === highlight}
                             className={`picker-opt${i === highlight ? ' is-active' : ''}`}
                             onMouseDown={() => choose(i, true)}>
                            <span className="code">{t.code}</span>
                            <span className="picker-desc">{t.description}</span>
                            <span className="picker-price">₱{plain(t.price)} / {t.uom}</span>
                        </div>
                    )) : (
                        <div className="picker-empty">Nothing found.</div>
                    )}
                </div>
            )}
        </>
    );
}

/* ---------- the page ---------- */
// also the Revise screen: with a `quotation` it starts filled in and saves over that quotation
export default function Create({ number, today, customers, tariffs, types, quotation = null }) {
    const form = useForm(quotation
        ? { ...quotation, id: undefined, lines: quotation.lines.map((l) => ({ key: nextKey++, ...l })) }
        : {
            customer_id: '', date: today, address: '', contact_person: '', contact_no: '', project: '',
            discount_percent: '0', lines: [newLine()],
        });
    const backTo = quotation ? `/quotations/${quotation.id}` : '/';
    const { data, setData, errors, processing } = form;

    const refs = useRef({});                  // line key → { picker, qty, price }
    const [focusKey, setFocusKey] = useState(null);
    const [missing, setMissing] = useState({});
    const [emptyAlert, setEmptyAlert] = useState(false);

    const tariffOf = (line) => tariffs.find((t) => t.id === Number(line.tariff_id));
    const customer = customers.find((c) => c.id === Number(data.customer_id));
    const lineRef = (key, field) => (el) => { refs.current[key] = { ...refs.current[key], [field]: el }; };

    // a new or next line gets the focus in its code box
    useEffect(() => {
        if (focusKey && refs.current[focusKey]?.picker) {
            refs.current[focusKey].picker.focus();
            setFocusKey(null);
        }
    }, [focusKey, data.lines]);

    // leaving with unsaved changes asks first (closing the tab, or a menu click)
    const dirty = useRef(false);
    dirty.current = form.isDirty && ! processing;
    useEffect(() => {
        const onUnload = (event) => { if (dirty.current) { event.preventDefault(); event.returnValue = ''; } };
        window.addEventListener('beforeunload', onUnload);
        const stop = router.on('before', (event) => {
            if (dirty.current && event.detail.visit.method === 'get' && ! window.confirm('Leave without saving this quotation?')) {
                event.preventDefault();
            }
        });
        return () => { window.removeEventListener('beforeunload', onUnload); stop(); };
    }, []);

    const updateLines = (fn) => setData((prev) => ({ ...prev, lines: fn(prev.lines) }));
    const setLine = (key, changes) => updateLines((lines) => lines.map((l) => (l.key === key ? { ...l, ...changes } : l)));

    function addLine(type) {
        const line = newLine(type);
        updateLines((lines) => [...lines, line]);
        setFocusKey(line.key);
        setEmptyAlert(false);
    }

    function removeLine(key) {
        const index = data.lines.findIndex((l) => l.key === key);
        const rest = data.lines.filter((l) => l.key !== key);
        updateLines(() => rest);
        const next = rest[index] || rest[index - 1];
        if (next) setFocusKey(next.key);
    }

    function pick(line, t, moveFocus) {
        setLine(line.key, { tariff_id: t.id, unit_price: Number(t.price).toFixed(2) });
        setMissing((m) => ({ ...m, [line.key]: false }));
        if (moveFocus) {
            setTimeout(() => {
                refs.current[line.key]?.qty?.focus();
                refs.current[line.key]?.qty?.select();
            });
        }
    }

    // choosing a customer fills in the contact details and the customer's discount
    function chooseCustomer(id) {
        const c = customers.find((x) => x.id === Number(id));
        setData((prev) => ({
            ...prev, customer_id: id, address: c?.address ?? '', contact_person: c?.contact_person ?? '',
            contact_no: c?.contact_no ?? '', discount_percent: String(c ? c.discount_percent : 0),
        }));
    }

    const totals = useMemo(() => {
        let subtotal = 0;
        let cost = 0;
        let filled = 0;

        data.lines.forEach((line) => {
            const t = tariffOf(line);
            const qty = Number(line.qty) || 0;
            subtotal += Math.round(qty * (Number(line.unit_price) || 0) * 100) / 100;
            if (t) { cost += qty * Number(t.cost); filled++; }
        });

        const percent = Math.min(Math.max(Number(data.discount_percent) || 0, 0), 100);
        const discount = Math.round(subtotal * percent) / 100;
        const grand = subtotal - discount;

        return { subtotal, discount, grand, cost, profit: grand - cost, percent, filled };
    }, [data.lines, data.discount_percent]);

    function submit(event) {
        event.preventDefault();

        if (! data.lines.length) {
            setEmptyAlert(true);
            return;
        }

        const blank = data.lines.find((l) => ! l.tariff_id);
        if (blank) {
            setMissing((m) => ({ ...m, [blank.key]: true }));
            refs.current[blank.key]?.picker?.focus();
            return;
        }

        dirty.current = false;
        quotation ? form.put(`/quotations/${quotation.id}`, { preserveScroll: true }) : form.post('/quotations', { preserveScroll: true });
    }

    const messages = [...new Set(Object.values(errors))];
    const lineError = (i, field) => Boolean(errors[`lines.${i}.${field}`]);

    return (
        <AppLayout title={quotation ? `Revise ${number}` : 'New quotation'} active={quotation ? 'quotations' : ''}>
            <div className="page-head">
                <div>
                    <div className="eyebrow"><Link href={backTo}><Icon name="chevron-left" size={14} /> {quotation ? number : 'Dashboard'}</Link></div>
                    {quotation ? (
                        <>
                            <h1>Revise quotation</h1>
                            <p className="sub">Changes are saved on this same quotation. It stays <b>for approval</b> until the customer agrees.</p>
                        </>
                    ) : (
                        <>
                            <h1>New quotation <span className="code code-lg" title="The number is given when you save">{number}</span></h1>
                            <p className="sub">Choose the customer, then add the services and items they asked for.</p>
                        </>
                    )}
                </div>
            </div>

            {messages.length > 0 && (
                <div className="alert alert-error" role="alert">
                    <Icon name="alert" />
                    <div>
                        <b>Please check the following:</b>
                        <ul>{messages.map((m) => <li key={m}>{m}</li>)}</ul>
                    </div>
                </div>
            )}

            <form onSubmit={submit} noValidate
                  onKeyDown={(e) => { if (e.key === 'Enter' && e.target.tagName === 'INPUT') e.preventDefault(); }}>
                <div className="stack">
                    <section className="card">
                        <div className="card-head"><h2><Icon name="user" /> Customer &amp; project</h2></div>
                        <div className="card-body grid-2">
                            <div className="field">
                                <label htmlFor="customer_id">Customer</label>
                                <select id="customer_id" autoFocus value={data.customer_id} className={errors.customer_id ? 'is-invalid' : ''}
                                        onChange={(e) => chooseCustomer(e.target.value)}>
                                    <option value="">Choose a customer…</option>
                                    {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                                {customer && (
                                    <span className={`customer-badge${customer.discount_percent ? '' : ' none'}`}>
                                        <Icon name="tag" size={14} /> {customer.discount_percent ? `${customer.discount_percent}% customer discount` : 'No customer discount'}
                                    </span>
                                )}
                            </div>
                            <div className="field">
                                <label htmlFor="date">Date</label>
                                <input id="date" type="date" value={data.date} className={errors.date ? 'is-invalid' : ''} onChange={(e) => setData('date', e.target.value)} />
                            </div>
                            <div className="field">
                                <label htmlFor="address">Address</label>
                                <input id="address" value={data.address} onChange={(e) => setData('address', e.target.value)} />
                            </div>
                            <div className="field">
                                <label htmlFor="contact_person">Contact person</label>
                                <input id="contact_person" value={data.contact_person} onChange={(e) => setData('contact_person', e.target.value)} />
                            </div>
                            <div className="field">
                                <label htmlFor="project">Project</label>
                                <input id="project" value={data.project} placeholder="e.g. Barge hull repair" onChange={(e) => setData('project', e.target.value)} />
                            </div>
                            <div className="field">
                                <label htmlFor="contact_no">Contact no.</label>
                                <input id="contact_no" value={data.contact_no} onChange={(e) => setData('contact_no', e.target.value)} />
                            </div>
                        </div>
                    </section>

                    <section className="card">
                        <div className="card-head">
                            <h2><Icon name="file-text" /> Services &amp; items</h2>
                            <span className="muted" style={{ fontSize: 13 }}>Type a code or description to search · Enter jumps to the next field</span>
                        </div>
                        <div className="table-scroll">
                            <table className="table lines">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th className="col-type">Type</th>
                                        <th className="col-code">Code</th>
                                        <th>Description</th>
                                        <th className="col-uom">UOM</th>
                                        <th className="col-qty num">Qty</th>
                                        <th className="col-price num">Unit price</th>
                                        <th className="col-total num">Total</th>
                                        <th className="col-x"><span className="sr-only">Remove</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.lines.map((line, i) => {
                                        const t = tariffOf(line);
                                        const total = Math.round((Number(line.qty) || 0) * (Number(line.unit_price) || 0) * 100) / 100;
                                        const priceChanged = t && line.unit_price !== '' && Number(line.unit_price) !== Number(t.price);

                                        return (
                                            <tr key={line.key} className={`line${t ? '' : ' is-empty'}`}>
                                                <td className="line-no">{i + 1}</td>
                                                <td>
                                                    <select value={line.type} aria-label="Type" className={lineError(i, 'type') ? 'is-invalid' : ''}
                                                            onChange={(e) => { setLine(line.key, { type: e.target.value, tariff_id: '', unit_price: '' }); setFocusKey(line.key); }}
                                                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); refs.current[line.key]?.picker?.focus(); } }}>
                                                        {Object.entries(types).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                                    </select>
                                                </td>
                                                <td>
                                                    <CodePicker tariffs={tariffs} type={line.type} value={line.tariff_id}
                                                                invalid={missing[line.key] || lineError(i, 'tariff_id')}
                                                                onInput={lineRef(line.key, 'picker')} onPick={(tariff, move) => pick(line, tariff, move)} />
                                                </td>
                                                <td className="desc-cell">
                                                    <div className="desc">{t?.description}</div>
                                                    <div className="unit">{t?.unit ? `per ${t.unit.toLowerCase()}` : ''}</div>
                                                </td>
                                                <td className="uom">{t?.uom ?? '—'}</td>
                                                <td>
                                                    <input ref={lineRef(line.key, 'qty')} type="number" className={`qty${lineError(i, 'qty') ? ' is-invalid' : ''}`}
                                                           min="0.01" step="0.01" aria-label="Quantity" value={line.qty}
                                                           onChange={(e) => setLine(line.key, { qty: e.target.value })}
                                                           onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); refs.current[line.key]?.price?.focus(); refs.current[line.key]?.price?.select(); } }} />
                                                </td>
                                                <td>
                                                    <input ref={lineRef(line.key, 'price')} type="number" className={`price${lineError(i, 'unit_price') ? ' is-invalid' : ''}`}
                                                           min="0" step="0.01" aria-label="Unit price" value={line.unit_price}
                                                           onChange={(e) => setLine(line.key, { unit_price: e.target.value })}
                                                           onKeyDown={(e) => {
                                                               if (e.key !== 'Enter') return;
                                                               e.preventDefault();
                                                               const next = data.lines[i + 1];
                                                               next ? refs.current[next.key]?.picker?.focus() : addLine(line.type);
                                                           }} />
                                                    {priceChanged && (
                                                        <button type="button" className="price-hint" title="Use the list price"
                                                                onClick={() => setLine(line.key, { unit_price: Number(t.price).toFixed(2) })}>
                                                            List price ₱{plain(t.price)} · reset
                                                        </button>
                                                    )}
                                                </td>
                                                <td className="line-total num">{plain(total)}</td>
                                                <td>
                                                    <button type="button" className="btn btn-ghost btn-icon btn-danger" title="Remove line" aria-label="Remove line"
                                                            onClick={() => removeLine(line.key)}><Icon name="trash" size={16} /></button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                                {data.lines.length === 0 && (
                                    <tbody className={`empty-lines${emptyAlert ? ' attention' : ''}`}>
                                        <tr><td colSpan={9}>{emptyAlert ? 'Add at least one service or item before saving.' : 'No lines yet. Add the services and items the customer asked for.'}</td></tr>
                                    </tbody>
                                )}
                            </table>
                        </div>
                        <div className="add-row">
                            <button type="button" className="btn btn-sm" onClick={() => addLine('service')}><Icon name="wrench" size={16} /> Add service</button>
                            <button type="button" className="btn btn-sm" onClick={() => addLine('item')}><Icon name="package" size={16} /> Add item</button>
                        </div>

                        <div className="lines-foot">
                            <div className="staff-box">
                                <div className="staff-box-title"><Icon name="lock" size={14} /> Staff only · not printed</div>
                                <div className="staff-grid">
                                    <div><span>Total cost</span><b>{peso(totals.cost)}</b></div>
                                    <div><span>Profit</span><b className={totals.profit < 0 ? 'neg' : ''}>{peso(totals.profit)}</b></div>
                                    <div><span>Margin</span><b className={totals.profit < 0 ? 'neg' : ''}>{totals.grand > 0 ? `${(totals.profit / totals.grand * 100).toFixed(1)}%` : '—'}</b></div>
                                </div>
                            </div>
                            <div className="totals">
                                <div className="totals-row"><span>Sub total</span><b>{peso(totals.subtotal)}</b></div>
                                <div className="totals-row">
                                    <span className="disc-label">
                                        Discount
                                        <span className="affix has-suf disc-input">
                                            <input type="number" min="0" max="100" step="0.01" aria-label="Discount percent" value={data.discount_percent}
                                                   className={errors.discount_percent ? 'is-invalid' : ''} onChange={(e) => setData('discount_percent', e.target.value)} />
                                            <span className="suf">%</span>
                                        </span>
                                    </span>
                                    <b>− {peso(totals.discount)}</b>
                                </div>
                                <div className="discount-hint">
                                    {customer && Number(data.discount_percent) !== customer.discount_percent ? (
                                        <>Changed from the customer's {customer.discount_percent}% · <button type="button" className="reset-link"
                                            onClick={() => setData('discount_percent', String(customer.discount_percent))}>Reset</button></>
                                    ) : (customer && customer.discount_percent ? 'From the customer profile' : '')}
                                </div>
                                <div className="totals-grand"><span>Grand total</span><span className="amount">{peso(totals.grand)}</span></div>
                            </div>
                        </div>
                    </section>
                </div>

                <div className="action-bar">
                    <div>
                        <div className="bar-label">Grand total</div>
                        <div className="bar-total">{peso(totals.grand)}</div>
                    </div>
                    <div className="bar-meta">
                        {totals.filled} {totals.filled === 1 ? 'line' : 'lines'}{totals.percent ? ` · ${totals.percent}% discount` : ''}
                    </div>
                    <div className="actions">
                        <Link href={backTo} className="btn btn-ghost">Cancel</Link>
                        <button type="submit" className="btn btn-amber" disabled={processing}>
                            <Icon name="check" /> {processing ? 'Saving…' : (quotation ? 'Save changes' : 'Save quotation')}
                        </button>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
