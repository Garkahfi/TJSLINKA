<x-layouts.admin :title="ucfirst($pageStatus)">
    @php
        $titles = [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'draft-rejected' => 'Draft & Rejected',
        ];

        $colors = [
            'pending' => 'bg-[#f9a008]',
            'approved' => 'bg-[#e82428]',
            'draft-rejected' => 'bg-[#2f66e9]',
        ];
    @endphp

    <div class="mx-auto max-w-275">
        <h1 class="mb-8 text-center text-3xl font-bold">{{ $titles[$pageStatus] }}</h1>

        <div class="space-y-5">
            @foreach (range(1, 7) as $i)
                <article class="{{ $colors[$pageStatus] }} rounded-md p-4 text-white shadow">
                    <strong class="text-lg">Program Jaminan Sosial Bidang Ketenagakerjaan Bagi Pekerja Rentan</strong>
                    <p class="mt-1 text-sm">13.49 23/01/2026</p>
                </article>
            @endforeach
        </div>
    </div>
</x-layouts.admin>
