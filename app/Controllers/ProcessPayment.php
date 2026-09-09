<?php

declare(strict_types=1);

namespace App\Controllers;

use Bin\Request\Request;
use Bin\Response\Response;

/**
 * ProcessPayment 控制器
 */
class ProcessPayment
{
    /**
     * 单一动作控制器
     */
    public function __invoke(Request $request): Response
    {
        return new Response();
    }
}
