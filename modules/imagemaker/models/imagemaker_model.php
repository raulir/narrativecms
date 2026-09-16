<?php

namespace imagemaker;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * GD compositing for layered images (warp overlay + colour tint).
 *
 * Domain modules should call provides.image_compose (run_action imagemaker/compose),
 * not this model. Engine: add_image / add_colour.
 *
 * Image arguments: CMS relative keys under upload_path (e.g. "2025/04/x.png")
 * or relative keys already under imagemaker/, or absolute filesystem paths.
 * Returns relative keys under upload_path (prefer imagemaker/…).
 */
class imagemaker_model extends \Model {

	/**
	 * Whether the imagemaker module is active on this site.
	 */
	function is_available(){
		return in_array('imagemaker', $GLOBALS['config']['modules'] ?? [], true);
	}

	/**
	 * Whether style blending is on (default). Explicit "off" disables.
	 *
	 * @param array|string $style style panel or raw blending value
	 */
	function style_blending_enabled($style){

		if (is_array($style)){
			$v = $style['blending'] ?? 'on';
		} else {
			$v = $style;
		}
		$v = strtolower(trim((string)$v));
		if ($v === '' || $v === 'on' || $v === '1' || $v === 'yes' || $v === 'true'){
			return true;
		}
		if ($v === 'off' || $v === '0' || $v === 'no' || $v === 'false'){
			return false;
		}
		return true;

	}

	/**
	 * Warp $image_ontop onto $image_base using a transform control grid.
	 *
	 * @param string $image_ontop overlay / print
	 * @param string $image_base destination background
	 * @param string|array $transform JSON string or decoded array (width, height, maxx, maxy, data)
	 * @param bool $blending true = blend_colour; false = overwrite with overlay pixel
	 * @return array{image:string,mask:string}|array{image:string,mask:string,error:string}
	 */
	function add_image($image_ontop, $image_base, $transform, $blending = true){

		$points = $this->decode_transform($transform);
		if ($points === null){
			return ['image' => '', 'mask' => '', 'error' => 'Invalid transform JSON'];
		}

		$blending = (bool)$blending;
		$points_key = is_string($transform) ? $transform : json_encode($transform);
		// keep_alpha: cache bust after preserving base PNG alpha (savealpha + skip fully transparent)
		$cache_key = md5($image_ontop.'_'.$image_base.'_'.$points_key.'_'.($blending ? 'blend' : 'copy').'_keep_alpha');
		$rel_image = 'imagemaker/a_'.$cache_key.'.png';
		$rel_mask = 'imagemaker/m_'.$cache_key.'.png';
		$abs_image = $this->cache_absolute($rel_image);
		$abs_mask = $this->cache_absolute($rel_mask);

		if (is_file($abs_image) && is_file($abs_mask)){
			return ['image' => $rel_image, 'mask' => $rel_mask];
		}

		$base = $this->load_gd($image_base);
		$addon = $this->load_gd($image_ontop);
		if ($base === false || $addon === false){
			return ['image' => '', 'mask' => '', 'error' => 'Failed to load base or overlay image'];
		}

		$bw = imagesx($base);
		$bh = imagesy($base);
		$points = $this->transform_to_pixels($points, $bw, $bh);
		$points = $this->expand_transform_grid($points);

		$aw = imagesx($addon);
		$ah = imagesy($addon);
		$maxx = $bw;
		$maxy = $bh;
		$grid_w = (int)$points['width'];
		$grid_h = (int)$points['height'];

		$mask = null;
		$build_mask = !is_file($abs_mask);
		if ($build_mask){
			$mask = imagecreatetruecolor($maxx, $maxy);
			imagealphablending($mask, false);
			imagesavealpha($mask, true);
			imagefill($mask, 0, 0, imagecolorallocate($mask, 255, 255, 255));
		}

		$build_image = !is_file($abs_image);
		if ($build_image){
			imagealphablending($base, false);
			imagesavealpha($base, true);
		}

		for ($y = 0; $y < $grid_h; $y++){
			for ($x = 0; $x < $grid_w; $x++){

				if (!isset($points['data'][$y][$x], $points['data'][$y][$x + 1],
						$points['data'][$y + 1][$x], $points['data'][$y + 1][$x + 1])){
					continue;
				}

				$vertices = [
						[$points['data'][$y][$x][0], $points['data'][$y][$x][1]],
						[$points['data'][$y][$x + 1][0], $points['data'][$y][$x + 1][1]],
						[$points['data'][$y + 1][$x + 1][0], $points['data'][$y + 1][$x + 1][1]],
						[$points['data'][$y + 1][$x][0], $points['data'][$y + 1][$x][1]],
				];

				$bx_min = (int)min($points['data'][$y][$x][0], $points['data'][$y + 1][$x][0]);
				$bx_max = (int)max($points['data'][$y][$x + 1][0], $points['data'][$y + 1][$x + 1][0]);
				$by_min = (int)min($points['data'][$y][$x][1], $points['data'][$y][$x + 1][1]);
				$by_max = (int)max($points['data'][$y + 1][$x][1], $points['data'][$y + 1][$x + 1][1]);

				for ($bx = $bx_min; $bx < $bx_max; $bx++){
					for ($by = $by_min; $by < $by_max; $by++){

						if (!$this->point_inside($vertices, $bx, $by)){
							continue;
						}

						$dist_up = $this->dist_to_line(
								$points['data'][$y][$x][0], $points['data'][$y][$x][1],
								$points['data'][$y][$x + 1][0], $points['data'][$y][$x + 1][1],
								$bx, $by);
						$dist_down = $this->dist_to_line(
								$points['data'][$y + 1][$x][0], $points['data'][$y + 1][$x][1],
								$points['data'][$y + 1][$x + 1][0], $points['data'][$y + 1][$x + 1][1],
								$bx, $by);
						$sum_y = $dist_up + $dist_down;
						$coef_y = $sum_y > 0 ? $dist_up / $sum_y : 0;

						$dist_left = $this->dist_to_line(
								$points['data'][$y][$x][0], $points['data'][$y][$x][1],
								$points['data'][$y + 1][$x][0], $points['data'][$y + 1][$x][1],
								$bx, $by);
						$dist_right = $this->dist_to_line(
								$points['data'][$y][$x + 1][0], $points['data'][$y][$x + 1][1],
								$points['data'][$y + 1][$x + 1][0], $points['data'][$y + 1][$x + 1][1],
								$bx, $by);
						$sum_x = $dist_left + $dist_right;
						$coef_x = $sum_x > 0 ? $dist_left / $sum_x : 0;

						$pw = $aw / $grid_w;
						$ph = $ah / $grid_h;
						$ax = (int)round(($x + $coef_x) * $pw);
						$ay = (int)round(($y + $coef_y) * $ph);

						if ($build_image && $ax >= 0 && $ax < $aw && $ay >= 0 && $ay < $ah
								&& $bx >= 0 && $by >= 0 && $bx < imagesx($base) && $by < imagesy($base)){
							$brgba = imagecolorsforindex($base, imagecolorat($base, $bx, $by));
							// Fully transparent base: keep the hole; RGB is often junk (black)
							if ((int)($brgba['alpha'] ?? 0) < 127){
								$argba = imagecolorsforindex($addon, imagecolorat($addon, $ax, $ay));
								if ($blending){
									$nc = $this->blend_colour($brgba, $argba);
								} else {
									// RGB overwrite; alpha always multiplied (below)
									$nc = [
											'red' => $argba['red'],
											'green' => $argba['green'],
											'blue' => $argba['blue'],
									];
								}
								$nc['alpha'] = $this->blend_alpha($brgba['alpha'] ?? 0, $argba['alpha'] ?? 0);
								imagesetpixel($base, $bx, $by,
										imagecolorallocatealpha($base, $nc['red'], $nc['green'], $nc['blue'], $nc['alpha']));
							}
						}

						if ($build_mask && $mask && $bx >= 0 && $by >= 0 && $bx < $maxx && $by < $maxy){
							imagesetpixel($mask, $bx, $by, imagecolorallocate($mask, 0, 0, 0));
						}
					}
				}
			}
		}

		$this->ensure_cache_dir();

		if ($build_image){
			imagealphablending($base, false);
			imagesavealpha($base, true);
			imagepng($base, $abs_image);
		}
		if ($build_mask && $mask){
			imagepng($mask, $abs_mask);
			imagedestroy($mask);
		}

		imagedestroy($base);
		imagedestroy($addon);

		return ['image' => $rel_image, 'mask' => $rel_mask];
	}

	/**
	 * Tint $image with $colour. Optional mask: pure black pixels are not painted.
	 *
	 * @param string $colour #rrggbb or rrggbb
	 * @param string $image source image key/path
	 * @param string $mask optional mask key/path
	 * @return string relative path under upload_path, or '' on failure
	 */
	function add_colour($colour, $image, $mask = ''){

		$colour = ltrim((string)$colour, '#');
		if (strlen($colour) < 6 || $image === '' || $image === null){
			return '';
		}

		$ar = hexdec(substr($colour, 0, 2));
		$ag = hexdec(substr($colour, 2, 2));
		$ab = hexdec(substr($colour, 4, 2));

		$cache_key = md5($colour.'_'.$image.'_'.$mask);
		$rel = 'imagemaker/c_'.$cache_key.'.png';
		$abs = $this->cache_absolute($rel);
		if (is_file($abs)){
			return $rel;
		}

		$src = $this->load_gd($image);
		if ($src === false){
			return '';
		}

		$mask_gd = null;
		if ($mask !== '' && $mask !== null){
			$mask_gd = $this->load_gd($mask);
		}

		$width = imagesx($src);
		$height = imagesy($src);
		$out = imagecreatetruecolor($width, $height);
		imagealphablending($out, false);
		imagesavealpha($out, true);

		for ($x = 0; $x < $width; $x++){
			for ($y = 0; $y < $height; $y++){

				$colour_at = imagecolorat($src, $x, $y);
				$rgba = imagecolorsforindex($src, $colour_at);

				if ($mask_gd !== null && !$this->mask_allows_paint($mask_gd, $x, $y)){
					imagesetpixel($out, $x, $y,
							imagecolorallocatealpha($out, $rgba['red'], $rgba['green'], $rgba['blue'], $rgba['alpha']));
					continue;
				}

				$nrgba = $this->blend_colour($rgba, [
						'red' => $ar,
						'green' => $ag,
						'blue' => $ab,
				]);
				// Tint keeps source alpha (not warp multiply)
				imagesetpixel($out, $x, $y,
						imagecolorallocatealpha($out, $nrgba['red'], $nrgba['green'], $nrgba['blue'], (int)$rgba['alpha']));
			}
		}

		$this->ensure_cache_dir();
		imagepng($out, $abs);

		imagedestroy($src);
		imagedestroy($out);
		if ($mask_gd !== null){
			imagedestroy($mask_gd);
		}

		return $rel;
	}

	// -------------------------------------------------------------------------
	// Transform / geometry
	// -------------------------------------------------------------------------

	/**
	 * @param string|array $transform
	 * @return array|null
	 */
	function decode_transform($transform){

		if (is_array($transform)){
			return $transform;
		}
		$transform = trim((string)$transform);
		if ($transform === ''){
			return null;
		}
		$data = json_decode($transform, true);
		return is_array($data) ? $data : null;
	}

	/**
	 * Convert transform control points to pixel coords on the base image.
	 * units "percent" (or maxx/maxy ≈ 100): x/y are % of width/height (like cms_image crop).
	 * Otherwise treat data as absolute pixels (legacy prototype).
	 *
	 * @param array $points
	 * @param int $base_w
	 * @param int $base_h
	 * @return array
	 */
	function transform_to_pixels(array $points, $base_w, $base_h){

		$base_w = (int)$base_w;
		$base_h = (int)$base_h;
		if ($base_w < 1 || $base_h < 1 || empty($points['data']) || !is_array($points['data'])){
			return $points;
		}

		$units = strtolower(trim((string)($points['units'] ?? '')));
		$maxx = (float)($points['maxx'] ?? 0);
		$maxy = (float)($points['maxy'] ?? 0);
		$as_percent = ($units === 'percent' || $units === '%' || $units === 'pct')
				|| ($maxx > 0 && $maxx <= 100.5 && $maxy > 0 && $maxy <= 100.5);

		if (!$as_percent){
			// Legacy: data already in pixels; maxx/maxy describe canvas (may match base)
			if ($maxx < 1){
				$points['maxx'] = $base_w;
			}
			if ($maxy < 1){
				$points['maxy'] = $base_h;
			}
			return $points;
		}

		foreach ($points['data'] as $yi => $row){
			if (!is_array($row)){
				continue;
			}
			foreach ($row as $xi => $pt){
				if (!is_array($pt) || count($pt) < 2){
					continue;
				}
				$px = ((float)$pt[0] / 100.0) * $base_w;
				$py = ((float)$pt[1] / 100.0) * $base_h;
				$points['data'][$yi][$xi] = [round($px), round($py)];
			}
		}
		$points['maxx'] = $base_w;
		$points['maxy'] = $base_h;
		$points['units'] = 'pixel';

		return $points;
	}

	/**
	 * Fill sparse mid-rows that only have two endpoints.
	 *
	 * @param array $points
	 * @return array
	 */
	function expand_transform_grid(array $points){

		$height = (int)($points['height'] ?? 0);
		$width = (int)($points['width'] ?? 0);
		if ($height < 1 || $width < 1 || empty($points['data']) || !is_array($points['data'])){
			return $points;
		}

		for ($y = 1; $y < $height; $y++){
			if (!isset($points['data'][$y]) || !is_array($points['data'][$y])){
				continue;
			}
			if (count($points['data'][$y]) != 2){
				continue;
			}

			$lx = $points['data'][$y][1][0];
			$ly = $points['data'][$y][1][1];

			for ($x = 1; $x < $width; $x++){
				$dx = $lx - $points['data'][$y][0][0];
				$newx = round($points['data'][$y][0][0] + $dx * ($x / $width));

				$dy = $points['data'][$height][$x][1] - $points['data'][0][$x][1];
				$newy = round($points['data'][0][$x][1] + $dy * ($y / $height));

				$points['data'][$y][$x] = [$newx, $newy];
			}

			$points['data'][$y][$width] = [$lx, $ly];
		}

		return $points;
	}

	/**
	 * Multiply transparencies (always used for warp alpha).
	 * Transparency T: 0 = opaque, 1 = fully transparent. T_out = T_base * T_overlay.
	 * GD alpha: 0 = opaque, 127 = fully transparent.
	 *
	 * @param int $base_alpha GD alpha 0–127
	 * @param int $overlay_alpha GD alpha 0–127
	 * @return int GD alpha 0–127
	 */
	function blend_alpha($base_alpha, $overlay_alpha){

		$tb = max(0, min(127, (int)$base_alpha)) / 127.0;
		$to = max(0, min(127, (int)$overlay_alpha)) / 127.0;
		$tout = max(0.0, min(1.0, $tb * $to));
		return (int)round($tout * 127);

	}

	/**
	 * Lightness-aware blend of overlay RGB onto base RGB.
	 * Alpha is not set here — use blend_alpha() at the call site.
	 *
	 * @param array $base_rgba
	 * @param array $overlay_rgba
	 * @return array{red:int,green:int,blue:int}
	 */
	function blend_colour(array $base_rgba, array $overlay_rgba){

		$sum = $base_rgba['red'] + $base_rgba['green'] + $base_rgba['blue'];
		$dc = min(765 - $sum, $sum) / 384;
		$dck = 0.5 * $dc;
		$ck = 1 - $dck;
		$ok = 1 - $ck;
		$intensity = $ck * $sum / 765;

		return [
				'red' => (int)round($intensity * $overlay_rgba['red'] + $ok * $base_rgba['red']),
				'green' => (int)round($intensity * $overlay_rgba['green'] + $ok * $base_rgba['green']),
				'blue' => (int)round($intensity * $overlay_rgba['blue'] + $ok * $base_rgba['blue']),
		];
	}

	function dist_to_line($x1, $y1, $x2, $y2, $xp, $yp){

		$a = $y2 - $y1;
		$b = -($x2 - $x1);
		$c = ($x2 - $x1) * $y1 - ($y2 - $y1) * $x1;
		$denominator = sqrt($a * $a + $b * $b);
		if ($denominator == 0){
			return 0.0;
		}
		return abs($a * $xp + $b * $yp + $c) / $denominator;
	}

	/**
	 * @param array $vertices list of [x,y]
	 */
	function point_inside(array $vertices, $x, $y){

		$n = count($vertices);
		if ($n < 3){
			return false;
		}

		for ($i = 0; $i < $n; $i++){
			$x_i = $vertices[$i][0];
			$y_i = $vertices[$i][1];
			$x_j = $vertices[($i + 1) % $n][0];
			$y_j = $vertices[($i + 1) % $n][1];
			$x_k = $vertices[($i + 2) % $n][0];
			$y_k = $vertices[($i + 2) % $n][1];

			$cross = ($x_j - $x_i) * ($y - $y_i) - ($y_j - $y_i) * ($x - $x_i);
			$cross_ref = ($x_j - $x_i) * ($y_k - $y_i) - ($y_j - $y_i) * ($x_k - $x_i);

			if ($cross * $cross_ref < 0){
				return false;
			}
		}

		return true;
	}

	/**
	 * Black mask pixel = no paint.
	 */
	function mask_allows_paint($mask_gd, $x, $y){

		if ($x < 0 || $y < 0 || $x >= imagesx($mask_gd) || $y >= imagesy($mask_gd)){
			return true;
		}
		$rgb = imagecolorsforindex($mask_gd, imagecolorat($mask_gd, $x, $y));
		return !($rgb['red'] == 0 && $rgb['green'] == 0 && $rgb['blue'] == 0);
	}

	// -------------------------------------------------------------------------
	// Paths / load / save
	// -------------------------------------------------------------------------

	function ensure_cache_dir(){

		$dir = rtrim($GLOBALS['config']['upload_path'], '/\\').DIRECTORY_SEPARATOR.'imagemaker';
		if (!is_dir($dir)){
			mkdir($dir, 0755, true);
		}
		return $dir;
	}

	/**
	 * Absolute path for a relative key under upload_path (e.g. imagemaker/a_….png).
	 */
	function cache_absolute($relative){

		$relative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, ltrim((string)$relative, '/\\'));
		return rtrim($GLOBALS['config']['upload_path'], '/\\').DIRECTORY_SEPARATOR.$relative;
	}

	/**
	 * Resolve image argument to an absolute readable file path.
	 */
	function resolve_absolute($image){

		$image = (string)$image;
		if ($image === ''){
			return '';
		}
		if (is_file($image)){
			return $image;
		}

		// Already under upload_path as absolute-looking relative
		$under = $this->cache_absolute($image);
		if (is_file($under)){
			return $under;
		}

		// Strip accidental upload_url / upload_path prefix from stored values
		$up = str_replace('\\', '/', rtrim($GLOBALS['config']['upload_path'], '/\\')).'/';
		$norm = str_replace('\\', '/', $image);
		if (strpos($norm, $up) === 0){
			$under = $this->cache_absolute(substr($norm, strlen($up)));
			if (is_file($under)){
				return $under;
			}
		}

		return '';
	}

	/**
	 * Load GD resource from CMS key or filesystem path.
	 *
	 * @return resource|\GdImage|false
	 */
	function load_gd($image){

		$abs = $this->resolve_absolute($image);
		if ($abs === ''){
			return false;
		}

		$size = @getimagesize($abs);
		if ($size === false){
			return false;
		}
		$original_width = $size[0];
		$original_height = $size[1];

		$needed = (8 * $original_width * $original_height) * 3.5 + 10000000;
		$limit = str_replace(['G', 'M', 'K'], ['000000000', '000000', '000'], ini_get('memory_limit'));
		if ($limit > 0 && $limit < $needed){
			ini_set('memory_limit', (string)(int)$needed);
		}
		$limit = str_replace(['G', 'M', 'K'], ['000000000', '000000', '000'], ini_get('memory_limit'));
		if ($needed > $limit && $limit > 0){
			trigger_error('Not enough memory to work with image: needed='.$needed.' memory_limit='.$limit, E_USER_NOTICE);
			return false;
		}

		if (!function_exists('imagecreatetruecolor')){
			trigger_error('PHP GD not available', E_USER_WARNING);
			return false;
		}

		$imagetype = @exif_imagetype($abs);
		if ($imagetype == IMAGETYPE_JPEG){
			return imagecreatefromjpeg($abs);
		}
		if ($imagetype == IMAGETYPE_PNG){
			ob_start();
			$src = imagecreatefrompng($abs);
			ob_end_clean();
			if ($src){
				imagealphablending($src, false);
				imagesavealpha($src, true);
			}
			return $src;
		}

		return false;
	}
}
