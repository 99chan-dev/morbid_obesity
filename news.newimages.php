<?php
/*
 * This file is part of kusaba.
 *
 * kusaba is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * kusaba is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * kusaba; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */
/**
 * News display, which is the first page shown when a user visits a chan's index
 *
 * Any news added by an administrator in the manage panel will show here, with
 * the newest entry on the top.
 *
 * @package kusaba
 */

// Require the configuration file
require 'config.php';
require KU_ROOTDIR . 'inc/functions.php';
require_once KU_ROOTDIR . 'lib/dwoo.php';
$dwoo_tpl = new Dwoo_Template_File(KU_TEMPLATEDIR . '/news.tpl');

$topads = $tc_db->GetOne("SELECT code FROM `" . KU_DBPREFIX . "ads` WHERE `position` = 'top' AND `disp` = '1'");
$botads = $tc_db->GetOne("SELECT code FROM `" . KU_DBPREFIX . "ads` WHERE `position` = 'bot' AND `disp` = '1'");

// - BEGIN MOD LAST PICS
$onlythread = false;// Enable this if you want to show only thread starting pictures (better graphic layout)
$nsfw = Array('b', 's', '34', 'd', 'di', 'do', 'fetish', 'h', 'hor', 'pure', 'men', 'y', 'cd');// Add/delete here your NSFW board
$shownsfwpics = false;// Show NSFW pics or a simple text saying "NSFW board"

$sqlquery = "SELECT ".KU_DBPREFIX."posts.id, parentid, file, file_type, thumb_w, thumb_h, ".KU_DBPREFIX."boards.name as name FROM 
`".KU_DBPREFIX."posts` INNER JOIN ".KU_DBPREFIX."boards ON ".KU_DBPREFIX."boards.id = boardid WHERE (`file_type` = 'jpg' OR `file_type` = 
'gif' OR `file_type` = 'png') AND `is_deleted` = 0";
if ($onlythread)
$sqlquery .= " AND `parentid` = 0";
$sqlquery .= " ORDER BY `timestamp` DESC LIMIT 4";

$results = $tc_db->GetAll($sqlquery);

$topads .= "<div class=content><table width='100%'><tr><td colspan=5 style='color:white'><blink>latest pictures n shit</blink> - <a href=news.php>Hide</a></td></tr><tr>" ."\n";

foreach ($results as $result) {
$real_parentid = ($result['parentid'] == 0) ? $result['id'] : $result['parentid'];
// $result['boardname'] = $tc_db->GetOne("SELECT `name` FROM `".KU_DBPREFIX."boards` WHERE `id` = '".$result['boardid']."'");

$topads .= '<td align="center">'."\n".'<a href="'. KU_WEBPATH . '/' . $result['name'] . '/res/'. $real_parentid . '.html#'. $result['id'] . 
'">'."\n";

if (in_array($result['name'], $nsfw)) {
if ($shownsfwpics)
$topads .= '<img src="'. KU_WEBPATH . '/' . $result['name'] . '/thumb/'. $result['file'] . 's.'. $result['file_type'] . '" width="'. 
$result['thumb_w'] . '" height="'. $result['thumb_h'] . '" border="0" />';
else
$topads .= '<img src="'. KU_WEBPATH . '/banners/logo"><br>NSFW - ' . $result['name'] . '';
} else
$topads .= '<img src="'. KU_WEBPATH . '/' . $result['name'] . '/thumb/'. $result['file'] . 's.'. $result['file_type'] . '" width="'. 
$result['thumb_w'] . '" height="'. $result['thumb_h'] . '" border="0" />';
$topads .= '</a>'."\n".'</td>'."\n";
}
$topads .= "</tr></table></div>";
// - END MOD LAST PICS

//NOTE: there might be wrong lines ends!
$dwoo_data->assign('topads', $topads);
$dwoo_data->assign('botads', $botads);


if (!isset($_GET['p'])) $_GET['p'] = '';

if ($_GET['p'] == 'faq') {
	$entries = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "front` WHERE `page` = 1 ORDER BY `order` ASC");
} elseif ($_GET['p'] == 'rules') {
	$entries = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "front` WHERE `page` = 2 ORDER BY `order` ASC");
} else {
	$entries = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "front` WHERE `page` = 0 ORDER BY `timestamp` DESC");
}

$styles = explode(':', KU_MENUSTYLES);

$dwoo_data->assign('styles', $styles);
$dwoo_data->assign('ku_webpath', getCWebPath());
$dwoo_data->assign('entries', $entries);

$dwoo->output($dwoo_tpl, $dwoo_data);
?>
