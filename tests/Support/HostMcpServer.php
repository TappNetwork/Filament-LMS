<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests\Support;

use Laravel\Mcp\Server;
use Tapp\FilamentLms\Mcp\Concerns\HasSanctumAbilities;
use Tapp\FilamentLms\Mcp\LmsServer;

class HostMcpServer extends Server
{
    use HasSanctumAbilities;

    protected string $name = 'Host App';

    protected array $tools = [
        ...LmsServer::TOOLS,
        HostPingTool::class,
    ];
}
