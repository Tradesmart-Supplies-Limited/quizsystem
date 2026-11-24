<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · TradeSmart Operations Quiz Arena</title>
    <link rel="icon" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="login-page">
<div class="login-card">
    <div class="text-center mb-4">
        <img src="{{ asset('images/logo.png') }}" alt="TradeSmart Supplies Limited" class="login-logo">
        <h1 class="h5 mt-3 mb-0 fw-bold letter">OPERATIONS QUIZ ARENA</h1>
        <p class="text-muted small mb-0">Facilitator sign-in</p>
    </div>
    @include('partials.flash')
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input class="form-control form-control-lg" type="email" name="email" id="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <input class="form-control form-control-lg" type="password" name="password" id="password" required autocomplete="current-password">
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remember" id="remember">
            <label class="form-check-label" for="remember">Keep me signed in on this computer</label>
        </div>
        <button class="btn btn-brand btn-lg w-100"><i class="bi bi-box-arrow-in-right"></i> Sign in</button>
    </form>
</div>
</body>
</html>
