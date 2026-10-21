import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import DateInput from '../../Components/DateInput';
import Icon from '../../Components/Icon';
import { peso, plain } from '../../Components/format';
import AppLayout from '../../Layouts/AppLayout';

const NONE = '__none__'; // tariffs without a category
// "Manual entry" in the Category list: a line typed by hand (description, UOM, price) instead of picked from the tariff
const MANUAL = '__manual__';
const isManual = (line) => line.category === MANUAL;
let nextKey = 1;
const newLine = (type = 'service', category = '') => ({
    key: nextKey++, type, category, tariff_id: '', code: '', description: '', uom: '', uom2: '', show2: false, qty: '1', qty2: '', unit_price: '', note: '',
});
const VESSEL = { vessel_name: '', loa: '', lbp: '', breadth: '', depth: '', date_arrival: '', date_drydocked: '', date_undocked: '', date_sail_out: '' };

// a unit in two parts ("Pax / Day") multiplies both quantities; an empty second quantity counts as 1
// "8 Hrs", "24 Hrs", "8Hrs" are blocks of hours: that box takes hours, so 16 hours of "8 Hrs" is 2 blocks
// (a plain "Hr" is already counted in hours)
const hoursPer = (unit) => {
    const m = /^(\d+(?:\.\d+)?)\s*hrs?$/i.exec(String(unit ?? '').trim());
    return m && Number(m[1]) > 0 ? Number(m[1]) : null;
};
const unitsIn = (unit, n) => (hoursPer(unit) ? n / hoursPer(unit) : n);
// "per 8 hrs per day", "per unit per 8 hrs": what one unit price buys
const perText = (uom, uom2) => [uom, uom2].filter(Boolean)
    .map((u) => (hoursPer(u) ? `${hoursPer(u)} hrs` : String(u).toLowerCase())).join(' per ');
// the units of a line: from its tariff, or typed on a manual line
const partsOf = (t, line) => (t ? { uom: t.uom, uom2: t.uom2 } : (isManual(line) ? { uom: line.uom, uom2: line.uom2 || null } : { uom: null, uom2: null }));
// how many priced units a line is: qty (× 2nd qty for a unit in two parts), with hours turned into blocks;
// an empty 2nd qty is one unit (one block of hours for "Unit / 8Hrs")
const unitsOf = (t, line) => {
    const { uom, uom2 } = partsOf(t, line);
    const first = unitsIn(uom, Number(line.qty) || 0);
    if (! uom2) return first;
    return first * unitsIn(uom2, Number(line.qty2) || hoursPer(uom2) || 1);
};
// typing "Pax / Day" in a manual line's UOM box gives it a second unit
const splitUnit = (text) => {
    const [a, ...rest] = String(text).split('/');
    return rest.length && text.trim().toUpperCase() !== 'N/A' ? { uom: a.trim(), uom2: rest.join('/').trim() } : null;
};
const unitLabel = (t) => (t?.uom ? (t.uom2 ? `${t.uom} / ${t.uom2}` : t.uom) : '—');
// drydocking and wharfage are charged per linear meter of the vessel: the L.O.A. is the quantity
// (not lay days: "per day" lines are counted in days)
const usesLoa = (t) => t && /^lm$/i.test(t.uom) && ! t.uom2 && /drydock|wharfage/i.test(`${t.category} ${t.description}`)
    && ! /per day/i.test(t.description);

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
            ...VESSEL, ...quotation, id: undefined,
            lines: quotation.lines.map((l) => {
                const t = byId(l.tariff_id);
                // a line without a tariff (typed by hand, or its tariff was deleted) opens as a manual line
                return { key: nextKey++, qty2: '', note: '', description: '', uom: '', uom2: '', ...l, show2: Boolean(l.uom2), category: t ? catOf(t) : MANUAL, code: t?.code ?? '' };
            }),
        }
        : {
            customer_id: '', date: today, address: '', contact_person: '', contact_no: '', project: '', ...VESSEL,
            discount_percent: '0', lines: [newLine()],
        });
    const backTo = quotation ? `/quotations/${quotation.id}` : '/';
    const { data, setData, errors, processing } = form;

    // only the chosen type (service or item): its categories in the tariff sheet's order (by first code,
    // "no category" last), and each category's entries grouped by sub-category
    const firstCode = (type, category) => tariffs.filter((t) => t.type === type && catOf(t) === category).map((t) => t.code).sort()[0] ?? '';
    const categoriesFor = (type) => [...new Set(tariffs.filter((t) => t.type === type).map(catOf))]
        .sort((a, b) => (a === NONE) - (b === NONE) || firstCode(type, a).localeCompare(firstCode(type, b)));
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

    // a new line starts in the last category used for that type: quicker when entering one section of the tariff;
    // a manual line starts with the cursor in its description
    function addLine(type, manual = false) {
        const last = data.lines.filter((l) => l.type === type && ! isManual(l)).at(-1)?.category ?? '';
        const line = newLine(type, manual ? MANUAL : last);
        updateLines((lines) => [...lines, line]);
        setFocus([line.key, line.category ? 'service' : 'category']);
        setEmptyAlert(false);
    }

    function chooseCategory(line, category) {
        setLine(line.key, { category, tariff_id: '', code: '', unit_price: '', qty2: '', ...(category === MANUAL ? { description: '', uom: '', uom2: '', show2: false } : {}) });
        setMissing((m) => ({ ...m, [line.key]: false }));
        setFocus([line.key, 'service']);
    }

    function removeLine(key) {
        const index = data.lines.findIndex((l) => l.key === key);
        const rest = data.lines.filter((l) => l.key !== key);
        updateLines(() => rest);
        const next = rest[index] || rest[index - 1];
        if (next) setFocus([next.key, 'service']);
    }

    // a different service starts at one unit: 1, or the hours of one block ("8 Hrs" → 8),
    // or the vessel's L.O.A. for drydocking / wharfage per LM
    const startQty = (t) => (hoursPer(t.uom) ? String(hoursPer(t.uom)) : (usesLoa(t) && Number(data.loa) > 0 ? String(Number(data.loa)) : '1'));

    // picking a service fills in its code, unit and list price
    function choose(line, t) {
        const fresh = Number(line.tariff_id) !== t.id;
        setLine(line.key, {
            tariff_id: t.id, type: t.type, category: catOf(t), code: t.code, unit_price: Number(t.price).toFixed(2),
            ...(fresh ? { qty: startQty(t), qty2: '' } : {}),
        });
        setMissing((m) => ({ ...m, [line.key]: false }));
        setFocus([line.key, 'qty']);
    }

    // typing a code that exists picks that service; anything else leaves the line empty (shown in red)
    function typeCode(line, text) {
        const t = tariffs.find((x) => x.code.toLowerCase() === text.trim().toLowerCase());
        if (t) {
            setLine(line.key, { code: t.code, tariff_id: t.id, type: t.type, category: catOf(t), unit_price: Number(t.price).toFixed(2),
                ...(Number(line.tariff_id) !== t.id ? { qty: startQty(t), qty2: '' } : {}) });
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
            const qty = unitsOf(t, line);
            subtotal += Math.round(qty * (Number(line.unit_price) || 0) * 100) / 100;
            if (t) { cost += qty * Number(t.cost); filled++; } else if (isManual(line) && line.description.trim()) filled++;
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

        // every line needs a picked service, or (manual line) a typed description
        const blank = data.lines.find((l) => (isManual(l) ? ! l.description.trim() : ! l.tariff_id));
        if (blank) {
            setMissing((m) => ({ ...m, [blank.key]: true }));
            setFocus([blank.key, blank.category ? 'service' : 'category']);
            return;
        }
        // a manual line with a 2nd quantity also needs the name of that unit ("Day")
        const unnamed = data.lines.find((l) => isManual(l) && String(l.qty2).trim() !== '' && ! String(l.uom2 ?? '').trim());
        if (unnamed) {
            setMissing((m) => ({ ...m, [`${unnamed.key}:uom2`]: true }));
            setFocus([unnamed.key, 'uom2']);
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

            <form className="quote-form" onSubmit={submit} noValidate
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
                                <DateInput id="date" value={data.date} invalid={Boolean(errors.date)} clearable={false} onChange={(v) => setData('date', v)} />
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
                            <h2><Icon name="clipboard" /> Vessel particulars &amp; dates</h2>
                            <span className="muted" style={{ fontSize: 13 }}>Optional · printed only when filled in · L.O.A. fills in drydocking and wharfage per LM</span>
                        </div>
                        <div className="card-body grid-2">
                            <div className="field span-2">
                                <label htmlFor="vessel_name">Vessel</label>
                                <input id="vessel_name" value={data.vessel_name} placeholder="e.g. M/Tkr. Leila Micaela" onChange={(e) => setData('vessel_name', e.target.value)} />
                            </div>
                            <div className="grid-2 span-2 vessel-grid">
                                {[['loa', 'L.O.A. (m)'], ['lbp', 'L.B.P. (m)'], ['breadth', 'Breadth mld. (m)'], ['depth', 'Depth mld. (m)']].map(([name, label]) => (
                                    <div key={name} className="field">
                                        <label htmlFor={name}>{label}</label>
                                        <input id={name} type="number" min="0" step="0.01" value={data[name]} className={errors[name] ? 'is-invalid' : ''}
                                               onChange={(e) => setData(name, e.target.value)} />
                                    </div>
                                ))}
                                {[['date_arrival', 'Date arrival'], ['date_drydocked', 'Date drydocked'], ['date_undocked', 'Date undocked'], ['date_sail_out', 'Date sail-out / tow-out']].map(([name, label]) => (
                                    <div key={name} className="field">
                                        <label htmlFor={name}>{label}</label>
                                        <DateInput id={name} value={data[name]} invalid={Boolean(errors[name])} placeholder="Not set"
                                                   onChange={(v) => setData(name, v)} />
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="card">
                        <div className="card-head">
                            <h2><Icon name="file-text" /> Services &amp; items</h2>
                            <span className="muted" style={{ fontSize: 13 }}>Pick service or item, then category, or just type the code · not in the tariff? choose <b>Manual entry</b> · Enter jumps to the next field</span>
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
                                        <th className="col-qty2" title="For units in two parts, e.g. Pax / Day">× 2nd qty</th>
                                        <th className="col-price num">Unit price</th>
                                        <th className="col-total num">Total</th>
                                        <th className="col-x"><span className="sr-only">Remove</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.lines.map((line, i) => {
                                        const manual = isManual(line);
                                        const t = manual ? null : byId(line.tariff_id);
                                        const total = Math.round(unitsOf(t, line) * (Number(line.unit_price) || 0) * 100) / 100;
                                        const { uom: unit1, uom2: unit2 } = partsOf(t, line);
                                        const hrs1 = hoursPer(unit1);
                                        const hrs2 = hoursPer(unit2);
                                        const has2 = Boolean(unit2) || (manual && line.show2);
                                        const priceChanged = t && line.unit_price !== '' && Number(line.unit_price) !== Number(t.price);
                                        const unknownCode = ! manual && line.code.trim() !== '' && ! t;
                                        const needService = missing[line.key] || lineError(i, 'tariff_id') || lineError(i, 'description');

                                        return (
                                            <tr key={line.key} className={`line${t || manual ? '' : ' is-empty'}${manual ? ' manual' : ''}`}>
                                                <td className="line-no">{i + 1}</td>
                                                <td>
                                                    {/* service or item first: the lists after it only show that type */}
                                                    <select value={line.type} aria-label="Type"
                                                            onChange={(e) => {
                                                                // a manual line stays manual (it has no tariff to match); a tariff line starts over
                                                                setLine(line.key, manual ? { type: e.target.value } : { type: e.target.value, category: '', tariff_id: '', code: '', unit_price: '' });
                                                                setFocus([line.key, manual ? 'service' : 'category']);
                                                            }}
                                                            onKeyDown={enterTo(line.key, 'category')}>
                                                        {Object.entries(types).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                                    </select>
                                                </td>
                                                <td>
                                                    <select ref={lineRef(line.key, 'category')} value={line.category} aria-label="Category"
                                                            className={needService && ! line.category ? 'is-invalid' : ''}
                                                            onChange={(e) => chooseCategory(line, e.target.value)}
                                                            onKeyDown={enterTo(line.key, 'service')}>
                                                        <option value="">{categoriesFor(line.type).length ? 'Choose…' : `No ${line.type === 'item' ? 'items' : 'services'} yet`}</option>
                                                        {categoriesFor(line.type).map((c) => <option key={c} value={c}>{c === NONE ? 'Other (no category)' : c}</option>)}
                                                        <optgroup label="Not in the tariff">
                                                            <option value={MANUAL}>✎ Manual entry</option>
                                                        </optgroup>
                                                    </select>
                                                </td>
                                                <td className="svc-cell">
                                                    {manual ? (
                                                        <input ref={lineRef(line.key, 'service')} value={line.description} maxLength={500} aria-label="Description"
                                                               placeholder={`Describe the ${line.type === 'item' ? 'item' : 'service'}`}
                                                               className={needService && ! line.description.trim() ? 'is-invalid' : ''}
                                                               onChange={(e) => { setLine(line.key, { description: e.target.value }); setMissing((m) => ({ ...m, [line.key]: false })); }}
                                                               onKeyDown={enterTo(line.key, 'uom')} />
                                                    ) : (
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
                                                    )}
                                                    {manual && <div className="unit">Manual line · not from the tariff</div>}
                                                    {t && <div className="unit">{t.subcategory ? `${t.subcategory} · ` : ''}{t.unit ? (Number(t.price) === 0 ? t.unit : `per ${t.unit.toLowerCase()}`) : ''}</div>}
                                                    {(t || manual) && (
                                                        <input className={`line-note${lineError(i, 'note') ? ' is-invalid' : ''}`} value={line.note ?? ''} maxLength={500}
                                                               placeholder="Note on the printout (optional) — e.g. Lay days: July 02 to July 31"
                                                               aria-label="Note on the printout" onChange={(e) => setLine(line.key, { note: e.target.value })} />
                                                    )}
                                                </td>
                                                <td>
                                                    {manual ? <span className="manual-tag" title="Typed by hand, not from the tariff">Manual</span> : (
                                                        <input ref={lineRef(line.key, 'code')} className={`code-input${unknownCode ? ' is-invalid' : ''}`} list="tariffCodes"
                                                               value={line.code} placeholder="auto" aria-label="Code" autoComplete="off"
                                                               title={unknownCode ? 'No service has this code' : 'Fills in by itself, or type a code'}
                                                               onChange={(e) => typeCode(line, e.target.value)} onKeyDown={enterTo(line.key, 'qty')} />
                                                    )}
                                                </td>
                                                {manual ? (
                                                    <td>
                                                        <input ref={lineRef(line.key, 'uom')} className={`uom-input${lineError(i, 'uom') ? ' is-invalid' : ''}`} value={line.uom}
                                                               maxLength={20} placeholder="e.g. lot" aria-label="UOM" title='Type "Pax / Day" for a unit in two parts'
                                                               onChange={(e) => setLine(line.key, { uom: e.target.value })}
                                                               onBlur={() => { const s = splitUnit(line.uom); if (s) setLine(line.key, { ...s, show2: true }); }}
                                                               onKeyDown={enterTo(line.key, 'qty')} />
                                                    </td>
                                                ) : <td className="uom">{unitLabel(t)}</td>}
                                                <td>
                                                    {/* hour blocks ("8 Hrs") are typed in hours: the box says so, like "[ 10 ] Day" beside it */}
                                                    <span className={hrs1 ? 'qty2' : undefined}>
                                                        <input ref={lineRef(line.key, 'qty')} type="number" className={`qty${lineError(i, 'qty') ? ' is-invalid' : ''}`}
                                                               min="0.01" step="0.01" aria-label={hrs1 ? 'Hours' : 'Quantity'} value={line.qty}
                                                               title={usesLoa(t) && Number(data.loa) > 0 ? `Vessel L.O.A. is ${Number(data.loa)} m` : undefined}
                                                               onChange={(e) => setLine(line.key, { qty: e.target.value })} onKeyDown={enterTo(line.key, has2 ? 'qty2' : 'price')} />
                                                        {hrs1 && <span className="uom">hrs</span>}
                                                    </span>
                                                </td>
                                                <td>
                                                    {has2 ? (
                                                        <>
                                                            <span className="qty2">
                                                                <input ref={lineRef(line.key, 'qty2')} type="number" className={`qty${lineError(i, 'qty2') ? ' is-invalid' : ''}`}
                                                                       min="0.01" step="0.01" aria-label={hrs2 ? 'Hours' : (unit2 || 'Second quantity')} value={line.qty2}
                                                                       placeholder={String(hrs2 || 1)}
                                                                       onChange={(e) => setLine(line.key, { qty2: e.target.value })} onKeyDown={enterTo(line.key, manual ? 'uom2' : 'price')} />
                                                                {manual ? (
                                                                    <input ref={lineRef(line.key, 'uom2')} value={line.uom2} maxLength={20} placeholder="e.g. Day" aria-label="Second unit"
                                                                           className={`uom2-input${missing[`${line.key}:uom2`] || lineError(i, 'uom2') ? ' is-invalid' : ''}`}
                                                                           onChange={(e) => { setLine(line.key, { uom2: e.target.value }); setMissing((m) => ({ ...m, [`${line.key}:uom2`]: false })); }}
                                                                           onKeyDown={enterTo(line.key, 'price')} />
                                                                ) : <span className="uom">{hrs2 ? 'hrs' : unit2}</span>}
                                                                {manual && (
                                                                    <button type="button" className="unit2-x" title="Remove the second unit" aria-label="Remove the second unit"
                                                                            onClick={() => setLine(line.key, { uom2: '', qty2: '', show2: false })}><Icon name="x" size={13} /></button>
                                                                )}
                                                            </span>
                                                        </>
                                                    ) : manual ? (
                                                        <button type="button" className="add-unit2" title="For a unit in two parts, e.g. Pax / Day: both quantities are multiplied"
                                                                onClick={() => { setLine(line.key, { show2: true }); setFocus([line.key, 'qty2']); }}>
                                                            <Icon name="plus" size={13} /> 2nd unit
                                                        </button>
                                                    ) : <span className="muted">—</span>}
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
                                                    {/* what the price buys, when hours are involved: "per 8 hrs per day" */}
                                                    {(hrs1 || hrs2) && <div className="unit price-per">per {perText(unit1, unit2)}</div>}
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
                                        <tr><td colSpan={11}>{emptyAlert ? 'Add at least one service or item before saving.' : 'No lines yet. Add the services and items the customer asked for.'}</td></tr>
                                    </tbody>
                                )}
                            </table>
                        </div>
                        <div className="add-row">
                            <button type="button" className="btn btn-sm" onClick={() => addLine('service')}><Icon name="wrench" size={16} /> Add service</button>
                            <button type="button" className="btn btn-sm" onClick={() => addLine('item')}><Icon name="package" size={16} /> Add item</button>
                            <button type="button" className="btn btn-sm" onClick={() => addLine('service', true)} title="A line that is not in the tariff: type the description, UOM and price yourself">
                                <Icon name="pencil" size={16} /> Add manual line
                            </button>
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
                        {/* always on screen: add a line without scrolling to the end of a long quotation */}
                        <button type="button" className="btn btn-on-dark" onClick={() => addLine(data.lines.at(-1)?.type ?? 'service')}
                                title="Add a line at the end and jump to it"><Icon name="plus" /> Add line</button>
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
