<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * UploadService
 *
 * Secure image upload handling: validates size, extension and real MIME type,
 * generates a random filename and stores the file inside the public uploads
 * directory (which itself denies script execution via .htaccess).
 */
final class UploadService
{
    private int $maxSize;
    /** @var string[] */
    private array $allowedMimes;
    /** @var string[] */
    private array $allowedExt;
    private string $path;

    public function __construct()
    {
        $cfg = (array) config('uploads', []);
        $this->maxSize      = (int) ($cfg['max_size'] ?? 2_097_152);
        $this->allowedMimes = (array) ($cfg['allowed_mimes'] ?? ['image/png', 'image/jpeg']);
        $this->allowedExt   = (array) ($cfg['allowed_ext'] ?? ['png', 'jpg', 'jpeg']);
        $this->path         = (string) ($cfg['path'] ?? __DIR__ . '/../../public/assets/uploads');
    }

    /**
     * Validate and store an uploaded image.
     *
     * @param array<string,mixed> $file An entry from $_FILES.
     * @return string The stored filename (relative to uploads/).
     *
     * @throws RuntimeException on any validation failure.
     */
    public function storeImage(array $file, string $prefix = 'img'): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload failed.');
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Invalid upload source.');
        }

        if ((int) $file['size'] > $this->maxSize) {
            throw new RuntimeException(
                'File is too large (max ' . round($this->maxSize / 1048576, 1) . ' MB).'
            );
        }

        // Verify the real MIME type from file contents, not the client header.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($file['tmp_name']);
        if (!in_array($mime, $this->allowedMimes, true)) {
            throw new RuntimeException('Unsupported file type.');
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowedExt, true)) {
            throw new RuntimeException('Unsupported file extension.');
        }

        if (!is_dir($this->path) && !mkdir($this->path, 0755, true) && !is_dir($this->path)) {
            throw new RuntimeException('Upload directory is not writable.');
        }

        $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $target   = rtrim($this->path, '/\\') . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException('Could not save the uploaded file.');
        }

        return $filename;
    }

    /**
     * Delete a previously stored upload (best effort).
     */
    public function delete(string $filename): void
    {
        if ($filename === '') {
            return;
        }
        $target = rtrim($this->path, '/\\') . DIRECTORY_SEPARATOR . basename($filename);
        if (is_file($target)) {
            @unlink($target);
        }
    }
}
