<x-layouts.admin title="Status Bantuan TJSL">
    @include('admin.partials.status-badge-styles')

    <div class="mx-auto max-w-275">
        <h1 class="mb-7 text-3xl font-bold">Bantuan TJSL</h1>

        @php($resultCount = $bantuanTanpaPilar->count() + $bantuanPerPilar->sum(fn ($pillar) => $pillar->bantuanCsr->count()))
        @include('admin.partials.status-filter', [
            'routeName' => 'admin.assistance.index',
            'filterId' => 'admin-assistance-status-filter',
            'selectedPillar' => null,
            'resultCount' => $resultCount,
            'resultLabel' => 'bantuan',
        ])

        @include('admin.assistance.partials.pillar-grouped-list', [
            'detailRouteName' => 'admin.assistance.show',
            'showCreator' => false,
            'emptyMessage' => 'Belum ada Bantuan TJSL dengan status '.$statusOptions[$statusFilter].'.',
        ])
    </div>
</x-layouts.admin>
