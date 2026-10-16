<?php

namespace OiLab\OiLaravelDrive\Tests\Fixtures;

use Laravel\Mcp\Server;
use OiLab\OiLaravelDrive\Mcp\DriveTools;

/**
 * A host's MCP server, holding nothing but the drive tools.
 */
class DriveServer extends Server
{
    protected string $name = 'Drive';

    protected array $tools = [];

    protected function boot(): void
    {
        $this->tools = DriveTools::all();
    }
}
