<?php
/**
 * VerityVote - Accurate Categorization of Historical & Campus Elections
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

$db = Database::getInstance();
$elections = $db->fetchAll("SELECT id, title, position, department, category FROM elections");

$updated = 0;
$counts = [];

foreach ($elections as $el) {
    $id = (int)$el['id'];
    $title = $el['title'] ?? '';
    $pos = $el['position'] ?? '';
    $dept = $el['department'] ?? '';

    $titleLower = strtolower($title);
    $posLower = strtolower($pos);

    $cat = 'student_council';
    $newDept = $dept;

    if (strpos($titleLower, 'department') !== false) {
        $cat = 'department';
        if (empty($newDept)) $newDept = 'Computer Applications';
    } elseif ($posLower === 'president' || strpos($titleLower, 'presidential') !== false) {
        $cat = 'college_president';
        if (empty($newDept)) $newDept = 'Campus-Wide';
    } elseif (strpos($titleLower, 'cultural') !== false || $posLower === 'cultural secretary' || strpos($titleLower, 'club') !== false || strpos($titleLower, 'society') !== false) {
        $cat = 'club_society';
        if (empty($newDept)) $newDept = 'Cultural & Arts Guild';
    } elseif (strpos($posLower, 'security') !== false || strpos($posLower, 'auditor') !== false || strpos($titleLower, 'committee') !== false || strpos($posLower, 'chair') !== false) {
        $cat = 'committee';
        if (empty($newDept)) $newDept = 'Electoral & Audit Oversight';
    } elseif (strpos($titleLower, 'senate') !== false || strpos($titleLower, 'university') !== false) {
        $cat = 'university';
        if (empty($newDept)) $newDept = 'University Academic Senate';
    } elseif (strpos($titleLower, 'class rep') !== false || strpos($posLower, 'class rep') !== false) {
        $cat = 'class_rep';
        if (empty($newDept)) $newDept = 'BCA';
    } elseif (strpos($titleLower, 'other election') !== false || $posLower === 'treasurer') {
        $cat = 'other';
        if (empty($newDept)) $newDept = 'Special Assembly';
    } else {
        $cat = 'student_council';
        if (empty($newDept)) $newDept = 'Student Council';
    }

    $db->execute(
        "UPDATE elections SET category = ?, department = ? WHERE id = ?",
        [$cat, $newDept, $id]
    );

    $counts[$cat] = ($counts[$cat] ?? 0) + 1;
    $updated++;
}

echo "Successfully categorized {$updated} elections.\n";
echo "New Category Distribution:\n";
print_r($counts);
