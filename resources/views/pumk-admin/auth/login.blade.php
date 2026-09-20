<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin PUMK — LENSA TJSL INKA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        *{box-sizing:border-box}html,body{margin:0;min-height:100%;font-family:Poppins,sans-serif}.admin-login{min-height:100vh;display:grid;place-items:center;padding:30px;background:url('/images/admin-login-train.jpg') center/cover no-repeat}.login-panel{width:400px;min-height:468px;padding:43px 30px 25px;border-radius:18px;background:rgba(220,220,220,.63);box-shadow:0 3px 14px rgba(0,0,0,.14)}.inka-mark{width:196px;height:60px;margin:0 auto 25px;background-image:url('/images/admin-login-reference.png');background-repeat:no-repeat;background-size:1440px 748px;background-position:-622px -166px}.login-panel h1{margin:0 0 9px;color:#050505;font-size:32px;font-weight:700;line-height:1;text-align:center}.login-panel .subtitle{margin:0 0 36px;color:#1e293b;font-size:13px;text-align:center}.field{margin-bottom:17px}.field label{display:block;margin-bottom:2px;font-size:14px;font-weight:500;text-transform:uppercase}.field input{width:100%;height:45px;padding:0 18px;border:1px solid #777;border-radius:999px;outline:none;background:#fff;font:500 15px Poppins,sans-serif}.field input:focus{border-color:#111;box-shadow:0 0 0 3px rgba(255,255,255,.55)}.remember{display:flex;align-items:center;gap:8px;margin-top:3px;font-size:12px}.remember input{width:16px;height:16px;accent-color:#2653ff}.login-btn{display:block;margin:23px auto 0;padding:10px 27px;border:0;border-radius:999px;background:rgba(255,255,255,.34);box-shadow:0 2px 7px rgba(0,0,0,.13);color:#fff;cursor:pointer;font:500 19px Poppins,sans-serif}.login-btn:hover{background:rgba(255,255,255,.48)}.login-error{margin:8px 0 -8px;color:#b91c1c;font-size:12px;font-weight:600;text-align:center}@media(max-width:520px){.admin-login{padding:18px}.login-panel{width:100%;min-height:auto;padding:34px 24px}.login-panel h1{font-size:27px}.inka-mark{transform:scale(.9)}}
    </style>
</head>
<body>
<main class="admin-login">
    <section class="login-panel">
        <div class="inka-mark" role="img" aria-label="INKA"></div>
        <h1>Admin PUMK Dashboard</h1>
        <p class="subtitle">Pengelolaan Kartu Piutang Program Pendanaan UMK</p>

        <form method="POST" action="{{ route('pumk-admin.login.store') }}">
            @csrf
            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" value="{{ old('username') }}" required autofocus autocomplete="username">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
            </div>
            <label class="remember" for="remember"><input id="remember" type="checkbox" name="remember" value="1"> Ingat sesi login</label>
            @error('username')<p class="login-error">{{ $message }}</p>@enderror
            <button class="login-btn" type="submit">Login</button>
        </form>
    </section>
</main>
</body>
</html>
