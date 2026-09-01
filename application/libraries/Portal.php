<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Portal
{
    public static function configuration(array $environment, array $server, $appEnvironment)
    {
        $legacyBaseUrl = self::normalizeBaseUrl($environment['APP_BASEURL'] ?? 'http://localhost:8000/', 'APP_BASEURL');
        $hasManagementUrl = isset($environment['APP_BASEURL_GESTAO']) && trim((string) $environment['APP_BASEURL_GESTAO']) !== '';
        $hasClientUrl = isset($environment['APP_BASEURL_CLIENTE']) && trim((string) $environment['APP_BASEURL_CLIENTE']) !== '';

        if ($hasManagementUrl xor $hasClientUrl) {
            throw new InvalidArgumentException('APP_BASEURL_GESTAO e APP_BASEURL_CLIENTE devem ser configuradas em conjunto.');
        }

        if (! $hasManagementUrl) {
            return [
                'split_enabled' => false,
                'current' => 'legacy',
                'base_url' => $legacyBaseUrl,
                'management_base_url' => $legacyBaseUrl,
                'client_base_url' => $legacyBaseUrl,
                'request_host' => self::requestHost($server),
            ];
        }

        $managementBaseUrl = self::normalizeBaseUrl($environment['APP_BASEURL_GESTAO'], 'APP_BASEURL_GESTAO');
        $clientBaseUrl = self::normalizeBaseUrl($environment['APP_BASEURL_CLIENTE'], 'APP_BASEURL_CLIENTE');
        $managementHost = self::urlHost($managementBaseUrl);
        $clientHost = self::urlHost($clientBaseUrl);

        if ($managementHost === $clientHost) {
            throw new InvalidArgumentException('APP_BASEURL_GESTAO e APP_BASEURL_CLIENTE devem usar hostnames distintos.');
        }

        $requestHost = self::requestHost($server);
        $current = 'unknown';
        $baseUrl = $managementBaseUrl;

        if ($requestHost === $managementHost) {
            $current = 'management';
        } elseif ($requestHost === $clientHost) {
            $current = 'client';
            $baseUrl = $clientBaseUrl;
        } elseif (self::isLocalRequest($requestHost, $legacyBaseUrl, $appEnvironment)) {
            $current = 'local';
            $baseUrl = $legacyBaseUrl;
        }

        return [
            'split_enabled' => true,
            'current' => $current,
            'base_url' => $baseUrl,
            'management_base_url' => $managementBaseUrl,
            'client_base_url' => $clientBaseUrl,
            'request_host' => $requestHost,
        ];
    }

    public static function redirectInstruction($portal, $path, $method = 'GET', $query = '')
    {
        $path = trim((string) $path, '/');
        $normalizedPath = strtolower($path);
        $isClientPath = $normalizedPath === 'mine'
            || strpos($normalizedPath, 'mine/') === 0
            || $normalizedPath === 'api/v1/client'
            || strpos($normalizedPath, 'api/v1/client/') === 0;

        if ($portal === 'unknown') {
            return ['portal' => 'management', 'path' => '', 'query' => '', 'status' => 302];
        }

        if ($portal === 'client') {
            if ($path === '') {
                return ['portal' => 'client', 'path' => 'mine', 'query' => '', 'status' => 302];
            }

            if (! $isClientPath) {
                return ['portal' => 'management', 'path' => '', 'query' => '', 'status' => 302];
            }

            return null;
        }

        if ($portal === 'management' && $isClientPath) {
            $query = (string) $query;
            if (strpos($query, "\r") !== false || strpos($query, "\n") !== false) {
                $query = '';
            }

            $statusCode = in_array(strtoupper((string) $method), ['GET', 'HEAD'], true) ? 302 : 307;

            return ['portal' => 'client', 'path' => $path, 'query' => $query, 'status' => $statusCode];
        }

        return null;
    }

    public static function siteUrl($baseUrl, $indexPage = '', $uri = '')
    {
        $baseUrl = rtrim((string) $baseUrl, '/') . '/';
        $uri = ltrim((string) $uri, '/');

        if ($uri === '') {
            return $baseUrl;
        }

        $indexPage = trim((string) $indexPage, '/');

        return $baseUrl . ($indexPage === '' ? '' : $indexPage . '/') . $uri;
    }

    private static function normalizeBaseUrl($url, $variable)
    {
        $url = trim((string) $url);
        $parts = parse_url($url);

        if (
            $url === ''
            || $parts === false
            || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new InvalidArgumentException($variable . ' deve ser uma URL HTTP(S) absoluta, sem credenciais, query string ou fragmento.');
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower(rtrim($parts['host'], '.'));
        $hostForUrl = strpos($host, ':') !== false ? '[' . $host . ']' : $host;
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = isset($parts['path']) ? trim($parts['path'], '/') : '';

        return $scheme . '://' . $hostForUrl . $port . '/' . ($path === '' ? '' : $path . '/');
    }

    private static function urlHost($url)
    {
        return strtolower(rtrim((string) parse_url($url, PHP_URL_HOST), '.'));
    }

    private static function requestHost(array $server)
    {
        $hostHeader = strtolower(trim((string) ($server['HTTP_HOST'] ?? '')));

        if (preg_match('/^\[([0-9a-f:]+)\](?::[0-9]+)?$/D', $hostHeader, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^([a-z0-9.-]+)(?::[0-9]+)?$/D', $hostHeader, $matches)) {
            return rtrim($matches[1], '.');
        }

        return '';
    }

    private static function isLocalRequest($requestHost, $legacyBaseUrl, $appEnvironment)
    {
        if ($appEnvironment === 'production') {
            return false;
        }

        if ($requestHost === '' && PHP_SAPI === 'cli') {
            return true;
        }

        $localHosts = ['localhost', '127.0.0.1', '::1'];

        return in_array($requestHost, $localHosts, true) || $requestHost === self::urlHost($legacyBaseUrl);
    }
}
