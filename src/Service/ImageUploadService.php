<?php

declare(strict_types=1);

namespace App\Service;

use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Psr\Http\Message\UploadedFileInterface;

class ImageUploadService
{
    public const QUALITY_LOGO = 80;
    public const QUALITY_STICKER = 90;
    public const QUALITY_PHOTO = 70;

    private string $dataDir;
    private ImageManager $manager;

    public function __construct(?string $dataDir = null)
    {
        $this->dataDir = $dataDir ?? dirname(__DIR__, 2) . '/data';
        $this->manager = extension_loaded('imagick') ? ImageManager::imagick() : ImageManager::gd();
    }

    public function getDataDir(): string
    {
        return $this->dataDir;
    }

    public function validate(
        UploadedFileInterface $file,
        int $maxSize,
        array $allowedExtensions,
        array $allowedMimeTypes = []
    ): void {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('upload error');
        }
        $size = $file->getSize();
        if ($size !== null && $size > $maxSize) {
            throw new \RuntimeException('file too large');
        }
        $extension = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new \RuntimeException('unsupported file type');
        }
        $mime = strtolower($file->getClientMediaType() ?? '');
        if ($allowedMimeTypes !== [] && !in_array($mime, $allowedMimeTypes, true)) {
            throw new \RuntimeException('invalid mime type');
        }
    }

    public function readImage(UploadedFileInterface $file, bool $autoOrient = false): ImageInterface
    {
        $stream = $file->getStream();
        $image = $this->manager->read($stream->detach());
        if ($autoOrient) {
            $image->orient();
        }
        return $image;
    }

    public function saveImage(
        ImageInterface $image,
        string $dir,
        string $filename,
        ?int $maxWidth = null,
        ?int $maxHeight = null,
        int $quality = self::QUALITY_LOGO,
        ?string $format = null
    ): string {
        if ($maxWidth !== null || $maxHeight !== null) {
            $image->scaleDown($maxWidth ?? 0, $maxHeight ?? 0);
        }
        $dir = trim($dir, '/');
        $targetDir = $this->dataDir . '/' . $dir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('unable to create directory');
        }
        $path = $targetDir . '/' . $filename;
        $format = strtolower($format ?? pathinfo($filename, PATHINFO_EXTENSION));
        match ($format) {
            'png' => $image->toPng()->save($path),
            'jpg', 'jpeg' => $image->toJpeg($quality)->save($path),
            'webp' => $image->toWebp($quality)->save($path),
            default => $image->save($path, $quality),
        };
        return '/' . $dir . '/' . $filename;
    }

    public function saveUploadedFile(
        UploadedFileInterface $file,
        string $dir,
        string $baseName,
        ?int $maxWidth = null,
        ?int $maxHeight = null,
        int $quality = self::QUALITY_LOGO,
        bool $autoOrient = false,
        ?string $format = null
    ): string {
        $extension = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        $filename = $baseName . '.' . $extension;
        $image = $this->readImage($file, $autoOrient);
        return $this->saveImage($image, $dir, $filename, $maxWidth, $maxHeight, $quality, $format ?? $extension);
    }
}
