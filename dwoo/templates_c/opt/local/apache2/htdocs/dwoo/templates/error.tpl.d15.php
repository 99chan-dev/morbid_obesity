<?php
if (function_exists('Dwoo_Plugin_capitalize')===false)
	$this->getLoader()->loadPlugin('capitalize');
if (function_exists('smarty_block_t')===false)
	$this->getLoader()->loadPlugin('t');
ob_start(); /* template body */ ?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<title><?php echo KU_NAME;?></title>
<link rel="shortcut icon" href="<?php echo KU_WEBPATH;?>/favicon.ico" />
<link rel="stylesheet" type="text/css" href="<?php echo KU_BOARDSPATH;?>/css/menu_global.css" />
<?php 
$_loop0_data = (isset($this->scope["styles"]) ? $this->scope["styles"] : null);
if ($this->isArray($_loop0_data) === true)
{
	foreach ($_loop0_data as $tmp_key => $this->scope["-loop-"])
	{
		$_loop0_scope = $this->setScope(array("-loop-"));
/* -- loop start output */
?>
	<link rel="<?php if ($this->scope != (defined("KU_DEFAULTMENUSTYLE") ? KU_DEFAULTMENUSTYLE : null)) {
?>alternate <?php 
}?>stylesheet" type="text/css" href="<?php echo KU_WEBFOLDER;?>css/site_<?php echo $this->scope;?>.css" title="<?php echo Dwoo_Plugin_capitalize($this, $this->scope, false);?>" />
	<link rel="<?php if ($this->scope != (defined("KU_DEFAULTMENUSTYLE") ? KU_DEFAULTMENUSTYLE : null)) {
?>alternate <?php 
}?>stylesheet" type="text/css" href="<?php echo KU_WEBFOLDER;?>css/sitemenu_<?php echo $this->scope;?>.css" title="<?php echo Dwoo_Plugin_capitalize($this, $this->scope, false);?>" />
<?php 
/* -- loop end output */
		$this->setScope($_loop0_scope, true);
	}
}
?>


<style type="text/css">
body {
	width: 100% !important;
}
</style>
</head>
<body>
<h1 style="font-size: 3em;"><?php  if (!isset($_tag_stack)){ $_tag_stack = array(); } $_tag_stack[] = array(); $_block_repeat=true; smarty_block_t($_tag_stack[count($_tag_stack)-1], null, $this, $_block_repeat); while ($_block_repeat) { ob_start();?>Error<?php  $_block_content = ob_get_clean(); $_block_repeat=false; echo smarty_block_t($_tag_stack[count($_tag_stack)-1], $_block_content, $this, $_block_repeat); } array_pop($_tag_stack);?></h1>
<br />
<h2 style="font-size: 2em;font-weight: bold;text-align: center;">
<?php echo $this->scope["errormsg"];?>

</h2>
<?php echo $this->scope["errormsgext"];?>

<div style="text-align: center;width: 100%;position: absolute;bottom: 10px;">
<br />
<div class="footer" style="clear: both;">
	<div class="legal">	- <a href="http://kusabax.cultnet.net/" target="_top">kusaba x <?php echo KU_VERSION;?></a> -
</div>
</div>
</body>
</html><?php  /* end template body */
return $this->buffer . ob_get_clean();
?>