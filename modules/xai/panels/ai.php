<?php

namespace xai;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Generic AI service provider panel (provides service "ai").
 * Call via run_panel_method('xai/ai', 'ai_request', ['task' => …, 'payload' => …]).
 */
class ai extends \Controller {

	function panel_params($params){

		return $params;

	}

	/**
	 * @param array $params task, payload
	 * @return array{ok:int,error?:string,result?:array}
	 */
	function ai_request($params){

		$task = trim((string)($params['task'] ?? ''));
		$payload = $params['payload'] ?? [];
		if (!is_array($payload)){
			$payload = [];
		}

		if ($task === ''){
			return ['ok' => 0, 'error' => 'Missing AI task'];
		}

		$this->load->model('xai/xai_model');

		if ($task === 'translate'){
			return $this->_task_translate($payload);
		}

		if ($task === 'chat'){
			return $this->_task_chat($payload);
		}

		// describe_image etc. later
		return ['ok' => 0, 'error' => 'Unsupported AI task: '.$task];

	}

	function _task_chat($payload){

		$messages = $payload['messages'] ?? [];
		if (!is_array($messages) || $messages === []){
			return ['ok' => 0, 'error' => 'chat requires messages'];
		}

		$options = [];
		if (isset($payload['temperature'])){
			$options['temperature'] = $payload['temperature'];
		}
		if (isset($payload['max_tokens'])){
			$options['max_tokens'] = $payload['max_tokens'];
		}
		if (!empty($payload['model'])){
			$options['model'] = $payload['model'];
		}

		$out = $this->xai_model->chat_completions($messages, $options);
		if (empty($out['ok'])){
			return ['ok' => 0, 'error' => $out['error'] ?? 'Chat failed'];
		}

		return [
				'ok' => 1,
				'result' => [
						'content' => $out['content'] ?? '',
				],
		];

	}

	function _task_translate($payload){

		$source_language = trim((string)($payload['source_language'] ?? ''));
		$target_language = trim((string)($payload['target_language'] ?? ''));
		$items = $payload['items'] ?? [];
		$context = $payload['context'] ?? [];
		if (!is_array($items) || $items === []){
			return ['ok' => 0, 'error' => 'No items to translate'];
		}
		if ($source_language === '' || $target_language === ''){
			return ['ok' => 0, 'error' => 'Source and target language required'];
		}

		$settings = $this->xai_model->get_settings();
		$site_style = $settings['context_style'];

		$allowed_keys = [];
		$compact = [];
		foreach ($items as $item){
			if (!is_array($item)){
				continue;
			}
			$key = (string)($item['key'] ?? '');
			if ($key === ''){
				continue;
			}
			$allowed_keys[$key] = 1;
			$compact[] = [
					'key' => $key,
					'label' => (string)($item['label'] ?? ''),
					'source' => (string)($item['source'] ?? ''),
					'current' => (string)($item['current'] ?? ''),
					'definition_default' => (string)($item['definition_default'] ?? ''),
					'field_type' => (string)($item['field_type'] ?? 'text'),
			];
		}

		if ($compact === []){
			return ['ok' => 0, 'error' => 'No valid translation items'];
		}

		$panel_meta = is_array($context) ? $context : [];

		// System: fixed instructions + site style (stable across panels/languages → better prompt cache)
		$system_parts = [];
		$system_parts[] = "You are a professional UI/copy translator for a CMS.\n"
				."Return ONLY valid JSON of the form: {\"items\":[{\"key\":\"...\",\"suggestion\":\"...\"},...]}\n"
				."Rules:\n"
				."- Include every key from the request exactly once; do not invent keys.\n"
				."- Preserve placeholders like {{name}}, HTML tags, and punctuation intent.\n"
				."- Keep tone consistent with site context when provided.\n"
				."- If source is empty, suggestion must be empty string.\n"
				."- suggestion is the translation only (no quotes around the whole JSON string value beyond JSON rules).";
		if ($site_style !== ''){
			$system_parts[] = "Site / style context from admin:\n".$site_style;
		}

		// User: fully request-specific (language pair, panel meta, field payload)
		$user_parts = [];
		$user_parts[] = "Translate each item's source text from language \"".$source_language."\" to \"".$target_language."\".";
		if (!empty($panel_meta['panel_name'])){
			$user_parts[] = 'CMS panel: '.$panel_meta['panel_name'];
		}
		if (!empty($panel_meta['admin_title'])){
			$user_parts[] = 'Panel title: '.$panel_meta['admin_title'];
		}
		if (!empty($panel_meta['extra'])){
			$user_parts[] = 'Extra context: '.(is_string($panel_meta['extra']) ? $panel_meta['extra'] : json_encode($panel_meta['extra'], JSON_UNESCAPED_UNICODE));
		}
		$user_parts[] = "Fields to translate (JSON):\n".json_encode(['items' => $compact], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

		$messages = [
				['role' => 'system', 'content' => implode("\n\n", $system_parts)],
				['role' => 'user', 'content' => implode("\n\n", $user_parts)],
		];

		$out = $this->xai_model->chat_completions($messages, [
				'temperature' => 0.2,
				'max_tokens' => 8000,
		]);

		if (empty($out['ok'])){
			return ['ok' => 0, 'error' => $out['error'] ?? 'Translation request failed'];
		}

		$parsed = $this->_parse_json_content($out['content'] ?? '');
		if ($parsed === null || empty($parsed['items']) || !is_array($parsed['items'])){
			return ['ok' => 0, 'error' => 'AI returned unparseable translation JSON'];
		}

		$suggestions = [];
		foreach ($parsed['items'] as $row){
			if (!is_array($row)){
				continue;
			}
			$key = (string)($row['key'] ?? '');
			if ($key === '' || empty($allowed_keys[$key])){
				continue;
			}
			$text = is_scalar($row['suggestion'] ?? '') ? (string)$row['suggestion'] : '';
			// Real UTF-8 for CMS storage/UI — never HTML entities
			if (function_exists('cms_utf8_string')){
				$text = cms_utf8_string($text);
			}
			$suggestions[$key] = $text;
		}

		return [
				'ok' => 1,
				'result' => [
						'items' => array_map(function($key, $suggestion){
							return ['key' => $key, 'suggestion' => $suggestion];
						}, array_keys($suggestions), array_values($suggestions)),
						'suggestions' => $suggestions,
				],
		];

	}

	function _parse_json_content($content){

		$content = trim((string)$content);
		if ($content === ''){
			return null;
		}

		// Strip common markdown fences
		if (preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```$/i', $content, $m)){
			$content = trim($m[1]);
		}

		$decoded = json_decode($content, true);
		if (is_array($decoded)){
			return $decoded;
		}

		// Try first { … } block
		$start = strpos($content, '{');
		$end = strrpos($content, '}');
		if ($start !== false && $end !== false && $end > $start){
			$decoded = json_decode(substr($content, $start, $end - $start + 1), true);
			if (is_array($decoded)){
				return $decoded;
			}
		}

		return null;

	}

}
