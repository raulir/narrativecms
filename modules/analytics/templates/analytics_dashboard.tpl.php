<div class="analytics_dashboard_container">
	<div class="analytics_dashboard_content">

		<h1 class="analytics_dashboard_title">Analytics</h1>

		<div class="analytics_dashboard_summary">
			<div class="analytics_dashboard_summary_item">
				<span class="analytics_dashboard_summary_label">Total pageviews</span>
				<span class="analytics_dashboard_summary_value"><?= (int)$total_pageviews ?></span>
			</div>
			<div class="analytics_dashboard_summary_item">
				<span class="analytics_dashboard_summary_label">Total sessions</span>
				<span class="analytics_dashboard_summary_value"><?= (int)$total_sessions ?></span>
			</div>
			<div class="analytics_dashboard_summary_item">
				<span class="analytics_dashboard_summary_label">Last 7 days pageviews</span>
				<span class="analytics_dashboard_summary_value"><?= (int)$pageviews_7_days ?></span>
			</div>
			<div class="analytics_dashboard_summary_item">
				<span class="analytics_dashboard_summary_label">Last 7 days sessions</span>
				<span class="analytics_dashboard_summary_value"><?= (int)$sessions_7_days ?></span>
			</div>
			<div class="analytics_dashboard_summary_item">
				<span class="analytics_dashboard_summary_label">Last 30 days pageviews</span>
				<span class="analytics_dashboard_summary_value"><?= (int)$pageviews_30_days ?></span>
			</div>
			<div class="analytics_dashboard_summary_item">
				<span class="analytics_dashboard_summary_label">Last 30 days sessions</span>
				<span class="analytics_dashboard_summary_value"><?= (int)$sessions_30_days ?></span>
			</div>
		</div>

		<div class="analytics_dashboard_chart_wrap">
			<img class="analytics_dashboard_chart" src="<?= $chart_url ?>" alt="Pageviews and sessions started per hour (last 7 days)" width="100%">
		</div>

		<h2 class="analytics_dashboard_heading">Last 50 sessions</h2>
		<?php if (!empty($geoip_error)): ?>
		<p class="analytics_dashboard_geoip_error"><?= htmlentities($geoip_error) ?></p>
		<?php endif ?>
		<table class="analytics_dashboard_table">
			<thead>
				<tr>
					<th>Started</th>
					<th>Last activity</th>
					<th>Session</th>
					<th>User</th>
					<th>Pages</th>
					<th>Total seconds</th>
					<th>Language</th>
					<th>Source</th>
					<?php if (empty($geoip_error)): ?>
					<th>Country</th>
					<th>Area</th>
					<th>City</th>
					<?php endif ?>
					<th>First page</th>
					<th>Last page</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($last_sessions as $session): ?>
				<tr>
					<td><?= htmlentities($session['started']) ?></td>
					<td><?= htmlentities($session['last_activity']) ?></td>
					<td><?= htmlentities(analytics_session_hash_display($session['session_id'] ?? '')) ?></td>
					<td><?= htmlentities($session['username'] ?? '') ?></td>
					<td><?= (int)($session['pageviews'] ?? 0) ?></td>
					<td><?= (int)($session['total_seconds'] ?? 0) ?></td>
					<td><?= htmlentities($session['language'] ?? '') ?></td>
					<td><?= htmlentities(analytics_session_source_label($session['source'] ?? 'beacon')) ?></td>
					<?php if (empty($geoip_error)): ?>
					<td><?= htmlentities($session['country'] ?? '') ?></td>
					<td><?= htmlentities($session['region'] ?? '') ?></td>
					<td><?= htmlentities($session['city'] ?? '') ?></td>
					<?php endif ?>
					<td><?= htmlentities($session['first_page'] ?? '') ?></td>
					<td><?= htmlentities($session['last_page'] ?? '') ?></td>
					<td><button type="button" class="analytics_dashboard_details_button" data-row_type="session" data-row_id="<?= htmlentities($session['session_id'] ?? '') ?>">Details</button></td>
				</tr>
				<?php endforeach ?>
			</tbody>
		</table>

		<h2 class="analytics_dashboard_heading">Last 50 pageviews</h2>
		<table class="analytics_dashboard_table">
			<thead>
				<tr>
					<th>Time</th>
					<th>Session</th>
					<th>Page</th>
					<th>IP</th>
					<?php if (!empty($has_multiple_languages)): ?>
					<th>Language</th>
					<?php endif ?>
					<th>Seconds</th>
					<th>Scroll %</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($last_pageviews as $pageview): ?>
				<tr>
					<td><?= htmlentities($pageview['created']) ?></td>
					<td><?= htmlentities(analytics_session_hash_display($pageview['session_id'] ?? '')) ?></td>
					<td><?= htmlentities($pageview['page']) ?></td>
					<td><?= htmlentities($pageview['ip_anonymised'] ?? '') ?></td>
					<?php if (!empty($has_multiple_languages)): ?>
					<td><?= htmlentities($pageview['language'] ?? '') ?></td>
					<?php endif ?>
					<td><?= (int)($pageview['seconds'] ?? 0) ?></td>
					<td><?= (int)($pageview['scroll_pct'] ?? 0) ?></td>
					<td><button type="button" class="analytics_dashboard_details_button" data-row_type="pageview" data-row_id="<?= (int)($pageview['cms_analytics_pageview_id'] ?? 0) ?>">Details</button></td>
				</tr>
				<?php endforeach ?>
			</tbody>
		</table>

		<h2 class="analytics_dashboard_heading">Top pages</h2>
		<table class="analytics_dashboard_table">
			<thead>
				<tr>
					<th>Page</th>
					<th>Pageviews</th>
					<th>Avg seconds</th>
					<th>Avg scroll %</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($top_pages as $page): ?>
				<tr>
					<td><?= htmlentities($page['page']) ?></td>
					<td><?= (int)$page['pageviews'] ?></td>
					<td><?= (int)($page['avg_seconds'] ?? 0) ?></td>
					<td><?= (int)($page['avg_scroll'] ?? 0) ?></td>
				</tr>
				<?php endforeach ?>
			</tbody>
		</table>

		<h2 class="analytics_dashboard_heading">Geographic top 50</h2>
		<?php if (!empty($geoip_error)): ?>
		<p class="analytics_dashboard_geoip_error"><?= htmlentities($geoip_error) ?></p>
		<?php else: ?>
		<table class="analytics_dashboard_table">
			<thead>
				<tr>
					<th>Country</th>
					<th>Area</th>
					<th>Sessions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($geo_top as $geo): ?>
				<tr>
					<td><?= htmlentities($geo['country']) ?></td>
					<td><?= htmlentities($geo['region'] ?? '') ?></td>
					<td><?= (int)$geo['sessions'] ?></td>
				</tr>
				<?php endforeach ?>
			</tbody>
		</table>
		<?php endif ?>

		<?php if (!empty($show_geoip_debug)): ?>
		<h2 class="analytics_dashboard_heading">GeoIP diagnostics</h2>
		<p class="analytics_dashboard_geoip_debug_help">Copy this block when reporting GeoIP issues.</p>
		<pre class="analytics_dashboard_geoip_debug"><?= htmlentities($geoip_debug_report ?? '') ?></pre>
		<?php endif ?>

		<div class="analytics_dashboard_detail_panel" hidden>
			<div class="analytics_dashboard_detail_panel_inner"></div>
		</div>

	</div>
</div>
<script>
var analytics_dashboard_rows = <?= json_encode([
	'sessions' => $last_sessions ?? [],
	'pageviews' => $last_pageviews ?? [],
], JSON_UNESCAPED_UNICODE) ?>;
</script>