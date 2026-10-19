import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Icon from '../Components/Icon';

// page frame: top menu (desktop), slide-out menu (phones), success message
export default function AppLayout({ title, active, children }) {
    const { auth, flash, today, pendingCount, activeJobs, unpaidInvoices } = usePage().props;
    const user = auth?.user;
    const initials = (user?.name ?? '').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join('');
    const signOut = () => router.post('/logout');
    const [userOpen, setUserOpen] = useState(false);
    useEffect(() => {
        if (! userOpen) return undefined;
        const onClick = (e) => { if (! e.target.closest('.account')) setUserOpen(false); };
        const onKey = (e) => { if (e.key === 'Escape') setUserOpen(false); };
        document.addEventListener('click', onClick);
        document.addEventListener('keydown', onKey);
        return () => { document.removeEventListener('click', onClick); document.removeEventListener('keydown', onKey); };
    }, [userOpen]);
    const [menuOpen, setMenuOpen] = useState(false);
    const [toast, setToast] = useState(null);

    // the stylesheet opens the phone menu with a class on <body>
    useEffect(() => {
        document.body.classList.toggle('nav-open', menuOpen);
    }, [menuOpen]);

    useEffect(() => router.on('navigate', () => setMenuOpen(false)), []);

    // the "saved" message goes away by itself
    useEffect(() => {
        if (! flash?.success) return;
        setToast(flash.success);
        const timer = setTimeout(() => setToast(null), 5000);
        return () => clearTimeout(timer);
    }, [flash?.success]);

    const links = [
        { key: 'dashboard', href: '/', icon: 'layout', label: 'Dashboard' },
        { key: 'quotations', href: '/quotations', icon: 'file-text', label: 'Quotations', count: pendingCount, countTitle: 'Waiting for customer approval' },
        { key: 'job-orders', href: '/job-orders', icon: 'clipboard', label: 'Job orders', count: activeJobs, countTitle: 'In progress', soft: true },
        { key: 'invoices', href: '/invoices', icon: 'receipt', label: 'Invoices', count: unpaidInvoices, countTitle: 'Not fully paid', soft: true },
    ];
    const masterData = [
        { key: 'customers', href: '/customers', icon: 'users', label: 'Customer profiles', hint: 'Contacts and discounts' },
        { key: 'tariff-service', href: '/tariffs?type=service', icon: 'wrench', label: 'Service tariff', hint: 'Work you charge for' },
        { key: 'tariff-item', href: '/tariffs?type=item', icon: 'package', label: 'Item tariff', hint: 'Materials and parts' },
    ];
    const inMasterData = masterData.some((link) => link.key === active);
    const badge = (link) => (link.count ? <span className={`count${link.soft ? ' soft' : ''}`} title={link.countTitle}>{link.count}</span> : null);

    // "Master data" drop-down: closes on a click elsewhere, Escape, or after picking a page
    const [dropOpen, setDropOpen] = useState(false);
    useEffect(() => {
        if (! dropOpen) return undefined;
        const onClick = (e) => { if (! e.target.closest('.dropdown')) setDropOpen(false); };
        const onKey = (e) => { if (e.key === 'Escape') setDropOpen(false); };
        document.addEventListener('click', onClick);
        document.addEventListener('keydown', onKey);
        return () => { document.removeEventListener('click', onClick); document.removeEventListener('keydown', onKey); };
    }, [dropOpen]);
    useEffect(() => router.on('navigate', () => setDropOpen(false)), []);

    return (
        <>
            <Head title={title} />

            <header className="topbar">
                <button type="button" className="topbar-btn" onClick={() => setMenuOpen(true)} aria-label="Open menu"><Icon name="menu" size={20} /></button>
                <Link href="/" className="topbar-brand">QJOMS</Link>
                <Link href="/quotations/create" className="topbar-btn" aria-label="New quotation"><Icon name="plus" size={20} /></Link>
            </header>

            <header className="topnav">
                <div className="topnav-inner">
                    <Link href="/" className="brand">
                        <span className="brand-mark"><Icon name="file-text" size={19} /></span>
                        <span><b>QJOMS</b><small>Quotation &amp; Job Order</small></span>
                    </Link>
                    <nav className="topnav-links" aria-label="Main menu">
                        {links.map((link) => (
                            <Link key={link.key} href={link.href} className={active === link.key ? 'active' : ''}>
                                <Icon name={link.icon} size={17} /> {link.label} {badge(link)}
                            </Link>
                        ))}
                        <div className={`dropdown${dropOpen ? ' open' : ''}`}
                             onBlur={(e) => { if (! e.currentTarget.contains(e.relatedTarget)) setDropOpen(false); }}>
                            <button type="button" className={`dropdown-toggle${inMasterData ? ' active' : ''}`} aria-expanded={dropOpen}
                                    onClick={() => setDropOpen(! dropOpen)}>
                                <Icon name="folder" size={17} /> Master data <Icon name="chevron-down" size={15} className="chev" />
                            </button>
                            <div className="dropdown-menu">
                                {masterData.map((link) => (
                                    <Link key={link.key} href={link.href} className={active === link.key ? 'active' : ''}>
                                        <span className="dd-ic"><Icon name={link.icon} size={18} /></span>
                                        <span><b>{link.label}</b><small>{link.hint}</small></span>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    </nav>
                    <div className="topnav-right">
                        <Link href="/quotations/create" className="cta"><Icon name="plus" /> New quotation</Link>
                        {user && (
                            <div className={`dropdown account${userOpen ? ' open' : ''}`}
                                 onBlur={(e) => { if (! e.currentTarget.contains(e.relatedTarget)) setUserOpen(false); }}>
                                <button type="button" className="account-btn" aria-expanded={userOpen} onClick={() => setUserOpen(! userOpen)}>
                                    <span className="avatar">{initials}</span>
                                    <span className="who-name">{user.name}</span>
                                    <Icon name="chevron-down" size={15} className="chev" />
                                </button>
                                <div className="dropdown-menu account-menu">
                                    <div className="account-who">
                                        <span className="avatar">{initials}</span>
                                        <span><b>{user.name}</b><small>{user.email}</small></span>
                                    </div>
                                    <button type="button" className="account-item" onClick={signOut}><Icon name="arrow-right" size={16} /> Sign out</button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </header>

            <aside className="sidebar">
                <Link href="/" className="brand">
                    <span className="brand-mark"><Icon name="file-text" size={20} /></span>
                    <span><b>QJOMS</b><small>Quotation &amp; Job Order</small></span>
                </Link>
                <Link href="/quotations/create" className="cta"><Icon name="plus" /> New quotation</Link>
                <nav className="nav" aria-label="Main menu">
                    {links.map((link) => (
                        <Link key={link.key} href={link.href} className={active === link.key ? 'active' : ''}>
                            <Icon name={link.icon} /> {link.label} {badge(link)}
                        </Link>
                    ))}
                    <div className="nav-group">Master data</div>
                    {masterData.map((link) => (
                        <Link key={link.key} href={link.href} className={active === link.key ? 'active' : ''}>
                            <Icon name={link.icon} /> {link.label}
                        </Link>
                    ))}
                </nav>
                <div className="sidebar-foot">
                    {user && <div style={{ color: '#fff', fontWeight: 600 }}>{user.name}<div style={{ fontWeight: 400 }}>{user.email}</div></div>}
                    {today}
                    {user && <button type="button" className="signout" style={{ marginTop: 10 }} onClick={signOut}>Sign out</button>}
                </div>
            </aside>
            <div className="scrim" onClick={() => setMenuOpen(false)} />

            <main className="main">
                <div className="container">
                    {flash?.error && <div className="alert alert-error" role="alert"><Icon name="alert" /> <div>{flash.error}</div></div>}
                    {children}
                </div>
            </main>

            <div className="toasts">
                {toast && (
                    <div className="toast" role="status">
                        <Icon name="check-circle" className="toast-ic" />
                        <span>{toast}</span>
                        <button type="button" className="toast-x" onClick={() => setToast(null)} aria-label="Dismiss"><Icon name="x" size={16} /></button>
                    </div>
                )}
            </div>
        </>
    );
}
