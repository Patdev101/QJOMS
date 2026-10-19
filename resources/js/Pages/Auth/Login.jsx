import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../../Components/Icon';

// the same document flow shown on the quotation / job order / invoice pages
const flow = [
    ['file-text', 'Quotation', 'Price the job from your tariff'],
    ['clipboard', 'Job order', 'Track the work once approved'],
    ['receipt', 'Invoice', 'Bill the customer when done'],
    ['check-circle', 'Paid', 'Record payments to the last peso'],
];

// sign-in page: brand panel (pine, like the top bar) + form panel (sand, like the app pages)
export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });
    const { data, setData, errors, processing } = form;
    const [showPassword, setShowPassword] = useState(false);

    function submit(event) {
        event.preventDefault();
        form.post('/login', { onFinish: () => form.reset('password') });
    }

    return (
        <div className="auth-page">
            <Head title="Sign in" />

            <aside className="auth-side">
                <div className="auth-brand">
                    <span className="brand-mark"><Icon name="file-text" size={22} /></span>
                    <div>
                        <b>QJOMS</b>
                        <small>Quotation &amp; Job Order Management System</small>
                    </div>
                </div>

                <div className="auth-pitch">
                    <h2>From quotation to payment, in one place.</h2>
                    <ol className="auth-flow">
                        {flow.map(([icon, title, text], i) => (
                            <li key={title}>
                                <span className="auth-flow-ic"><Icon name={icon} size={18} /></span>
                                <div>
                                    <b>{i + 1}. {title}</b>
                                    <span>{text}</span>
                                </div>
                            </li>
                        ))}
                    </ol>
                </div>

                <div className="auth-foot">© {new Date().getFullYear()} QJOMS</div>
            </aside>

            <main className="auth-main">
                <form className="auth-card" onSubmit={submit} noValidate>
                    <h1>Welcome back</h1>
                    <p className="sub">Sign in to continue to QJOMS.</p>

                    {(errors.email || errors.password) && (
                        <div className="alert alert-error" role="alert">
                            <Icon name="alert" /> <div>{errors.email || errors.password}</div>
                        </div>
                    )}

                    <div className="field">
                        <label htmlFor="email">Email</label>
                        <input id="email" type="email" autoComplete="username" autoFocus value={data.email} placeholder="you@company.com"
                               className={errors.email ? 'is-invalid' : ''} onChange={(e) => setData('email', e.target.value)} />
                    </div>
                    <div className="field field-gap">
                        <label htmlFor="password">Password</label>
                        <div className="password-box">
                            <input id="password" type={showPassword ? 'text' : 'password'} autoComplete="current-password" value={data.password}
                                   placeholder="Your password" className={errors.password ? 'is-invalid' : ''} onChange={(e) => setData('password', e.target.value)} />
                            <button type="button" className="eye-btn" onClick={() => setShowPassword(! showPassword)}
                                    aria-label={showPassword ? 'Hide password' : 'Show password'} title={showPassword ? 'Hide password' : 'Show password'}>
                                <Icon name={showPassword ? 'eye-off' : 'eye'} size={18} />
                            </button>
                        </div>
                    </div>

                    <label className="switch auth-remember">
                        <input type="checkbox" checked={data.remember} onChange={(e) => setData('remember', e.target.checked)} />
                        Remember me on this computer
                    </label>

                    <button type="submit" className="btn btn-amber auth-submit" disabled={processing}>
                        {processing ? 'Signing in…' : <>Sign in <Icon name="arrow-right" size={17} /></>}
                    </button>
                </form>
            </main>
        </div>
    );
}
