<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once ACE_PLUGIN_DIR . 'providers/class-ace-provider-base.php';

final class ACE_Provider_OpenRouter extends ACE_Provider_Base
{
    public function chat(array $payload): array
    {
        $key = trim((string) ($this->settings['openrouter_api_key'] ?? ''));
        if ($key === '') {
            return ['error' => __('OpenRouter API key is missing.', 'ai-chat-editor')];
        }

        $endpoint = 'https://openrouter.ai/api/v1/chat/completions';
        $model    = $this->settings['openrouter_model'] ?: 'openai/gpt-4.1-mini';
        $result   = $this->request(
            $endpoint,
            [
                'model'       => $model,
                'temperature' => 0.2,
                'messages'    => [
                    ['role' => 'system', 'content' => $this->build_system_prompt($payload)],
                    ['role' => 'user', 'content' => $payload['prompt']],
                ],
            ],
            [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ]
        );

        if (! $result['ok']) {
            return ['error' => $result['error']];
        }

        $content = (string) ($result['data']['choices'][0]['message']['content'] ?? '');
        return $this->coerce_result($content, (int) ($payload['context']['post_id'] ?? 0));
    }
}
