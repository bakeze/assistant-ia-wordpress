# AI Chat Editor (WordPress Plugin)

AI Chat Editor embeds a ChatGPT-like assistant into WordPress admin and optionally frontend. It can inspect the current post/page/LearnPress content, propose changes, show a diff preview, and apply updates after explicit confirmation.

## Features

- Floating chat bubble + full chat window in admin.
- Optional frontend chat for logged-in users.
- Context-aware editing (`post`, `page`, `lp_course`, `lp_lesson`, `lp_quiz`).
- Multi-provider support:
  - OpenAI
  - OpenRouter
  - Anthropic (Claude)
  - Google Gemini
  - Local HTTP endpoint
- Structured action JSON responses (`update_post` or `suggest_only`).
- Preview and apply workflow with diff view.
- REST API + nonce verification.
- User message history.
- Content chunking for long posts.

## Installation

1. Copy the `ai-chat-editor` plugin folder into `wp-content/plugins/`.
2. Activate **AI Chat Editor** from WordPress Admin → Plugins.
3. Go to **Settings → AI Chat Editor**.
4. Choose an AI provider and add credentials.
5. (Optional) Enable frontend chat.
6. Open any post/page/LearnPress item and use the chat bubble.

## Provider payload examples

### OpenAI request shape

```json
{
  "model": "gpt-4.1-mini",
  "messages": [
    {"role": "system", "content": "...strict JSON instructions..."},
    {"role": "user", "content": "...post context and user instruction..."}
  ]
}
```

### Anthropic request shape

```json
{
  "model": "claude-3-5-sonnet-latest",
  "system": "...strict JSON instructions...",
  "messages": [
    {"role": "user", "content": "...prompt..."}
  ]
}
```

### Gemini request shape

```json
{
  "system_instruction": {"parts": [{"text": "...strict JSON instructions..."}]},
  "contents": [
    {"role": "user", "parts": [{"text": "...prompt..."}]}
  ]
}
```

### Expected model output

```json
{
  "action": "update_post",
  "post_id": 123,
  "content": "<p>Improved HTML content</p>",
  "message": "Updated for clarity and SEO."
}
```

or

```json
{
  "action": "suggest_only",
  "post_id": 123,
  "content": "<p>Suggested revision</p>",
  "message": "Suggestion ready for review."
}
```

## REST Endpoints

- `POST /wp-json/ace/v1/chat` - submit prompt and receive preview proposal.
- `POST /wp-json/ace/v1/apply/{proposal_id}` - apply approved change.
- `GET /wp-json/ace/v1/history` - fetch user chat history.

All endpoints require authenticated user + `X-WP-Nonce`.

## Notes

- Applying edits is admin-only (`manage_options`) and still enforces per-post capability checks.
- AI-generated HTML is sanitized with `wp_kses_post()` before save.
- Proposal state is stored as expiring transient (15 minutes).
