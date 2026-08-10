<?php

namespace weather;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class weather extends \Controller {

	function panel_params($params){

		$this->load->model('weather/weather_model');

		$location = isset($params['location']) ? $params['location'] : 'Cheltenham';
		$lat = isset($params['latitude']) ? $params['latitude'] : '51.88964722230162';
		$lon = isset($params['longitude']) ? $params['longitude'] : '-2.1204895339274916';
		$provider = isset($params['weather_provider']) ? $params['weather_provider'] : null;

		$result = $this->weather_model->get_forecast($lat, $lon, $location, false, $provider);

		$params['ok'] = !empty($result['ok']) ? 1 : 0;
		$params['error'] = !empty($result['error']) ? $result['error'] : '';
		$params['location'] = !empty($result['location']) ? $result['location'] : $location;
		$params['days'] = !empty($result['days']) ? $result['days'] : [];
		$params['later_days'] = !empty($result['later_days']) ? $result['later_days'] : [];
		$params['hours_by_date'] = !empty($result['hours_by_date']) ? $result['hours_by_date'] : new \stdClass();
		$params['fetched_at'] = !empty($result['fetched_at']) ? (int)$result['fetched_at'] : 0;

		// BBC weather day (06:00 start) — before 6am still "yesterday"
		$tz = new \DateTimeZone('Europe/London');
		$now = new \DateTime('now', $tz);
		$weather_day = !empty($result['current_weather_day'])
				? $result['current_weather_day']
				: $this->weather_model->weather_day_key($now);
		$params['today_date'] = $weather_day;
		$params['selected_date'] = $weather_day;
		if (!empty($params['days'][0]['date'])){
			$has = false;
			foreach ($params['days'] as $d){
				if ($d['date'] === $weather_day){
					$has = true;
					break;
				}
			}
			if (!$has){
				$params['selected_date'] = $params['days'][0]['date'];
			}
		}

		$params['label_later'] = !empty($params['label_later']) ? $params['label_later'] : 'Later';
		$params['label_error'] = !empty($params['label_error']) ? $params['label_error'] : 'Could not load forecast';
		$params['label_empty'] = !empty($params['label_empty']) ? $params['label_empty'] : 'No forecast data yet';

		// Attribution: panel override, else provider string, else source-based default
		if (empty($params['attribution'])){
			if (!empty($result['attribution'])){
				$params['attribution'] = $result['attribution'];
			} else {
				$src = !empty($result['source']) ? $result['source'] : '';
				$params['attribution'] = ($src === 'metoffice')
					? 'Met Office DataHub Global Spot'
					: 'Met Office via Open-Meteo';
			}
		}

		// Pre-build sky HTML with model helper (same as menu chip) for SSR day icons
		foreach ($params['days'] as $di => $day_row){
			$sky_k = !empty($day_row['sky']) ? $day_row['sky'] : 'cloudy';
			$params['days'][$di]['sky_html'] = $this->weather_model->sky_html($sky_k);
		}

		$params['days_json'] = json_encode($params['days'], JSON_UNESCAPED_UNICODE);
		$params['later_days_json'] = json_encode($params['later_days'], JSON_UNESCAPED_UNICODE);
		$params['hours_json'] = json_encode($params['hours_by_date'], JSON_UNESCAPED_UNICODE);

		return $params;

	}

}
