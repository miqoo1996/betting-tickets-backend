<?php

namespace App\Services\AiChatBots;

use Illuminate\Support\Facades\Http;

class ChatGptApiService implements AiChatBotInterface
{
    private ?string $apiKey;
    protected string $baseUrl = 'https://api.openai.com/v1/chat/completions';
    protected string $defaultModel = 'gpt-4.1-mini';

    public function __construct()
    {
        $this->apiKey = config('ai-bots.chat-gpt.apiKey');
    }

    public function prompt(string $prompt, ?string $model = null): array
    {
        if (!$this->apiKey) {
            return [
                'success' => false,
                'status' => 0,
                'error' => 'OpenAI API key not configured'
            ];
        }

        $targetModel = $model ?: $this->defaultModel;

        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->post($this->baseUrl, [
                'model' =>   $targetModel,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ]
                ],
                'max_tokens' => 1000,
            ]);

        if ($response->failed()) {
            return [
                'success' => false,
                'status' => $response->status(),
                'error' => $response->json(),
            ];
        }

        return [
            'success' => true,
            'status' => $response->status(),
            'response' => $response->json('choices.0.message.content'),
        ];
    }
}
