<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once ACE_PLUGIN_DIR . 'providers/class-ace-provider-base.php';

final class ACE_Provider_Local extends ACE_Provider_Base
{
    public function chat(array $payload): array
    {
        $endpoint = esc_url_raw((string) ($this->settings['local_endpoint'] ?? ''));
        if ($endpoint === '') {
            return ['error' => __('Local endpoint is missing.', 'ai-chat-editor')];
        }

        $result = $this->request($endpoint, [
            'system'  => $this->build_system_prompt($payload),
            'prompt'  => $payload['prompt'],
            'context' => $payload['context'],
        ], ['Content-Type' => 'application/json']);

        if (! $result['ok']) {
            return ['error' => $result['error']];
        }

        if (isset($result['data']['action'])) {
            return [
                'action'  => sanitize_key($result['data']['action']),
                'post_id' => absint($result['data']['post_id'] ?? 0),
                'content' => wp_kses_post((string) ($result['data']['content'] ?? '')),
                'message' => sanitize_text_field($result['data']['message'] ?? ''),
            ];
        }

        return $this->coerce_result((string) ($result['data']['output'] ?? ''), (int) ($payload['context']['post_id'] ?? 0));
    }
}
