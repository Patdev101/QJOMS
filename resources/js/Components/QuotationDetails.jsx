import { Fragment, useState } from 'react';
import Icon from './Icon';
import { peso, plain } from './format';

const LONG = 15; // more lines than this: main categories start closed, with a search box

// lines grouped by main category (the tariff's sheet section), in the order they were entered
function groupLines(lines) {
    const groups = [];
    lines.forEach((line, i) => {
        const name = line.section || 'OTHER WORKS (manual lines)';
        let g = groups.find((x) => x.name === name);
        if (! g) groups.push(g = { name, lines: [], total: 0 });
        g.lines.push({ line, i });
        g.total += Number(line.total) || 0;
    });
    // A. … G. in the sheet's order, then other sheets ("II. …"), manual lines last — the same order as the printout
    return groups.sort((a, b) => (a.name.startsWith('OTHER WORKS') - b.name.startsWith('OTHER WORKS')) || a.name.localeCompare(b.name));
}

// progress line: [{ label, sub, state: 'done' | 'current' | '' }]
export function Steps({ steps }) {
    return (
        <div className="card">
            <div className="steps">
                {steps.map((step, i) => (
                    <div key={step.label} className={`step ${step.state}`}>
                        <span className="step-dot">{step.state === 'done' ? <Icon name="check" size={16} /> : i + 1}</span>
                        <span className="step-text">
                            <span className="step-label">{step.label}</span>
                            {step.sub && <span className="step-sub">{step.sub}</span>}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

// customer info, lines and totals of a quotation (quotation page and job order page)
export default function QuotationDetails({ q }) {
    const groups = groupLines(q.lines);
    const [open, setOpen] = useState(() => new Set());
    const [find, setFind] = useState('');
    const needle = find.trim().toLowerCase();
    const matches = ({ line }) => ! needle || [line.code, line.description, line.note].join(' ').toLowerCase().includes(needle);
    const toggle = (name) => setOpen((prev) => { const next = new Set(prev); next.has(name) ? next.delete(name) : next.add(name); return next; });
    const info = [
        ['users', 'Customer', q.customer],
        ['user', 'Contact person', q.contact_person],
        ['phone', 'Contact no.', q.contact_no],
        ['map-pin', 'Address', q.address],
        ['folder', 'Project', q.project],
        ['calendar', 'Quotation date', q.date],
    ];

    return (
        <>
            <section className="card">
                <div className="card-head"><h2><Icon name="user" /> Customer &amp; project</h2></div>
                <div className="info-grid">
                    {info.map(([icon, label, value]) => (
                        <div key={label} className="info-item">
                            <span className="info-ic"><Icon name={icon} size={17} /></span>
                            <div>
                                <div className="info-label">{label}</div>
                                <div className={`info-value${value ? '' : ' muted'}`}>{value || '—'}</div>
                            </div>
                        </div>
                    ))}
                </div>
            </section>

            {(q.vessel_name || Object.keys(q.vessel ?? {}).length > 0) && (
                <section className="card">
                    <div className="card-head"><h2><Icon name="clipboard" /> Vessel particulars &amp; dates</h2></div>
                    <div className="info-grid">
                        {[['Vessel', q.vessel_name], ...Object.entries(q.vessel ?? {})].filter(([, v]) => v).map(([label, value]) => (
                            <div key={label} className="info-item">
                                <span className="info-ic"><Icon name={label.startsWith('Date') ? 'calendar' : 'clipboard'} size={17} /></span>
                                <div>
                                    <div className="info-label">{label}</div>
                                    <div className="info-value">{value}</div>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            )}

            <section className="card">
                <div className="card-head">
                    <h2><Icon name="file-text" /> Services &amp; items</h2>
                    <span className="muted">{q.lines.length} {q.lines.length === 1 ? 'line' : 'lines'} in {groups.length} {groups.length === 1 ? 'main category' : 'main categories'}</span>
                </div>
                {/* a long quotation: find a line, or open / close the main categories */}
                {q.lines.length > LONG && (
                    <div className="toolbar">
                        <label className="search">
                            <Icon name="search" size={17} />
                            <span className="sr-only">Search lines</span>
                            <input type="search" value={find} placeholder="Find a line: code, description, note" onChange={(e) => setFind(e.target.value)} />
                        </label>
                        <div className="toolbar-right">
                            {/* one button: Expand all ⇄ Collapse all */}
                            {open.size === groups.length ? (
                                <button type="button" className="btn btn-sm" onClick={() => setOpen(new Set())}><Icon name="chevron-right" size={15} /> Collapse all</button>
                            ) : (
                                <button type="button" className="btn btn-sm" onClick={() => setOpen(new Set(groups.map((g) => g.name)))}><Icon name="chevron-down" size={15} /> Expand all</button>
                            )}
                        </div>
                    </div>
                )}
                <div className="table-scroll">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>#</th><th>Type</th><th>Code</th><th>Description</th><th>UOM</th>
                                <th className="num">Qty</th><th className="num">Unit price</th><th className="num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {groups.map((g) => {
                                const shown = g.lines.filter(matches);
                                if (! shown.length) return null;
                                const isOpen = open.has(g.name) || Boolean(needle) || q.lines.length <= LONG;
                                return (
                                    <Fragment key={g.name}>
                                        <tr className="main-row" onClick={() => toggle(g.name)} aria-expanded={isOpen}>
                                            <td colSpan={8}>
                                                <span className={`tree-chev${isOpen ? ' open' : ''}`}><Icon name="chevron-right" size={16} /></span>
                                                {g.name}
                                                <span className="tree-count">{shown.length} {shown.length === 1 ? 'line' : 'lines'}</span>
                                                <span className="main-total">{peso(g.total)}</span>
                                            </td>
                                        </tr>
                                        {isOpen && shown.map(({ line, i }) => (
                                <tr key={line.id}>
                                    <td className="muted">{i + 1}</td>
                                    <td><span className={`tag ${line.type}`}>{line.type_label}</span></td>
                                    <td>{line.code ? <span className="code">{line.code}</span> : <span className="manual-tag" title="Typed by hand, not from the tariff">Manual</span>}</td>
                                    <td>
                                        <div className="cell-main">{line.description}</div>
                                        {line.note && <div className="cell-sub line-note-text">{line.note}</div>}
                                    </td>
                                    <td>{line.uom}</td>
                                    <td className="num">{line.qty_label ?? line.qty}</td>
                                    <td className="num">{plain(line.unit_price)}</td>
                                    <td className="num cell-main">{plain(line.total)}</td>
                                </tr>
                                        ))}
                                    </Fragment>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
                <div className="lines-foot">
                    <div className="staff-box">
                        <div className="staff-box-title"><Icon name="lock" size={14} /> Staff only · not printed</div>
                        <div className="staff-grid">
                            <div><span>Total cost</span><b>{peso(q.total_cost)}</b></div>
                            <div><span>Profit</span><b className={q.profit < 0 ? 'neg' : ''}>{peso(q.profit)}</b></div>                        </div>
                    </div>
                    <div className="totals">
                        <div className="totals-row"><span>Sub total</span><b>{peso(q.subtotal)}</b></div>
                        <div className="totals-row"><span>Discount ({q.discount_percent}%)</span><b>− {peso(q.discount_amount)}</b></div>
                        <div className="totals-grand"><span>Grand total</span><span className="amount">{peso(q.grand_total)}</span></div>
                    </div>
                </div>
            </section>
        </>
    );
}
