<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Login pengguna LENSA TJSL INKA">
    <title>Login — LENSA TJSL INKA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        *{box-sizing:border-box}
        body{margin:0;font-family:Poppins,sans-serif}
        .public-login{position:relative;isolation:isolate;display:grid;min-height:100vh;place-items:center;overflow:hidden;padding:28px}
        .public-login-video{position:absolute;inset:0;z-index:-2;width:100%;height:100%;object-fit:cover}
        .public-login-overlay{position:absolute;inset:0;z-index:-1;background:rgba(0,0,0,.22)}
        .public-login-card{width:min(100%,495px);border:1px solid rgba(255,255,255,.64);border-radius:18px;background:linear-gradient(145deg,rgba(255,255,255,.64),rgba(241,245,249,.5));padding:28px 34px 26px;box-shadow:0 20px 55px rgba(0,0,0,.25);-webkit-backdrop-filter:blur(9px) saturate(115%);backdrop-filter:blur(9px) saturate(115%)}
        .public-login-brand{display:block;width:210px;height:auto;margin:0 auto 20px}
        .public-login-field{display:block;margin-top:15px;color:#111827;font-size:16px;font-weight:500}
        .public-login-field input{display:block;width:100%;height:57px;margin-top:8px;border:1px solid #6b7280;border-radius:999px;background:rgba(255,255,255,.77);padding:9px 22px;color:#111827;font:inherit;outline:none}
        .public-login-field input:focus{border-color:#17233f;box-shadow:0 0 0 3px rgba(23,35,63,.14)}
        .public-login-submit{display:block;min-width:137px;margin:27px auto 0;border:0;border-radius:999px;background:#17233f;padding:12px 28px;color:#fff;font:500 21px Poppins,sans-serif;cursor:pointer}
        .public-login-submit:hover{background:#243451}
        .public-login-error{margin:15px 0 0;color:#c81e1e;font-size:13px;font-weight:500;text-align:center}

        @media(max-width:600px){
            .public-login{padding:18px}
            .public-login-card{padding:25px 22px}
            .public-login-brand{width:185px}
            .public-login-field{font-size:14px}
            .public-login-field input{height:50px}
            .public-login-submit{font-size:18px}
        }
    </style>
</head>
<body>
    <main class="public-login">
        <video class="public-login-video" autoplay muted loop playsinline>
            <source src="{{ asset('videos/waterfall-bg.mp4') }}" type="video/mp4">
        </video>
        <div class="public-login-overlay"></div>

        <form method="POST" action="{{ route('login.store') }}" class="public-login-card">
            @csrf

            <svg
                class="public-login-brand"
                viewBox="0 0 240 220"
                role="img"
                aria-label="LENSA TJSL INKA"
            >
                <g stroke="#ffffff" stroke-width="3">
                    <rect x="102" y="16" width="40" height="40" rx="7" fill="#f4aa3f" transform="rotate(45 122 36)"/>
                    <rect x="72" y="47" width="40" height="40" rx="7" fill="#67c69b" transform="rotate(45 92 67)"/>
                    <rect x="132" y="47" width="40" height="40" rx="7" fill="#6c92e7" transform="rotate(45 152 67)"/>
                    <rect x="102" y="77" width="40" height="40" rx="7" fill="#e97883" transform="rotate(45 122 97)"/>
                </g>
                <g fill="none" stroke="#111111" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M113 45V32M121 45V27M129 45V21M108 45h26M108 36l8-5 7-7 8-9"/>
                    <circle cx="91" cy="67" r="10"/><path d="M82 64l7 2 2-5 4 3 5 1M89 76l2-6 5-2"/>
                    <path d="M140 64l8-5 4 3 5-1 8 7M143 70l5 5 4-2 4 2 5-5M148 59l5 6"/>
                    <path d="M106 103h32M116 82l-9 21M128 82l9 21M122 79v28M111 91h10M127 91h10M113 91c0 7-7 7-7 0M138 91c0 7-7 7-7 0"/>
                    <path d="M71 88l-18 22 5 8 28 15 13-3-17-12M173 88l18 22-5 8-28 15-13-3 17-12M55 110l-9-8M189 110l9-8"/>
                </g>
                <text x="122" y="163" text-anchor="middle" fill="#d31f26" font-family="Poppins, sans-serif" font-size="28" font-weight="800" letter-spacing="5">LENSA</text>
                <text x="103" y="194" text-anchor="middle" fill="#d31f26" font-family="Poppins, sans-serif" font-size="26" font-weight="800" letter-spacing="4">TJSL</text>
                <text x="178" y="194" text-anchor="middle" fill="#18181b" font-family="Poppins, sans-serif" font-size="20" font-weight="800">INKA</text>
            </svg>

            <label class="public-login-field" for="username">
                USERNAME
                <input
                    id="username"
                    name="username"
                    value="{{ old('username') }}"
                    required
                    autofocus
                    autocomplete="username"
                >
            </label>

            <label class="public-login-field" for="password">
                PASSWORD
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                >
            </label>

            @error('username')
                <p class="public-login-error">{{ $message }}</p>
            @enderror

            <button type="submit" class="public-login-submit">Login</button>
        </form>
    </main>
</body>
</html>
