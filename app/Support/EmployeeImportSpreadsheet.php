<?php

namespace App\Support;

use Phar;
use PharData;
use RuntimeException;
use SimpleXMLElement;

class EmployeeImportSpreadsheet
{
    /**
     * Build a minimal XLSX workbook containing the import headings and example employee.
     *
     * @param  list<string>  $headers
     * @param  list<string>  $sample
     */
    public static function createSample(array $headers, array $sample): string
    {
        $rows = [$headers, $sample];
        $sheetRows = '';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $cells = '';

            foreach ($row as $columnIndex => $value) {
                $columnName = self::columnName($columnIndex + 1);
                $escapedValue = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cells .= "<c r=\"{$columnName}{$excelRow}\" t=\"inlineStr\"><is><t>{$escapedValue}</t></is></c>";
            }

            $sheetRows .= "<row r=\"{$excelRow}\">{$cells}</row>";
        }

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="Employees" sheetId="1" r:id="rId1"/></sheets>'
                .'</workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'</Relationships>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
                .$sheetRows
                .'</sheetData></worksheet>',
        ];

        $temporaryPath = tempnam(sys_get_temp_dir(), 'employee-xlsx-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary Excel workbook.');
        }

        unlink($temporaryPath);
        $path = $temporaryPath.'.zip';

        try {
            $archive = new PharData($path, 0, null, Phar::ZIP);

            foreach ($files as $fileName => $contents) {
                $archive->addFromString($fileName, $contents);
            }

            unset($archive);
            $contents = file_get_contents($path);

            if ($contents === false) {
                throw new RuntimeException('Unable to read the generated Excel workbook.');
            }

            return $contents;
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Read the first worksheet in an XLSX workbook into a zero-based row/cell array.
     *
     * @return list<list<string>>
     */
    public static function read(string $path): array
    {
        try {
            $archive = new PharData($path);

            if (! isset($archive['xl/worksheets/sheet1.xml'])) {
                throw new RuntimeException('The Excel workbook does not contain a first worksheet.');
            }

            $sharedStrings = [];

            if (isset($archive['xl/sharedStrings.xml'])) {
                $sharedStringXml = self::parseXml($archive['xl/sharedStrings.xml']->getContent());

                foreach ($sharedStringXml->xpath('//*[local-name()="si"]') ?: [] as $sharedString) {
                    $sharedStrings[] = self::textContent($sharedString);
                }
            }

            $worksheet = self::parseXml($archive['xl/worksheets/sheet1.xml']->getContent());
            $rows = [];

            foreach ($worksheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
                $cells = [];

                foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                    $reference = (string) $cell['r'];
                    preg_match('/^[A-Z]+/', $reference, $matches);
                    $columnIndex = self::columnIndex($matches[0] ?? 'A');
                    $type = (string) $cell['t'];
                    $valueNode = $cell->xpath('./*[local-name()="v"]')[0] ?? null;

                    $cells[$columnIndex] = match ($type) {
                        's' => $sharedStrings[(int) ($valueNode ?? 0)] ?? '',
                        'inlineStr' => self::textContent($cell),
                        default => (string) ($valueNode ?? ''),
                    };
                }

                if ($cells === []) {
                    $rows[] = [];

                    continue;
                }

                ksort($cells);
                $rows[] = array_replace(array_fill(0, max(array_keys($cells)) + 1, ''), $cells);
            }

            unset($archive);

            return $rows;
        } catch (\Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('The uploaded XLSX file is not a valid Excel workbook.', previous: $exception);
        }
    }

    private static function parseXml(string $contents): SimpleXMLElement
    {
        $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);

        if ($xml === false) {
            throw new RuntimeException('The uploaded XLSX file contains invalid worksheet data.');
        }

        return $xml;
    }

    private static function textContent(SimpleXMLElement $node): string
    {
        $text = '';

        foreach ($node->xpath('.//*[local-name()="t"]') ?: [] as $textNode) {
            $text .= (string) $textNode;
        }

        return $text;
    }

    private static function columnName(int $column): string
    {
        $name = '';

        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    }

    private static function columnIndex(string $column): int
    {
        $index = 0;

        foreach (str_split($column) as $letter) {
            $index = $index * 26 + ord($letter) - 64;
        }

        return $index - 1;
    }
}
