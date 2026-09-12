<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — MoniSurv</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        :root {
            --green-900: #0d3b26;
            --green-800: #145234;
            --green-700: #1a6b42;
            --green-600: #228554;
            --green-500: #2ea86a;
            --green-50: #f0f8f4;
            --accent: #3ecf8e;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh; display: flex;
            -webkit-font-smoothing: antialiased;
        }

        .login-left {
            flex: 1; display: flex; flex-direction: column;
            background: linear-gradient(135deg, #05150f 0%, var(--green-900) 50%, #0f4a30 100%);
            color: #fff; padding: 40px;
            position: relative; overflow: hidden;
        }
        .login-left::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(circle at 30% 80%, rgba(62,207,142,.12) 0%, transparent 60%),
                        radial-gradient(circle at 70% 20%, rgba(62,207,142,.08) 0%, transparent 50%);
            pointer-events: none;
        }
        .login-left-content {
            flex: 1; display: flex; flex-direction: column;
            justify-content: center; position: relative; z-index: 1;
            max-width: 480px; margin: 0 auto;
        }
        .login-left-brand {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 48px;
        }
        .login-left-brand img { width: 42px; height: 42px; border-radius: 12px; filter: brightness(1.2); }
        .login-left-brand span { font-size: 22px; font-weight: 800; letter-spacing: .3px; }
        .login-left h1 {
            font-size: 34px; font-weight: 800; line-height: 1.2;
            letter-spacing: -.5px; margin-bottom: 16px;
        }
        .login-left h1 span { color: var(--accent); }
        .login-left p {
            font-size: 15px; color: rgba(255,255,255,.55);
            line-height: 1.7; max-width: 420px;
        }

        .login-features {
            margin-top: 48px;
            display: flex; flex-direction: column; gap: 16px;
        }
        .login-feature {
            display: flex; align-items: center; gap: 14px;
        }
        .login-feature-icon {
            width: 40px; height: 40px; border-radius: 12px;
            background: rgba(62,207,142,.1);
            border: 1px solid rgba(62,207,142,.15);
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; color: var(--accent); flex-shrink: 0;
        }
        .login-feature-text { font-size: 13.5px; color: rgba(255,255,255,.6); font-weight: 450; }

        /* Decorative dots */
        .login-left .deco-dots {
            position: absolute; bottom: 40px; right: 40px;
            display: grid; grid-template-columns: repeat(5, 8px); gap: 12px;
            opacity: .1; z-index: 0;
        }
        .login-left .deco-dots span {
            width: 6px; height: 6px; border-radius: 50%; background: #fff;
        }

        .login-right {
            width: 480px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            background: #fff; padding: 40px;
        }
        .login-form { width: 100%; max-width: 360px; }

        .login-form-title {
            font-size: 22px; font-weight: 800; color: #1a1e2c;
            margin-bottom: 4px; letter-spacing: -.3px;
        }
        .login-form-sub {
            font-size: 13px; color: #8896a4; margin-bottom: 32px;
        }

        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block; font-size: 12px; font-weight: 600;
            color: #4a5568; margin-bottom: 6px;
        }
        .form-group .input-wrap {
            position: relative;
        }
        .form-group .input-wrap i {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: #b8c0cc; font-size: 15px; pointer-events: none;
        }
        .form-group input {
            width: 100%; padding: 11px 14px 11px 42px;
            border: 1.5px solid #e4e8f0; border-radius: 12px;
            font-size: 13.5px; font-family: inherit;
            color: #1a1e2c; transition: all .2s;
            outline: none; background: #fff;
        }
        .form-group input:focus {
            border-color: var(--green-500);
            box-shadow: 0 0 0 3px rgba(46,168,106,.1);
        }
        .form-group input::placeholder { color: #c8ced6; }

        .password-toggle {
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #b8c0cc;
            cursor: pointer; font-size: 15px; padding: 0;
        }
        .password-toggle:hover { color: #4a5568; }

        .login-extras {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 24px;
        }
        .login-extras label {
            display: flex; align-items: center; gap: 8px;
            font-size: 12.5px; color: #8896a4; cursor: pointer;
        }
        .login-extras label input[type="checkbox"] {
            width: 16px; height: 16px;
            accent-color: var(--green-700);
            cursor: pointer;
        }
        .login-extras a {
            font-size: 12.5px; color: var(--green-700);
            text-decoration: none; font-weight: 500;
        }
        .login-extras a:hover { color: var(--green-800); }

        .btn-login {
            width: 100%; padding: 12px;
            background: linear-gradient(135deg, var(--green-700), var(--green-800));
            color: #fff; border: none; border-radius: 12px;
            font-size: 14px; font-weight: 600; font-family: inherit;
            cursor: pointer; transition: all .2s;
            box-shadow: 0 4px 16px rgba(20,82,52,.3);
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, var(--green-800), var(--green-900));
            box-shadow: 0 6px 24px rgba(20,82,52,.4);
            transform: translateY(-1px);
        }
        .btn-login:active { transform: translateY(0); }

        .login-demo {
            margin-top: 32px; padding-top: 24px;
            border-top: 1px solid #eef1f6;
            text-align: center;
        }
        .login-demo p {
            font-size: 11.5px; color: #b8c0cc; margin-bottom: 8px;
        }
        .login-demo code {
            background: #f3f4f6; padding: 2px 8px;
            border-radius: 4px; font-size: 11px;
            color: var(--green-800); font-weight: 600;
        }

        .alert-login {
            padding: 10px 14px; border-radius: 10px;
            background: #fef2f2; color: #b91c1c;
            font-size: 12.5px; margin-bottom: 20px;
            border-left: 3px solid #ef4444;
        }

        @media (max-width: 991.98px) {
            body { flex-direction: column; }
            .login-left { display: none; }
            .login-right {
                width: 100%; min-height: 100vh;
                padding: 24px;
            }
            .login-form { max-width: 400px; }
            .login-form-title::before {
                content: '';
                display: block; width: 48px; height: 48px;
                background: url('{{ asset("assets/logo.png") }}') center/contain no-repeat;
                margin-bottom: 16px; border-radius: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="login-left">
        <div class="login-left-content">
            <div class="login-left-brand">
                <img src="{{ asset('assets/logo.png') }}" alt="MoniSurv">
                <span>MoniSurv</span>
            </div>
            <h1>Transaction <span>Surveillance</span> & Monitoring</h1>
            <p>Comprehensive AML/CFT compliance platform for financial institutions. Detect, investigate, and report suspicious activities with precision.</p>

            <div class="login-features">
                <div class="login-feature">
                    <div class="login-feature-icon"><i class="bi bi-shield-check"></i></div>
                    <span class="login-feature-text">Rule-based & AI-powered transaction monitoring</span>
                </div>
                <div class="login-feature">
                    <div class="login-feature-icon"><i class="bi bi-search"></i></div>
                    <span class="login-feature-text">PEP, Sanctions & Adverse Media screening</span>
                </div>
                <div class="login-feature">
                    <div class="login-feature-icon"><i class="bi bi-file-earmark-code"></i></div>
                    <span class="login-feature-text">goAML 5.0.2 compliant STR/CTR reporting</span>
                </div>
                <div class="login-feature">
                    <div class="login-feature-icon"><i class="bi bi-graph-up-arrow"></i></div>
                    <span class="login-feature-text">Customer risk rating with CDD/EDD reviews</span>
                </div>
            </div>
        </div>

        <div class="deco-dots">
            @for($i = 0; $i < 25; $i++)<span></span>@endfor
        </div>
    </div>

    <div class="login-right">
        <div class="login-form">
            <div class="login-form-title">Welcome back</div>
            <div class="login-form-sub">Sign in to your MoniSurv account</div>

            @if($errors->any())
            <div class="alert-login">
                @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
                @endforeach
            </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrap">
                        <i class="bi bi-envelope"></i>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="you@company.com">
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrap">
                        <i class="bi bi-lock"></i>
                        <input type="password" name="password" id="passwordInput" required placeholder="••••••••">
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="login-extras">
                    <label>
                        <input type="checkbox" name="remember" id="remember">
                        Remember me
                    </label>
                </div>

                <button type="submit" class="btn-login">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Sign In
                </button>
            </form>

            <div class="login-demo">
                <p>Demo credentials</p>
                <code>admin@monisurv.com</code> &nbsp;/&nbsp; <code>password</code>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('passwordInput');
            const icon = document.getElementById('toggleIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }
    </script>
</body>
</html>
