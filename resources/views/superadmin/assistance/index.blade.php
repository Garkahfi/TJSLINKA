<x-layouts.admin title="Status Bantuan TJSL">
    @include('admin.partials.status-badge-styles')

    <div class="mx-auto max-w-275">
        <h1 class="mb-7 text-center text-3xl font-bold">Bantuan TJSL INKA</h1>

        @include('admin.assistance.partials.pillar-grouped-list', [
            'detailRouteName' => 'superadmin.assistance.show',
            'showCreator' => true,
            'emptyMessage' => 'Belum ada bantuan yang perlu ditinjau.',
        ])
    </div>
</x-layouts.admin>
