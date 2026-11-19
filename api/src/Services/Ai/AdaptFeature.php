<?php

namespace App\Configs\Content\Services\Ai;

/** Адаптация текста под площадку: формат, структура, хештеги, предел знаков. Результат — связанная версия публикации. */
final class AdaptFeature extends ContentFeature
{
    public function key(): string
    {
        return 'content.adapt';
    }

    protected function task(array $input): string
    {
        return 'Адаптируй текст под указанную площадку: её формат, привычную структуру и тон, предел длины. '
            . 'Смысл и факты сохрани, лишнее убери, при необходимости добавь уместные хештеги в конце.';
    }

    protected function answerFormat(): string
    {
        return 'Ответь ТОЛЬКО готовым текстом для публикации (без markdown-заголовков, без пояснений).';
    }

    protected function userBlocks(array $input): array
    {
        return [$this->postBlock($input), PlatformHints::block($input['platform'] ?? []), $this->textBlock($input, 'ИСХОДНЫЙ ТЕКСТ')];
    }

    public function postProcess(string $raw, array $input): string
    {
        return PlatformHints::fit($this->clean($raw), $input['platform'] ?? []);
    }
}
