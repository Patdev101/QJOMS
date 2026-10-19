import Icon from './Icon';
import { peso, plain } from './format';

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

            <section className="card">
                <div className="card-head">
                    <h2><Icon name="file-text" /> Services &amp; items</h2>
                    <span className="muted">{q.lines.length} {q.lines.length === 1 ? 'line' : 'lines'}</span>
                </div>
                <div className="table-scroll">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>#</th><th>Type</th><th>Code</th><th>Description</th><th>UOM</th>
                                <th className="num">Qty</th><th className="num">Unit price</th><th className="num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {q.lines.map((line, i) => (
                                <tr key={line.id}>
                                    <td className="muted">{i + 1}</td>
                                    <td><span className={`tag ${line.type}`}>{line.type_label}</span></td>
                                    <td><span className="code">{line.code}</span></td>
                                    <td className="cell-main">{line.description}</td>
                                    <td>{line.uom}</td>
                                    <td className="num">{line.qty}</td>
                                    <td className="num">{plain(line.unit_price)}</td>
                                    <td className="num cell-main">{plain(line.total)}</td>
                                </tr>
                            ))}
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
