<script>
    (function () {
        try {
            const stored = localStorage.getItem('hims_theme');
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored === 'dark' || (!stored && prefersDark) || (stored === 'system' && prefersDark)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        } catch (e) {
            // Guard against restricted localStorage in private browsing or iframe sandboxes
        }
    })();
</script>
