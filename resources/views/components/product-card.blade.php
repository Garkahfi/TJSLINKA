@props(['product'])

<article class="panel overflow-hidden bg-white">
    @if ($product->photo_url)
        @if (str_contains($product->foto_path, 'katalog-reference'))
            <div class="relative aspect-square overflow-hidden bg-white" role="img" aria-label="{{ $product->nama_produk }} dari {{ $product->nama_umkm }}">
                <img
                    class="absolute left-0 top-0 h-auto max-w-none"
                    style="width: 500%; transform: translate(-56.5%, -45.8%);"
                    src="{{ $product->photo_url }}"
                    alt=""
                    loading="lazy"
                >
            </div>
        @else
            <img
                class="aspect-square w-full bg-white object-cover"
                src="{{ $product->photo_url }}"
                alt="{{ $product->nama_produk }} dari {{ $product->nama_umkm }}"
                loading="lazy"
            >
        @endif
    @else
        <div class="grid aspect-square w-full place-items-center bg-slate-100 text-xs text-slate-400">Foto produk belum tersedia</div>
    @endif

    <div class="border-t border-slate-100 p-3">
        <h3 class="text-sm font-bold leading-tight">{{ $product->nama_produk }}</h3>
        <p class="mt-1 text-xs leading-tight text-slate-600">{{ $product->nama_umkm }}</p>
    </div>
</article>
