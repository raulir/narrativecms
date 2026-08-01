# Email transport (`cms_email_model`)

All outbound email uses [`cms_email_model`](../models/cms_email_model.php).

```php
$this->load->model('cms/cms_email_model');
$this->cms_email_model->send_mail($to, $subject, $body, $params);
```

Returns `bool`. Failures are logged with prefix `cms_email_model send_mail:` (and queue processing logs with `process_mail_queue:`).

## Async by default

By default **`send_mail()` enqueues** the message as a JSON file under `cache/email_queue/` and returns immediately (no SMTP wait). The cron panel **`cms/cms_email_queue`** processes the queue.

Pass **`$params['send_now'] => true`** (or `1`) for **synchronous** delivery on the request path. Use this for time-sensitive user flows:

| Flow | Where | Why immediate |
|------|--------|----------------|
| Password reminder | `user/reminder` | User is waiting for the link |
| Password updated | `user_model::send_password_updated_email` | Security notice should go out now |
| Email verification | `user_model::send_email_verification` | Confirm link should arrive immediately |

Everything else (forms, webhooks, welcome mail, log rotate, Stripe notifications, …) stays async so buttons and webhooks stay fast.

## Queue + cron

| Piece | Path / name |
|-------|-------------|
| Queue dir | `cache/email_queue/*.json` |
| Cron panel | `cms/cms_email_queue` (`flag: cron`) |
| Runner | CMS repeating tasks (`cms/cms_cron`) — add item, e.g. every **5 minutes** |
| Process API | `cms_email_model::process_mail_queue()` |

### Queue file shape

```json
{
  "id": "20260326120000_…",
  "to": "user@example.com",
  "subject": "…",
  "body": "…",
  "params": { "auto_submitted": 1, "is_html": 0 },
  "created": 1710000000,
  "attempts": 0,
  "last_try": 0,
  "last_error": ""
}
```

### Processing rules

1. Up to **`email_queue_limit`** (default **50**) eligible messages per cron run (oldest files first).
2. First attempt: no wait (`attempts == 0`).
3. After a failure: wait **`2 × n²` minutes** where **n = attempts so far** before retrying  
   (n=1 → 2 min, n=2 → 8 min, n=3 → 18 min, n=4 → 32 min, …).
4. After **`email_queue_max_attempts`** (default **5**) failures: log and delete the file (no silent infinite retries).
5. Successful send: delete the queue file.
6. Worker always delivers via SMTP/`mail()`; it never re-enqueues.

Register the cron task in admin: **CMS repeating tasks** → add **CMS email queue** → e.g. Minute × 5 (or rely on visit-triggered cron + minimum 5-minute cadence).

## Transport

1. **SMTP** when CMS settings `smtp_server` is set (PHPMailer, STARTTLS).
2. **PHP `mail()`** when SMTP is empty (matches CMS settings help: *"If empty, php mail is used"*).

## Configuration (CMS settings)

| Field | Use |
|-------|-----|
| `email` | From address |
| `from_name` | From display name |
| `reply_email` / `reply_name` | Default Reply-To |
| `admin_email` | Technical notifications (e.g. error log rotation) |
| `email_queue_limit` | Max messages processed per cron run (default 50) |
| `email_queue_max_attempts` | Give up after this many failed sends (default 5) |
| `smtp_server`, `smtp_port`, `smtp_username`, `smtp_password` | SMTP |
| `smtp_debug` | Write SMTP debug log to `cache/smtp_debug_*` |

## `$params`

| Key | Description |
|-----|-------------|
| `send_now` | If truthy: send immediately (skip queue) |
| `is_html` | HTML body |
| `alt_body` | Plain alternative; also used as `mail()` body when `is_html` and SMTP unavailable |
| `auto_submitted` | `Auto-Submitted: auto-generated` header |
| `reply_to` | `['email' =>, 'name' =>]` override |
| `from_email` / `from_name` | Optional overrides |
| `mail_from_email_only` | PHP `mail()` only: `From: email` without name (form admin notifications) |
| `x_mailer` | PHPMailer `XMailer` value |
| `smtp_debug` | Force SMTP debug for this send |

## Callers

| Module | Use | Default |
|--------|-----|---------|
| `user_model` | `send_email_verification()`, `send_password_updated_email()` | **send_now** |
| `user/reminder` | Password reminder link | **send_now** |
| `user_model` | Welcome after registration | queue |
| `form_model` | Autoreply, confirmation, admin notification emails | queue |
| `cms_log_rotate` | PHP error log summary to `admin_email` | queue |
| `stripe_model` | Webhook notification emails | queue |

Domain-specific content (tokens, templates, form field substitution) stays in the calling model or panel.
