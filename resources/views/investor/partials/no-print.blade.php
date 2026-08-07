{{-- Blank out printed pages; soft-block Ctrl/Cmd+P. Cannot stop all capture methods. --}}
<style>
    @media print {
        html, body {
            background: #fff !important;
        }
        body * {
            visibility: hidden !important;
        }
        body::before {
            content: "Printing is not permitted.\\A\\A Checkout investor materials are confidential.\\A This page intentionally left blank when printed.";
            visibility: visible !important;
            white-space: pre-line;
            display: block;
            position: fixed;
            inset: 0;
            padding: 3rem 2rem;
            font-family: system-ui, sans-serif;
            font-size: 14pt;
            font-weight: 600;
            color: #111;
            background: #fff;
            text-align: center;
            line-height: 1.5;
        }
    }
</style>
<script>
    (function () {
        function blockPrint(e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'p' || e.key === 'P')) {
                e.preventDefault();
                e.stopPropagation();
                alert('Printing is disabled for confidential investor materials.');
                return false;
            }
        }
        document.addEventListener('keydown', blockPrint, true);
        window.addEventListener('beforeprint', function () {
            document.documentElement.setAttribute('data-print-blocked', '1');
        });
        window.addEventListener('afterprint', function () {
            document.documentElement.removeAttribute('data-print-blocked');
        });
    })();
</script>
