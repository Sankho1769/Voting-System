<?php
namespace App\Helpers;

/**
 * Business and Security Input Validation Helper
 */
class Validator
{
    /**
     * Institutional ID validation (Roll numbers / Employee IDs / Admin IDs)
     * e.g., BCA2024-001, EMP-8942, STU1024, ADMIN01
     */
    public static function validateIdentifier(string $id): bool
    {
        $id = trim($id);
        if (strlen($id) < 3 || strlen($id) > 64) {
            return false;
        }
        // Allows alphanumeric, dashes, underscores, slashes
        return (bool)preg_match('/^[A-Za-z0-9\-\/_]+$/', $id);
    }

    /**
     * Password strength validation
     * Minimum 8 characters, at least one letter and one number
     */
    public static function validatePassword(string $password): bool
    {
        if (strlen($password) < 8 || strlen($password) > 128) {
            return false;
        }
        $hasLetter = preg_match('/[A-Za-z]/', $password);
        $hasNumber = preg_match('/[0-9]/', $password);
        return $hasLetter && $hasNumber;
    }

    /**
     * Validates election dates (both valid datetime strings, and closes_at strictly after opens_at)
     */
    public static function validateElectionDates(string $opensAt, string $closesAt, ?string &$error = null): bool
    {
        $openTime = strtotime($opensAt);
        $closeTime = strtotime($closesAt);

        if (!$openTime) {
            $error = 'Invalid election opening date and time.';
            return false;
        }

        if (!$closeTime) {
            $error = 'Invalid election closing date and time.';
            return false;
        }

        if ($closeTime <= $openTime) {
            $error = 'Election closing time must be strictly after the opening time.';
            return false;
        }

        return true;
    }

    /**
     * Validates candidate image file upload
     * Checks upload error, file size, MIME type using finfo, extension, and getimagesize
     */
    public static function validateUploadedImage(array $file, array $allowedMimes, array $allowedExts, int $maxBytes, ?string &$error = null): bool
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum upload size (2MB).',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                default => 'File upload error code: ' . $file['error'],
            };
            return false;
        }

        if ($file['size'] > $maxBytes) {
            $error = 'Image exceeds the maximum allowed size of 2MB.';
            return false;
        }

        $tmpPath = $file['tmp_name'];
        if (!is_uploaded_file($tmpPath)) {
            $error = 'Security check failed: File is not a valid uploaded file.';
            return false;
        }

        // Check real MIME type using Fileinfo
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($tmpPath);

        if (!in_array($realMime, $allowedMimes, true)) {
            $error = 'Invalid image format. Only JPEG, PNG, and WebP are accepted.';
            return false;
        }

        // Check extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) {
            $error = 'Invalid image extension.';
            return false;
        }

        // Ensure it is truly an image using getimagesize
        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false) {
            $error = 'File is not a valid or readable image.';
            return false;
        }

        return true;
    }
}
