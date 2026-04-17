<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once ACE_PLUGIN_DIR . 'providers/class-ace-provider-base.php';

final class ACE_Provider_Gemini extends ACE_Provider_Base
{
    public function chat(array $payload): array
    {
        $key = trim((string) ($this->settings['gemini_api_key'] ?? ''));
        if ($key === '') {
            return ['error' => __('Gemini API key is missing.', 'ai-chat-editor')];
        }

        $model    = $this->settings['gemini_model'] ?: 'gemini-1.5-pro';
        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            rawurlencode($model),
            rawurlencode($key)
        );

        $result = $this->request($endpoint, [
            'system_instruction' => [
                'parts' => [
                    ['text' => $this->build_system_prompt($payload)],
                ],
            ],
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $payload['prompt']],
                    ],
                ],
            ],
        ], ['Content-Type' => 'application/json']);

        if (! $result['ok']) {
            return ['error' => $result['error']];
        }

        $content = (string) ($result['data']['candidates'][0]['content']['parts'][0]['text'] ?? '');
        return $this->coerce_result($content, (int) ($payload['context']['post_id'] ?? 0));
    }
}
