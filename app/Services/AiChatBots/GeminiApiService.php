<?php

namespace App\Services\AiChatBots;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GeminiApiService implements AiChatBotInterface
{
    protected ?string $apiKey;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
    protected string $defaultModel = 'gemini-3-flash-preview';

    public function __construct()
    {
        $this->apiKey = config('ai-bots.gemini.apiKey');
    }

    public function prompt(string $prompt, ?string $model = null): array
    {
        if (!$this->apiKey) {
            return [
                'success' => false,
                'status' => 0,
                'error' => 'Gemini API key not configured'
            ];
        }

        $targetModel = $model ?? $this->defaultModel;

        // The endpoint URL structure required by Google
        $url = "{$this->baseUrl}{$targetModel}:generateContent?key={$this->apiKey}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($url, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => trim($prompt)]
                    ]
                ]
            ]
        ]);

        if ($response->failed()) {
            return [
                'success' => false,
                'status' => $response->status(),
                'error' => 'Gemini API Error: ' . $response->body(),
            ];
        }

        return [
            'success' => true,
            'status' => $response->status(),
            'response' => $this->parseResponse($response),
        ];
    }

    protected function parseResponse(Response $response): string
    {
        $data = $response->json();

        // Safely dig into the Google API JSON structure
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'No response generated.';
    }
}
