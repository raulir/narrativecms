# CMS video encoding

**See also:** [`agents.md`](agents.md) (CMS architecture rules), [`cms_input_image.md`](cms_input_image.md) (selector/preview), [`cms_image.md`](cms_image.md) (video-as-image editor).

## Overview

Uploaded `.mp4` files and animated GIFs converted to `.mp4` are stored in `cms_image` like images. DASH adaptive streaming assets are built lazily by a cron task — not at upload time.

While DASH encode is pending, deferred, or skipped, the site serves the **uploaded original** `.mp4` or `filename.data/fallback.mp4` once partial encode exists.

## Queue

- **File:** `cache/video_queue.json`
- **Lock:** `cache/video_queue.lock` (JSON `{ "started": <unix_time> }`, auto-cleared after 2 hours if stale)
- **Log:** `cache/video_encode.log`

### Enqueue (`cms_video_model::video_add_queue`)

Triggered by:

- MP4 upload ([`cms_images_upload.php`](../panels/cms_images_upload.php))
- Animated GIF lazy convert ([`cms_image_model::_convert_animated_gif()`](../models/cms_image_model.php))

Skips when:

- ffmpeg not configured / not found
- ffprobe metadata fails
- Same `videofile` path already in queue

## Cron

1. **Settings → CMS repeating tasks** — add `CMS video encode` (`cms/cms_video_encode`) on desired interval
2. Cron runner: [`cms_helper_model::run_cron()`](../models/cms_helper_model.php) → `cms_video_encode::panel_action()` → `cms_video_model::process_encode_queue()`
3. Also triggered on site visits when `cron_trigger: visits` (via `cms_cron_run.js` hitting `/cms_operations/cron/`)

Each cron tick processes **one** queue item (if allowed).

### Defer / skip conditions

| Condition | Behaviour |
|-----------|-----------|
| Queue empty | Returns `queue empty` |
| Lock held (fresh) | Returns `locked, encoding in progress` |
| Lock stale (>2h) | Lock cleared, encode may proceed |
| ffmpeg missing | Returns `ffmpeg not available` |
| CPU load ≥ limit | Returns `server load too high (N%)` — queue unchanged |
| Source file missing | Item removed from queue |
| Encode failure | Logged; failed item moved to **end** of queue |

**CPU load limit:** Site settings → **Image optimisation** → **Video encode max load** (`video_encode_max_load`, default `0.8`). Linux uses `sys_getloadavg` ÷ CPU count; Windows uses `wmic cpu get loadpercentage`.

## Encode passes (per queue item)

All runs synchronously in one cron request (`set_time_limit(3600)`):

1. Optional screen-recording normalisation (`_normalise_video`)
2. Cover jpg extract
3. DASH HEVC (`manifest.mpd`)
4. Fallback mp4 (400k)
5. Fallback HD mp4 (1Mbps)
6. Thumb gif
7. DASH AVC (`libx264/manifest.mpd`)

Output folder: `img/<path>/<name>.mp4.data/`

## Frontend serving

[`image_helper.php`](../../system/helpers/image_helper.php) `_ib()` mp4 branch:

- Uses `fallback.mp4` / `fallback_hd.mp4` when present, else original upload
- Adds DASH manifest attributes when `.data/manifest.mpd` exists
- Missing source and no fallback → `cms_no_image.png` (broken image behaviour)
- **Video child** (meta-only `_vN.mp4`): resolves parent file for URLs; emits `data-cms_video_view="1"` and crop/adjust meta for [`cms_video_init()`](../../modules/cms/js/cms_video.js) view branch + [`cms_media_view.js`](../../modules/cms/js/cms_media_view.js). Poster uses `{child}.data/cover.jpg` when exported on save (ffmpeg required for that step; skipped otherwise), else parent cover.

**Background fit on host div** — panel CSS `background-size` / `background-position` on the `_ib()` element controls layout (same as images):

- `cover`, `contain`, and explicit sizes (`4rem auto`, `auto 30%`, etc.)
- Two numeric sizes → width only, height from cropped aspect ratio (no stretch)
- Cover poster `background-image` cleared when video plays

Player init uses `cms_video_init()` / DASH / native fallback only. Legacy `video_play()` / carousel helpers in `cms_video.js` are unused for `_ib` embeds.

### Playback resilience (`cms_video.js`)

Muted `_ib()` loops attach a **viewport gate** and **playback warden** (not used inside the image editor `.cms_image_container`):

- **Off-screen** — `IntersectionObserver` pauses the video and keeps `currentTime` (reduces Chrome decoder pressure in the image grid).
- **On-screen** — resumes with `play()` from the current position.
- **Stuck while visible** — warden detects unexpected `pause`, `stalled` / `waiting`, or frozen `currentTime`; recovery resumes from the same position (`play()` → tiny seek nudge → rare `load()` with time restore). Does **not** seek back to the start.
- **Tab visibility** — when the document becomes visible again, on-screen ready hosts resume from their current time.

## Panel

[`cms_video_encode.php`](../panels/cms_video_encode.php) — thin wrapper calling `process_encode_queue()`.

## Future todos

- **`_probe_video()` validation** — guard empty ffprobe JSON / missing `streams` before foreach in encode and `get_video_metadata()`
- **Split encode into separate queue jobs** — first job: fast passes + lowest DASH ladder step; subsequent jobs: one high-quality ladder step each, increasing quality order (resumable after partial failure)
- **CLI background worker** — optional decouple from web PHP cron timeout