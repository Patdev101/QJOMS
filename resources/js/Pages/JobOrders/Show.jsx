import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '../../Components/ConfirmDialog';
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

export default function Show({ jobOrder: jo, statuses, quotation: q }) {
    const keys = Object.keys(statuses);
    const currentIndex = keys.indexOf(jo.status);
    const [askBilled, setAskBilled] = useState(false);
    const [busy, setBusy] = useState(false);

    function setStatus(status) {
        setBusy(true);
        router.patch(`/job-orders/${jo.id}/status`, { status }, {
            preserveScroll: true,
            onFinish: () => { setBusy(false); setAskBilled(false); },
        });
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
                <section className="card">
                    <div className="card-head">
                        <h2><Icon name="clipboard" /> Job status</h2>
                        <span className="muted" style={{ fontSize: 13 }}>Click a step to update it</span>
                    </div>
                    <div className="track">
                        {keys.map((key, i) => {
                            const state = i < currentIndex ? 'done' : (key === jo.status ? 'current' : '');
                            return (
                                <button key={key} type="button" className={`track-step ${state}`} disabled={key === jo.status || busy}
                                        aria-current={state === 'current' ? 'step' : undefined}
                                        onClick={() => (key === 'billed' ? setAskBilled(true) : setStatus(key))}>
                                    <span className="step-dot">{state === 'done' ? <Icon name="check" size={15} /> : i + 1}</span>
                                    <b>{statuses[key]}</b>
                                    <small>{explain[key]}</small>
                                </button>
                            );
                        })}
                    </div>
                </section>

                <QuotationDetails q={q} />
            </div>

            <ConfirmDialog open={askBilled} busy={busy}
                           title={`Mark ${jo.jo_no} as billed?`}
                           message={`Do this once the customer has been billed for ${peso(q.grand_total)}.`}
                           confirmLabel="Mark as billed"
                           onConfirm={() => setStatus('billed')} onCancel={() => setAskBilled(false)} />
        </AppLayout>
    );
}
