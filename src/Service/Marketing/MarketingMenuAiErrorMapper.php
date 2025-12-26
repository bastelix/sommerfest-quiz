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

final class MarketingMenuAiErrorMapper
{
    /**
     * @return array{error_code:string,message:string,status:int}
     */
    public function map(Throwable $exception): array
    {
        $message = $exception instanceof RuntimeException ? $exception->getMessage() : '';

        if ($message === MarketingMenuAiGenerator::ERROR_PROMPT_MISSING) {
            return [
                'error_code' => 'prompt_missing',
                'message' => 'The AI prompt template is not configured.',
                'status' => 500,
            ];
        }

        if ($message === MarketingMenuAiGenerator::ERROR_RESPONDER_MISSING) {
            return [
                'error_code' => 'ai_unavailable',
                'message' => 'The AI responder is not configured. Check RAG_CHAT_SERVICE_* variables in your environment.',
                'status' => 500,
            ];
        }

        if ($message === MarketingMenuAiGenerator::ERROR_EMPTY_RESPONSE) {
            return [
                'error_code' => 'ai_empty',
                'message' => 'The AI responder returned an empty response.',
                'status' => 500,
            ];
        }

        if ($message === MarketingMenuAiGenerator::ERROR_INVALID_JSON) {
            return [
                'error_code' => 'ai_invalid_json',
                'message' => 'The AI responder did not return valid JSON.',
                'status' => 500,
            ];
        }

        if ($message === MarketingMenuAiGenerator::ERROR_INVALID_ITEMS) {
            return [
                'error_code' => 'ai_invalid_items',
                'message' => 'The AI responder did not return a valid items structure.',
                'status' => 500,
            ];
        }

        if ($message === MarketingMenuAiGenerator::ERROR_INVALID_LINKS) {
            return [
                'error_code' => 'ai_invalid_links',
                'message' => 'The AI responder returned links that are not present in the page content.',
                'status' => 422,
            ];
        }

        if ($message !== '' && str_starts_with($message, MarketingMenuAiGenerator::ERROR_RESPONDER_FAILED . ':')) {
            $details = trim(substr($message, strlen(MarketingMenuAiGenerator::ERROR_RESPONDER_FAILED . ':')));
            if ($this->isTimeout($exception)) {
                return [
                    'error_code' => 'ai_timeout',
                    'message' => $details !== ''
                        ? sprintf('The AI responder did not respond in time. %s', $details)
                        : 'The AI responder did not respond in time.',
                    'status' => 504,
                ];
            }

            if ($this->isRateLimit($exception)) {
                return [
                    'error_code' => 'ai_rate_limited',
                    'message' => $details !== ''
                        ? sprintf('The AI responder is temporarily rate limited. %s', $details)
                        : 'The AI responder is temporarily rate limited.',
                    'status' => 429,
                ];
            }

            return [
                'error_code' => 'ai_failed',
                'message' => $details !== ''
                    ? sprintf('The AI responder failed to generate navigation. %s', $details)
                    : 'The AI responder failed to generate navigation.',
                'status' => 500,
            ];
        }

        return [
            'error_code' => 'ai_error',
            'message' => 'The AI responder failed to generate navigation.',
            'status' => 500,
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

    private function isRateLimit(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());
        if (str_contains($message, '429') || str_contains($message, 'too many requests') || str_contains($message, 'rate limit')) {
            return true;
        }

        $previous = $exception->getPrevious();
        if ($previous instanceof Throwable) {
            return $this->isRateLimit($previous);
        }

        return false;
    }
}
