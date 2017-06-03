<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_panel_export extends MY_Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}
	
	// delete directory
	function rrmdir($dir) {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (is_dir($dir."/".$object))
						rrmdir($dir."/".$object);
						else
							unlink($dir."/".$object);
				}
			}
			rmdir($dir);
		}
	}
	
	function add_image($filename, $folder){
		
		$image_data = $this->cms_image_model->get_cms_image_by_filename($filename);
			
		if(empty($this->data['_images'][$image_data['filename']])){
		
			if (empty($image_data['hash'])){
					
				$hash = sha1_file($GLOBALS['config']['upload_path'].$image_data['filename']);
					
				// update image hash in db
				$this->cms_image_model->update_cms_image($image_data['filename'], ['hash' => $hash, ]);
					
			} else {
					
				$hash = $image_data['hash'];
		
			}
		
			$export_filename = substr($hash, 0, 8).'_'.$image_data['name'].'.'.pathinfo($filename, PATHINFO_EXTENSION);
		
			$this->data['_images'][$image_data['filename']] = [
					'hash' => $hash,
					'export_filename' => $export_filename,
					'category' => $image_data['category'],
					'name' => $image_data['name'],
			];
		
			// copy image too
			copy($GLOBALS['config']['upload_path'].$image_data['filename'], $folder.'/'.$export_filename);
			
			return filesize($folder.'/'.$export_filename);
				
		}
		
		return 0;
		
	}

	function add_file($filename, $folder){
		
		$file_data = $this->cms_file_model->get_cms_file_by_filename($filename);
			
		if(empty($this->data['_files'][$file_data['filename']])){
		
			if (empty($file_data['hash'])){
					
				$hash = sha1_file($GLOBALS['config']['upload_path'].$file_data['filename']);
					
				// update image hash in db
				$this->cms_file_model->update_cms_file($file_data['filename'], ['hash' => $hash, ]);
					
			} else {
					
				$hash = $file_data['hash'];
		
			}
		
			$export_filename = substr($hash, 0, 8).'_'.$file_data['name'].'.'.pathinfo($filename, PATHINFO_EXTENSION);
		
			$this->data['_images'][$file_data['filename']] = [
					'hash' => $hash,
					'export_filename' => $export_filename,
					'name' => $file_data['name'],
			];
		
			// copy file too
			copy($GLOBALS['config']['upload_path'].$file_data['filename'], $folder.'/'.$export_filename);
			
			return filesize($folder.'/'.$export_filename);
		
		}
		
		return 0;
		
	}
	
	function panel_action($params){

		$this->load->model('cms_page_panel_model');
		$this->load->model('cms_panel_model');
		$this->load->model('cms_image_model');
		$this->load->model('cms_file_model');
		
		$do = $this->input->post('do');

		if ($do == 'cms_page_panel_export'){
			
			$start_time = microtime(true);
			 
			$cms_page_panel_id = $this->input->post('export_id');
			
			// get original data
			$this->data = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
			$panel_structure = $this->cms_panel_model->get_cms_panel_definition($this->data['panel_name']);
			 
			// set changes
			$this->data['show'] = 0;
			if ($this->data['page_id'] == 999999) $this->data['page_id'] = 0;
			
			// create folder
			$folder = $GLOBALS['config']['base_path'] . 'cache/'.date('ymd').'__'.str_replace('/', '_', $this->data['panel_name']).'__'.$cms_page_panel_id.'__'.
					trim(substr(preg_replace('/[ _]+/', '_', preg_replace('/[^0-9a-zA-Z ]/', '', $this->data['title'])), 0, 24), '_');
			
			$this->rrmdir($folder);
			mkdir($folder);
			
			$params['stats']['images']['count'] = 0;
			$params['stats']['images']['size'] = 0;
			$params['stats']['files']['count'] = 0;
			$params['stats']['files']['size'] = 0;
						
			// add images and files
			foreach($panel_structure as $struct){
				if ($struct['type'] == 'image' && !empty($this->data[$struct['name']])){
					
					$filesize = $this->add_image($this->data[$struct['name']], $folder);
					$params['stats']['images']['count'] += 1;
					$params['stats']['images']['size'] += $filesize;
										
				} else if ($struct['type'] == 'file' && !empty($this->data[$struct['name']])){
					
					$filesize = $this->add_file($this->data[$struct['name']], $folder);
					$params['stats']['files']['count'] += 1;
					$params['stats']['files']['size'] += $filesize;
						
				} else if ($struct['type'] == 'repeater' && !empty($this->data[$struct['name']])) {

					foreach($this->data[$struct['name']] as $rdata){
						
						foreach($struct['fields'] as $rstruct){
							
							if ($rstruct['type'] == 'image' && !empty($rdata[$rstruct['name']])){
									
								$filesize = $this->add_image($rdata[$rstruct['name']], $folder);
								$params['stats']['images']['count'] += 1;
								$params['stats']['images']['size'] += $filesize;
								
							} else if ($rstruct['type'] == 'file' && !empty($rdata[$rstruct['name']])){
									
								$filesize = $this->add_file($rdata[$rstruct['name']], $folder);
								$params['stats']['files']['count'] += 1;
								$params['stats']['files']['size'] += $filesize;
								
							}
							
						}
						
					}
					
				}
			}
			
			// put data to folder
			$data_json = json_encode($this->data, JSON_PRETTY_PRINT);
			file_put_contents($folder.'/data.json', $data_json);
			
			$params['stats']['time']['data'] = microtime(true) - $start_time;
				
			// compress
			if (file_exists($folder.'.zip')){
				unlink($folder.'.zip');
			}
			$zip = new ZipArchive();
			
			if ($zip->open($folder.'.zip', ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE) !== TRUE) {
				print('An error occurred');
			}
			
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder.'/'));
    		$files = array_keys(iterator_to_array($iterator, true));

    		foreach ($files as $file) {
    			if (is_file($file)){
        			$zip->addFile($file, pathinfo($file, PATHINFO_FILENAME).'.'.pathinfo($file, PATHINFO_EXTENSION));
    			}
    		}

    		$zip->close();
    		
    		$params['stats']['time']['compress'] = round((microtime(true) - $params['stats']['time']['data'] - $start_time) * 1000);
    		$params['stats']['time']['data'] = round($params['stats']['time']['data'] * 1000);
    		
    		// stats
    		$params['stats']['panels']['count'] = 1;
    		$params['stats']['panels']['size'] = filesize($folder.'/data.json');
    		
    		$params['stats']['total']['size'] = $this->makesize($params['stats']['panels']['size'] + $params['stats']['images']['size'] + $params['stats']['files']['size']);
    		$params['stats']['total']['compressed'] = $this->makesize(filesize($folder.'.zip'));
    		
    		$params['stats']['panels']['size'] = $this->makesize($params['stats']['panels']['size']);
    		$params['stats']['images']['size'] = $this->makesize($params['stats']['images']['size']);
    		$params['stats']['files']['size'] = $this->makesize($params['stats']['files']['size']);
     		
    		$this->rrmdir($folder);
    		
    		$params['filename'] = pathinfo($folder.'.zip', PATHINFO_FILENAME);
			
			return $params;
			
			 
		}
		
		return $params;

	}
	
	function makesize($size){
		
		if ($size < 512){
		
			return $size.' B';
		
		}
			
		$size = $size / 1024;
		
		if ($size < 100){
			return round($size, 1).' kB';
		} else if ($size < 512){
			return round($size).' kB';
		}
		
		$size = $size / 1024;
				
		if ($size < 100){
			return round($size, 1).' MB';
		} else if ($size < 512){
			return round($size).' MB';
		}
		
		$size = $size / 1024;
		
		return round($size, 1).' GB';
		
	}

}
