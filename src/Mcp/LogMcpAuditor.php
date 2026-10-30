<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp;

use Illuminate\Support\Facades\Log;

class LogMcpAuditor implements McpAuditor
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(array $context): void
    {
        Log::info('LMS MCP write.', $context);
    }
}
