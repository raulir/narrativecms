# xAI module

Generic **AI provider** for the CMS via module **`provides`** (`service: ai` → panel `xai/ai`).

## Setup

1. Enable module **`xai`** in Site settings → Modules (and save).
2. **Site settings → AI** (end of page): **AI provider** = xAI Grok; **Ask confirmation**; **Only missing texts** (shared for any AI provider). Leave provider empty to disable AI.
3. **Tools → xAI → Settings**: API key, model (default **`grok-4.3`** — good for translation; use **`grok-4.5`** for hard coding/agents), base URL, **Site / style context** (this provider’s profile / tone).

Shared AI options live under **Site settings → AI**, not on the provider.

## API

- Base: `https://api.x.ai/v1` (OpenAI-compatible chat completions).
- Key from [console.x.ai](https://console.x.ai).
- HTTP via PHP streams (`stream_context_create` + `file_get_contents`) — not curl.

## Provider contract

```php
run_panel_method('xai/ai', 'ai_request', [
  'task' => 'translate' | 'chat' | …,
  'payload' => [ … ],
]);
// → [ 'ok' => 1|0, 'error' => '', 'result' => […] ]
```

| Task | Payload | Result |
|------|---------|--------|
| `translate` | `source_language`, `target_language`, `items[]`, optional `context` | `suggestions` map + `items[]` |
| `chat` | `messages[]` | `content` string |
| `describe_image` | (future) | |

Translation batch is used by **admin panel translations** (`cms_translation`). Suggestions are not saved until the editor copies them into the edit column and clicks Save.

## Files

| Path | Role |
|------|------|
| `models/xai_model.php` | HTTP client |
| `panels/ai.php` | `ai_request` task switch |
| `definitions/xai.json` | Settings |
