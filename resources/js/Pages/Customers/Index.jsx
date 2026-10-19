import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '../../Components/ConfirmDialog';
import Icon from '../../Components/Icon';
import Pager from '../../Components/Pager';
import AppLayout from '../../Layouts/AppLayout';

export default function Index({ customers, filters }) {
    const [search, setSearch] = useState(filters.q);
    const [deleting, setDeleting] = useState(null);
    const [busy, setBusy] = useState(false);

    function runSearch(event) {
        event.preventDefault();
        router.get('/customers', search ? { q: search } : {}, { preserveState: true, replace: true });
    }

    function remove() {
        setBusy(true);
        router.delete(`/customers/${deleting.id}`, { preserveScroll: true, onFinish: () => { setBusy(false); setDeleting(null); } });
    }

    return (
        <AppLayout title="Customer profiles" active="customers">
            <div className="page-head">
                <div>
                    <h1>Customer profiles</h1>
                    <p className="sub">A customer's discount is filled in automatically on their quotations.</p>
                </div>
                <Link href="/customers/create" className="btn btn-primary"><Icon name="plus" /> Add customer</Link>
            </div>

            <div className="card">
                <form className="toolbar" onSubmit={runSearch}>
                    <label className="search">
                        <Icon name="search" size={17} />
                        <span className="sr-only">Search</span>
                        <input type="search" value={search} placeholder="Search name or contact person" onChange={(e) => setSearch(e.target.value)} />
                    </label>
                    <span className="muted">{customers.total} {customers.total === 1 ? 'customer' : 'customers'}</span>
                </form>

                {customers.data.length === 0 ? (
                    <div className="empty">
                        <div className="empty-icon"><Icon name="users" size={26} /></div>
                        {filters.q ? (
                            <>
                                <h3>No customers found</h3>
                                <p>Nobody matches "{filters.q}".</p>
                                <Link href="/customers" className="btn" onClick={() => setSearch('')}>Clear search</Link>
                            </>
                        ) : (
                            <>
                                <h3>No customers yet</h3>
                                <p>Add your customers once, then just pick them on every quotation.</p>
                                <Link href="/customers/create" className="btn btn-primary"><Icon name="plus" /> Add customer</Link>
                            </>
                        )}
                    </div>
                ) : (
                    <>
                        <div className="table-scroll">
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Contact</th>
                                        <th>Discount</th>
                                        <th className="num">Quotations</th>
                                        <th />
                                    </tr>
                                </thead>
                                <tbody>
                                    {customers.data.map((c) => (
                                        <tr key={c.id} data-href onClick={(e) => ! e.target.closest('a, button') && router.visit(`/customers/${c.id}/edit`)}>
                                            <td>
                                                <div className="who">
                                                    <span className="avatar" style={{ background: c.color }}>{c.initials}</span>
                                                    <div>
                                                        <div className="cell-main">{c.name}</div>
                                                        <div className="cell-sub">{c.address || 'No address'}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>{c.contact_person || '—'}<div className="cell-sub">{c.contact_no}</div></td>
                                            <td>
                                                {c.discount_percent > 0
                                                    ? <span className="tag discount"><Icon name="tag" size={13} /> {c.discount_percent}% off</span>
                                                    : <span className="tag none">No discount</span>}
                                            </td>
                                            <td className="num">{c.quotations_count}</td>
                                            <td>
                                                <div className="row-actions">
                                                    <Link href={`/customers/${c.id}/edit`} className="btn btn-ghost btn-icon" title="Edit" aria-label={`Edit ${c.name}`}><Icon name="pencil" size={16} /></Link>
                                                    <button type="button" className="btn btn-ghost btn-icon btn-danger" aria-label={`Delete ${c.name}`}
                                                            disabled={c.quotations_count > 0}
                                                            title={c.quotations_count > 0 ? "Has quotations, so it can't be deleted" : 'Delete'}
                                                            onClick={() => setDeleting(c)}><Icon name="trash" size={16} /></button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pager page={customers} />
                    </>
                )}
            </div>

            <ConfirmDialog open={Boolean(deleting)} tone="danger" busy={busy}
                           title="Delete customer?" message={`${deleting?.name} will be removed from your customer list.`}
                           confirmLabel="Delete" onConfirm={remove} onCancel={() => setDeleting(null)} />
        </AppLayout>
    );
}
