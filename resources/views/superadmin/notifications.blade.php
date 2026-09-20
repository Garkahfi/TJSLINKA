<x-layouts.admin title="Notifikasi">
    <style>
        .notif-page{max-width:1338px;margin:auto}
        .notif-card{display:block;min-height:82px;margin:10px 0;padding:12px 10px;border:1px solid #111;border-radius:6px;background:#fff;color:#111;text-decoration:none}
        .notif-card strong{display:block;font-size:19px}
        .notif-card p{margin:9px 0 0;font-size:14px}
        .notif-card.unread{border-left:5px solid #2653ff}
    </style>

    <div class="notif-page">
        @forelse($items as $item)
            @php
                $url = match ($item->related_type) {
                    'program' => route('superadmin.programs.show', $item->related_id),
                    'bantuan_csr' => route('superadmin.assistance.show', $item->related_id),
                    default => route('superadmin.notifications'),
                };
            @endphp

            <a href="{{ $url }}" class="notif-card {{ ! $item->is_read ? 'unread' : '' }}">
                <strong>{{ $item->title }}</strong>
                <p>{{ $item->message }}</p>
            </a>
        @empty
            <p>Belum ada notifikasi.</p>
        @endforelse
    </div>
</x-layouts.admin>
