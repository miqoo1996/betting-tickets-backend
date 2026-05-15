<?php

namespace App\Services\AiChatBots;

interface AiChatBotInterface
{
    public function prompt(string $prompt, ?string $model = null): array;
}
