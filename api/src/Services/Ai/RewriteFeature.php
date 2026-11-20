<?php

namespace App\Configs\Content\Services\Ai;

/** Улучшить, сократить, расширить или сменить тон существующего текста; смысл и факты сохраняются. */
final class RewriteFeature extends ContentFeature
{
    public function key(): string
    {
        return 'content.rewrite';
    }

    protected function task(array $input): string
    {
        return 'Перепиши переданный текст по пожеланию (если его нет — улучши: яснее, живее, без воды), сохранив смысл и все факты. '
            . 'Не добавляй новых фактов.';
    }

    protected function answerFormat(): string
    {
        return 'Ответь ТОЛЬКО готовым текстом (без markdown, без пояснений и вариантов на выбор).' . $this->hashtagsRule();
    }

    protected function userBlocks(array $input): array
    {
        return [$this->postBlock($input), PlatformHints::block($input['platform'] ?? []), $this->textBlock($input, 'ИСХОДНЫЙ ТЕКСТ')];
    }

    public function postProcess(string $raw, array $input): string
    {
        return $this->bodyWithHashtags($raw, $input);
    }
}
