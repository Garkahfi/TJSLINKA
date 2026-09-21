<x-layouts.admin title="Status Bantuan TJSL">
    @include('admin.partials.status-badge-styles')

    <div class="mx-auto max-w-275">
        <h1 class="mb-7 text-center text-3xl font-bold">Bantuan TJSL INKA</h1>

        @php($resultCount = $bantuanTanpaPilar->count() + $bantuanPerPilar->sum(fn ($pillar) => $pillar->bantuanCsr->count()))
        @include('admin.partials.status-filter', [
            'routeName' => 'superadmin.assistance.index',
            'filterId' => 'superadmin-assistance-status-filter',
            'selectedPillar' => null,
            'resultCount' => $resultCount,
            'resultLabel' => 'bantuan',
        ])

        @include('admin.assistance.partials.pillar-grouped-list', [
            'detailRouteName' => 'superadmin.assistance.show',
            'showCreator' => true,
            'emptyMessage' => 'Belum ada Bantuan TJSL dengan status '.$statusOptions[$statusFilter].'.',
        ])
    </div>
</x-layouts.admin>
