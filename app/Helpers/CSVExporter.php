<?php
namespace App\Helpers;

/**
 * CSV Exporter with Formula Injection (DDE) Mitigation
 * Escapes leading sensitive characters (=, +, -, @, \t, \r) to prevent spreadsheet code execution.
 */
class CSVExporter
{
    /**
     * Escape cell content to neutralize formula injection
     */
    public static function sanitizeCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $str = (string)$value;

        // Check if string begins with characters that trigger formula execution in Excel/Calc
        if (preg_match('/^[\=\+\-\@\t\r]/', $str)) {
            return "'" . $str;
        }

        return $str;
    }

    /**
     * Generate CSV string from rows
     *
     * @param array $headers Header labels
     * @param array $rows Array of associative or indexed rows
     * @return string
     */
    public static function generate(array $headers, array $rows): string
    {
        $fp = fopen('php://temp', 'r+');

        // Write UTF-8 BOM for Excel compatibility
        fputs($fp, "\xEF\xBB\xBF");

        // Write headers
        $cleanHeaders = array_map([self::class, 'sanitizeCell'], $headers);
        fputcsv($fp, $cleanHeaders);

        // Write rows
        foreach ($rows as $row) {
            $cleanRow = [];
            foreach ($row as $val) {
                $cleanRow[] = self::sanitizeCell($val);
            }
            fputcsv($fp, $cleanRow);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv;
    }

    /**
     * Output CSV download directly to browser
     */
    public static function download(string $filename, array $headers, array $rows): void
    {
        $csv = self::generate($headers, $rows);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $csv;
        exit;
    }
}
