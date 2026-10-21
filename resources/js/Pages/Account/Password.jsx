import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../../Components/Icon';
import AppLayout from '../../Layouts/AppLayout';

// the signed-in user changes their own password
export default function Password() {
    const form = useForm({ current_password: '', password: '', password_confirmation: '' });
    const { data, setData, errors, processing } = form;
    const [show, setShow] = useState(false);
    const messages = [...new Set(Object.values(errors))];
    const field = (name) => ({
        id: name, value: data[name], type: show ? 'text' : 'password', autoComplete: name === 'current_password' ? 'current-password' : 'new-password',
        className: errors[name] ? 'is-invalid' : '', onChange: (e) => setData(name, e.target.value),
    });

    function submit(event) {
        event.preventDefault();
        form.put('/account/password', { onFinish: () => form.reset() });
    }

    return (
        <AppLayout title="Change password">
            <div className="page-head">
                <div>
                    <div className="eyebrow"><Link href="/"><Icon name="chevron-left" size={14} /> Dashboard</Link></div>
                    <h1>Change password</h1>
                    <p className="sub">Use at least 8 characters. You stay signed in on this computer.</p>
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
                    <div><h3>Current password</h3><p>To make sure it is really you.</p></div>
                    <div className="field" style={{ maxWidth: 360 }}>
                        <label htmlFor="current_password">Current password</label>
                        <input {...field('current_password')} autoFocus />
                    </div>
                </div>
                <div className="form-section">
                    <div><h3>New password</h3><p>Type it twice so a typing mistake is caught.</p></div>
                    <div className="grid-2">
                        <div className="field">
                            <label htmlFor="password">New password</label>
                            <input {...field('password')} />
                        </div>
                        <div className="field">
                            <label htmlFor="password_confirmation">Repeat new password</label>
                            <input {...field('password_confirmation')} />
                        </div>
                        <label className="switch span-2">
                            <input type="checkbox" checked={show} onChange={(e) => setShow(e.target.checked)} /> Show passwords
                        </label>
                    </div>
                </div>
                <div className="form-foot">
                    <Link href="/" className="btn btn-ghost">Cancel</Link>
                    <button type="submit" className="btn btn-primary" disabled={processing}>
                        <Icon name="check" /> {processing ? 'Saving…' : 'Change password'}
                    </button>
                </div>
            </form>
        </AppLayout>
    );
}
