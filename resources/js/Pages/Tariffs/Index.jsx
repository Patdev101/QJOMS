import { Link, router } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import ConfirmDialog from '../../Components/ConfirmDialog';
import Icon from '../../Components/Icon';
import Pager from '../../Components/Pager';
import { peso, plain } from '../../Components/format';
import AppLayout from '../../Layouts/AppLayout';

export const marginClass = (m) => (m >= 30 ? 'margin-good' : (m >= 10 ? 'margin-ok' : 'margin-low'));

const range = (values, fmt) => {
    const min = Math.min(...values);
    const max = Math.max(...values);
    return min === max ? fmt(min) : `${fmt(min)} – ${fmt(max)}`;
};

// UOM, cost, price and margin of a whole category or sub-category, lined up under the column headings
function SummaryCells({ items, addHref }) {
    const uoms = [...new Set(items.map((t) => t.uom))];
    const costed = items.filter((t) => t.cost > 0);
    const margins = costed.map((t) => t.margin).filter((m) => m !== null);

    return (
        <>
            <td className="sum-cell">{uoms.join(', ')}</td>
            <td className="num sum-cell">
                {costed.length === 0 ? <span className="muted">not set</span>
                    : <>{range(costed.map((t) => t.cost), plain)}{costed.length < items.length && <div className="cell-sub">{items.length - costed.length} not set</div>}</>}
            </td>
            <td className="num sum-cell">{range(items.map((t) => t.price), peso)}</td>
            <td className="num sum-cell">
                {/* nothing to show until costs are entered */}
                {margins.length > 0 && <span className={marginClass(Math.min(...margins))}>{range(margins, (m) => `${m.toFixed(0)}%`)}</span>}
            </td>
            <td>
                <div className="row-actions">
                    <Link href={addHref} className="btn btn-ghost btn-sm group-add" title="Add a new one in this group"><Icon name="plus" size={14} /> Add</Link>
                </div>
            </td>
        </>
    );
}

export default function Index({ tariffs, type, types, counts, categories, filters }) {
    const [search, setSearch] = useState(filters.q);
    const [deleting, setDeleting] = useState(null);
    const [busy, setBusy] = useState(false);
    const label = types[type];
    const plural = type === 'service' ? 'services' : 'items';

    // "＋" on a category / sub-category row: the add form opens with these filled in
    const addUrl = (category, subcategory) => `/tariffs/create?${new URLSearchParams({ type, ...(category ? { category } : {}), ...(subcategory ? { subcategory } : {}) })}`;

    // folder tree: category › sub-category › services (the rows already come sorted that way)
    const tree = [];
    tariffs.data.forEach((t) => {
        let cat = tree.find((c) => c.name === t.category);
        if (! cat) tree.push(cat = { key: `c:${t.category}`, name: t.category, count: 0, subs: [] });
        let sub = cat.subs.find((s) => s.name === t.subcategory);
        if (! sub) cat.subs.push(sub = { key: `s:${t.category}|${t.subcategory}`, name: t.subcategory, items: [] });
        sub.items.push(t);
        cat.count++;
    });

    // closed by default; searching or picking a category opens everything so the matches show
    const [open, setOpen] = useState(() => new Set());
    const [expandAll, setExpandAll] = useState(false);
    const expanded = expandAll || Boolean(filters.q || filters.category);
    const toggle = (key) => setOpen((prev) => {
        const next = new Set(prev);
        next.has(key) ? next.delete(key) : next.add(key);
        return next;
    });

    // under a sub-category heading, "Gritblasting SA 2.5 — Hull Area" only needs to say "Hull Area"
    const shortName = (t) => (t.subcategory && t.description.startsWith(t.subcategory + ' — ')
        ? t.description.slice(t.subcategory.length + 3)
        : (t.subcategory && t.description.includes(' — ') ? t.description.split(' — ').pop() : t.description));

    const visit = (changes) => {
        const params = { type, q: search, category: filters.category, ...changes };
        router.get('/tariffs', Object.fromEntries(Object.entries(params).filter(([, v]) => v)), { preserveState: true, replace: true });
    };

    function runSearch(event) {
        event.preventDefault();
        visit({});
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
                    <div className="toolbar-right">
                        {tree.length > 0 && ! (filters.q || filters.category) && (
                            <button type="button" className="btn btn-ghost btn-sm" onClick={() => { setExpandAll(! expandAll); setOpen(new Set()); }}>
                                <Icon name={expandAll ? 'chevron-right' : 'chevron-down'} size={15} /> {expandAll ? 'Collapse all' : 'Expand all'}
                            </button>
                        )}
                        {categories.length > 0 && (
                            <select className="category-filter" value={filters.category} aria-label="Category" onChange={(e) => visit({ category: e.target.value })}>
                                <option value="">All categories</option>
                                {categories.map((c) => <option key={c} value={c}>{c}</option>)}
                            </select>
                        )}
                        <label className="search">
                            <Icon name="search" size={17} />
                            <span className="sr-only">Search</span>
                            <input type="search" value={search} placeholder="Search code, description, category" onChange={(e) => setSearch(e.target.value)} />
                        </label>
                    </div>
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
                                    {tree.map((cat) => {
                                        const catOpen = expanded || open.has(cat.key);
                                        return (
                                        <Fragment key={cat.key}>
                                        <tr className="group-row" onClick={(e) => ! e.target.closest('a') && toggle(cat.key)} aria-expanded={catOpen}>
                                            <td colSpan={2}>
                                                <span className={`tree-chev${catOpen ? ' open' : ''}`}><Icon name="chevron-right" size={16} /></span>
                                                <Icon name="folder" size={15} /> {cat.name || 'No category'}
                                                <span className="tree-count">
                                                    {cat.subs.filter((s) => s.name).length > 0 && `${cat.subs.filter((s) => s.name).length} sub-categories · `}
                                                    {cat.count} {cat.count === 1 ? label.toLowerCase() : plural}
                                                </span>
                                            </td>
                                            <SummaryCells items={cat.subs.flatMap((s) => s.items)} addHref={addUrl(cat.name)} />
                                        </tr>
                                        {catOpen && cat.subs.map((sub) => {
                                            const subOpen = ! sub.name || expanded || open.has(sub.key);
                                            return (
                                            <Fragment key={sub.key}>
                                            {sub.name && (
                                                <tr className="subgroup-row" onClick={(e) => ! e.target.closest('a') && toggle(sub.key)} aria-expanded={subOpen}>
                                                    <td colSpan={2}>
                                                        <span className={`tree-chev${subOpen ? ' open' : ''}`}><Icon name="chevron-right" size={14} /></span>
                                                        {sub.name}
                                                        <span className="tree-count">{sub.items.map(shortName).join(' · ')}</span>
                                                    </td>
                                                    <SummaryCells items={sub.items} addHref={addUrl(cat.name, sub.name)} />
                                                </tr>
                                            )}
                                            {subOpen && sub.items.map((t) => (
                                        <tr key={t.id} data-href className={`${sub.name ? 'tree-item' : ''} ${t.active ? '' : 'is-inactive'}`}
                                            onClick={(e) => ! e.target.closest('a, button') && router.visit(`/tariffs/${t.id}/edit`)}>
                                            <td><span className="code">{t.code}</span></td>
                                            <td>
                                                <div className="cell-main">{shortName(t)}</div>
                                                {t.active
                                                    ? (t.unit && <div className="cell-sub">per {t.unit.toLowerCase()}</div>)
                                                    : <span className="pill inactive">Inactive</span>}
                                            </td>
                                            <td>{t.uom}</td>
                                            <td className="num muted">{plain(t.cost)}</td>
                                            <td className="num cell-main">{peso(t.price)}</td>
                                            <td className="num">
                                                {/* no cost entered yet: a margin would be a meaningless 100% */}
                                                {t.cost === 0 ? <span className="muted" style={{ fontSize: 12.5 }}>cost not set</span>
                                                    : (t.margin === null ? <span className="muted">—</span> : <span className={marginClass(t.margin)}>{t.margin.toFixed(1)}%</span>)}
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
                                            </Fragment>
                                            );
                                        })}
                                        </Fragment>
                                        );
                                    })}
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
