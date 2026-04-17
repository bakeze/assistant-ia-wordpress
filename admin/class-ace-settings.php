<?php

if (! defined('ABSPATH')) {
    exit;
}

final class ACE_Settings
{
    private static ?ACE_Settings $instance = null;
    private const OPTION_KEY = 'ace_settings';

    public static function instance(): ACE_Settings
    {
        if (! self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register']);
    }

    public function add_menu(): void
    {
        add_options_page(
            __('AI Chat Editor', 'ai-chat-editor'),
            __('AI Chat Editor', 'ai-chat-editor'),
            'manage_options',
            'ai-chat-editor',
            [$this, 'render_page']
        );
    }

    public function register(): void
    {
        register_setting('ace_settings_group', self::OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
            'default'           => $this->defaults(),
        ]);

        add_settings_section('ace_provider', __('Provider Configuration', 'ai-chat-editor'), '__return_false', 'ai-chat-editor');

        foreach ($this->fields() as $id => $field) {
            add_settings_field(
                $id,
                $field['label'],
                [$this, 'render_field'],
                'ai-chat-editor',
                'ace_provider',
                [
                    'id'   => $id,
                    'type' => $field['type'],
                ]
            );
        }
    }

    public function defaults(): array
    {
        return [
            'provider'          => 'openai',
            'openai_api_key'    => '',
            'openai_model'      => 'gpt-4.1-mini',
            'openrouter_api_key'=> '',
            'openrouter_model'  => 'openai/gpt-4.1-mini',
            'anthropic_api_key' => '',
            'anthropic_model'   => 'claude-3-5-sonnet-latest',
            'gemini_api_key'    => '',
            'gemini_model'      => 'gemini-1.5-pro',
            'local_endpoint'    => '',
            'enable_frontend'   => 0,
        ];
    }

    public function get(): array
    {
        return wp_parse_args(get_option(self::OPTION_KEY, []), $this->defaults());
    }

    public function sanitize(array $input): array
    {
        $defaults = $this->defaults();

        return [
            'provider'          => in_array($input['provider'] ?? '', ['openai', 'openrouter', 'anthropic', 'gemini', 'local'], true) ? $input['provider'] : $defaults['provider'],
            'openai_api_key'    => sanitize_text_field($input['openai_api_key'] ?? ''),
            'openai_model'      => sanitize_text_field($input['openai_model'] ?? $defaults['openai_model']),
            'openrouter_api_key'=> sanitize_text_field($input['openrouter_api_key'] ?? ''),
            'openrouter_model'  => sanitize_text_field($input['openrouter_model'] ?? $defaults['openrouter_model']),
            'anthropic_api_key' => sanitize_text_field($input['anthropic_api_key'] ?? ''),
            'anthropic_model'   => sanitize_text_field($input['anthropic_model'] ?? $defaults['anthropic_model']),
            'gemini_api_key'    => sanitize_text_field($input['gemini_api_key'] ?? ''),
            'gemini_model'      => sanitize_text_field($input['gemini_model'] ?? $defaults['gemini_model']),
            'local_endpoint'    => esc_url_raw($input['local_endpoint'] ?? ''),
            'enable_frontend'   => empty($input['enable_frontend']) ? 0 : 1,
        ];
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI Chat Editor Settings', 'ai-chat-editor'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('ace_settings_group');
                do_settings_sections('ai-chat-editor');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_field(array $args): void
    {
        $settings = $this->get();
        $id       = $args['id'];
        $type     = $args['type'];
        $value    = $settings[$id] ?? '';

        if ($id === 'provider') {
            ?>
            <select name="<?php echo esc_attr(self::OPTION_KEY . '[' . $id . ']'); ?>">
                <option value="openai" <?php selected($value, 'openai'); ?>>OpenAI</option>
                <option value="openrouter" <?php selected($value, 'openrouter'); ?>>OpenRouter</option>
                <option value="anthropic" <?php selected($value, 'anthropic'); ?>>Anthropic Claude</option>
                <option value="gemini" <?php selected($value, 'gemini'); ?>>Google Gemini</option>
                <option value="local" <?php selected($value, 'local'); ?>>Local Endpoint</option>
            </select>
            <?php
            return;
        }

        if ($type === 'checkbox') {
            ?>
            <label>
                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY . '[' . $id . ']'); ?>" value="1" <?php checked((int) $value, 1); ?>>
                <?php esc_html_e('Enable on frontend for logged in users.', 'ai-chat-editor'); ?>
            </label>
            <?php
            return;
        }

        ?>
        <input
            class="regular-text"
            type="<?php echo $type === 'password' ? 'password' : 'text'; ?>"
            name="<?php echo esc_attr(self::OPTION_KEY . '[' . $id . ']'); ?>"
            value="<?php echo esc_attr((string) $value); ?>"
            autocomplete="off"
        >
        <?php
    }

    private function fields(): array
    {
        return [
            'provider'          => ['label' => __('Provider', 'ai-chat-editor'), 'type' => 'select'],
            'openai_api_key'    => ['label' => __('OpenAI API Key', 'ai-chat-editor'), 'type' => 'password'],
            'openai_model'      => ['label' => __('OpenAI Model', 'ai-chat-editor'), 'type' => 'text'],
            'openrouter_api_key'=> ['label' => __('OpenRouter API Key', 'ai-chat-editor'), 'type' => 'password'],
            'openrouter_model'  => ['label' => __('OpenRouter Model', 'ai-chat-editor'), 'type' => 'text'],
            'anthropic_api_key' => ['label' => __('Anthropic API Key', 'ai-chat-editor'), 'type' => 'password'],
            'anthropic_model'   => ['label' => __('Anthropic Model', 'ai-chat-editor'), 'type' => 'text'],
            'gemini_api_key'    => ['label' => __('Gemini API Key', 'ai-chat-editor'), 'type' => 'password'],
            'gemini_model'      => ['label' => __('Gemini Model', 'ai-chat-editor'), 'type' => 'text'],
            'local_endpoint'    => ['label' => __('Local API Endpoint', 'ai-chat-editor'), 'type' => 'text'],
            'enable_frontend'   => ['label' => __('Frontend Chat', 'ai-chat-editor'), 'type' => 'checkbox'],
        ];
    }
}
