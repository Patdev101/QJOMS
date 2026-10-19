import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../../Components/Icon';
import { peso } from '../../Components/format';
import AppLayout from '../../Layouts/AppLayout';

export default function Index({ jobOrders, counts, filters }) {
    const [search, setSearch] = useState(filters.q);
    const chips = [['', 'All', counts.all], ['active', 'In progress', counts.active], ['completed', 'Ready for billing', counts.completed], ['billed', 'Billed', counts.billed]];
    const query = (changes) => Object.fromEntries(Object.entries({ ...filters, ...changes }).filter(([, v]) => v));

    function runSearch(event) {
        event.preventDefault();
        router.get('/job-orders', query({ q: search }), { preserveState: true, replace: true });
    }

    function openRow(event, id) {
        if (event.target.closest('a, button')) return;
        event.ctrlKey || event.metaKey ? window.open(`/job-orders/${id}`, '_blank') : router.visit(`/job-orders/${id}`);
    }

    return (
        <AppLayout title="Job orders" active="job-orders">
            <div className="page-head">
                <div>
                    <h1>Job orders</h1>
                    <p className="sub">Every approved quotation becomes a job order. Track the work here until it's billed.</p>
                </div>
            </div>

            <div className="card">
                <form className="toolbar" onSubmit={runSearch}>
                    <div className="chips">
                        {chips.map(([key, label, n]) => (
                            <Link key={key || 'all'} href="/job-orders" data={query({ status: key })} preserveState
                                  className={`chip${filters.status === key ? ' active' : ''}`}>
                                {label} <span className="n">{n}</span>
                            </Link>
                        ))}
                    </div>
                    <label className="search">
                        <Icon name="search" size={17} />
                        <span className="sr-only">Search</span>
                        <input type="search" value={search} placeholder="Search JO no., customer, project" onChange={(e) => setSearch(e.target.value)} />
                    </label>
                </form>

                {jobOrders.data.length === 0 ? (
                    <div className="empty">
                        <div className="empty-icon"><Icon name="clipboard" size={26} /></div>
                        {filters.q || filters.status ? (
                            <>
                                <h3>No job orders found</h3>
                                <p>Try another search or filter.</p>
                                <Link href="/job-orders" className="btn" onClick={() => setSearch('')}>Clear filters</Link>
                            </>
                        ) : (
                            <>
                                <h3>No job orders yet</h3>
                                <p>Approve a quotation once the customer agrees, and it will appear here.</p>
                            </>
                        )}
                    </div>
                ) : (
                    <>
                        <div className="table-scroll">
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Job order</th>
                                        <th>Customer &amp; project</th>
                                        <th>Quotation</th>
                                        <th>Date</th>
                                        <th className="num">Amount</th>
                                        <th>Status</th>
                                        <th />
                                    </tr>
                                </thead>
                                <tbody>
                                    {jobOrders.data.map((jo) => (
                                        <tr key={jo.id} data-href onClick={(e) => openRow(e, jo.id)}>
                                            <td><span className="code">{jo.jo_no}</span></td>
                                            <td>
                                                <div className="cell-main">{jo.customer}</div>
                                                <div className="cell-sub">{jo.project || 'No project name'}</div>
                                            </td>
                                            <td>{jo.quotation_no}</td>
                                            <td>{jo.date}</td>
                                            <td className="num cell-main">{peso(jo.grand_total)}</td>
                                            <td><span className={`pill ${jo.status}`}>{jo.status_label}</span></td>
                                            <td className="row-go"><Icon name="chevron-right" /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="pager">
                            <span>Showing {jobOrders.from}–{jobOrders.to} of {jobOrders.total}</span>
                            {jobOrders.last_page > 1 && (
                                <ul className="pagination">
                                    {jobOrders.links.map((link, i) => (
                                        <li key={i} className={`page-item${link.active ? ' active' : ''}${link.url ? '' : ' disabled'}`}>
                                            {link.url
                                                ? <Link className="page-link" href={link.url} preserveScroll dangerouslySetInnerHTML={{ __html: link.label }} />
                                                : <span className="page-link" dangerouslySetInnerHTML={{ __html: link.label }} />}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
