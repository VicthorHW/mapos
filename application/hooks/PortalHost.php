<?php

defined('BASEPATH') or exit('No direct script access allowed');

class PortalHost
{
    public function enforce()
    {
        if (is_cli()) {
            return;
        }

        $config = &load_class('Config', 'core');

        if (! $config->item('portal_split_enabled')) {
            return;
        }

        $uri = &load_class('URI', 'core');
        $instruction = Portal::redirectInstruction(
            $config->item('portal_current'),
            $uri->uri_string(),
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $_SERVER['QUERY_STRING'] ?? ''
        );

        if ($instruction !== null) {
            $target = $this->portalUrl($config, $instruction['portal'], $instruction['path']);
            if ($instruction['query'] !== '') {
                $target .= '?' . $instruction['query'];
            }

            $this->redirect($target, $instruction['status']);
        }
    }

    private function portalUrl($config, $portal, $uri = '')
    {
        $configKey = $portal === 'client' ? 'portal_client_base_url' : 'portal_management_base_url';

        return Portal::siteUrl($config->item($configKey), $config->item('index_page'), $uri);
    }

    private function redirect($target, $statusCode = 302)
    {
        header('Cache-Control: no-store, no-cache, must-revalidate', true);
        header('Location: ' . $target, true, $statusCode);
        exit;
    }
}
