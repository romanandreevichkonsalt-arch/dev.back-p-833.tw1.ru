<?php

namespace app\services\catalog;

use app\models\CatalogFabricCollection;
use app\models\CatalogFabricColor;
use app\models\MediaFile;
use app\services\media\LocalMediaStorage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use yii\helpers\Html;

class FabricLibraryPdfRenderer
{
    public function __construct(
        private readonly LocalMediaStorage $mediaStorage = new LocalMediaStorage(),
    ) {
    }

    /**
     * @param iterable<CatalogFabricCollection> $collections
     */
    public function renderToFile(string $absolutePath, iterable $collections): void
    {
        if (!class_exists(Mpdf::class)) {
            throw new \RuntimeException('Библиотека mPDF не установлена. Выполните composer install на сервере.');
        }

        $tempDir = \Yii::getAlias('@runtime/mpdf');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'tempDir' => $tempDir,
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 16,
            'margin_bottom' => 16,
            'simpleTables' => true,
            'packTableData' => true,
        ]);

        $mpdf->SetTitle('Библиотека тканей');
        $mpdf->WriteHTML($this->renderStyles());
        $mpdf->WriteHTML($this->renderCover());

        $first = true;
        foreach ($collections as $collection) {
            $mpdf->WriteHTML($this->renderCollection($collection, !$first));
            $first = false;
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        $mpdf->Output($absolutePath, Destination::FILE);
    }

    /**
     * @param iterable<CatalogFabricCollection> $collections
     */
    public function renderHtmlForCollections(iterable $collections): string
    {
        $parts = [$this->renderStyles(), $this->renderCover()];

        $first = true;
        foreach ($collections as $collection) {
            $parts[] = $this->renderCollection($collection, !$first);
            $first = false;
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'
            . implode("\n", $parts)
            . '</body></html>';
    }

    private function renderStyles(): string
    {
        return <<<'CSS'
<style>
    body { font-family: dejavusans, sans-serif; font-size: 10pt; color: #1a1a1a; }
    h1 { font-size: 20pt; margin: 0 0 8px; }
    h2 { font-size: 15pt; margin: 0 0 10px; page-break-after: avoid; }
    h3 { font-size: 11pt; margin: 14px 0 8px; page-break-after: avoid; }
    .cover-meta { color: #555; font-size: 9pt; margin-bottom: 24px; }
    .collection { page-break-before: always; }
    .collection--first { page-break-before: auto; }
    .spec { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    .spec th { text-align: left; vertical-align: top; width: 38%; padding: 4px 8px 4px 0; color: #444; font-weight: normal; }
    .spec td { padding: 4px 0; vertical-align: top; }
    .colors { width: 100%; border-collapse: collapse; }
    .colors td { width: 25%; vertical-align: top; padding: 0 8px 14px 0; }
    .color-card { border: 1px solid #ddd; padding: 8px; min-height: 110px; }
    .color-photo { width: 32mm; height: 32mm; object-fit: cover; display: block; margin-bottom: 6px; background: #f0f0f0; }
    .color-photo-placeholder { width: 32mm; height: 32mm; background: #ececec; color: #888; font-size: 8pt; text-align: center; line-height: 32mm; margin-bottom: 6px; }
    .color-code { font-weight: bold; font-size: 9.5pt; }
    .color-name { font-size: 9pt; color: #333; }
    .muted { color: #666; font-size: 9pt; }
</style>
CSS;
    }

    private function renderCover(): string
    {
        return '<section class="cover">'
            . '<h1>Библиотека тканей</h1>'
            . '<p class="cover-meta">Сформировано: ' . Html::encode(date('d.m.Y H:i')) . '</p>'
            . '</section>';
    }

    private function renderCollection(CatalogFabricCollection $collection, bool $pageBreak): string
    {
        $class = 'collection' . ($pageBreak ? '' : ' collection--first');

        $html = '<section class="' . $class . '">';
        $html .= '<h2>' . Html::encode((string)$collection->name) . '</h2>';
        $html .= '<table class="spec">';
        $html .= $this->specRow('Категория ткани', (string)($collection->material_kind ?? ''));
        $html .= $this->specRow('Фактура', (string)($collection->texture ?? ''));
        $html .= $this->specRow('Плотность, г/м²', $this->formatInt($collection->density_gsm));
        $html .= $this->specRow('Ширина рулона, см', $this->formatInt($collection->roll_width_cm));
        $html .= $this->specRow('Износостойкость (циклы Мартиндейла)', $this->formatInt($collection->martindale));
        $html .= $this->specRowMultiline('Описание', $collection->description);
        $html .= $this->specRowMultiline('Состав', $collection->composition);
        $html .= $this->specRowMultiline('Свойства', $collection->care_instructions);
        $html .= '</table>';

        $colors = $collection->activeColors;
        if ($colors === []) {
            $html .= '<p class="muted">Активных цветов нет.</p>';
        } else {
            $html .= '<h3>Цвета</h3>';
            $html .= '<table class="colors"><tr>';
            $colIndex = 0;
            foreach ($colors as $color) {
                if ($colIndex > 0 && $colIndex % 4 === 0) {
                    $html .= '</tr><tr>';
                }
                $html .= '<td>' . $this->renderColorCard($color) . '</td>';
                $colIndex++;
            }
            while ($colIndex % 4 !== 0) {
                $html .= '<td></td>';
                $colIndex++;
            }
            $html .= '</tr></table>';
        }

        $html .= '</section>';

        return $html;
    }

    private function renderColorCard(CatalogFabricColor $color): string
    {
        $designCode = trim((string)$color->design_code);
        $colorName = trim((string)($color->getCatalogColorName() ?? ''));

        $html = '<div class="color-card">';
        $html .= $this->renderSwatchImage($color->swatchMedia);
        $html .= '<div class="color-code">' . Html::encode($designCode !== '' ? $designCode : '—') . '</div>';
        $html .= '<div class="color-name">' . Html::encode($colorName !== '' ? $colorName : '—') . '</div>';
        $html .= '</div>';

        return $html;
    }

    private function renderSwatchImage(?MediaFile $media): string
    {
        $path = $this->resolveImagePath($media);
        if ($path === null) {
            return '<div class="color-photo-placeholder">Нет фото</div>';
        }

        return '<img class="color-photo" src="' . Html::encode($path) . '" alt="" />';
    }

    private function resolveImagePath(?MediaFile $media): ?string
    {
        if ($media === null || trim((string)$media->path) === '') {
            return null;
        }

        if (!$media->isImage()) {
            return null;
        }

        foreach ([$media->path_mini, $media->path_medium, $media->path] as $relative) {
            $relative = trim((string)$relative);
            if ($relative === '') {
                continue;
            }
            $absolute = $this->mediaStorage->resolveFullPath($relative);
            if (is_file($absolute)) {
                return $absolute;
            }
        }

        return null;
    }

    private function specRow(string $label, string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            $value = '—';
        }

        return '<tr><th>' . Html::encode($label) . '</th><td>' . Html::encode($value) . '</td></tr>';
    }

    private function specRowMultiline(string $label, ?string $value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return $this->specRow($label, '—');
        }

        $escaped = nl2br(Html::encode($value));

        return '<tr><th>' . Html::encode($label) . '</th><td>' . $escaped . '</td></tr>';
    }

    private function formatInt(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return (string)(int)$value;
    }
}
