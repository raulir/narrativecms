<div class="analytics_dashboard_container">
	<div class="analytics_dashboard_content">

		<h1 class="analytics_dashboard_title">Analytics</h1>

		<div class="analytics_dashboard_summary">
			<div class="analytics_dashboard_summary_item">
				<span class="analytics_dashboard_summary_label">Total pageviews</span>
				<span class="analytics_dashboard_summary_value"><?= (int)$total_pageviews ?></span>
			</div>
			<div class="analytics_dashboard_summary_item">
				<span class="analytics_dashboard_summary_label">Last 30 days</span>
				<span class="analytics_dashboard_summary_value"><?= (int)$pageviews_30_days ?></span>
			</div>
		</div>

		<div class="analytics_dashboard_chart_wrap">
			<img class="analytics_dashboard_chart" src="<?= $chart_url ?>" alt="Pageviews per hour (last 7 days)" width="100%">
		</div>

		<h2 class="analytics_dashboard_heading">Last 50 pageviews</h2>
		<?php if (!empty($geoip_error)): ?>
		<p class="analytics_dashboard_geoip_error"><?= htmlentities($geoip_error) ?></p>
		<?php endif ?>
		<table class="analytics_dashboard_table">
			<thead>
				<tr>
					<th>Time</th>
					<th>Session</th>
					<th>Page</th>
					<?php if (empty($geoip_error)): ?>
					<th>Country</th>
					<th>Area</th>
					<th>City</th>
					<?php endif ?>
					<th>Seconds</th>
					<th>Scroll %</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($last_pageviews as $pageview): ?>
				<tr>
					<td><?= htmlentities($pageview['created']) ?></td>
					<td><?= htmlentities(analytics_session_hash_display($pageview['session_id'] ?? '')) ?></td>
					<td><?= htmlentities($pageview['page']) ?></td>
					<?php if (empty($geoip_error)): ?>
					<td><?= htmlentities($pageview['country'] ?? '') ?></td>
					<td><?= htmlentities($pageview['region'] ?? '') ?></td>
					<td><?= htmlentities($pageview['city'] ?? '') ?></td>
					<?php endif ?>
					<td><?= (int)($pageview['seconds'] ?? 0) ?></td>
					<td><?= (int)($pageview['scroll_pct'] ?? 0) ?></td>
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
					<th>City</th>
					<th>Pageviews</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($geo_top as $geo): ?>
				<tr>
					<td><?= htmlentities($geo['country']) ?></td>
					<td><?= htmlentities($geo['region'] ?? '') ?></td>
					<td><?= htmlentities($geo['city'] ?? '') ?></td>
					<td><?= (int)$geo['pageviews'] ?></td>
				</tr>
				<?php endforeach ?>
			</tbody>
		</table>
		<?php endif ?>

	</div>
</div>