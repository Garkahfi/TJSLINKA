<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Super Admin — LENSA TJSL INKA</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Poppins, sans-serif; }
        .login {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 28px;
            background: linear-gradient(#0004, #0004), url('/images/admin-login-train.jpg') center / cover;
        }
        .panel {
            width: 396px;
            min-height: 470px;
            padding: 42px 30px 25px;
            border-radius: 16px;
            background: #dcdcdca8;
            box-shadow: 0 4px 18px #0003;
        }
        .inka-mark {
            width: 196px;
            height: 60px;
            margin: 0 auto 20px;
            background-image: url('/images/admin-login-reference.png');
            background-repeat: no-repeat;
            background-size: 1440px 748px;
            background-position: -622px -166px;
        }
        .panel h1 { margin: 0 0 52px; font-size: 27px; text-align: center; }
        .field { display: block; margin: 17px 0; font-size: 13px; text-transform: uppercase; }
        .field input {
            display: block;
            width: 100%;
            height: 45px;
            margin-top: 4px;
            padding: 0 18px;
            border: 1px solid #777;
            border-radius: 999px;
            font: inherit;
        }
        .login-button {
            display: block;
            margin: 25px auto 0;
            padding: 10px 28px;
            border: 0;
            border-radius: 999px;
            background: #ffffff66;
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-size: 18px;
        }
        .error { color: #b91c1c; font-size: 12px; text-align: center; }
    </style>
</head>
<body>
    <main class="login">
        <section class="panel">
            <div class="inka-mark" role="img" aria-label="INKA"></div>
            <h1>SuperAdmin Dashboard</h1>

            <form method="POST" action="{{ route('superadmin.login.store') }}">
                @csrf
                <label class="field">
                    Username
                    <input name="username" value="{{ old('username') }}" required autofocus autocomplete="username">
                </label>
                <label class="field">
                    Password
                    <input type="password" name="password" required autocomplete="current-password">
                </label>
                @if ($errors->any())
                    <p class="error">{{ $errors->first() }}</p>
                @endif
                <button class="login-button" type="submit">Login</button>
            </form>
        </section>
    </main>
</body>
</html>
