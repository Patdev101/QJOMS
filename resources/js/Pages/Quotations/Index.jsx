import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../../Components/Icon';
import { peso } from '../../Components/format';
import AppLayout from '../../Layouts/AppLayout';

export default function Index({ quotations, counts, filters }) {
    const [search, setSearch] = useState(filters.q);
    const chips = [['', 'All', counts.all], ['pending', 'For approval', counts.pending], ['approved', 'Approved', counts.approved]];
    const query = (changes) => Object.fromEntries(Object.entries({ ...filters, ...changes }).filter(([, v]) => v));

    function runSearch(event) {
        event.preventDefault();
        router.get('/quotations', query({ q: search }), { preserveState: true, replace: true });
    }

    // a click anywhere on a row opens it (Ctrl+click: new tab)
    function openRow(event, id) {
        if (event.target.closest('a, button')) return;
        event.ctrlKey || event.metaKey ? window.open(`/quotations/${id}`, '_blank') : router.visit(`/quotations/${id}`);
    }

    const filtered = filters.q || filters.status;

    return (
        <AppLayout title="Quotations" active="quotations">
            <div className="page-head">
                <div>
                    <h1>Quotations</h1>
                    <p className="sub">Every quotation you've made. Open one to see its details or print it for the customer.</p>
                </div>
            </div>

            <div className="card">
                <form className="toolbar" onSubmit={runSearch}>
                    <div className="chips">
                        {chips.map(([key, label, n]) => (
                            <Link key={key || 'all'} href="/quotations" data={query({ status: key })} preserveState
                                  className={`chip${filters.status === key ? ' active' : ''}`}>
                                {label} <span className="n">{n}</span>
                            </Link>
                        ))}
                    </div>
                    <label className="search">
                        <Icon name="search" size={17} />
                        <span className="sr-only">Search</span>
                        <input type="search" value={search} placeholder="Search no., customer or project"
                               onChange={(e) => setSearch(e.target.value)} />
                    </label>
                </form>

                {quotations.data.length === 0 ? (
                    <div className="empty">
                        <div className="empty-icon"><Icon name="file-text" size={26} /></div>
                        {filtered ? (
                            <>
                                <h3>No quotations found</h3>
                                <p>Try another search or filter.</p>
                                <Link href="/quotations" className="btn" onClick={() => setSearch('')}>Clear filters</Link>
                            </>
                        ) : (
                            <>
                                <h3>No quotations yet</h3>
                                <p>Use the New quotation button at the top to make your first one.</p>
                            </>
                        )}
                    </div>
                ) : (
                    <>
                        <div className="table-scroll">
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Quotation</th>
                                        <th>Customer &amp; project</th>
                                        <th>Date</th>
                                        <th className="num">Grand total</th>
                                        <th>Status</th>
                                        <th />
                                    </tr>
                                </thead>
                                <tbody>
                                    {quotations.data.map((q) => (
                                        <tr key={q.id} data-href onClick={(e) => openRow(e, q.id)}>
                                            <td><span className="code">{q.quotation_no}</span></td>
                                            <td>
                                                <div className="cell-main">{q.customer}</div>
                                                <div className="cell-sub">{q.project || 'No project name'}</div>
                                            </td>
                                            <td>{q.date}<div className="cell-sub">{q.age}</div></td>
                                            <td className="num cell-main">{peso(q.grand_total)}</td>
                                            <td>
                                                {q.jo_no ? (
                                                    <><span className="pill approved">Approved</span><div className="cell-sub">→ {q.jo_no}</div></>
                                                ) : <span className="pill pending">For approval</span>}
                                            </td>
                                            <td className="row-go"><Icon name="chevron-right" /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="pager">
                            <span>Showing {quotations.from}–{quotations.to} of {quotations.total}</span>
                            {quotations.last_page > 1 && (
                                <ul className="pagination">
                                    {quotations.links.map((link, i) => (
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
