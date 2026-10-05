    <style>
        .program-monitoring .program-kind-badge{display:inline-flex;align-items:center;margin-right:6px;border-radius:999px;padding:3px 9px;color:#fff;font-size:11px;font-weight:700;line-height:1.2;vertical-align:middle}
        .program-monitoring .program-kind-badge.internal{background:#dc2626}
        .program-monitoring .program-kind-badge.csr{background:#7c3aed}
        .program-monitoring .program-kind-badge.cooperation{background:#6b7280}
        .program-monitoring .program-kind-badge.rejected{background:#6b7280}
        .program-monitoring .document-check{display:inline-grid;width:24px;height:24px;place-items:center;border-radius:5px;color:#fff;font-weight:800;line-height:1}
        .program-monitoring .document-check.complete{background:#16a34a}
        .program-monitoring .document-check.incomplete{background:#9ca3af}
        .program-monitoring .document-check.in-progress{background:#f59e0b}
        .program-monitoring .document-check.in-progress svg{width:16px;height:16px}
        .program-monitoring .document-na{display:inline-block;color:#cbd5e1;font-size:25px;font-weight:500;line-height:1}
        .program-monitoring .monitoring-search-field{display:flex;min-height:46px;align-items:center;gap:10px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;padding:0 14px;transition:border-color .15s,box-shadow .15s}
        .program-monitoring .monitoring-search-field:focus-within{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.13)}
        .program-monitoring .monitoring-search-field svg{width:21px;height:21px;flex:0 0 auto;color:#64748b}
        .program-monitoring .monitoring-search-field input{min-width:0;flex:1;border:0;background:transparent;padding:10px 0;color:#0f172a;outline:0}
        .program-monitoring .monitoring-search-field input::placeholder{color:#94a3b8}
        .program-monitoring .monitoring-search-clear{border-radius:7px;background:#e2e8f0;padding:7px 11px;color:#334155;font-size:12px;font-weight:700;text-decoration:none}
        .program-monitoring .monitoring-search-submit{border:0;border-radius:7px;background:#2563eb;padding:8px 16px;color:#fff;font-size:12px;font-weight:700;cursor:pointer}
        .program-monitoring .monitoring-search-submit:hover{background:#1d4ed8}
        .program-monitoring .monitoring-search-hint{margin-top:7px;color:#64748b;font-size:11px}
        .program-monitoring .monitoring-meta{margin-top:4px;color:#64748b;font-size:11px}
        .program-monitoring .rejection-open{display:inline-grid;width:34px;height:34px;place-items:center;border:1px solid #cbd5e1;border-radius:999px;background:#fff;color:#334155;cursor:pointer}
        .program-monitoring .rejection-open:hover{border-color:#dc2626;background:#fef2f2;color:#b91c1c}
        .program-monitoring .rejection-open svg{width:20px;height:20px}
        .rejection-dialog{width:min(460px,calc(100vw - 32px));max-width:460px;padding:0;border:0;border-radius:14px;background:transparent;box-shadow:0 24px 80px rgba(15,23,42,.3)}
        .rejection-dialog::backdrop{background:rgba(15,23,42,.58)}
        .rejection-dialog-card{overflow:hidden;border:1px solid #fecaca;border-radius:14px;background:#fff}
        .rejection-dialog-header{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 20px;background:#fef2f2;color:#991b1b}
        .rejection-dialog-close{display:grid;width:32px;height:32px;place-items:center;border:0;border-radius:999px;background:#fff;color:#991b1b;font-size:22px;cursor:pointer}
        .rejection-dialog-body{display:grid;gap:14px;padding:20px;color:#334155}
        .rejection-dialog-label{display:block;margin-bottom:4px;color:#64748b;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
        .rejection-dialog-reason{border-left:3px solid #dc2626;border-radius:4px;background:#fef2f2;padding:10px 12px;color:#7f1d1d;line-height:1.55}
    </style>
