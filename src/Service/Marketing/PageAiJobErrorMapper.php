<?php

declare(strict_types=1);

namespace App\Service\Marketing;

use RuntimeException;
use Throwable;

use function sprintf;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;

final class PageAiJobErrorMapper
{
    /**
     * @return array{error_code:string,message:string}
     */
    public function map(Throwable $exception): array
    {
        $message = $exception instanceof RuntimeException ? $exception->getMessage() : '';

        if ($message === PageAiGenerator::ERROR_PROMPT_MISSING) {
            return [
                'error_code' => 'prompt_missing',
                'message' => 'The AI prompt template is not configured.',
            ];
        }

        if ($message === PageAiGenerator::ERROR_RESPONDER_MISSING) {
            return [
                'error_code' => 'ai_unavailable',
                'message' => 'The AI responder is not configured. Check RAG_CHAT_SERVICE_URL (and RAG_CHAT_SERVICE_TOKEN, RAG_CHAT_SERVICE_MODEL, RAG_CHAT_SERVICE_TIMEOUT) in your environment configuration.',
            ];
        }

        if ($message === PageAiGenerator::ERROR_EMPTY_RESPONSE) {
            return [
                'error_code' => 'ai_empty',
                'message' => 'The AI responder returned an empty response.',
            ];
        }

        if ($message === PageAiGenerator::ERROR_INVALID_HTML) {
            return [
                'error_code' => 'ai_invalid_html',
                'message' => 'The AI responder returned HTML that did not pass validation.',
            ];
        }

        if ($message !== '' && str_starts_with($message, PageAiGenerator::ERROR_RESPONDER_FAILED . ':')) {
            $details = trim(substr($message, strlen(PageAiGenerator::ERROR_RESPONDER_FAILED . ':')));
            if ($this->isTimeout($exception)) {
                return [
                    'error_code' => 'ai_timeout',
                    'message' => $details !== ''
                        ? sprintf('The AI responder did not respond in time. %s', $details)
                        : 'The AI responder did not respond in time.',
                ];
            }

            return [
                'error_code' => 'ai_failed',
                'message' => $details !== ''
                    ? sprintf('The AI responder failed to generate HTML. %s', $details)
                    : 'The AI responder failed to generate HTML.',
            ];
        }

        return [
            'error_code' => 'ai_error',
            'message' => 'The AI responder failed to generate HTML.',
        ];
    }

    private function isTimeout(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());
        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            return true;
        }

        $previous = $exception->getPrevious();
        if ($previous instanceof Throwable) {
            return $this->isTimeout($previous);
        }

        return false;
    }
}
