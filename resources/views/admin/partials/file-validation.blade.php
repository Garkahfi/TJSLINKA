@php
    $formId = (string) ($formId ?? '');
    $isProgramUploadForm = $formId === 'program-form';
    $restrictDocumentExtensions = in_array($formId, [
        'program-form',
        'assistance-form',
        'assistance-bast-form',
    ], true);
    $requiredUploadFields = $isProgramUploadForm
        ? ['nama_program','deskripsi_program','sasaran_program','lokasi_program','mitra_program','rencana_anggaran','pillar_id']
        : ['nama_program_bantuan','deskripsi_bantuan','pillar_id','rencana_anggaran'];
@endphp
<template
    data-file-validation-config
    data-form-id="{{ $formId }}"
    data-program-upload-form="{{ $isProgramUploadForm ? 'true' : 'false' }}"
    data-restrict-document-extensions="{{ $restrictDocumentExtensions ? 'true' : 'false' }}"
    data-required-upload-fields="{{ implode(',', $requiredUploadFields) }}"
></template>
<script>
(function () {
    const config = document.currentScript?.previousElementSibling;
    if (!(config instanceof HTMLTemplateElement)) return;

    const formId = config.dataset.formId;
    if (!formId) return;

    const form = document.getElementById(formId);
    if (!form) return;

    const isProgramUploadForm = config.dataset.programUploadForm === 'true';
    const restrictDocumentExtensions = config.dataset.restrictDocumentExtensions === 'true';
    const photoLimit = 20 * 1024 * 1024;
    const documentLimit = 10 * 1024 * 1024;
    const programDocumentExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];

    function validateFileInput(input) {
        input.setCustomValidity('');
        const isPhoto = input.name.includes('photos') || input.name.includes('[foto]');
        const limit = isPhoto ? photoLimit : documentLimit;

        for (const file of input.files) {
            if (file.size > limit) {
                input.setCustomValidity(`${file.name} terlalu besar. Maksimal ${isPhoto ? '20 MB' : '10 MB'}.`);
                return false;
            }
            if (isPhoto && !file.type.startsWith('image/')) {
                input.setCustomValidity(`${file.name} harus berupa file gambar.`);
                return false;
            }
            if (restrictDocumentExtensions && !isPhoto) {
                const extension = file.name.split('.').pop().toLowerCase();
                if (!programDocumentExtensions.includes(extension)) {
                    input.setCustomValidity(`${file.name} harus berformat PDF, DOC, DOCX, XLS, atau XLSX.`);
                    return false;
                }
            }
        }
        return true;
    }

    const requiredNames = (config.dataset.requiredUploadFields || '').split(',').filter(Boolean);
    requiredNames.forEach(function (name) {
        const field = form.querySelector(`[name="${name}"]`);
        if (field && !field.disabled) field.required = true;
    });
    if (isProgramUploadForm) {
        form.querySelectorAll('[name^="tujuan["][name$="[deskripsi]"]').forEach(function (field) {
            if (!field.disabled) field.required = true;
        });
    }
    if (!isProgramUploadForm) {
        form.querySelectorAll('[name="targets[]"], [name^="details["]').forEach(function (field) {
            if (!field.disabled && field.type !== 'file' && !field.name.endsWith('[photo_caption]')) field.required = true;
        });
    }

    form.addEventListener('change', function (event) {
        if (event.target.matches('input[type="file"]')) validateFileInput(event.target);
    });
    form.addEventListener('submit', function (event) {
        let valid = true;
        form.querySelectorAll('input[type="file"]').forEach(function (input) {
            if (!validateFileInput(input)) valid = false;
        });
        if (!valid || !form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
        }
    });
})();
</script>
