<?php

declare(strict_types=1);

namespace App\Service\RagChat;

use InvalidArgumentException;
use RuntimeException;

use function App\runSyncProcess;

final class DomainIndexManager
{
    private DomainDocumentStorage $storage;

    private string $projectRoot;

    private string $pythonBinary;

    public function __construct(
        DomainDocumentStorage $storage,
        ?string $projectRoot = null,
        string $pythonBinary = 'python3'
    ) {
        $this->storage = $storage;
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
        $this->pythonBinary = $pythonBinary;
    }

    /**
     * @return array{success:bool,stdout:string,stderr:string,cleared:bool}
     */
    public function rebuild(string $domain): array
    {
        try {
            $normalized = $this->storage->normaliseDomain($domain);
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException('Invalid domain supplied.', 0, $exception);
        }

        $documents = $this->storage->getDocumentFiles($normalized);
        if ($documents === []) {
            $this->storage->removeIndex($normalized);

            return [
                'success' => true,
                'stdout' => 'No documents available – cleared domain index.',
                'stderr' => '',
                'cleared' => true,
            ];
        }

        $corpusPath = $this->storage->getCorpusPath($normalized);
        $indexPath = $this->storage->getIndexPath($normalized);
        $uploadsDir = $this->storage->getUploadsDirectory($normalized);
        $this->ensureDirectory(dirname($corpusPath));

        $script = $this->projectRoot . '/scripts/rag_pipeline.py';
        if (!is_file($script)) {
            throw new RuntimeException('Pipeline script is missing.');
        }

        $args = [
            $script,
            $uploadsDir,
            '--corpus',
            $corpusPath,
            '--index',
            $indexPath,
            '--force',
        ];

        $result = runSyncProcess($this->pythonBinary, $args);
        $result['cleared'] = false;

        return $result;
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Unable to create directory: %s', $path));
        }
    }
}

