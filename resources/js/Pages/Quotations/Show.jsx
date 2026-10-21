import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '../../Components/ConfirmDialog';
import DocFlow from '../../Components/DocFlow';
import History from '../../Components/History';
import Icon from '../../Components/Icon';
import QuotationDetails from '../../Components/QuotationDetails';
import { peso } from '../../Components/format';
import AppLayout from '../../Layouts/AppLayout';

// same layout as the job order and invoice pages: header → flow strip → next step → details → history
export default function Show({ quotation: q, history }) {
    const jo = q.job_order;
    const [asking, setAsking] = useState(null); // 'approve' | 'delete' | null
    const [busy, setBusy] = useState(false);

    function approve() {
        setBusy(true);
        router.post(`/quotations/${q.id}/approve`, {}, { onFinish: () => { setBusy(false); setAsking(null); } });
    }

    function remove() {
        setBusy(true);
        router.delete(`/quotations/${q.id}`, { onFinish: () => { setBusy(false); setAsking(null); } });
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
                    <a href={`/quotations/${q.id}/print`} target="_blank" rel="noreferrer" className="btn"><Icon name="printer" /> Print quotation</a>
                </div>
            </div>

            <div className="stack">
                <DocFlow q={q} here="quotation" />

                {/* the one thing to do next */}
                <section className="card">
                    <div className="card-head"><h2><Icon name="file-text" /> Quotation status</h2></div>
                    {q.editable ? (
                        <div className="next-step">
                            <span className="next-ic"><Icon name="clock" size={22} /></span>
                            <div className="next-text">
                                <b>Next step:</b> print this quotation for the customer. If they want changes, revise it. Once they agree, approve it to create the job order.
                            </div>
                            <div className="next-actions">
                                <Link href={`/quotations/${q.id}/edit`} className="btn" title="Change the lines, prices or details"><Icon name="pencil" size={16} /> Revise</Link>
                                <button type="button" className="btn btn-primary" onClick={() => setAsking('approve')}><Icon name="check" /> Approve → Job order</button>
                            </div>
                        </div>
                    ) : (
                        <div className="next-step closed">
                            <span className="next-ic"><Icon name="check-circle" size={22} /></span>
                            <div className="next-text"><b>Approved by the customer</b> on {q.approved}. The work continues on job order {jo?.jo_no}.</div>
                            {jo && (
                                <div className="next-actions">
                                    <Link href={`/job-orders/${jo.id}`} className="btn btn-primary"><Icon name="arrow-right" /> Open {jo.jo_no}</Link>
                                </div>
                            )}
                        </div>
                    )}
                </section>

                <QuotationDetails q={q} />
                <History items={history} />

                {q.editable && (
                    <div className="danger-zone">
                        <button type="button" className="btn btn-danger" onClick={() => setAsking('delete')}><Icon name="trash" size={16} /> Delete quotation</button>
                    </div>
                )}
            </div>

            <ConfirmDialog open={asking === 'approve'} tone="success" busy={busy}
                           title={`Customer approved ${q.quotation_no}?`}
                           message="It becomes a job order and can no longer be revised."
                           confirmLabel="Approve & create job order"
                           onConfirm={approve} onCancel={() => setAsking(null)} />

            <ConfirmDialog open={asking === 'delete'} tone="danger" busy={busy}
                           title={`Delete ${q.quotation_no}?`}
                           message="This quotation and its lines will be removed for good."
                           confirmLabel="Delete quotation"
                           onConfirm={remove} onCancel={() => setAsking(null)} />
        </AppLayout>
    );
}
