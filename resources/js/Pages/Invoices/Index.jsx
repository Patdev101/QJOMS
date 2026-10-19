import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../../Components/Icon';
import { peso } from '../../Components/format';
import AppLayout from '../../Layouts/AppLayout';

export function InvoiceStatus({ inv }) {
    return (
        <>
            <span className={`pill ${inv.status}`}>{inv.status_label}</span>
            {inv.overdue && <> <span className="pill overdue">Overdue</span></>}
        </>
    );
}

export default function Index({ invoices, counts, outstanding, filters }) {
    const [search, setSearch] = useState(filters.q);
    const chips = [['', 'All', counts.all], ['unpaid', 'Unpaid', counts.unpaid], ['partial', 'Partly paid', counts.partial],
        ['paid', 'Paid', counts.paid], ['overdue', 'Overdue', counts.overdue]];
    const query = (changes) => Object.fromEntries(Object.entries({ ...filters, ...changes }).filter(([, v]) => v));

    function runSearch(event) {
        event.preventDefault();
        router.get('/invoices', query({ q: search }), { preserveState: true, replace: true });
    }

    return (
        <AppLayout title="Invoices" active="invoices">
            <div className="page-head">
                <div>
                    <h1>Invoices</h1>
                    <p className="sub">Bills for completed job orders. Record payments here until each one is fully paid.</p>
                </div>
                <div className="text-right">
                    <div className="stat-label">Still to collect</div>
                    <div className="stat-value" style={{ color: 'var(--amber-ink)' }}>{peso(outstanding)}</div>
                </div>
            </div>

            <div className="card">
                <form className="toolbar" onSubmit={runSearch}>
                    <div className="chips">
                        {chips.map(([key, label, n]) => (
                            <Link key={key || 'all'} href="/invoices" data={query({ status: key })} preserveState
                                  className={`chip${filters.status === key ? ' active' : ''}`}>
                                {label} <span className="n">{n}</span>
                            </Link>
                        ))}
                    </div>
                    <label className="search">
                        <Icon name="search" size={17} />
                        <span className="sr-only">Search</span>
                        <input type="search" value={search} placeholder="Search invoice, JO, customer" onChange={(e) => setSearch(e.target.value)} />
                    </label>
                </form>

                {invoices.length === 0 ? (
                    <div className="empty">
                        <div className="empty-icon"><Icon name="receipt" size={26} /></div>
                        {filters.q || filters.status ? (
                            <>
                                <h3>No invoices found</h3>
                                <p>Try another search or filter.</p>
                                <Link href="/invoices" className="btn" onClick={() => setSearch('')}>Clear filters</Link>
                            </>
                        ) : (
                            <>
                                <h3>No invoices yet</h3>
                                <p>When a job order is completed, open it and click <b>Create invoice</b>.</p>
                            </>
                        )}
                    </div>
                ) : (
                    <div className="table-scroll">
                        <table className="table">
                            <thead>
                                <tr>
                                    <th>Invoice</th>
                                    <th>Customer &amp; project</th>
                                    <th>Due</th>
                                    <th className="num">Amount</th>
                                    <th className="num">Balance</th>
                                    <th>Status</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.map((inv) => (
                                    <tr key={inv.id} data-href onClick={(e) => (e.ctrlKey || e.metaKey ? window.open(`/invoices/${inv.id}`, '_blank') : router.visit(`/invoices/${inv.id}`))}>
                                        <td><span className="code">{inv.invoice_no}</span><div className="cell-sub">{inv.jo_no}</div></td>
                                        <td>
                                            <div className="cell-main">{inv.customer}</div>
                                            <div className="cell-sub">{inv.project || 'No project name'}</div>
                                        </td>
                                        <td>{inv.due_date}<div className="cell-sub">issued {inv.date}</div></td>
                                        <td className="num">{peso(inv.amount)}</td>
                                        <td className="num cell-main">{peso(Math.max(inv.balance, 0))}</td>
                                        <td><InvoiceStatus inv={inv} /></td>
                                        <td className="row-go"><Icon name="chevron-right" /></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
