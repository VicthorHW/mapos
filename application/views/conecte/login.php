<!DOCTYPE html>
<html lang="pt-br">

<head>
    <title>TecNina | Área do Cliente</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="<?php echo $this->config->item('app_name') . ' - ' . $this->config->item('app_subname') ?>">
    <meta name="csrf-token-name" content="<?= config_item("csrf_token_name") ?>">
    <meta name="csrf-token" content="<?= html_escape($this->security->get_csrf_hash()) ?>">
    <meta name="csrf-cookie-name" content="<?= config_item("csrf_cookie_name") ?>">
    <link rel="stylesheet" href="<?php echo base_url() ?>assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="<?php echo base_url() ?>assets/css/bootstrap-responsive.min.css" />
    <link rel="stylesheet" href="<?php echo base_url() ?>assets/css/matrix-login.css" />
    <link rel="stylesheet" href="<?= base_url(); ?>assets/tecnina/css/tecnina-login.css?v=<?= filemtime(FCPATH . 'assets/tecnina/css/tecnina-login.css'); ?>" />
    <link href="<?= base_url('assets/css/particula.css'); ?>" rel="stylesheet">
    <link href="<?php echo base_url(); ?>assets/font-awesome/css/font-awesome.css" rel="stylesheet" />
    <script src="<?php echo base_url() ?>assets/js/jquery-1.12.4.min.js"></script>
    <link rel="icon" type="image/x-icon" href="<?= base_url(); ?>assets/tecnina/img/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= base_url(); ?>assets/tecnina/img/favicon/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= base_url(); ?>assets/tecnina/img/favicon/apple-touch-icon.png">
    <link rel="manifest" href="<?= base_url(); ?>assets/tecnina/img/favicon/site.webmanifest">
    <script src="<?php echo base_url() ?>assets/js/jquery.mask.min.js"></script>
    <script src="<?php echo base_url() ?>assets/js/funcoes.js"></script>
    <link href='https://unpkg.com/boxicons@2.1.1/css/boxicons.min.css' rel='stylesheet'>
    <!-- Script webeddy.com.br -->
    <script>
        function formatar(mascara, documento) {
            var i = documento.value.length;
            var saida = mascara.substring(0, 1);
            var texto = mascara.substring(i)

            if (texto.substring(0, 1) != saida) {
                documento.value += texto.substring(0, 1);
            }
        }
    </script>
    <script type="text/javascript" src="<?= base_url(); ?>assets/js/funcoesGlobal.js"></script>
    <script type="text/javascript" src="<?= base_url(); ?>assets/js/csrf.js"></script>
</head>

<?php
$parse_email = $this->input->get('e');
    $parse_cpfcnpj = $this->input->get('c');
    ?>

<body>
    <div class="main-login">
        <div class="left-login">
            <h1 class="h-one">Área do Cliente</h1>
            <img src="<?php echo base_url() ?>assets/img/forms-animate.svg" class="left-login-imagec" alt="Ilustração da Área do Cliente TecNina">
        </div>

        <div id="loginbox">
            <form class="form-vertical" id="formLogin" method="post" action="<?php echo cliente_url('mine/login') ?>">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
                <div class="d-flex flex-column">
                    <div class="right-login">
                        <div class="container">
                            <div class="card card-cad">
                                <div class="content">
                                    <div id="newlog" class="tecnina-login-brand">
                                        <img src="<?= base_url(); ?>assets/tecnina/img/svg/logo-on-dark.svg" alt="TECNINA">
                                    </div>
                                    <div class="control-group">
                                        <div class="controls">
                                            <div class="main_input_box">
                                                <span class="add-on bg_lg"><i class='bx bx-user-plus iconU'></i></span>
                                                <input id="email" name="email" type="text" placeholder="Email" value="<?php echo trim($parse_email); ?>" />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="control-group">
                                        <div class="controls">
                                            <div class="main_input_box">
                                                <span class="add-on bg_ly"><i class='bx bx-id-card iconU'></i></span>
                                                <input class="" maxlength="18" size="18" name="senha" type="password" placeholder="Senha" value="" />
                                            </div>
                                        </div>
                                    </div>

                                    <button style="margin: 0" class="btn btn-info btn-large"> Acessar</button>
                                    <div class="links-uteis"><a href="<?= cliente_url('mine/resetarSenha') ?>">
                                            <p style="margin:0px 0 18px">Esqueceu a senha?</p>
                                        </a></div>
                                    <div class="links-uteis"><p>&copy; <?= date('Y'); ?> TecNina</p></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
    </div>

    <script src="<?php echo base_url() ?>assets/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url() ?>assets/js/jquery.validate.js"></script>
    <script src="<?php echo base_url() ?>assets/js/sweetalert2.all.min.js"></script>
    <?php if ($this->session->flashdata('success') != null) { ?>
        <script>
            Swal.fire({
                position: 'center',
                icon: 'success',
                title: '<?php echo $this->session->flashdata('success'); ?>',
                showConfirmButton: false,
                timer: 4000
            })
        </script>
    <?php } ?>

    <?php if ($this->session->flashdata('error') != null) { ?>
        <script>
            Swal.fire({
                position: 'center',
                icon: 'error',
                title: '<?php echo $this->session->flashdata('error'); ?>',
                showConfirmButton: false,
                timer: 4000
            })
        </script>
    <?php } ?>

    <script type="text/javascript">
        $(document).ready(function() {

            $("#formLogin").validate({
                rules: {
                    email: {
                        required: true,
                        email: true
                    },
                    senha: {
                        required: true
                    }
                },
                messages: {
                    email: {
                        required: 'Campo Requerido.',
                        email: 'Insira Email válido'
                    },
                    senha: {
                        required: 'Campo Requerido.'
                    }
                },
                submitHandler: function(form) {
                    var dados = $(form).serialize();


                    $.ajax({
                        type: "POST",
                        url: "<?php echo cliente_url('mine/login'); ?>?ajax=true",
                        data: dados,
                        dataType: 'json',
                        success: function(data) {
                            if (data.result == true) {
                                window.location.href = "<?php echo cliente_url('mine/painel'); ?>";
                            } else {
                                Swal.fire({
                                    position: 'center',
                                    icon: 'error',
                                    title: 'Os dados de acesso estão incorretos.\n Por favor tente novamente!',
                                    showConfirmButton: false,
                                    timer: 4000
                                })

                                var newCsrfToken = data.MAPOS_TOKEN;
                                $("input[name='<?= $this->security->get_csrf_token_name(); ?>']").val(newCsrfToken);
                            }
                        }
                    });

                    return false;
                },

                errorClass: "help-inline",
                errorElement: "span",
                highlight: function(element, errorClass, validClass) {
                    $(element).parents('.control-group').addClass('error');
                },
                unhighlight: function(element, errorClass, validClass) {
                    $(element).parents('.control-group').removeClass('error');
                    $(element).parents('.control-group').addClass('success');
                }
            });

        });
    </script>
</body>

</html>
