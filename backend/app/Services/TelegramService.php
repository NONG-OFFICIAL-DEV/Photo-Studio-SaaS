<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around Telegram's Bot API (https://core.telegram.org/bots/api).
 * Every tenant connects their OWN bot (token pasted in Settings), so every
 * method here takes the token as a plain argument rather than reading it
 * from a single app-wide config value.
 *
 * sendDocument() forwards straight through to Telegram — $contents may be a
 * stream resource (fopen() on an uploaded file's temp path) or a raw string
 * (an in-memory generated PDF) — this app never writes the file to its own
 * storage disk at any point.
 */
class TelegramService
{
    protected function baseUrl(string $token): string
    {
        return "https://api.telegram.org/bot{$token}";
    }

    public function getMe(string $token): array
    {
        return Http::timeout(15)->get("{$this->baseUrl($token)}/getMe")->json() ?? ['ok' => false];
    }

    public function setWebhook(string $token, string $url, string $secretToken): array
    {
        return Http::timeout(15)->asForm()->post("{$this->baseUrl($token)}/setWebhook", [
            'url' => $url,
            'secret_token' => $secretToken,
        ])->json() ?? ['ok' => false];
    }

    public function deleteWebhook(string $token): array
    {
        return Http::timeout(15)->post("{$this->baseUrl($token)}/deleteWebhook")->json() ?? ['ok' => false];
    }

    public function sendMessage(string $token, string $chatId, string $text): array
    {
        return Http::timeout(15)->asForm()->post("{$this->baseUrl($token)}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
        ])->json() ?? ['ok' => false];
    }

    public function sendDocument(string $token, string $chatId, mixed $contents, string $filename, ?string $caption = null): array
    {
        return Http::timeout(60)
            ->attach('document', $contents, $filename)
            ->post("{$this->baseUrl($token)}/sendDocument", array_filter([
                'chat_id' => $chatId,
                'caption' => $caption,
            ]))
            ->json() ?? ['ok' => false];
    }
}
