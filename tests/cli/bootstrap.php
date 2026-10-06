<?php

if (defined('BASEPATH')) {
    return;
}

if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit(1);
}

$fcpath = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR;
define('FCPATH', $fcpath);
define('ENVIRONMENT', getenv('APP_ENVIRONMENT') ?: 'development');

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_USER_DEPRECATED);
ini_set('display_errors', 1);

$composerAutoloadFile = FCPATH . 'application/vendor/autoload.php';
if (file_exists($composerAutoloadFile)) {
    require_once $composerAutoloadFile;
}

$envFile = FCPATH . 'application/.env';
if (file_exists($envFile) && class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(FCPATH . 'application');
    $dotenv->load();
}

define('BASEPATH', FCPATH . 'application/vendor/codeigniter/framework/system/');
define('APPPATH', FCPATH . 'application/');
define('VIEWPATH', APPPATH . 'views/');

require_once BASEPATH . 'core/Common.php';
require_once APPPATH . 'config/constants.php';

$charset = strtoupper(config_item('charset') ?: 'UTF-8');
ini_set('default_charset', $charset);
define('MB_ENABLED', extension_loaded('mbstring'));
define('ICONV_ENABLED', extension_loaded('iconv'));

require_once BASEPATH . 'core/compat/mbstring.php';
require_once BASEPATH . 'core/compat/hash.php';
require_once BASEPATH . 'core/compat/password.php';
require_once BASEPATH . 'core/compat/standard.php';

$BM = &load_class('Benchmark', 'core');
$EXT = &load_class('Hooks', 'core');
$CFG = &load_class('Config', 'core');
$UNI = &load_class('Utf8', 'core');
$URI = &load_class('URI', 'core');
$RTR = &load_class('Router', 'core');
$OUT = &load_class('Output', 'core');
$SEC = &load_class('Security', 'core');
$IN = &load_class('Input', 'core');
$LANG = &load_class('Lang', 'core');

require_once BASEPATH . 'core/Controller.php';

function &get_instance()
{
    return CI_Controller::get_instance();
}

if (file_exists(APPPATH . 'core/' . $CFG->config['subclass_prefix'] . 'Controller.php')) {
    require_once APPPATH . 'core/' . $CFG->config['subclass_prefix'] . 'Controller.php';
}
