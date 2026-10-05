<?php

namespace tests\unit\services;

use app\models\CatalogModel;
use app\services\dealer\DealerModelTechPhotosService;
use Codeception\Test\Unit;

class DealerModelTechPhotosServiceTest extends Unit
{
    public function testListFolderLinksResponseShape(): void
    {
        $schema = CatalogModel::getTableSchema();
        if ($schema === null || $schema->getColumn('tech_photos_folder_url') === null) {
            $this->markTestSkipped('tech_photos_folder_url is not migrated in test DB.');
        }

        $payload = (new DealerModelTechPhotosService())->listFolderLinks();

        verify($payload)->arrayHasKey('items');
        verify($payload)->arrayHasKey('meta');
        verify($payload)->arrayHasKey('url');
        verify($payload['meta'])->arrayHasKey('total');
        verify($payload['meta']['total'])->equals(count($payload['items']));

        foreach ($payload['items'] as $item) {
            verify($item)->arrayHasKey('label');
            verify($item)->arrayHasKey('url');
            verify(trim((string)$item['label']))->notEmpty();
            verify(trim((string)$item['url']))->notEmpty();
        }
    }
}
