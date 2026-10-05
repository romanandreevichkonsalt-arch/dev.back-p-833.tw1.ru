<?php

namespace app\commands;

use app\models\User;
use app\services\search\SearchBenchmarkRunner;
use app\services\search\SearchService;
use yii\console\Controller;
use yii\console\ExitCode;

class SearchController extends Controller
{
    /** @var string Запрос для bench */
    public $query = 'диван';

    /** @var int limit для /search */
    public $limit = 5;

    /** @var bool Прогреть кэш перед замером */
    public $warm = true;

    /** @var int Число итераций */
    public $iterations = 2;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['query', 'limit', 'warm', 'iterations']);
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), ['q' => 'query', 'l' => 'limit']);
    }

    public function actionBench(): int
    {
        $runner = new SearchBenchmarkRunner();
        $queries = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', $this->query) ?: [])));
        if ($queries === []) {
            $this->stderr("Укажите --query=\n");

            return ExitCode::USAGE;
        }

        if ($this->warm) {
            $search = new SearchService();
            foreach ($queries as $q) {
                $search->search($q, (int)$this->limit);
            }
        }

        foreach ($queries as $q) {
            $this->stdout("\n=== {$q} ===\n");
            for ($i = 1; $i <= max(1, (int)$this->iterations); $i++) {
                if ($this->iterations > 1) {
                    $this->stdout("Iteration {$i}:\n");
                }
                $steps = $runner->profileSearch($q, (int)$this->limit);
                $this->stdout($runner->formatReport($q, $steps));
            }
        }

        return ExitCode::OK;
    }

    public function actionSmoke(string $query = 'диван'): int
    {
        $payload = (new SearchService())->search($query, 5);
        $this->stdout(sprintf(
            "matchType=%s products=%d categoriesFound=%d\n",
            (string)($payload['matchType'] ?? ''),
            count($payload['products'] ?? []),
            count($payload['categoriesFound'] ?? [])
        ));

        return ExitCode::OK;
    }
}
