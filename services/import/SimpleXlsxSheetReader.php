<?php

namespace app\services\import;

use ZipArchive;

/**
 * Минимальное чтение первого листа .xlsx без PhpSpreadsheet (ZIP + XML).
 */
class SimpleXlsxSheetReader
{
    /**
     * @throws \InvalidArgumentException
     */
    public static function assertZipXlsx(string $filePath): void
    {
        if (!is_file($filePath)) {
            throw new \InvalidArgumentException('Файл не найден.');
        }

        if (filesize($filePath) === 0) {
            throw new \InvalidArgumentException('Файл пустой. Повторите загрузку .xlsx.');
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new \InvalidArgumentException('Не удалось открыть файл.');
        }

        $magic = fread($handle, 2);
        fclose($handle);
        if ($magic !== 'PK') {
            throw new \InvalidArgumentException(
                'Неверный формат файла. Загрузите настоящий Excel (.xlsx), а не HTML или переименованный файл.'
            );
        }

        if (!class_exists(ZipArchive::class)) {
            throw new \InvalidArgumentException(
                'На сервере не включено расширение PHP zip. Обратитесь к администратору сервера.'
            );
        }
    }

    /**
     * @return array<int, array<string, string>> row number (1-based) => [column letters => value]
     */
    public function readFirstSheetGrid(string $filePath): array
    {
        $grids = $this->readAllSheetsGrids($filePath);
        if ($grids === []) {
            throw new \InvalidArgumentException('В файле не найден лист Excel.');
        }

        return reset($grids);
    }

    /**
     * @return list<string> названия листов в порядке книги
     */
    public function listSheetNames(string $filePath): array
    {
        self::assertZipXlsx($filePath);

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \InvalidArgumentException('Не удалось открыть .xlsx как ZIP-архив.');
        }

        try {
            return array_map(
                static fn (array $entry): string => $entry['name'],
                $this->resolveSheetEntries($zip)
            );
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<string, array<int, array<string, string>>> normalized sheet name => grid
     */
    public function readAllSheetsGrids(string $filePath): array
    {
        self::assertZipXlsx($filePath);

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \InvalidArgumentException('Не удалось открыть .xlsx как ZIP-архив.');
        }

        try {
            $entries = $this->resolveSheetEntries($zip);
            if ($entries === []) {
                return [];
            }

            $sharedStrings = $this->readSharedStrings($zip);
            $grids = [];
            foreach ($entries as $entry) {
                $normalized = SpreadsheetSheetResolver::normalizeName($entry['name']);
                $grids[$normalized] = $this->readSheetGrid($zip, $entry['path'], $sharedStrings);
            }

            return $grids;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<array{name: string, path: string}>
     */
    private function resolveSheetEntries(ZipArchive $zip): array
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        if ($workbookXml === false || $workbookXml === '') {
            return $this->fallbackSheetEntries($zip);
        }

        $document = $this->loadXml($workbookXml);
        if ($document === null) {
            return $this->fallbackSheetEntries($zip);
        }

        $mainNs = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $relNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $targetsByRelId = $this->readWorkbookRelationshipTargets($zip);

        $entries = [];
        foreach ($document->getElementsByTagNameNS($mainNs, 'sheet') as $sheetNode) {
            $name = (string)$sheetNode->attributes?->getNamedItem('name')?->nodeValue;
            if ($name === '') {
                continue;
            }

            $relId = (string)$sheetNode->attributes?->getNamedItemNS($relNs, 'id')?->nodeValue;
            if ($relId === '') {
                $relId = (string)$sheetNode->attributes?->getNamedItem('id')?->nodeValue;
            }

            $target = $targetsByRelId[$relId] ?? null;
            if ($target === null) {
                continue;
            }

            $path = str_starts_with($target, '/')
                ? ltrim($target, '/')
                : 'xl/' . ltrim($target, '/');

            if ($zip->locateName($path) === false) {
                continue;
            }

            $entries[] = ['name' => $name, 'path' => $path];
        }

        return $entries !== [] ? $entries : $this->fallbackSheetEntries($zip);
    }

    /**
     * @return array<string, string> rId => target path relative to xl/
     */
    private function readWorkbookRelationshipTargets(ZipArchive $zip): array
    {
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($relsXml === false || $relsXml === '') {
            return [];
        }

        $document = $this->loadXml($relsXml);
        if ($document === null) {
            return [];
        }

        $relNs = 'http://schemas.openxmlformats.org/package/2006/relationships';
        $map = [];
        foreach ($document->getElementsByTagNameNS($relNs, 'Relationship') as $relNode) {
            $id = (string)$relNode->attributes?->getNamedItem('Id')?->nodeValue;
            $target = (string)$relNode->attributes?->getNamedItem('Target')?->nodeValue;
            if ($id !== '' && $target !== '') {
                $map[$id] = $target;
            }
        }

        return $map;
    }

    /**
     * @return list<array{name: string, path: string}>
     */
    private function fallbackSheetEntries(ZipArchive $zip): array
    {
        $entries = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (!is_string($name) || preg_match('#^xl/worksheets/sheet(\d+)\.xml$#', $name, $matches) !== 1) {
                continue;
            }

            $entries[] = [
                'name' => 'Sheet' . $matches[1],
                'path' => $name,
            ];
        }

        usort(
            $entries,
            static fn (array $a, array $b): int => strcmp($a['path'], $b['path'])
        );

        return $entries;
    }

    private function resolveFirstSheetPath(ZipArchive $zip): ?string
    {
        foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml'] as $candidate) {
            if ($zip->locateName($candidate) !== false) {
                return $candidate;
            }
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (is_string($name) && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name) === 1) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false || $xml === '') {
            return [];
        }

        $document = $this->loadXml($xml);
        if ($document === null) {
            return [];
        }

        $strings = [];
        foreach ($document->getElementsByTagNameNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'si') as $si) {
            $text = '';
            foreach ($si->getElementsByTagNameNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 't') as $node) {
                $text .= $node->textContent;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @param string[] $sharedStrings
     * @return array<int, array<string, string>>
     */
    private function readSheetGrid(ZipArchive $zip, string $sheetPath, array $sharedStrings): array
    {
        $xml = $zip->getFromName($sheetPath);
        if ($xml === false || $xml === '') {
            throw new \InvalidArgumentException('Лист Excel пуст или повреждён.');
        }

        $document = $this->loadXml($xml);
        if ($document === null) {
            throw new \InvalidArgumentException('Не удалось разобрать XML листа Excel.');
        }

        $grid = [];
        $mainNs = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

        foreach ($document->getElementsByTagNameNS($mainNs, 'row') as $rowNode) {
            $rowNumber = (int)$rowNode->attributes?->getNamedItem('r')?->nodeValue;
            if ($rowNumber <= 0) {
                continue;
            }

            foreach ($rowNode->getElementsByTagNameNS($mainNs, 'c') as $cellNode) {
                $ref = (string)$cellNode->attributes?->getNamedItem('r')?->nodeValue;
                if (!preg_match('/^([A-Z]+)\d+$/', $ref, $matches)) {
                    continue;
                }

                $column = $matches[1];
                $value = $this->resolveCellValue($cellNode, $sharedStrings, $mainNs);
                if ($value === '') {
                    continue;
                }

                $grid[$rowNumber][$column] = $value;
            }
        }

        return $grid;
    }

    /**
     * @param string[] $sharedStrings
     */
    private function resolveCellValue(\DOMElement $cellNode, array $sharedStrings, string $mainNs): string
    {
        $type = (string)$cellNode->attributes?->getNamedItem('t')?->nodeValue;

        if ($type === 'inlineStr') {
            $text = '';
            foreach ($cellNode->getElementsByTagNameNS($mainNs, 't') as $node) {
                $text .= $node->textContent;
            }

            return trim($text);
        }

        $valueNode = $cellNode->getElementsByTagNameNS($mainNs, 'v')->item(0);
        if ($valueNode === null) {
            return '';
        }

        $raw = trim((string)$valueNode->textContent);
        if ($raw === '') {
            return '';
        }

        if ($type === 's') {
            $index = (int)$raw;

            return trim($sharedStrings[$index] ?? '');
        }

        return trim($raw);
    }

    private function loadXml(string $xml): ?\DOMDocument
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $document : null;
    }
}
