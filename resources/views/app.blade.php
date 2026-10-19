<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="/css/style.css?v={{ filemtime(public_path('css/style.css')) }}">
    {{-- React pages (resources/js) are drawn inside this page --}}
    @viteReactRefresh
    @vite('resources/js/app.jsx')
    <x-inertia::head>
        <title>QJOMS</title>
    </x-inertia::head>
</head>
<body>
    <x-inertia::app />
</body>
</html>
