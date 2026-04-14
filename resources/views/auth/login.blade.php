<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login · Gosyen Stock Opname</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <main class="mx-auto grid min-h-screen max-w-md place-items-center px-4">
        <form method="POST" action="{{ route('login.store') }}" class="panel w-full p-6">
            @csrf
            <p class="text-sm font-bold uppercase text-[var(--brand)]">Gosyen</p>
            <h1 class="mt-1 text-2xl font-bold text-[var(--text)]">Masuk Stock Opname</h1>
            <p class="mt-2 text-sm text-[var(--muted)]">Gunakan akun employee yang sudah diapprove admin.</p>

            @if (session('status'))
                <div class="mt-4 rounded-md bg-[var(--panel-soft)] p-3 text-sm font-semibold text-[var(--brand)]">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="mt-4 rounded-md bg-[#fdecec] p-3 text-sm font-semibold text-[#a12020]">{{ $errors->first() }}</div>
            @endif

            <div class="mt-5 grid gap-3">
                <label>
                    <span class="label">Email</span>
                    <input name="email" type="email" value="{{ old('email') }}" required autofocus class="field mt-1">
                </label>
                <label>
                    <span class="label">Password</span>
                    <input name="password" type="password" required class="field mt-1">
                </label>
                <label class="flex items-center gap-2 text-sm font-semibold text-[var(--muted)]">
                    <input name="remember" type="checkbox" class="h-4 w-4 rounded border-[var(--line)]">
                    Ingat login
                </label>
                <button class="rounded-md bg-[var(--brand)] px-4 py-3 text-sm font-bold text-white">Masuk</button>
            </div>

            <p class="mt-5 text-center text-sm text-[var(--muted)]">
                Belum punya akun?
                <a href="{{ route('register') }}" class="font-bold text-[var(--brand)]">Daftar</a>
            </p>
        </form>
    </main>
</body>
</html>
