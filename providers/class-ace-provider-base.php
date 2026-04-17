<?php

if (! defined('ABSPATH')) {
    exit;
}

abstract class ACE_Provider_Base implements ACE_Provider_Interface
{
    protected array $settings;

    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    protected function request(string $url, array $body, array $headers = []): array
    {
        $response = wp_remote_post($url, [
            'timeout' => 60,
            'headers' => $headers,
            'body'    => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return [
                'ok'    => false,
                'error' => $response->get_error_message(),
            ];
        }

        $status = wp_remote_retrieve_response_code($response);
        $raw    = wp_remote_retrieve_body($response);
        $json   = json_decode($raw, true);

        if ($status < 200 || $status >= 300) {
            return [
                'ok'    => false,
                'error' => is_array($json) ? wp_json_encode($json) : $raw,
            ];
        }

        return [
            'ok'   => true,
            'data' => $json,
        ];
    }

    protected function build_system_prompt(array $payload): string
    {
        return "You are an expert WordPress editor assistant.\n"
            . "Always return strict JSON only (no markdown).\n"
            . "Allowed actions: update_post, suggest_only.\n"
            . "Schema: {\"action\":\"update_post|suggest_only\",\"post_id\":number,\"content\":\"<html>\",\"message\":\"optional note\"}.\n"
            . "Never execute destructive actions.\n"
            . "Context post type: " . sanitize_text_field($payload['context']['post_type'] ?? 'unknown');
    }

    protected function coerce_result(string $output, int $fallback_post_id = 0): array
    {
        $json = json_decode(trim($output), true);

        if (! is_array($json)) {
            return [
                'action'  => 'suggest_only',
                'post_id' => $fallback_post_id,
                'content' => wp_kses_post($output),
                'message' => __('Model returned non-JSON output; converted to suggestion.', 'ai-chat-editor'),
            ];
        }

        $action = $json['action'] ?? 'suggest_only';

        if (! in_array($action, ['update_post', 'suggest_only'], true)) {
            $action = 'suggest_only';
        }

        return [
            'action'  => $action,
            'post_id' => absint($json['post_id'] ?? $fallback_post_id),
            'content' => wp_kses_post((string) ($json['content'] ?? '')),
            'message' => sanitize_text_field($json['message'] ?? ''),
        ];
    }
}
