# Email transport (`cms_email_model`)

All outbound email uses [`cms_email_model`](../models/cms_email_model.php).

```php
$this->load->model('cms/cms_email_model');
$this->cms_email_model->send_mail($to, $subject, $body, $params);
```

Returns `bool`. Failures are logged with prefix `cms_email_model send_mail:`.

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
| `smtp_server`, `smtp_port`, `smtp_username`, `smtp_password` | SMTP |
| `smtp_debug` | Write SMTP debug log to `cache/smtp_debug_*` |

## `$params`

| Key | Description |
|-----|-------------|
| `is_html` | HTML body |
| `alt_body` | Plain alternative; also used as `mail()` body when `is_html` and SMTP unavailable |
| `auto_submitted` | `Auto-Submitted: auto-generated` header |
| `reply_to` | `['email' =>, 'name' =>]` override |
| `from_email` / `from_name` | Optional overrides |
| `mail_from_email_only` | PHP `mail()` only: `From: email` without name (form admin notifications) |
| `x_mailer` | PHPMailer `XMailer` value |
| `smtp_debug` | Force SMTP debug for this send |

## Callers

| Module | Use |
|--------|-----|
| `user_model` | `send_email_verification()` |
| `user/reminder` | Password reminder + update confirmation |
| `form_model` | Autoreply, confirmation, admin notification emails |
| `cms_log_rotate` | PHP error log summary to `admin_email` |

Domain-specific content (tokens, templates, form field substitution) stays in the calling model or panel.