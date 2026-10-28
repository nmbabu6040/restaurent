<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>Admin login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="flex min-h-screen items-center justify-center bg-warm p-4">
    <main class="card w-full max-w-sm p-8">
        <h1 class="text-center text-3xl font-bold">{{ setting('name', config('app.name')) }}</h1>
        <p class="mt-1 text-center text-sm">Admin login</p>

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5">
            @csrf
            <x-form.field name="email" type="email" label="Email" :required="true" autocomplete="username"
                autofocus />
            <x-form.field name="password" type="password" label="Password" :required="true"
                autocomplete="current-password" />
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1" class="accent-navy"> Remember me
            </label>
            <button type="submit" class="btn-primary w-full">Log in</button>
        </form>
    </main>
</body>

</html>
