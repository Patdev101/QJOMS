import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '../../Components/ConfirmDialog';
import DateInput from '../../Components/DateInput';
import DocFlow from '../../Components/DocFlow';
import History from '../../Components/History';
import Icon from '../../Components/Icon';
import QuotationDetails from '../../Components/QuotationDetails';
import { peso } from '../../Components/format';
import AppLayout from '../../Layouts/AppLayout';
import { InvoiceStatus } from './Index';

export default function Show({ invoice: inv, quotation: q, methods, today, history }) {
    const [paying, setPaying] = useState(false);
    const [removing, setRemoving] = useState(null);
    const [busy, setBusy] = useState(false);
    const settled = inv.balance <= 0;
    const payForm = useForm({ date: today, amount: '', method: 'cash', reference: '' });

    function openPayment() {
        payForm.clearErrors();
        payForm.setData({ date: today, amount: inv.balance.toFixed(2), method: 'cash', reference: '' });
        setPaying(true);
    }

    function recordPayment(event) {
        event.preventDefault();
        payForm.post(`/invoices/${inv.id}/payments`, { preserveScroll: true, onSuccess: () => setPaying(false) });
    }

    function removePayment() {
        setBusy(true);
        router.delete(`/payments/${removing.id}`, { preserveScroll: true, onFinish: () => { setBusy(false); setRemoving(null); } });
    }

    return (
        <AppLayout title={inv.invoice_no} active="invoices">
            <div className="page-head">
                <div>
                    <div className="eyebrow"><Link href="/invoices"><Icon name="chevron-left" size={14} /> Invoices</Link></div>
                    <h1>{inv.invoice_no} <InvoiceStatus inv={inv} /></h1>
                    <p className="sub">
                        {[inv.customer, inv.project].filter(Boolean).join(' · ')} · for{' '}
                        <Link href={`/job-orders/${inv.job_order_id}`} className="link">{inv.jo_no}</Link> · issued {inv.date}, due {inv.due_date}
                    </p>
                </div>
                <div className="actions">
                    <a href={`/invoices/${inv.id}/print`} target="_blank" rel="noreferrer" className="btn"><Icon name="printer" /> Print invoice</a>
                </div>
            </div>

            <div className="stack">
                <DocFlow q={q} here="invoice" />

                <section className="card">
                    <div className="card-head"><h2><Icon name="receipt" /> Invoice status</h2></div>
                    <div className="money-grid">
                        <div><span>Invoice total</span><b>{peso(inv.amount)}</b></div>
                        <div><span>Paid so far</span><b>{peso(inv.paid)}</b></div>
                        <div className={settled ? 'settled' : 'due'}><span>{settled ? 'Balance' : 'Balance due'}</span><b>{peso(Math.max(inv.balance, 0))}</b></div>
                    </div>

                    {settled ? (
                        <div className="next-step closed">
                            <span className="next-ic"><Icon name="check-circle" size={22} /></span>
                            <div className="next-text"><b>Paid in full.</b> Nothing left to collect. This quotation's flow is complete.</div>
                        </div>
                    ) : (
                        <div className="next-step">
                            <span className="next-ic"><Icon name="clock" size={22} /></span>
                            <div className="next-text">
                                <b>{inv.overdue ? 'Overdue.' : 'Next step:'}</b> record the customer's payment. {peso(inv.balance)} is still due{inv.overdue ? ` since ${inv.due_date}` : ` by ${inv.due_date}`}.
                            </div>
                            <div className="next-actions">
                                <button type="button" className="btn btn-primary" onClick={openPayment}><Icon name="plus" /> Record payment</button>
                            </div>
                        </div>
                    )}

                    {inv.payments.length > 0 && (
                        <div className="table-scroll">
                            <table className="table">
                                <thead>
                                    <tr><th>Date</th><th>Method</th><th>Reference</th><th className="num">Amount</th><th /></tr>
                                </thead>
                                <tbody>
                                    {inv.payments.map((p) => (
                                        <tr key={p.id}>
                                            <td>{p.date}</td>
                                            <td>{p.method}</td>
                                            <td className="muted">{p.reference || '—'}</td>
                                            <td className="num cell-main">{peso(p.amount)}</td>
                                            <td>
                                                <div className="row-actions">
                                                    <button type="button" className="btn btn-ghost btn-icon btn-danger" title="Remove this payment" aria-label="Remove payment"
                                                            onClick={() => setRemoving(p)}><Icon name="trash" size={16} /></button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                {inv.notes && <div className="callout"><Icon name="file-text" size={20} /><div><b>Notes:</b> {inv.notes}</div></div>}

                <QuotationDetails q={q} />
                <History items={history} />
            </div>

            {paying && (
                <div className="modal show" onClick={(e) => { if (e.target === e.currentTarget) setPaying(false); }}>
                    <form className="modal-box" role="dialog" aria-modal="true" aria-labelledby="payTitle" onSubmit={recordPayment} noValidate>
                        <div className="modal-icon success"><Icon name="receipt" size={22} className="ic-success" /></div>
                        <h3 id="payTitle">Record payment</h3>
                        <p>{inv.invoice_no} · balance <b>{peso(inv.balance)}</b></p>
                        {Object.keys(payForm.errors).length > 0 && (
                            <div className="alert alert-error"><Icon name="alert" /><div>{Object.values(payForm.errors).join(' ')}</div></div>
                        )}
                        <div className="grid-2">
                            <div className="field">
                                <label htmlFor="pay_amount">Amount</label>
                                <span className="affix has-pre">
                                    <span className="pre">₱</span>
                                    <input id="pay_amount" type="number" min="0.01" step="0.01" autoFocus value={payForm.data.amount}
                                           className={payForm.errors.amount ? 'is-invalid' : ''} onChange={(e) => payForm.setData('amount', e.target.value)} />
                                </span>
                            </div>
                            <div className="field">
                                <label htmlFor="pay_date">Date received</label>
                                <DateInput id="pay_date" value={payForm.data.date} clearable={false} invalid={Boolean(payForm.errors.date)}
                                           onChange={(v) => payForm.setData('date', v)} />
                            </div>
                            <div className="field">
                                <label htmlFor="pay_method">Method</label>
                                <select id="pay_method" value={payForm.data.method} onChange={(e) => payForm.setData('method', e.target.value)}>
                                    {Object.entries(methods).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                </select>
                            </div>
                            <div className="field">
                                <label htmlFor="pay_ref">Reference <span className="muted">(optional)</span></label>
                                <input id="pay_ref" value={payForm.data.reference} placeholder="OR / check / transfer no." onChange={(e) => payForm.setData('reference', e.target.value)} />
                            </div>
                        </div>
                        <div className="actions end" style={{ marginTop: 20 }}>
                            <button type="button" className="btn btn-ghost" onClick={() => setPaying(false)}>Cancel</button>
                            <button type="submit" className="btn btn-primary" disabled={payForm.processing}>
                                <Icon name="check" /> {payForm.processing ? 'Saving…' : 'Record payment'}
                            </button>
                        </div>
                    </form>
                </div>
            )}

            <ConfirmDialog open={Boolean(removing)} tone="danger" busy={busy}
                           title="Remove this payment?"
                           message={`Only do this if it was entered by mistake. ${removing ? peso(removing.amount) : ''} will be added back to the balance.`}
                           confirmLabel="Remove payment" onConfirm={removePayment} onCancel={() => setRemoving(null)} />
        </AppLayout>
    );
}
