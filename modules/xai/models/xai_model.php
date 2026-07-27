<?php

namespace xai;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * xAI HTTP client (OpenAI-compatible chat completions).
 */
class xai_model extends \Model {

	function get_settings(){

		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('xai/xai');
		if (!is_array($settings)){
			$settings = [];
		}

		return [
				'api_key' => trim((string)($settings['api_key'] ?? '')),
				'model' => trim((string)($settings['model'] ?? 'grok-4.3')) ?: 'grok-4.3',
				'base_url' => rtrim(trim((string)($settings['base_url'] ?? 'https://api.x.ai/v1')) ?: 'https://api.x.ai/v1', '/'),
				'context_style' => trim((string)($settings['context_style'] ?? '')),
		];

	}

	/**
	 * @param array $messages OpenAI-style messages
	 * @param array $options temperature, max_tokens, model override, response_format
	 * @return array{ok:int,error?:string,content?:string,raw?:array}
	 */
	function chat_completions($messages, $options = []){

		$settings = $this->get_settings();

		if ($settings['api_key'] === ''){
			return ['ok' => 0, 'error' => 'xAI API key is not configured'];
		}

		if (!is_array($messages) || $messages === []){
			return ['ok' => 0, 'error' => 'No messages for chat completion'];
		}

		$model = !empty($options['model']) ? (string)$options['model'] : $settings['model'];
		$body = [
				'model' => $model,
				'messages' => $messages,
		];

		if (isset($options['temperature'])){
			$body['temperature'] = (float)$options['temperature'];
		}
		if (isset($options['max_tokens'])){
			$body['max_tokens'] = (int)$options['max_tokens'];
		}
		if (!empty($options['response_format'])){
			$body['response_format'] = $options['response_format'];
		}

		$url = $settings['base_url'].'/chat/completions';
		$json = json_encode($body, JSON_UNESCAPED_UNICODE);
		if ($json === false){
			return ['ok' => 0, 'error' => 'Failed to encode request body'];
		}

		$timeout = !empty($options['timeout']) ? (int)$options['timeout'] : 120;

		// Same pattern as form_model / basic pageshare — no curl
		$context = stream_context_create([
				'http' => [
						'method' => 'POST',
						'header' =>
								"Content-Type: application/json\r\n".
								"Accept: application/json\r\n".
								'Authorization: Bearer '.$settings['api_key']."\r\n",
						'content' => $json,
						'timeout' => $timeout,
						'ignore_errors' => true,
				],
		]);

		$response = @file_get_contents($url, false, $context);

		$status = 0;
		if (!empty($http_response_header) && is_array($http_response_header)){
			foreach ($http_response_header as $hline){
				if (preg_match('#^HTTP/\S+\s+(\d+)#', $hline, $m)){
					$status = (int)$m[1];
					break;
				}
			}
		}

		if ($response === false){
			return ['ok' => 0, 'error' => 'xAI request failed (no response'.($status ? ', HTTP '.$status : '').')'];
		}

		$decoded = json_decode((string)$response, true);
		if (!is_array($decoded)){
			return ['ok' => 0, 'error' => 'Invalid JSON from xAI (HTTP '.$status.')'];
		}

		if ($status > 0 && ($status < 200 || $status >= 300)){
			$msg = $decoded['error']['message'] ?? ($decoded['error'] ?? ('HTTP '.$status));
			if (is_array($msg)){
				$msg = json_encode($msg);
			}
			return ['ok' => 0, 'error' => (string)$msg, 'raw' => $decoded];
		}

		// If status could not be parsed but body looks like an API error object
		if ($status === 0 && !empty($decoded['error'])){
			$msg = $decoded['error']['message'] ?? $decoded['error'];
			if (is_array($msg)){
				$msg = json_encode($msg);
			}
			return ['ok' => 0, 'error' => (string)$msg, 'raw' => $decoded];
		}

		$content = $decoded['choices'][0]['message']['content'] ?? '';
		if (!is_string($content)){
			$content = '';
		}

		return [
				'ok' => 1,
				'content' => $content,
				'raw' => $decoded,
		];

	}

}
