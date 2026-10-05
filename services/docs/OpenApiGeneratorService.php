<?php

namespace app\services\docs;

use FilesystemIterator;
use OpenApi\Generator;
use OpenApi\Processors;
use OpenApi\Utils\Pipeline;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Yii;

class OpenApiGeneratorService
{
    public const OUTPUT_ALIAS = '@runtime/openapi.json';
    public const CACHE_KEY = 'openapi-json-schema-v3';

    /**
     * @return list<string>
     */
    public function getScanPaths(): array
    {
        return [
            Yii::getAlias('@app/docs'),
            Yii::getAlias('@app/controllers'),
        ];
    }

    public function getOutputPath(): string
    {
        return Yii::getAlias(self::OUTPUT_ALIAS);
    }

    public function getSourceVersion(): string
    {
        $mtime = 0;
        foreach ($this->getScanPaths() as $path) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );
            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $mtime = max($mtime, $file->getMTime());
                }
            }
        }

        return (string)$mtime;
    }

    public function isValidJson(?string $json): bool
    {
        if ($json === null || $json === '') {
            return false;
        }

        $data = json_decode($json, true);

        return is_array($data) && isset($data['openapi']) && is_string($data['openapi']);
    }

    public function generateJson(): string
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $openapi = $this->createGenerator()->generate($this->getScanPaths(), null, false);
        if ($openapi === null) {
            throw new RuntimeException('OpenAPI generation returned empty result.');
        }

        $json = $openapi->toJson();
        if (!$this->isValidJson($json)) {
            throw new RuntimeException('Generated OpenAPI JSON has no openapi version field.');
        }

        return $json;
    }

    public function writeToRuntime(bool $force = false): string
    {
        $path = $this->getOutputPath();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create runtime directory for OpenAPI export.');
        }

        if (!$force && $this->isRuntimeFresh()) {
            return $path;
        }

        $json = $this->generateJson();
        if (file_put_contents($path, $json) === false) {
            throw new RuntimeException('Cannot write OpenAPI JSON to runtime.');
        }

        return $path;
    }

    public function readFromRuntime(): ?string
    {
        $path = $this->getOutputPath();
        if (!is_file($path)) {
            return null;
        }

        $json = file_get_contents($path);
        if (!$this->isValidJson($json)) {
            return null;
        }

        return $json;
    }

    public function isRuntimeFresh(): bool
    {
        $path = $this->getOutputPath();
        if (!is_file($path)) {
            return false;
        }

        return (string)filemtime($path) >= $this->getSourceVersion();
    }

    /**
     * Swagger-php default pipeline resolves PHP types via Symfony TypeInfo (AugmentProperties et al.).
     * That takes 30+ minutes on our annotation-only spec; explicit processors finish in ~1 s.
     */
    private function createGenerator(): Generator
    {
        $generator = new Generator();

        return $generator->setProcessorPipeline(new Pipeline([
            new Processors\DocBlockDescriptions(),
            new Processors\MergeIntoOpenApi(),
            new Processors\MergeIntoComponents(),
            new Processors\BuildPaths(),
            new Processors\AugmentParameters(),
            new Processors\AugmentRefs(),
            new Processors\MergeJsonContent(),
            new Processors\MergeXmlContent(),
            new Processors\OperationId(),
            new Processors\CleanUnmerged(),
            new Processors\CleanUnusedComponents(),
            new Processors\AugmentTags(),
        ]));
    }
}
