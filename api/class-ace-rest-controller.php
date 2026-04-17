<?php

if (! defined('ABSPATH')) {
    exit;
}

final class ACE_REST_Controller
{
    private static ?ACE_REST_Controller $instance = null;
    private const NAMESPACE = 'ace/v1';

    public static function instance(): ACE_REST_Controller
    {
        if (! self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/chat', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'chat'],
            'permission_callback' => [$this, 'can_chat'],
            'args'                => [
                'message' => [
                    'required' => true,
                    'type'     => 'string',
                ],
                'context' => [
                    'required' => false,
                    'type'     => 'object',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/apply/(?P<proposal_id>[a-zA-Z0-9_-]+)', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'apply'],
            'permission_callback' => [$this, 'can_edit'],
        ]);

        register_rest_route(self::NAMESPACE, '/history', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'history'],
            'permission_callback' => [$this, 'can_chat'],
        ]);
    }

    public function can_chat(WP_REST_Request $request): bool
    {
        $nonce_valid = wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest');
        return is_user_logged_in() && (bool) $nonce_valid;
    }

    public function can_edit(WP_REST_Request $request): bool
    {
        if (! $this->can_chat($request)) {
            return false;
        }

        return current_user_can('manage_options');
    }

    public function history(): WP_REST_Response
    {
        $history = get_user_meta(get_current_user_id(), 'ace_chat_history', true);
        return new WP_REST_Response(['history' => is_array($history) ? $history : []]);
    }

    public function chat(WP_REST_Request $request): WP_REST_Response
    {
        $settings = ACE_Settings::instance()->get();

        $message = sanitize_textarea_field((string) $request->get_param('message'));
        if ($message === '') {
            return new WP_REST_Response(['error' => __('Message cannot be empty.', 'ai-chat-editor')], 422);
        }

        $context = $this->resolve_context($request->get_param('context'));
        $prompt  = $this->build_prompt($message, $context);

        $provider = ACE_Provider_Factory::make($settings['provider'], $settings);
        $result   = $provider->chat([
            'prompt'  => $prompt,
            'context' => $context,
        ]);

        if (isset($result['error'])) {
            return new WP_REST_Response(['error' => $result['error']], 500);
        }

        if ($result['action'] === 'update_post' && empty($result['post_id'])) {
            $result['post_id'] = (int) $context['post_id'];
        }

        $proposal_id = wp_generate_uuid4();
        $before      = $context['content'];
        $after       = $result['content'];

        set_transient(
            $this->proposal_key($proposal_id),
            [
                'user_id' => get_current_user_id(),
                'action'  => $result['action'],
                'post_id' => (int) $result['post_id'],
                'before'  => $before,
                'after'   => $after,
            ],
            15 * MINUTE_IN_SECONDS
        );

        $history = get_user_meta(get_current_user_id(), 'ace_chat_history', true);
        if (! is_array($history)) {
            $history = [];
        }

        $history[] = [
            'time'      => current_time('mysql'),
            'prompt'    => $message,
            'action'    => $result['action'],
            'post_id'   => (int) $result['post_id'],
            'assistant' => $result['message'] ?: __('Draft ready for review.', 'ai-chat-editor'),
        ];

        update_user_meta(get_current_user_id(), 'ace_chat_history', array_slice($history, -20));

        return new WP_REST_Response([
            'action'      => $result['action'],
            'post_id'     => (int) $result['post_id'],
            'assistant'   => $result['message'] ?: __('I created a suggested update. Review diff before applying.', 'ai-chat-editor'),
            'proposal_id' => $proposal_id,
            'before'      => $before,
            'after'       => $after,
        ]);
    }

    public function apply(WP_REST_Request $request): WP_REST_Response
    {
        $proposal_id = sanitize_key((string) $request->get_param('proposal_id'));
        $proposal    = get_transient($this->proposal_key($proposal_id));

        if (! is_array($proposal) || (int) $proposal['user_id'] !== get_current_user_id()) {
            return new WP_REST_Response(['error' => __('Proposal expired or not found.', 'ai-chat-editor')], 404);
        }

        $post_id = (int) $proposal['post_id'];
        if (($proposal['action'] ?? 'suggest_only') !== 'update_post') {
            return new WP_REST_Response(['error' => __('Only update_post proposals can be applied.', 'ai-chat-editor')], 400);
        }

        if ($post_id < 1 || ! current_user_can('edit_post', $post_id)) {
            return new WP_REST_Response(['error' => __('You are not allowed to edit this post.', 'ai-chat-editor')], 403);
        }

        $post_type = get_post_type($post_id);
        if (! in_array($post_type, ['post', 'page', 'lp_course', 'lp_lesson', 'lp_quiz'], true)) {
            return new WP_REST_Response(['error' => __('Post type is not supported.', 'ai-chat-editor')], 400);
        }

        $updated = wp_update_post([
            'ID'           => $post_id,
            'post_content' => wp_kses_post((string) $proposal['after']),
        ], true);

        if (is_wp_error($updated)) {
            return new WP_REST_Response(['error' => $updated->get_error_message()], 500);
        }

        delete_transient($this->proposal_key($proposal_id));

        return new WP_REST_Response([
            'success' => true,
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'message' => __('Changes applied successfully.', 'ai-chat-editor'),
        ]);
    }

    private function resolve_context($raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $post_id = absint($raw['post_id'] ?? 0);
        if ($post_id < 1 && is_admin()) {
            $post_id = absint($_GET['post'] ?? 0);
        }

        $post = $post_id ? get_post($post_id) : null;

        return [
            'post_id'    => $post ? (int) $post->ID : 0,
            'post_type'  => $post ? $post->post_type : 'unknown',
            'title'      => $post ? $post->post_title : '',
            'content'    => $post ? (string) $post->post_content : '',
            'permalink'  => $post ? get_permalink($post) : '',
        ];
    }

    private function build_prompt(string $message, array $context): string
    {
        $chunks = $this->chunk_content($context['content']);
        $joined = implode("\n\n---\n\n", $chunks);

        return sprintf(
            "User request:\n%s\n\nCurrent post context:\nID: %d\nType: %s\nTitle: %s\nPermalink: %s\n\nContent:\n%s",
            $message,
            (int) $context['post_id'],
            $context['post_type'],
            $context['title'],
            $context['permalink'],
            $joined
        );
    }

    private function chunk_content(string $content, int $limit = 6000): array
    {
        if (strlen($content) <= $limit) {
            return [$content];
        }

        $chunks = [];
        $offset = 0;
        $length = strlen($content);

        while ($offset < $length) {
            $chunks[] = substr($content, $offset, $limit);
            $offset += $limit;
        }

        return array_slice($chunks, 0, 4);
    }

    private function proposal_key(string $proposal_id): string
    {
        return 'ace_proposal_' . $proposal_id;
    }
}
