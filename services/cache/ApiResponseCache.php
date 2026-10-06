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
        $cacheKey = $this->buildKey($namespace, $key);

        $cached = Yii::$app->cache->get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $value = $factory();
        Yii::$app->cache->set($cacheKey, $value, $ttl);

        return $value;
    }

    /**
     * Soft/hard TTL: serve stale immediately after soft expiry and refresh in background.
     * True cold (no cache) still builds synchronously.
     *
     * @template T
     * @param callable():T $factory
     * @return T
     */
    public function getSoft(
        string $namespace,
        string $key,
        callable $factory,
        int $softTtl,
        int $hardTtl,
        ?callable $scheduleRefresh = null,
    ): mixed {
        $softTtl = max(1, $softTtl);
        $hardTtl = max($softTtl, $hardTtl);
        $cacheKey = $this->buildKey($namespace, $key);
        $envelopeKey = $cacheKey . ':envelope';

        $envelope = Yii::$app->cache->get($envelopeKey);
        if (!is_array($envelope) || !array_key_exists('v', $envelope) || !isset($envelope['builtAt'])) {
            $value = $factory();
            $this->storeEnvelope($envelopeKey, $value, $hardTtl);

            return $value;
        }

        $age = time() - (int)$envelope['builtAt'];
        if ($age <= $softTtl) {
            return $envelope['v'];
        }

        // Soft-expired: return stale, refresh once under lock.
        $lockKey = $cacheKey . ':refresh-lock';
        if (Yii::$app->cache->add($lockKey, 1, min(180, $softTtl))) {
            if ($scheduleRefresh !== null) {
                $scheduleRefresh();
            } else {
                $this->scheduleDetachedRefresh($factory, $envelopeKey, $hardTtl, $lockKey);
            }
        }

        return $envelope['v'];
    }

    /**
     * Force-store soft-cache envelope (used by CLI rebuild / warm).
     */
    public function putSoft(string $namespace, string $key, mixed $value, int $hardTtl): void
    {
        $envelopeKey = $this->buildKey($namespace, $key) . ':envelope';
        $this->storeEnvelope($envelopeKey, $value, max(300, $hardTtl));
    }

    /**
     * Prefer detached CLI so HTTP never waits on rebuild (php -S has no fastcgi_finish_request).
     * Falls back to shutdown refresh if CLI cannot be spawned.
     *
     * @template T
     * @param callable():T $factory
     */
    private function scheduleDetachedRefresh(
        callable $factory,
        string $envelopeKey,
        int $hardTtl,
        string $lockKey,
    ): void {
        if ($this->trySpawnSearchIndexRebuild()) {
            // Lock cleared by CLI after putSoft.
            return;
        }

        register_shutdown_function(function () use ($factory, $envelopeKey, $hardTtl, $lockKey): void {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            try {
                $value = $factory();
                $this->storeEnvelope($envelopeKey, $value, $hardTtl);
            } catch (\Throwable $e) {
                Yii::error($e->getMessage(), __METHOD__);
            } finally {
                Yii::$app->cache->delete($lockKey);
            }
        });
    }

    private function trySpawnSearchIndexRebuild(): bool
    {
        if (PHP_SAPI === 'cli' && !getenv('FABRIC_ALLOW_NESTED_SEARCH_REBUILD')) {
            // Avoid recursive spawn from console warm itself.
            return false;
        }

        $yii = Yii::getAlias('@app/yii');
        if (!is_file($yii) || !is_readable($yii)) {
            return false;
        }

        $cmd = sprintf(
            'FABRIC_ALLOW_NESTED_SEARCH_REBUILD=1 %s %s catalog-cache/rebuild-search-index > /dev/null 2>&1 &',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($yii)
        );

        try {
            $handle = popen($cmd, 'r');
            if ($handle === false) {
                return false;
            }
            pclose($handle);

            return true;
        } catch (\Throwable $e) {
            Yii::warning($e->getMessage(), __METHOD__);

            return false;
        }
    }

    private function storeEnvelope(string $envelopeKey, mixed $value, int $hardTtl): void
    {
        Yii::$app->cache->set(
            $envelopeKey,
            [
                'v' => $value,
                'builtAt' => time(),
            ],
            $hardTtl
        );
    }

    private function buildKey(string $namespace, string $key): string
    {
        return sprintf('api:%s:v%d:%s', $namespace, $this->getVersion(), $key);
    }
}
