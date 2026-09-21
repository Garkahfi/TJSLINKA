@php
    $isPumkPanel = request()->routeIs('pumk-admin.*');
    $profilePrefix = $isPumkPanel
        ? 'pumk-admin'
        : (request()->routeIs('superadmin.*') ? 'superadmin' : 'admin');
    $profileLayout = $isPumkPanel ? 'layouts.pumk-admin' : 'layouts.admin';
    $passwordFormOpen = old('password_form') === '1'
        || $errors->has('current_password')
        || $errors->has('password');
@endphp

<x-dynamic-component :component="$profileLayout" title="Profil">
    <style>
        .profile-card{width:100%;max-width:860px;margin:0 auto;background:#fff;border:1px solid #ececec;border-radius:0 0 14px 14px;padding:42px 34px 28px;box-shadow:0 3px 2px rgba(0,0,0,.22);color:#090f20}
        .profile-title{margin:0 0 28px;font-size:18px;font-weight:500}.profile-identity{display:flex;align-items:center;gap:18px;margin-bottom:30px}.profile-avatar{display:grid;width:72px;height:72px;flex:0 0 72px;place-items:center;overflow:hidden;border-radius:999px;background:#d9d9d9;color:#111827;font-size:22px}.profile-avatar img{width:100%;height:100%;object-fit:cover}.profile-person-name{font-size:18px;font-weight:600}.profile-person-role{margin-top:5px;font-size:13px}
        .profile-section-head{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:20px}.profile-section-title{margin:0;font-size:17px;font-weight:500}.profile-pill{display:inline-flex;align-items:center;justify-content:center;gap:4px;min-width:78px;box-sizing:border-box;border:1px solid #111827;border-radius:999px;background:#fff;padding:3px 12px;color:#090f20;font:inherit;font-size:12px;line-height:1.25;text-decoration:none;cursor:pointer}.profile-pill:hover{background:#f3f4f6}.profile-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px 28px;max-width:590px}.profile-field label,.profile-password-field label{display:block;margin-bottom:3px;font-size:12px}.profile-field input,.profile-password-field input{box-sizing:border-box;width:100%;height:29px;border:1px solid #9ca3af;border-radius:6px;background:#fff;padding:3px 7px;color:#111827;font:inherit;font-size:12px}.profile-field input:read-only{color:#111827}.profile-avatar-input{display:none;margin-top:9px;font-size:11px}.profile-form.is-editing .profile-avatar-input{display:block}.profile-password-section{margin-top:48px}.profile-password-section .profile-section-head{margin-bottom:20px}.profile-password-field{width:100%;max-width:369px}.profile-password-preview{margin-top:20px}.profile-password-form{display:none}.profile-password-fields{display:grid;width:min(100%,369px);gap:26px}.profile-password-fields .profile-password-field label{font-size:16px;font-weight:500}.profile-password-fields .profile-password-field input{height:36px;border-radius:10px;background:#ededed;padding:5px 10px;font-size:15px}.profile-password-actions{display:none;align-items:center;gap:13px}.profile-password-section.is-changing .profile-password-edit-action,.profile-password-section.is-changing .profile-password-preview{display:none}.profile-password-section.is-changing .profile-password-actions,.profile-password-section.is-changing .profile-password-form{display:flex}.profile-password-section.is-changing .profile-password-form{display:block}.profile-password-error{margin:4px 0 0;color:#dc2626;font-size:11px}.profile-logout{margin-top:30px;border:0;border-radius:999px;background:#669df6;padding:8px 20px;color:#fff;font:inherit;font-size:12px;font-weight:600;cursor:pointer}.profile-alert{margin-bottom:20px;border-radius:7px;background:#dcfce7;padding:10px 14px;color:#166534;font-size:13px}.profile-errors{margin-bottom:20px;border-radius:7px;background:#fee2e2;padding:10px 14px;color:#991b1b;font-size:13px}
        @media(max-width:767px){.profile-card{padding:30px 20px}.profile-grid{grid-template-columns:1fr}.profile-section-head{align-items:flex-start}.profile-password-section{margin-top:38px}}
        .super-panel .admin-main{padding-top:80px}.super-panel .profile-card{max-width:990px;min-height:795px;padding:80px 20px 40px}.super-panel .profile-grid{max-width:622px;gap:15px 32px}.super-panel .profile-avatar{width:80px;height:80px;flex-basis:80px}.super-panel .profile-identity{margin-bottom:42px}.super-panel .profile-password-section{margin-top:58px}
    </style>

    <section class="profile-card">
        <h1 class="profile-title">Profil</h1>

        @if(session('success'))
            <div class="profile-alert">{{ session('success') }}</div>
        @endif
        @if($user->must_change_password)
            <div class="profile-errors" role="alert">Password sementara wajib diganti sebelum membuka dashboard. Gunakan formulir Password di bawah ini.</div>
        @endif
        @if($errors->any())
            <div class="profile-errors">{{ $errors->first() }}</div>
        @endif

        <form method="POST" enctype="multipart/form-data" action="{{ route($profilePrefix.'.profile.update') }}" class="profile-form" data-profile-form>
            @csrf
            @method('PUT')

            <div class="profile-identity">
                <div class="profile-avatar">
                    @if($user->avatar_path)
                        <img src="{{ Storage::url($user->avatar_path) }}" alt="Foto profil {{ $user->name }}">
                    @else
                        {{ strtoupper(substr($user->nama_depan ?: 'A', 0, 1)) }}
                    @endif
                </div>
                <div>
                    <div class="profile-person-name">{{ $user->name ?: 'User Admin' }}</div>
                    <div class="profile-person-role">{{ $user->jabatan ?: 'Karyawan' }}</div>
                    <input type="file" name="avatar" accept="image/*" class="profile-avatar-input" data-profile-input disabled>
                </div>
            </div>

            <div class="profile-section-head">
                <h2 class="profile-section-title">Informasi Pribadi</h2>
                <button type="button" class="profile-pill" data-profile-edit><span aria-hidden="true">✎</span><span data-profile-edit-label>Edit</span></button>
            </div>

            <div class="profile-grid">
                @foreach(['nama_depan'=>'Nama Depan','nama_belakang'=>'Nama Belakang','email'=>'Email','no_telephone'=>'No Telephone','jabatan'=>'Jabatan','alamat'=>'Alamat'] as $name=>$label)
                    <div class="profile-field">
                        <label for="profile-{{ $name }}">{{ $label }}</label>
                        <input id="profile-{{ $name }}" name="{{ $name }}" value="{{ old($name, $user->$name) }}" readonly data-profile-input>
                    </div>
                @endforeach
            </div>
        </form>

        <section
            @class([
                'profile-password-section',
                'is-changing' => $passwordFormOpen || $user->must_change_password,
            ])
            data-password-section
        >
            <div class="profile-section-head">
                <h2 class="profile-section-title">Password</h2>

                <button type="button" class="profile-pill profile-password-edit-action" data-password-edit>
                    <span aria-hidden="true">✎</span> Ubah Password
                </button>

                <div class="profile-password-actions">
                    <button type="submit" form="profile-password-change-form" class="profile-pill">Simpan</button>
                    <button type="button" class="profile-pill" data-password-cancel>Batal</button>
                </div>
            </div>

            <div class="profile-password-field profile-password-preview">
                <label for="profile-password">Password</label>
                <input id="profile-password" type="text" value="********" readonly aria-label="Password tersamarkan">
            </div>

            <form
                id="profile-password-change-form"
                method="POST"
                action="{{ route($profilePrefix.'.password.update') }}"
                class="profile-password-form"
            >
                @csrf
                @method('PUT')
                <input type="hidden" name="password_form" value="1">

                <div class="profile-password-fields">
                    <div class="profile-password-field">
                        <label for="profile-current-password">Password Lama</label>
                        <input
                            id="profile-current-password"
                            type="password"
                            name="current_password"
                            required
                            autocomplete="current-password"
                        >
                        @error('current_password')
                            <p class="profile-password-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="profile-password-field">
                        <label for="profile-new-password">Password Baru</label>
                        <input
                            id="profile-new-password"
                            type="password"
                            name="password"
                            required
                            autocomplete="new-password"
                        >
                        @error('password')
                            <p class="profile-password-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="profile-password-field">
                        <label for="profile-password-confirmation">Konfirmasi Password Baru</label>
                        <input
                            id="profile-password-confirmation"
                            type="password"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                        >
                    </div>
                </div>
            </form>
        </section>

        <form method="POST" action="{{ route($profilePrefix.'.logout') }}">
            @csrf
            <button type="submit" class="profile-logout">Logout</button>
        </form>
    </section>

    <script>
        (function(){
            var form=document.querySelector('[data-profile-form]');
            var editButton=document.querySelector('[data-profile-edit]');
            if(!form||!editButton)return;
            editButton.addEventListener('click',function(){
                if(form.classList.contains('is-editing')){
                    form.requestSubmit();
                    return;
                }
                form.classList.add('is-editing');
                form.querySelectorAll('[data-profile-input]').forEach(function(input){
                    if(input.type==='file')input.disabled=false;
                    else input.readOnly=false;
                });
                editButton.querySelector('[data-profile-edit-label]').textContent='Simpan';
            });
        })();

        (function(){
            var section=document.querySelector('[data-password-section]');
            var editButton=document.querySelector('[data-password-edit]');
            var cancelButton=document.querySelector('[data-password-cancel]');
            var passwordForm=document.getElementById('profile-password-change-form');
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
</x-dynamic-component>
