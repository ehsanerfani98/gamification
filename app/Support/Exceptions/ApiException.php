<?php

namespace App\Support\Exceptions;

use RuntimeException;

/**
 * استثنای دامنه با کد ماشین‌خوان — قرارداد پاسخ خطا (فصل ۸-۱ سند معماری):
 * { "error": { "code": "MACHINE_CODE", "message": "پیام فارسی قابل نمایش", "fields": {} } }
 */
final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly array $fields = [],
    ) {
        parent::__construct($message);
    }
}
