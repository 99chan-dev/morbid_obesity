<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en"> 
<head>
	<title>{$dwoo.const.KU_NAME}</title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

	{for style $styles}
				<link rel="{if $styles[$style] neq $dwoo.const.KU_DEFAULTMENUSTYLE}alternate {/if}stylesheet" type="text/css" href="{$dwoo.const.KU_WEBFOLDER}css/site_{$styles[$style]}.css" title="{$styles[$style]|capitalize}" />
	{/for}
<script type="text/javascript"><!--
	var style_cookie_site = "kustyle_site";
//--></script>
<link rel="shortcut icon" href="{$dwoo.const.KU_WEBFOLDER}favicon.ico" />
<script type="text/javascript" src="{%KU_WEBFOLDER}lib/javascript/gettext.js"></script>
<script type="text/javascript" src="{$dwoo.const.KU_WEBFOLDER}lib/javascript/kusaba.js"></script></head>
<body>
	<h1>{$dwoo.const.KU_NAME}</h1>
	{if $dwoo.const.KU_SLOGAN neq ''}<h3><i>{$dwoo.const.KU_SLOGAN}</h3></i>{/if}
	<img src="$ku_webpath}banners/big">
	
	<div class="menu" id="topmenu">
		{$topads}
		{strip}<ul>
			<li class="{if $dwoo.get.p eq ''}current {else}tab {/if}first">{if $dwoo.get.p neq ''}<a href="{$ku_webpath}news.php">{/if}{t}News{/t}{if $dwoo.get.p neq ''}</a>{/if}</li>
			<li class="{if $dwoo.get.p eq 'faq'}current{else}tab{/if}">{if $dwoo.get.p neq 'faq'}<a href="{$ku_webpath}news.php?p=faq">{/if}{t}FAQ{/t}{if $dwoo.get.p neq 'faq'}</a>{/if}</li>
			<li class="{if $dwoo.get.p eq 'rules'}current{else}tab{/if}">{if $dwoo.get.p neq 'rules'}<a href="{$ku_webpath}news.php?p=rules">{/if}{t}Rules{/t}{if $dwoo.get.p neq 'rules'}</a>{/if}</li>
		</ul>{/strip}
		<br />
	</div>
	<!-- Srsly twobutts if you had any idea how wasted I am right now you'd in your father's mouth. Michael Jackson was was less wasted than this when he died. //-->
	<div align="center">
		<iframe width="560" height="315" src="//www.youtube.com/embed/H6CviihdQ4Y?autoplay=1" frameborder="0" allowfullscreen></iframe>
	</div>
	<!-- I know inline styles are bad practice but I junked I ain't fucking lookin for the fuckin css files right now Fuck you. I decided against the inline styles. Still fuck you. //-->
	<div class="content" >I'd like for everyone to come into irc and personally thank wharrves for this wonderful video that will autoplay here until the sun explodes and kills us all. I'm fuckin wasted as shit.  I just nodded out. I hope you fucks are wasted ttoo. Wait, no I don't. Don't look at me like that motherfucker I'll total you. You fucking pussy faggot bitch fuckn cunt pussy fuckin bitch. Look at the ground motherfucker. Now give me your shoes. I SAID GIVE ME YOUR SHOES! See you in the showers loverboy. wait, wut fuckin ngr fgt . no fgt ngr. yes disliking Wendy's is now bannable offense. Go get some of them tasty nuggets. Then you prepare your anus. You know what's coming. ANAL UP FAGGOT punk bitch fuckin got a touch of the tar brush fuckin ewok lookin ain't got no fuckin hair on your floppy fuckin fhead fuckin French Canadian fuckin get the fuck out now bring me my fuckin drugs you fuckin jew bastards - duchess</p>
	</div>
	<!-- Fuck it, fuckin css. //-->
{foreach item=entry from=$entries}
	<div class="content">
		<h2><span class="newssub">{$entry.subject|stripslashes}{if $dwoo.get.p eq ''} by {if $entry.email neq ''}<a href="mailto:{$entry.email|stripslashes}">{/if}{$entry.poster|stripslashes}{if $entry.email neq ''}</a>{/if} - {$entry.timestamp|date_format:"%D @ %I:%M %p %Z"}{/if}</span>
		<span class="permalink"><a href="#{$entry.id}">#</a></span></h2>
		{$entry.message|stripslashes}
	</div><br />
{/foreach}
			<center> 
				{$botads}
			</center>
</div>
<a name="bottom"></a>
<p align="center">
<a href="javascript:dmcadick('/dmca/contactform.php')" class="dmca">DMCA</a></p>  
</body>
</html>
