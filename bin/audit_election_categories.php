<?php
/**
 * VerityVote - Inspect & Recategorize Existing Elections
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

$db = Database::getInstance();
$distinct = $db->fetchAll("SELECT DISTINCT title, position, department FROM elections ORDER BY title ASC");

echo "Distinct Election Types Found: " . count($distinct) . "\n";
foreach ($distinct as $d) {
    echo "• Title: {$d['title']} | Position: {$d['position']} | Dept: " . ($d['department'] ?: 'None') . "\n";
}
