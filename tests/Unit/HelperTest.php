<?php
namespace Tests\Unit;

use App\Helpers\Sanitizer;
use App\Helpers\Validator;
use App\Helpers\CSVExporter;
use App\Helpers\TimeHelper;

class HelperTest
{
    public static function run(): array
    {
        $results = [];

        // 1. Sanitizer::e
        $xss = '<script>alert("XSS")</script>';
        $escaped = Sanitizer::e($xss);
        $results[] = [
            'name' => 'Sanitizer escapes XSS tags',
            'pass' => $escaped === '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;'
        ];

        // 2. Sanitizer::cleanIdentifier
        $cleanId = Sanitizer::cleanIdentifier('  bca-2024/001_A!@#$  ');
        $results[] = [
            'name' => 'Sanitizer normalizes institutional ID',
            'pass' => $cleanId === 'BCA-2024/001_A'
        ];

        // 3. Validator::validateIdentifier
        $results[] = [
            'name' => 'Validator accepts valid institutional IDs',
            'pass' => Validator::validateIdentifier('BCA2024-001') && Validator::validateIdentifier('EMP_102')
        ];
        $results[] = [
            'name' => 'Validator rejects invalid institutional IDs',
            'pass' => !Validator::validateIdentifier('A') && !Validator::validateIdentifier('ID with spaces')
        ];

        // 4. Validator::validatePassword
        $results[] = [
            'name' => 'Validator accepts strong password',
            'pass' => Validator::validatePassword('SecretPass123')
        ];
        $results[] = [
            'name' => 'Validator rejects weak password without digits',
            'pass' => !Validator::validatePassword('OnlyLetters')
        ];
        $results[] = [
            'name' => 'Validator rejects too short password',
            'pass' => !Validator::validatePassword('Pass1')
        ];

        // 5. Validator::validateElectionDates
        $err = null;
        $validDates = Validator::validateElectionDates('2026-11-01 10:00:00', '2026-11-02 17:00:00', $err);
        $invalidDates = Validator::validateElectionDates('2026-11-02 17:00:00', '2026-11-01 10:00:00', $err);
        $results[] = [
            'name' => 'Validator enforces closing time after opening time',
            'pass' => $validDates && !$invalidDates && strpos($err, 'strictly after') !== false
        ];

        // 6. CSVExporter Formula Injection (DDE) Defense
        $dangerousCells = ['=cmd|/c calc!A0', '+12345', '-SUM(A1:A10)', '@HYPERLINK("http://evil.com")'];
        $cleanCells = array_map([CSVExporter::class, 'sanitizeCell'], $dangerousCells);
        $results[] = [
            'name' => 'CSVExporter neutralizes spreadsheet formula injection prefixes (=, +, -, @)',
            'pass' => (
                $cleanCells[0] === "'=cmd|/c calc!A0" &&
                $cleanCells[1] === "'+12345" &&
                $cleanCells[2] === "'-SUM(A1:A10)" &&
                $cleanCells[3] === "'@HYPERLINK(\"http://evil.com\")"
            )
        ];

        return $results;
    }
}
