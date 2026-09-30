<?php
declare(strict_types=1);
function localPhoto(?string $path): ?string
{
    if ($path === null || !preg_match('~^assets/images/(?:uploads/)?[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp)$~D', $path)) { return null; }
    return is_file(dirname(__DIR__) . '/' . $path) ? $path : null;
}

function imageUploadLimit(): int
{
    $limit = trim((string) ini_get('upload_max_filesize'));
    $number = (float) $limit;
    $unit = strtolower(substr($limit, -1));
    $bytes = (int) ($number * (['g' => 1073741824, 'm' => 1048576, 'k' => 1024][$unit] ?? 1));
    return $bytes > 0 ? min(2097152, $bytes) : 2097152;
}

function storeImageUpload(string $field): ?string
{
    $file = $_FILES[$field] ?? null;
    if ($file === null) { return null; }
    if (!is_array($file) || !isset($file['error']) || !is_int($file['error'])) { throw new DomainException('Invalid image upload. Please select one file.'); }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) { return null; }
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) { throw new DomainException('The photo exceeds the upload size limit.'); }
    if ($file['error'] !== UPLOAD_ERR_OK) { throw new DomainException('The photo upload did not complete. Please select the file again.'); }
    if (!is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) { throw new DomainException('Invalid image upload.'); }
    $size = filesize($file['tmp_name']);
    if (!$size || $size > imageUploadLimit()) { throw new DomainException('Choose a photo within the upload size limit.'); }
    if (!extension_loaded('gd') || !extension_loaded('fileinfo')) { throw new RuntimeException('PHP GD and Fileinfo are required for photo uploads.'); }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($types[$mime])) { throw new DomainException('Choose a JPG, PNG, or WebP image.'); }
    $dimensions = @getimagesize($file['tmp_name']);
    if (!$dimensions || ($dimensions['mime'] ?? '') !== $mime || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] * $dimensions[1] > 8000000) {
        throw new DomainException('Choose a valid photo no larger than 8 megapixels.');
    }
    $image = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
    if ($image === false) { throw new DomainException('This image could not be decoded. Choose another photo.'); }
    $directory = dirname(__DIR__) . '/assets/images/uploads';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        imagedestroy($image);
        throw new RuntimeException('Image directory is unavailable.');
    }
    $relative = 'assets/images/uploads/' . bin2hex(random_bytes(16)) . '.' . $types[$mime];
    $destination = dirname(__DIR__) . '/' . $relative;
    // Decode and re-encode so uploaded metadata and appended executable bytes are discarded.
    imagealphablending($image, false);
    imagesavealpha($image, true);
    try {
        if ($mime === 'image/jpeg') { $saved = imagejpeg($image, $destination, 88); }
        elseif ($mime === 'image/png') { $saved = imagepng($image, $destination, 6); }
        else { $saved = imagewebp($image, $destination, 85); }
        if (!$saved) { throw new RuntimeException('Could not save the photo.'); }
        chmod($destination, 0644);
    } catch (Throwable $exception) {
        if (is_file($destination)) { unlink($destination); }
        throw $exception;
    } finally { imagedestroy($image); }
    return $relative;
}

function discardImageUpload(?string $path): void
{
    if ($path && preg_match('~^assets/images/uploads/[a-f0-9]{32}\.(jpg|png|webp)$~D', $path)) {
        $file = dirname(__DIR__) . '/' . $path;
        if (is_file($file)) { unlink($file); }
    }
}
