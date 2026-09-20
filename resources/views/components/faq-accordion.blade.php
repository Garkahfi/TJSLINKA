@props(['faqs'])

<div class="faq-frame">
    @foreach($faqs as $faq)
        @php
            $answerId = 'faq-answer-'.($faq['id'] ?? $loop->iteration);
        @endphp
        <article class="faq-item">
            <button
                type="button"
                class="faq-question"
                data-faq-button
                aria-expanded="true"
                aria-controls="{{ $answerId }}"
            >
                <span>{{ $faq['question'] }}</span>
                <svg
                    class="faq-chevron"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 15 7-7 7 7"/>
                </svg>
            </button>
            <div id="{{ $answerId }}" class="faq-answer">
                {{ $faq['answer'] }}
            </div>
        </article>
    @endforeach
</div>

@once
    @push('head')
        <style>
            .faq-frame{border:2px solid #202020;background:#fff;padding:8px}
            .faq-item{border:1px solid #202020;background:#fff}
            .faq-item+.faq-item{margin-top:8px}
            .faq-question{display:flex;width:100%;align-items:center;justify-content:space-between;gap:24px;border:0;background:#fff;padding:18px 16px 12px;color:#050505;font:700 22px/1.35 Poppins,sans-serif;text-align:left;cursor:pointer}
            .faq-question:hover{background:#fafafa}
            .faq-question:focus-visible{outline:3px solid rgba(169,45,47,.25);outline-offset:-3px}
            .faq-chevron{width:28px;height:28px;flex:none;transition:transform .2s ease}
            .faq-question[aria-expanded="false"] .faq-chevron{transform:rotate(180deg)}
            .faq-answer{padding:12px 16px 18px;color:#111;font-size:17px;line-height:1.45}
            .faq-answer.hidden{display:none}

            @media(max-width:720px){
                .faq-frame{padding:6px}
                .faq-item+.faq-item{margin-top:6px}
                .faq-question{gap:14px;padding:15px 12px 10px;font-size:17px}
                .faq-chevron{width:23px;height:23px}
                .faq-answer{padding:9px 12px 15px;font-size:14px}
            }
        </style>
    @endpush
@endonce
