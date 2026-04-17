<?php
/**
 * Plugin Name: AI Chat Editor
 * Description: Real-time AI chat interface for editing WordPress and LearnPress content with validation and preview.
 * Version: 1.0.0
 * Author: AI Chat Editor Team
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Text Domain: ai-chat-editor
 */

if (! defined('ABSPATH')) {
    exit;
}

define('ACE_VERSION', '1.0.0');
define('ACE_PLUGIN_FILE', __FILE__);
define('ACE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ACE_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once ACE_PLUGIN_DIR . 'admin/class-ace-settings.php';
require_once ACE_PLUGIN_DIR . 'chat-ui/class-ace-chat-ui.php';
require_once ACE_PLUGIN_DIR . 'api/class-ace-rest-controller.php';
require_once ACE_PLUGIN_DIR . 'providers/class-ace-provider-factory.php';

final class ACE_Plugin
{
    private static ?ACE_Plugin $instance = null;

    public static function instance(): ACE_Plugin
    {
        if (! self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'boot']);
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain('ai-chat-editor', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function boot(): void
    {
        ACE_Settings::instance();
        ACE_Chat_UI::instance();
        ACE_REST_Controller::instance();
    }
}

ACE_Plugin::instance();
