<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 出场业务规则被违反（如最后一轮不合格却要求放行）。
 * 由全局异常处理统一渲染成 422 JSON。
 */
class GatePassRuleException extends RuntimeException
{
    public string $errorCode;

    public function __construct(string $message, string $errorCode = 'RULE_VIOLATION')
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
    }
}
