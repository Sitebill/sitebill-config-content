<?php

namespace App\Configs\Content\Services\Ai;

/** Хештеги по публикации и теме проекта (через пробел, с «#»). */
final class HashtagsFeature extends ContentFeature
{
    public function key(): string
    {
        return 'content.hashtags';
    }

    protected function task(array $input): string
    {
        return 'Подбери 6–10 хештегов к публикации: от широких к узким, без пробелов внутри, без повторов, без запрещённых и вводящих в заблуждение тегов.';
    }

    protected function answerFormat(): string
    {
        return 'Ответь ТОЛЬКО хештегами через пробел, каждый начинается с «#».';
    }

    protected function userBlocks(array $input): array
    {
        return [$this->postBlock($input), PlatformHints::block($input['platform'] ?? []), $this->textBlock($input)];
    }

    public function maxTokens(): int
    {
        return 200;
    }

    public function postProcess(string $raw, array $input): string
    {
        preg_match_all('/#[\p{L}\p{N}_]+/u', $raw, $m);

        return implode(' ', array_slice(array_values(array_unique($m[0])), 0, 12));
    }
}
