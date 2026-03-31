<?php

declare(strict_types=1);

namespace OpenCompany\PrismRelay\Errors;

enum ErrorCategory: string
{
    case Auth = 'auth';
    case RateLimit = 'rate_limit';
    case Overloaded = 'overloaded';
    case RequestTooLarge = 'request_too_large';
    case Server = 'server';
    case Network = 'network';
    case InvalidRequest = 'invalid_request';
    case ContentFilter = 'content_filter';
    case InsufficientBalance = 'insufficient_balance';
}
