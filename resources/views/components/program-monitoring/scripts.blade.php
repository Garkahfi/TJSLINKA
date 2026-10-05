@if($showRejectedInfo)
    <script>
        document.querySelectorAll('[data-rejection-open]').forEach(function (button) {
            if (button.dataset.rejectionBound === 'true') return;
            button.dataset.rejectionBound = 'true';
            button.addEventListener('click', function () {
                document.getElementById(button.dataset.rejectionOpen)?.showModal();
            });
        });

        document.querySelectorAll('[data-rejection-dialog]').forEach(function (dialog) {
            if (dialog.dataset.rejectionBound === 'true') return;
            dialog.dataset.rejectionBound = 'true';
            dialog.querySelector('[data-rejection-close]')?.addEventListener('click', function () {
                dialog.close();
            });
            dialog.addEventListener('click', function (event) {
                if (event.target === dialog) dialog.close();
            });
        });
    </script>
@endif
