<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once ACE_PLUGIN_DIR . 'providers/class-ace-provider-base.php';

final class ACE_Provider_Anthropic extends ACE_Provider_Base
{
    public function chat(array $payload): array
    {
        $key = trim((string) ($this->settings['anthropic_api_key'] ?? ''));
        if ($key === '') {
            return ['error' => __('Anthropic API key is missing.', 'ai-chat-editor')];
        }

        $model  = $this->settings['anthropic_model'] ?: 'claude-3-5-sonnet-latest';
        $result = $this->request(
            'https://api.anthropic.com/v1/messages',
            [
                'model'      => $model,
                'max_tokens' => 4096,
                'system'     => $this->build_system_prompt($payload),
                'messages'   => [
                    [
                        'role'    => 'user',
                        'content' => $payload['prompt'],
                    ],
                ],
            ],
            [
                'x-api-key'         => $key,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ]
        );

        if (! $result['ok']) {
            return ['error' => $result['error']];
        }

        $parts = $result['data']['content'] ?? [];
        $text  = '';
        foreach ($parts as $part) {
            if (($part['type'] ?? '') === 'text') {
                $text .= $part['text'] ?? '';
            }
        }

        return $this->coerce_result($text, (int) ($payload['context']['post_id'] ?? 0));
    }
}
