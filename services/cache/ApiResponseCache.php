<?php

namespace app\services\cache;

use Yii;

class ApiResponseCache
{
    private const VERSION_KEY = 'api_response_cache_version';

    public function getVersion(): int
    {
        $version = Yii::$app->cache->get(self::VERSION_KEY);

        return is_int($version) && $version > 0 ? $version : 1;
    }

    public function bump(): void
    {
        Yii::$app->cache->set(self::VERSION_KEY, $this->getVersion() + 1);
    }

    /**
     * @template T
     * @param callable():T $factory
     * @return T
     */
    public function get(string $namespace, string $key, callable $factory, ?int $ttl = null): mixed
    {
        $config = Yii::$app->params['apiCache'] ?? [];
        $ttl ??= (int)($config['defaultTtl'] ?? 300);
        $cacheKey = sprintf('api:%s:v%d:%s', $namespace, $this->getVersion(), $key);

        $cached = Yii::$app->cache->get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $value = $factory();
        Yii::$app->cache->set($cacheKey, $value, $ttl);

        return $value;
    }
}
