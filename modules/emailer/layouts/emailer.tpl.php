<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <!--[if gte mso 15]>
    <xml>
    <o:OfficeDocumentSettings>
    <o:AllowPNG/>
    <o:PixelsPerInch>96</o:PixelsPerInch>
    </o:OfficeDocumentSettings>
    </xml>
    <![endif]-->
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Emailer</title>
	<style type="text/css">

		img,a img{
			border:0;
			outline:none;
			text-decoration:none;
		}
		body,#bodyTable,#bodyCell{
			height:100%;
			margin:0;
			padding:0;
			width:100%;
		}
		#outlook a{
			padding:0;
		}
		img{
			-ms-interpolation-mode:bicubic;
		}
		table{
			mso-table-lspace:0pt;
			mso-table-rspace:0pt;
		}
		.ReadMsgBody{
			width:100%;
		}
		.ExternalClass{
			width:100%;
		}
		p,a,li,td,blockquote{
			mso-line-height-rule:exactly;
		}
		a[href^=tel],a[href^=sms]{
			color:inherit;
			cursor:default;
			text-decoration:none;
		}
		p,a,li,td,body,table,blockquote{
			-ms-text-size-adjust:100%;
			-webkit-text-size-adjust:100%;
		}

@font-face {
	font-family: asap;    
	font-style: normal;    
	font-weight: normal;    
	src: url(https://uamh.uk/modules/cms/css/asap/asap.ttf) format('truetype');
}
@font-face {
	font-family: source;    
	font-style: normal;    
	font-weight: normal;    
	src: url(https://uamh.uk/modules/cms/css/source/source.ttf) format('truetype');
}
@font-face {
	font-family: source;    
	font-style: normal;    
	font-weight: bold;    
	src: url(https://uamh.uk/modules/cms/css/source/source_bold.ttf) format('truetype');
}
@font-face {
	font-family: source;    
	font-style: normal;    
	font-weight: 100;    
	src: url(https://uamh.uk/modules/cms/css/source/source_light.ttf) format('truetype');
}

.font_asap {
	font-family: asap,Arial,Helvetica,sans-serif;
}
.font_source{
	font-family: source,Arial,Helvetica,sans-serif;
}
		
</style></head>
<body class="font_source" align="center" bgcolor="white" style="padding: 0; margin: 0; text-align: center; ">

	<?= get_position('header', $data) ?>

	<?= get_position('main', $data) ?>

	<?= get_position('footer', $data) ?>
	
</body>
</html>