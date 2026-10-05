<?php

namespace app\commands;

use app\services\cache\ApiCacheInvalidator;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class CatalogPurgeController extends Controller
{
    /** @var bool */
    public $dryRun = false;

    /** @var bool */
    public $force = false;

    /**
     * @return string[]
     */
    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['dryRun', 'force']);
    }

    /**
     * @return array<string, string>
     */
    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), [
            'd' => 'dryRun',
            'f' => 'force',
        ]);
    }

    public function actionAll(): int
    {
        if (!$this->dryRun && !$this->force) {
            $confirmed = $this->confirm(
                'Удалить все заказы, корзины, избранное, товары, модели, категории, подкатегории и коллекции?'
            );
            if (!$confirmed) {
                $this->stdout("Отменено.\n");

                return ExitCode::OK;
            }
        }

        $db = Yii::$app->db;
        $tables = [
            '{{%cart_items}}',
            '{{%favorite_items}}',
            '{{%order_documents}}',
            '{{%order_status_log}}',
            '{{%order_items}}',
            '{{%orders}}',
            '{{%catalog_model_dimension_images}}',
            '{{%catalog_model_images}}',
            '{{%catalog_model_fabric_collections}}',
            '{{%catalog_model_price_categories}}',
            '{{%catalog_model_prices}}',
            '{{%catalog_products}}',
            '{{%catalog_models}}',
            '{{%catalog_collection_images}}',
            '{{%catalog_collections}}',
            '{{%catalog_subcategories}}',
            '{{%catalog_categories}}',
        ];

        if (!$this->dryRun) {
            $db->createCommand('SET FOREIGN_KEY_CHECKS=0')->execute();
            $this->nullifyOptionalForeignKeys($db);
        }

        foreach ($tables as $table) {
            if (!$this->tableExists($db, $table)) {
                $this->stdout(sprintf("%s: пропущено (таблица не найдена)\n", $table), Console::FG_YELLOW);
                continue;
            }

            $count = (int)$db->createCommand("SELECT COUNT(*) FROM {$table}")->queryScalar();
            $this->stdout(sprintf("%s: %d\n", $table, $count));

            if (!$this->dryRun && $count > 0) {
                $db->createCommand()->truncateTable($table)->execute();
            }
        }

        if (!$this->dryRun) {
            $db->createCommand('SET FOREIGN_KEY_CHECKS=1')->execute();
            ApiCacheInvalidator::touch();
        }

        $this->stdout(
            $this->dryRun ? "Dry run complete.\n" : "Purge complete.\n",
            Console::FG_GREEN
        );

        return ExitCode::OK;
    }

    private function nullifyOptionalForeignKeys(\yii\db\Connection $db): void
    {
        if ($this->tableExists($db, '{{%dealer_promo_grants}}')) {
            $db->createCommand('UPDATE {{%dealer_promo_grants}} SET used_order_id = NULL WHERE used_order_id IS NOT NULL')->execute();
        }
        if ($this->tableExists($db, '{{%dealer_cashback_ledger}}')) {
            $db->createCommand('UPDATE {{%dealer_cashback_ledger}} SET order_id = NULL WHERE order_id IS NOT NULL')->execute();
        }
    }

    private function tableExists(\yii\db\Connection $db, string $table): bool
    {
        $rawName = str_replace(['{{%', '}}'], '', $table);
        $schema = $db->schema->getTableSchema($rawName, true);

        return $schema !== null;
    }
}
