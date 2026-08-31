<div class="row-fluid">
    <div id="footer" class="span12">
        <a class="pecolor" href="https://github.com/RamonSilva20/mapos" target="_blank">
            <?= date('Y') ?> &copy; Ramon Silva - Map-OS - Versão: <?= $this->config->item('app_version') ?>
        </a>
    </div>
</div>
<!--end-Footer-part-->
<script src="<?= base_url() ?>assets/js/bootstrap.min.js"></script>
<script src="<?= base_url() ?>assets/js/matrix.js"></script>
<script>
(function () {
    const csrfName = <?= json_encode($this->security->get_csrf_token_name()) ?>;
    const csrfHash = <?= json_encode($this->security->get_csrf_hash()) ?>;

    /*
     * Adiciona automaticamente CSRF a todos os formulários POST
     * que ainda não possuem token.
     */
    function addCsrfToForms() {
        document.querySelectorAll('form').forEach(function (form) {
            const method = (form.getAttribute('method') || 'GET').toUpperCase();

            if (method !== 'POST') {
                return;
            }

            if (form.querySelector('input[name="' + csrfName + '"]')) {
                return;
            }

            const input = document.createElement('input');

            input.type = 'hidden';
            input.name = csrfName;
            input.value = csrfHash;

            form.appendChild(input);
        });
    }

    document.addEventListener('DOMContentLoaded', addCsrfToForms);

    /*
     * Também cobre formulários/modais adicionados dinamicamente.
     */
    const observer = new MutationObserver(function () {
        addCsrfToForms();
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true
    });

    /*
     * Proteção global para requisições AJAX jQuery POST.
     */
    if (typeof jQuery !== 'undefined') {
        jQuery.ajaxPrefilter(function (options, originalOptions) {
            const method = (options.type || options.method || 'GET').toUpperCase();

            if (method !== 'POST') {
                return;
            }

            if (typeof options.data === 'string') {
                const encodedName = encodeURIComponent(csrfName);

                if (!options.data.includes(encodedName + '=')) {
                    options.data +=
                        (options.data ? '&' : '') +
                        encodedName +
                        '=' +
                        encodeURIComponent(csrfHash);
                }
            } else {
                options.data = options.data || {};

                if (typeof options.data[csrfName] === 'undefined') {
                    options.data[csrfName] = csrfHash;
                }
            }
        });
    }
})();
</script>
</body>
<script type="text/javascript">
    $(document).ready(function() {
        var dataTableEnabled = '<?= $configuration['control_datatable'] ?>';
        if(dataTableEnabled == '1') {
            $('#tabela').dataTable( {
                "pageLength": <?= $configuration['per_page'] ?>,
                "ordering": false,
                "info": false,
                "language": {
                    "url": "<?= base_url() ?>assets/js/dataTable_pt-br.json",
                },
                "oLanguage": {
                    "sSearch": "Pesquisa rápida na tabela abaixo:"
                }
            } );
        }
    } );
</script>
</html>
