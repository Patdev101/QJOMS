import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '../../Components/ConfirmDialog';
import Icon from '../../Components/Icon';
import QuotationDetails, { Steps } from '../../Components/QuotationDetails';
import { peso } from '../../Components/format';
import AppLayout from '../../Layouts/AppLayout';

export default function Show({ quotation: q }) {
    const jo = q.job_order;
    const [asking, setAsking] = useState(false);
    const [askDelete, setAskDelete] = useState(false);
    const [busy, setBusy] = useState(false);

    const steps = [
        { label: 'Quotation made', sub: q.created, state: 'done' },
        { label: 'Customer approval', sub: jo ? `Approved ${q.approved}` : 'Waiting', state: jo ? 'done' : 'current' },
        { label: 'Job order', sub: jo ? `${jo.jo_no} · ${jo.status_label}` : 'After approval', state: jo ? (jo.status === 'billed' ? 'done' : 'current') : '' },
        { label: 'Billing', sub: jo?.status === 'billed' ? 'Billed' : (jo?.status === 'completed' ? 'Ready to bill' : ''), state: jo?.status === 'billed' ? 'done' : '' },
    ];

    function approve() {
        setBusy(true);
        router.post(`/quotations/${q.id}/approve`, {}, { onFinish: () => { setBusy(false); setAsking(false); } });
    }

    function remove() {
        setBusy(true);
        router.delete(`/quotations/${q.id}`, { onFinish: () => { setBusy(false); setAskDelete(false); } });
    }

    return (
        <AppLayout title={q.quotation_no} active="quotations">
            <div className="page-head">
                <div>
                    <div className="eyebrow"><Link href="/quotations"><Icon name="chevron-left" size={14} /> Quotations</Link></div>
                    <h1>
                        {q.quotation_no}
                        {jo ? <span className="pill approved">Approved</span> : <span className="pill pending">For approval</span>}
                    </h1>
                    <p className="sub">{[q.customer, q.project, peso(q.grand_total)].filter(Boolean).join(' · ')}</p>
                </div>
                <div className="actions">
                    {/* the printable page is plain HTML, so it opens in a new tab */}
                    <a href={`/quotations/${q.id}/print`} target="_blank" rel="noreferrer" className="btn"><Icon name="printer" /> Print</a>
                    {q.editable ? (
                        <>
                            <Link href={`/quotations/${q.id}/edit`} className="btn"><Icon name="pencil" /> Revise</Link>
                            <button type="button" className="btn btn-primary" onClick={() => setAsking(true)}><Icon name="check" /> Approve → Job order</button>
                        </>
                    ) : jo && (
                        <Link href={`/job-orders/${jo.id}`} className="btn btn-primary">Open {jo.jo_no} <Icon name="arrow-right" /></Link>
                    )}
                </div>
            </div>

            <div className="stack">
                <Steps steps={steps} />

                {q.editable && (
                    <div className="callout">
                        <Icon name="clock" size={20} />
                        <div>
                            <b>Waiting for the customer's go signal.</b> Print this quotation for the customer. If they want changes,
                            click <b>Revise</b>. Once they agree, click <b>Approve</b> to turn it into a job order.
                        </div>
                    </div>
                )}

                <QuotationDetails q={q} />

                {q.editable && (
                    <div className="danger-zone">
                        <button type="button" className="btn btn-ghost btn-danger" onClick={() => setAskDelete(true)}><Icon name="trash" size={16} /> Delete quotation</button>
                    </div>
                )}
            </div>

            <ConfirmDialog open={askDelete} tone="danger" busy={busy}
                           title={`Delete ${q.quotation_no}?`}
                           message="This quotation and its lines will be removed for good."
                           confirmLabel="Delete quotation"
                           onConfirm={remove} onCancel={() => setAskDelete(false)} />

            <ConfirmDialog open={asking} tone="success" busy={busy}
                           title={`Customer approved ${q.quotation_no}?`}
                           message="It becomes a job order and can no longer be revised."
                           confirmLabel="Approve & create job order"
                           onConfirm={approve} onCancel={() => setAsking(false)} />
        </AppLayout>
    );
}
