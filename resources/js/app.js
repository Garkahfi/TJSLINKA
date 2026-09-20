document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-pillar-hero]').forEach((hero) => {
        const slides = JSON.parse(hero.dataset.slides || '[]');
        const title = hero.querySelector('[data-pillar-title]');
        const description = hero.querySelector('[data-pillar-description]');
        let index = 0;
        if (!slides.length) return;
        window.setInterval(() => {
            title.parentElement.classList.add('opacity-0', 'translate-y-2');
            window.setTimeout(() => {
                index = (index + 1) % slides.length;
                title.textContent = slides[index].title;
                description.textContent = slides[index].description;
                title.parentElement.classList.remove('opacity-0', 'translate-y-2');
            }, 350);
        }, 4200);
    });
    document.querySelectorAll('[data-menu-button]').forEach((button) => button.addEventListener('click', () => document.querySelector('[data-mobile-menu]')?.classList.toggle('hidden')));
    document.querySelectorAll('[data-faq-button]').forEach((button) => button.addEventListener('click', () => {
        const answer = button.nextElementSibling;
        const isExpanded = button.getAttribute('aria-expanded') === 'true';

        button.setAttribute('aria-expanded', String(!isExpanded));
        answer?.classList.toggle('hidden', isExpanded);
    }));
    const adminSidebar = document.querySelector('[data-admin-sidebar]');
    const adminOverlay = document.querySelector('[data-admin-overlay]');
    document.querySelectorAll('[data-admin-menu-button]').forEach((button) => button.addEventListener('click', () => {
        adminSidebar?.classList.toggle('-translate-x-full');
        adminOverlay?.classList.toggle('hidden');
    }));
    adminOverlay?.addEventListener('click', () => {
        adminSidebar?.classList.add('-translate-x-full');
        adminOverlay.classList.add('hidden');
    });

    document.querySelector('[data-add-target]')?.addEventListener('click', () => {
        const wrapper = document.querySelector('[data-repeat-targets]');
        const row = document.createElement('div');
        row.className = 'mb-2 flex gap-2';
        row.innerHTML = '<textarea name="targets[]" required class="min-h-16 flex-1 rounded bg-[#eeeeee] p-3"></textarea><button type="button" class="rounded bg-action-light px-4 text-white" data-remove-row>Hapus</button>';
        wrapper?.append(row);
    });
    document.querySelector('[data-add-detail]')?.addEventListener('click', () => {
        const wrapper = document.querySelector('[data-repeat-details]');
        const index = wrapper?.querySelectorAll('fieldset').length ?? 0;
        const fieldset = document.createElement('fieldset');
        fieldset.className = 'mt-7 rounded-lg border p-4';
        fieldset.innerHTML = `<legend class="px-2 text-xl font-semibold">Rincian ${index + 1}</legend>${[['rincian_kegiatan','Rincian Kegiatan'],['penerima_bantuan','Penerima Bantuan'],['jenis_bantuan','Jenis Bantuan'],['quality','Quality'],['nominal_bantuan','Nominal Bantuan']].map(([name,label]) => `<label class="grid gap-3 py-2 md:grid-cols-[220px_1fr]"><span>${label}</span><input required name="details[${index}][${name}]" type="${name === 'nominal_bantuan' ? 'number' : 'text'}" class="h-11 rounded bg-[#eeeeee] px-3"></label>`).join('')}<label class="grid gap-3 py-2 md:grid-cols-[220px_1fr]"><span>Foto Dokumentasi</span><input type="file" multiple name="details[${index}][photos][]" accept="image/*" class="rounded bg-[#eeeeee] p-2"></label><button type="button" data-remove-row class="mt-2 rounded bg-action-light px-4 py-2 text-white">Hapus Rincian</button>`;
        wrapper?.append(fieldset);
    });
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-remove-row]')) event.target.closest('[data-remove-row]').parentElement.remove();
    });
});
