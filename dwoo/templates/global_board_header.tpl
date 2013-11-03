<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html{$htmloptions} xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>99chan</title>

<script type="text/javascript">
function dmcadick(URL) {
day = new Date();
id = day.getTime();
eval("page" + id + " = window.open(URL, '" + id + "', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=680,height=600,left = 380,top = 150');");
}
</script>
<style type="text/css">
.dmca {
	font-size: 10px;
}
</style>
<link rel="shortcut icon" href="{$cwebpath}/favicon.ico" />
{if $locale != 'en'}
	<link rel="gettext" type="application/x-po" href="{$cwebpath}/inc/lang/{$locale}/LC_MESSAGES/kusaba.po" />
{/if}
<meta http-equiv="Pragma" content="no-cache" />
<meta http-equiv="cache-control" content="no-cache" />
<meta http-equiv="expires" content="Sat, 17 Mar 1990 00:00:01 GMT" />
<meta http-equiv="Content-Type" content="text/html;charset={%KU_CHARSET}" />
<script type="text/javascript" src="{$cwebpath}/lib/javascript/gettext.js"></script>
<script type="text/javascript" src="/qwoo.js"></script>
