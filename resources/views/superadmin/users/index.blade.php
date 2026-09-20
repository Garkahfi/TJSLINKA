<x-layouts.admin title="User">
    <style>
        .user-page { max-width: 1080px; margin: auto; }
        .user-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
        }
        .user-add {
            padding: 11px 18px;
            border-radius: 7px;
            background: #2653ff;
            color: #fff;
            text-decoration: none;
        }
        .user-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            box-shadow: 0 2px 7px #0002;
        }
        .user-table th,
        .user-table td {
            padding: 14px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        .user-role {
            display: inline-flex;
            padding: 4px 9px;
            border-radius: 999px;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
        }
        .user-role-admin { background: #dc2626; }
        .user-role-pumk { background: #0f766e; }
        .user-actions { display: flex; gap: 9px; }
        .user-actions a,
        .user-actions button {
            border: 0;
            background: transparent;
            font-size: 18px;
            cursor: pointer;
            text-decoration: none;
        }
        .inactive { color: #9ca3af; }
        @media (max-width: 800px) {
            .user-table-wrap { overflow-x: auto; }
            .user-table { min-width: 760px; }
        }
    </style>

    <div class="user-page">
        <div class="user-head">
            <h1>User</h1>
            <a class="user-add" href="{{ route('superadmin.users.create') }}">+ Tambah User</a>
        </div>

        @if(session('success'))
            <p>{{ session('success') }}</p>
        @endif

        <div class="user-table-wrap">
            <table class="user-table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Jabatan</th>
                        <th>Tipe Akun</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $account)
                        <tr class="{{ ! $account->is_active ? 'inactive' : '' }}">
                            <td>{{ $account->name }}</td>
                            <td>{{ $account->email }}</td>
                            <td>{{ $account->jabatan }}</td>
                            <td>
                                <span class="user-role {{ $account->role === 'pumk_admin' ? 'user-role-pumk' : 'user-role-admin' }}">
                                    {{ $roleLabels[$account->role] }}
                                </span>
                            </td>
                            <td>{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                            <td>
                                <div class="user-actions">
                                    <a href="{{ route('superadmin.users.edit', $account) }}" title="Edit">✎</a>
                                    @if($account->is_active)
                                        <form
                                            method="POST"
                                            action="{{ route('superadmin.users.destroy', $account) }}"
                                            onsubmit="return confirm('Nonaktifkan akun ini?')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Nonaktifkan">♲</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Belum ada akun Admin.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
