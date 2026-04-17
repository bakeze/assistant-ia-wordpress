<?php

if (! defined('ABSPATH')) {
    exit;
}

final class ACE_Chat_UI
{
    private static ?ACE_Chat_UI $instance = null;

    public static function instance(): ACE_Chat_UI
    {
        if (! self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend']);
        add_action('admin_footer', [$this, 'render_mount']);
        add_action('wp_footer', [$this, 'render_mount_frontend']);
        add_shortcode('ai_chat_editor', [$this, 'shortcode']);
    }

    public function enqueue(): void
    {
        if (! is_user_logged_in()) {
            return;
        }
        $this->register_assets();
        wp_enqueue_style('ace-chat-ui');
        wp_enqueue_script('ace-chat-ui');
        wp_localize_script('ace-chat-ui', 'ACE_CONFIG', $this->config());
    }

    public function enqueue_frontend(): void
    {
        if (! is_user_logged_in()) {
            return;
        }

        $settings = ACE_Settings::instance()->get();
        if (! (int) $settings['enable_frontend']) {
            return;
        }

        $this->register_assets();
        wp_enqueue_style('ace-chat-ui');
        wp_enqueue_script('ace-chat-ui');
        wp_localize_script('ace-chat-ui', 'ACE_CONFIG', $this->config());
    }

    public function render_mount(): void
    {
        if (! is_user_logged_in()) {
            return;
        }
        echo '<div id="ace-chat-root" data-scope="admin"></div>';
    }

    public function render_mount_frontend(): void
    {
        $settings = ACE_Settings::instance()->get();
        if (! is_user_logged_in() || ! (int) $settings['enable_frontend']) {
            return;
        }

        echo '<div id="ace-chat-root" data-scope="frontend"></div>';
    }

    public function shortcode(): string
    {
        if (! is_user_logged_in()) {
            return '';
        }

        return '<div id="ace-chat-root" data-scope="shortcode"></div>';
    }

    private function register_assets(): void
    {
        wp_register_style('ace-chat-ui', ACE_PLUGIN_URL . 'chat-ui/assets/chat-ui.css', [], ACE_VERSION);
        wp_register_script('ace-chat-ui', ACE_PLUGIN_URL . 'chat-ui/assets/chat-ui.js', [], ACE_VERSION, true);
    }

    private function config(): array
    {
        global $post;

        $post_id   = $post instanceof WP_Post ? $post->ID : absint($_GET['post'] ?? 0);
        $post_type = $post_id ? get_post_type($post_id) : '';

        return [
            'restUrl'   => esc_url_raw(rest_url('ace/v1')),
            'nonce'     => wp_create_nonce('wp_rest'),
            'context'   => [
                'post_id'   => $post_id,
                'post_type' => $post_type,
            ],
            'i18n'      => [
                'placeholder' => __('Ask AI to edit this content…', 'ai-chat-editor'),
                'typing'      => __('AI is thinking…', 'ai-chat-editor'),
                'apply'       => __('Apply', 'ai-chat-editor'),
                'cancel'      => __('Cancel', 'ai-chat-editor'),
            ],
        ];
    }
}
