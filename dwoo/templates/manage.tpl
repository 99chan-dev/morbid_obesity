<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>{t}Manage Boards{/t}</title>
	{for style $styles}
				<link rel="{if $styles[$style] neq $dwoo.const.KU_DEFAULTMENUSTYLE}alternate {/if}stylesheet" type="text/css" href="{$dwoo.const.KU_WEBFOLDER}css/site_{$styles[$style]}.css" title="{$styles[$style]|capitalize}" />
	{/for}
<script type="text/javascript"><!--
	var style_cookie_site = "kustyle_site";
//--></script>  
<link rel="shortcut icon" href="{$dwoo.const.KU_WEBFOLDER}favicon.ico" />
<script type="text/javascript" src="{%KU_WEBFOLDER}lib/javascript/gettext.js"></script>
<script type="text/javascript" src="{$dwoo.const.KU_WEBFOLDER}lib/javascript/kusaba.js"></script></head>  
<link rel="shortcut icon" href="{%KU_WEBPATH}/favicon.ico" />
</head>
<body style="min-width: 600px; padding: 1em 20px 3em 20px;">
{$includeheader}
<div id="main">
	<div id="contents">
		{$page}
	</div>
</div>	
{$footer}

<style media="screen" type="text/css">  
html, body {
	background: #000000;
color:#909090;
}
* {
font-family: "Trebuchet MS", Tahoma, Verdana, Arial, sans-serif;
font-size: 10pt;
}
input, textarea {
background-color: #101010;
color: #ffffff;
border: 1px solid #202020;
}
.unkfunc {
background:inherit;
color:#a9ffa9;
}
a {
background:inherit;
color:white;
text-decoration: none;
cursor: pointer;
}
a:visited {
background:inherit;
color:white;
}
a:hover {
color: #A0A0A0;
background:inherit;
text-decoration:underline;
}
a.quotelink {
background:inherit;
color:#ffffff;
}
.catalogmode {
background: #333333;
color: #ffffff;
}
.logo {
clear:both;
text-align:center;
background:inherit;
font-size:24pt;
color: #ffffff;
width:100%;
font-weight: bold;
}
.postarea {
background:inherit;
}
.postblock {
background: #202020;
border: 1px solid #303030;
color: #ffffff;
font-weight: bold;
padding: 2px 5px 2px 5px;
}
.footer {
text-align:center;
font-size:12px;
}
.unkfunc {
background:inherit;
color:#909090;
}
.filesize {
text-decoration:none;
}
.filetitle {
background:inherit;
color:#606060;
font-weight:800;
}
.postername {
background:inherit;
font-size:11pt;
color:#505050;
font-weight:bold;
}
.postertrip {
background:inherit;
color:#808080;
}
.oldpost {
background:inherit;
color:#404040;
font-weight:800;
}
.omittedposts {
background:inherit;
color:#707070;
}
.reply {
background:#202020;
color:#A0A0A0;
border:1px solid #101010;
}
.replyhl {
background:#000000;
color:#707070;
}
.doubledash {
vertical-align:top;
clear:both;
float:left;
}
.replytitle {
background:inherit;
font-size:18px;
color:#000000;
font-weight:800;
}
.commentpostername {
background:inherit;
font-size:11pt;
color:#505050;
font-weight: bold;
}
a.quotejs {
color:#909090;
text-decoration: none;
}
a.quotejs:hover {
font-weight:bold;
}
.adminbar {
text-align: left;
}
#watchedthreads {
background-color: #000000 !important;
border: 1px solid #303030 !important;
border-top: 0px none !important;
}
.thumb { cursor: pointer; }

</style>  
</body>
</html>