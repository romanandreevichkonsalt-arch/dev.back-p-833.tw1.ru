<?php

namespace tests\unit\services\import;

use app\services\import\fabric\CloudStorageUrlResolver;
use app\services\import\fabric\FabricColorNameValidator;
use app\services\import\fabric\FabricDesignCodeNormalizer;
use app\services\import\fabric\FabricMediaNaming;
use app\services\import\fabric\MediaUrlClassifier;
use app\services\import\fabric\PriceCategoryLabelParser;
use Codeception\Test\Unit;

class FabricRegistryImportHelpersTest extends Unit
{
    public function testPriceCategoryParser(): void
    {
        $parsed = PriceCategoryLabelParser::parse('3 кат (от 801 до 900 руб)');
        verify($parsed)->notNull();
        verify($parsed['number'])->equals(3);
        verify($parsed['price_min'])->equals(801);
        verify($parsed['price_max'])->equals(900);
        verify($parsed['label'])->equals('3 кат (от 801 до 900 руб)');

        $parsedNine = PriceCategoryLabelParser::parse('9 кат ( от 1101 до 1200)');
        verify($parsedNine)->notNull();
        verify($parsedNine['number'])->equals(9);
        verify($parsedNine['price_min'])->equals(1101);
        verify($parsedNine['price_max'])->equals(1200);
        verify($parsedNine['label'])->equals('9 кат (от 1101 до 1200 руб)');

        verify(PriceCategoryLabelParser::format(8, 1301, 1400))->equals('8 кат (от 1301 до 1400 руб)');
        verify(PriceCategoryLabelParser::format(1, null, 700))->equals('1 кат (до 700 руб)');
        verify(PriceCategoryLabelParser::format(9, null, null))->equals('Категория 9');

        verify(PriceCategoryLabelParser::extractCategoryNumber('1 кат (до 700 руб)'))->equals(1);
        verify(PriceCategoryLabelParser::parse(''))->null();
    }

    public function testDesignCodeFromRegistry(): void
    {
        verify(FabricDesignCodeNormalizer::fromRegistry('4.0'))->equals('4');
        verify(FabricDesignCodeNormalizer::fromRegistry('422'))->equals('422');
        verify(FabricDesignCodeNormalizer::fromRegistry('Savana Terracotta'))->equals('Savana Terracotta');
        verify(FabricDesignCodeNormalizer::normalize('Savana Terracotta'))->equals('savana-terracotta');
    }

    public function testColorNameValidator(): void
    {
        verify(FabricColorNameValidator::isImportableColorName('бежевый'))->true();
        verify(FabricColorNameValidator::isImportableColorName('светло-серый'))->true();
        verify(FabricColorNameValidator::isImportableColorName('422'))->true();
        verify(FabricColorNameValidator::normalizeLabel('терракота'))->equals('Терракота');
        verify(FabricColorNameValidator::normalizeLabel('светло-серый'))->equals('Светло-серый');
        verify(FabricColorNameValidator::isImportableColorName(''))->false();
    }

    public function testMediaNaming(): void
    {
        verify(FabricMediaNaming::swatch('leonardo', '4', 'jpg'))->equals('leonardo-4-swatch.jpg');
        verify(FabricMediaNaming::pbr('leonardo', '4', 'zip'))->equals('leonardo-4-pbr.zip');
    }

    public function testMailRuWeblinkExtraction(): void
    {
        $url = 'https://cloud.mail.ru/public/3kch/odW9hY8Zo/%D0%91%D1%83%D0%BA%D0%BB%D0%B5/GUCCI/GUCCI%20422%20(32.32).jpg';
        $resolved = CloudStorageUrlResolver::resolve($url);
        verify($resolved)->notNull();
        verify($resolved)->stringContainsString('weblink/view/');
        verify($resolved)->stringContainsString('3kch');
    }

    public function testMediaUrlClassifier(): void
    {
        verify(MediaUrlClassifier::classify(''))->equals(MediaUrlClassifier::TYPE_EMPTY);
        verify(MediaUrlClassifier::classify('снять фотографом'))->equals(MediaUrlClassifier::TYPE_NON_DIRECT);
        verify(MediaUrlClassifier::classify('https://drive.google.com/drive/folders/abc'))->equals(MediaUrlClassifier::TYPE_FOLDER);
        verify(MediaUrlClassifier::classify('https://disk.yandex.ru/d/hash/file.jpg'))->equals(MediaUrlClassifier::TYPE_DIRECT);
        verify(MediaUrlClassifier::classify('https://souz-m.ru/products/gucci-690.html'))->equals(MediaUrlClassifier::TYPE_NON_DIRECT);
    }

    public function testGoogleDriveFolderIsUnsupported(): void
    {
        $url = 'https://drive.google.com/drive/folders/1vcvw4-2lC8kjJGZwhXj6XM9aJOqxBxtH';
        verify(CloudStorageUrlResolver::resolve($url))->null();
        verify(CloudStorageUrlResolver::unsupportedReason($url))->stringContainsString('папку Google Drive');
    }

    public function testGoogleDriveFileResolvesToDownloadUrl(): void
    {
        $url = 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWx/view?usp=sharing';
        $resolved = CloudStorageUrlResolver::resolve($url);
        verify($resolved)->notNull();
        verify($resolved)->equals('https://drive.google.com/uc?export=download&id=1AbCdEfGhIjKlMnOpQrStUvWx');
    }

    public function testYandexNestedFileUrl(): void
    {
        $url = 'https://disk.yandex.ru/d/-yhIhgyiRd2juA/2023/31.05%20LEONARDO/LEONARDO%20(цвет%201)-2.jpg';
        $resolved = CloudStorageUrlResolver::resolve($url);
        verify($resolved)->notNull();
        verify($resolved)->stringStartsWith('https://');
    }

    public function testSouzMProductPageResolvesOgImage(): void
    {
        $url = 'https://souz-m.ru/products/gucci-690.html';
        $resolved = CloudStorageUrlResolver::resolve($url);
        verify($resolved)->notNull();
        verify($resolved)->stringContainsString('gucci');
        verify($resolved)->stringContainsString('.jpg');
    }
}
