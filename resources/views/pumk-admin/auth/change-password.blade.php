<x-layouts.pumk-admin title="Ganti Password Admin PUMK">
    <div class="pumk-page" style="max-width:560px">
        <h1 class="pumk-page-title">Ganti Password</h1>
        <p class="pumk-page-subtitle">Gunakan password baru sebelum mengakses data PUMK.</p>

        <form method="POST" action="{{ route('pumk-admin.password.update') }}" class="pumk-card" style="margin-top:24px;padding:28px;display:grid;gap:18px">
            @csrf
            @method('PUT')
            @if(session('password_change_required'))
                <p class="pumk-alert error" role="alert">{{ session('password_change_required') }}</p>
            @endif
            <label style="display:grid;gap:6px">Password saat ini
                <input type="password" name="current_password" required autocomplete="current-password" style="padding:10px;border:1px solid #94a3b8;border-radius:7px">
                @error('current_password') <span style="color:#b91c1c">{{ $message }}</span> @enderror
            </label>
            <label style="display:grid;gap:6px">Password baru
                <input type="password" name="password" required autocomplete="new-password" style="padding:10px;border:1px solid #94a3b8;border-radius:7px">
                @error('password') <span style="color:#b91c1c">{{ $message }}</span> @enderror
            </label>
            <label style="display:grid;gap:6px">Konfirmasi password baru
                <input type="password" name="password_confirmation" required autocomplete="new-password" style="padding:10px;border:1px solid #94a3b8;border-radius:7px">
            </label>
            <p class="pumk-page-subtitle">Minimal 8 karakter, mengandung huruf dan angka, serta berbeda dari password saat ini.</p>
            <button class="pumk-primary-button" type="submit">Simpan Password Baru</button>
        </form>
    </div>
</x-layouts.pumk-admin>
