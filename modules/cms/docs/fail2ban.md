# Fail2ban: CMS 404 logs

PHP writes every CMS 404 to `{dir.log}/` (default `log/` in the project):

| File | When | Suggested jail |
|------|------|----------------|
| `cms_404_probe.log` | Hidden path (`/.…` except `/.well-known/`) or a probe needle (`.env`, `wp-`, `phpinfo`, `auth.json`, …) | 2 hits / 4 h → 24 h ban |
| `cms_404.log` | All other 404s | 10 hits / 1 h → 4 h ban |

Line format:

```
2026-09-20 15:48:57 [client 157.180.101.229] CMS 404 /.git/HEAD
```

Do **not** add these files to `apache-invaliduri` (that jail is AH00126 in httpd `error_log`).

`cms_404_is_probe()` (`system/helpers/error_helper.php`) ignores the query string. It flags hidden paths (`/.…` except `/.well-known/`), stack needles (WordPress, actuators, phpinfo, `auth.json`, Docker/CI, keys, …), and leaked dump/config basenames (`.sql`, `.env`, `settings.php`, `backup.zip`, …). Leave `ads.txt`, `index.php`, `/.well-known/`, and `/auth-google/` on `cms_404.log`. Do not add `composer` as a needle (music slugs).

Probe 404s return plain `Not Found` (no 302 to `/not-found/`, no layout). Logging is unchanged.

## Files on the server

`/etc/fail2ban/filter.d/cms-404.conf` (shared by both jails):

```
[Definition]
failregex = \[client <HOST>\] CMS 404
ignoreregex =
datepattern = ^%%Y-%%m-%%d %%H:%%M:%%S
```

`/etc/fail2ban/jail.d/cms-404.conf`:

```
[cms-404-probe]
enabled   = true
filter    = cms-404
logpath   = /var/www/html/scoretutor.com/log/cms_404_probe.log
banaction = %(banaction_allports)s
maxretry  = 2
findtime  = 4h
bantime   = 24h

[cms-404]
enabled   = true
filter    = cms-404
logpath   = /var/www/html/scoretutor.com/log/cms_404.log
banaction = %(banaction_allports)s
maxretry  = 10
findtime  = 1h
bantime   = 4h
```

Change `logpath` if the site root is not `/var/www/html/scoretutor.com`. Create empty log files if fail2ban refuses a missing path:

```
touch /var/www/html/scoretutor.com/log/cms_404.log /var/www/html/scoretutor.com/log/cms_404_probe.log
chown apache:apache /var/www/html/scoretutor.com/log/cms_404*.log
```

Then `fail2ban-client reload`.

## Analytics location cluster

PHP-only 1-page sessions over the city cap or the country/region cap are dropped from analytics. Both write `{dir.log}/analytics_cluster.log` (anonymised **C-class** `.0` + user agent) — no extra fail2ban jail. Ban the **/24**, not the `.0` host. Do not add this file to `cms-404` or `apache-invaliduri`.

Line format:

```
2026-09-21 12:00:00 [client 45.138.12.0] CMS analytics cluster Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) ...
```

`/etc/fail2ban/filter.d/cms-analytics-cluster.conf`:

```
[Definition]
failregex = \[client <HOST>\] CMS analytics cluster
ignoreregex =
datepattern = ^%%Y-%%m-%%d %%H:%%M:%%S
```

`/etc/fail2ban/action.d/iptables-allports-subnet24.conf`:

```
[Definition]
actionban = iptables -I INPUT -s <ip>/24 -j DROP
actionunban = iptables -D INPUT -s <ip>/24 -j DROP
```

`/etc/fail2ban/jail.d/cms-analytics-cluster.conf`:

```
[cms-analytics-cluster]
enabled   = true
filter    = cms-analytics-cluster
logpath   = /var/www/html/scoretutor.com/log/analytics_cluster.log
action    = iptables-allports-subnet24[name=cms-analytics-cluster]
maxretry  = 1
findtime  = 24h
bantime   = 24h
```

```
touch /var/www/html/scoretutor.com/log/analytics_cluster.log
chown apache:apache /var/www/html/scoretutor.com/log/analytics_cluster.log
fail2ban-client reload
```

IPv6 cluster lines are not written (v1). Review UAs in this log and add stable bot strings to `analytics_is_bot_user_agent()` so those hits are skipped at record time and not firewall-banned.
