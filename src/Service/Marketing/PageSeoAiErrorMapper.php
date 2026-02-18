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

final class PageSeoAiErrorMapper
{
    /**
     * @return array{error_code:string,message:string,status:int}
     */
    public function map(Throwable $exception): array
    {
        $message = $exception instanceof RuntimeException ? $exception->getMessage() : '';

        if ($message === PageSeoAiGenerator::ERROR_PROMPT_MISSING) {
            return [
                'error_code' => 'prompt_missing',
                'message' => 'Das KI-Prompt-Template für die SEO-Generierung fehlt. Bitte Konfiguration prüfen.',
                'status' => 500,
            ];
        }

        if ($message === PageSeoAiGenerator::ERROR_RESPONDER_MISSING) {
            return [
                'error_code' => 'ai_unavailable',
                'message' => 'Der KI-Dienst ist nicht konfiguriert. Bitte RAG_CHAT_SERVICE-Umgebungsvariablen prüfen.',
                'status' => 500,
            ];
        }

        if ($message === PageSeoAiGenerator::ERROR_EMPTY_RESPONSE) {
            return [
                'error_code' => 'ai_empty',
                'message' => 'Die KI hat eine leere Antwort zurückgegeben. Bitte erneut versuchen.',
                'status' => 500,
            ];
        }

        if ($message === PageSeoAiGenerator::ERROR_INVALID_JSON) {
            return [
                'error_code' => 'ai_invalid_json',
                'message' => 'Die KI-Antwort enthält kein gültiges JSON. Bitte erneut versuchen.',
                'status' => 500,
            ];
        }

        if ($message !== '' && str_starts_with($message, PageSeoAiGenerator::ERROR_RESPONDER_FAILED . ':')) {
            $details = trim(substr($message, strlen(PageSeoAiGenerator::ERROR_RESPONDER_FAILED . ':')));
            if ($this->isTimeout($exception)) {
                return [
                    'error_code' => 'ai_timeout',
                    'message' => $details !== ''
                        ? sprintf('Die KI hat nicht rechtzeitig geantwortet (Timeout). Details: %s', $details)
                        : 'Die KI hat nicht rechtzeitig geantwortet (Timeout). Bitte erneut versuchen.',
                    'status' => 504,
                ];
            }

            if ($this->isRateLimit($exception)) {
                return [
                    'error_code' => 'ai_rate_limited',
                    'message' => $details !== ''
                        ? sprintf('Der KI-Dienst ist vorübergehend überlastet (Rate-Limit). Details: %s', $details)
                        : 'Der KI-Dienst ist vorübergehend überlastet. Bitte in einigen Sekunden erneut versuchen.',
                    'status' => 429,
                ];
            }

            return [
                'error_code' => 'ai_failed',
                'message' => $details !== ''
                    ? sprintf('KI-Fehler bei der SEO-Generierung. Details: %s', $details)
                    : 'KI-Fehler bei der SEO-Generierung. Bitte erneut versuchen.',
                'status' => 500,
            ];
        }

        return [
            'error_code' => 'ai_error',
            'message' => 'Unbekannter KI-Fehler bei der SEO-Generierung. Bitte erneut versuchen oder Logs prüfen.',
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
        if (
            str_contains($message, '429')
            || str_contains($message, 'too many requests')
            || str_contains($message, 'rate limit')
        ) {
            return true;
        }

        $previous = $exception->getPrevious();
        if ($previous instanceof Throwable) {
            return $this->isRateLimit($previous);
        }

        return false;
    }
}
