import { Link, useForm } from '@inertiajs/react';
import Icon from '../../Components/Icon';
import AppLayout from '../../Layouts/AppLayout';

// add a customer, or edit one when `customer` is given
export default function Form({ customer }) {
    const form = useForm(customer ?? { name: '', address: '', contact_person: '', contact_no: '', discount_percent: '0' });
    const { data, setData, errors, processing } = form;
    const messages = [...new Set(Object.values(errors))];
    const field = (name) => ({ id: name, value: data[name], className: errors[name] ? 'is-invalid' : '', onChange: (e) => setData(name, e.target.value) });

    function submit(event) {
        event.preventDefault();
        customer ? form.put(`/customers/${customer.id}`) : form.post('/customers');
    }

    return (
        <AppLayout title={customer ? 'Edit customer' : 'Add customer'} active="customers">
            <div className="page-head">
                <div>
                    <div className="eyebrow"><Link href="/customers"><Icon name="chevron-left" size={14} /> Customer profiles</Link></div>
                    <h1>{customer ? customer.name : 'Add customer'}</h1>
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
                    <div><h3>Customer</h3><p>The company or person you send quotations to.</p></div>
                    <div>
                        <div className="field">
                            <label htmlFor="name">Customer name</label>
                            <input {...field('name')} autoFocus />
                        </div>
                        <div className="field field-gap">
                            <label htmlFor="address">Address</label>
                            <input {...field('address')} />
                        </div>
                    </div>
                </div>

                <div className="form-section">
                    <div><h3>Contact</h3><p>Filled in on new quotations. You can still change it per quotation.</p></div>
                    <div className="grid-2">
                        <div className="field">
                            <label htmlFor="contact_person">Contact person</label>
                            <input {...field('contact_person')} />
                        </div>
                        <div className="field">
                            <label htmlFor="contact_no">Contact no.</label>
                            <input {...field('contact_no')} placeholder="0917 123 4567" />
                        </div>
                    </div>
                </div>

                <div className="form-section">
                    <div><h3>Discount</h3><p>Applied automatically to this customer's quotations.</p></div>
                    <div>
                        <div className="field" style={{ maxWidth: 200 }}>
                            <label htmlFor="discount_percent">Discount</label>
                            <span className="affix has-suf">
                                <input {...field('discount_percent')} type="number" min="0" max="100" step="0.01" />
                                <span className="suf">%</span>
                            </span>
                        </div>
                        <span className="hint">Use 0 for no discount. Staff can still change it on a quotation before it's approved.</span>
                    </div>
                </div>

                <div className="form-foot">
                    <Link href="/customers" className="btn btn-ghost">Cancel</Link>
                    <button type="submit" className="btn btn-primary" disabled={processing}>
                        <Icon name="check" /> {processing ? 'Saving…' : (customer ? 'Save changes' : 'Add customer')}
                    </button>
                </div>
            </form>
        </AppLayout>
    );
}
