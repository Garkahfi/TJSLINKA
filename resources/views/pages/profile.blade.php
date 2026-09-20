<x-layouts.app title="Profil">
    @php
        $passwordFormOpen = old('password_form') === '1'
            || $errors->has('current_password')
            || $errors->has('password');
    @endphp

    @push('head')
        <style>
            .public-profile-page{min-height:calc(100vh - 80px);background:#f3f3f3;padding:34px 0 54px}
            .public-profile-card{width:100%;min-height:690px;border:1px solid #ececec;border-radius:0 0 14px 14px;background:#fff;padding:34px 38px 30px;box-shadow:0 3px 3px rgba(0,0,0,.2);color:#090f20}
            .public-profile-title{margin:0 0 28px;font-size:18px;font-weight:500}
            .public-profile-identity{display:flex;align-items:center;gap:30px;margin-bottom:48px}
            .public-profile-avatar{display:grid;width:100px;height:100px;flex:0 0 100px;place-items:center;overflow:hidden;border-radius:999px;background:#d9d9d9;color:#111827;font-size:27px}
            .public-profile-avatar img{width:100%;height:100%;object-fit:cover}
            .public-profile-name{font-size:21px;font-weight:600}
            .public-profile-role{margin-top:7px;font-size:15px}
            .public-profile-avatar-input{display:none;margin-top:10px;font-size:12px}
            .public-profile-form.is-editing .public-profile-avatar-input{display:block}
            .public-profile-section{margin-top:28px}
            .public-profile-section-head{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:25px}
            .public-profile-section-title{margin:0;font-size:20px;font-weight:500}
            .public-profile-pill{display:inline-flex;align-items:center;justify-content:center;gap:4px;border:1px solid #111827;border-radius:999px;background:#fff;padding:4px 14px;color:#090f20;font:inherit;font-size:13px;line-height:1.2;text-decoration:none;cursor:pointer}
            .public-profile-pill:hover{background:#f3f4f6}
            .public-profile-grid{display:grid;max-width:780px;grid-template-columns:1fr 1fr;gap:26px 40px}
            .public-profile-field label,.public-password-field label{display:block;margin-bottom:4px;font-size:15px}
            .public-profile-field input,.public-password-field input{box-sizing:border-box;width:100%;height:37px;border:1px solid #9ca3af;border-radius:7px;background:#fff;padding:4px 8px;color:#111827;font:inherit;font-size:15px}
            .public-profile-password{margin-top:68px}
            .public-password-preview{max-width:370px}
            .public-password-form{display:none}
            .public-password-fields{display:grid;width:min(100%,370px);gap:22px}
            .public-password-fields input{border-radius:10px;background:#ededed}
            .public-password-actions{display:none;align-items:center;gap:12px}
            .public-profile-password.is-changing .public-password-preview,.public-profile-password.is-changing [data-public-password-edit]{display:none}
            .public-profile-password.is-changing .public-password-actions{display:flex}
            .public-profile-password.is-changing .public-password-form{display:block}
            .public-profile-logout{margin-top:34px;border:0;border-radius:999px;background:#669df6;padding:9px 23px;color:#fff;font:600 13px Poppins,sans-serif;cursor:pointer}
            .public-profile-alert{margin-bottom:20px;border-radius:7px;background:#dcfce7;padding:10px 14px;color:#166534;font-size:13px}
            .public-profile-errors{margin-bottom:20px;border-radius:7px;background:#fee2e2;padding:10px 14px;color:#991b1b;font-size:13px}
            .public-profile-error{margin:4px 0 0;color:#dc2626;font-size:11px}

            @media(max-width:767px){
                .public-profile-page{padding:22px 0 36px}
                .public-profile-card{padding:28px 20px}
                .public-profile-identity{gap:20px;margin-bottom:36px}
                .public-profile-avatar{width:80px;height:80px;flex-basis:80px}
                .public-profile-grid{grid-template-columns:1fr;gap:18px}
                .public-profile-password{margin-top:48px}
            }
        </style>
    @endpush

    <section class="public-profile-page">
        <div class="container-site">
            <article class="public-profile-card">
                <h1 class="public-profile-title">Profil</h1>

                @if(session('success'))
                    <div class="public-profile-alert">{{ session('success') }}</div>
                @endif
                @if($errors->any())
                    <div class="public-profile-errors">{{ $errors->first() }}</div>
                @endif

                <form
                    method="POST"
                    enctype="multipart/form-data"
                    action="{{ route('public.profile.update') }}"
                    class="public-profile-form"
                    data-public-profile-form
                >
                    @csrf
                    @method('PUT')

                    <div class="public-profile-identity">
                        <div class="public-profile-avatar">
                            @if($user->avatar_path)
                                <img src="{{ Storage::url($user->avatar_path) }}" alt="Foto profil {{ $user->name }}">
                            @else
                                {{ strtoupper(substr($user->nama_depan ?: 'U', 0, 1)) }}
                            @endif
                        </div>
                        <div>
                            <div class="public-profile-name">{{ $user->name ?: 'User Admin' }}</div>
                            <div class="public-profile-role">{{ $user->jabatan ?: 'Karyawan' }}</div>
                            <input
                                type="file"
                                name="avatar"
                                accept="image/*"
                                class="public-profile-avatar-input"
                                data-public-profile-input
                                disabled
                            >
                        </div>
                    </div>

                    <div class="public-profile-section-head">
                        <h2 class="public-profile-section-title">Informasi Pribadi</h2>
                        <button type="button" class="public-profile-pill" data-public-profile-edit>
                            <span aria-hidden="true">✎</span>
                            <span data-public-profile-edit-label>Edit</span>
                        </button>
                    </div>

                    <div class="public-profile-grid">
                        @foreach([
                            'nama_depan' => 'Nama Depan',
                            'nama_belakang' => 'Nama Belakang',
                            'email' => 'Email',
                            'no_telephone' => 'No Telephone',
                            'jabatan' => 'Jabatan',
                            'alamat' => 'Alamat',
                        ] as $name => $label)
                            <div class="public-profile-field">
                                <label for="public-profile-{{ $name }}">{{ $label }}</label>
                                <input
                                    id="public-profile-{{ $name }}"
                                    name="{{ $name }}"
                                    value="{{ old($name, $user->$name) }}"
                                    readonly
                                    data-public-profile-input
                                >
                            </div>
                        @endforeach
                    </div>
                </form>

                <section
                    @class([
                        'public-profile-section public-profile-password',
                        'is-changing' => $passwordFormOpen,
                    ])
                    data-public-password-section
                >
                    <div class="public-profile-section-head">
                        <h2 class="public-profile-section-title">Password</h2>
                        <button type="button" class="public-profile-pill" data-public-password-edit>
                            <span aria-hidden="true">✎</span> Ubah Password
                        </button>
                        <div class="public-password-actions">
                            <button type="submit" form="public-password-form" class="public-profile-pill">Simpan</button>
                            <button type="button" class="public-profile-pill" data-public-password-cancel>Batal</button>
                        </div>
                    </div>

                    <div class="public-profile-field public-password-preview">
                        <label for="public-password-preview">Password</label>
                        <input id="public-password-preview" value="********" readonly>
                    </div>

                    <form
                        id="public-password-form"
                        method="POST"
                        action="{{ route('public.password.update') }}"
                        class="public-password-form"
                    >
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="password_form" value="1">

                        <div class="public-password-fields">
                            <div class="public-password-field">
                                <label for="public-current-password">Password Lama</label>
                                <input
                                    id="public-current-password"
                                    type="password"
                                    name="current_password"
                                    required
                                    autocomplete="current-password"
                                >
                                @error('current_password')
                                    <p class="public-profile-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="public-password-field">
                                <label for="public-new-password">Password Baru</label>
                                <input
                                    id="public-new-password"
                                    type="password"
                                    name="password"
                                    required
                                    autocomplete="new-password"
                                >
                                @error('password')
                                    <p class="public-profile-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="public-password-field">
                                <label for="public-password-confirmation">Konfirmasi Password Baru</label>
                                <input
                                    id="public-password-confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    required
                                    autocomplete="new-password"
                                >
                            </div>
                        </div>
                    </form>
                </section>

                <form method="POST" action="{{ route('public.logout') }}">
                    @csrf
                    <button type="submit" class="public-profile-logout">Logout</button>
                </form>
            </article>
        </div>
    </section>

    <script>
        (function(){
            var form=document.querySelector('[data-public-profile-form]');
            var editButton=document.querySelector('[data-public-profile-edit]');
            if(!form||!editButton)return;

            editButton.addEventListener('click',function(){
                if(form.classList.contains('is-editing')){
                    form.requestSubmit();
                    return;
                }

                form.classList.add('is-editing');
                form.querySelectorAll('[data-public-profile-input]').forEach(function(input){
                    if(input.type==='file')input.disabled=false;
                    else input.readOnly=false;
                });
                editButton.querySelector('[data-public-profile-edit-label]').textContent='Simpan';
            });
        })();

        (function(){
            var section=document.querySelector('[data-public-password-section]');
            var editButton=document.querySelector('[data-public-password-edit]');
            var cancelButton=document.querySelector('[data-public-password-cancel]');
            var passwordForm=document.getElementById('public-password-form');
            if(!section||!editButton||!cancelButton||!passwordForm)return;

            editButton.addEventListener('click',function(){
                section.classList.add('is-changing');
                passwordForm.querySelector('input[name="current_password"]')?.focus();
            });
            cancelButton.addEventListener('click',function(){
                passwordForm.reset();
                section.classList.remove('is-changing');
            });
        })();
    </script>
</x-layouts.app>
