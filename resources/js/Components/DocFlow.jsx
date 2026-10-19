import { Link } from '@inertiajs/react';
import Icon from './Icon';

/**
 * The same strip on the quotation, job order and invoice pages:
 *   Quotation → Job order → Invoice → Paid
 * each step shows its number and status, links to its page, and "here" marks the page you're on.
 * `q` is the quotation details; `here` is 'quotation' | 'job_order' | 'invoice'.
 */
export default function DocFlow({ q, here }) {
    const jo = q.job_order;
    const inv = q.invoice;
    const paid = inv?.status === 'paid';

    const steps = [
        {
            key: 'quotation', label: 'Quotation', no: q.quotation_no, href: `/quotations/${q.id}`,
            sub: jo ? `Approved ${q.approved}` : 'Waiting for approval',
            state: jo ? 'done' : 'current',
        },
        {
            key: 'job_order', label: 'Job order', no: jo?.jo_no, href: jo && `/job-orders/${jo.id}`,
            sub: jo ? jo.status_label : 'After approval',
            state: jo ? (jo.status === 'billed' ? 'done' : 'current') : '',
        },
        {
            key: 'invoice', label: 'Invoice', no: inv?.invoice_no, href: inv && `/invoices/${inv.id}`,
            sub: inv ? inv.status_label : 'After the work is done',
            state: inv ? (paid ? 'done' : 'current') : '',
        },
        {
            key: 'paid', label: 'Paid', no: null, href: null,
            sub: paid ? inv.paid_on : '',
            state: paid ? 'done' : '',
        },
    ];

    return (
        <nav className="card docflow" aria-label="Document flow">
            {steps.map((step, i) => {
                const body = (
                    <>
                        <span className="step-dot">{step.state === 'done' ? <Icon name="check" size={16} /> : i + 1}</span>
                        <span className="step-text">
                            <span className="step-label">{step.label}{step.no && <span className="docflow-no">{step.no}</span>}</span>
                            <span className="step-sub">
                                {step.sub}
                                {step.key === here && <span className="docflow-here">This page</span>}
                            </span>
                        </span>
                    </>
                );
                const cls = `step ${step.state}${step.key === here ? ' here' : ''}`;

                return step.href && step.key !== here
                    ? <Link key={step.key} href={step.href} className={`${cls} docflow-link`}>{body}</Link>
                    : <div key={step.key} className={cls} aria-current={step.key === here ? 'page' : undefined}>{body}</div>;
            })}
        </nav>
    );
}
