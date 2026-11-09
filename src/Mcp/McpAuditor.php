<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp;

interface McpAuditor
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(array $context): void;
}
