<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests\Support;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class HostPingTool extends Tool
{
    protected string $name = 'host_ping';

    protected string $description = 'Host-only tool used to tell the host server apart from the package server.';

    public function handle(Request $request): Response
    {
        return Response::text('pong');
    }
}
