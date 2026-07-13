<?php

declare(strict_types=1);

final class LiteConfig
{
    public static function load(string $root): array
    {
        $legacy = self::loadLegacy($root . '/config/config.php', 'config');
        $tokens = self::loadLegacy($root . '/config/api_key.php', 'tokenList');

        $defaults = [
            'base_url' => rtrim((string) ($legacy['domain'] ?? ''), '/'),
            'username' => (string) ($legacy['user'] ?? 'admin'),
            'password' => (string) ($legacy['password'] ?? ''),
            'max_size' => max(1, (int) ($legacy['maxSize'] ?? 10 * 1024 * 1024)),
            'max_files' => 10,
            'timezone' => (string) ($legacy['timezone'] ?? 'Asia/Shanghai'),
            'public_path' => '/i',
            'app_path' => '/lite',
            'upload_root' => $root . '/i',
            'api_enabled' => (bool) ($legacy['apiStatus'] ?? false),
            'api_tokens' => [],
            'allow_legacy_api_tokens' => false,
            'delete_ttl' => 86400,
            'session_name' => 'easyimage_lite',
            'trusted_proxy' => false,
            'trusted_proxy_ips' => [],
            'client_ip_header' => 'HTTP_X_REAL_IP',
        ];

        $local = self::loadLocal($root . '/config/lite.local.php');
        $config = array_replace($defaults, $local);
        $config['timezone'] = self::validTimezone((string) $config['timezone']);
        $config['base_url'] = rtrim((string) $config['base_url'], '/');
        $config['public_path'] = '/' . trim((string) $config['public_path'], '/');
        $config['app_path'] = '/' . trim((string) $config['app_path'], '/');
        $config['upload_root'] = rtrim((string) $config['upload_root'], '/');
        $config['max_size'] = max(1, (int) $config['max_size']);
        $config['max_files'] = max(1, min(50, (int) $config['max_files']));
        $config['delete_ttl'] = max(60, (int) $config['delete_ttl']);
        $config['trusted_proxy'] = (bool) $config['trusted_proxy'];
        $config['trusted_proxy_ips'] = array_values(array_filter(
            is_array($config['trusted_proxy_ips']) ? $config['trusted_proxy_ips'] : [],
            static fn ($ip): bool => is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false
        ));
        $config['client_ip_header'] = strtoupper((string) $config['client_ip_header']);
        $config['allow_legacy_api_tokens'] = (bool) $config['allow_legacy_api_tokens'];
        $localTokens = is_array($config['api_tokens']) ? $config['api_tokens'] : [];
        $config['api_tokens'] = $config['allow_legacy_api_tokens']
            ? array_replace(is_array($tokens) ? $tokens : [], $localTokens)
            : $localTokens;

        if (!self::isPasswordHash((string) $config['password'])) {
            throw new RuntimeException('Lite 管理员密码必须是 password_hash() 生成的哈希，请在 config/lite.local.php 中配置');
        }
        if (!self::validBaseUrl((string) $config['base_url'])) {
            throw new RuntimeException('Lite base_url 只能为空或使用 http/https 地址');
        }
        if ($config['app_path'] !== '/' && preg_match('#^/[A-Za-z0-9/_-]+$#D', (string) $config['app_path']) !== 1) {
            throw new RuntimeException('Lite app_path 格式无效');
        }
        if (preg_match('/^HTTP_[A-Z0-9_]+$/D', $config['client_ip_header']) !== 1) {
            throw new RuntimeException('Lite client_ip_header 格式无效');
        }

        $config['hmac_secret'] = self::loadSecret($root . '/config/lite.secret.php');

        return $config;
    }

    private static function isPasswordHash(string $password): bool
    {
        if ($password === '') {
            return false;
        }
        $info = password_get_info($password);
        return isset($info['algoName']) && $info['algoName'] !== 'unknown';
    }

    private static function validBaseUrl(string $url): bool
    {
        if ($url === '') {
            return true;
        }
        $parts = parse_url($url);
        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && isset($parts['host']);
    }

    private static function loadSecret(string $file): string
    {
        if (is_link($file)) {
            throw new RuntimeException('Lite 独立密钥路径不能是符号链接');
        }
        if (!is_file($file)) {
            self::createSecret($file);
        }

        $secret = include $file;
        if (!is_string($secret) || preg_match('/^[a-f0-9]{64}$/D', $secret) !== 1) {
            throw new RuntimeException('Lite 独立密钥文件无效：' . $file);
        }
        return $secret;
    }

    private static function createSecret(string $file): void
    {
        $directory = dirname($file);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('无法创建 Lite 独立密钥，请检查 config 目录写权限');
        }

        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $payload = "<?php\n\nreturn '" . bin2hex(random_bytes(32)) . "';\n";
        if (file_put_contents($temporary, $payload, LOCK_EX) === false || !chmod($temporary, 0600)) {
            @unlink($temporary);
            throw new RuntimeException('无法安全写入 Lite 独立密钥');
        }

        if (!@link($temporary, $file) && !is_file($file)) {
            @unlink($temporary);
            throw new RuntimeException('无法原子创建 Lite 独立密钥');
        }
        @unlink($temporary);
    }

    private static function loadLegacy(string $file, string $variable): array
    {
        if (!is_file($file)) {
            return [];
        }

        return (static function (string $file, string $variable): array {
            include $file;
            $value = ${$variable} ?? [];
            return is_array($value) ? $value : [];
        })($file, $variable);
    }

    private static function loadLocal(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        return (static function (string $file): array {
            $liteConfig = [];
            $returned = include $file;
            if (is_array($returned)) {
                return $returned;
            }
            return is_array($liteConfig) ? $liteConfig : [];
        })($file);
    }

    private static function validTimezone(string $timezone): string
    {
        try {
            new DateTimeZone($timezone);
            return $timezone;
        } catch (Throwable) {
            return 'Asia/Shanghai';
        }
    }
}

final class LiteUrl
{
    public static function app(array $config, string $path = ''): string
    {
        return rtrim((string) $config['app_path'], '/') . ($path === '' ? '/' : '/' . ltrim($path, '/'));
    }

    public static function image(array $config, string $relative): string
    {
        return rtrim((string) $config['public_path'], '/') . '/' . ltrim($relative, '/');
    }

    public static function absolute(array $config, string $path): string
    {
        return (string) $config['base_url'] !== '' ? $config['base_url'] . $path : $path;
    }
}
