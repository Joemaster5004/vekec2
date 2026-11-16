<?php
declare(strict_types=1);
namespace App;
final class AiError extends \RuntimeException {
    public function __construct(public readonly string $reason, string $message, int $status=422) { parent::__construct($message,$status); }
}
