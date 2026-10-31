<?php

namespace App\Exceptions;

use Exception;

class GatewayException extends Exception
{
    public function __construct(
        public readonly string $errorType,
        string $message,
        public readonly int $status = 400,
        public readonly ?string $errorCode = null,
        public readonly ?string $param = null,
    ) {
        parent::__construct($message);
    }

    /**
     * @return array{error: array{message: string, type: string, param: string|null, code: string|null}}
     */
    public function payload(): array
    {
        return [
            'error' => [
                'message' => $this->getMessage(),
                'type' => $this->errorType,
                'param' => $this->param,
                'code' => $this->errorCode,
            ],
        ];
    }
}
