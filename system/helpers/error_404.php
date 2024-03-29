<!DOCTYPE html>
<html lang="en">
<head>
<title>404 Page Not Found</title>
<meta http-equiv="refresh" content="5;URL=<?php print($GLOBALS['config']['base_url']); ?>">
<style type="text/css">

::selection{ background-color: #E13300; color: white; }
::moz-selection{ background-color: #E13300; color: white; }
::webkit-selection{ background-color: #E13300; color: white; }

body {
	background-color: #fff;
	margin: 40px;
	font: 1.3rem/20px normal Helvetica, Arial, sans-serif;
	color: #4F5155;
}

a {
	color: #003399;
	background-color: transparent;
	font-weight: normal;
}

h1 {
	color: #444;
	background-color: transparent;
	border-bottom: 0.1rem solid #D0D0D0;
	font-size: 1.9rem;
	font-weight: normal;
	margin: 0 0 1.4rem 0;
	padding: 1.4rem 10.5rem 10px 10.5rem;
}

code {
	font-family: Consolas, Monaco, Courier New, Courier, monospace;
	font-size: 12px;
	background-color: #f9f9f9;
	border: 0.1rem solid #D0D0D0;
	color: #002166;
	display: block;
	margin: 1.4rem 0 1.4rem 0;
	padding: 12px 10px 12px 10px;
}

#container {
	margin: 10px;
	border: 0.1rem solid #D0D0D0;
	-webkit-box-shadow: 0 0 0.8rem #D0D0D0;
}

p {
	margin: 12px 10.5rem 12px 10.5rem;
}
</style>
</head>
<body>
	<div id="container">
		<h1><?php echo $heading; ?></h1>
		<?php echo $message; ?><br>
		<br>
		&nbsp;&nbsp;&nbsp;&nbsp;Redirecting in 5 seconds<br>
		<br>
	</div>
</body>
</html>