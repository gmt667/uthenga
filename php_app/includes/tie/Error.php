<?php

class UthengaTieException extends RuntimeException
{
    private string $type;
    private int $httpStatus;
    private array $details;

    public function __construct(string $type, string $message, int $httpStatus = 500, array $details = [])
    {
        parent::__construct($message);
        $this->type = $type;
        $this->httpStatus = $httpStatus;
        $this->details = $details;
    }

    public function type(): string { return $this->type; }
    public function httpStatus(): int { return $this->httpStatus; }
    public function details(): array { return $this->details; }
}

final class UthengaTieErrors
{
    public static function validation(array $fields): UthengaTieException
    {
        return new UthengaTieException('validation_error', 'The request is invalid.', 422, ['fields' => $fields]);
    }

    public static function authentication(): UthengaTieException
    {
        return new UthengaTieException('authentication_error', 'Authentication is required.', 401);
    }

    public static function authorization(): UthengaTieException
    {
        return new UthengaTieException('authorization_error', 'You are not allowed to access this resource.', 403);
    }

    public static function featureDisabled(string $feature): UthengaTieException
    {
        return new UthengaTieException('feature_disabled', 'This travel feature is not enabled.', 503, ['feature' => $feature]);
    }

    public static function providerUnavailable(string $provider): UthengaTieException
    {
        return new UthengaTieException('provider_error', 'A required travel provider is unavailable.', 503, ['provider' => $provider]);
    }

    public static function rateLimited(): UthengaTieException
    {
        return new UthengaTieException('rate_limited', 'Too many requests. Please try again shortly.', 429);
    }

    public static function response(Throwable $error, string $requestId): array
    {
        if ($error instanceof UthengaTieException) {
            return [
                'success' => false,
                'error' => [
                    'type' => $error->type(),
                    'message' => $error->getMessage(),
                    'details' => $error->details(),
                    'request_id' => $requestId,
                ],
                '_http_status' => $error->httpStatus(),
            ];
        }

        return [
            'success' => false,
            'error' => [
                'type' => 'internal_error',
                'message' => 'The travel service could not complete this request.',
                'request_id' => $requestId,
            ],
            '_http_status' => 500,
        ];
    }
}
