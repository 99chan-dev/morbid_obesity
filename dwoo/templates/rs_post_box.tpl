<div align="center" class="postarea" >
	<a id="postbox"></a>
	<table border="0">
		<tr>
			<td>
				<table cellpadding="1" cellspacing="1">
					<tr>
						<td class="postblock" align="left">
							<b>{t}Search{/t}</b>
						</td>
						<td>
						<form method="get" action="">
							<input type="text" name="search" size="35" />
							<input type="submit" value="Submit" accesskey="s" />
						</form>
						</td>
					</tr>
					<tr>
						<td class="postblock" align="left">
							<b>{t}Boards{/t}</b>
						</td>
						<td align="left">
							<form method="get" action="">
								<select name="from">
									<option value="ALL">- {t}Select Board{/t} -</option>
									{foreach name=sections item=sect from=$boardlist}
										{foreach name=brds item=brd from=$sect}
											<option value="{$brd.name}">/{$brd.name}/ - {$brd.desc}</option>
										{/foreach}
									{/foreach}
								</select>
								<input type="submit" value="Go" />
							</form>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td align="left">
				<table border="0" cellpadding="0" cellspacing="0" >
					<tr>
						<td align="left">
							<ul style="font-size: 16px; line-height: 11px; margin-top: 0px; margin-bottom:0;padding-left: 1em;margin-left: 0;" > 
								<li><span style="font-size: 10.3px;font-family: sans-serif;">{t}Searches{/t}: 
									[<a href="?{$url}date">{t}Recent{/t}</a>] 
									[<a href="?{$url}size=biggest">{t}Biggest{/t}</a>] 
									[<a href="?{$url}size=smallest">{t}Smallest{/t}</a>] 
									[<a href="?{$url}date=oldest">{t}Oldest{/t}</a>]
									[<a href="?">X</a>]
								</span></li>
								<li><span style="font-size: 10.3px; font-family: sans-serif;">
									{t}Other{/t}: [<a href="?submit=1">{t}Submit{/t}</a>]
								</span>	</li>
							</ul>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</div>
