<?php

declare(strict_types=1);

namespace App\Agovena\Exports;

use XMLWriter;

/**
 * Writes export rows to a stream one record at a time.
 *
 * CSV: UTF-8 with BOM, header row, RFC 4180 quoting with CRLF line endings and
 * spreadsheet formula neutralisation. JSON: one array of objects. XML: a root
 * element named after the dataset with one child element per record.
 */
final class ExportStreamWriter
{
    private const JSON_FLAGS = JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE;

    /**
     * @param  resource  $output
     * @param  iterable<array<string, string|int|bool|null>>  $rows
     */
    public function write($output, ExportFormat $format, ExportDataset $dataset, iterable $rows): void
    {
        match ($format) {
            ExportFormat::Csv => $this->writeCsv($output, $dataset->columns(), $rows),
            ExportFormat::Json => $this->writeJson($output, $dataset->columns(), $rows),
            ExportFormat::Xml => $this->writeXml($output, $dataset, $rows),
        };
    }

    /**
     * Neutralise values a spreadsheet would evaluate as a formula. Plain numbers
     * (including negative amounts) cannot be formulas and stay numeric.
     */
    public function csvCell(string|int|bool|null $value): string
    {
        $value = $this->scalar($value);

        if ($value === '' || preg_match('/^-?\d+(?:\.\d+)?$/', $value) === 1) {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /**
     * @param  resource  $output
     * @param  list<string>  $columns
     * @param  iterable<array<string, string|int|bool|null>>  $rows
     */
    private function writeCsv($output, array $columns, iterable $rows): void
    {
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $columns, ',', '"', '', "\r\n");

        foreach ($rows as $row) {
            $cells = [];
            foreach ($columns as $column) {
                $cells[] = $this->csvCell($row[$column] ?? null);
            }
            fputcsv($output, $cells, ',', '"', '', "\r\n");
        }
    }

    /**
     * @param  resource  $output
     * @param  list<string>  $columns
     * @param  iterable<array<string, string|int|bool|null>>  $rows
     */
    private function writeJson($output, array $columns, iterable $rows): void
    {
        fwrite($output, '[');
        $first = true;

        foreach ($rows as $row) {
            $ordered = [];
            foreach ($columns as $column) {
                $ordered[$column] = $row[$column] ?? null;
            }

            fwrite($output, ($first ? "\n" : ",\n").json_encode($ordered, self::JSON_FLAGS));
            $first = false;
        }

        fwrite($output, $first ? ']' : "\n]");
    }

    /**
     * @param  resource  $output
     * @param  iterable<array<string, string|int|bool|null>>  $rows
     */
    private function writeXml($output, ExportDataset $dataset, iterable $rows): void
    {
        $columns = $dataset->columns();
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement($dataset->key());

        foreach ($rows as $row) {
            $xml->startElement($dataset->recordElement());
            foreach ($columns as $column) {
                $xml->startElement($column);
                $value = $row[$column] ?? null;
                if ($value !== null) {
                    $xml->text($this->xmlText($this->scalar($value)));
                }
                $xml->endElement();
            }
            $xml->endElement();
            fwrite($output, $xml->flush());
        }

        $xml->endElement();
        $xml->endDocument();
        fwrite($output, $xml->flush());
    }

    private function scalar(string|int|bool|null $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }

    /** Drop byte sequences and control characters XML 1.0 cannot represent. */
    private function xmlText(string $value): string
    {
        $value = mb_scrub($value, 'UTF-8');

        return (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);
    }
}
