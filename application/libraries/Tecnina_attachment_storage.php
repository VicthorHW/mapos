<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Library: Tecnina_attachment_storage
 *
 * Implements private pre-OS attachment storage outside web root.
 * Governed by CIAO-S06A, ADR-005, and Technical Order 85 Section 18-21.
 *
 * Enforces:
 * - Persistent storage strictly outside public web root
 * - File validation by real content MIME (finfo) and extension
 * - Strict rejection of SVG, HTML, scripts, executables, archives
 * - Per-file limit (15 MiB) and per-intake limit (60 MiB)
 * - Random server-generated storage_key (never uses customer filename in filesystem)
 * - Path traversal prevention by construction
 * - Private thumbnail generation for images only (no PDF thumbnails)
 * - Safe response headers (nosniff, attachment disposition)
 */
class Tecnina_attachment_storage
{
    public const CEILING_FILE_SIZE_BYTES = 15728640;     // 15 MiB hard ceiling
    public const CEILING_INTAKE_SIZE_BYTES = 62914560;   // 60 MiB hard ceiling
    public const MAX_FILE_SIZE_BYTES = 15728640;     // 15 MiB legacy constant
    public const MAX_INTAKE_SIZE_BYTES = 62914560;   // 60 MiB legacy constant

    private const ALLOWED_MIME_EXT_MAP = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'application/pdf' => ['pdf'],
    ];

    private string $storageRoot;
    private string $thumbRoot;

    public function __construct()
    {
        $configured = $_ENV['TECNINA_PRIVATE_INTAKE_STORAGE_PATH'] ?? getenv('TECNINA_PRIVATE_INTAKE_STORAGE_PATH');
        if (! empty($configured)) {
            $this->storageRoot = rtrim((string) $configured, '/\\');
        } else {
            // Default outside web root
            $this->storageRoot = '/var/lib/mapos/private-intakes';
        }

        $this->thumbRoot = $this->storageRoot . DIRECTORY_SEPARATOR . 'thumbs';

        // Ensure directories exist if writable
        if (! is_dir($this->storageRoot)) {
            @mkdir($this->storageRoot, 0750, true);
        }
        if (! is_dir($this->thumbRoot)) {
            @mkdir($this->thumbRoot, 0750, true);
        }
    }

    public function getMaxFileSizeBytes(): int
    {
        $val = $_ENV['TECNINA_PRIVATE_ATTACHMENT_MAX_FILE_BYTES'] ?? getenv('TECNINA_PRIVATE_ATTACHMENT_MAX_FILE_BYTES');
        if ($val !== false && $val !== null && $val !== '') {
            $num = (int) $val;
            if ($num > 0) {
                return min($num, self::CEILING_FILE_SIZE_BYTES);
            }
        }
        return self::CEILING_FILE_SIZE_BYTES;
    }

    public function getMaxIntakeSizeBytes(): int
    {
        $val = $_ENV['TECNINA_PRIVATE_ATTACHMENT_MAX_INTAKE_BYTES'] ?? getenv('TECNINA_PRIVATE_ATTACHMENT_MAX_INTAKE_BYTES');
        if ($val !== false && $val !== null && $val !== '') {
            $num = (int) $val;
            if ($num > 0) {
                return min($num, self::CEILING_INTAKE_SIZE_BYTES);
            }
        }
        return self::CEILING_INTAKE_SIZE_BYTES;
    }

    public function getStorageRoot(): string
    {
        return $this->storageRoot;
    }

    /**
     * Validate an uploaded file for MIME, extension, size, and disallow dangerous content.
     */
    public function validateUpload(array $fileInfo, int $currentIntakeTotalBytes = 0): array
    {
        $isUploaded = isset($fileInfo['tmp_name']) && (is_uploaded_file($fileInfo['tmp_name']) || ((is_cli() || (defined('ENVIRONMENT') && ENVIRONMENT === 'testing')) && file_exists($fileInfo['tmp_name'])));
        if (! $isUploaded) {
            return ['ok' => false, 'reason' => 'invalid_upload_file'];
        }

        $size = (int) ($fileInfo['size'] ?? 0);
        $maxFile = $this->getMaxFileSizeBytes();
        if ($size <= 0 || $size > $maxFile) {
            return ['ok' => false, 'reason' => 'file_size_exceeded'];
        }

        $maxIntake = $this->getMaxIntakeSizeBytes();
        if (($currentIntakeTotalBytes + $size) > $maxIntake) {
            return ['ok' => false, 'reason' => 'intake_total_size_exceeded'];
        }

        $origName = (string) ($fileInfo['name'] ?? 'upload');
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        // Detect real MIME type from file content
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $fileInfo['tmp_name']);
        finfo_close($finfo);

        if (! isset(self::ALLOWED_MIME_EXT_MAP[$detectedMime])) {
            return ['ok' => false, 'reason' => 'unsupported_file_type', 'detected_mime' => $detectedMime];
        }

        $allowedExts = self::ALLOWED_MIME_EXT_MAP[$detectedMime];
        if (! in_array($ext, $allowedExts, true)) {
            return ['ok' => false, 'reason' => 'extension_mime_mismatch'];
        }

        // Additional safety check against embedded script/html in images/pdf
        $sample = file_get_contents($fileInfo['tmp_name'], false, null, 0, 4096);
        if ($sample !== false) {
            $lower = strtolower($sample);
            if (
                strpos($lower, '<script') !== false
                || strpos($lower, '<?php') !== false
                || strpos($lower, '<html') !== false
                || strpos($lower, '<svg') !== false
                || strpos($lower, 'eval(') !== false
            ) {
                return ['ok' => false, 'reason' => 'dangerous_content_detected'];
            }
        }

        return [
            'ok' => true,
            'detected_mime' => $detectedMime,
            'extension' => $ext,
            'size_bytes' => $size,
            'original_name' => $origName,
        ];
    }

    /**
     * Store an uploaded file in the private storage directory.
     * Computes SHA-256 and generates thumbnail for images.
     */
    public function storeUpload(array $fileInfo, array $validationResult): array
    {
        $ext = $validationResult['extension'];
        $storageKey = bin2hex(random_bytes(16)) . '.' . $ext;
        $targetPath = $this->storageRoot . DIRECTORY_SEPARATOR . $storageKey;

        $moved = @move_uploaded_file($fileInfo['tmp_name'], $targetPath);
        if (! $moved && (is_cli() || (defined('ENVIRONMENT') && ENVIRONMENT === 'testing'))) {
            $moved = @copy($fileInfo['tmp_name'], $targetPath);
        }

        if (! $moved) {
            return ['ok' => false, 'reason' => 'storage_write_failed'];
        }

        chmod($targetPath, 0640);
        $sha256 = hash_file('sha256', $targetPath);

        // Generate thumbnail if image
        $hasThumbnail = false;
        if (in_array($validationResult['detected_mime'], ['image/jpeg', 'image/png'], true)) {
            $thumbPath = $this->thumbRoot . DIRECTORY_SEPARATOR . $storageKey;
            $hasThumbnail = $this->generateThumbnail($targetPath, $thumbPath, $validationResult['detected_mime']);
        }

        // Sanitize original filename for storage metadata
        $safeOriginalName = $this->sanitizeFilename($validationResult['original_name']);

        return [
            'ok' => true,
            'storage_key' => $storageKey,
            'original_name' => $safeOriginalName,
            'detected_mime' => $validationResult['detected_mime'],
            'size_bytes' => $validationResult['size_bytes'],
            'sha256' => $sha256,
            'has_thumbnail' => $hasThumbnail,
        ];
    }

    /**
     * Generate an image thumbnail without executing metadata.
     */
    public function generateThumbnail(string $sourcePath, string $destPath, string $mime, int $maxWidth = 300, int $maxHeight = 300): bool
    {
        if (! function_exists('imagecreatetruecolor')) {
            return false;
        }

        try {
            $src = null;
            if ($mime === 'image/jpeg') {
                $src = @imagecreatefromjpeg($sourcePath);
            } elseif ($mime === 'image/png') {
                $src = @imagecreatefrompng($sourcePath);
            }

            if (! $src) {
                return false;
            }

            $origW = imagesx($src);
            $origH = imagesy($src);

            if ($origW <= 0 || $origH <= 0) {
                imagedestroy($src);
                return false;
            }

            $ratio = min($maxWidth / $origW, $maxHeight / $origH);
            if ($ratio >= 1.0) {
                $newW = $origW;
                $newH = $origH;
            } else {
                $newW = (int) round($origW * $ratio);
                $newH = (int) round($origH * $ratio);
            }

            $thumb = imagecreatetruecolor($newW, $newH);

            if ($mime === 'image/png') {
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
            }

            imagecopyresampled($thumb, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

            if ($mime === 'image/jpeg') {
                imagejpeg($thumb, $destPath, 85);
            } else {
                imagepng($thumb, $destPath, 8);
            }

            imagedestroy($src);
            imagedestroy($thumb);
            chmod($destPath, 0640);

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Resolve and verify that a storage key stays within the storage root (traversal defense).
     */
    public function resolveFilePath(string $storageKey, bool $thumbnail = false): ?string
    {
        // Must match strict random key pattern: 32 hex chars + dot + extension
        if (! preg_match('/^[a-f0-9]{32}\.(jpg|jpeg|png|pdf)$/i', $storageKey)) {
            return null;
        }

        $base = $thumbnail ? $this->thumbRoot : $this->storageRoot;
        $path = $base . DIRECTORY_SEPARATOR . $storageKey;

        if (! file_exists($path)) {
            return null;
        }

        return $path;
    }

    /**
     * Delete an attachment file and its thumbnail.
     */
    public function deleteFile(string $storageKey): bool
    {
        $deleted = false;
        $path = $this->resolveFilePath($storageKey, false);
        if ($path && file_exists($path)) {
            $deleted = @unlink($path);
        }

        $thumbPath = $this->resolveFilePath($storageKey, true);
        if ($thumbPath && file_exists($thumbPath)) {
            @unlink($thumbPath);
        }

        return $deleted;
    }

    /**
     * Sanitize original filename for storage metadata.
     */
    public function sanitizeFilename(string $filename): string
    {
        // Strip directory separators and traversal characters
        $base = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $filename));
        $clean = preg_replace('/[^\p{L}\p{N}\._\-\s]/u', '_', $base);
        $clean = trim($clean, '. ');
        return mb_substr($clean ?: 'anexo', 0, 255);
    }
}
