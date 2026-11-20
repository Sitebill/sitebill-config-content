<?php

namespace App\Configs\Content\Services\Ai;

/** Публикация с нуля по карточке проекта, рубрике, формату и тезисам. */
final class DraftFeature extends ContentFeature
{
    public function key(): string
    {
        return 'content.draft';
    }

    protected function task(array $input): string
    {
        return 'Напиши публикацию с нуля: цепляющее начало, понятная структура, конкретика, спокойный призыв в конце. '
            . 'Учитывай тематику, аудиторию и тон проекта; если задана площадка — её формат и предел длины.';
    }

    protected function answerFormat(): string
    {
        return 'Ответь ТОЛЬКО готовым текстом простыми абзацами (без markdown, без заголовка, без пояснений и вариантов на выбор).' . $this->hashtagsRule();
    }

    protected function userBlocks(array $input): array
    {
        return [$this->postBlock($input), PlatformHints::block($input['platform'] ?? []), 'Задача: напиши публикацию по тезисам из пожелания (если они есть) и теме проекта.'];
    }

    public function postProcess(string $raw, array $input): string
    {
        return $this->bodyWithHashtags($raw, $input);
    }

    public function maxTokens(): int
    {
        return 1600;
    }
}
