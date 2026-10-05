<?php

namespace tests\unit\services\import;

use app\services\catalog\CatalogFilterFunction;
use app\services\import\catalog\CatalogModelSpreadsheetReader;
use Codeception\Test\Unit;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class CatalogModelSpreadsheetReaderTest extends Unit
{
    public function testReadsModelRowWithLegacyTemplateColumns(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Модели');

        $headers = [
            'Направление',
            'Коллекция *',
            'Категория',
            'Подкатегория',
            'Подзаголовок',
            'Описание',
            'Размер (Ш×В×Г)',
            'Глубина посадочного места',
            'Клиренс',
            'Каркас',
            'Ткань (выбор из списка)',
            'Ссылка на примерочную 3D',
            'Видео титульное (если нет то берется первое фото из интерьера)',
            'Фото ссылки по одной через «, »',
            'Тех.фото по одной через «, »',
            '1 кат',
            '2 кат',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            'Линия 1',
            'Хьюстон',
            'Кресло',
            'Кресло',
            'Комфортная посадка',
            'Описание кресла',
            '90×95×85',
            '60',
            '25',
            'массив бука',
            'Mistral, GUCCI',
            'https://example.com/fitting',
            'https://example.com/video.mp4',
            'https://example.com/photo1.jpg, https://example.com/photo2.jpg',
            'https://example.com/tech.jpg',
            45000,
            46000,
        ], null, 'A2');

        $reader = new CatalogModelSpreadsheetReader();
        verify($reader->isModelsSheet($sheet))->true();

        $rows = $reader->readSheet($sheet);
        verify(count($rows))->equals(1);
        verify($rows[0]->directionLabel)->equals('Линия 1');
        verify($rows[0]->collectionName)->equals('Хьюстон');
        verify($rows[0]->displayLabel)->equals('Кресло Хьюстон');
        verify($rows[0]->subtitle)->equals('Комфортная посадка');
        verify($rows[0]->overallSize)->equals('90×95×85');
        verify($rows[0]->legHeight)->equals('25');
        verify($rows[0]->frame)->equals('массив бука');
        verify($rows[0]->frameSpec)->null();
        verify($rows[0]->fabricCollectionNames)->equals(['Mistral', 'GUCCI']);
        verify($rows[0]->fittingRoomUrl)->equals('https://example.com/fitting');
        verify($rows[0]->pricesByCategoryNumber[1])->equals(45000);
    }

    public function testReadsModelRowWithNewTemplateColumns(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Модели');

        $headers = [
            'Направление',
            'Коллекция *',
            'Категория',
            'Подкатегория',
            'Описание',
            'Размер (Ш×В×Г), мм',
            'Глубина посадочного места, мм',
            'Высота посадочного места, мм',
            'Ширина подлокотника, мм',
            'Высота опоры, мм',
            'Каркас',
            'Механизм',
            'Наполнение',
            'Дополнительно',
            'Каркас (красивое)',
            'Основание(красивое)',
            'Опоры (красивое)',
            'Наполнение (красивое)',
            'Обивка (красивое)',
            'Ткань (выбор из списка)',
            'Ссылка на примерочную 3D',
            '1 кат',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            'А+',
            'Адриано',
            'Диван',
            'Прямой диван',
            'Описание модели',
            '2500×860х1200',
            '590',
            '500',
            '240',
            '25',
            'фанера',
            'Тик-так',
            'Техническое наполнение',
            '2 подушки',
            'Массив бука',
            'Ламели',
            'Металл',
            'ППУ',
            'Ткань',
            'Riz',
            'https://souz-m3d.online/example',
            100000,
        ], null, 'A2');

        $reader = new CatalogModelSpreadsheetReader();
        $rows = $reader->readSheet($sheet);

        verify(count($rows))->equals(1);
        verify($rows[0]->description)->equals('Описание модели');
        verify($rows[0]->subtitle)->null();
        verify($rows[0]->overallSize)->equals('2500×860х1200');
        verify($rows[0]->seatDepth)->equals('590');
        verify($rows[0]->seatHeight)->equals('500');
        verify($rows[0]->armrestWidth)->equals('240');
        verify($rows[0]->legHeight)->equals('25');
        verify($rows[0]->frameSpec)->equals('фанера');
        verify($rows[0]->mechanism)->equals('Тик-так');
        verify($rows[0]->fillingSpec)->equals('Техническое наполнение');
        verify($rows[0]->additional)->equals('2 подушки');
        verify($rows[0]->frame)->equals('Массив бука');
        verify($rows[0]->foundation)->equals('Ламели');
        verify($rows[0]->supports)->equals('Металл');
        verify($rows[0]->filling)->equals('ППУ');
        verify($rows[0]->upholstery)->equals('Ткань');
        verify($rows[0]->fabricCollectionNames)->equals(['Riz']);
        verify($rows[0]->fittingRoomUrl)->equals('https://souz-m3d.online/example');
    }

    public function testReadsClientTemplateHeadersFromDownloadsShape(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Модели');

        $headers = [
            'Active',
            'Направление',
            'Коллекция *',
            'Категория',
            'Подкатегория',
            'Описание',
            'Размер (Ш×В×Г), мм',
            '1 кат',
            'Каркас',
            'Механизм',
            'Наполнение',
            'Дополнительно',
            'Описание (красивое)',
            'Каркас (красивое)',
            'Основание(красивое)',
            'Детали (красивое)',
            'Наполнение (красивое)',
            'Обивка (красивое)',
            'Ткань (выбор из списка)',
            'Ссылка на примерочную 3D',
            'Фото ссылки по одной через «, »' . "\n" . 'Тех Фото',
            'Полигоны для 3д ',
            'Ссылка на файл 3д',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            '',
            'А+',
            'Адриано',
            'Диван',
            'Прямой диван',
            'Краткое описание',
            '2500×860х1200',
            100000,
            'фанера',
            'тик-так',
            'наполнение',
            'доп',
            'Длинное описание',
            'каркас',
            'основание',
            'опоры',
            'наполнение',
            'обивка',
            'Riz',
            'https://example.com/fitting',
            'https://example.com/photo.jpg',
            '120000',
            'https://example.com/model.glb',
        ], null, 'A2');

        $reader = new CatalogModelSpreadsheetReader();
        $rows = $reader->readSheet($sheet);

        verify(count($rows))->equals(1);
        verify($rows[0]->subtitle)->equals('Краткое описание');
        verify($rows[0]->description)->equals('Длинное описание');
        verify($rows[0]->supports)->equals('опоры');
        verify($rows[0]->galleryPhotoUrls)->equals(['https://example.com/photo.jpg']);
        verify($rows[0]->dimensionPhotoFolderUrl)->null();
        verify($rows[0]->polygons3d)->equals('120000');
        verify($rows[0]->file3dUrl)->equals('https://example.com/model.glb');
    }

    public function testReadsCombinedGalleryAndTechFolderFromClientTemplate(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Модели');

        $headers = [
            'Направление',
            'Коллекция *',
            'Категория',
            'Подкатегория',
            'Описание',
            'Фото ссылки по одной через «, » | Тех Фото',
            '1 кат',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            'А+',
            'Адриано',
            'Диван',
            'Прямой диван',
            'Описание',
            'https://drive.google.com/drive/u/0/folders/abc123',
            100000,
        ], null, 'A2');

        $reader = new CatalogModelSpreadsheetReader();
        $rows = $reader->readSheet($sheet);

        verify(count($rows))->equals(1);
        verify($rows[0]->galleryPhotoUrls)->equals([]);
        verify($rows[0]->dimensionPhotoUrls)->equals([]);
        verify($rows[0]->dimensionPhotoFolderUrl)->stringContainsString('folders/abc123');
    }

    public function testReadsPolygonsAndFile3dColumns(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Модели');

        $headers = [
            'Направление',
            'Коллекция *',
            'Категория',
            'Подкатегория',
            'Описание',
            'Размер (Ш×В×Г), мм',
            'Ткань (выбор из списка)',
            'Ссылка на примерочную 3D',
            'Полигоны для 3д',
            'Ссылка на файл 3д',
            '1 кат',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            'А+',
            'Адриано',
            'Диван',
            'Прямой диван',
            'Описание модели',
            '2500×860х1200',
            'Riz',
            'https://souz-m3d.online/example',
            '245000',
            'https://example.com/model.glb',
            100000,
        ], null, 'A2');

        $reader = new CatalogModelSpreadsheetReader();
        $rows = $reader->readSheet($sheet);

        verify(count($rows))->equals(1);
        verify($rows[0]->polygons3d)->equals('245000');
        verify($rows[0]->file3dUrl)->equals('https://example.com/model.glb');
        verify($rows[0]->fittingRoomUrl)->equals('https://souz-m3d.online/example');
    }

    public function testReadsSleepingPlaceFromFilterFunctionColumn(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Модели');

        $headers = [
            'Направление',
            'Коллекция *',
            'Категория',
            'Подкатегория',
            'Описание',
            'Размер (Ш×В×Г), мм',
            'Функция (для фильтра)',
            '1 кат',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            'А+',
            'Адриано',
            'Диван',
            'Прямой диван',
            'Описание',
            '2500×860х1200',
            'Спальное место',
            100000,
        ], null, 'A2');

        $reader = new CatalogModelSpreadsheetReader();
        $rows = $reader->readSheet($sheet);

        verify(count($rows))->equals(1);
        verify($rows[0]->overallSize)->equals('2500×860х1200');
        verify($rows[0]->filterFunction)->equals(CatalogFilterFunction::WITH_SLEEPING);
    }

    public function testSkipsInactiveRows(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Модели');

        $headers = [
            'Active',
            'Направление',
            'Коллекция *',
            'Категория',
            'Подкатегория',
            '1 кат',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray(['no', 'А+', 'Адриано', 'Диван', 'Прямой диван', 100000], null, 'A2');
        $sheet->fromArray(['', 'А+', 'Артемида', 'Диван', 'Прямой диван', 90000], null, 'A3');

        $reader = new CatalogModelSpreadsheetReader();
        $rows = $reader->readSheet($sheet);

        verify(count($rows))->equals(1);
        verify($rows[0]->collectionName)->equals('Артемида');
    }

    public function testSkipsInactiveRowsWithEmojiMarker(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Модели');

        $headers = [
            'Active',
            'Направление',
            'Коллекция *',
            'Категория',
            'Подкатегория',
            '1 кат',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray(['🛑 no', 'Линия 1', 'Манхэттен', 'Диван', 'Кресло', 100000], null, 'A2');
        $sheet->fromArray(['', 'Линия 1', 'Манхэттен', 'Диван', 'Прямой диван', 90000], null, 'A3');

        $reader = new CatalogModelSpreadsheetReader();
        $rows = $reader->readSheet($sheet);

        verify(count($rows))->equals(1);
        verify($rows[0]->subcategoryLabel)->equals('Прямой диван');
    }

    public function testSkipsInvalidDivanKresloPairEvenWithoutActiveMarker(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Модели');

        $headers = [
            'Active',
            'Направление',
            'Коллекция *',
            'Категория',
            'Подкатегория',
            '1 кат',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray(['', 'Линия 1', 'Манхэттен', 'Диван', 'Кресло', 100000], null, 'A2');
        $sheet->fromArray(['', 'Линия 1', 'Манхэттен', 'Диван', 'Прямой диван', 90000], null, 'A3');

        $reader = new CatalogModelSpreadsheetReader();
        $rows = $reader->readSheet($sheet);

        verify(count($rows))->equals(1);
        verify($rows[0]->subcategoryLabel)->equals('Прямой диван');
    }

    public function testReadsSleepingPlaceSizeFromNewTemplateColumns(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Модели');

        $headers = [
            'Направление',
            'Коллекция *',
            'Категория',
            'Подкатегория',
            'Функция (для фильтра)',
            'Размеры спального места  (Ш×Г), мм (если есть, иначе оставьте пустым)',
            '1 кат',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray(['А+', 'Адриано', 'Диван', 'Прямой диван', 'Со спальным местом', '100х100', 100000], null, 'A2');

        $reader = new CatalogModelSpreadsheetReader();
        $rows = $reader->readSheet($sheet);

        verify(count($rows))->equals(1);
        verify($rows[0]->filterFunction)->equals(CatalogFilterFunction::WITH_SLEEPING);
        verify($rows[0]->sleepingPlaceSize)->equals('100х100');
    }
}
