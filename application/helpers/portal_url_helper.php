<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (! function_exists('portal_url')) {
    function portal_url($portal, $uri = '')
    {
        $CI = &get_instance();
        $baseUrl = portal_base_url($portal);

        return Portal::siteUrl($baseUrl, $CI->config->item('index_page'), $uri);
    }
}

if (! function_exists('portal_base_url')) {
    function portal_base_url($portal, $uri = '')
    {
        $CI = &get_instance();
        if (in_array($CI->config->item('portal_current'), ['legacy', 'local'], true)) {
            $baseUrl = rtrim((string) $CI->config->item('base_url'), '/') . '/';
        } else {
            $configKey = $portal === 'client' ? 'portal_client_base_url' : 'portal_management_base_url';
            $baseUrl = rtrim((string) $CI->config->item($configKey), '/') . '/';
        }

        return $baseUrl . ltrim((string) $uri, '/');
    }
}

if (! function_exists('cliente_url')) {
    function cliente_url($uri = '')
    {
        return portal_url('client', $uri);
    }
}

if (! function_exists('gestao_url')) {
    function gestao_url($uri = '')
    {
        return portal_url('management', $uri);
    }
}

if (! function_exists('cliente_asset_url')) {
    function cliente_asset_url($url)
    {
        $url = str_replace('\\', '/', (string) $url);
        $path = parse_url($url, PHP_URL_PATH);

        if ($path !== false && preg_match('#(?:^|/)(assets/.*)$#i', $path, $matches)) {
            return portal_base_url('client', $matches[1]);
        }

        return $url;
    }
}
