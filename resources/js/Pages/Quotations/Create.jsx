import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import Icon from '../../Components/Icon';
import { peso, plain } from '../../Components/format';
import AppLayout from '../../Layouts/AppLayout';

const NONE = '__none__'; // tariffs without a category
let nextKey = 1;
const newLine = (type = 'service', category = '') => ({ key: nextKey++, type, category, tariff_id: '', code: '', qty: '1', unit_price: '' });

// under its sub-category, "Gritblasting SA 2.5 — Hull Area" only needs to say "Hull Area"
const shortName = (t) => (t.subcategory && t.description.includes(' — ')
    ? t.description.split(' — ').pop()
    : t.description);

/* ---------- the page ---------- */
// also the Revise screen: with a `quotation` it starts filled in and saves over that quotation
export default function Create({ number, today, customers, tariffs, types, quotation = null }) {
    const byId = (id) => tariffs.find((t) => t.id === Number(id));
    const catOf = (t) => t?.category ?? NONE;

    const form = useForm(quotation
        ? {
            ...quotation, id: undefined,
            lines: quotation.lines.map((l) => {
                const t = byId(l.tariff_id);
                return { key: nextKey++, ...l, category: t ? catOf(t) : '', code: t?.code ?? '' };
            }),
        }
        : {
            customer_id: '', date: today, address: '', contact_person: '', contact_no: '', project: '',
            discount_percent: '0', lines: [newLine()],
        });
    const backTo = quotation ? `/quotations/${quotation.id}` : '/';
    const { data, setData, errors, processing } = form;

    // only the chosen type (service or item): its categories in name order ("no category" last),
    // and each category's entries grouped by sub-category
    const categoriesFor = (type) => [...new Set(tariffs.filter((t) => t.type === type).map(catOf))]
        .sort((a, b) => (a === NONE) - (b === NONE) || a.localeCompare(b));
    const groupsIn = (type, category) => {
        const groups = [];
        tariffs.filter((t) => t.type === type && catOf(t) === category)
            .sort((a, b) => a.code.localeCompare(b.code))
            .forEach((t) => {
                let g = groups.find((x) => x.name === (t.subcategory ?? ''));
                if (! g) groups.push(g = { name: t.subcategory ?? '', items: [] });
                g.items.push(t);
            });
        return groups;
    };

    const refs = useRef({}); // line key → { service, code, qty, price }
    const [focus, setFocus] = useState(null); // [line key, field] to focus after the next render
    const [missing, setMissing] = useState({});
    const [emptyAlert, setEmptyAlert] = useState(false);
    const customer = customers.find((c) => c.id === Number(data.customer_id));
    const lineRef = (key, field) => (el) => { refs.current[key] = { ...refs.current[key], [field]: el }; };

    useEffect(() => {
        if (! focus) return;
        const el = refs.current[focus[0]]?.[focus[1]];
        if (el) {
            el.focus();
            el.select?.();
            setFocus(null);
        }
    }, [focus, data.lines]);

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

    // a new line starts in the last category used for that type: quicker when entering one section of the tariff
    function addLine(type) {
        const line = newLine(type, data.lines.filter((l) => l.type === type).at(-1)?.category ?? '');
        updateLines((lines) => [...lines, line]);
        setFocus([line.key, line.category ? 'service' : 'category']);
        setEmptyAlert(false);
    }

    function removeLine(key) {
        const index = data.lines.findIndex((l) => l.key === key);
        const rest = data.lines.filter((l) => l.key !== key);
        updateLines(() => rest);
        const next = rest[index] || rest[index - 1];
        if (next) setFocus([next.key, 'service']);
    }

    // picking a service fills in its code, unit and list price
    function choose(line, t) {
        setLine(line.key, { tariff_id: t.id, type: t.type, category: catOf(t), code: t.code, unit_price: Number(t.price).toFixed(2) });
        setMissing((m) => ({ ...m, [line.key]: false }));
        setFocus([line.key, 'qty']);
    }

    // typing a code that exists picks that service; anything else leaves the line empty (shown in red)
    function typeCode(line, text) {
        const t = tariffs.find((x) => x.code.toLowerCase() === text.trim().toLowerCase());
        if (t) {
            setLine(line.key, { code: t.code, tariff_id: t.id, type: t.type, category: catOf(t), unit_price: Number(t.price).toFixed(2) });
            setMissing((m) => ({ ...m, [line.key]: false }));
        } else {
            setLine(line.key, { code: text, tariff_id: '', unit_price: '' });
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
            const t = byId(line.tariff_id);
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
            setFocus([blank.key, blank.category ? 'service' : 'category']);
            return;
        }

        dirty.current = false;
        quotation ? form.put(`/quotations/${quotation.id}`, { preserveScroll: true }) : form.post('/quotations', { preserveScroll: true });
    }

    const messages = [...new Set(Object.values(errors))];
    const lineError = (i, field) => Boolean(errors[`lines.${i}.${field}`]);
    const enterTo = (key, field) => (e) => { if (e.key === 'Enter') { e.preventDefault(); setFocus([key, field]); } };

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

            {/* every tariff code, suggested while typing in a Code box */}
            <datalist id="tariffCodes">
                {tariffs.map((t) => <option key={t.id} value={t.code}>{t.description} — ₱{plain(t.price)}</option>)}
            </datalist>

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
                            <span className="muted" style={{ fontSize: 13 }}>Pick service or item, then category, or just type the code · Enter jumps to the next field</span>
                        </div>
                        <div className="table-scroll">
                            <table className="table lines">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th className="col-type">Type</th>
                                        <th className="col-cat">Category</th>
                                        <th>Service / item</th>
                                        <th className="col-code">Code</th>
                                        <th className="col-uom">UOM</th>
                                        <th className="col-qty num">Qty</th>
                                        <th className="col-price num">Unit price</th>
                                        <th className="col-total num">Total</th>
                                        <th className="col-x"><span className="sr-only">Remove</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.lines.map((line, i) => {
                                        const t = byId(line.tariff_id);
                                        const total = Math.round((Number(line.qty) || 0) * (Number(line.unit_price) || 0) * 100) / 100;
                                        const priceChanged = t && line.unit_price !== '' && Number(line.unit_price) !== Number(t.price);
                                        const unknownCode = line.code.trim() !== '' && ! t;
                                        const needService = missing[line.key] || lineError(i, 'tariff_id');

                                        return (
                                            <tr key={line.key} className={`line${t ? '' : ' is-empty'}`}>
                                                <td className="line-no">{i + 1}</td>
                                                <td>
                                                    {/* service or item first: the lists after it only show that type */}
                                                    <select value={line.type} aria-label="Type"
                                                            onChange={(e) => { setLine(line.key, { type: e.target.value, category: '', tariff_id: '', code: '', unit_price: '' }); setFocus([line.key, 'category']); }}
                                                            onKeyDown={enterTo(line.key, 'category')}>
                                                        {Object.entries(types).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                                    </select>
                                                </td>
                                                <td>
                                                    <select ref={lineRef(line.key, 'category')} value={line.category} aria-label="Category"
                                                            className={needService && ! line.category ? 'is-invalid' : ''}
                                                            onChange={(e) => { setLine(line.key, { category: e.target.value, tariff_id: '', code: '', unit_price: '' }); setFocus([line.key, 'service']); }}
                                                            onKeyDown={enterTo(line.key, 'service')}>
                                                        <option value="">{categoriesFor(line.type).length ? 'Choose…' : `No ${line.type === 'item' ? 'items' : 'services'} yet`}</option>
                                                        {categoriesFor(line.type).map((c) => <option key={c} value={c}>{c === NONE ? 'Other (no category)' : c}</option>)}
                                                    </select>
                                                </td>
                                                <td className="svc-cell">
                                                    <select ref={lineRef(line.key, 'service')} value={line.tariff_id} aria-label="Service or item"
                                                            disabled={! line.category} className={needService && line.category ? 'is-invalid' : ''}
                                                            onChange={(e) => { const picked = byId(e.target.value); if (picked) choose(line, picked); }}
                                                            onKeyDown={enterTo(line.key, 'qty')}>
                                                        <option value="">{line.category ? `Choose ${line.type === 'item' ? 'an item' : 'a service'}…` : 'Choose a category first'}</option>
                                                        {line.category && groupsIn(line.type, line.category).map((g) => (g.name ? (
                                                            <optgroup key={g.name} label={g.name}>
                                                                {g.items.map((x) => <option key={x.id} value={x.id}>{shortName(x)} — ₱{plain(x.price)}</option>)}
                                                            </optgroup>
                                                        ) : g.items.map((x) => <option key={x.id} value={x.id}>{x.description} — ₱{plain(x.price)}</option>)))}
                                                    </select>
                                                    {t && <div className="unit">{t.subcategory ? `${t.subcategory} · ` : ''}{t.unit ? `per ${t.unit.toLowerCase()}` : ''}</div>}
                                                </td>
                                                <td>
                                                    <input ref={lineRef(line.key, 'code')} className={`code-input${unknownCode ? ' is-invalid' : ''}`} list="tariffCodes"
                                                           value={line.code} placeholder="auto" aria-label="Code" autoComplete="off"
                                                           title={unknownCode ? 'No service has this code' : 'Fills in by itself, or type a code'}
                                                           onChange={(e) => typeCode(line, e.target.value)} onKeyDown={enterTo(line.key, 'qty')} />
                                                </td>
                                                <td className="uom">{t?.uom ?? '—'}</td>
                                                <td>
                                                    <input ref={lineRef(line.key, 'qty')} type="number" className={`qty${lineError(i, 'qty') ? ' is-invalid' : ''}`}
                                                           min="0.01" step="0.01" aria-label="Quantity" value={line.qty}
                                                           onChange={(e) => setLine(line.key, { qty: e.target.value })} onKeyDown={enterTo(line.key, 'price')} />
                                                </td>
                                                <td>
                                                    <input ref={lineRef(line.key, 'price')} type="number" className={`price${lineError(i, 'unit_price') ? ' is-invalid' : ''}`}
                                                           min="0" step="0.01" aria-label="Unit price" value={line.unit_price}
                                                           onChange={(e) => setLine(line.key, { unit_price: e.target.value })}
                                                           onKeyDown={(e) => {
                                                               if (e.key !== 'Enter') return;
                                                               e.preventDefault();
                                                               const next = data.lines[i + 1];
                                                               next ? setFocus([next.key, 'service']) : addLine(line.type);
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
                                        <tr><td colSpan={10}>{emptyAlert ? 'Add at least one service or item before saving.' : 'No lines yet. Add the services and items the customer asked for.'}</td></tr>
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
