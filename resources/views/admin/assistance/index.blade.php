<x-layouts.admin title="Status Bantuan TJSL">
    @include('admin.partials.status-badge-styles')

    <div class="mx-auto max-w-275">
        <h1 class="mb-7 text-3xl font-bold">Bantuan TJSL</h1>

        @include('admin.assistance.partials.pillar-grouped-list', [
            'detailRouteName' => 'admin.assistance.show',
            'showCreator' => false,
            'emptyMessage' => 'Belum ada Bantuan TJSL.',
        ])
    </div>
</x-layouts.admin>
