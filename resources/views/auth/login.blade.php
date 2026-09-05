<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Sign in — {{ config('aimemory.brand', 'ai-memory') }}</title>
    @include('layouts._styles')
</head>
<body>
<main class="login">
    <div class="login__box">
        <div class="login__brand">
            <span class="brand__mark" aria-hidden="true">aim</span>
            <span class="brand__text">{{ config('aimemory.brand', 'ai-memory') }}<span>.</span></span>
        </div>
        <p class="login__lede">Read-only panel over the ai-memory index.</p>

        <form method="POST" action="{{ route('login') }}" class="card">
            @csrf

            <div class="form-row">
                <label for="email">E-mail</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                @error('email')<p class="err">{{ $message }}</p>@enderror
            </div>

            <div class="form-row">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required autocomplete="current-password">
                @error('password')<p class="err">{{ $message }}</p>@enderror
            </div>

            <div class="form-row">
                <label class="form-check">
                    <input type="checkbox" name="remember" value="1"> Keep me signed in
                </label>
            </div>

            <button type="submit" class="btn btn--primary">Sign in</button>
        </form>

        <p class="login__foot">
            There is no sign-up. Accounts are created on the host with<br>
            <code>php artisan aimemory:user you@example.com</code>
        </p>
    </div>
</main>
</body>
</html>
