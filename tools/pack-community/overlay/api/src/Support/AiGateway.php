<?php

namespace SitebillApps\Rumantic\ContentManager\Support;

use Sitebill\AppSdk\Host;

/**
 * Тот же вызов `runFeature`, что у ИИ-шлюза ядра, но поверх Host API: ключ, лимиты и журнал — у хоста,
 * приложение только собирает промпт и чистит ответ. Тенант определяет хост (текущий запрос или Host::site()->runAs).
 */
class AiGateway
{
    /** @return array{available: bool, source: ?string} */
    public function status(mixed $tenant = null): array
    {
        return Host::ai()->status();
    }

    public function runFeature(mixed $tenant, mixed $user, AiFeature $feature, array $input): string
    {
        $text = Host::ai()->complete(ContentApp::ID, $feature->key(), $feature->buildMessages($input, $tenant), [
            'max_tokens' => $feature->maxTokens(),
            'temperature' => $feature->temperature(),
            'instruction' => $feature->logInstruction($input),
        ]);

        return $feature->postProcess($text, $input);
    }
}
