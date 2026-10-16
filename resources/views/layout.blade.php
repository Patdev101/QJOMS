<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · Quotation System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="/css/style.css?v={{ filemtime(public_path('css/style.css')) }}">
</head>
<body>
    @php
        // which menu item is highlighted: each page sets @section('nav', '...')
        $nav = trim($__env->yieldContent('nav'));

        // one menu, drawn twice: as the top menu and as the sidebar (the sidebar is also the phone menu)
        $transactions = [
            ['key' => 'quotations', 'url' => route('quotations.index'), 'icon' => 'file-text', 'label' => 'Quotations',
             'count' => $navCounts['pending'], 'countTitle' => 'Waiting for customer approval', 'soft' => false],
            ['key' => 'job-orders', 'url' => route('job-orders.index'), 'icon' => 'clipboard', 'label' => 'Job orders',
             'count' => $navCounts['active_jobs'], 'countTitle' => 'In progress', 'soft' => true],
        ];
        $masterData = [
            ['key' => 'customers', 'url' => route('customers.index'), 'icon' => 'users', 'label' => 'Customer profiles', 'hint' => 'Contacts and discounts'],
            ['key' => 'tariff-service', 'url' => route('tariffs.index', ['type' => 'service']), 'icon' => 'wrench', 'label' => 'Service tariff', 'hint' => 'Work you charge for'],
            ['key' => 'tariff-item', 'url' => route('tariffs.index', ['type' => 'item']), 'icon' => 'package', 'label' => 'Item tariff', 'hint' => 'Materials and parts'],
        ];
        $inMasterData = in_array($nav, array_column($masterData, 'key'));
    @endphp

    {{-- phones and small tablets (both layouts) --}}
    <header class="topbar">
        <button type="button" class="topbar-btn" id="navOpen" aria-label="Open menu"><x-icon name="menu" size="20" /></button>
        <a href="{{ route('dashboard') }}" class="topbar-brand">Quotation System</a>
        <a href="{{ route('quotations.create') }}" class="topbar-btn" aria-label="New quotation"><x-icon name="plus" size="20" /></a>
    </header>

    {{-- top menu layout (default) --}}
    <header class="topnav">
        <div class="topnav-inner">
            <a href="{{ route('dashboard') }}" class="brand">
                <span class="brand-mark"><x-icon name="file-text" size="19" /></span>
                <span><b>Quotation System</b></span>
            </a>

            <nav class="topnav-links" aria-label="Main menu">
                <a href="{{ route('dashboard') }}" @class(['active' => $nav === 'dashboard'])><x-icon name="layout" size="17" /> Dashboard</a>
                {{-- hidden for now: Quotations, Job orders, Master data --}}
            </nav>

            <div class="topnav-right">
                <a href="{{ route('quotations.create') }}" class="cta"><x-icon name="plus" /> New quotation</a>
            </div>
        </div>
    </header>

    {{-- sidebar layout (chosen with the switch), and the slide-out menu on phones --}}
    <aside class="sidebar">
        <a href="{{ route('dashboard') }}" class="brand">
            <span class="brand-mark"><x-icon name="file-text" size="20" /></span>
            <span>
                <b>Quotation System</b>
                <small>Quote · Job order · Billing</small>
            </span>
        </a>

        <a href="{{ route('quotations.create') }}" class="cta"><x-icon name="plus" /> New quotation</a>

        <nav class="nav" aria-label="Main menu">
            <a href="{{ route('dashboard') }}" @class(['active' => $nav === 'dashboard'])><x-icon name="layout" /> Dashboard</a>

            {{-- hidden for now: Quotations, Job orders, Master data --}}
        </nav>

        <div class="sidebar-foot">
            <div class="flow">
                Quotation <x-icon name="arrow-right" size="12" /> Job order <x-icon name="arrow-right" size="12" /> Billing
            </div>
            {{ now()->format('l, F j, Y') }}        </div>
    </aside>
    <div class="scrim" id="scrim"></div>

    <main class="main">
        <div class="container">
            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    <x-icon name="alert" />
                    <div>
                        <b>Please check the following:</b>
                        <ul>
                            @foreach (array_unique($errors->all()) as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-error" role="alert"><x-icon name="alert" /> <div>{{ session('error') }}</div></div>
            @endif

            @yield('content')
        </div>
    </main>

    <div class="toasts">
        @if (session('success'))
            <div class="toast" role="status">
                <x-icon name="check-circle" class="toast-ic" />
                <span>{{ session('success') }}</span>
                <button type="button" class="toast-x" aria-label="Dismiss"><x-icon name="x" size="16" /></button>
            </div>
        @endif
    </div>

    {{-- "Are you sure?" pop-up. A form with data-confirm="question" shows it before it is sent.
         Optional: data-confirm-title, data-confirm-ok (button text), data-confirm-tone (danger | success). --}}
    <div class="modal" id="confirmModal">
        <div class="modal-box" role="alertdialog" aria-modal="true" aria-labelledby="confirmTitle" aria-describedby="confirmMessage">
            <div class="modal-icon" id="confirmIcon">
                <x-icon name="alert" size="22" class="ic-default" />
                <x-icon name="check-circle" size="22" class="ic-success" />
                <x-icon name="trash" size="22" class="ic-danger" />
            </div>
            <h3 id="confirmTitle">Please confirm</h3>
            <p id="confirmMessage"></p>
            <div class="actions end">
                <button type="button" class="btn btn-ghost" id="confirmCancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmOk">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const body = document.body;

            // mobile menu
            document.getElementById('navOpen').addEventListener('click', () => body.classList.add('nav-open'));
            document.getElementById('scrim').addEventListener('click', () => body.classList.remove('nav-open'));

            // "Master data" drop-down in the top menu
            function closeDropdowns() {
                document.querySelectorAll('.dropdown.open').forEach(function (dropdown) {
                    dropdown.classList.remove('open');
                    dropdown.querySelector('.dropdown-toggle').setAttribute('aria-expanded', 'false');
                });
            }

            document.querySelectorAll('.dropdown').forEach(function (dropdown) {
                const toggle = dropdown.querySelector('.dropdown-toggle');

                toggle.addEventListener('click', function () {
                    const open = ! dropdown.classList.contains('open');

                    closeDropdowns();
                    dropdown.classList.toggle('open', open);
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                });

                // tabbing out of the menu closes it
                dropdown.addEventListener('focusout', function (event) {
                    if (! dropdown.contains(event.relatedTarget)) {
                        closeDropdowns();
                    }
                });
            });

            document.addEventListener('click', function (event) {
                if (! event.target.closest('.dropdown')) {
                    closeDropdowns();
                }
            });

            // a click anywhere on a table row opens that record (Ctrl+click opens a new tab)
            document.addEventListener('click', function (event) {
                const row = event.target.closest('tr[data-href]');

                if (! row || event.target.closest('a, button, input, select, form')) {
                    return;
                }

                if (event.ctrlKey || event.metaKey) {
                    window.open(row.dataset.href, '_blank');
                } else {
                    location.href = row.dataset.href;
                }
            });

            // the success message goes away by itself
            document.querySelectorAll('.toast').forEach(function (toast) {
                const close = function () {
                    toast.classList.add('hide');
                    setTimeout(() => toast.remove(), 300);
                };

                toast.querySelector('.toast-x').addEventListener('click', close);
                setTimeout(close, 5000);
            });

            const modal = document.getElementById('confirmModal');
            const okButton = document.getElementById('confirmOk');
            let waiting = null;

            function closeConfirm() {
                modal.classList.remove('show');

                if (waiting && waiting.submitter) {
                    waiting.submitter.focus();
                }

                waiting = null;
            }

            document.addEventListener('submit', function (event) {
                const form = event.target;

                if (event.defaultPrevented) {
                    return;
                }

                if (form.dataset.confirm && form.dataset.confirmed !== '1') {
                    event.preventDefault();

                    const tone = form.dataset.confirmTone || '';

                    document.getElementById('confirmTitle').textContent = form.dataset.confirmTitle || 'Please confirm';
                    document.getElementById('confirmMessage').textContent = form.dataset.confirm;
                    document.getElementById('confirmIcon').className = 'modal-icon ' + tone;
                    okButton.textContent = form.dataset.confirmOk || 'Confirm';
                    okButton.className = 'btn ' + (tone === 'danger' ? 'btn-danger-solid' : 'btn-primary');

                    waiting = { form: form, submitter: event.submitter };
                    modal.classList.add('show');
                    okButton.focus();
                    return;
                }

                // a second click while the page is still saving does nothing
                if (form.method.toLowerCase() === 'post') {
                    if (form.dataset.busy === '1') {
                        event.preventDefault();
                        return;
                    }

                    form.dataset.busy = '1';
                }
            });

            okButton.addEventListener('click', function () {
                if (! waiting) {
                    return;
                }

                const form = waiting.form;
                const submitter = waiting.submitter;

                form.dataset.confirmed = '1';
                modal.classList.remove('show');
                waiting = null;
                submitter ? form.requestSubmit(submitter) : form.requestSubmit();
            });

            document.getElementById('confirmCancel').addEventListener('click', closeConfirm);

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeConfirm();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    if (modal.classList.contains('show')) {
                        closeConfirm();
                    }

                    body.classList.remove('nav-open');
                    closeDropdowns();
                }
            });

            // coming back with the browser's Back button: forms can be sent again
            window.addEventListener('pageshow', function () {
                document.querySelectorAll('form').forEach(function (form) {
                    delete form.dataset.busy;
                    delete form.dataset.confirmed;
                });
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
