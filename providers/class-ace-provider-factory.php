<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once ACE_PLUGIN_DIR . 'providers/class-ace-provider-interface.php';
require_once ACE_PLUGIN_DIR . 'providers/class-ace-provider-openai.php';
require_once ACE_PLUGIN_DIR . 'providers/class-ace-provider-openrouter.php';
require_once ACE_PLUGIN_DIR . 'providers/class-ace-provider-anthropic.php';
require_once ACE_PLUGIN_DIR . 'providers/class-ace-provider-gemini.php';
require_once ACE_PLUGIN_DIR . 'providers/class-ace-provider-local.php';

final class ACE_Provider_Factory
{
    public static function make(string $provider, array $settings): ACE_Provider_Interface
    {
        return match ($provider) {
            'anthropic' => new ACE_Provider_Anthropic($settings),
            'gemini'    => new ACE_Provider_Gemini($settings),
            'openrouter' => new ACE_Provider_OpenRouter($settings),
            'local'     => new ACE_Provider_Local($settings),
            default     => new ACE_Provider_OpenAI($settings),
        };
    }
}
