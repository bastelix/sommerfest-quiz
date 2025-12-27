<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\ConfigService;
use App\Service\ImageUploadService;
use App\Service\MediaLibraryService;
use App\Service\NamespaceValidator;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;

use function json_encode;

class MediaLibraryServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/media-lib-' . uniqid('', true);
        if (!is_dir($this->tempDir) && !mkdir($this->tempDir, 0775, true) && !is_dir($this->tempDir)) {
            throw new RuntimeException('unable to create temp directory');
        }
    }

    protected function tearDown(): void {
        $this->removeDir($this->tempDir);
        parent::tearDown();
    }

    public function testMetadataPersistenceRoundTrip(): void {
        [$service, $config, $images] = $this->createService();

        $tmp = tempnam($this->tempDir, 'img');
        file_put_contents($tmp, 'test-image');
        $stream = (new StreamFactory())->createStreamFromFile($tmp);
        $uploaded = new UploadedFile($stream, 'banner.png', 'image/png', $stream->getSize(), UPLOAD_ERR_OK);

        $uploadedInfo = $service->uploadFile(MediaLibraryService::SCOPE_GLOBAL, $uploaded, null, [
            'name' => 'homepage-banner',
            'tags' => ['Hero', 'Summer'],
            'folder' => 'marketing/home',
        ]);

        $this->assertSame(['Hero', 'Summer'], $uploadedInfo['tags']);
        $this->assertSame('marketing/home', $uploadedInfo['folder']);

        $files = $service->listFiles(MediaLibraryService::SCOPE_GLOBAL);
        $this->assertCount(1, $files);
        $this->assertSame(['Hero', 'Summer'], $files[0]['tags']);
        $this->assertSame('marketing/home', $files[0]['folder']);

        $updated = $service->renameFile(
            MediaLibraryService::SCOPE_GLOBAL,
            $uploadedInfo['name'],
            $uploadedInfo['name'],
            null,
            [
                'tags' => ['Highlight'],
                'folder' => 'marketing',
            ]
        );

        $this->assertSame(['Highlight'], $updated['tags']);
        $this->assertSame('marketing', $updated['folder']);

        $listed = $service->listFiles(MediaLibraryService::SCOPE_GLOBAL);
        $this->assertCount(1, $listed);
        $this->assertSame(['Highlight'], $listed[0]['tags']);
        $this->assertSame('marketing', $listed[0]['folder']);

        $metadataPath = $config->getGlobalUploadsDir() . DIRECTORY_SEPARATOR . '.media-metadata.json';
        $this->assertFileExists($metadataPath);

        $service->deleteFile(MediaLibraryService::SCOPE_GLOBAL, $updated['name']);

        $this->assertFileDoesNotExist($metadataPath);
        $this->assertSame([], $service->listFiles(MediaLibraryService::SCOPE_GLOBAL));
        $this->assertTrue($images->saveCalled, 'Raster uploads should be processed via ImageUploadService');
    }

    public function testSvgUploadBypassesRasterProcessing(): void {
        [$service, $config, $images] = $this->createService();

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4"/></svg>
SVG;

        $tmp = tempnam($this->tempDir, 'svg');
        file_put_contents($tmp, $svg);
        $stream = (new StreamFactory())->createStreamFromFile($tmp);
        $uploaded = new UploadedFile($stream, 'icon.svg', 'image/svg+xml', $stream->getSize(), UPLOAD_ERR_OK);

        $info = $service->uploadFile(MediaLibraryService::SCOPE_GLOBAL, $uploaded);

        $this->assertSame('icon.svg', $info['name']);
        $this->assertSame('svg', $info['extension']);
        $this->assertFalse($images->saveCalled, 'SVG uploads must not invoke ImageUploadService');

        $storedPath = $config->getGlobalUploadsDir() . DIRECTORY_SEPARATOR . 'icon.svg';
        $this->assertFileExists($storedPath);
        $this->assertStringEqualsFile($storedPath, $svg);
    }

    public function testPdfUploadBypassesRasterProcessing(): void {
        [$service, $config, $images] = $this->createService();

        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

        $tmp = tempnam($this->tempDir, 'pdf');
        file_put_contents($tmp, $pdf);
        $stream = (new StreamFactory())->createStreamFromFile($tmp);
        $uploaded = new UploadedFile($stream, 'document.pdf', 'application/pdf', $stream->getSize(), UPLOAD_ERR_OK);

        $info = $service->uploadFile(MediaLibraryService::SCOPE_GLOBAL, $uploaded);

        $this->assertSame('document.pdf', $info['name']);
        $this->assertSame('pdf', $info['extension']);
        $this->assertFalse($images->saveCalled, 'PDF uploads must not invoke ImageUploadService');

        $storedPath = $config->getGlobalUploadsDir() . DIRECTORY_SEPARATOR . 'document.pdf';
        $this->assertFileExists($storedPath);
        $this->assertStringEqualsFile($storedPath, $pdf);
    }

    public function testMp4UploadBypassesRasterProcessing(): void {
        [$service, $config, $images] = $this->createService();

        $video = str_repeat('mp4-video', 32);

        $tmp = tempnam($this->tempDir, 'mp4');
        file_put_contents($tmp, $video);
        $stream = (new StreamFactory())->createStreamFromFile($tmp);
        $uploaded = new UploadedFile($stream, 'teaser.mp4', 'video/mp4', $stream->getSize(), UPLOAD_ERR_OK);

        $info = $service->uploadFile(MediaLibraryService::SCOPE_GLOBAL, $uploaded);

        $this->assertSame('teaser.mp4', $info['name']);
        $this->assertSame('mp4', $info['extension']);
        $this->assertFalse($images->saveCalled, 'MP4 uploads must not invoke ImageUploadService');

        $storedPath = $config->getGlobalUploadsDir() . DIRECTORY_SEPARATOR . 'teaser.mp4';
        $this->assertFileExists($storedPath);
        $this->assertStringEqualsFile($storedPath, $video);
    }

    public function testMp3UploadBypassesRasterProcessing(): void {
        [$service, $config, $images] = $this->createService();

        $audio = str_repeat('mp3-audio', 16);

        $tmp = tempnam($this->tempDir, 'mp3');
        file_put_contents($tmp, $audio);
        $stream = (new StreamFactory())->createStreamFromFile($tmp);
        $uploaded = new UploadedFile($stream, 'theme.mp3', 'audio/mpeg', $stream->getSize(), UPLOAD_ERR_OK);

        $info = $service->uploadFile(MediaLibraryService::SCOPE_GLOBAL, $uploaded);

        $this->assertSame('theme.mp3', $info['name']);
        $this->assertSame('mp3', $info['extension']);
        $this->assertFalse($images->saveCalled, 'MP3 uploads must not invoke ImageUploadService');

        $storedPath = $config->getGlobalUploadsDir() . DIRECTORY_SEPARATOR . 'theme.mp3';
        $this->assertFileExists($storedPath);
        $this->assertStringEqualsFile($storedPath, $audio);
    }

    public function testWebmUploadBypassesRasterProcessing(): void {
        [$service, $config, $images] = $this->createService();

        $video = str_repeat('webm-video', 32);

        $tmp = tempnam($this->tempDir, 'webm');
        file_put_contents($tmp, $video);
        $stream = (new StreamFactory())->createStreamFromFile($tmp);
        $uploaded = new UploadedFile($stream, 'clip.webm', 'video/webm', $stream->getSize(), UPLOAD_ERR_OK);

        $info = $service->uploadFile(MediaLibraryService::SCOPE_GLOBAL, $uploaded);

        $this->assertSame('clip.webm', $info['name']);
        $this->assertSame('webm', $info['extension']);
        $this->assertFalse($images->saveCalled, 'WEBM uploads must not invoke ImageUploadService');

        $storedPath = $config->getGlobalUploadsDir() . DIRECTORY_SEPARATOR . 'clip.webm';
        $this->assertFileExists($storedPath);
        $this->assertStringEqualsFile($storedPath, $video);
    }

    public function testConvertVideoToWebmSkipsAudioOptionsWithoutAudioTrack(): void {
        [, $config, $images] = $this->createService();

        $sourcePath = $config->getGlobalUploadsDir() . DIRECTORY_SEPARATOR . 'clip.mp4';
        file_put_contents($sourcePath, 'dummy-video');

        $service = $this->createConversionService($config, $images, false);

        $info = $service->convertFile(MediaLibraryService::SCOPE_GLOBAL, 'clip.mp4');

        $this->assertSame('clip.webm', $info['name']);
        $this->assertSame('webm', $info['extension']);

        $ffmpegCall = null;
        foreach ($service->processCalls as $call) {
            if ($call[0] === 'ffmpeg') {
                $ffmpegCall = $call;
            }
        }

        $this->assertNotNull($ffmpegCall);
        $this->assertIsArray($ffmpegCall);
        /** @var array{0:string,1:list<string>} $ffmpegCall */
        $this->assertNotContains('-c:a', $ffmpegCall[1]);
        $this->assertNotContains('-b:a', $ffmpegCall[1]);
        $this->assertContains('-an', $ffmpegCall[1]);
    }

    public function testConvertVideoToWebmKeepsAudioOptionsWhenAudioIsPresent(): void {
        [, $config, $images] = $this->createService();

        $sourcePath = $config->getGlobalUploadsDir() . DIRECTORY_SEPARATOR . 'teaser.mp4';
        file_put_contents($sourcePath, 'dummy-video');

        $service = $this->createConversionService($config, $images, true);

        $info = $service->convertFile(MediaLibraryService::SCOPE_GLOBAL, 'teaser.mp4');

        $this->assertSame('teaser.webm', $info['name']);
        $this->assertSame('webm', $info['extension']);

        $ffmpegCall = null;
        foreach ($service->processCalls as $call) {
            if ($call[0] === 'ffmpeg') {
                $ffmpegCall = $call;
            }
        }

        $this->assertNotNull($ffmpegCall);
        $this->assertIsArray($ffmpegCall);
        /** @var array{0:string,1:list<string>} $ffmpegCall */
        $this->assertContains('-c:a', $ffmpegCall[1]);
        $this->assertContains('-b:a', $ffmpegCall[1]);
        $this->assertNotContains('-an', $ffmpegCall[1]);
    }

    public function testRawUploadCreatesProjectDirectory(): void
    {
        [$service, $config] = $this->createService();

        if (!method_exists($config, 'setCreateProjectDir')) {
            $this->markTestSkipped('project directory creation flag unavailable');
        }

        $config->setCreateProjectDir(false);
        $namespace = 'fresh-namespace';
        $targetDir = $config->getProjectUploadsDir($namespace);
        $this->assertDirectoryDoesNotExist($targetDir);

        $tmp = tempnam($this->tempDir, 'raw');
        file_put_contents($tmp, 'raw document');
        $stream = (new StreamFactory())->createStreamFromFile($tmp);
        $uploaded = new UploadedFile($stream, 'document.pdf', 'application/pdf', $stream->getSize(), UPLOAD_ERR_OK);

        $info = $service->uploadFile(MediaLibraryService::SCOPE_PROJECT, $uploaded, null, null, $namespace);

        $this->assertSame('document.pdf', $info['name']);
        $this->assertDirectoryExists($targetDir);
        $this->assertFileExists($targetDir . DIRECTORY_SEPARATOR . 'document.pdf');
    }

    private function removeDir(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($dir);
    }

    /**
     * @return array{0:MediaLibraryService,1:ConfigService,2:ImageUploadService}
     */
    private function createService(): array {
        $pdo = new PDO('sqlite::memory:');

        $config = new class ($pdo, $this->tempDir) extends ConfigService {
            private string $baseDir;
            private string $activeUid = 'event-test';
            private bool $createProjectDir = true;

            public function __construct(PDO $pdo, string $baseDir) {
                $this->baseDir = $baseDir;
                parent::__construct($pdo);
            }

            public function setActiveEventUid(string $uid): void {
                $this->activeUid = $uid;
            }

            public function getActiveEventUid(): string {
                return $this->activeUid;
            }

            public function getGlobalUploadsPath(): string {
                return '/uploads';
            }

            public function getGlobalUploadsDir(): string {
                $dir = $this->baseDir . '/uploads';
                if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                    throw new RuntimeException('unable to create uploads directory');
                }
                return $dir;
            }

            public function getProjectUploadsPath(string $namespace): string
            {
                $validator = new NamespaceValidator();
                $normalized = $validator->normalizeCandidate($namespace);
                if ($normalized === null) {
                    throw new RuntimeException('invalid namespace');
                }

                return '/uploads/projects/' . $normalized;
            }

            public function setCreateProjectDir(bool $create): void
            {
                $this->createProjectDir = $create;
            }

            public function getProjectUploadsDir(string $namespace): string
            {
                $dir = $this->baseDir . $this->getProjectUploadsPath($namespace);
                if ($this->createProjectDir && !is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                    throw new RuntimeException('unable to create project uploads directory');
                }

                return $dir;
            }

            public function getEventImagesPath(?string $uid = null): string {
                $uid = $uid ?? $this->activeUid;
                return '/events/' . $uid . '/images';
            }

            public function getEventImagesDir(?string $uid = null): string {
                $uid = $uid ?? $this->activeUid;
                $dir = $this->baseDir . '/events/' . $uid . '/images';
                if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                    throw new RuntimeException('unable to create event directory');
                }
                return $dir;
            }
        };

        $images = new class ($this->tempDir) extends ImageUploadService {
            public bool $saveCalled = false;
            private string $baseDir;

            public function __construct(string $baseDir) {
                $this->baseDir = rtrim($baseDir, '/');
            }

            public function validate(
                UploadedFileInterface $file,
                int $maxSize,
                array $allowedExtensions,
                array $allowedMimeTypes = []
            ): void {
                if ($file->getError() !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('upload error');
                }
                $size = $file->getSize();
                if ($size !== null && $size > $maxSize) {
                    throw new RuntimeException('file too large');
                }
                $extension = strtolower((string) pathinfo((string) $file->getClientFilename(), PATHINFO_EXTENSION));
                if (!in_array($extension, $allowedExtensions, true)) {
                    throw new RuntimeException('unsupported file type');
                }
                $mime = strtolower((string) $file->getClientMediaType());
                if ($allowedMimeTypes !== [] && !in_array($mime, $allowedMimeTypes, true)) {
                    throw new RuntimeException('invalid mime type');
                }
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
                $this->saveCalled = true;

                $extension = strtolower((string) pathinfo((string) $file->getClientFilename(), PATHINFO_EXTENSION));
                $filename = $baseName . '.' . $extension;
                $relative = trim($dir, '/');
                $targetDir = $this->baseDir . '/' . $relative;
                if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                    throw new RuntimeException('unable to create directory');
                }
                $stream = $file->getStream();
                if (method_exists($stream, 'rewind')) {
                    $stream->rewind();
                }
                $contents = $stream->getContents();
                $path = $targetDir . '/' . $filename;
                if (@file_put_contents($path, $contents) === false) {
                    throw new RuntimeException('unable to write file');
                }

                return '/' . $relative . '/' . $filename;
            }
        };

        return [new MediaLibraryService($config, $images), $config, $images];
    }

    private function createConversionService(
        ConfigService $config,
        ImageUploadService $images,
        bool $hasAudio
    ): MediaLibraryService {
        return new class ($config, $images, $hasAudio) extends MediaLibraryService {
            /** @var list<array{0:string,1:list<string>}> */
            public array $processCalls = [];

            private bool $hasAudio;

            public function __construct(ConfigService $config, ImageUploadService $images, bool $hasAudio)
            {
                parent::__construct($config, $images);
                $this->hasAudio = $hasAudio;
            }

            protected function runProcess(string $binary, array $args): array
            {
                $this->processCalls[] = [$binary, $args];

                if ($binary === 'ffprobe') {
                    $streams = $this->hasAudio ? [['codec_type' => 'audio']] : [];
                    return [
                        'success' => true,
                        'stdout' => json_encode(['streams' => $streams]),
                        'stderr' => '',
                    ];
                }

                if ($binary === 'ffmpeg') {
                    $target = end($args);
                    if (is_string($target)) {
                        $dir = dirname($target);
                        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                            throw new RuntimeException('unable to create target directory');
                        }
                        file_put_contents($target, 'webm');
                    }

                    return ['success' => true, 'stdout' => '', 'stderr' => ''];
                }

                return ['success' => true, 'stdout' => '', 'stderr' => ''];
            }
        };
    }
}
