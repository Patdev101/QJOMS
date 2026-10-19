import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '../../Components/ConfirmDialog';
import Icon from '../../Components/Icon';
import Pager from '../../Components/Pager';
import { peso, plain } from '../../Components/format';
import AppLayout from '../../Layouts/AppLayout';

export const marginClass = (m) => (m >= 30 ? 'margin-good' : (m >= 10 ? 'margin-ok' : 'margin-low'));

export default function Index({ tariffs, type, types, counts, filters }) {
    const [search, setSearch] = useState(filters.q);
    const [deleting, setDeleting] = useState(null);
    const [busy, setBusy] = useState(false);
    const label = types[type];
    const plural = type === 'service' ? 'services' : 'items';

    function runSearch(event) {
        event.preventDefault();
        router.get('/tariffs', { type, ...(search ? { q: search } : {}) }, { preserveState: true, replace: true });
    }

    function remove() {
        setBusy(true);
        router.delete(`/tariffs/${deleting.id}`, { preserveScroll: true, onFinish: () => { setBusy(false); setDeleting(null); } });
    }

    return (
        <AppLayout title={`${label} tariff`} active={`tariff-${type}`}>
            <div className="page-head">
                <div>
                    <h1>{label} tariff</h1>
                    <p className="sub">Standard {plural} with cost and selling price. The price fills in automatically on quotations.</p>
                </div>
                <Link href={`/tariffs/create?type=${type}`} className="btn btn-primary"><Icon name="plus" /> Add {label.toLowerCase()}</Link>
            </div>

            <div className="card">
                <form className="toolbar" onSubmit={runSearch}>
                    <div className="segmented">
                        <Link href="/tariffs?type=service" className={type === 'service' ? 'active' : ''} onClick={() => setSearch('')}>
                            <Icon name="wrench" size={16} /> Services <span className="n">{counts.service}</span>
                        </Link>
                        <Link href="/tariffs?type=item" className={type === 'item' ? 'active' : ''} onClick={() => setSearch('')}>
                            <Icon name="package" size={16} /> Items <span className="n">{counts.item}</span>
                        </Link>
                    </div>
                    <label className="search">
                        <Icon name="search" size={17} />
                        <span className="sr-only">Search</span>
                        <input type="search" value={search} placeholder="Search code or description" onChange={(e) => setSearch(e.target.value)} />
                    </label>
                </form>

                {tariffs.data.length === 0 ? (
                    <div className="empty">
                        <div className="empty-icon"><Icon name={type === 'service' ? 'wrench' : 'package'} size={26} /></div>
                        {filters.q ? (
                            <>
                                <h3>No {plural} found</h3>
                                <p>Nothing matches "{filters.q}".</p>
                                <Link href={`/tariffs?type=${type}`} className="btn" onClick={() => setSearch('')}>Clear search</Link>
                            </>
                        ) : (
                            <>
                                <h3>No {plural} yet</h3>
                                <p>Add your standard {plural} so they can be picked on quotations.</p>
                                <Link href={`/tariffs/create?type=${type}`} className="btn btn-primary"><Icon name="plus" /> Add {label.toLowerCase()}</Link>
                            </>
                        )}
                    </div>
                ) : (
                    <>
                        <div className="table-scroll">
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Description</th>
                                        <th>UOM</th>
                                        <th className="num">Cost</th>
                                        <th className="num">Price</th>
                                        <th className="num">Margin</th>
                                        <th />
                                    </tr>
                                </thead>
                                <tbody>
                                    {tariffs.data.map((t) => (
                                        <tr key={t.id} data-href className={t.active ? '' : 'is-inactive'}
                                            onClick={(e) => ! e.target.closest('a, button') && router.visit(`/tariffs/${t.id}/edit`)}>
                                            <td><span className="code">{t.code}</span></td>
                                            <td>
                                                <div className="cell-main">{t.description}</div>
                                                {t.active
                                                    ? (t.unit && <div className="cell-sub">per {t.unit.toLowerCase()}</div>)
                                                    : <span className="pill inactive">Inactive</span>}
                                            </td>
                                            <td>{t.uom}</td>
                                            <td className="num muted">{plain(t.cost)}</td>
                                            <td className="num cell-main">{peso(t.price)}</td>
                                            <td className="num">
                                                {t.margin === null ? <span className="muted">—</span> : <span className={marginClass(t.margin)}>{t.margin.toFixed(1)}%</span>}
                                            </td>
                                            <td>
                                                <div className="row-actions">
                                                    <Link href={`/tariffs/${t.id}/edit`} className="btn btn-ghost btn-icon" title="Edit" aria-label={`Edit ${t.code}`}><Icon name="pencil" size={16} /></Link>
                                                    <button type="button" className="btn btn-ghost btn-icon btn-danger" title="Delete" aria-label={`Delete ${t.code}`}
                                                            onClick={() => setDeleting(t)}><Icon name="trash" size={16} /></button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pager page={tariffs} />
                    </>
                )}
            </div>

            <ConfirmDialog open={Boolean(deleting)} tone="danger" busy={busy}
                           title={`Delete ${label.toLowerCase()}?`}
                           message={`${deleting?.code} — ${deleting?.description} will be deleted. Quotations that already use it keep their copy.`}
                           confirmLabel="Delete" onConfirm={remove} onCancel={() => setDeleting(null)} />
        </AppLayout>
    );
}
