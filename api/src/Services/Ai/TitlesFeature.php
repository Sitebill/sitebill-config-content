<?php

namespace App\Configs\Content\Services\Ai;

/** Варианты заголовка (по одному на строку). */
final class TitlesFeature extends ContentFeature
{
    public function key(): string
    {
        return 'content.titles';
    }

    protected function task(array $input): string
    {
        return 'Предложи 5 разных заголовков для публикации: конкретных, без кликбейта и обмана, до 90 знаков каждый.';
    }

    protected function answerFormat(): string
    {
        return 'Ответь ТОЛЬКО списком: один заголовок на строке, без нумерации, кавычек и пояснений.';
    }

    protected function userBlocks(array $input): array
    {
        return [$this->postBlock($input), PlatformHints::block($input['platform'] ?? []), $this->textBlock($input)];
    }

    public function maxTokens(): int
    {
        return 400;
    }

    public function postProcess(string $raw, array $input): string
    {
        return implode("\n", self::lines($this->clean($raw), 8));
    }

    /** @return list<string> */
    public static function lines(string $text, int $limit): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim(preg_replace('/^\s*(?:[-•–]|\d+[.)])\s*/u', '', $line) ?? $line, " \t\"«»");
            if ($line !== '' && ! in_array($line, $out, true)) {
                $out[] = mb_substr($line, 0, 200);
            }
        }

        return array_slice($out, 0, $limit);
    }
}
