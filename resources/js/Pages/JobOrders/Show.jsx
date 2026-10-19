import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '../../Components/ConfirmDialog';
import DocFlow from '../../Components/DocFlow';
import History from '../../Components/History';
import Icon from '../../Components/Icon';
import QuotationDetails from '../../Components/QuotationDetails';
import { peso } from '../../Components/format';
import AppLayout from '../../Layouts/AppLayout';

const explain = {
    open: 'Not started yet',
    ongoing: 'Work in progress',
    completed: 'Done, ready for billing',
    billed: 'Billed to the customer',
};

// what the one big button does at each stage
const nextStep = {
    open: { to: 'ongoing', label: 'Start work', icon: 'arrow-right', text: 'The job is waiting to start. Click when the team begins the work.' },
    ongoing: { to: 'completed', label: 'Mark as completed', icon: 'check', text: 'Work is in progress. Click when everything on this job order is done.' },
    completed: { to: 'invoice', label: 'Create invoice', icon: 'receipt', text: 'The work is done. Create the invoice to bill the customer.' },
};

const isoDate = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);

export default function Show({ jobOrder: jo, statuses, quotation: q, invoice, history }) {
    const keys = Object.keys(statuses);
    const currentIndex = keys.indexOf(jo.status);
    const closed = jo.status === 'billed';
    const next = nextStep[jo.status];
    const previous = invoice ? null : keys[currentIndex - 1]; // an invoiced job order can't go back
    const [asking, setAsking] = useState(null); // 'invoice' | 'back' | null
    const [busy, setBusy] = useState(false);

    const in30 = new Date();
    in30.setDate(in30.getDate() + 30);
    const invoiceForm = useForm({ date: isoDate(new Date()), due_date: isoDate(in30), notes: '' });

    function setStatus(status) {
        setBusy(true);
        router.patch(`/job-orders/${jo.id}/status`, { status }, {
            preserveScroll: true,
            onFinish: () => { setBusy(false); setAsking(null); },
        });
    }

    function createInvoice(event) {
        event.preventDefault();
        invoiceForm.post(`/job-orders/${jo.id}/invoice`);
    }

    return (
        <AppLayout title={jo.jo_no} active="job-orders">
            <div className="page-head">
                <div>
                    <div className="eyebrow"><Link href="/job-orders"><Icon name="chevron-left" size={14} /> Job orders</Link></div>
                    <h1>{jo.jo_no} <span className={`pill ${jo.status}`}>{jo.status_label}</span></h1>
                    <p className="sub">
                        {[q.customer, q.project].filter(Boolean).join(' · ')} · from{' '}
                        <Link href={`/quotations/${q.id}`} className="link">{q.quotation_no}</Link>, approved {q.approved}
                    </p>
                </div>
                <div className="actions">
                    <a href={`/quotations/${q.id}/print`} target="_blank" rel="noreferrer" className="btn"><Icon name="printer" /> Print job order</a>
                </div>
            </div>

            <div className="stack">
                <DocFlow q={q} here="job_order" />

                <section className="card">
                    <div className="card-head"><h2><Icon name="clipboard" /> Job order status</h2></div>

                    {/* progress: finished steps get a check; once billed, every step is finished */}
                    <div className="steps">
                        {keys.map((key, i) => {
                            const state = closed || i < currentIndex ? 'done' : (i === currentIndex ? 'current' : '');
                            return (
                                <div key={key} className={`step ${state}`} aria-current={state === 'current' ? 'step' : undefined}>
                                    <span className="step-dot">{state === 'done' ? <Icon name="check" size={16} /> : i + 1}</span>
                                    <span className="step-text">
                                        <span className="step-label">{statuses[key]}</span>
                                        <span className="step-sub">{explain[key]}</span>
                                    </span>
                                </div>
                            );
                        })}
                    </div>

                    {/* the one thing to do next */}
                    <div className={`next-step${closed ? ' closed' : ''}`}>
                        <span className="next-ic"><Icon name={closed ? 'check-circle' : 'clock'} size={22} /></span>
                        <div className="next-text">
                            {closed ? (
                                <><b>Job closed.</b> Billed for {peso(q.grand_total)}{invoice ? ` on invoice ${invoice.invoice_no}` : ''}. Payments are recorded on the invoice.</>
                            ) : (
                                <><b>Next step:</b> {next.text}</>
                            )}
                        </div>
                        <div className="next-actions">
                            {previous && (
                                <button type="button" className="btn btn-ghost btn-sm" disabled={busy} onClick={() => setAsking('back')}>
                                    <Icon name="chevron-left" size={15} /> Back to {statuses[previous]}
                                </button>
                            )}
                            {closed && invoice && (
                                <Link href={`/invoices/${invoice.id}`} className="btn btn-primary"><Icon name="arrow-right" /> Open {invoice.invoice_no}</Link>
                            )}
                            {! closed && (
                                <button type="button" className="btn btn-primary" disabled={busy}
                                        onClick={() => (next.to === 'invoice' ? setAsking('invoice') : setStatus(next.to))}>
                                    <Icon name={next.icon} /> {busy ? 'Saving…' : next.label}
                                </button>
                            )}
                        </div>
                    </div>
                </section>

                <QuotationDetails q={q} />
                <History items={history} />
            </div>

            {asking === 'invoice' && (
                <div className="modal show" onClick={(e) => { if (e.target === e.currentTarget) setAsking(null); }}>
                    <form className="modal-box" role="dialog" aria-modal="true" aria-labelledby="invTitle" onSubmit={createInvoice} noValidate>
                        <div className="modal-icon success"><Icon name="receipt" size={22} className="ic-success" /></div>
                        <h3 id="invTitle">Create invoice for {jo.jo_no}</h3>
                        <p>Amount: <b>{peso(q.grand_total)}</b>. The job order will be marked as billed.</p>
                        {Object.keys(invoiceForm.errors).length > 0 && (
                            <div className="alert alert-error"><Icon name="alert" /><div>{Object.values(invoiceForm.errors).join(' ')}</div></div>
                        )}
                        <div className="grid-2">
                            <div className="field">
                                <label htmlFor="inv_date">Invoice date</label>
                                <input id="inv_date" type="date" value={invoiceForm.data.date} onChange={(e) => invoiceForm.setData('date', e.target.value)} />
                            </div>
                            <div className="field">
                                <label htmlFor="inv_due">Due date</label>
                                <input id="inv_due" type="date" value={invoiceForm.data.due_date} className={invoiceForm.errors.due_date ? 'is-invalid' : ''}
                                       onChange={(e) => invoiceForm.setData('due_date', e.target.value)} />
                            </div>
                        </div>
                        <div className="field field-gap">
                            <label htmlFor="inv_notes">Notes <span className="muted">(optional, printed on the invoice)</span></label>
                            <input id="inv_notes" value={invoiceForm.data.notes} placeholder="e.g. Payment terms: 30 days" onChange={(e) => invoiceForm.setData('notes', e.target.value)} />
                        </div>
                        <div className="actions end" style={{ marginTop: 20 }}>
                            <button type="button" className="btn btn-ghost" onClick={() => setAsking(null)}>Cancel</button>
                            <button type="submit" className="btn btn-primary" disabled={invoiceForm.processing}>
                                <Icon name="check" /> {invoiceForm.processing ? 'Creating…' : 'Create invoice'}
                            </button>
                        </div>
                    </form>
                </div>
            )}

            <ConfirmDialog open={asking === 'back'} busy={busy}
                           title={`Move back to ${previous ? statuses[previous] : ''}?`}
                           message={`Only do this to correct a mistake. ${jo.jo_no} will go back from ${jo.status_label} to ${previous ? statuses[previous] : ''}.`}
                           confirmLabel={`Move back to ${previous ? statuses[previous] : ''}`}
                           onConfirm={() => setStatus(previous)} onCancel={() => setAsking(null)} />
        </AppLayout>
    );
}
