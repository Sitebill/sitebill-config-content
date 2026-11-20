<?php

namespace SitebillApps\Rumantic\ContentManager\Support;

/** Фича ИИ: собирает промпт из входных данных и чистит ответ (то же, что `App\Ai\Features\AiFeature` в ядре). */
interface AiFeature
{
    public function key(): string;

    /** @return array<int, array{role: string, content: string}> */
    public function buildMessages(array $input, mixed $tenant): array;

    public function postProcess(string $raw, array $input): string;

    public function logInstruction(array $input): ?string;

    public function maxTokens(): int;

    public function temperature(): ?float;
}
