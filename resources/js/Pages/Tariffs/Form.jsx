import { Link, useForm } from '@inertiajs/react';
import Icon from '../../Components/Icon';
import { peso } from '../../Components/format';
import AppLayout from '../../Layouts/AppLayout';
import { marginClass } from './Index';

// add a service/item, or edit one when `tariff` is given
export default function Form({ tariff, type, groups = {}, preset = {} }) {
    const form = useForm(tariff ?? {
        type, category: preset.category ?? '', subcategory: preset.subcategory ?? '',
        code: '', description: '', uom: '', uom2: '', unit: '', cost: '0', price: '0', active: true,
    });
    const { data, setData, errors, processing } = form;
    const messages = [...new Set(Object.values(errors))];
    const field = (name) => ({ id: name, value: data[name], className: errors[name] ? 'is-invalid' : '', onChange: (e) => setData(name, e.target.value) });
    const listUrl = `/tariffs?type=${tariff?.type ?? type}`;

    // live profit and margin while typing the cost and price
    const cost = Number(data.cost) || 0;
    const price = Number(data.price) || 0;
    const margin = price > 0 ? (price - cost) / price * 100 : null;

    function submit(event) {
        event.preventDefault();
        tariff ? form.put(`/tariffs/${tariff.id}`) : form.post('/tariffs');
    }

    return (
        <AppLayout title={tariff ? `Edit ${tariff.code}` : 'Add tariff'} active={`tariff-${data.type}`}>
            <div className="page-head">
                <div>
                    <div className="eyebrow"><Link href={listUrl}><Icon name="chevron-left" size={14} /> Tariff · {(tariff?.type ?? type) === 'item' ? 'Items' : 'Services'}</Link></div>
                    <h1>{tariff ? `Edit ${tariff.code}` : 'Add tariff'}</h1>
                </div>
            </div>

            {messages.length > 0 && (
                <div className="alert alert-error" role="alert">
                    <Icon name="alert" />
                    <div><b>Please check the following:</b><ul>{messages.map((m) => <li key={m}>{m}</li>)}</ul></div>
                </div>
            )}

            <form className="card form-page" onSubmit={submit} noValidate>
                <div className="form-section">
                    <div><h3>What is it?</h3><p>Services are work you do. Items are materials or parts you supply.</p></div>
                    <div>
                        <span className="label">Type</span>
                        <div className="segmented">
                            {[['service', 'wrench', 'Service'], ['item', 'package', 'Item']].map(([value, icon, text]) => (
                                <label key={value}>
                                    <input type="radio" name="type" value={value} checked={data.type === value} onChange={() => setData('type', value)} />
                                    <Icon name={icon} size={16} /> {text}
                                </label>
                            ))}
                        </div>

                        <div className="grid-2 field-gap">
                            {/* suggestions come from categories already in use, so spelling stays the same */}
                            <div className="field">
                                <label htmlFor="category">Category</label>
                                <input {...field('category')} list="categoryList" placeholder="e.g. Gritblasting" />
                                <datalist id="categoryList">{Object.keys(groups).map((c) => <option key={c} value={c} />)}</datalist>
                            </div>
                            <div className="field">
                                <label htmlFor="subcategory">Sub-category</label>
                                <input {...field('subcategory')} list="subcategoryList" placeholder="e.g. High pressure washing" />
                                <datalist id="subcategoryList">{(groups[data.category] ?? []).map((s) => <option key={s} value={s} />)}</datalist>
                            </div>
                            <div className="field">
                                <label htmlFor="code">Code</label>
                                <input {...field('code')} className={`mono ${errors.code ? 'is-invalid' : ''}`} placeholder="e.g. SVC-004" autoFocus />
                            </div>
                            <div className="field">
                                <label htmlFor="uom">UOM</label>
                                <input {...field('uom')} placeholder="hr, pc, sqm, lot" />
                            </div>
                            <div className="field">
                                <label htmlFor="uom2">UOM 2 <span className="muted">(optional)</span></label>
                                <input {...field('uom2')} placeholder="e.g. Day" />
                                <span className="hint">For units in two parts like Pax / Day: on a quotation both quantities are multiplied.</span>
                            </div>
                            <div className="field">
                                <label htmlFor="unit">Unit name</label>
                                <input {...field('unit')} placeholder="e.g. Hour, Piece, Square meter" />
                                <span className="hint">The full name of the unit of measure.</span>
                            </div>
                            <div className="field span-2">
                                <label htmlFor="description">Description</label>
                                <input {...field('description')} />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="form-section">
                    <div><h3>Pricing</h3><p>Cost is for staff only. It never appears on a printed quotation.</p></div>
                    <div>
                        <div className="grid-2">
                            <div className="field">
                                <label htmlFor="cost">Cost</label>
                                <span className="affix has-pre"><span className="pre">₱</span><input {...field('cost')} type="number" min="0" step="0.01" /></span>
                            </div>
                            <div className="field">
                                <label htmlFor="price">Selling price</label>
                                <span className="affix has-pre"><span className="pre">₱</span><input {...field('price')} type="number" min="0" step="0.01" /></span>
                            </div>
                        </div>
                        <div className="margin-preview">
                            <span>Profit per unit <b className={price - cost < 0 ? 'neg' : ''}>{peso(price - cost)}</b></span>
                            <span>Margin <b className={margin === null ? '' : marginClass(margin)}>{margin === null ? '—' : `${margin.toFixed(1)}%`}</b></span>
                        </div>
                    </div>
                </div>

                <div className="form-section">
                    <div><h3>Availability</h3><p>Inactive ones stay on old quotations but can't be picked on new ones.</p></div>
                    <div>
                        <label className="switch">
                            <input type="checkbox" checked={data.active} onChange={(e) => setData('active', e.target.checked)} />
                            Active: can be used on new quotations
                        </label>
                    </div>
                </div>

                <div className="form-foot">
                    <Link href={listUrl} className="btn btn-ghost">Cancel</Link>
                    <button type="submit" className="btn btn-primary" disabled={processing}>
                        <Icon name="check" /> {processing ? 'Saving…' : (tariff ? 'Save changes' : 'Add to tariff')}
                    </button>
                </div>
            </form>
        </AppLayout>
    );
}
