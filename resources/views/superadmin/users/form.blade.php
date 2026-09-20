<x-layouts.admin :title="$account->exists ? 'Edit User' : 'Tambah User'">
    <style>
        .account-card {
            max-width: 720px;
            margin: auto;
            padding: 30px;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 2px 7px #0002;
        }
        .account-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .account-field { display: block; }
        .account-field span {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
        }
        .account-field input,
        .account-field select,
        .account-field textarea {
            box-sizing: border-box;
            width: 100%;
            border: 1px solid #9ca3af;
            border-radius: 7px;
            padding: 10px;
            background: #fff;
            font: inherit;
        }
        .account-field.full { grid-column: 1 / -1; }
        .account-help {
            display: block;
            margin-top: 6px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
        }
        .account-error {
            margin: 0 0 18px;
            color: #dc2626;
        }
        .account-save {
            margin-top: 22px;
            border: 0;
            border-radius: 7px;
            padding: 11px 22px;
            background: #2653ff;
            color: #fff;
            font-weight: 600;
        }
        @media (max-width: 650px) {
            .account-grid { grid-template-columns: 1fr; }
        }
    </style>

    <form
        method="POST"
        action="{{ $account->exists ? route('superadmin.users.update', $account) : route('superadmin.users.store') }}"
        class="account-card"
    >
        @csrf
        @if($account->exists)
            @method('PUT')
        @endif

        <h1>{{ $account->exists ? 'Edit User' : 'Tambah User' }}</h1>

        @if($errors->any())
            <p class="account-error">{{ $errors->first() }}</p>
        @endif

        <div class="account-grid">
            <label class="account-field full">
                <span>Tipe Akun Admin</span>
                <select name="role" required>
                    @foreach($roleLabels as $role => $label)
                        <option value="{{ $role }}" @selected(old('role', $account->role ?: 'admin') === $role)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <small class="account-help">
                    Admin TJSL mengakses dashboard pengelolaan Program dan Bantuan TJSL. Admin PUMK mengakses dashboard Kartu Piutang PUMK.
                </small>
            </label>

            @foreach([
                'nama_depan' => 'Nama Depan',
                'nama_belakang' => 'Nama Belakang',
                'username' => 'Username',
                'email' => 'Email',
                'jabatan' => 'Jabatan',
                'no_telephone' => 'No Telephone',
            ] as $name => $label)
                <label class="account-field">
                    <span>{{ $label }}</span>
                    <input
                        name="{{ $name }}"
                        value="{{ old($name, $account->$name) }}"
                        @required(in_array($name, ['nama_depan', 'nama_belakang', 'username', 'email'], true))
                    >
                </label>
            @endforeach

            <label class="account-field full">
                <span>Alamat</span>
                <textarea name="alamat">{{ old('alamat', $account->alamat) }}</textarea>
            </label>

            @unless($account->exists)
                <label class="account-field full">
                    <span>Password Sementara</span>
                    <input type="password" name="password" required minlength="8">
                </label>
            @endunless
        </div>

        <button class="account-save" type="submit">Simpan</button>
    </form>
</x-layouts.admin>
