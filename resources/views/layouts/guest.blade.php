<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sign in') · ORDO</title>
    @vite(['resources/css/transmittal.css', 'resources/js/transmittal.js'])
</head>
<body class="guest-body">
    <a class="brand guest-brand" href="{{ route('transmittals.index') }}"><span class="brand-mark">O</span><span><strong>ORDO</strong><small>TRANSMITTAL V2</small></span></a>
    <main class="guest-card">
        @if (session('acknowledged'))<div class="flash success">{{ session('acknowledged') }}</div>@endif
        @if ($errors->any())<div class="flash error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
    <p class="guest-foot">Secure document exchange · ORDO Transmittal V2</p>
</body>
</html>
