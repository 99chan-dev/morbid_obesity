<table width="100%"><tr><td colspan="3">

{if $submit neq 1}
	<center>
	<table>
		{if $offset neq '' && $offset neq 0}
			<tr>
				<td colspan="3" align="center"><b>{t}50 Most Recent:{/t}</b></td>
			</tr>
		{/if}
		<tr>
			<td colspan="3">
				<table width="98%" >
					<tr>
						{if $admin eq 'allboards'}<td class="postblock" align="center">Del</td>{/if}
						<td class="postblock" align="center">File</td>
						<td class="postblock" align="center" width="80px">Size</td>
						<td class="postblock" align="center">Date</td>
						<td class="postblock" align="center">Board</td>
						<td class="postblock" align="center">Site</td>
					</tr>
					{foreach item=link from=$links name=linksloop}
						<tr>
							{if $admin eq 'allboards'}<td align="center">[<a href="?del={$link.id}">X</a>]</td>{/if}
							<td align="left">
								[<a href="{$link.url}" >{$link.file}</a>]
								{if $link.password neq ''}
									<br /><span style="color: rgb(204,87,17);">(Password: <input type="text" value="{$link.password}" size="25" />)</span>
								{/if}
							</td>
							<td align="right" width="100px">{$link.size}</td>
							<td align="center">{$link.date}</td>
							<td align="center">/<a href="{%KU_WEBPATH}/{$link.board}/" title="/{$link.board}/">{$link.board}</a>/</td>
							<td align="center"><a href="?{$url}site={$link.abbreviation}">{$link.abbreviation}</a></td>
						</tr>
					{/foreach}
				</table>
			</td>
		</tr>
	</table>
	</center>

	<hr />

	<table width="98%">
		<tr>
		<td align="left">
		<table class="pages" border="1">
		<tr>
			<td>{if $offset neq '' && $offset neq 0}
				<form action="?{$url}offset={$offset - 50}" onsubmit='location=this.action;return false' method="post">
					<input type="submit" value="{t}Previous{/t}" />
				</form>
			{else}
				{t}Previous{/t}
			{/if}
			</td>
			<td>{if $.foreach.linksloop.iteration eq 51}
				<form action="?{$url}offset={$offset + 50}" onsubmit='location=this.action;return false' method="post">
					<input type="submit" value="{t}Next{/t}" />
				</form>
			{else}
				{t}Next{/t}
			{/if}
			</td>
		</tr>
		</table>
		</td>
		</tr>
	</table>
{else}
<center><b>Link submission form:</b><br />
<form method="post" action="">
	<table cellpadding="1" cellspacing="1"><tbody><tr>
		<td class="postblock" align="left"><b>Links</b></td>
		<td><textarea name="links" cols="48" rows="4" wrap="soft"></textarea></td></tr>
		<tr><td class="postblock" align="left"><b>Password</b></td>
		<td><input name="password" type="text" size="48" accesskey="t" /><input value="Submit" accesskey="s" type="submit"></td>
	</tr></tbody></table>
</form>
</center>
<hr />
{/if}

</td></tr></table>