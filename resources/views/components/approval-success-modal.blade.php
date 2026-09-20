@props(['approval'])

@php
    $approvalType = in_array(data_get($approval, 'type'), ['internal', 'csr'], true)
        ? data_get($approval, 'type')
        : 'program';
    $approvalName = trim((string) data_get($approval, 'name', ''));
@endphp

<style>
    .approval-success-modal{position:fixed;inset:0;z-index:9999;display:grid;place-items:center;padding:24px;background:rgba(0,0,0,.52);opacity:0;visibility:hidden;transition:opacity .28s ease,visibility .28s ease}
    .approval-success-modal.is-visible{opacity:1;visibility:visible}
    .approval-success-modal.is-leaving{opacity:0;visibility:visible}
    .approval-success-card{box-sizing:border-box;display:flex;width:min(836px,calc(100vw - 32px));min-height:384px;flex-direction:column;align-items:center;justify-content:center;gap:70px;border-radius:26px;background:#fff;padding:58px 32px 54px;box-shadow:0 6px 18px rgba(15,23,42,.24);outline:0;transform:translateY(22px) scale(.94);opacity:0}
    .approval-success-modal.is-visible .approval-success-card{animation:approval-card-in .48s cubic-bezier(.2,.85,.32,1.16) forwards}
    .approval-success-icon{display:block;width:100px;height:100px;object-fit:contain;opacity:0;transform:translateY(-30px) rotate(-8deg) scale(.65)}
    .approval-success-modal.is-visible .approval-success-icon{animation:approval-icon-connect .72s .18s cubic-bezier(.2,.9,.26,1.25) forwards}
    .approval-success-message{display:block;width:min(476px,82vw);height:auto;object-fit:contain;opacity:0;transform:translateY(26px) scale(.96)}
    .approval-success-modal.is-visible .approval-success-message{animation:approval-message-connect .5s .58s ease-out forwards}
    .approval-success-sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
    @keyframes approval-card-in{to{opacity:1;transform:translateY(0) scale(1)}}
    @keyframes approval-icon-connect{0%{opacity:0;transform:translateY(-30px) rotate(-8deg) scale(.65)}62%{opacity:1;transform:translateY(5px) rotate(3deg) scale(1.08)}100%{opacity:1;transform:translateY(0) rotate(0) scale(1)}}
    @keyframes approval-message-connect{0%{opacity:0;transform:translateY(26px) scale(.96)}100%{opacity:1;transform:translateY(0) scale(1)}}
    @media(max-width:640px){.approval-success-modal{padding:16px}.approval-success-card{min-height:310px;gap:50px;border-radius:20px;padding:46px 20px}.approval-success-icon{width:84px;height:84px}.approval-success-message{width:min(476px,78vw)}}
    @media(prefers-reduced-motion:reduce){.approval-success-modal,.approval-success-card,.approval-success-icon,.approval-success-message{transition:none!important;animation:none!important}.approval-success-modal.is-visible,.approval-success-modal.is-visible .approval-success-card,.approval-success-modal.is-visible .approval-success-icon,.approval-success-modal.is-visible .approval-success-message{opacity:1;visibility:visible;transform:none}}
</style>

<div
    class="approval-success-modal"
    data-approval-success-modal
    data-approval-type="{{ $approvalType }}"
    role="dialog"
    aria-modal="true"
    aria-labelledby="approval-success-title"
    aria-describedby="approval-success-description"
>
    <div class="approval-success-card" data-approval-success-card tabindex="-1">
        <h2 id="approval-success-title" class="approval-success-sr-only">
            Program Anda Telah Berhasil Disimpan
        </h2>
        <p id="approval-success-description" class="approval-success-sr-only">
            Persetujuan final {{ $approvalType }}{{ $approvalName !== '' ? ' untuk '.$approvalName : '' }} berhasil disimpan.
        </p>
        <img
            src="{{ asset('images/superadmin/approval-success-icon.png') }}"
            class="approval-success-icon"
            alt=""
            aria-hidden="true"
        >
        <img
            src="{{ asset('images/superadmin/approval-success-message.png') }}"
            class="approval-success-message"
            alt=""
            aria-hidden="true"
        >
    </div>
</div>

<script>
(function () {
    var modal = document.querySelector('[data-approval-success-modal]');
    var card = modal?.querySelector('[data-approval-success-card]');
    var closeTimer;

    if (!modal || !card) return;

    function handleApprovalKeydown(event) {
        if (event.key === 'Escape') closeApprovalSuccess();
    }

    function closeApprovalSuccess() {
        if (modal.classList.contains('is-leaving')) return;
        window.clearTimeout(closeTimer);
        document.removeEventListener('keydown', handleApprovalKeydown);
        modal.classList.add('is-leaving');
        modal.classList.remove('is-visible');
        window.setTimeout(function () { modal.remove(); }, 300);
    }

    window.requestAnimationFrame(function () {
        modal.classList.add('is-visible');
        card.focus({ preventScroll: true });
        closeTimer = window.setTimeout(closeApprovalSuccess, 4200);
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) closeApprovalSuccess();
    });
    document.addEventListener('keydown', handleApprovalKeydown);
})();
</script>
