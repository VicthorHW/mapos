<!DOCTYPE html>
<html lang="pt-br">

<head>
    <title>TecNina | Área do Cliente</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="<?php echo $this->config->item('app_name') . ' - ' . $this->config->item('app_subname') ?>">
    <meta name="csrf-token-name" content="<?= config_item("csrf_token_name") ?>">
    <meta name="csrf-cookie-name" content="<?= config_item("csrf_cookie_name") ?>">
    <link rel="stylesheet" href="<?php echo base_url(); ?>assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="<?php echo base_url(); ?>assets/css/bootstrap-responsive.min.css" />
    <link rel="stylesheet" href="<?php echo base_url(); ?>assets/css/matrix-style.css" />
    <link rel="stylesheet" href="<?php echo base_url(); ?>assets/css/matrix-media.css" />
    <link href="<?php echo base_url(); ?>assets/font-awesome/css/font-awesome.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?php echo base_url(); ?>assets/css/fullcalendar.css" />
    <link href="<?php echo base_url(); ?>assets/css/bootstrap-responsive.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo base_url(); ?>assets/css/conecte-mobile.css" />
    <link rel="stylesheet" href="<?= base_url(); ?>assets/tecnina/css/tecnina.css?v=<?= filemtime(FCPATH . 'assets/tecnina/css/tecnina.css'); ?>" />
    <script type="text/javascript" src="<?php echo base_url(); ?>assets/js/jquery-1.12.4.min.js"></script>
    <script type="text/javascript" src="<?= base_url(); ?>assets/js/sweetalert.min.js"></script>
    <link rel="icon" type="image/x-icon" href="<?= base_url(); ?>assets/tecnina/img/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= base_url(); ?>assets/tecnina/img/favicon/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= base_url(); ?>assets/tecnina/img/favicon/apple-touch-icon.png">
    <link rel="manifest" href="<?= base_url(); ?>assets/tecnina/img/favicon/site.webmanifest">
    <link href='https://unpkg.com/boxicons@2.1.1/css/boxicons.min.css' rel='stylesheet'>
    <script type="text/javascript" src="<?= base_url(); ?>assets/js/funcoesGlobal.js"></script>
    <script type="text/javascript" src="<?= base_url(); ?>assets/js/csrf.js"></script>
</head>

<body>
    <!-- Botão hamburger para mobile -->
    <button class="c-menu-toggle" id="c-menu-toggle" aria-label="Abrir menu">
        <i class='bx bx-menu'></i>
    </button>
    <!-- Overlay escuro ao abrir sidebar no mobile -->
    <div class="c-sidebar-overlay" id="c-sidebar-overlay"></div>

    <!--Header-part-->
    <div id="header">
        <h1><a href="<?= cliente_url('mine/painel') ?>">TecNina</a></h1>
    </div>
    <!--close-Header-part-->

    <!--top-Header-menu-->
    <div class="navebarn" style="margin-top: -60px;height: 25px;margin-bottom: 15px">
        <div id="user-nav" class="navbar navbar-inverse">
            <ul class="nav">
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class='bx bx-user-circle iconN1'></i> <?= $this->session->userdata('nome') ?> </a>
                    <ul class="dropdown-menu">
                        <li class=""><a title="Meu Perfil" href="<?php echo cliente_url('mine/conta') ?>"><i class="fas fa-user"></i> <span class="text">Meu Perfil</span></a></li>
                        <li class="divider"></li>
                        <li class=""><a title="Sair" href="<?php echo cliente_url('mine/sair') ?>"><i class="fas fa-sign-out-alt"></i> <span class="text">Sair</span></a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>

    <nav id="sidebar">
        <div id="newlog" class="tecnina-brand">
            <div class="tecnina-brand-logo">
                <img src="<?= base_url(); ?>assets/tecnina/img/svg/logo-on-dark.svg" alt="TECNINA">
            </div>
        </div>
        <a href="#" class="visible-phone">
            <div class="mode">
                <div class="moon-menu">
                    <i class='bx bx-chevron-right iconX open-2'></i>
                    <i class='bx bx-chevron-left iconX close-2'></i>
                </div>
            </div>
        </a>

        <div class="menu-bar">
            <div class="menu">
                <ul class="menu-links" style="position: relative;">
                    <li class="<?php if (isset($menuPainel)) {
                        echo 'active';
                    }; ?>"><a class="tip-bottom" title="" href="<?php echo cliente_url('mine/painel') ?>"><i class='bx bx-home-alt iconX'></i> <span class="title">Painel</span></a></li>
                    <li class="<?php if (isset($menuConta)) {
                        echo 'active';
                    }; ?>"><a class="tip-bottom" title="" href="<?php echo cliente_url('mine/conta') ?>"><i class="bx bx-user-circle iconX"></i> <span class="title">Minha Conta</span></a></li>
                    <li class="<?php if (isset($menuOs)) {
                        echo 'active';
                    }; ?>"><a class="tip-bottom" title="" href="<?php echo cliente_url('mine/os') ?>"><i class='bx bx-spreadsheet iconX'></i> <span class="title">Ordens de Serviço</span></a></li>
                    <li class="<?php if (isset($menuVendas)) {
                        echo 'active';
                    }; ?>"><a class="tip-bottom" title="" href="<?php echo cliente_url('mine/compras') ?>"><i class='bx bx-cart-alt iconX'></i> <span class="title">Compras</span></a></li>
                    <li class="<?php if (isset($menuCobrancas)) {
                        echo 'active';
                    }; ?>"><a class="tip-bottom" title="" href="<?php echo cliente_url('mine/cobrancas') ?>"><i class='bx bx-credit-card-front iconX'></i> <span class="title">Cobranças</span></a></li>
                </ul>
            </div>

            <div class="botton-content">
                <li class="">
                    <a class="tip-bottom" title="" href="<?= cliente_url('mine/sair'); ?>">
                        <i class='bx bx-log-out-circle iconX'></i>
                        <span class="title">Sair</span></a>
                </li>
            </div>

        </div>
    </nav>

    <div style="background: #f3f4f6" id="content">
        <div class="content-header" id="content-header">
            <div id="breadcrumb"><a href="<?php echo cliente_url('mine/painel'); ?>" title="Painel" class="tip-bottom"><i class="fas fa-home"></i> Painel</a></div>
        </div>

        <div class="container-fluid">
            <div class="row-fluid">

                <div class="span12">
                    <?php if ($var = $this->session->flashdata('success')) : ?><script>
                            swal("Sucesso!", "<?php echo str_replace('"', '', $var); ?>", "success");
                        </script><?php endif; ?>
                    <?php if ($var = $this->session->flashdata('error')) : ?><script>
                            swal("Falha!", "<?php echo str_replace('"', '', $var); ?>", "error");
                        </script><?php endif; ?>
                    <?php if (isset($output)) {
                        $this->load->view($output);
                    } ?>

                </div>
            </div>

        </div>
    </div>
    <!--Footer-part-->
    <div class="row-fluid">
        <div id="footer" class="span12">
            &copy; <?= date('Y') ?> TecNina
        </div>
    </div>

    <!-- javascript
================================================== -->

    <script src="<?= base_url(); ?>assets/js/bootstrap.min.js"></script>
    <script src="<?= base_url(); ?>assets/js/matrix.js"></script>
    <script>
        (function () {
            var toggle   = document.getElementById('c-menu-toggle');
            var sidebar  = document.getElementById('sidebar');
            var overlay  = document.getElementById('c-sidebar-overlay');
            if (!toggle || !sidebar || !overlay) return;
            toggle.addEventListener('click', function () {
                sidebar.classList.toggle('mobile-open');
                overlay.classList.toggle('active');
            });
            overlay.addEventListener('click', function () {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
            });
        })();
    </script>
</body>

</html>
