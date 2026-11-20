<?php

namespace App\Configs\Content\Mcp\Tools;

use App\Configs\Content\Mcp\Concerns\ContentMcpAccess;
use App\Mcp\Tools\WriteTool;

/**
 * Базовый класс tools записи контент-фабрики: нужен токен с `mcp:write` (WriteTool) И право роли (`ability()`).
 * Каждый наследник называет своё право — автор не видит tools управления проектами, согласующий — запись текста.
 */
abstract class ContentWriteTool extends WriteTool
{
    use ContentMcpAccess;
}
