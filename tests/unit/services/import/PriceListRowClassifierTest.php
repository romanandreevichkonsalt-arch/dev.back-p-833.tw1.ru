<?php

namespace tests\unit\services\import;

use app\services\import\catalog\PriceListRowClassifier;
use Codeception\Test\Unit;

class PriceListRowClassifierTest extends Unit
{
    private PriceListRowClassifier $classifier;

    protected function _before(): void
    {
        $this->classifier = new PriceListRowClassifier();
    }

    public function testClassifySofaRow(): void
    {
        $dto = $this->classifier->classifyModelRow('Артемида', 30, 'диван', [1 => 78529, 2 => 80029]);
        verify($dto)->notNull();
        verify($dto->categoryLabel)->equals('Диван');
        verify($dto->subcategoryLabel)->equals('Прямой диван');
        verify($dto->productTitlePart)->equals('Диван');
        verify($dto->displayLabel)->equals('Диван');
    }

    public function testClassifyModuleRowStripsDimensions(): void
    {
        $dto = $this->classifier->classifyModelRow('Стенли', 89, 'Диван 190 см с бок.', [1 => 82280]);
        verify($dto)->notNull();
        verify($dto->isModule)->true();
        verify($dto->categoryLabel)->equals('Модули');
        verify($dto->subcategoryLabel)->equals('Модуль');
        verify($dto->displayLabel)->equals('Диван с бок');
    }

    public function testClassifyOttomanModule(): void
    {
        $dto = $this->classifier->classifyModelRow('Барни', 49, 'Оттоманка', [1 => 47890]);
        verify($dto)->notNull();
        verify($dto->categoryLabel)->equals('Модули');
        verify($dto->subcategoryLabel)->equals('Оттоманка');
        verify($dto->productTitlePart)->equals('Оттоманка');
    }

    public function testSkipExplanationRow(): void
    {
        $label = 'Луиджи является не модульной системой, а может быть либо прямым, либо угловым диваном.';
        verify($this->classifier->shouldSkipLabel($label))->true();
        verify($this->classifier->classifyModelRow('Луиджи', 72, $label, [1 => 100]))->null();
    }
}
