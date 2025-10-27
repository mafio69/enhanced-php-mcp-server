<?php

namespace App\Services;

use App\Interfaces\ToolInterface;
use App\Interfaces\ToolExecutorInterface;
use App\Tools\BraveSearchTool;
use App\Tools\CalculateTool;
use App\Tools\GetTimeTool;
use App\Tools\GetWeatherTool;
use App\Tools\GitHubTool;
use App\Tools\HelloTool;
use App\Tools\HttpRequestTool;
use App\Tools\JsonParseTool;
use App\Tools\PHPDiagnosticsTool;
use App\Tools\ListFilesTool;
use App\Tools\PlaywrightTool;
use App\Tools\ReadFileTool;
use App\Tools\SystemInfoTool;
use App\Tools\WriteFileTool;
use Exception;

class ToolRegistry implements ToolExecutorInterface
{
    private array $tools = [];

    public function __construct()
    {
        $this->registerDefaultTools();
    }

    private function registerDefaultTools(): void
    {
        $this->registerTool(new HelloTool());
        $this->registerTool(new GetTimeTool());
        $this->registerTool(new CalculateTool());
        $this->registerTool(new ListFilesTool());
        $this->registerTool(new ReadFileTool());
        $this->registerTool(new WriteFileTool());
        $this->registerTool(new SystemInfoTool());
        $this->registerTool(new HttpRequestTool());
        $this->registerTool(new JsonParseTool());
        $this->registerTool(new GetWeatherTool());
        $this->registerTool(new BraveSearchTool());
        $this->registerTool(new GitHubTool());
        $this->registerTool(new PHPDiagnosticsTool());
        $this->registerTool(new PlaywrightTool());
    }

    public function registerTool(ToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    public function executeTool(string $toolName, array $arguments = []): string
    {
        if (!$this->hasTool($toolName)) {
            throw new Exception("Nieznane narzędzie: {$toolName}");
        }

        $tool = $this->tools[$toolName];
        if (!$tool->isEnabled()) {
            throw new Exception("Tool '{$toolName}' is disabled");
        }

        return $tool->execute($arguments);
    }

    public function hasTool(string $toolName): bool
    {
        return isset($this->tools[$toolName]);
    }

    public function getTools(): array
    {
        $toolsData = [];
        foreach ($this->tools as $tool) {
            if ($tool->isEnabled()) {
                $toolsData[] = [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'inputSchema' => $tool->getSchema()
                ];
            }
        }
        return $toolsData;
    }
}