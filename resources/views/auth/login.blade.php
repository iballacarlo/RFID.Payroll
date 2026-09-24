<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Payroll System</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="login-page">
    <div class="login-backdrop"></div>
    <main class="login-shell">
        <section class="login-card">
            <div class="brand login-brand">
                <div class="brand-logos">
                    <img src="{{ asset('images/cvsu-logo.png') }}" alt="Cavite State University logo">
                    <img src="{{ asset('images/dcs-logo.png') }}" alt="Department of Computer Studies logo">
                </div>
                <div>
                    <strong>Payroll System</strong>
                    <small>Department of Computer Studies</small>
                </div>
            </div>

            <h1>Sign in</h1>
            <p>Use your assigned account to access attendance, payroll, and payslip records.</p>

            @if ($errors->any())
                <div class="alert error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}">
                @csrf
                <label>Email<input type="email" name="email" value="{{ old('email') }}" required autofocus></label>
                <label>Password
                    <span class="password-wrap">
                        <input id="password" type="password" name="password" required>
                        <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false">
                            <span class="eye-icon"></span>
                        </button>
                    </span>
                </label>
                <button class="button full" type="submit">Login</button>
            </form>
        </section>
    </main>
    <script>
        const passwordInput = document.getElementById('password');
        const passwordToggle = document.querySelector('.password-toggle');

        passwordToggle.addEventListener('click', () => {
            const isHidden = passwordInput.type === 'password';
            passwordInput.type = isHidden ? 'text' : 'password';
            passwordToggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            passwordToggle.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
        });
    </script>
</body>
</html>
