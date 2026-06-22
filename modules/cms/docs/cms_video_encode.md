# cms_video_encode panel

## Encoding flow

- Normalisation step converts screen recordings to clean 30 fps AVC before
  main encoding
- HEVC encoding runs first (original ladder, best quality)
- AVC fallback is generated in separate `avc/` subfolder with same
  resolutions

## TODO

- This file only queues the job — never runs ffmpeg directly
- Job file `job_*.json` is written to `cache/video_jobs/` and picked up by
  background worker
- Background worker writes detailed logs to `cache/video_logs/` and updates
  job status
- Never use `exec()` for long ffmpeg calls here — always queue