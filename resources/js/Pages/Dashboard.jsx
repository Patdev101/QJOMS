import { Link, usePage } from '@inertiajs/react';
import Icon from '../Components/Icon';
import { peso } from '../Components/format';
import AppLayout from '../Layouts/AppLayout';

function Stat({ icon, tone, label, value, foot, href }) {
    const body = (
        <>
            <span className={`stat-icon icon-${tone}`}><Icon name={icon} size={20} /></span>
            <div className="stat-label">{label}</div>
            <div className="stat-value">{value}</div>
            <div className="stat-foot">{foot}</div>
        </>
    );

    return href ? <Link href={href} className="card stat">{body}</Link> : <div className="card stat">{body}</div>;
}

function Empty({ icon, title, text }) {
    return (
        <div className="empty compact">
            <div className="empty-icon"><Icon name={icon} size={24} /></div>
            <h3>{title}</h3>
            <p>{text}</p>
        </div>
    );
}

export default function Dashboard({ greeting, month, stats, waiting, jobs }) {
    const { today } = usePage().props;

    return (
        <AppLayout title="Dashboard" active="dashboard">
            <div className="page-head">
                <div>
                    <div className="eyebrow">{today}</div>
                    <h1>{greeting}!</h1>
                    <p className="sub">Here's what needs your attention today.</p>
                </div>
            </div>

            <div className="stats">
                <Stat icon="clock" tone="amber" label="Waiting for approval" value={stats.pending_count} foot={`${peso(stats.pending_value)} quoted`} href="/quotations?status=pending" />
                <Stat icon="clipboard" tone="pine" label="Job orders in progress" value={stats.active_jobs} foot="Open and ongoing work" href="/job-orders?status=active" />
                <Stat icon="receipt" tone="clay" label="Ready for billing" value={stats.to_bill_count} foot={`${peso(stats.to_bill_value)} to bill`} href="/job-orders?status=completed" />
                <Stat icon="trending" tone="ink" label={`Approved in ${month}`} value={peso(stats.month_value).replace(/\.\d\d$/, '')}
                      foot={`${stats.month_count} ${stats.month_count === 1 ? 'quotation' : 'quotations'} approved`} />
            </div>

            <div className="dash-grid">
                <section className="card">
                    <div className="card-head">
                        <h2><Icon name="clock" /> Waiting for customer approval</h2>
                        <Link href="/quotations?status=pending" className="link">View all</Link>
                    </div>
                    {waiting.length ? waiting.map((q) => (
                        <Link key={q.id} href={`/quotations/${q.id}`} className="list-row">
                            <div>
                                <div className="cell-main">{q.customer}</div>
                                <div className="cell-sub"><span className="code">{q.quotation_no}</span> {q.project}</div>
                            </div>
                            <div className="text-right">
                                <div className="cell-main num">{peso(q.grand_total)}</div>
                                <div className="cell-sub">sent {q.sent}</div>
                            </div>
                        </Link>
                    )) : <Empty icon="check-circle" title="All caught up" text="No quotations are waiting for the customer." />}
                </section>

                <section className="card">
                    <div className="card-head">
                        <h2><Icon name="clipboard" /> Job orders to work on</h2>
                        <Link href="/job-orders" className="link">View all</Link>
                    </div>
                    {jobs.length ? jobs.map((jo) => (
                        <Link key={jo.id} href={`/job-orders/${jo.id}`} className="list-row">
                            <div>
                                <div className="cell-main">{jo.customer}</div>
                                <div className="cell-sub"><span className="code">{jo.jo_no}</span> {jo.project}</div>
                            </div>
                            <span className={`pill ${jo.status}`}>{jo.status_label}</span>
                        </Link>
                    )) : <Empty icon="clipboard" title="No active job orders" text="Approved quotations show up here as job orders." />}
                </section>
            </div>
        </AppLayout>
    );
}
