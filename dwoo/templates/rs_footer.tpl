<br />
{if $boardlist}
	<div class="navbar">
	{if %KU_GENERATEBOARDLIST}
		{foreach name=sections item=sect from=$boardlist}
			[
			{foreach name=brds item=brd from=$sect}
				<a title="{$brd.desc}" href="{%KU_BOARDSFOLDER}{$brd.name}/">{$brd.name}</a>{if $.foreach.brds.last}{else} / {/if}
			{/foreach}
			]
		{/foreach}
	{else}
		{if is_file($boardlist)}
			{include $boardlist}
		{/if}
	{/if}
	</div>
{/if}
<br />
<div class="footer" style="clear: both;">
	{if $botads neq ''}
		<div class="content ads">
			<center> 
				{$botads}
			</center>
		</div>
	{/if}
</div>