<?php

namespace App\Configs\Content\Mcp\Tools;

use App\Configs\Content\Mcp\Concerns\ContentMcpAccess;
use App\Mcp\Tools\PlatformTool;

/** Базовый класс tools чтения контент-фабрики (и «безопасных» вычислений вроде сборки промпта): токен чтения достаточно. */
abstract class ContentTool extends PlatformTool
{
    use ContentMcpAccess;

    protected function ability(): string
    {
        return 'content.read';
    }
}
