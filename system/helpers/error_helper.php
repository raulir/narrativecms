<?php

/**
	severity - 1 = 'Warning', 2 = 'Error'
 */
function _show_error($severity, $message, $filepath = false, $line = false) {
	
	if (is_numeric($severity)){
		$severity = ($severity == 1 ? 'Warning' : $severity) == 2 ? 'Error' : 'Fatal';
	}

	if ($filepath){
		$filepath = str_replace('\\', '/', $filepath);
	
		if (false !== strpos($filepath, '/'))	{
			$x = explode('/', $filepath);
			$filepath = $x[count($x)-2].'/'.end($x);
		}
	}

	print('<pre style="background-color: white; color: black; display: block; border: 0.1rem solid red; padding: 1.0rem; font-size: 0.8rem;
		line-height: 0.9rem; letter-spacing: 0; font-family: monospace; text-align: left;">');
	print('<span style="font-size: 1.3rem; ">An error was encountered</span>'."\n\n");
	
	print('Severity: '.$severity."\n");
	print('Message: '.$message."\n");
	if ($filepath) print('Filename: '.$filepath."\n");
	if ($line) print('Number: '.$line."\n");

	print('</pre>');

}
