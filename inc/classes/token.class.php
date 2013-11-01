
<?php /*
 * This file is part of kusaba.
 *
 * kusaba is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * kusaba is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * kusaba; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * +------------------------------------------------------------------------------+
 * Manage Class
 * +------------------------------------------------------------------------------+
 * Manage functions, along with the pages available
 * +------------------------------------------------------------------------------+
 */
class Manage {

	/* Show the header of the manage page */
	function Header() {
		global $tc_db, $smarty, $tpl_page;
		
		if (is_file(KU_ROOTDIR . 'inc/pages/modheader.html')) {
			$tpl_includeheader = file_get_contents(KU_ROOTDIR . 'inc/pages/modheader.html');
		} else {
			$tpl_includeheader = '';
		}
		
		$smarty->assign('includeheader', $tpl_includeheader);
	}
	
	/* Show the footer of the manage page */
	function Footer() {
		global $tc_db, $smarty, $tpl_page;
		
		$smarty->assign('page', $tpl_page);
		
		$board_class = new Board('');
		$smarty->assign('footer', $board_class->Footer(true));
		
		$smarty->display('manage.tpl');
	}
	
	/* Validate the current session */
	function ValidateSession($is_menu = false) {
		global $tc_db, $smarty, $tpl_page;
	
		if (isset($_SESSION['manageusername']) && isset($_SESSION['managepassword'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `username` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . mysql_real_escape_string($_SESSION['manageusername']) . "' AND `password` = '" . mysql_real_escape_string($_SESSION['managepassword']) . "' LIMIT 1");
			if (count($results) == 0) {
				session_destroy();
				exitWithErrorPage(_gettext('Invalid session.'), '<a href="manage_page.php">' . _gettext('Log in again.') . '</a>');
			}
			
			$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `lastactive` = " . time() . " WHERE `username` = '" . mysql_real_escape_string($_SESSION['manageusername']) . "' LIMIT 1");		
			
			return true;
		} else {
			if (!$is_menu) {
				$this->LoginForm();
				die($tpl_page);
			} else {
				return false;
			}
		}
	}
	
	/* Show the login form and halt execution */
	function LoginForm() {
		global $tc_db, $smarty, $tpl_page;
		
		if (file_exists(KU_ROOTDIR . 'inc/pages/manage_login.html')) {
			$tpl_page .= file_get_contents(KU_ROOTDIR . 'inc/pages/manage_login.html');
		}
	}
	
	/* Check login names and create session if user/pass is correct */
	function CheckLogin() {
		global $tc_db, $smarty, $tpl_page, $action;
		
		$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "loginattempts` WHERE `timestamp` < '" . (time() - 1200) . "'");
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `ip` FROM `" . KU_DBPREFIX . "loginattempts` WHERE `ip` = '" . $_SERVER['REMOTE_ADDR'] . "' LIMIT 6");
		if (count($results) > 5) {
			exitWithErrorPage(_gettext('System lockout'), _gettext('Sorry, because of your numerous failed logins, you have been locked out from logging in for 20 minutes.  Please wait and then try again.'));
		} else {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `username` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . mysql_real_escape_string($_POST['username']) . "' AND `password` = '" . md5($_POST['password']) . "' AND `type` != 3 AND `suspended` != 1 LIMIT 1");
			if (count($results) > 0) {
				$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "loginattempts` WHERE `ip` < '" . $_SERVER['REMOTE_ADDR'] . "'");
				$_SESSION['manageusername'] = $_POST['username'];
				$_SESSION['managepassword'] = md5($_POST['password']);
				$this->SetModerationCookies();
				$action = 'posting_rates';
				management_addlogentry(_gettext('Logged in (') . $_SERVER['REMOTE_ADDR'] . ')', 1);
				die('<script type="text/javascript">top.location.href = \'' . KU_CGIPATH . '/manage.php\';</script>');
			} else {
				$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "loginattempts` ( `username` , `ip` , `timestamp` ) VALUES ( '" . mysql_real_escape_string($_POST['username']) . "' , '" . $_SERVER['REMOTE_ADDR'] . "' , '" . time() . "' )");
				if (eregi('[^A-Za-z0-9]', $_POST['username'])) {
					die();
				}
				$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "modlog` ( `entry` , `user` , `category` , `timestamp` ) VALUES ('Failed login attempt (" . mysql_real_escape_string($_POST['username']) . ")' , '" . $_SERVER['REMOTE_ADDR'] . "' , '0' , '". time() ."')");
				exitWithErrorPage(_gettext('Incorrect username/password.'));
			}
		}
	}
	
	/* Set mod cookies for boards */
	function SetModerationCookies() {
		global $tc_db, $smarty, $tpl_page;
		
		if (isset($_SESSION['manageusername'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `boards` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . mysql_real_escape_string($_SESSION['manageusername']) . "' LIMIT 1");
			if ($this->CurrentUserIsAdministrator() || $results[0][0] == 'allboards') {
				$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards`");
				foreach ($resultsboard as $lineboard) {					
					setcookie("kumod", "yes", time() + 3600, KU_BOARDSFOLDER . $lineboard['name'] . "/", KU_DOMAIN);
					$_SESSION['modonly'] = 1;
				}
			} else {
				if ($results[0][0] != '') {
					foreach ($results as $line) {
						$array_boards = explode('|', $line['boards']);
					}
					foreach ($array_boards as $this_board_name) {								
						setcookie("kumod", "yes", time() + 3600, KU_BOARDSFOLDER . $this_board_name . "/", KU_DOMAIN);
						$_SESSION['modonly'] = 1;
					}
				}
			}
		}
	}
	/* Log current user out */
	function Logout() {
		global $tc_db, $smarty, $tpl_page;
		
		$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards`");
		foreach ($resultsboard as $lineboard) {
			setcookie('kumod', '', 0, KU_BOARDSFOLDER . $lineboard['name'] . '/', KU_DOMAIN);
		}
		
		session_destroy();
		unset($_SESSION['manageusername']);
		unset($_SESSION['managepassword']);
		die('<script type="text/javascript">top.location.href = \'' . KU_CGIPATH . '/manage.php\';</script>');
	}
	function Security() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		$tpl_page .= '<h2>' . ucwords(_gettext('Security')) . '</h2><br>';
		if(isset($_POST['submit'])) {
			if(is_numeric($_POST['lock'])) {
				if($_POST['lock'] == 0) {
					$boards = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
					foreach($boards as $board) {
						$tc_db->Execute("UPDATE `". KU_DBPREFIX ."boards` SET `locked` = '0' WHERE `name` = '".$board['name']."'");
					}
					$tpl_page .= "All boards unlocked<br/><br/>";
				}
				elseif($_POST['lock'] == 1) {
					$boards = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
					foreach($boards as $board) {
						$tc_db->Execute("UPDATE `". KU_DBPREFIX ."boards` SET `locked` = '1' WHERE `name` = '".$board['name']."'");
					}
					$tpl_page .= "All boards locked<br/><br/>";
				}
				else {
					$tpl_page .= "What are you even doing?<br/><br/>";
				}
			}
			if(is_numeric($_POST['fap'])) {
				if($_POST['fap'] == 0) {
					$boards = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
					foreach($boards as $board) {
						$tc_db->Execute("UPDATE `". KU_DBPREFIX ."boards` SET `enablefaptcha` = '0' WHERE `name` = 
'".$board['name']."'");
					}
					$tpl_page .= "Faptchas removed<br/><br/>";
				}
				elseif($_POST['fap'] == 1) {
					$boards = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
					foreach($boards as $board) {
						$tc_db->Execute("UPDATE `". KU_DBPREFIX ."boards` SET `enablefaptcha` = '1' WHERE `name` = '".$board['name']."'");
					}
					$tpl_page .= "Faptchas enabled<br/><br/>";
				}
				else {
					$tpl_page .= "What are you even doing?<br/><br/>";
				}
			}
		}
		$tpl_page .= 'Enter 1 to set, 0 to remove.<br/><br/><form action="?action=security" method="post"><label for="lock">Lock all boards:</label><input type="text" name="lock"/><br/><label for="fap">Faptcha all boards:</label><input type="text" name="fap"/><br/><input type="submit" name="submit" value="k"/></form>';
	}
	/*
	 * +------------------------------------------------------------------------------+
	 * Manage pages
	 * +------------------------------------------------------------------------------+
	 */    
    function appeals() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
$i = 0;
		$tpl_page .= '<h2>' . ucwords(_gettext('Appeals')) . '</h2><br>';
		$appeals = $tc_db->GetAll("SELECT * FROM `".KU_DBPREFIX."appeal` WHERE `status` = '1'");
		if(count($appeals) > 1) {
			$tpl_page .= '<div style="width: 780px; margin: 0 auto"><table width="780" cellpadding="3" align="center" style="border: 1px solid"><tr style="background: #AFDCEC"><th>IP</th><th>Reason</th><th>Appeal</th><th>Moderator\'s Note</th><th>Banned on</th><th>Boards</th><th>Expires</th><th>By</th><th>Unban</th><th>Deny</th></tr>';
		foreach($appeals as $appeal) {
		$results = $tc_db->GetAll("SELECT * FROM `".KU_DBPREFIX."banlist` WHERE `ipmd5` = '" . md5($appeal['ip']) . "' LIMIT 1");
			if (count($results)>0) {
				foreach($results AS $line) {
					if($appeal['ip'] != '') {
						$i++;
						$tpl_page .= '<tr><td>'.$appeal['ip'] .  '</td>' . 
							'<td>' . $line['reason'] . "</td>" .
							'<td>' .		$appeal['appeal'] . "</td>" .
							'<td>' .		$line['note'] . "</td>" .
							'<td>' . date("F j, Y, g:i a", $line['at']) . "</td><td>";
							$boards = explode("|",$line['boards']);
							if($line['boards'] == '') {
								$tpl_page .= 'All boards';
							}
							else {
								foreach($boards as $board) {
									$tpl_page .= '/'.$board . "/ ";
								}
							}
							$tpl_page .= '</td>';
							if($line['until'] == 0) {
								$tpl_page .= '<td style="text-align: center">Never</td>';
							}
							else {
								$tpl_page .= '<td style="text-align: center">' . date("F j, Y, g:i a", $line['until']). "</td>";
							}
							$tpl_page .= '<td style="text-align: center">' . $line['by'] . "</td>" .
							'<td style="text-align: center"><a href="' . KU_CGIPATH . '/manage_page.php?action=bans&sm=sm&delban=' . $line['id'] . '&appeal='.$appeal['id'].'">Unban</a></td>' .
							'<td style="text-align: center"><a href="' . KU_CGIPATH . '/manage_page.php?action=bans&sm=sm&denyappeal=' . $line['id'] . '&appeal='.$appeal['id'].'">Deny</a></td>';
							$tpl_page .= '</tr>';
					}
				}
			}
		}
		}
		$tpl_page .= '<br style="clear: both"/></table></div>';
		  if($i == 0) {
                        $tpl_page .= 'No appeals need reviewing';
                }

	}	 
	function addmodule() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		$tpl_page .= '<h2>' . ucwords(_gettext('Module Editor')) . '</h2><br>';
		if(isset($_GET["delete"])) {
			if(!is_numeric($_GET['delete'])) {
				exitWithErrorPage('Invalid ID');
			}
			if($tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "module` WHERE `id` = '".$_GET['delete']."'")) {
				$tpl_page .= '<b>Module deleted</b><br/><br/>';
			}
			else {
				exitWithErrorPage("Failed to delete" . mysql_error());
			}
		}
		if(isset($_GET["edit"])) {
			if(isset($_POST['trigger']) && isset($_POST['html']) && isset($_POST['submit'])) {
				if(!is_numeric($_POST['id'])) {
					exitWithErrorPage('Invalid ID');
				}
						$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
						$module_boards = array();
						foreach ($results as $line) {
							$module_boards = array_merge($module_boards, array($line['name']));
						}
						$module_changed_boards = array();
						$module_new_boards = array();
						while (list($postkey, $postvalue) = each($_POST)) {
							if (substr($postkey, 0, 10) == "modulefrom") {
								$module_changed_boards = array_merge($module_changed_boards, array(substr($postkey, 10)));
							}
						}
						while (list(, $module_thisboard_name) = each($module_boards)) {
							if (in_array($module_thisboard_name, $module_changed_boards)) {
								$module_new_boards = array_merge($module_new_boards, array($module_thisboard_name));
							}
						}
						if ($module_new_boards == array()) {
							exitWithErrorPage(_gettext('Please select a board.'));
						}
						$module_boards = implode('|', $module_new_boards);
				if($tc_db->Execute("UPDATE`" . KU_DBPREFIX . "module` SET `trigger` = '".mysql_real_escape_string($_POST['trigger'])."', `html` = '".mysql_real_escape_string($_POST['html'])."', `boards` = '".mysql_real_escape_string($module_boards)."' WHERE `id` = '".$_POST['id']."' ")) {
					$tpl_page .= '<b>Module edited</b><br/><br/>';
				}
				else {
					die(mysql_error());
				}
			}
			else {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "module` WHERE `id` = '".$_GET['edit']."'");
			if (count($results) > 0) {
				foreach($results as $line) {
					$tpl_page .= '<div>Editing module '.$line['id'].'.</div><br/><form action="?action=addmodule&edit" method="post"><input type="hidden" name="id" value="'.$line['id'].'"/><label for="trigger">Trigger:</label><input type="text" name="trigger" value="'.$line['trigger'].'"/><br/><label for="html">HTML:</label><textarea name="html" rows="5" cols="50">'.$line['html'].'</textarea>';
												$tpl_page .='<label>'._gettext('Boards').':</label><br>';
							
							$array_boards = array();
							$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
							foreach ($resultsboard as $lineboard) {
								$array_boards = array_merge($array_boards, array($lineboard['name']));
							}
							foreach ($array_boards as $this_board_name) {
								$tpl_page .= '<label for="module' . $this_board_name . '">' . $this_board_name . '</label><input type="checkbox" name="modulefrom' . $this_board_name . '" ';
								if (in_array($this_board_name, explode("|", $line['boards'])) && explode("|", $line['boards']) != '') {
									$tpl_page .= 'checked ';
								}
								$tpl_page .= '><br>';
							}
					$tpl_page .= '<input type="submit" name="submit" value="Edit"/></form><br/>';
				}
			}
			else {
				exitWithErrorPage('No module found');
			}
			}
		}
		if($_GET['do'] == "upload") {
		//die('Uploading disabled');
		if(isset($_POST['submit'])) {
if ($_FILES["file"]["error"] > 0) {
    die($_FILES["file"]["error"]);
   }
  else {
	if (!is_dir("module_images")) {
		mkdir("module_images", 0755);
	}
    if (file_exists("module_images/" . $_FILES["file"]["name"]))
      {
     $tpl_page .= $_FILES["file"]["name"] . " already exists.<br/><br/> ";
      }
    else
      {
	  $filetype = substr($_FILES["file"]["name"], -3, strlen($_FILES["file"]["name"]));
	  if($filetype == 'jpg' || $filetype == 'png' || $filetype == 'gif') {
		  move_uploaded_file($_FILES["file"]["tmp_name"], "module_images/" . $_FILES["file"]["name"]);
		  $tpl_page .= 'Uploaded to <a href="' . "module_images/" . $_FILES["file"]["name"] . '">/module_images/ '. $_FILES["file"]["name"] .'</a><br/><br/>';
	   }
	   else {
			exitWithErrorPage('Invalid filetype');
		}
      }
    }


		}
		$tpl_page .= '<form action="?action=addmodule&do=upload" method="post" enctype="multipart/form-data">
<label for="file">Filename:</label>
<input type="file" name="file" id="file" /> 
<br />
<input type="submit" name="submit" value="Submit" />
</form>';
		}
		if($_GET['do'] == "add") {
		if(empty($_POST['trigger']) || empty($_POST['html'])) {
			exitWithErrorPage("Fill in all values");
		}
						$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
						$module_boards = array();
						if (isset($_POST['addfromall'])) {
							foreach ($results as $line) {
								$module_new_boards[] = $line['name'];
							}
						}
						else {
						foreach ($results as $line) {
							$module_boards = array_merge($module_boards, array($line['name']));
						}
						$module_changed_boards = array();
						$module_new_boards = array();
						while (list($postkey, $postvalue) = each($_POST)) {
							if (substr($postkey, 0, 10) == "modulefrom") {
								$module_changed_boards = array_merge($module_changed_boards, array(substr($postkey, 10)));
							}
						}
						while (list(, $module_thisboard_name) = each($module_boards)) {
							if (in_array($module_thisboard_name, $module_changed_boards)) {
								$module_new_boards = array_merge($module_new_boards, array($module_thisboard_name));
							}
						}
						if ($module_new_boards == array() && $_POST['addfromall'] != 'on') {
							exitWithErrorPage(_gettext('Please select a board.'));
						}
						}
						$module_boards = implode('|', $module_new_boards);
			if($tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "module` (`trigger`,`html`,`boards`) VALUES('".mysql_real_escape_string($_POST['trigger'])."', '".mysql_real_escape_string($_POST['html'])."', '".mysql_real_escape_string($module_boards)."')")) {
				$tpl_page .= '<b>Module inserted</b><br/><br/>';
			}
			else {
				die(mysql_error());
			}
		}
	if(!$_GET['edit'] && !$_GET['do']) {
		$tpl_page .= '<div><form action="?action=addmodule&do=add" method="post"><label for="trigger">Trigger</label><input type="text" name="trigger"/><br/><label for="html">HTML to append</label><textarea name="html" rows="5" cols="50"></textarea>';
		$tpl_page .= '<br/>
		'._gettext('Boards').':
		
		<label for="banfromall"><b>'._gettext('All boards').'</b></label>
		<input type="checkbox" name="addfromall"><br>OR<br>' .
		$this->MakeBoardListCheckboxes('modulefrom', $this->BoardList($_SESSION['manageusername'])) .
		'<br/>
		<input type="submit" value="Add" name="submit"/></form></div><br/><hr/><br/>';
	    $tpl_page .= '<table cellspacing="2" cellpadding="2" frame="hsides" width="100%"><tr><th>Trigger</th><th>Boards</th><th width="50%">HTML</th><th>Action</th></tr>';
      $results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "module` ORDER BY `id` DESC");
    if (count($results) > 0) {
     foreach($results as $line) {
      $count += 1;
      if ($count%2 == false ? $linecolour = "#fff" : $linecolour = "#dce3e8");
      $tpl_page .= "<tr bgcolor=\"$linecolour\"><td>" . $line['trigger'] . "</td><td>";
      $modboards = explode("|",$line['boards']);
      foreach($modboards as $board) $tpl_page .= '/' .$board . "/ ";
     $tpl_page .= "</td><td align=justify>" . wordwrap(htmlentities(substr($line['html'], 0 ,400))) . "</td>";
      $tpl_page .= '<td>[<a href="?action=addmodule&edit=' . $line['id'] . '">'. 'Edit' .'</a>] [<a href="?action=addmodule&delete=' . $line['id'] . '">'. 'x' .'</a>]</td>';
      $tpl_page .= '</tr>';
   }
   }
   else {
     $tpl_page .= 'No modules added';
    }
  $tpl_page .= '</table>';
		$tpl_page .= '<br/><h1>Files</h1><a href="?action=addmodule&do=upload">Upload a file</a><br/><br/>';
		$imgdir = 'module_images'; 
  	 $allowed_types = array('png','jpg','jpeg','gif');
  	 $dimg = opendir($imgdir);
		while($imgfile = readdir($dimg)) {
			if(in_array(strtolower(substr($imgfile,-3)),$allowed_types)) {
				$a_img[] = $imgfile;
				sort($a_img);
				reset ($a_img);
			} 
		}
 	$totimg = count($a_img); // total image number
		for($x=0; $x < $totimg; $x++) {
			$tpl_page .= '<a href="'. $imgdir . '/' . $a_img[$x].'"><img src="'.$imgdir . '/' . $a_img[$x].'" width="50" height="50" alt="" style="border: 1px solid #000; float: left; margin-right: 5px"/></a>';
		}
			$tpl_page .= '<br/><br/>' . $totimg .' images uploaded.<br/>';
		}
	}
		function images() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		$tpl_page .= '<h2>' . ucwords(_gettext('Manage Images')) . '</h2><br>';
		if(isset($_POST['comment_submit'])) {
			foreach($_POST as $del) {
				if(eregi("comment_",$del)) {
					$del = str_replace("comment_","",$del);
					if(is_numeric($del)) {
						$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "comment_cache` WHERE `id` = '".$del."'") or die(mysql_error());
					}
				}
			}
			$tpl_page .= 'Comments deleted<br/><br/>';
		}
		if(isset($_POST['image_submit'])) {
			foreach($_POST as $del) {
				if(eregi("image_",$del)) {
					$del = str_replace("image_","",$del);
					if(is_numeric($del)) {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "image` SET `deleted` = '1' WHERE `id` = '".$del."' LIMIT 1") or die(mysql_error());
					}
				}
			}
			$tpl_page .= 'Image deleted<br/><br/>';
		}
		if(isset($_POST['tag_submit'])) {
			foreach($_POST as $del) {
				if(eregi("tag_",$del)) {
					$del = str_replace("tag_","",$del);
					if(is_numeric($del)) {
						$rozo = $tc_db->GetAll("SELECT HIGH_PRIORITY `image`,`tag` from `".KU_DBPREFIX."tag_cache` WHERE `id` = '".mysql_real_escape_string($del)."' LIMIT 1") or die('wattt');
						if(count($rozo) == 1) {
							foreach($rozo as $snoopy) {
								$bozo = $tc_db->GetAll("SELECT `tags` FROM `" . KU_DBPREFIX . "image` WHERE `image` = '".mysql_real_escape_string($snoopy['image'])."' LIMIT 1") or die('wat');
								if(count($bozo) == 1) {
									foreach($bozo as $theclown) {
										if($theclown['tags'] != '') {					
											$toggyz = str_replace($snoopy['tag'] . ",","",$theclown['tags']);
											if(!$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "image` SET `tags` = '".$toggyz ."' WHERE `image` = '".mysql_real_escape_string($snoopy['image'])."'")) {
												die('wot');
											}
											else {
												$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "tag_cache` WHERE `id` = '".mysql_real_escape_string($del)."'") or die(mysql_error());
												$tpl_page .= 'Tags deleted<br/><br/>';
											}
										}
									}
								}
								else {
									exitWithErrorPage('aintgoodmayne');
								}
							}
						}
						else {
							exitWithErrorPage('idkwhathappened');
						}
					}
				}
			}
		}				
		$ressyz = $tc_db->GetAll("SELECT `ip`,`searchtag`,`time` FROM `" . KU_DBPREFIX . "searchtag_cache` ORDER BY `time` desc LIMIT 100");
		$tpl_page .= 'Latest searchs<br/><table width="400" cellpadding="3" style="border: 1px solid"><tr><th>IP</th><th>Search</th><th>Time</th></tr>';
		if(count($ressyz) > 0) {
			foreach($ressyz as $rezzy) {
				$tpl_page .= '<tr><td>' . $rezzy['ip'] . '</td><td>' . htmlentities($rezzy['searchtag']) . '</td><td> ' . date('F j, Y, g:i a',$rezzy['time']) . "</td></tr>";
			}
		}
		$tpl_page .= '</table><br/>Latest votes</br>';
		$ressyz = $tc_db->GetAll("SELECT `ip`,`image`,`like`,`dislike`,`time` FROM `" . KU_DBPREFIX . "like_cache` ORDER BY `time` desc LIMIT 100");
		$tpl_page .= '<table width="400" cellpadding="3" style="border: 1px solid"><tr><th>IP</th><th>Image</th><th>Time</th><th>Vote</th></tr>';
		if(count($ressyz) > 0) {
			foreach($ressyz as $rezzy) {
				if($rezzy['like'] == 1) {
					$tpl_page .= '<tr><td>' . $rezzy['ip'] . '</td><td>' . $rezzy['image'] . '</td><td> ' . date('F j, Y, g:i a',$rezzy['time']) . "</td><td>Like</td>";
				}
				if($rezzy['dislike'] == 1) {
					$tpl_page .= '<tr><td>' . $rezzy['ip'] . '</td><td>' . $rezzy['image'] . '</td><td> ' . date('F j, Y, g:i a',$rezzy['time']) . "</td><td>Dislike</td>";
				}
				$tpl_page .= "</tr>";
			}
		}
		$tpl_page .= '</table><br/>';
			$ressyz = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "comment_cache` ORDER BY `time` desc LIMIT 100");
		$tpl_page .= 'Latest comments<form action="' . KU_CGIPATH . '/manage_page.php?action=images" method="post"><table width="400" cellpadding="3" style="border: 1px solid"><tr><th>IP</th><th>Search</th><th>Time</th><th>ID</th><th>Delete</th></tr>';
		if(count($ressyz) > 0) {
			foreach($ressyz as $rezzy) {
				$tpl_page .= '<tr><td>' . $rezzy['ip'] . '</td><td>' . $rezzy['comment'] . '</td><td> ' . date('F j, Y, g:i a',$rezzy['time']) . '</td><td><a href="'.KU_WEBPATH.'/download.php?comment='. $rezzy['comment_id']. '">Go</a></td><td><input type="radio" id="comment" name="comment_'.$rezzy['id'].'" value="comment_'.$rezzy['id'].'"/></tr>';
			}
		}
		$tpl_page .= '</table><input type="Submit" value="Delete" name="comment_submit"/></form><br/>';
		$tpl_page .= '</table><br/>Latest tags</br>';
			$ressyz = $tc_db->GetAll("SELECT `ip`,`tag`,`time`,`image`,`id` FROM `" . KU_DBPREFIX . "tag_cache` WHERE `ip` != '0' ORDER BY `time` desc LIMIT 100");
		$tpl_page .= '<form action="' . KU_CGIPATH . '/manage_page.php?action=images" method="post"><table width="400" cellpadding="3" style="border: 1px solid"><tr><th>IP</th><th>Tag</th><th>Time</th><th>Image</th><th>Delete</th></tr>';
		if(count($ressyz) > 0) {
			foreach($ressyz as $rezzy) {
				$tpl_page .= '<tr><td>' . $rezzy['ip'] . '</td><td>' . htmlentities($rezzy['tag']) . '</td><td> ' . date('F j, Y, g:i a',$rezzy['time']) . '</td><td>'.$rezzy['image'].'</td><td><input type="radio" id="comment" name="tag_'.$rezzy['id'].'" value="tag_'.$rezzy['id'].'"/></tr>';
			}
		}
		$tpl_page .= '</table><input type="Submit" value="Delete" name="tag_submit"/></form><br/>';
		$tpl_page .= '</table><br/>Latest images</br>';
		$ressyz = $tc_db->GetAll("SELECT `thumb`,`id` FROM `" . KU_DBPREFIX . "image` WHERE `deleted` = '0' ORDER BY `id` DESC LIMIT 250");
		$tpl_page .= '<form action="' . KU_CGIPATH . '/manage_page.php?action=images" method="post"><table width="400" cellpadding="3" style="border: 1px solid"><tr><th>Image</th><th>Link</th><th>Delete</th></tr>';
		if(count($ressyz) > 0) {
			foreach($ressyz as $rezzy) {
				$tpl_page .= '<tr><td><img src="'.$rezzy['thumb'].'"/></td><td>' . $rezzy['thumb'] . '</td><td><input type="radio" id="comment" name="image_'.$rezzy['id'].'" value="image_'.$rezzy['id'].'"/></tr>';
			}
		}
		$tpl_page .= '</table><input type="Submit" value="Delete" name="image_submit"/></form><br/>';
	}
	/* Add, view, and delete sections */
	function editsections() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		
		$tpl_page .= '<h2>' . ucwords(_gettext('Edit sections')) . '</h2><br>';
		if (isset($_GET['do'])) {
			if ($_GET['do'] == 'addsection') {
				if (isset($_POST['name'])) {
					if ($_POST['name'] != '' && $_POST['abbreviation'] != '') {
						$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "sections` ( `name` , `abbreviation` , `order` , `hidden` ) VALUES ( '" . mysql_real_escape_string($_POST['name']) . "' , '" . mysql_real_escape_string($_POST['abbreviation']) . "' , '" . mysql_real_escape_string($_POST['order']) . "' , '" . (isset($_POST['hidden']) ? '1' : '0') . "' )");
						require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
						$menu_class = new Menu();
						$menu_class->Generate();
						$tpl_page .= _gettext('Section added.');
					}
				} else {
					$tpl_page .= '<form action="?action=editsections&do=addsection" method="post">
					<label for="name">Name:</label><input type="text" name="name"><div class="desc">The name of the section</div><br>
					<label for="abbreviation">Abbreviation:</label><input type="text" name="abbreviation"><div class="desc">Abbreviation (less then 10 characters)</div><br>
					<label for="order">Order:</label><input type="text" name="order"><div class="desc">Order to show this section with others, in ascending order</div><br>
					<label for="hidden">Hidden:</label><input type="checkbox" name="hidden" ><div class="desc">If checked, this section will be collapsed by default when a user visits the site.</div><br>
					<input type="submit" value="Add">
					</form>';
				}
				$tpl_page .= '<br><hr>';
			}
			if ($_GET['do'] == 'editsection' && $_GET['sectionid'] > 0) {
				if (isset($_POST['name'])) {
					if ($_POST['name'] != '' && $_POST['abbreviation'] != '') {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "sections` SET `name` = '" . mysql_real_escape_string($_POST['name']) . "' , `abbreviation` = '" . mysql_real_escape_string($_POST['abbreviation']) . "' , `order` = '" . mysql_real_escape_string($_POST['order']) . "' , `hidden` = '" . (isset($_POST['hidden']) ? '1' : '0') . "' WHERE `id` = '" . $_GET['sectionid'] . "'");
						require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
						$menu_class = new Menu();
						$menu_class->Generate();
						$tpl_page .= _gettext('Section updated.');
					}
				} else {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "sections` WHERE `id` = '" . mysql_real_escape_string($_GET['sectionid']) . "'");
					if (count($results) > 0) {
						foreach ($results as $line) {
							$tpl_page .= '<form action="?action=editsections&do=editsection&sectionid=' . $_GET['sectionid'] . '" method="post">
							<input type="hidden" name="id" value="' . $_GET['sectionid'] . '">
							
							<label for="name">Name:</label>
							<input type="text" name="name" value="' . $line['name'] . '">
							<div class="desc">The name of the section</div><br>
							
							<label for="abbreviation">Abbreviation:</label>
							<input type="text" name="abbreviation" value="' . $line['abbreviation'] . '">
							<div class="desc">Abbreviation (less then 10 characters)</div><br>
							
							<label for="order">Order:</label>
							<input type="text" name="order" value="' . $line['order'] . '">
							<div class="desc">Order to show this section with others, in ascending order</div><br>
							
							<label for="hidden">Hidden:</label>
							<input type="checkbox" name="hidden" ' . ($line['hidden'] == 0 ? '' : 'checked') . '>
							<div class="desc">If checked, this section will be collapsed by default when a user visits the site.</div><br>
							
							<input type="submit" value="Edit">
							
							</form>';
						}
					} else {
						$tpl_page .= _gettext('Unable to locate a section with that ID.');
					}
				}
				$tpl_page .= '<br><hr>';
			}
			if ($_GET['do'] == 'deletesection' && isset($_GET['sectionid'])) {
				if ($_GET['sectionid'] > 0) {
					$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "sections` WHERE `id` = '" . mysql_real_escape_string($_GET['sectionid']) . "'");
					require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
					$menu_class = new Menu();
					$menu_class->Generate();
					$tpl_page .= _gettext('Section deleted.') . '<br><hr>';
				}
			}
		}
		$tpl_page .= '<a href="?action=editsections&do=addsection">Add section</a><br><br>';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "sections` ORDER BY `order` ASC");
		if (count($results) > 0) {
			$tpl_page .= '<table border="1" width="100%"><tr><th>'.('ID').'</th><th>'.('Order').'</th><th>Abbreviation</th><th>Name</th><th>Edit/Delete</th></tr>';
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['id'] . '</td><td>' . $line['order'] . '</td><td>' . $line['abbreviation'] . '</td><td>' . $line['name'] . '</td><td><a href="?action=editsections&do=editsection&sectionid=' . $line['id'] . '">Edit</a> <a href="?action=editsections&do=deletesection&sectionid=' . $line['id'] . '">Delete</a></td></tr>';
			}
			$tpl_page .= '</table>';
		} else {
			$tpl_page .= _gettext('There are currently no sections.');
		}
	}
	function guide() {
		global $tc_db, $smarty, $tpl_page, $action;
		$this->ModeratorsOnly();
		$tpl_page .= '<h2>' . _gettext('Moderator Guide') . '</h2><br>';
		if($_GET['add']) {
			$this->AdministratorsOnly();
			$tpl_page .= '<form action="?action=announcement" method="post">';
			$tpl_page .= '<b>Add item</b><br/>
			This message will be displayed to all staff members. Format your announcement with HTML code.<br/>
			<br/>
			<label for="name">Name:</label><input type="text" name="name" length="20" maxlength="20" value="'. $_SESSION['manageusername'] .'" readonly="readonly"/><br/><label for"displayname" style="font-size: 9px">Display name:</label> <input type="checkbox" name="displayname" checked="checked"/><br/><label for="subject">Subject:</label><input type="text" name="subject" length="50"/><br/><label for="news_item">News:</label><textarea name="news_item" rows="15" cols="50"></textarea><input type="submit" value="Add News"/></form><br/>';
		}
		if($_GET['delete']) {
			$this->AdministratorsOnly();
			if($_GET['delete'] != '') {
				if($tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "announcements` WHERE `id` = '" . mysql_real_escape_string($_GET['delete']) . "'")) {
					$tpl_page .= 'News post deleted<br/><br/>';
					management_addlogentry(_gettext('Deleted an announcement entry'), 9);
				}
			}
		}
		if(isset($_POST['news_item'])) {
			$this->AdministratorsOnly();
			if($_POST['news_item'] == '' || $_POST['subject'] == '') {
				exitWithErrorPage(_gettext('Please fill in all fields.'));
			}
			if($_POST['name'] != $_SESSION['manageusername']) {
				die('Invalid name');
			}
			if(!isset($_POST['displayname'])) {
				$_POST['name'] = '';
			}
			if($tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "announcements` ( `name`, `subject` , `news` , `postedat` , `postedby`) VALUES ( '" . mysql_real_escape_string($_POST['name']) . "' , '" . mysql_real_escape_string($_POST['subject']) . "' , '" . mysql_real_escape_string($_POST['news_item']) . "' , '" . time() . "' , '" . mysql_real_escape_string($_SESSION['manageusername']) . "')")) {		
				management_addlogentry(_gettext('Added an announcement entry'), 9);
				$tpl_page .= (_gettext('News item successfully added.')) . '<br/><br/>';
			}
			else {
				exitWithErrorPage(_gettext('Item was not added ' . mysql_error()));
			}			
		}
		if($_GET['commentdelete']) {
			$this->AdministratorsOnly();
			$_GET['commentdelete'] = urldecode($_GET['commentdelete']);
			if(!is_numeric($_GET['id'])) {
				exitWithErrorPage(_gettext('Invalid ID'));
			}
			else {
				$results = $tc_db->GetAll ("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "announcements` WHERE `id` = '". mysql_real_escape_string($_GET['id'])."'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$line['comments'] = str_replace($_GET['commentdelete'], "", $line['comments']);
					}
					if($tc_db->Execute("UPDATE `" . KU_DBPREFIX . "announcements` SET `comments` = '".mysql_real_escape_string($line['comments'])."' WHERE `id` = '". mysql_real_escape_string($_GET['id'])."'")) {
						$tpl_page .= 'Comment deleted';
					}
					else {
						$tpl_page .= 'Failed to delete: ' . mysql_error();
					}
				}
				else {
					$tpl_page .= 'Invalid ID';
				}
			}
		}
		if($_GET['comment']) {
			if(isset($_POST['addcomment'])) {
				$_POST['addcomment'] = str_replace("|", "-", $_POST['addcomment']);
				$_POST['addcomment'] = str_replace(":", ";", $_POST['addcomment']);
				if($tc_db->Execute("UPDATE `" . KU_DBPREFIX . "announcements` SET `comments` = CONCAT( `comments`, '". mysql_real_escape_string($_POST['addcomment']) . '|' . $_POST['commentid'] . '|' . $_SESSION['manageusername'] . '|' . time() . ':' . "') WHERE `id` = '". $_POST['commentid']."'")) {
					$tpl_page .= 'Comment added!<br/><br/>';
					management_addlogentry(_gettext('Added an announcement comment'), 9);
				}
				else {
					die(mysql_error());
				}
			}
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "announcements` WHERE `id` = '". mysql_real_escape_string($_GET['comment'])."' ORDER BY `id` DESC");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$tpl_page .= '<div style="border: 1px solid #888"><div style="padding: 5px;background:#E0E0E0;"><b>'.$line['subject'].'</b> | '.date('F j, Y, g:i a', $line['postedat']).'';
					if($line['name'] != '') {
						$tpl_page .= ' by '.$line['name'].' [<a href="manage_page.php?action=announcement&delete='.$line['id'].'">x</a>]</div>';
					}
					else {
						$tpl_page .= ' [<a href="manage_page.php?action=announcement&delete='.$line['id'].'">x</a>]</div>';
					}
					$tpl_page .= '<div style="padding: 5px;">'.$line['news'].'</div></div><br/>';
					if($line['comments'] == '') {
						$tpl_page .= 'No comments<br/>';
					}
					else {
						$comment = explode(":", $line['comments']);
						foreach($comment as $comment2) {
							if($comment2 != '') {
								$comment3 = explode("|", $comment2);
								$tpl_page .= '<div style="border: 1px solid #999;"><div style="background-color: #F0F0F0; padding: 4px;"><strong>'.$comment3[2] . '</strong> ('.date('F j, Y, g:i a', $comment3[3]).') [<a href="manage_page.php?action=announcement&commentdelete='.urlencode($comment2).'&id='.$line['id'].'">x</a>]</div><div style="padding: 4px;">' . htmlspecialchars($comment3[0]).'</div></div><br/>';
							}
						}
					}
					$tpl_page .= '<br/><form action="?action=announcement&comment='.$line['id'].'" method="post"><input type="hidden" value="'.$_GET['comment'].'" name="commentid"/><textarea name="addcomment" rows="4" cols="50"></textarea><input type="submit" name="submitcomment" value="Add"/></form><br/>';
				}
			}
			else {
				$tpl_page .= 'Invalid comment ID';
			}
		}
		if(!($_GET['add']) && !($_GET['comment']) && !($_GET['commentdelete'])) {			
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "announcements` WHERE `id` = 91 ORDER BY `id` DESC");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$tpl_page .= '<div style="border: 1px solid #888"><div style="padding: 5px;background:#E0E0E0;"><b>'.$line['subject'].'</b> | '.date('F j, Y, g:i a', $line['postedat']).'';
					if($line['name'] != '') {
						$tpl_page .= ' by '.$line['name'].' [<a href="manage_page.php?action=announcement&delete='.$line['id'].'">x</a>]</div>';
					}
					else {
						$tpl_page .= ' [<a href="manage_page.php?action=announcement&delete='.$line['id'].'">x</a>]</div>';
					}
					$tpl_page .= '<div style="padding: 5px;">'.$line['news'].'</div></div>';
					$comment = explode(":", $line['comments']);
					$i = 0;
					foreach($comment as $comment2) {
						if($comment2 != '') {
							$i++;
						}
					}
					if($i == 0) {
						$tpl_page .=  '<div align="right" style="padding: 3px"><a href="manage_page.php?action?announcement&comment='.$line['id'].'" style="color: grey">No comments</a></div><br/><br/>';
					}
				    else {
						$tpl_page .=  '<div align="right" style="padding: 3px"><a href="manage_page.php?action?announcement&comment='.$line['id'].'">Comments ('.$i.')</a></div><br/><br/>';
					}				
				}
			} else {
				$tpl_page .= 'No news feed.';
			}
			if($this->CurrentUserIsAdministrator()) {
				$tpl_page .= '<p style="text-align: right">[<a href="manage_page.php?action=announcement&add=1">Add item</a>]</p>';
			}
		}
	}
	/* Add, view, and delete filetypes */
	function editfiletypes() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		
		$tpl_page .= '<h2>' . ucwords(_gettext('Edit filetypes')) . '</h2><br>';
		if (isset($_GET['do'])) {
			if ($_GET['do'] == 'addfiletype') {
				if (isset($_POST['filetype']) || isset($_POST['image'])) {
					if ($_POST['filetype'] != '' && $_POST['image'] != '') {
						$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "filetypes` ( `filetype` , `mime` , `image` , `image_w` , `image_h` ) VALUES ( '" . mysql_real_escape_string($_POST['filetype']) . "' , '" . mysql_real_escape_string($_POST['mime']) . "' , '" . mysql_real_escape_string($_POST['image']) . "' , '" . mysql_real_escape_string($_POST['image_w']) . "' , '" . mysql_real_escape_string($_POST['image_h']) . "' )");
						$tpl_page .= _gettext('Filetype added.');
					}
				} else {
					$tpl_page .= '<form action="?action=editfiletypes&do=addfiletype" method="post">
					<label for="filetype">Filetype:</label>
					<input type="text" name="filetype">
					<div class="desc">The extension this will be applied to.  <b>Must be lowercase</b></div><br>
					
					<label for="mime">MIME type:</label>
					<input type="text" name="mime">
					<div class="desc">The MIME type which must be present with an image uploaded in this type.  Leave blank to disable.</div><br>
					
					<label for="image">Image:</label>
					<input type="text" name="image" value="generic.png">
					<div class="desc">The image which will be used, found in inc/filetypes.</div><br>
					
					<label for="image_w">Image width:</label>
					<input type="text" name="image_w" value="48">
					<div class="desc">The height of the image.  Needs to be set to prevent the page from jumping around while images load.</div><br>
					
					<label for="image_h">Image height:</label>
					<input type="text" name="image_h" value="48">
					<div class="desc">See above.</div><br>
					
					<input type="submit" value="Add">
					
					</form>';
				}
				$tpl_page .= '<br><hr>';
			}
			if ($_GET['do'] == 'editfiletype' && $_GET['filetypeid'] > 0) {
				if (isset($_POST['filetype'])) {
					if ($_POST['filetype'] != '' && $_POST['image'] != '') {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "filetypes` SET `filetype` = '" . mysql_real_escape_string($_POST['filetype']) . "' , `mime` = '" . mysql_real_escape_string($_POST['mime']) . "' , `image` = '" . mysql_real_escape_string($_POST['image']) . "' , `image_w` = '" . mysql_real_escape_string($_POST['image_w']) . "' , `image_h` = '" . mysql_real_escape_string($_POST['image_h']) . "' WHERE `id` = '" . mysql_real_escape_string($_GET['filetypeid']) . "'");
						if (KU_APC) {
							apc_delete('filetype|' . $_POST['filetype']);
						}
						$tpl_page .= _gettext('Filetype updated.');
					}
				} else {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "filetypes` WHERE `id` = '" . mysql_real_escape_string($_GET['filetypeid']) . "'");
					if (count($results) > 0) {
						foreach ($results as $line) {
							$tpl_page .= '<form action="?action=editfiletypes&do=editfiletype&filetypeid=' . $_GET['filetypeid'] . '" method="post">
							
							<label for="filetype">Filetype:</label>
							<input type="text" name="filetype" value="' . $line['filetype'] . '">
							<div class="desc">The extension this will be applied to.  <b>Must be lowercase</b></div><br>
							
							<label for="mime">MIME type:</label>
							<input type="text" name="mime" value="' . $line['mime'] . '">
							<div class="desc">The MIME type which must be present with an image uploaded in this type.  Leave blank to disable.</div><br>
							
							<label for="image">Image:</label>
							<input type="text" name="image" value="' . $line['image'] . '">
							<div class="desc">The image which will be used, found in inc/filetypes.</div><br>
							
							<label for="image_w">Image width:</label>
							<input type="text" name="image_w" value="' . $line['image_w'] . '">
							<div class="desc">The height of the image.  Needs to be set to prevent the page from jumping around while images load.</div><br>
							
							<label for="image_h">Image height:</label>
							<input type="text" name="image_h" value="' . $line['image_h'] . '">
							<div class="desc">See above.</div><br>
							
							<input type="submit" value="Edit">
							
							</form>';
						}
					} else {
						$tpl_page .= _gettext('Unable to locate a filetype with that ID.');
					}
				}
				$tpl_page .= '<br><hr>';
			}
			if ($_GET['do'] == 'deletefiletype' && $_GET['filetypeid'] > 0) {
				$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "filetypes` WHERE `id` = '" . mysql_real_escape_string($_GET['filetypeid']) . "'");
				$tpl_page .= _gettext('Filetype deleted.');
				$tpl_page .= '<br><hr>';
			}
		}
		$tpl_page .= '<a href="?action=editfiletypes&do=addfiletype">Add filetype</a><br><br>';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "filetypes` ORDER BY `filetype` ASC");
		if (count($results) > 0) {
			$tpl_page .= '<table border="1" width="100%"><tr><th>ID</th><th>Filetype</th><th>Image</th><th>Edit/Delete</th></tr>';
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['id'] . '</td><td>' . $line['filetype'] . '</td><td>' . $line['image'] . '</td><td><a href="?action=editfiletypes&do=editfiletype&filetypeid=' . $line['id'] . '">Edit</a> <a href="?action=editfiletypes&do=deletefiletype&filetypeid=' . $line['id'] . '">Delete</a></td></tr>';
			}
			$tpl_page .= '</table>';
		} else {
			$tpl_page .= _gettext('There are currently no filetypes.');
		}
	}
	
	/* Rebuild all boards */
	function rebuildhtml() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		$tpl_page .= '<h2>' . ucwords(_gettext('Rebuild HTML files')) . '</h2><br>';
		if (isset($_POST['rebuild'])) {
			$time_start = time();
				$deletion_boards = array();
				$deletion_new_boards = array();
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
				if (isset($_POST['rebuildfromall'])) {
					$this->ModeratorsOnly();
					foreach ($results as $line) {
						$deletion_new_boards[] = $line['name'];
					}
				} else {
					foreach ($results as $line) {
						$deletion_boards[] = $line['name'];
					}
					$deletion_changed_boards = array();
					$deletion_new_boards = array();
					while (list($postkey, $postvalue) = each($_POST)) {
						if (substr($postkey, 0, 11) == 'rebuildfrom') {
							$deletion_changed_boards[] = substr($postkey, 11);
						}
					}
					while (list(, $deletion_thisboard_name) = each($deletion_boards)) {
						if (in_array($deletion_thisboard_name, $deletion_changed_boards)) {
							$deletion_new_boards[] = $deletion_thisboard_name;
						}
					}
					if ($deletion_new_boards == array()) {
						exitWithErrorPage(_gettext('Please select a board.'));
					}
				}
				$delete_boards = implode('|', $deletion_new_boards);
				foreach ($deletion_new_boards as $board) {
					if (!$this->CurrentUserIsModeratorOfBoard($board, $_SESSION['manageusername'])) {
						exitWithErrorPage('/' . $board . '/: ' . _gettext('You can only rebuild HTML from boards you moderate.'));
					}
				}
				$i = 0;
				foreach ($deletion_new_boards as $deletion_board) {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysql_real_escape_string($deletion_board) . "'");
					foreach ($results as $line) {
						$board_name = $line['name'];
					}
					$board_class = new Board($board_name);
					$board_class->RegenerateAll();
					$tpl_page .= sprintf(_gettext('Regenerated %s'), '/' . $board_name . '/') . '<br>';
					unset($board_class);
					flush();	
				}
			require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
			$menu_class = new Menu();
			$menu_class->Generate();
			$tpl_page .= 'Regenerated menu pages<br>';
			$tpl_page .= sprintf(_gettext('Rebuild complete.  Took <b>%d</b> seconds.'), time() - $time_start);
			management_addlogentry(_gettext('Rebuilt HTML'), 2);
			unset($board_class);
			}
		else {
			$tpl_page .= '<form action="?action=rebuildhtml" method="post">';
			$tpl_page .= ''._gettext('Select HTML to rebuild').':<br/><br/>
			<label for="rebuildfromall"><b>'._gettext('All boards').'</b></label>
			<input type="checkbox" name="rebuildfromall"><br>OR<br>' .
			$this->MakeBoardListCheckboxes('rebuildfrom', $this->BoardList($_SESSION['manageusername'])) .
			'<br>
			<input type="submit" name="rebuild" value="'._gettext('Rebuild boards').'">		
			</form><br/>';
		}
	}
	
	/* Show APC info */
	function apc() {
		global $tpl_page;
	
		if (KU_APC) {
			$apc_info_system = apc_cache_info();
			$apc_info_user = apc_cache_info('user');
			$tpl_page .= '<h2>APC</h2><h3>System (File cache)</h3><ul>';
			$tpl_page .= '<li>Start time: <b>' . date("y/m/d(D)H:i", $apc_info_system['start_time']) . '</b></li>';
			$tpl_page .= '<li>Hits: <b>' . $apc_info_system['num_hits'] . '</b></li>';
			$tpl_page .= '<li>Misses: <b>' . $apc_info_system['num_misses'] . '</b></li>';
			$tpl_page .= '<li>Entries: <b>' . $apc_info_system['num_entries'] . '</b></li>';
			$tpl_page .= '</ul><br><h3>User (kusaba)</h3><ul>';
			$tpl_page .= '<li>Start time: <b>' . date("y/m/d(D)H:i", $apc_info_user['start_time']) . '</b></li>';
			$tpl_page .= '<li>Hits: <b>' . $apc_info_user['num_hits'] . '</b></li>';
			$tpl_page .= '<li>Misses: <b>' . $apc_info_user['num_misses'] . '</b></li>';
			$tpl_page .= '<li>Entries: <b>' . $apc_info_user['num_entries'] . '</b></li>';
			$tpl_page .= '</ul><br><br><a href="?action=clearcache">Clear APC cache</a>';
		} else {
			$tpl_page .= 'APC isn\'t enabled!';
		}
	}
	
	/* Clear the APC cache */
	function clearcache() {
		global $tpl_page;
	
		if (KU_APC) {
			apc_clear_cache();
			apc_clear_cache('user');
			$tpl_page .= 'APC cache cleared.';
			management_addlogentry(_gettext('Cleared APC cache'), 0);
		} else {
			$tpl_page .= 'APC isn\'t enabled!';
		}
	}
	
	/* Display disk space used per board, and finally total in a large table */
	function spaceused() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		
		$tpl_page .= '<h2>' . ucwords(_gettext('Disk space used')) . '</h2><br>';
		$spaceused_res = 0;
		$spaceused_src = 0;
		$spaceused_thumb = 0;
		$spaceused_total = 0;
		$files_res = 0;
		$files_src = 0;
		$files_thumb = 0;
		$files_total = 0;
		$tpl_page .= '<table border="1" width="100%"><tr><th>Board</th><th>Area</th><th>Files</th><th>Space Used</th></tr>';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
		foreach ($results as $line) {
			list($spaceused_board_res, $files_board_res) = recursive_directory_size(KU_BOARDSDIR . $line['name'] . '/res');
			list($spaceused_board_src, $files_board_src) = recursive_directory_size(KU_BOARDSDIR . $line['name'] . '/src');
			list($spaceused_board_thumb, $files_board_thumb) = recursive_directory_size(KU_BOARDSDIR . $line['name'] . '/thumb');
			
			$spaceused_board_total = $spaceused_board_res + $spaceused_board_src + $spaceused_board_thumb;
			$files_board_total = $files_board_res + $files_board_src + $files_board_thumb;
			
			$spaceused_res += $spaceused_board_res;
			$files_res += $files_board_res;
			
			$spaceused_src += $spaceused_board_src;
			$files_src += $files_board_src;
			
			$spaceused_thumb += $spaceused_board_thumb;
			$files_thumb += $files_board_thumb;
			
			$spaceused_total += $spaceused_board_total;
			$files_total += $files_board_total;
			
			$tpl_page .= '<tr><td rowspan="4">/'.$line['name'].'/</td><td>res/</td><td>' . number_format($files_board_res) . '</td><td>' . ConvertBytes($spaceused_board_res) . '</td></tr>';
			$tpl_page .= '<tr><td>src/</td><td>' . number_format($files_board_src) . '</td><td>' . ConvertBytes($spaceused_board_src) . '</td></tr>';
			$tpl_page .= '<tr><td>thumb/</td><td>' . number_format($files_board_thumb) . '</td><td>' . ConvertBytes($spaceused_board_thumb) . '</td></tr>';
			$tpl_page .= '<tr><td><b>Total</b></td><td>' . number_format($files_board_total) . '</td><td>' . ConvertBytes($spaceused_board_total) . '</td></tr>';
		}
		$tpl_page .= '<tr><td rowspan="4"><b>All boards</b></td><td>res/</td><td>' . number_format($files_res) . '</td><td>' . ConvertBytes($spaceused_res) . '</td></tr>';
		$tpl_page .= '<tr><td>src/</td><td>' . number_format($files_src) . '</td><td>' . ConvertBytes($spaceused_src) . '</td></tr>';
		$tpl_page .= '<tr><td>thumb/</td><td>' . number_format($files_thumb) . '</td><td>' . ConvertBytes($spaceused_thumb) . '</td></tr>';
		$tpl_page .= '<tr><td><b>Total</b></td><td>' . number_format($files_total) . '</td><td>' . ConvertBytes($spaceused_total) . '</td></tr>';
		$tpl_page .= '</table>';
		management_addlogentry(_gettext('Viewed disk space used'), 0);
	}
	
	/* Display moderators and administrators actions which were logged */
	function modlog() {
		global $tc_db, $smarty, $tpl_page;
		//$this->AdministratorsOnly();
		if($this->CheckAccess() < 10) {
			exitWithErrorPage('You do not have permission to access this page');
		}		
		//$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "modlog` WHERE `timestamp` < '" . (time() - KU_MODLOGDAYS * 86400) . "'");
		if($_GET['reset'] == 1) {
			$this->RootOnly();
			$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "modlog` WHERE `entry` != 1");
			management_addlogentry(_gettext('Cleared the modlog'), 0);
		}
		$tpl_page .= "<h2>ModLog</h2><br/>";
		if ($_GET['all'] == '1') {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "modlog` ORDER BY `timestamp` DESC");
		} else {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "modlog` ORDER BY `timestamp` DESC LIMIT 50");
		}
		$tpl_page .= '<table cellspacing="2" cellpadding="1" border="1" width="100%"><tr><th>Time</th><th>User</th><th width="100%">Action</th></tr>';
		foreach ($results as $line) {
			$count += 1;
			if ($count%2 == false) {
					$linecolour = "#ffffff";
			} else {
					$linecolour = "#dce3e8";
			}
			$tpl_page .= "<tr bgcolor=\"$linecolour\">";
			$tpl_page .= "<td>" . date("y/m/d(D)H:i", $line['timestamp']) . "</td><td>" . $line['user'] . "</td><td>" . $line['entry'] . "</td></tr>";
		}
		$tpl_page .= '</table>';
		$tpl_page .= '<p align="right"><a href="?action=modlog&all=1">Modlog Archive</a>';
		if($this->CurrentUserIsAdministrator()) {
			$tpl_page .= '| <a href="manage_page.php?action=modlog&reset=1" onclick="return confirm(\'Are you sure you want to clear the modlog?\');">Clear Modlog</a></p>';
		}
	}
		function userlog() {
		global $tc_db, $smarty, $tpl_page;
		$this->ModeratorsOnly();
		if($this->CheckAccess() < 6) {
			exitWithErrorPage('You do not have permission to access this page');
		}		
		//$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "modlog` WHERE `timestamp` < '" . (time() - KU_MODLOGDAYS * 86400) . "'");
		if($_GET['reset'] == 1) {
			$this->RootOnly();
			$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "userlog` WHERE `entry` != 1");
			management_addlogentry(_gettext('Cleared the userlog'), 0);
		}
		if(is_numeric($_GET['delete']) && $_GET['delete'] != '') {
			$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "userlog` WHERE `id` = '".mysql_real_escape_string($_GET['delete'])."'");
			management_addlogentry(_gettext('Cleared userlog ID '.$_GET['delete'].''), 0);
		}
		$tpl_page .= "<h2>UserLog</h2><br/>";
		if ($_GET['all'] == '1') {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "userlog` ORDER BY `timestamp` DESC LIMIT 250");
		} else {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "userlog` ORDER BY `timestamp` DESC LIMIT 50");
		}
		$tpl_page .= '<table cellspacing="2" cellpadding="1" border="1" width="100%"><tr><th>Time</th><th>User</th><th>Board</th><th>Image</th><th width="100%">Post</th><th>User-agent</th><th>Delete</th></tr>';
		foreach ($results as $line) {
		$subip = substr($line['user'], -1, 1);
			$subip2 = substr($line['user'], -2, 1);
			switch($subip) {
				case 1:
					$hex = "pink";
					break;
				case 2:
					$hex = "blue";
					break;
				case 3:
					$hex = "green";
					break;
				case 4:
					$hex = "grey";
					break;
				case 5:
					$hex = "black";
					break;
				case 6:
					$hex = "red";
					break;
				case 7:
					$hex = "orange";
					break;
				case 8:
					$hex = "yellow";
					break;
				case 9:
					$hex = "purple";
					break;
				case 0:
					$hex = "darkgrey";
					break;
				case ".":
					$hex = "lightpink";
					break;
				default:
					$hex = "brown";				
			}	
			$count += 1;
			if ($count%2 == false) {
					$linecolour = "#ffffff";
			} else {
					$linecolour = "#dce3e8";
			}
//$name = explode("!", $line['entry']);
//$name2 = explode("#", $name[0]); 			
$tpl_page .= "<tr bgcolor=\"$linecolour\">";
					$tpl_page .= "<td>" . date("y/m/d(D)H:i", $line['timestamp']) . "</td><td><span style=\"color: ".$hex."\">" . $line['user'] . "</span></td><td>/".$line['board']."/</td><td><a href=\"". KU_WEBPATH . $line['image'] ."\"><img src=\"". KU_WEBPATH . 
$line['image_thumb']."\" width=\"100\" height=\"100\" border=\"0\"/></td><td>" . $line['entry'] . ' <a href="'.$line['thread'].'">Go >></a></td><td>'.htmlentities($line['agent']).'</td><td>[<a href="manage_page.php?action=userlog&delete='.$line['id'].'">x</a>]</td></tr>';
		}
		$tpl_page .= '</table>';
		$tpl_page .= '<p align="right"><a href="?action=userlog&all=1">Userlog Archive</a> | <a href="manage_page.php?action=userlog&reset=1" onclick="return confirm(\'Are you sure you want to clear the userlog?\');">Clear Userlog</a></p>';
	}
	/* Allow SQL injection for administrators */
	function sql() {
		global $tc_db, $smarty, $tpl_page;
		$this->RootOnly();
		$tpl_page .= '<h2>' . _gettext('SQL query') . '</h2><br>';
		if (isset($_POST['query'])) {
			$tpl_page .= '<hr>';
			$result = $tc_db->Execute($_POST['query']);
			if ($result) {
				$tpl_page .= _gettext('Query executed successfully');
			} else {
				$tpl_page .= 'Error: ' . $tc_db->ErrorMsg();
			}
			$tpl_page .= '<hr>';
			management_addlogentry(_gettext('Inserted SQL'), 0);
		}
		$tpl_page .= '<form method="post" action="?action=sql">
		
		<textarea name="query" rows="20" cols="60"></textarea><br>
		
		<input type="submit" value="' . _gettext('Inject') . '">
		
		</form><br/>';
	}
	function menuedit() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		$tpl_page .= '<h2>' . _gettext('Menu Editor') . '</h2><br>';
		if($_GET['add']) {
			$this->AdministratorsOnly();
			if(isset($_POST['additem'])) {
				if(!is_numeric($_POST['order'])) {
					exitWithErrorPage('Order must be a number');
				}
				if($tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "menu` (`header`,`body`,`order`) VALUES ('" . mysql_real_escape_string($_POST['header']) . "' , '" . mysql_real_escape_string($_POST['body']) ."' , '" . mysql_real_escape_string($_POST['order']). "')")) {
					require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
					$menu_class = new Menu();
					$menu_class->Generate();
					$tpl_page .= 'Menu item added!<br/><br/>';
					management_addlogentry(_gettext('Added a menu item'), 9);
				}
				else {
					$tpl_page .= 'Failed to add menu item: ' . mysql_error();
				}
			}
			$this->AdministratorsOnly();
			$tpl_page .= '<form action="?action=menuedit&add=1" method="post">';
			$tpl_page .= '<b>Add menu item</b><br/>
			This menu item will be displayed below the boards. Format your item with HTML code.<br/>
			<br/>
			<label for="header">Header:</label><input type="text" name="header"/><br/><label for="body">Body:</label><textarea name="body" cols="60" rows="5"></textarea><br/><br/><label for="order">Order: <small>(must be a number from ascending order)</small></label><input type="order" name="order" maxlength="3"/><br/><input type="submit" name="additem" value="Add"/></form><br/>';
		}
		if($_GET['delete']) {
			$this->AdministratorsOnly();
			if($_GET['delete'] != '') {
				if($tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "menu` WHERE `id` = '" . mysql_real_escape_string($_GET['delete']) . "'")) {
					$tpl_page .= 'Menu item deleted<br/><br/>';
					management_addlogentry(_gettext('Deleted a menu item'), 9);
				}
			}
		}
		if($_GET['edit']) {
			$this->AdministratorsOnly();
			if(isset($_POST['editnews'])) {
				if($tc_db->Execute("UPDATE `" . KU_DBPREFIX . "menu` SET `body` = '". mysql_real_escape_string($_POST['body'])."', `header` = '".mysql_real_escape_string($_POST['header'])."', `order` = '".mysql_real_escape_string($_POST['order'])."' WHERE `id` = '".$_GET['edit']."'")) {
						require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
						$menu_class = new Menu();
						$menu_class->Generate();
						$tpl_page .= 'Menu edited';						
						management_addlogentry(_gettext('Edited a menu item'), 9);
				}
				else {
					$tpl_page .= 'Failed to edit: ' . mysql_error();
				}
			}		
			if($_GET['edit'] != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "menu` WHERE `id` = '".mysql_real_escape_string($_GET['edit'])."'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$tpl_page .= '<form action="?action=menuedit&edit='.$_GET['edit'].'" method="post"><label for="header">Header:</label><input type="text" name="header" value="'.$line['header'].'"/><br/><label for="body">Body:</label><textarea name="body" cols="60" rows="5">'.$line['body'].'</textarea><br/><label for="order">Order:</label><input type="text" name="order" value="'.$line['order'].'"/><br/>
							<input type="submit" name="editnews" value="Edit"/></form>';
					}
				}
				else {
					$tpl_page .= 'Invalid ID';
				}
			}
		}
			
		if(!$_GET['add'] && (!$_GET['edit'])) {
			$tpl_page .= 'To display a menu item above the boards, set the order to 0. If the menu fails to update, rebuild HTML files (any board) and clear cache.<br/><br/>';
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "menu` ORDER BY `id` DESC");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$tpl_page .= '<div style="border: 1px solid grey"><div style="background: #F0F0F0; border-bottom: 1px solid #BEBEBE; padding: 2px;"><b>'.$line['header'].'</b> ['.$line['order'].'] | <a href="manage_page.php?action=menuedit&edit='.$line['id'].'">Edit</a> | [<a href="manage_page.php?action=menuedit&delete='.$line['id'].'">x</a>]</div><br/>'.$line['body'].'</div><br/>';
				}
			}
			else {
				$tpl_page .= 'No menu items';
			}
			$tpl_page .= '<br/><div align="right">[ <a href="manage_page.php?action=menuedit&add=1">Add menu item</a> ]</div>';
		}
	}
	function managemods() {
		global $tc_db, $smarty, $tpl_page;
		if($this->CheckAccess() < 10) {
			exitWithErrorPage('You do not have permission to access this page');
		}
		$tpl_page .= '<h2>' . _gettext('Manage Moderators') . '</h2>';
		if (isset($_POST['staffusername']) && isset($_POST['staffpassword'])) {
			if ($_POST['staffusername'] != '' && ($_POST['staffpassword'] != '' || $_POST['type'] == '3')) {
				$this->AdministratorsOnly();
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . mysql_real_escape_string($_POST['staffusername']) . "'");
				if (count($results) == 0) {
					if ($_POST['type'] == '0' || $_POST['type'] == '1' || $_POST['type'] == '2' || $_POST['type'] == '3') {
						if(!is_numeric($_POST['access'])) {
							exitWithErrorPage('Invalid access level');
						}
						$length = strlen($_POST['staffpassword']);
						if($length < 7) {
							exitWithErrorPage('Password must be 7 characters or more');
						}
						$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "staff` ( `username` , `password` , `type` , `addedon` , `access`) VALUES ( '" . mysql_real_escape_string($_POST['staffusername']) . "' , '" . md5($_POST['staffpassword']) . "' , '" . $_POST['type'] . "' , '" . time() . "' , '" . $_POST['access'] . "' )");
					} else {
						exitWithErrorPage('Invalid type.');
					}
					$tpl_page .= _gettext('Staff member successfully added.');
					if ($_POST['type'] != 3) {
						$logentry = _gettext('Added staff member') . ' - ';
						if ($_POST['type'] == '1') {
							$logentry .= _gettext('Administrator');
						} elseif ($_POST['type'] == '2') {
							$logentry .= _gettext('Moderator');
						} elseif ($_POST['type'] == '0') {
							$logentry .= _gettext('Janitor');
						} else {
							$logentry .= 'VIP';
						}
						$logentry .= ": " . $_POST['staffusername'];
					} else {
						$logentry = 'Added a VIP code';
					}
					management_addlogentry($logentry, 6);
				} else {
					$tpl_page .= _gettext('A staff member with that ID already exists.');
				}
				$tpl_page .= '<hr>';
			}
		} elseif (isset($_GET['del'])) {
			$this->AdministratorsOnly();
			if ($_GET['del'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . mysql_real_escape_string($_GET['del']) . "'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$staff_username = $line['username'];
						$staff_type = $line['type'];
					}
					if($line['username'] == KU_ROOT) {
						exitWithErrorPage('Permission denied');
					}
					$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . mysql_real_escape_string($_GET['del']) . "'");
					$tpl_page .= _gettext('Staff successfully deleted');
					if ($staff_type != 3) {
						management_addlogentry(_gettext('Deleted staff member') . ': ' . $staff_username, 6);
					} else {
						management_addlogentry(_gettext('Deleted a VIP code'), 6);
					}
				} else {
					$tpl_page .= _gettext('Invalid staff ID.');
				}
				$tpl_page .= '<hr>';
			}
		} elseif (isset($_GET['suspend'])) {
			if ($_GET['suspend'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . mysql_real_escape_string($_GET['suspend']) . "'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$staff_username = $line['username'];
						$staff_type = $line['type'];
					}
					if($line['username'] == KU_ROOT) {
						exitWithErrorPage('Permission denied');
					}
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `suspended` = 1 WHERE `id` = '" . mysql_real_escape_string($_GET['suspend']) . "'");
					$tpl_page .= _gettext('<br/>Staff successfully suspended');
					if ($staff_type != 3) {
						management_addlogentry(_gettext('Suspended staff member') . ': ' . $staff_username, 6);
					} else {
						management_addlogentry(_gettext('Suspended a VIP code'), 6);
					}
				} else {
					$tpl_page .= _gettext('Invalid staff ID.');
				}
				$tpl_page .= '<hr>';
			}
		} elseif (isset($_GET['unsuspend'])) {
			if ($_GET['unsuspend'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . mysql_real_escape_string($_GET['unsuspend']) . "'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$staff_username = $line['username'];
						$staff_type = $line['type'];
					}
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `suspended` = 0 WHERE `id` = '" . mysql_real_escape_string($_GET['unsuspend']) . "'");
					$tpl_page .= _gettext('<br/>Staff successfully unsuspended');
					if ($staff_type != 3) {
						management_addlogentry(_gettext('Unsuspended staff member') . ': ' . $staff_username, 6);
					} else {
						management_addlogentry(_gettext('Unsuspended a VIP code'), 6);
					}
				} else {
					$tpl_page .= _gettext('Invalid staff ID.');
				}
				$tpl_page .= '<hr>';
			}
		} elseif (isset($_GET['edit'])) {
			if ($_GET['edit'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . mysql_real_escape_string($_GET['edit']) . "'");
				if (count($results) > 0) {
					foreach ($results as $line) {
							$staff_username = $line['username'];
							$staff_type = $line['type'];
						}
					if($staff_type == 1) {
						exitWithErrorPage('Permission denied');
					}	
					if($line['username'] == KU_ROOT) {
						exitWithErrorPage('Permission denied');
					}
					if($_POST['type'] == 1) {
						exitWithErrorPage('Permission denied');
					}
					if($staff_username == $_SESSION['manageusername']) {
						exitWithErrorPage('Permission denied');
					}
					if (isset($_POST['submitting'])) {
						if(isset($_POST['access'])) {
							if(!is_numeric($_POST['access'])) {
								exitWithErrorPage('Invalid access code');
							}
							if($_POST['access'] > 9) {
								exitWithErrorPage('Access level invalid');
							}
							else {
								$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `access` = '" . $_POST['access'] . "' WHERE `id` = '" . mysql_real_escape_string($_GET['edit']) . "'");
								$n_results = $tc_db->GetAll("SELECT HIGH_PRIORITY `username` FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . mysql_real_escape_string($_GET['edit']) . "' LIMIT 1");
								foreach($n_results as $line) {
								if($line['username'] == KU_ROOT) {
									exitWithErrorPage('Permission denied');
								}								
									management_addlogentry(_gettext('Updated access level to ') . $_POST['access'] . ' for ' . $line['username'], 6);
								}
							}
						}
						$staff_boards = array();
						if (isset($_POST['moderatesallboards'])) {
							$staff_new_boards = array('allboards');
						} else {
							$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
							foreach ($results as $line) {
								$staff_boards = array_merge($staff_boards, array($line['name']));
							}
							$staff_changed_boards = array();
							$staff_new_boards = array();
							while (list($postkey, $postvalue) = each($_POST)) {
								if (substr($postkey, 0, 8) == "moderate") {
									$staff_changed_boards = array_merge($staff_changed_boards, array(substr($postkey, 8)));
								}
							}
							while (list(, $staff_thisboard_name) = each($staff_boards)) {
								if (in_array($staff_thisboard_name, $staff_changed_boards)) {
									$staff_new_boards = array_merge($staff_new_boards, array($staff_thisboard_name));
								}
							}
						}
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `boards` = '" . mysql_real_escape_string(implode('|', $staff_new_boards)) . "' WHERE `id` = '" . mysql_real_escape_string($_GET['edit']) . "'");
						$tpl_page .= _gettext('Staff successfully updated') . '<hr>';
						if ($_POST['type'] != '3') {
							$logentry = _gettext('Updated staff member') . ' - ';
							if ($_POST['type'] == '1') {
								$logentry .= _gettext('Administrator');
							} elseif ($_POST['type'] == '2') {
								$logentry .= _gettext('Moderator');
							} elseif ($_POST['type'] == '0') {
								$logentry .= _gettext('Janitor');
							} else {
								exitWithErrorPage('Something went wrong.');
							}
							$logentry .= ': ' . $staff_username;
							if ($_POST['type'] != '1') {
								$logentry .= ' - ' . _gettext('Moderates') . ': ';
								if (isset($_POST['moderatesallboards'])) {
									$logentry .= strtolower(_gettext('All boards'));
								} else {
									$logentry .= '/' . implode('/, /', $staff_new_boards) . '/';
								}
							}
						} else {
							$logentry = _gettext('Edited a VIP code');
						}
						management_addlogentry($logentry, 6);
					}
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . $_GET['edit'] . "'");
					foreach ($results as $line) {
						$staff_username = $line['username'];
						$staff_type = $line['type'];
						$staff_boards = explode('|', $line['boards']);
					}
					$tpl_page .= '<br/><form action="manage_page.php?action=managemods&edit=' . $_GET['edit'] . '" method="post">
					
					<label for="staffname">' . _gettext('Username') . ':</label>
					<input type="text" name="staffname" value="' . $staff_username . '" disabled><br>
					
					<label for="type">' . _gettext('Type') . ':</label>
					<select name="type">
					<option value="2"';
					if ($staff_type == '2') {
						$tpl_page .= 'selected';
					}
					$tpl_page .= '>' . _gettext('Moderator') . '</option>
					<option value="0"';
					if ($staff_type == '0') {
						$tpl_page .= 'selected';
					}
					$tpl_page .= '>' . _gettext('Janitor') . '</option>
					<option value="3"';
					if ($staff_type == '3') {
						$tpl_page .= 'selected';
					}
					$tpl_page .= '>VIP</option>
					</select><br><br>';
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `access` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $staff_username . "'LIMIT 1");
						foreach ($results as $line) {
							$tpl_page .= '<label for="moderatesallboards">'._gettext('Access level [<a href="manage_page.php?action=managemods&accesshelp=1">?</a>]').':</label> <input type="text" maxlength="4" name="access" value="'.$line['access'].'"/><br/>';
						}
					$tpl_page .= _gettext('Moderates') . '<br>' .
					'<label for="moderatesallboards"><b>'._gettext('All boards').'</b></label>' .
					'<input type="checkbox" name="moderatesallboards"';
					if ($staff_boards == array('allboards')) {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '><br>' . _gettext('or') . '<br>';
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
					foreach ($results as $line) {
						$tpl_page .= '<label for="moderate' . $line['name'] . '">' . $line['name'] . '</label><input type="checkbox" name="moderate' . $line['name'] . '" ';
						if (in_array($line['name'], $staff_boards)) {
							$tpl_page .= 'checked ';
						}
						$tpl_page .= '><br>';
					}
					$tpl_page .= '<input type="submit" value="' . _gettext('Modify staff member') . '" name="submitting">
					
					</form>
					<br>';

				} else {
					$tpl_page .= _gettext('A staff member with that id does not appear to exist.');
				}
				$tpl_page .= '<hr>';
			}
		}
		if($_GET['accesshelp'] == 1) {
			$tpl_page .= '<span>Access levels are a feature to limit the functions which Moderators can access. For example, if you set
			an access level of 5; the moderator can access all functions below and including Manage stickes/lock threads (which corresponds to level 5).</span><ol><li>Recently uploaded images</li><li>View Reports</li><li>Delete thread/post</li><li>Delete/find all posts by IP</li><li>Manage stickes/lock threads</li>
			<li>View/Add/Remove bans</li><li>All boards ban</li><li>Site ban</li><li>Manage Boards (Super Moderator)</li></ol><br/>';
		}
		/*
		$tpl_page .= '<form action="manage_page.php?action=managemods" method="post">
		
		<label for="username">' . _gettext('Username') . ':</label>
		<input type="text" name="staffusername"><br>
	
		<label for="password">' . _gettext('Password') . ':</label>
		<input type="text" name="staffpassword"><br>
		
		<label for="type">' . _gettext('Type') . ':</label>
		<select name="type">
		<option value="1">' . _gettext('Administrator') . '</option>
		<option value="2" selected>' . _gettext('Moderator') . '</option>
		<option value="0">' . _gettext('Janitor') . '</option>
		<option value="3">VIP</option>
		</select><br>
		<label for="access">'._gettext('Access level [<a href="manage_page.php?action=managemods&accesshelp=1">?</a>]').':</label> <input type="text" maxlength="4" name="access" value="'.$line['access'].'"/><br/>
		<input type="submit" value="' .  _gettext('Add staff member') . '">
		
		</form>
		<hr><br/>';
		*/
		$tpl_page .= '<br>';
		
		$tpl_page .= '<table border="1" width="100%"><tr><th>' . _gettext('Username') . '</th><th>' . _gettext('Added on') . '</th><th>' . _gettext('Last active') . '</th><th>' . _gettext('Moderating boards') . '</th><th>&nbsp;</th></tr>' . "\n";
		$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><b>' . _gettext('Administrators') . '</b></font></td></tr>' . "\n";
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `type` = '1' ORDER BY `username` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['username'] . '</td><td>' . date("y/m/d(D)H:i", $line['addedon']) . '</td><td>';
				if ($line['lastactive'] == 0) {
					$tpl_page .= _gettext('Never');
				} elseif ((time() - $line['lastactive']) > 300) {
					$tpl_page .= timeDiff($line['lastactive'], false);
				} else {
					$tpl_page .= _gettext('Online now');
				}
				$tpl_page .= '</td><td>';
				if ($line['boards'] != '') {
					if ($line['boards'] == 'allboards') {
						$tpl_page .= 'All boards';
					} else {
						$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>';
					}
				} else {
					$tpl_page .= _gettext('All boards');
				}
			}
		} else {
			$tpl_page .= '<tr><td colspan="4">' . _gettext('None') . '</td></tr>' . "\n";
			
		}
		$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><b>' . _gettext('Head Moderator') . '</b></font></td></tr>' . "\n";
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `type` = '2' AND `suspended` != 1 AND `access` >= 10 ORDER BY `username` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['username'];
				$tpl_page .= '</td><td>' . date("y/m/d(D)H:i", $line['addedon']) . '</td><td>';
				if ($line['lastactive'] == 0) {
					$tpl_page .= _gettext('Never');
				} elseif ((time() - $line['lastactive']) > 300) {
					$tpl_page .= timeDiff($line['lastactive'], false);
				} else {
					$tpl_page .= _gettext('Online now');
				}
				$tpl_page .= '</td><td>';
				if ($line['boards'] != '') {
					if ($line['boards'] == 'allboards') {
						$tpl_page .= 'All boards';
					} else {
						$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>';
					}
				} else {
					$tpl_page .= _gettext('No boards');
				}
				//$tpl_page .= '</td><td>[<a href="?action=managemods&edit=' . $line['id'] . '">' . _gettext('Edit') . '</a>]&nbsp;[<a href="?action=managemods&suspend=' . $line['id'] . '">' . _gettext('Suspend') . '</a>]</tr>' . "\n";
			}
		} else {
			$tpl_page .= '<tr><td colspan="4">' . _gettext('None') . '</td></tr>' . "\n";
		}
		$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><b>' . _gettext('Super Moderators') . '</b></font></td></tr>' . "\n";
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `type` = '2' AND `suspended` != 1 AND `access` = 9 ORDER BY `username` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['username'];
				$tpl_page .= '</td><td>' . date("y/m/d(D)H:i", $line['addedon']) . '</td><td>';
				if ($line['lastactive'] == 0) {
					$tpl_page .= _gettext('Never');
				} elseif ((time() - $line['lastactive']) > 300) {
					$tpl_page .= timeDiff($line['lastactive'], false);
				} else {
					$tpl_page .= _gettext('Online now');
				}
				$tpl_page .= '</td><td>';
				if ($line['boards'] != '') {
					if ($line['boards'] == 'allboards') {
						$tpl_page .= 'All boards';
					} else {
						$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>';
					}
				} else {
					$tpl_page .= _gettext('No boards');
				}
				$tpl_page .= '</td><td>[<a href="?action=managemods&edit=' . $line['id'] . '">' . _gettext('Edit') . '</a>]&nbsp;[<a href="?action=managemods&suspend=' . $line['id'] . '">' . _gettext('Suspend') . '</a>]' . "\n";
			}
		} else {
			$tpl_page .= '<tr><td colspan="4">' . _gettext('None') . '</td></tr>' . "\n";
		}
		$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><b>' . _gettext('Moderators') . '</b></font></td></tr>' . "\n";
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `type` = '2' AND `suspended` != 1 AND `access` < 9 ORDER BY `username` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['username'];
				$access = $tc_db->GetAll("SELECT HIGH_PRIORITY `access` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '". $line['username'] ."' AND `access` >= 9");
				foreach($access as $ac) {
					$tpl_page .= ' [<a style="color: red; text-decoration: none" href="manage_page.php?action=staff&supermod=1">*</a>]';
				}
				$tpl_page .= '</td><td>' . date("y/m/d(D)H:i", $line['addedon']) . '</td><td>';
				if ($line['lastactive'] == 0) {
					$tpl_page .= _gettext('Never');
				} elseif ((time() - $line['lastactive']) > 300) {
					$tpl_page .= timeDiff($line['lastactive'], false);
				} else {
					$tpl_page .= _gettext('Online now');
				}
				$tpl_page .= '</td><td>';
				if ($line['boards'] != '') {
					if ($line['boards'] == 'allboards') {
						$tpl_page .= 'All boards';
					} else {
						$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>';
					}
				} else {
					$tpl_page .= _gettext('No boards');
				}
				$tpl_page .= '</td><td>[<a href="?action=managemods&edit=' . $line['id'] . '">' . _gettext('Edit') . '</a>]&nbsp;[<a href="?action=managemods&suspend=' . $line['id'] . '">' . _gettext('Suspend') . '</a>]</tr>' . "\n";
			}
		} else {
			$tpl_page .= '<tr><td colspan="4">' . _gettext('None') . '</td></tr>' . "\n";
		}
		$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><b>' . _gettext('Janitors') . '</b></font></td></tr>' . "\n";
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `type` = '0' AND `suspended` != 1 ORDER BY `username` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['username'] . '</td><td>' . date("y/m/d(D)H:i", $line['addedon']) . '</td><td>';
				if ($line['lastactive'] == 0) {
					$tpl_page .= _gettext('Never');
				} elseif ((time() - $line['lastactive']) > 300) {
					$tpl_page .= timeDiff($line['lastactive'], false);
				} else {
					$tpl_page .= _gettext('Online now');
				}
				$tpl_page .= '</td><td>';
				if ($line['boards'] != '') {
					if ($line['boards'] == 'allboards') {
						$tpl_page .= 'All boards';
					} else {
						$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>';
					}
				} else {
					$tpl_page .= _gettext('No boards');
				}
				$tpl_page .= '</td><td>[<a href="?action=managemods&edit=' . $line['id'] . '">' . _gettext('Edit') . '</a>]&nbsp;[<a href="?action=managemods&suspend=' . $line['id'] . '">' . _gettext('Suspend') . '</a>]</td></tr>' . "\n";
			}
		} else {
			$tpl_page .= '<tr><td colspan="5">' . _gettext('None') . '</td></tr>' . "\n";
		}
				$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><b>' . _gettext('Suspended') . '</b></font></td></tr>' . "\n";
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `suspended` = 1 ORDER BY `username` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['username'] . '</td><td>' . date("y/m/d(D)H:i", $line['addedon']) . '</td><td>';
				if ($line['lastactive'] == 0) {
					$tpl_page .= _gettext('Never');
				} elseif ((time() - $line['lastactive']) > 300) {
					$tpl_page .= timeDiff($line['lastactive'], false);
				} else {
					$tpl_page .= _gettext('Online now');
				}
				$tpl_page .= '</td><td>';
				if ($line['boards'] != '') {
					if ($line['boards'] == 'allboards') {
						$tpl_page .= 'All boards';
					} else {
						$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>';
					}
				} else {
					$tpl_page .= _gettext('No boards');
				}
				$tpl_page .= '</td><td>[<a href="?action=managemods&edit=' . $line['id'] . '">' . _gettext('Edit') . '</a>]&nbsp;[<a href="?action=managemods&unsuspend=' . $line['id'] . '">' . _gettext('Unsuspend') . '</a>]</td></tr>' . "\n";
			}
		} else {
			$tpl_page .= '<tr><td colspan="4">' . _gettext('None') . '</td></tr>' . "\n";
		}
		$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><b>VIP</b></font></td></tr>' . "\n";
		$tpl_page .= '<tr><th>' . _gettext('Posting password') . '</th><th colspan="3">' . _gettext('Added on') . '</th><th>&nbsp;</th>' . "\n";;
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `type` = '3' ORDER BY `username` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['username'] . '</td><td colspan="2">' . date("y/m/d(D)H:i", $line['addedon']) . '</td><td>[<a href="?action=staff&edit=' . $line['id'] . '">' . _gettext('Edit') . '</a>]&nbsp;[<a href="?action=staff&del=' . $line['id'] . '">x</a>]</td></tr>' . "\n";
			}
		} else {
			$tpl_page .= '<tr><td colspan="5">' . _gettext('None') . '</td></tr>' . "\n";
		}
		$tpl_page .= '</table>';
	}
	function Notice() {
		global $tc_db, $smarty, $tpl_page;
		if($this->CheckAccess() < 10) {
			exitWithErrorPage('You do not have permission to access this page');
		}			
		$tpl_page .= '<h2>' . ucwords(_gettext('Notice')) . '</h2><br>';
		$tpl_page .= 'Send a notice to a staff member';
		if($_GET['send'] == 1) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `username` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '".mysql_real_escape_string($_POST['staff'])."'");
			if(count($results) > 0) {
				if($_POST['notice'] != "" && $_POST['notice'] != "Message") {
					$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "notice` ( `name` , `notice`) VALUES ('".mysql_real_escape_string($_POST['staff'])."', '".mysql_real_escape_string($_POST['notice'])
					."')");
					$tpl_page .= '<br/><b>Message sent to '. htmlentities($_POST['staff']) .'</b><br/>';
					management_addlogentry(_gettext('Sent notice to ' . htmlentities($_POST['staff'])), 1);
					
				}
				else {
					exitWithErrorPage('Enter a message');
				}
			}
			else {
				exitWithErrorPage('Staff member does not exist');
			}
		}
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `username` FROM `" . KU_DBPREFIX . "staff` ORDER BY `username`");		
		$staffnames = array();
		foreach($results as $result) {
			$staffnames = array_merge($staffnames, array($result['username']));
		}
		$tpl_page .= '<form action="?action=notice&send=1" method="post"><br/>Username: ' .
		$this->MakeBoardListStaff('staff', $staffnames) .
		'<br/><br/><textarea name="notice" cols="70" rows="5">Message</textarea><br/><input type="submit" value="'._gettext('Send').'">
		</form>';
		$tpl_page .= '<br/><h2>Sent Notices</h2><br/>';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "notice`");
		if(count($results) > 0) {
			foreach($results as $result) {
				$tpl_page .= '<div style="border: 1px solid grey; padding: 5px"><span style="color: grey">To:</span> ' . $result['name']; 
				if($result['read'] == 0) {
					$tpl_page .= ' <small><b>(Unread)</b></small>';
				}
				else { 
					$tpl_page .= ' <small><span style="color: grey">(Read)</span></small>';
				}
				$tpl_page .= '<br/> ' . $result['notice'] . '';
				$tpl_page .= '<br/></div><br/>';
			}
		}					
	}
	function announcement() {
		global $tc_db, $smarty, $tpl_page, $action;
		$this->ModeratorsOnly();
		$tpl_page .= '<h2>' . _gettext('Announcements') . '</h2><br>';
	        $results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "notice` WHERE `name` = '".$_SESSION['manageusername']."' AND `read` = 0");
		if($_GET['read']) {
			if(!is_numeric($_GET['read'])) {
				die();
			}
			$readz = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "notice` WHERE `id` = '".mysql_real_escape_string($_GET['read'])."' LIMIT 1");
			if(count($readz) > 0) {
				$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "notice` SET `read` = 1 WHERE `id` = '".mysql_real_escape_string($_GET['read'])."'");
			}
			header("Location: manage_page.php?action=announcement");
		}
		if(count($results) > 0) {
				$tpl_page .= '<div style="border: 1px solid red; padding: 5px; color: red; font-weight: bold">Attention: You have unread notices!</div><br/>';
				foreach($results as $result) {
					$tpl_page .= '<div style="border: 1px solid grey; padding: 2px">' . $result['notice'] . ' [<a href="manage_page.php?action=announcement&read='.$result['id'].'">OK</a>]</div><br/>';
				}
		}
		if($_GET['add']) {
			$this->AdministratorsOnly();
			$tpl_page .= '<form action="?action=announcement" method="post">';
			$tpl_page .= '<b>Add item</b><br/>
			This message will be displayed to all staff members. Format your announcement with HTML code.<br/>
			<br/>
			<label for="name">Name:</label><input type="text" name="name" length="20" maxlength="20" value="'. $_SESSION['manageusername'] .'" readonly="readonly"/><br/><label for"displayname" style="font-size: 9px">Display name:</label> <input type="checkbox" name="displayname" checked="checked"/><br/><label for="subject">Subject:</label><input type="text" name="subject" length="50"/><br/><label for="news_item">News:</label><textarea name="news_item" rows="15" cols="50"></textarea><input type="submit" value="Add News"/></form><br/>';
		}
		if($_GET['delete']) {
			$this->AdministratorsOnly();
			if($_GET['delete'] != '') {
				if($tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "announcements` WHERE `id` = '" . mysql_real_escape_string($_GET['delete']) . "'")) {
					$tpl_page .= 'News post deleted<br/><br/>';
					management_addlogentry(_gettext('Deleted an announcement entry'), 9);
				}
			}
		}
		if(isset($_POST['news_item'])) {
			$this->AdministratorsOnly();
			if($_POST['news_item'] == '' || $_POST['subject'] == '') {
				exitWithErrorPage(_gettext('Please fill in all fields.'));
			}
			if($_POST['name'] != $_SESSION['manageusername']) {
				die('Invalid name');
			}
			if(!isset($_POST['displayname'])) {
				$_POST['name'] = '';
			}
			if($tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "announcements` ( `name`, `subject` , `news` , `postedat` , `postedby`) VALUES ( '" . mysql_real_escape_string($_POST['name']) . "' , '" . mysql_real_escape_string($_POST['subject']) . "' , '" . mysql_real_escape_string($_POST['news_item']) . "' , '" . time() . "' , '" . mysql_real_escape_string($_SESSION['manageusername']) . "')")) {		
				management_addlogentry(_gettext('Added an announcement entry'), 9);
				$tpl_page .= (_gettext('News item successfully added.')) . '<br/><br/>';
			}
			else {
				exitWithErrorPage(_gettext('Item was not added ' . mysql_error()));
			}			
		}
		if($_GET['commentdelete']) {
			$this->AdministratorsOnly();
			$_GET['commentdelete'] = urldecode($_GET['commentdelete']);
			if(!is_numeric($_GET['id'])) {
				exitWithErrorPage(_gettext('Invalid ID'));
			}
			else {
				$results = $tc_db->GetAll ("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "announcements` WHERE `id` = '". mysql_real_escape_string($_GET['id'])."'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$line['comments'] = str_replace($_GET['commentdelete'], "", $line['comments']);
					}
					if($tc_db->Execute("UPDATE `" . KU_DBPREFIX . "announcements` SET `comments` = '".mysql_real_escape_string($line['comments'])."' WHERE `id` = '". mysql_real_escape_string($_GET['id'])."'")) {
						$tpl_page .= 'Comment deleted';
					}
					else {
						$tpl_page .= 'Failed to delete: ' . mysql_error();
					}
				}
				else {
					$tpl_page .= 'Invalid ID';
				}
			}
		}
		if($_GET['comment']) {
			if(isset($_POST['addcomment'])) {
				$_POST['addcomment'] = str_replace("|", "-", $_POST['addcomment']);
				$_POST['addcomment'] = str_replace(":", ";", $_POST['addcomment']);
				if($tc_db->Execute("UPDATE `" . KU_DBPREFIX . "announcements` SET `comments` = CONCAT( `comments`, '". mysql_real_escape_string($_POST['addcomment']) . '|' . $_POST['commentid'] . '|' . $_SESSION['manageusername'] . '|' . time() . ':' . "') WHERE `id` = '". $_POST['commentid']."'")) {
					$tpl_page .= 'Comment added!<br/><br/>';
					management_addlogentry(_gettext('Added an announcement comment'), 9);
				}
				else {
					die(mysql_error());
				}
			}
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "announcements` WHERE `id` = '". mysql_real_escape_string($_GET['comment'])."' ORDER BY `id` DESC");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$tpl_page .= '<div style="border: 1px solid #888"><div style="padding: 5px;background:#E0E0E0;"><b>'.$line['subject'].'</b> | '.date('F j, Y, g:i a', $line['postedat']).'';
					if($line['name'] != '') {
						$tpl_page .= ' by '.$line['name'].' [<a href="manage_page.php?action=announcement&delete='.$line['id'].'">x</a>]</div>';
					}
					else {
						$tpl_page .= ' [<a href="manage_page.php?action=announcement&delete='.$line['id'].'">x</a>]</div>';
					}
					$tpl_page .= '<div style="padding: 5px;">'.$line['news'].'</div></div><br/>';
					if($line['comments'] == '') {
						$tpl_page .= 'No comments<br/>';
					}
					else {
						$comment = explode(":", $line['comments']);
						foreach($comment as $comment2) {
							if($comment2 != '') {
								$comment3 = explode("|", $comment2);
								$tpl_page .= '<div style="border: 1px solid #999;"><div style="background-color: #F0F0F0; padding: 4px;"><strong>'.$comment3[2] . '</strong> ('.date('F j, Y, g:i a', $comment3[3]).') [<a href="manage_page.php?action=announcement&commentdelete='.urlencode($comment2).'&id='.$line['id'].'">x</a>]</div><div style="padding: 4px;">' . htmlspecialchars($comment3[0]).'</div></div><br/>';
							}
						}
					}
					$tpl_page .= '<br/><form action="?action=announcement&comment='.$line['id'].'" method="post"><input type="hidden" value="'.$_GET['comment'].'" name="commentid"/><textarea name="addcomment" rows="4" cols="50"></textarea><input type="submit" name="submitcomment" value="Add"/></form><br/>';
				}
			}
			else {
				$tpl_page .= 'Invalid comment ID';
			}
		}
		if(!($_GET['add']) && !($_GET['comment']) && !($_GET['commentdelete'])) {			
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "announcements` WHERE `id` != 91 ORDER BY `id` DESC");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$tpl_page .= '<div style="border: 1px solid #888"><div style="padding: 5px;background:#E0E0E0;"><b>'.$line['subject'].'</b> | '.date('F j, Y, g:i a', $line['postedat']).'';
					if($line['name'] != '') {
						$tpl_page .= ' by '.$line['name'].' [<a href="manage_page.php?action=announcement&delete='.$line['id'].'">x</a>]</div>';
					}
					else {
						$tpl_page .= ' [<a href="manage_page.php?action=announcement&delete='.$line['id'].'">x</a>]</div>';
					}
					$tpl_page .= '<div style="padding: 5px;">'.$line['news'].'</div></div>';
					$comment = explode(":", $line['comments']);
					$i = 0;
					foreach($comment as $comment2) {
						if($comment2 != '') {
							$i++;
						}
					}
					if($i == 0) {
						$tpl_page .=  '<div align="right" style="padding: 3px"><a href="manage_page.php?action?announcement&comment='.$line['id'].'" style="color: grey">No comments</a></div><br/><br/>';
					}
				    else {
						$tpl_page .=  '<div align="right" style="padding: 3px"><a href="manage_page.php?action?announcement&comment='.$line['id'].'">Comments ('.$i.')</a></div><br/><br/>';
					}				
				}
			} else {
				$tpl_page .= 'No news feed.';
			}
			if($this->CurrentUserIsAdministrator()) {
				$tpl_page .= '<p style="text-align: right">[<a href="manage_page.php?action=announcement&add=1">Add item</a>]</p>';
			}
		}
	}
	/* Add, edit, delete, and view news entries */
	function news() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		if (isset($_GET['edit'])) {
		$tpl_page .= '<h2>' . _gettext('Edit news post') . '</h2><br>';
			if (isset($_POST['news'])) {
				$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "news` SET `subject` = '" . mysql_real_escape_string($_POST['subject']) . "', `message` = '" . mysql_real_escape_string($_POST['news']) . "', `postedemail` = '" . mysql_real_escape_string($_POST['email']) . "' WHERE `id` = '" . mysql_real_escape_string($_GET['edit']) . "'");
				$tpl_page .= '<span>News post edited</span><br/><br/>';
				management_addlogentry(_gettext('Edited a news entry'), 9);
			}			
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "news` WHERE `id` = '" . mysql_real_escape_string($_GET['edit']) . "'");
			foreach ($results as $line) {
			$tpl_page .= '<form method="post" action="?action=news&edit=' . $_GET['edit'] . '">
			<label for="subject">' . _gettext('Subject') . ':</label>
			<input type="text" name="subject" value="' . $line['subject'] . '">
			<div class="desc">' . _gettext('Can not be left blank.') . '</div><br>
			
			<textarea name="news" rows="25" cols="80">' . $line['message'] . '</textarea><br>
			
			<label for="email">' . _gettext('E-mail') . ':</label>
			<input type="text" name="email" value="' . $line['postedemail'] . '">
			<div class="desc">' . _gettext('Can be left blank.') . '</div><br>
			<input type="submit" value="Edit">
			</form><br/>';
			}
		} elseif (isset($_GET['delete'])) {
			$results = $tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "news` WHERE `id` = '" . mysql_real_escape_string($_GET['delete']) . "'");
			$tpl_page .= '<h2>' . _gettext('Delete news post') . '</h2><br>';
			$tpl_page .= '<span>News post deleted</span>';
			management_addlogentry(_gettext('Deleted a news entry'), 9);
		} else {
			$tpl_page .= _gettext('<h2>Add News Post</h2>This message will be displayed as it is written, so make sure you add the proper HTML.') . '<br><br>';
			if (isset($_POST['news']) && isset($_POST['subject']) && isset($_POST['email'])) {
				if ($_POST['news'] != '') {
					$tpl_page .= '<hr>';
					if ($_POST['subject'] != '') {
						$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "news` ( `subject` , `message` , `postedat` , `postedby` , `postedemail` ) VALUES ( '" . mysql_real_escape_string($_POST['subject']) . "' , '" . mysql_real_escape_string($_POST['news']) . "' , '" . time() . "' , '" . mysql_real_escape_string($_SESSION['manageusername']) . "' , '" . mysql_real_escape_string($_POST['email']) . "' )");
						$tpl_page .= '<h3>' . _gettext('News entry successfully added.') . '</h3>';
						management_addlogentry(_gettext('Added a news entry'), 9);
					} else {
						$tpl_page .= _gettext('You must enter a subject.');
					}
					$tpl_page .= '<hr>';
				}
			}
			$tpl_page .= '<form method="post" action="?action=news">
			<label for="subject">' . _gettext('Subject') . ':</label>
			<input type="text" name="subject" value="">
			<div class="desc">' . _gettext('Can not be left blank.') . '</div><br>
			
			<textarea name="news" rows="25" cols="80"></textarea><br>
			
			<label for="email">' . _gettext('E-mail') . ':</label>
			<input type="text" name="email" value="">
			<div class="desc">' . _gettext('Can be left blank.') . '</div><br>
			
			<input type="submit" value="' . _gettext('Add') . '">
			</form><br/>';
			
			$tpl_page .= '<br><hr><h1>Edit/Delete News</h1>';
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "news` ORDER BY `id` DESC");
			if (count($results) > 0) {
				$tpl_page .= '<table border="1" width="100%"><tr><th>Date Added</th><th>Subject</th><th>Message</th><th>Edit/Delete</th></tr>';
				foreach ($results as $line) {
					$tpl_page .= '<tr><td>' . date('F j, Y, g:i a', $line['postedat']) . '</td><td>' . $line['subject'] . '</td><td>' . $line['message'] . '</td><td><a href="?action=news&edit=' . $line['id'] . '">Edit</a>/<a href="?action=news&delete=' . $line['id'] . '">Delete</a></td></tr>';
				}
				$tpl_page .= '</table>';
			} else {
				$tpl_page .= 'No news posts yet.';
			}
		}
	}
	
	function blotter() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		if (!KU_BLOTTER) {
			exitWithErrorPage(_gettext('Blotter is disabled.'));
		}
		$tpl_page .= '<h1>' . _gettext('Blotter') . '</h1>';
		
		if (isset($_POST['message'])) {
			$save_important = (isset($_POST['important'])) ? '1' : '0';
			
			if (isset($_POST['edit'])) {
				$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "blotter` SET `message` = '" . mysql_real_escape_string($_POST['message']) . "', `important` = '" . $save_important . "' WHERE `id` = '" . mysql_real_escape_string($_POST['edit']) . "'");
				$tpl_page .= '<h3>' . _gettext('Blotter entry updated.') . '</h3>';
			} else {
				$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "blotter` (`at`, `message`, `important`) VALUES ('" . time() . "', '" . mysql_real_escape_string($_POST['message']) . "', '" . $save_important . "')");
				$tpl_page .= '<h3>' . _gettext('Blotter entry added.') . '</h3>';
			}
			clearBlotterCache();
		} elseif (isset($_GET['delete'])) {
			$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "blotter` WHERE `id` =  '" . mysql_real_escape_string($_GET['delete']) . "'");
			clearBlotterCache();
			$tpl_page .= '<h3>' . _gettext('Blotter entry deleted.') . '</h3>';
		}
		
		$edit_id = '';
		$edit_message = '';
		$edit_important = '';
		if (isset($_GET['edit'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "blotter` WHERE `id` = '" . mysql_real_escape_string($_GET['edit']) . "' LIMIT 1");
			foreach ($results as $line) {
				$edit_id = $line['id'];
				$edit_message = $line['message'];
				$edit_important = $line['important'];
			}
		}
		
		$tpl_page .= '<form action="?action=blotter" method="post">';
		if ($edit_id != '') {
			$tpl_page .= '<input type="hidden" name="edit" value="' . $edit_id . '">';
		}
		$tpl_page .= '<label for="message">' . _gettext('Message') . ':</label>
		<input type="text" name="message" value="' . $edit_message . '" size="75"><br>
		
		<label for="important">' . _gettext('Important') . ':</label>
		<input type="checkbox" name="important"';
		if ($edit_important == 1) {
			$tpl_page .= ' checked';
		}
		$tpl_page .= '><br>
		
		<input type="submit" value="';
		if ($edit_id != '') {
			$tpl_page .= _gettext('Edit');
		} else {
			$tpl_page .= _gettext('Add new blotter entry');
		}
		$tpl_page .= '">';
		if ($edit_id != '') {
			$tpl_page .= '&nbsp;&nbsp;<a href="?action=blotter">' . _gettext('Cancel') . '</a>';
		}
		$tpl_page .= '<br>
		
		</form><br><br>';
		
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "blotter` ORDER BY `id` DESC");
		if (count($results) > 0) {
			$tpl_page .= '<table border="1" width="100%"><tr><th>' . _gettext('At') . '</th><th>' . _gettext('Message') . '</th><th>' . _gettext('Important') . '</th><th>&nbsp;</th></tr>';
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . date('m/d/y', $line['at']) . '</td><td>' . $line['message'] . '</td><td>';
				if ($line['important'] == 1) {
					$tpl_page .= _gettext('Yes');
				} else {
					$tpl_page .= _gettext('No');
				}
				$tpl_page .= '</td><td><a href="?action=blotter&edit=' . $line['id'] . '">Edit</a>/<a href="?action=blotter&delete=' . $line['id'] . '">Delete</a></td></tr>';
			}
		} else {
			$tpl_page .= '<tr><td colspan="4">' . _gettext('No blotter entries.') . '</td></tr>';
		}
		$tpl_page .= '</table>';
	}
	
	/* Edit a boards options */
	function boardopts() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		
		$tpl_page .= '<h2>' . ucwords(_gettext('Board options')) . '</h2><br>';
		if (isset($_SESSION['updateboard']) && isset($_POST['order']) && isset($_POST['maxpages']) && isset($_POST['maxage']) && isset($_POST['messagelength'])) {
			$_POST['updateboard'] = $_SESSION['updateboard'];
			if (!$this->CurrentUserIsModeratorOfBoard($_POST['updateboard'], $_SESSION['manageusername'])) {
				exitWithErrorPage(_gettext('You are not a moderator of this board.'));
			}
			$boardid = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysql_real_escape_string($_POST['updateboard']) . "' LIMIT 1");
			if ($boardid != '') {
				if ($_POST['order'] >= 0 && $_POST['maxpages'] >= 0 && $_POST['markpage'] >= 0 && $_POST['maxage'] >= 0 && $_POST['messagelength'] >= 0 && ($_POST['defaultstyle'] == '' || in_array($_POST['defaultstyle'], explode(':', KU_STYLES)) || in_array($_POST['defaultstyle'], explode(':', KU_TXTSTYLES)))) {
					$filetypes = array();
					while (list($postkey, $postvalue) = each($_POST)) {
						if (substr($postkey, 0, 9) == 'filetype_') {
							$filetypes[] = substr($postkey, 9);
						}
					}
					$updateboard_enablecatalog = isset($_POST['enablecatalog']) ? '1' : '0';
					$updateboard_enablenofile = isset($_POST['enablenofile']) ? '1' : '0';
					$updateboard_redirecttothread = isset($_POST['redirecttothread']) ? '1' : '0';
					$updateboard_enablereporting = isset($_POST['enablereporting']) ? '1' : '0';
					$updateboard_enablecaptcha = isset($_POST['enablecaptcha']) ? '1' : '0';
					$updateboard_enablefaptcha = isset($_POST['enablefaptcha']) ? '1' : '0';
					$updateboard_enableporn = isset($_POST['enableporn']) ? '1' : '0';
					$updateboard_forcedanon = isset($_POST['forcedanon']) ? '1' : '0';
					$updateboard_trial = isset($_POST['trial']) ? '1' : '0';
					$updateboard_popular = isset($_POST['popular']) ? '1' : '0';
					$updateboard_enablearchiving = isset($_POST['enablearchiving']) ? '1' : '0';
					$updateboard_enableusermod = isset($_POST['enableusermod']) ? '1' : '0';
					$updateboard_showid = isset($_POST['showid']) ? '1' : '0';
					$updateboard_compactlist = isset($_POST['compactlist']) ? '1' : '0';
					$updateboard_locked = isset($_POST['locked']) ? '1' : '0';
					//$_POST['includeheader'] = '';
					if (($_POST['type'] == '0' || $_POST['type'] == '1' || $_POST['type'] == '2' || $_POST['type'] == '3') && ($_POST['uploadtype'] == '0' || $_POST['uploadtype'] == '1' || $_POST['uploadtype'] == '2')) {
						if (!($_POST['uploadtype'] != '0' && $_POST['type'] == '3')) {
							$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "boards` SET `type` = '" . mysql_real_escape_string($_POST['type']) . "' , `uploadtype` = '" . mysql_real_escape_string($_POST['uploadtype']) . "' , `order` = '" . mysql_real_escape_string($_POST['order']) . "' , `section` = '" . mysql_real_escape_string($_POST['section']) . "' , `desc` = '" . mysql_real_escape_string($_POST['desc']) . "' , `locale` = '" . mysql_real_escape_string($_POST['locale']) . "' , `showid` = '" . $updateboard_showid . "' , `compactlist` = '" . $updateboard_compactlist . "' , `locked` = '" . $updateboard_locked . "' , `maximagesize` = '" . mysql_real_escape_string($_POST['maximagesize']) . "' , `messagelength` = '" . mysql_real_escape_string($_POST['messagelength']) . "' , `maxpages` = '" . mysql_real_escape_string($_POST['maxpages']) . "' , `maxage` = '" . mysql_real_escape_string($_POST['maxage']) . "' , `markpage` = '" . mysql_real_escape_string($_POST['markpage']) . "' , `maxreplies` = '" . mysql_real_escape_string($_POST['maxreplies']) . "' , `image` = '" . mysql_real_escape_string($_POST['image']) . "' , `includeheader` = '" . mysql_real_escape_string($_POST['includeheader']) . "' , `redirecttothread` = '" . $updateboard_redirecttothread . "' , `anonymous` = '" . mysql_real_escape_string($_POST['anonymous']) . "' , `forcedanon` = '" . $updateboard_forcedanon . "' , `trial` = '" . $updateboard_trial . "' , `popular` = '" . $updateboard_popular . "' , `defaultstyle` = '" . mysql_real_escape_string($_POST['defaultstyle']) . "' , `enablereporting` = '" . $updateboard_enablereporting . "' , `enablecaptcha` = '" . $updateboard_enablecaptcha . "' , `enablefaptcha` = '" . $updateboard_enablefaptcha . "' , `enableporn` = '" . $updateboard_enableporn . "' , `enablenofile` = '" . $updateboard_enablenofile . "' , `enablearchiving` = '" . $updateboard_enablearchiving . "', `enableusermod` = '". $updateboard_enableusermod ."', `enablecatalog` = '" . $updateboard_enablecatalog . "' , `loadbalanceurl` = '" . mysql_real_escape_string($_POST['loadbalanceurl']) . "' , `loadbalancepassword` = '" . mysql_real_escape_string($_POST['loadbalancepassword']) . "' WHERE `name` = '" . mysql_real_escape_string($_POST['updateboard']) . "'");
							$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "board_filetypes` WHERE `boardid` = '" . $boardid . "'");
							foreach ($filetypes as $filetype) {
								$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "board_filetypes` ( `boardid`, `typeid` ) VALUES ( '" . $boardid . "', '" . mysql_real_escape_string($filetype) . "' )");
							}
							require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
							if(isset($_POST['run_cleanup'])) {
								if(!$this->delspecorphanreplies(mysql_real_escape_string($_POST['updateboard']))) {
									exitWithErrorPage(_gettext('Could not delete orphan replies'));
								}
								if(!$this->delspecunusedimages(mysql_real_escape_string($_POST['updateboard']))) {
									exitWithErrorPage(_gettext('Could not delete unused images'));
								}
								$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "posts_" . mysql_real_escape_string($_POST['updateboard']) . "` WHERE `IS_DELETED` = 1 AND `deletedat` < " . (time() - 604800) . "");
								management_addlogentry(_gettext('Ran cleanup') . " - /" . $_POST['updateboard'] . "/", 4);
							}							
							$menu_class = new Menu();
							$menu_class->Generate();
							if (isset($_POST['submit_regenerate'])) {
								$board_class = new Board($_POST['updateboard']);
								$board_class->RegenerateAll();
							}
							$tpl_page .= _gettext('Update successful.');
							management_addlogentry(_gettext('Updated board configuration') . " - /" . $_POST['updateboard'] . "/", 4);
							unset($_SESSION['updateboard']);
						} else {
							$tpl_page .= _gettext('Sorry, embed may only be enabled on normal imageboards.');
						}
					} else {
						$tpl_page .= _gettext('Sorry, a generic error has occurred.');
					}
				} else {
					$tpl_page .= _gettext('Integer values must be entered correctly.');
				}
			} else {
				$tpl_page .= _gettext('Unable to locate a board named') . ' <b>' . $_POST['updateboard'] . '</b>.';
			}
		} elseif (isset($_POST['board'])) {
			if (!$this->CurrentUserIsModeratorOfBoard($_POST['board'], $_SESSION['manageusername'])) {
				exitWithErrorPage(_gettext('You are not a moderator of this board.'));
			}
			$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysql_real_escape_string($_POST['board']) . "'");
			if (count($resultsboard) > 0) {
				foreach ($resultsboard as $lineboard) {
					$tpl_page .= '<div class="container">
					<form action="?action=boardopts" method="post">';
					/* Directory */
					$tpl_page .= '<label for="board">'._gettext('Directory').':</label>
					<input type="text" name="board" value="'.$_POST['board'].'" disabled>
					<div class="desc">'._gettext('The directory of the board.').'</div><br>';
				    $_SESSION['updateboard'] = $_POST['board'];
					
					/* Description */
					$tpl_page .= '<label for="desc">'._gettext('Description').':</label>
					<input type="text" name="desc" value="'.$lineboard['desc'].'">
					<div class="desc">'._gettext('The name of the board.').'</div><br>';
					
					/* Locale */
					$tpl_page .= '<label for="locale">Locale:</label>
					<input type="text" name="locale" value="'.$lineboard['locale'].'">
					<div class="desc">Locale to use on this board.  Leave blank to use the locale defined in config.php</div><br>';
					
					/* Board type */
					$tpl_page .= '<label for="type">'._gettext('Board type:').'</label>
					<select name="type">
					<option value="0"';
					if ($lineboard['type'] == '0') { $tpl_page .= ' selected'; }
					$tpl_page .= '>'._gettext('Normal imageboard').'</option>
					<option value="1"';
					if ($lineboard['type'] == '1') { $tpl_page .= ' selected'; }
					$tpl_page .= '>'._gettext('Text board').'</option><option value="2"';
					if ($lineboard['type'] == '2') { $tpl_page .= ' selected'; }
					$tpl_page .= '>'._gettext('Oekaki imageboard').'</option><option value="3"';
					if ($lineboard['type'] == '3') { $tpl_page .= ' selected'; }
					$tpl_page .= '>'._gettext('Upload imageboard').'</option>
					</select>
					<div class="desc">'._gettext('The type of posts which will be accepted on this board.  A normal imageboard will feature image and extended format posts, a text board will have no images, an Oekaki board will allow users to draw images and use them in their posts, and an Upload imageboard will be styled more towards file uploads.').' '._gettext('Default').': <b>Normal Imageboard</b></div><br>';
					
					/* Upload type */
					$tpl_page .= '<label for="uploadtype">'._gettext('Upload type:').'</label>
					<select name="uploadtype">
					<option value="0"';
					if ($lineboard['uploadtype'] == '0') {
						$tpl_page .= ' selected';
					}
					$tpl_page .= '>'._gettext('No embedding').'</option>
					<option value="1"';
					if ($lineboard['uploadtype'] == '1') {
						$tpl_page .= ' selected';
					}
					$tpl_page .= '>'._gettext('Images and embedding').'</option>
					<option value="2"';
					if ($lineboard['uploadtype'] == '2') {
						$tpl_page .= ' selected';
					}
					$tpl_page .= '>'._gettext('Embedding only').'</option>
					</select>
					<div class="desc">'._gettext('Whether or not to allow embedding of videos.').' '._gettext('Default').'.: <b>No Embedding</b></div><br>';
					
					/* Order */
					$tpl_page .= '<label for="order">'._gettext('Order').':</label>
					<input type="text" name="order" value="'.$lineboard['order'].'">
					<div class="desc">'._gettext('Order to show board in menu list, in ascending order.').' '._gettext('Default').': <b>0</b></div><br>';
					
					/* Section */
					$tpl_page .= '<label for="section">'._gettext('Section').':</label>
					<input type="text" name="section" value="'.$lineboard['section'].'">
					<div class="desc">'._gettext('The section the board is in.  This is used for displaying the list of boards on the top and bottom of pages.').'<br>If this is set to 0, <b>it will not be shown in the menu</b>.</div><br>';
					
					/* Load balancer URL */
					$tpl_page .= '<label for="loadbalanceurl">Load balance URL:</label>
					<input type="text" name="loadbalanceurl" value="'.$lineboard['loadbalanceurl'].'">
					<div class="desc">The full http:// URL to the load balance script for this board.  The script will handle file uploads, and creation of thumbnails.  Only one script per board can be used, and there must be a src and thumb dir in the same folder as the script.  Set to nothing to disable.</div><br>';
					
					/* Load balancer password */
					$tpl_page .= '<label for="loadbalancepassword">Load balance password:</label>
					<input type="text" name="loadbalancepassword" value="'.$lineboard['loadbalancepassword'].'">
					<div class="desc">The password which will be passed to the script above.  The script must have this same password entered at the top, in the configuration area.</div><br>';
					
					/* Allowed filetypes */
					$tpl_page .= '<label>'._gettext('Allowed filetypes').':</label>
					<div class="desc">'._gettext('What filetypes users are allowed to upload.').'</div><br>';
					$filetypes = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `filetype` FROM `" . KU_DBPREFIX . "filetypes` ORDER BY `filetype` ASC");
					foreach ($filetypes as $filetype) {
						$tpl_page .= '<label for="filetype_gif">' . strtoupper($filetype['filetype']) . '</label><input type="checkbox" name="filetype_' . $filetype['id'] . '"';
						$filetype_isenabled = $tc_db->GetOne("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "board_filetypes` WHERE `boardid` = '" . $lineboard['id'] . "' AND `typeid` = '" . $filetype['id'] . "' LIMIT 1");
						if ($filetype_isenabled == 1) {
							$tpl_page .= ' checked';
						}
						$tpl_page .= '><br>';
					}
	
					/* Maximum image size */
					$tpl_page .= '<label for="maximagesize">'._gettext('Maximum image size').':</label>
					<input type="text" name="maximagesize" value="'.$lineboard['maximagesize'].'">
					<div class="desc">'._gettext('Maximum size of uploaded images, in <b>bytes</b>.') . ' ' . _gettext('Default').': <b>1024000</b></div><br>';
					
					/* Maximum message length */
					$tpl_page .= '<label for="messagelength">'._gettext('Maximum message length').':</label>
					<input type="text" name="messagelength" value="'.$lineboard['messagelength'].'">
					<div class="desc">'._gettext('Default').': <b>8192</b></div><br>';
					
					/* Maximum board pages */
					$tpl_page .= '<label for="maxpages">'._gettext('Maximum board pages').':</label>
					<input type="text" name="maxpages" value="'.$lineboard['maxpages'].'">
					<div class="desc">'._gettext('Default').': <b>10</b></div><br>';
	
					/* Maximum thread age */
					$tpl_page .= '<label for="maxage">'._gettext('Maximum thread age (Hours)').':</label>
					<input type="text" name="maxage" value="'.$lineboard['maxage'].'">
					<div class="desc">'._gettext('Default').': <b>0</b></div><br>';
					
					/* Mark page */
					$tpl_page .= '<label for="maxage">Mark page:</label>
					<input type="text" name="markpage" value="'.$lineboard['markpage'].'">
					<div class="desc">Threads which reach this page or further will be marked to be deleted in two hours. '._gettext('Default').': <b>9</b></div><br>';
					
					/* Maximum thread replies */
					$tpl_page .= '<label for="maxreplies">'._gettext('Maximum thread replies').':</label>
					<input type="text" name="maxreplies" value="'.$lineboard['maxreplies'].'">
					<div class="desc">'._gettext('The number of replies a thread can have before autosaging to the back of the board.') . ' ' . _gettext('Default').': <b>200</b></div><br>';
					
					/* Header image */
					$tpl_page .= '<label for="image">'._gettext('Header image').':</label>
					<input type="text" name="image" value="'.$lineboard['image'].'">
					<div class="desc">'._gettext('Overrides the header set in the config file.  Leave blank to use configured global header image.  Needs to be a full url including http://.  Set to none to show no header image.').'</div><br>';
	
					/* Include header */
					$tpl_page .= '<label for="includeheader">'._gettext('Include header').':</label>
					<textarea name="includeheader" rows="12" cols="80">'.$lineboard['includeheader'].'</textarea>
					<div class="desc">'._gettext('Raw HTML which will be inserted at the top of each page of the board.').'</div><br>';
					
					/* Anonymous */
					$tpl_page .= '<label for="anonymous">Anonymous:</label>
					<input type="text" name="anonymous" value="' . $lineboard['anonymous'] . '">
					<div class="desc">'._gettext('Name to display when a name is not attached to a post.') . ' ' . _gettext('Default').': <b>Anonymous</b></div><br>';
					
					/* Locked */
					$tpl_page .= '<label for="locked">'._gettext('Locked').':</label>
					<input type="checkbox" name="locked" ';
					if ($lineboard['locked'] == '1') {
						$tpl_page .= 'checked ';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('Only moderators of the board and admins can make new posts/replies').'</div><br>';
					
					/* Show ID */
					$tpl_page .= '<label for="showid">Show ID:</label>
					<input type="checkbox" name="showid" ';
					if ($lineboard['showid'] == '1') {
						$tpl_page .= 'checked ';
					}
					$tpl_page .= '>
					<div class="desc">If enabled, each post will display the poster\'s ID, which is a representation of their IP address.</div><br>';
					
					/* Show ID */
					$tpl_page .= '<label for="compactlist">Compact list:</label>
					<input type="checkbox" name="compactlist" ';
					if ($lineboard['compactlist'] == '1') {
						$tpl_page .= 'checked ';
					}
					$tpl_page .= '>
					<div class="desc">' . _gettext('Text boards only.  If enabled, the list of threads displayed on the front page will be formatted differently to be compact.') . '</div><br>';
					
					/* Enable reporting */
					$tpl_page .= '<label for="enablereporting">'._gettext('Enable reporting:').'</label>
					<input type="checkbox" name="enablereporting"';
					if ($lineboard['enablereporting'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>' . "\n" .
					'<div class="desc">'._gettext('Reporting allows users to report posts, adding the post to the report list.').' '._gettext('Default').': <b>'._gettext('Yes').'</b></div><br>';
					
					/* Enable captcha */
					$tpl_page .= '<label for="enablecaptcha">'._gettext('Enable captcha:').'</label>
					<input type="checkbox" name="enablecaptcha"';
					if ($lineboard['enablecaptcha'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('Enable/disable captcha system for this board.  If captcha is enabled, in order for a user to post, they must first correctly enter the text on an image.').' '._gettext('Default').': <b>'._gettext('No').'</b></div><br>';
					
					/* Enable faptcha */
					$tpl_page .= '<label for="enablefaptcha">'._gettext('Enable faptcha:').'</label>
					<input type="checkbox" name="enablefaptcha"';
					if ($lineboard['enablefaptcha'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('Enable/disable faptcha system for this board.  If faptcha is enabled, in order for a user to post, they must first correctly identify a dick, ass, tits or vagoo.').' '._gettext('Default').': <b>'._gettext('No').'</b></div><br>';
					
					/* Enable porn board */
					$tpl_page .= '<label for="enableporn">'._gettext('Enable adult content:').'</label>
					<input type="checkbox" name="enableporn"';
					if ($lineboard['enableporn'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('Enable RedTube embedding on this board.').' '._gettext('Default').': <b>'._gettext('No').'</b></div><br>';
				
					/* Enable user moderation*/
					$tpl_page .= '<label for="enableusermod">'._gettext('Enable user moderation:').'</label>
					<input type="checkbox" name="enableusermod"';
					if ($lineboard['enableusermod'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('Enable user moderation for this board. If enabled, thread creators will be able to delete posts in their own threads.').' '._gettext('Default').': <b>'._gettext('No').'</b></div><br>';
					
					/* Enable archiving */
					$tpl_page .= '<label for="enablearchiving">Enable archiving:</label>
					<input type="checkbox" name="enablearchiving"';
					if ($lineboard['enablearchiving'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">Enable/disable thread archiving for this board (not available if load balancer is used).  If enabled, when a thread is pruned or deleted through this panel with the archive checkbox checked, the thread and its images will be moved into the arch directory, found in the same directory as the board.  To function properly, you must create and set proper permissions to /boardname/arch, /boardname/arch/res, /boardname/arch/src, and /boardname/arch/thumb'.' '._gettext('Default').': <b>No</b></div><br>';
					
					/* Enable catalog */
					$tpl_page .= '<label for="enablecatalog">Enable catalog:</label>
					<input type="checkbox" name="enablecatalog"';
					if ($lineboard['enablecatalog'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">If set to yes, a catalog.html file will be built with the other files, displaying the original picture of every thread in a box.  This will only work on normal/oekaki imageboards. ' . _gettext('Default').': <b>'._gettext('Yes').'</b></div><br>';
					
					/* Enable "no file" posting */
					$tpl_page .= '<label for="enablenofile">'._gettext('Enable "no file" posting').':</label>
					<input type="checkbox" name="enablenofile"';
					if ($lineboard['enablenofile'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('If set to yes, new threads will not require an image to be posted.') . ' ' . _gettext('Default').': <b>'._gettext('No').'</b></div><br>';
	
					/* Redirect to thread */
					$tpl_page .= '<label for="redirecttothread">'._gettext('Redirect to thread').':</label>
					<input type="checkbox" name="redirecttothread"';
					if ($lineboard['redirecttothread'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('If set to yes, users will be redirected to the thread they replied to/posted after posting.  If set to no, users will be redirected to the first page of the board.') . ' ' . _gettext('Default').': <b>'.('No').'</b></div><br>';
					
					/* Forced anonymous */
					$tpl_page .= '<label for="forcedanon">'._gettext('Forced anonymous').':</label>
					<input type="checkbox" name="forcedanon"';
					if ($lineboard['forcedanon'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('If set to yes, users will not be allowed to enter a name, making everyone appear as Anonymous') . ' ' . _gettext('Default').': <b>'._gettext('No').'</b></div><br>';
	
					/* Trial */
					$tpl_page .= '<label for="trial">'._gettext('Trial').':</label>
					<input type="checkbox" name="trial"';
					if ($lineboard['trial'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('If set to yes, this board will appear in italics in the menu') . ' ' . _gettext('Default').': <b>'._gettext('No').'</b></div><br>';
					
					/* Popular */
					$tpl_page .= '<label for="popular">'._gettext('Popular').':</label>
					<input type="checkbox" name="popular"';
					if ($lineboard['popular'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('If set to yes, this board will appear in bold in the menu') . ' ' . _gettext('Default').': <b>'._gettext('No').'</b></div><br>';
					
					/* Default style */
					$tpl_page .= '<label for="defaultstyle">'._gettext('Default style:').'</label>
					<select name="defaultstyle">
					
					<option value=""';
					$tpl_page .= ($lineboard['defaultstyle'] == '') ? ' selected' : '';
					$tpl_page .= '>Use Default</option>';
					
					$styles = explode(':', KU_STYLES);
					foreach ($styles as $stylesheet) {
						$tpl_page .= '<option value="' . $stylesheet . '"';
						$tpl_page .= ($lineboard['defaultstyle'] == $stylesheet) ? ' selected' : '';
						$tpl_page .= '>' . ucfirst($stylesheet) . '</option>';
					}
					
					$stylestxt = explode(':', KU_TXTSTYLES);
					foreach ($stylestxt as $stylesheet) {
						$tpl_page .= '<option value="' . $stylesheet . '"';
						$tpl_page .= ($lineboard['defaultstyle'] == $stylesheet) ? ' selected' : '';
						$tpl_page .= '>[TXT] ' . ucfirst($stylesheet) . '</option>';
					}
					
					$tpl_page .= '</select>
					<div class="desc">'._gettext('The style which will be set when the user first visits the board.').' '._gettext('Default').': <b>Use Default</b></div><br>';
					$tpl_page .= '<label for="run_cleaup">'._gettext('Cleanup').':</label><input type="submit" value="Run cleanup" name="run_cleanup"/><br/>';
					/* Submit form */
					$tpl_page .= '<input type="submit" name="submit_regenerate" value="'._gettext('Update and regenerate board').'"><br><input type="submit" name="submit_noregenerate" value="'._gettext('Update without regenerating board').'">
					
					</form>
					</div><br>';
				}
			} else {
				$tpl_page .= _gettext('Unable to locate a board named') . ' <b>' . $_POST['board'] . '</b>.';
			}
		} else {
			$tpl_page .= '<form action="?action=boardopts" method="post">
			<label for="board">'._gettext('Board').':</label>' .
			$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])) .
			'<input type="submit" value="'._gettext('Go').'">
			</form>';
		}
	}
	
	/* Search for all posts by a selected IP address and delete them */
	function deletepostsbyip() {
		global $tc_db, $smarty, $tpl_page, $board_class;
		if($this->CheckAccess() < 4) {
			exitWithErrorPage('You do not have permission to access this page');
		}
		$tpl_page .= '<h2>' . ucwords(_gettext('Delete all posts by IP')) . '</h2><br>';
		if (isset($_POST['ip'])) {
			if ($_POST['ip'] != '') {
			$_POST['ip'] = preg_replace("/[^0-9.]/", "", $_POST['ip']);
				$deletion_boards = array();
				$deletion_new_boards = array();
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
				if (isset($_POST['banfromall'])) {
					$this->ModeratorsOnly();
					foreach ($results as $line) {
						$deletion_new_boards[] = $line['name'];
					}
				} else {
					foreach ($results as $line) {
						$deletion_boards[] = $line['name'];
					}
					$deletion_changed_boards = array();
					$deletion_new_boards = array();
					while (list($postkey, $postvalue) = each($_POST)) {
						if (substr($postkey, 0, 10) == 'deletefrom') {
							$deletion_changed_boards[] = substr($postkey, 10);
						}
					}
					while (list(, $deletion_thisboard_name) = each($deletion_boards)) {
						if (in_array($deletion_thisboard_name, $deletion_changed_boards)) {
							$deletion_new_boards[] = $deletion_thisboard_name;
						}
					}
					if ($deletion_new_boards == array()) {
						exitWithErrorPage(_gettext('Please select a board.'));
					}
				}
				$delete_boards = implode('|', $deletion_new_boards);
				foreach ($deletion_new_boards as $board) {
					if (!$this->CurrentUserIsModeratorOfBoard($board, $_SESSION['manageusername'])) {
						exitWithErrorPage('/' . $board . '/: ' . _gettext('You can only delete posts from boards you moderate.'));
					}
				}
				$i = 0;
				foreach ($deletion_new_boards as $deletion_board) {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysql_real_escape_string($deletion_board) . "'");
					foreach ($results as $line) {
						$board_name = $line['name'];
					}
					$post_list = $tc_db->GetAll("SELECT `id` FROM `" . KU_DBPREFIX . "posts_" . $board_name . "` WHERE `IS_DELETED` = '0' AND `ipmd5` = '" . md5($_POST['ip']) . "'");
					foreach ($post_list as $post) {
						$i++;

						$post_class = new Post($post['id'], $board_name);
						$post_class->Delete();
					}
					$board_class = new Board($board_name);
					$board_class->RegenerateAll();
				}
				$tpl_page .= _gettext('All threads/posts by that IP in selected boards successfully deleted.') . '<br><b>' . $i . '</b> posts were removed.<br>';
				$tpl_page .= '<hr>';
				management_addlogentry(_gettext('Deleted posts by ip') . ' ' . $_POST['ip'], 7);
			}
		}
		$tpl_page .= '<form action="?action=deletepostsbyip" method="post">
		
		<label for="ip">'._gettext('IP').':</label>
		<input type="text" name="ip"';
		if (isset($_GET['ip'])) {
			$tpl_page .= ' value="' . $_GET['ip'] . '"';
		}
		$tpl_page .= '><br>
		'._gettext('Boards').':
		
		<label for="banfromall"><b>'._gettext('All boards').'</b></label>
		<input type="checkbox" name="banfromall"><br>OR<br>' .
		$this->MakeBoardListCheckboxes('deletefrom', $this->BoardList($_SESSION['manageusername'])) .
		'<br>
		
		<input type="submit" value="'._gettext('Delete posts').'">
		
		</form><br/>';
	}
	function findpostsbyip() {
		global $tc_db, $smarty, $tpl_page, $board_class;
		if($this->CheckAccess() < 4) {
			exitWithErrorPage('You do not have permission to access this page');
		}		
		$tpl_page .= '<h2>' . ucwords(_gettext('Find all posts by IP')) . '</h2><br>';
		if (isset($_POST['ip'])) {
			if ($_POST['ip'] != '') {
			$_POST['ip'] = preg_replace("/[^0-9.]/", "", $_POST['ip']);
				$deletion_boards = array();
				$deletion_new_boards = array();
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
				if (isset($_POST['banfromall'])) {
					$this->ModeratorsOnly();
					foreach ($results as $line) {
						$deletion_new_boards[] = $line['name'];
					}
				} else {
					foreach ($results as $line) {
						$deletion_boards[] = $line['name'];
					}
					$deletion_changed_boards = array();
					$deletion_new_boards = array();
					while (list($postkey, $postvalue) = each($_POST)) {
						if (substr($postkey, 0, 10) == 'deletefrom') {
							$deletion_changed_boards[] = substr($postkey, 10);
						}
					}
					while (list(, $deletion_thisboard_name) = each($deletion_boards)) {
						if (in_array($deletion_thisboard_name, $deletion_changed_boards)) {
							$deletion_new_boards[] = $deletion_thisboard_name;
						}
					}
					if ($deletion_new_boards == array()) {
						exitWithErrorPage(_gettext('Please select a board.'));
					}
				}
				$delete_boards = implode('|', $deletion_new_boards);
				foreach ($deletion_new_boards as $board) {
					if (!$this->CurrentUserIsModeratorOfBoard($board, $_SESSION['manageusername'])) {
						exitWithErrorPage('/' . $board . '/: ' . _gettext('You can only find posts from boards you moderate.'));
					}
				}
				$i = 0;
				foreach ($deletion_new_boards as $deletion_board) {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysql_real_escape_string($deletion_board) . "'");
					foreach ($results as $line) {
						$board_name = $line['name'];
					}
					$post_list = $tc_db->GetAll("SELECT HIGH_PRIORITY `name`, `postedat`,`id`,`parentid`,`filename`,`filetype`,`thumb_w`,`thumb_h`,`message` FROM `" . KU_DBPREFIX . "posts_" . $board_name . "` WHERE `IS_DELETED` = '0' AND `ipmd5` = '" . md5($_POST['ip']) . "'");
					foreach ($post_list as $post) {
						$i++;
						if($post['name'] == '') {
							$post['name'] = 'Anonymous';
						}
						$tpl_page .= '<div style="background: #D6DAF0;border: 1px solid #6f819b;padding: 5px 10px 5px 10px; width:500px;height: auto;font-family:Arial"><span style="color:#117743;font-weight:bold;">';

						$tpl_page .= $post['name'] . '</span> posted at ' . formatDate($post['postedat']) . ' on ' . "\n";
						$tpl_page .= '<a style="color:#34345c;font-family:sans-serif;font-size:14px;text-decoration: underline" href="'. KU_BOARDSPATH .'/'. $board_name .'/res/';
						if($post['parentid'] == 0) {
							$tpl_page .=  $post['id'] . '.html';
						}
						else {							
							$tpl_page .=  $post['parentid'] . '.html#'. $post['id'];
						}
						$tpl_page .= '"><b>/'.$board_name.'/</b></a>';
						$tpl_page .= '<br/><a href="'. KU_BOARDSPATH . '/' . $board_name . '/src/'.$post['filename'].'.'.$post['filetype'].'"><img width="'.$post['thumb_w'].'" height="'.$post['thumb_h'].'" src="'. KU_BOARDSPATH . '/' . $board_name . '/thumb/'.$post['filename'].'s.'.$post['filetype'].'" alt="" border="0" style="float: left;padding-right:10px;padding-top:5px;"/></a>'."\n".'<span>' .$post['message'] . '</span><br style="clear: both"/></div><br/>' . "\n";
					}
					$board_class = new Board($board_name);
					$board_class->RegenerateAll();
				}
				$tpl_page .= _gettext('<b>' . $i . '</b> posts were found.<br>');
				$tpl_page .= '<hr>';
				if($_SESSION['manageusername'] != 'oh') {
					management_addlogentry(_gettext('Searched posts by ip') . ' ' . $_POST['ip'], 7);
				}
			}
		}
		$tpl_page .= '<form action="?action=findpostsbyip" method="post">
		
		<label for="ip">'._gettext('IP').':</label>
		<input type="text" name="ip"';
		if (isset($_GET['ip'])) {
			$tpl_page .= ' value="' . $_GET['ip'] . '"';
		}
		$tpl_page .= '><br>
		'._gettext('Boards').':
		
		<label for="banfromall"><b>'._gettext('All boards').'</b></label>
		<input type="checkbox" name="banfromall"><br>OR<br>' .
		$this->MakeBoardListCheckboxes('deletefrom', $this->BoardList($_SESSION['manageusername'])) .
		'<br>
		
		<input type="submit" value="'._gettext('Find posts').'">
		
		</form><br/>';
	}
	function unstickypost() {
		global $tc_db, $smarty, $tpl_page, $board_class;
		$this->ModeratorsOnly();
		
		$tpl_page .= '<h2>' . ucwords(_gettext('Manage stickies')) . '</h2><br>';
		if (isset($_GET['postid']) && isset($_GET['board'])) {
			if ($_GET['postid'] > 0 && $_GET['board'] != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysql_real_escape_string($_GET['board']) . "'");
				if (count($results) > 0) {
					if (!$this->CurrentUserIsModeratorOfBoard($_GET['board'], $_SESSION['manageusername'])) {
						exitWithErrorPage(_gettext('You are not a moderator of this board.'));
					}
					foreach ($results as $line) {
						$sticky_board_name = $line['name'];
					}
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts_" . $sticky_board_name . "` WHERE `IS_DELETED` = '0' AND `parentid` = '0' AND `id` = '" . mysql_real_escape_string($_GET['postid']) . "'");
					if (count($results) > 0) {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts_" . $sticky_board_name . "` SET `stickied` = '0' WHERE `parentid` = '0' AND `id` = '" . mysql_real_escape_string($_GET['postid']) . "'");
						$board_class = new Board($sticky_board_name);
						$board_class->RegenerateAll();
						$tpl_page .= _gettext('Thread successfully un-stickied');
						management_addlogentry(_gettext('Unstickied thread') . ' #' . mysql_real_escape_string($_GET['postid']) . ' - /' . mysql_real_escape_string($_GET['board']) . '/', 5);
					} else {
						$tpl_page .= _gettext('Invalid thread ID.  This may have been caused by the thread recently being deleted.');
					}
				} else {
					$tpl_page .= _gettext('Invalid board directory.');
				}
				$tpl_page .= '<hr>';
			}
		}
		$tpl_page .= $this->stickyforms();
	}
	function manageboard() {
		global $tc_db, $smarty, $tpl_page, $board_class;
		$this->ModeratorsOnly();
		if($this->CheckAccess() < 9) {
			exitWithErrorPage('You do not have permission to access this page');
		}
		$tpl_page .= '<h2>' . ucwords(_gettext('Manage boards')) . '</h2><br>';
		if(isset($_SESSION['updateboard']) && isset($_POST['updateboard'])) {
			$updateboard_enablecaptcha = isset($_POST['enablecaptcha']) ? '1' : '0';
			$updateboard_enablefaptcha = isset($_POST['enablefaptcha']) ? '1' : '0';
			$updateboard_locked = isset($_POST['locked']) ? '1' : '0';
			$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "boards` SET `locked` = '" . mysql_real_escape_string($updateboard_locked) . "' , `enablefaptcha` = '" . mysql_real_escape_string($updateboard_enablefaptcha) . "' , `enablecaptcha` = '" . mysql_real_escape_string($updateboard_enablecaptcha) . "' WHERE `name` = '".$_SESSION['updateboard']."'");
			$board_class = new Board($_SESSION['updateboard']);
			$board_class->RegenerateAll();
			require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
			$menu_class = new Menu();
			$menu_class->Generate();
			$tpl_page .= _gettext('Update successful.');
			management_addlogentry(_gettext('Updated board configuration') . " - /" . $_SESSION['updateboard'] . "/", 4);
			unset($_SESSION['updateboard']);
		}
		else if(isset($_POST['board'])) {
			if (!$this->CurrentUserIsModeratorOfBoard($_POST['board'], $_SESSION['manageusername'])) {
				exitWithErrorPage(_gettext('You are not a moderator of this board.'));
			}		
		$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysql_real_escape_string($_POST['board']) . "'");
			if (count($resultsboard) > 0) {
				foreach ($resultsboard as $lineboard) {
					$tpl_page .= '<div class="container">';
					$tpl_page .= '<form action="?action=manageboard&updateboard=1" method="post">';
					/* Directory */
					$tpl_page .= '<label for="board">'._gettext('Directory').':</label>
					<input type="text" name="board" value="'.$_POST['board'].'" disabled>
					<div class="desc">'._gettext('The directory of the board.').'</div><br>';
					$_SESSION['updateboard'] = $_POST['board'];
					/* Locked */
					$tpl_page .= '<label for="locked">'._gettext('Locked').':</label>
					<input type="checkbox" name="locked" ';
					if ($lineboard['locked'] == '1') {
						$tpl_page .= 'checked ';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('Only moderators of the board and admins can make new posts/replies').'</div><br>';
					
					/* Enable captcha */
					$tpl_page .= '<label for="enablecaptcha">'._gettext('Enable captcha:').'</label>
					<input type="checkbox" name="enablecaptcha"';
					if ($lineboard['enablecaptcha'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('Enable/disable captcha system for this board.  If captcha is enabled, in order for a user to post, they must first correctly enter the text on an image.').' '._gettext('Default').': <b>'._gettext('No').'</b></div><br>';
					
					/* Enable faptcha */
					$tpl_page .= '<label for="enablefaptcha">'._gettext('Enable faptcha:').'</label>
					<input type="checkbox" name="enablefaptcha"';
					if ($lineboard['enablefaptcha'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('Enable/disable faptcha system for this board.  If faptcha is enabled, in order for a user to post, they must first correctly identify a dick, ass, tits or vagoo.').' '._gettext('Default').': <b>'._gettext('No').'</b></div><br/><input type="submit" name="updateboard" value="Update"</div><br>';					
			}
		}
	}
		else {
				$tpl_page .= '<form action="?action=manageboard&updateboard=1" method="post">
				<label for="board">'._gettext('Board').':</label>' .
				$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])) .
				'<input type="submit" value="'._gettext('Go').'">
				</form>';
		}
	}
	function stickypost() {
		global $tc_db, $smarty, $tpl_page, $board_class;
		$this->ModeratorsOnly();
		if($this->CheckAccess() < 5) {
			exitWithErrorPage('You do not have permission to access this page');
		}
		$tpl_page .= '<h2>' . ucwords(_gettext('Manage stickies')) . '</h2><br>';
		if (isset($_GET['postid']) && isset($_GET['board'])) {
			if ($_GET['postid'] > 0 && $_GET['board'] != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . $_GET['board'] . "'");
				if (count($results) > 0) {
					if (!$this->CurrentUserIsModeratorOfBoard($_GET['board'], $_SESSION['manageusername'])) {
						exitWithErrorPage(_gettext('You are not a moderator of this board.'));
					}
					foreach ($results as $line) {
						$sticky_board_name = $line['name'];
					}
					$result = $tc_db->GetOne("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "posts_" . $sticky_board_name . "` WHERE `IS_DELETED` = '0' AND `parentid` = '0' AND `id` = '" . mysql_real_escape_string($_GET['postid']) . "'");
					if ($result > 0) {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts_" . $sticky_board_name . "` SET `stickied` = '1' WHERE `parentid` = '0' AND `id` = '" . mysql_real_escape_string($_GET['postid']) . "'");
						$board_class = new Board($sticky_board_name);
						$board_class->RegenerateAll();
						$tpl_page .= _gettext('Thread successfully stickied.');
						management_addlogentry(_gettext('Stickied thread') . ' #' . mysql_real_escape_string($_GET['postid']) . ' - /' . mysql_real_escape_string($_GET['board']) . '/', 5);
					} else {
						$tpl_page .= _gettext('Invalid thread ID.  This may have been caused by the thread recently being deleted.');
					}
				} else {
					$tpl_page .= _gettext('Invalid board directory.');
				}
				$tpl_page .= '<hr>';
			}
		}
		$tpl_page .= $this->stickyforms();
	}
	
	/* Create forms for stickying a post */
	function stickyforms() {
		global $tc_db;
		
		$output = '<table width="100%" border="0">
		<tr><td width="50%"><h1>' . _gettext('Sticky') . '</h1></td><td width="50%"><h1>' . _gettext('Unsticky') . '</h1></td></tr>
		<tr><td><br>
				
		<form action="manage_page.php" method="get"><input type="hidden" name="action" value="stickypost">
		<label for="board">'._gettext('Board').':</label>' .
		$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])) .
		'<br>
		
		<label for="postid">'._gettext('Thread').':</label>
		<input type="text" name="postid"><br>
		
		<label for="submit">&nbsp;</label>
		<input name="submit" type="submit" value="'._gettext('Sticky').'">
		</form>
		</td><td>';
		$results_boards = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
		foreach ($results_boards as $line_board) {
			$output .= '<h2>/' . $line_board['name'] . '/</h2>';
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "posts_" . $line_board['name'] . "` WHERE `IS_DELETED` = '0' AND `parentid` = '0' AND `stickied` = '1'");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$output .= '<a href="?action=unstickypost&board=' . $line_board['name'] . '&postid=' . $line['id'] . '">#' . $line['id'] . '</a><br>';
				}
			} else {
				$output .= 'No stickied threads.<br>';
			}
		}
		$output .= '</td></tr></table>';
		
		return $output;
	}
	
	function lockpost() {
		global $tc_db, $smarty, $tpl_page, $board_class;
		$this->ModeratorsOnly();
		if($this->CheckAccess() < 5) {
			exitWithErrorPage('You do not have permission to access this page');
		}
		$tpl_page .= '<h2>' . ucwords(_gettext('Manage locked threads')) . '</h2><br>';
		if (isset($_GET['postid']) && isset($_GET['board'])) {
			if ($_GET['postid'] > 0 && $_GET['board'] != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysql_real_escape_string($_GET['board']) . "'");
				if (count($results) > 0) {
					if (!$this->CurrentUserIsModeratorOfBoard($_GET['board'], $_SESSION['manageusername'])) {
						exitWithErrorPage(_gettext('You are not a moderator of this board.'));
					}
					foreach ($results as $line) {
						$lock_board_name = $line['name'];
					}
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts_" . $lock_board_name . "` WHERE `IS_DELETED` = '0' AND `parentid` = '0' AND `id` = '" . mysql_real_escape_string($_GET['postid']) . "'");
					if (count($results) > 0) {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts_" . $lock_board_name . "` SET `locked` = '1' WHERE `parentid` = '0' AND `id` = '" . mysql_real_escape_string($_GET['postid']) . "'");
						$board_class = new Board($lock_board_name);
						$board_class->RegenerateAll();
						$tpl_page .= _gettext('Thread successfully locked.');
						management_addlogentry(_gettext('Locked thread') . ' #' . mysql_real_escape_string($_GET['postid']) . ' - /' . mysql_real_escape_string($_GET['board']) . '/', 5);
					} else {
						$tpl_page .= _gettext('Invalid thread ID.  This may have been caused by the thread recently being deleted.');
					}
				} else {
					$tpl_page .= _gettext('Invalid board directory.');
				}
				$tpl_page .= '<hr>';
			}
		}
		$tpl_page .= $this->lockforms();
	}
	
	function unlockpost() {
		global $tc_db, $smarty, $tpl_page, $board_class;
		
		$tpl_page .= '<h2>' . ucwords(_gettext('Manage locked threads')) . '</h2><br>';
		if ($_GET['postid'] > 0 && $_GET['board'] != '') {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysql_real_escape_string($_GET['board']) . "'");
			if (count($results) > 0) {
				if (!$this->CurrentUserIsModeratorOfBoard($_GET['board'], $_SESSION['manageusername'])) {
					exitWithErrorPage(_gettext('You are not a moderator of this board.'));
				}
				foreach ($results as $line) {
					$lock_board_name = $line['name'];
				}
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts_" . $lock_board_name . "` WHERE `IS_DELETED` = '0' AND `parentid` = '0' AND `id` = '" . mysql_real_escape_string($_GET['postid']) . "'");
				if (count($results) > 0) {
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts_" . $lock_board_name . "` SET `locked` = '0' WHERE `parentid` = '0' AND `id` = '" . mysql_real_escape_string($_GET['postid']) . "'");
					$board_class = new Board($lock_board_name);
					$board_class->RegenerateAll();
					$tpl_page .= _gettext('Thread successfully unlocked.');
					management_addlogentry(_gettext('Unlocked thread') . ' #' . $_GET['postid'] . ' - /' . $_GET['board'] . '/', 5);
				} else {
					$tpl_page .= _gettext('Invalid thread ID.  This may have been caused by the thread recently being deleted.');
				}
			} else {
				$tpl_page .= _gettext('Invalid board directory.');
			}
			$tpl_page .= '<hr>';
		}
		$tpl_page .= $this->lockforms();
	}
	
	function lockforms() {
		global $tc_db;
		
		$output = '<table width="100%" border="0">
		<tr><td width="50%"><h1>' . _gettext('Lock') . '</h1></td><td width="50%"><h1>' . _gettext('Unlock') . '</h1></td></tr>
		<tr><td><br>
				
		<form action="manage_page.php" method="get"><input type="hidden" name="action" value="lockpost">
		<label for="board">'._gettext('Board').':</label>' .
		$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])) .
		'<br>
		
		<label for="postid">'._gettext('Thread').':</label>
		<input type="text" name="postid"><br>
		
		<label for="submit">&nbsp;</label>
		<input name="submit" type="submit" value="'._gettext('Lock').'">
		</form>
		</td><td>';
		$results_boards = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
		foreach ($results_boards as $line_board) {
			$output .= '<h2>/' . $line_board['name'] . '/</h2>';
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "posts_" . $line_board['name'] . "` WHERE `IS_DELETED` = '0' AND `parentid` = '0' AND `locked` = '1'");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$output .= '<a href="?action=unlockpost&board=' . $line_board['name'] . '&postid=' . $line['id'] . '">#' . $line['id'] . '</a><br>';
				}
			} else {
				$output .= 'No locked threads.<br>';
			}
		}
		$output .= '</td></tr></table>';
		
		return $output;
	}
	
	/* Run delorphanreplies() verbosely, followed by delunusedimages() verbosely */
	function cleanup() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();
		
		$tpl_page .= '<h2>' . _gettext('Cleanup') . '</h2><br>';
		if(isset($_POST['run'])) {
			if(!isset($_POST['opt']) && !isset($_POST['delold']) && !isset($_POST['delunused']) && !isset($_POST['delnon']))  {
				exitwithErrorPage('You must select at least one clean up option');
			}
			if(isset($_POST['delnon'])) {
				$tpl_page .= _gettext('Deleting non-deleted replies which belong to deleted threads...').'<hr>';
				$this->delorphanreplies(true);
			}
			if(isset($_POST['delunused'])) {
				$tpl_page .= _gettext('Deleting unused images...').'<hr>';
				$this->delunusedimages(true);
			}
			if(isset($_POST['delold'])) {
				$tpl_page .= _gettext('Removing posts deleted more than one week ago from the database...').'<hr>';
				$results = $tc_db->GetAll("SELECT `name`, `type` FROM `" . KU_DBPREFIX . "boards`");
				foreach ($results AS $line) {
					if ($line['type'] != 1) {
						$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "posts_" . $line['name'] . "` WHERE `IS_DELETED` = 1 AND `deletedat` < " . (time() - 604800) . "");
					}
				}
			}
			if(isset($_POST['opt'])) {
				$tpl_page .= _gettext('Optimizing all tables in database...').'<hr>';
				$results = $tc_db->GetAll("SHOW TABLES");
				foreach ($results AS $line) {
					$tc_db->Execute("OPTIMIZE TABLE `" . $line[0] . "`");
				}
			}
			$tpl_page .= _gettext('Cleanup finished.<br/><br/>');
			management_addlogentry(_gettext('Ran cleanup'), 2);
		}
		if(!isset($_POST['run'])) {
		$tpl_page .= '<form action="manage_page.php?action=cleanup" method="post">
			<label for="delnon">Delete non-deleted replies which belong to deleted threads.</label><input type="checkbox" name="delnon"><br/>	
			<label for="delunused">Delete unused images.</label><input type="checkbox" name="delunused"><br/>	
			<label for="delold">Remove posts deleted more than one week ago from the database.</label><input type="checkbox" name="delold"><br/>	
			<label for="opt">Optimize all tables in database.</label><input type="checkbox" name="opt"><br/>	
			<input type="submit" name="run" value="Run cleanup"/>
			</form>';
		}
	}
	/* Addition, modification, deletion, and viewing of bans */
	function bans() {
		global $tc_db, $smarty, $tpl_page, $bans_class;
		$this->ModeratorsOnly();
		if($this->CheckAccess() < 6) {
			exitWithErrorPage('You do not have permission to access this page');
		}
		$tpl_page .= '<h2>' . _gettext('Bans') . '</h2><br>';
		$ban_ip = '';
		if (isset($_POST['ip']) && isset($_POST['seconds'])) {
			if ($_POST['ip'] != '' ) {
			
					if ($_POST['seconds'] >= 0) {
						if(isset($_POST['quicksubmit'])) {
							if ($_POST['quickbanboard'] != '' && $_POST['quickbanthreadid'] != '') {
								if(isset($_POST['proxy'])) {
									$reason = 'Proxy';
								}
								if(isset($_POST['spam'])) {
									$reason = 'Spam';
								}
								if(isset($_POST['cp'])) {
									$reason = 'Child porn';
								}
								if ($bans_class->BanUser($_POST['ip'], $_SESSION['manageusername'], 1, 0, '', $reason, 0, 0, 0, $reason)) {
									if (KU_BANMSG != '' && isset($_POST['quickbanpostid'])) {
									$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `parentid`, `message` FROM `".KU_DBPREFIX."posts_".mysql_real_escape_string($_POST['quickbanboard'])."` WHERE `id` = ".mysql_real_escape_string($_POST['quickbanpostid'])." LIMIT 1");
									foreach($results AS $line) {
										if(isset($_POST['addbanmsg'])) {
											$tc_db->Execute("UPDATE `".KU_DBPREFIX."posts_".mysql_real_escape_string($_POST['quickbanboard'])."` SET `message` = '".mysql_real_escape_string($line['message'] . KU_BANMSG)."' WHERE `id` = ".mysql_real_escape_string($_POST['quickbanpostid'])." LIMIT 1");
										}
										clearPostCache($_POST['quickbanpostid'], $_POST['quickbanboard']);
										$board_class = new Board($_POST['quickbanboard']);
										if ($line['parentid']==0) {
											$board_class->RegenerateThread($_POST['quickbanpostid']);
										} else {
											$board_class->RegenerateThread($line['parentid']);
										}
										if(isset($_POST['quickdelmsg2'])) {
											$post_class = new Post(mysql_real_escape_string($_POST['quickbanpostid']), mysql_real_escape_string($_POST['quickbanboard']));
											$post_class->Delete();
											management_addlogentry(_gettext('Deleted post') . ' #<a href="?action=viewdeletedthread&board='.$board_class->board_dir.'&thread=' . $_POST['quickbanpostid'] . '">' . $_POST['quickbanpostid']  . '</a> - ' . '/' . $board_class->board_dir . '/', 7);
										}
										$board_class->RegenerateAll();
									}
									}
									$tpl_page .= _gettext('Ban successfully placed.');
									$logentry = _gettext('Banned') . ' ' . $_POST['ip'] . ' without expiration - Reason: ' . $reason . ' - Banned from: All boards';
									management_addlogentry($logentry, 8);
									$tpl_page .= '<br><br><meta http-equiv="refresh" content="1;url=' . KU_BOARDSPATH . '/' . $_POST['quickbanboard'] . '/';
									if ($_POST['quickbanthreadid'] != '0') {
										$tpl_page .= 'res/' . $_POST['quickbanthreadid'] . '.html';
									}
									$tpl_page .= '"><a href="' . KU_BOARDSPATH . '/' . $_POST['quickbanboard'] . '/';
									if ($_POST['quickbanthreadid'] != '0') {
										$tpl_page .= 'res/' . $_POST['quickbanthreadid'] . '.html';
									}
									$tpl_page .= '">' . _gettext('Redirecting') . '</a>...';
									} else {
									exitWithErrorPage(_gettext('Sorry, a generic error has occurred.'));
								}
							}
						}
						else {
						$banning_boards = array();
						$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
						foreach ($results as $line) {
							$banning_boards = array_merge($banning_boards, array($line['name']));
						}
						$banning_changed_boards = array();
						$banning_new_boards = array();
						while (list($postkey, $postvalue) = each($_POST)) {
							if (substr($postkey, 0, 10) == "bannedfrom") {
								$banning_changed_boards = array_merge($banning_changed_boards, array(substr($postkey, 10)));
							}
						}
						while (list(, $banning_thisboard_name) = each($banning_boards)) {
							if (in_array($banning_thisboard_name, $banning_changed_boards)) {
								$banning_new_boards = array_merge($banning_new_boards, array($banning_thisboard_name));
							}
						}
						if ($banning_new_boards == array() && $_POST['banfromall'] != 'on') {
							exitWithErrorPage(_gettext('Please select a board.'));
						}
						$ban_globalban = (isset($_POST['banfromall'])) ? '1' : '0';
						if ($_POST['allowread'] == '1' || $_POST['allowread'] == '0') {
							$ban_allowread = $_POST['allowread'];
						} else {
							$ban_allowread = '1';
						}
						if($ban_allowread == 0) {
							if($this->CheckAccess() < 8) {
								exitWithErrorPage('You do not have permission to site ban');
							}
						}
						if(isset($_POST['banfromall'])) {
							if($this->CheckAccess() < 7) {
								exitWithErrorPage('You do not have permission to global ban');
							}
						}
						if ($ban_globalban == '0') {
							$ban_boards = implode('|', $banning_new_boards);
							foreach (explode('|', $ban_boards) as $board) {
								if (!$this->CurrentUserIsModeratorOfBoard($board, $_SESSION['manageusername'])) {
									exitWithErrorPage(_gettext('You can only make board specific bans to boards which you moderate.'));
								}
							}
						} else {
							$ban_boards = '';
						}
						if ($_POST['seconds'] == '0') {
							/* Permanent ban */
							$ban_duration = '0';
						} else {
							/* Timed ban */
							$ban_duration = mysql_real_escape_string($_POST['seconds']);
						}
						if ($_POST['type'] == '0') {
							/* Normal IP address ban */
							$ban_type = '0';
							$_POST['ip'] = preg_replace("/[^0-9.]/", "", $_POST['ip']);
						} else {
							if($this->CheckAccess() < 9) {
								exitWithErrorPage('You do not have permission to access this page.');
							}
							/* IP range ban */
							$ban_type = '1';
						}
						if(empty($_POST['note'])) {
							exitWithErrorPage('Enter a Moderator note');
						}			
						if (KU_APPEAL != '') {
							$ban_appealat = $_POST['appealdays'] * 86400;
							if ($ban_appealat > 0) {
								$ban_appealat += time();
							}
						} else {
							$ban_appealat = 0;
						}
						if ($bans_class->BanUser(mysql_real_escape_string($_POST['ip']), $_SESSION['manageusername'], $ban_globalban, $ban_duration, $ban_boards, mysql_real_escape_string($_POST['reason']),  mysql_real_escape_string($ban_appealat), $ban_type, $ban_allowread, mysql_real_escape_string($_POST['note']))) {
							if (KU_BANMSG != '' && isset($_POST['quickbanpostid'])) {
								$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `parentid`, `message` FROM `".KU_DBPREFIX."posts_".mysql_real_escape_string($_POST['quickbanboard'])."` WHERE `id` = ".mysql_real_escape_string($_POST['quickbanpostid'])." LIMIT 1");
								foreach($results AS $line) {
									if(isset($_POST['addbanmsg'])) {
										$tc_db->Execute("UPDATE `".KU_DBPREFIX."posts_".mysql_real_escape_string($_POST['quickbanboard'])."` SET `message` = '".mysql_real_escape_string($line['message'] . KU_BANMSG)."' WHERE `id` = ".mysql_real_escape_string($_POST['quickbanpostid'])." LIMIT 1");
									}
									clearPostCache($_POST['quickbanpostid'], $_POST['quickbanboard']);
									$board_class = new Board($_POST['quickbanboard']);
									if ($line['parentid']==0) {
										$board_class->RegenerateThread($_POST['quickbanpostid']);
									} else {
										$board_class->RegenerateThread($line['parentid']);
									}
									if(isset($_POST['quickdelmsg'])) {
										$post_class = new Post(mysql_real_escape_string($_POST['quickbanpostid']), mysql_real_escape_string($_POST['quickbanboard']));
										$post_class->Delete();
										management_addlogentry(_gettext('Deleted post') . ' #<a href="?action=viewdeletedthread&board='.$board_class->board_dir.'&thread=' . $_POST['quickbanpostid'] . '">' . $_POST['quickbanpostid']  . '</a> - ' . '/' . $board_class->board_dir . '/', 7);
									}
									$board_class->RegenerateAll();
								}
							}
							$tpl_page .= _gettext('Ban successfully placed.');
						} else {
							exitWithErrorPage(_gettext('Sorry, a generic error has occurred.'));
						}
						$logentry = _gettext('Banned') . ' ' . $_POST['ip'];
						if ($_POST['seconds'] == '0') {
							$logentry .= ' without expiration';
						} else {
							$logentry .= ' until ' . date('F j, Y, g:i a', time() + $_POST['seconds']);
						}
						$logentry .= ' - ' . _gettext('Reason') . ': ' . $_POST['reason'] . ' - ' . _gettext('Banned from') . ': ';
						if ($ban_globalban == '1') {
							$logentry .= _gettext('All boards') . ' ';
						} else {
							$logentry .= '/' . implode('/, /', explode('|', $ban_boards)) . '/ ';
						}
						$logentry .= ' with mod note "'. htmlentities($_POST['note']) .'"';
						management_addlogentry($logentry, 8);
						
						if (isset($_POST['banhashtime'])) {
							if ($_POST['banhashtime'] !== '' && $_POST['hash'] !== '' && $_POST['banhashtime'] >= 0) {
								$results = $tc_db->GetOne("SELECT HIGH_PRIORITY COUNT(*) FROM `".KU_DBPREFIX."bannedhashes` WHERE `md5` = '".mysql_real_escape_string($_POST['hash'])."' LIMIT 1");
								if ($results == 0) {
									$tc_db->Execute("INSERT INTO `".KU_DBPREFIX."bannedhashes` ( `md5` , `bantime` , `description` ) VALUES ( '".mysql_real_escape_string($_POST['hash'])."' , '".mysql_real_escape_string($_POST['banhashtime'])."' , '".mysql_real_escape_string($_POST['banhashdesc'])."' )");
									management_addlogentry('Banned md5 hash ' . $_POST['hash'] . ' with a description of ' . $_POST['banhashdesc'], 8);
								}
							}
						}
						if ($_POST['quickbanboard'] != '' && $_POST['quickbanthreadid'] != '') {
							$tpl_page .= '<br><br><meta http-equiv="refresh" content="1;url=' . KU_BOARDSPATH . '/' . $_POST['quickbanboard'] . '/';
							if ($_POST['quickbanthreadid'] != '0') {
								$tpl_page .= 'res/' . $_POST['quickbanthreadid'] . '.html';
							}
							$tpl_page .= '"><a href="' . KU_BOARDSPATH . '/' . $_POST['quickbanboard'] . '/';
							if ($_POST['quickbanthreadid'] != '0') {
								$tpl_page .= 'res/' . $_POST['quickbanthreadid'] . '.html';
							}
							$tpl_page .= '">' . _gettext('Redirecting') . '</a>...';
						}
					}
					} else {
						$tpl_page .= _gettext('Please enter a positive amount of seconds, or zero for a permanent ban.');
				}
				$tpl_page .= '<hr>';
			}
		} elseif (isset($_GET['delban'])) {
			if ($_GET['delban'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `id` = '" . mysql_real_escape_string($_GET['delban']) . "'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$unban_ip = md5_decrypt($line['ip'], KU_RANDOMSEED);
						$name = $line['by'];
					}
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `username` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '".$name."' AND `type` = 1");
					if(count($results) > 0) {
						if (!$this->CurrentUserIsAdministrator()) {
							exitWithErrorPage("You do not have permission to remove this ban");
						}
					}
					$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "banlist` WHERE `id` = '" . mysql_real_escape_string($_GET['delban']) . "'");
					if(isset($_GET['appeal'])) {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "appeal` SET `status` = '0' WHERE `id` = '" . mysql_real_escape_string($_GET['appeal']) . "'");
					}
					$bans_class->UpdateHtaccess();
					$tpl_page .= _gettext('Ban successfully removed.');
					management_addlogentry(_gettext('Unbanned') . ' ' . $unban_ip, 8);
				} else {
					$tpl_page .= _gettext('Invalid ban ID');
				}
				$tpl_page .= '<hr>';
			}
		} elseif (isset($_GET['delhashid'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "bannedhashes` WHERE `id` = '" . mysql_real_escape_string($_GET['delhashid']) . "'");
			if (count($results) > 0) {
				$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "bannedhashes` WHERE `id` = '" . mysql_real_escape_string($_GET['delhashid']) . "'");
				$tpl_page .= 'Hash removed from ban list.<hr>';
			}
		} elseif (isset($_GET['denyappeal'])) {
			if ($_GET['denyappeal'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `id` = '" . mysql_real_escape_string($_GET['denyappeal']) . "'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$unban_ip = md5_decrypt($line['ip'], KU_RANDOMSEED);
					}
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "appeal` SET `status` = '2' WHERE `id` = '" . mysql_real_escape_string($_GET['appeal']) . "'");					
					$bans_class->UpdateHtaccess();
					$tpl_page .= _gettext('Appeal successfully denied.');
					management_addlogentry(_gettext('Denied the ban appeal for') . ' ' . $unban_ip, 8);
				} else {
					$tpl_page .= _gettext('Invalid ban ID');
				}
				$tpl_page .= '<hr>';
			}
		}
		if (isset($_GET['banboard']) && isset($_GET['banpost'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysql_real_escape_string($_GET['banboard']) . "'");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$ban_board_name = $line['name'];
				}
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts_" . $ban_board_name . "` WHERE `id` = '" . mysql_real_escape_string($_GET['banpost']) . "'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$ban_ip = md5_decrypt($line['ip'], KU_RANDOMSEED);
						$ban_hash = $line['filemd5'];
						$ban_parentid = $line['parentid'];
					}
				} else {
					$tpl_page .= _gettext('A post with that ID does not exist.') . '<hr>';
				}
			}
		}
		flush();
		
		$tpl_page .= '<form action="manage_page.php?action=bans" method="post" name="banform">';
		
		$isquickban = false;
		if ($ban_ip != '') {
			$tpl_page .= '<input type="hidden" name="quickbanboard" value="' . $_GET['banboard'] . '"><input type="hidden" name="quickbanthreadid" value="' . $ban_parentid . '"><input type="hidden" name="quickbanpostid" value="' . $_GET['banpost'] . '">';
			$isquickban = true;
		} elseif (isset($_GET['ip'])) {
			$ban_ip = $_GET['ip'];
		}
		if ($isquickban) {
			 management_addlogentry('Looked up IP for post #<a href="?action=viewdeletedthread&board='.$_GET['banboard'].'&thread=' . $_GET['banpost'] . '">' . htmlentities($_GET['banpost']) . '</a> on /'.htmlentities($_GET['banboard']) .'/', 8);	
			$tpl_page .= '<fieldset><legend>Quick ban (<span style="color: red"><b>Banning for Spam or CP? Make sure to delete the <a href="?action=userlog">Userlog</a> entry first.</b></span>)</legend><form action="?manage_page.php?action=bans&quickban=1"><label for="proxy">Proxy</label><input type="checkbox" name="proxy"/><label for="cp">CP</label><input type="checkbox" name="cp"/><label for="spam">Spam</label><input type="checkbox" name="spam"/><br/>';
			$tpl_page .='<label for="quickdelmsg">Delete post:</label>
			<input type="checkbox" name="quickdelmsg2" checked="checked"/>
			<div class="desc">If checked, the post will be deleted as well.</div><br/><input type="submit" value="Go" name="quicksubmit"/></fieldset><fieldset>';
		}
		$tpl_page .= '<fieldset>
		<legend>IP address lookup</legend>';
		if (isset($_POST['post_id']) && isset($_POST['board'])) {
			if(!empty($_POST['post_id']) && !empty($_POST['board'])) {	
				$board = $_POST['board'];
				$post_id = $_POST['post_id'];	
				$result = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `".KU_DBPREFIX."posts_". mysql_real_escape_string($board) ."` WHERE id = '" . $post_id . "'");		
				if (count($result) == 1) {
					foreach ($result as $line) {				
						$ip_mod = md5_decrypt($line['ip'], KU_RANDOMSEED);
					}
					$tpl_page .= 'IP address: <b>'. $ip_mod . '</b><br/><br/>' . "\n";
				}
				else {
					$tpl_page .= "Invalid post ID, try again.<br/><br/>" . "\n";
				}
			}
		}
		$tpl_page .= '<label for="board">'._gettext('Board').':</label>
		'.$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])) .'
		<br/>
		<label for="post_id">'._gettext('Post ID').':</label>
		<input type="text" name="post_id" value="">
		<br/>
		<input type="submit" value="'._gettext('Get IP').'">
		</fieldset>
		<fieldset>
		<legend>IP address and ban type</legend>
		<label for="ip">'._gettext('IP').':</label>
		<input type="text" name="ip" value="'.$ban_ip.'">';
		if ($ban_ip != '') { $tpl_page .= '&nbsp;&nbsp;<a href="?action=deletepostsbyip&ip=' . $ban_ip . '" target="_blank">' . _gettext('Delete all posts by this IP') . '</a>'; }
		$tpl_page .= '<br>
		<label for="allowread">Allow read:</label>
		<select name="allowread"><option value="1">Yes</option><option value="0">No</option></select>
		<div class="desc">Whether or not the user(s) affected by this ban will be allowed to read the boards.<br/><b>Warning</b>: Selecting No will prevent any reading of any page on the level of the boards on the server &amp; a global site ban.</div><br>
		
		<label for="type">Type:</label>
		<select name="type"><option value="0">Single IP</option><option value="1">IP Range</option></select>
		<div class="desc">The type of the ban.  A single IP can be banned by providing the full address, or an IP range can be banned by providing the range you wish to ban. If you set a range ban, the format should be *.*.*.* e.g. 72.234.*.*</div><br>';
		
		if ($isquickban && KU_BANMSG != '') {
			$tpl_page .= '<label for="addbanmsg">Add ban message:</label>
			<input type="checkbox" name="addbanmsg" checked>
			<div class="desc">If checked, the configured ban message will be added to the end of the post.</div><br>';
			$tpl_page .='<label for="quickdelmsg">Delete post:</label>
			<input type="checkbox" name="quickdelmsg"/>
			<div class="desc">If checked, the post will be deleted as well.</div><br>';
		}
		
		$tpl_page .= '</fieldset>
		<fieldset>
		<legend>' . _gettext('Ban from') . '</legend>
		<label for="banfromall"><b>'._gettext('All boards').'</b></label>
		<input type="checkbox" name="banfromall"><br><hr><br>' .
		$this->MakeBoardListCheckboxes('bannedfrom', $this->BoardList($_SESSION['manageusername'])) .
		'</fieldset>';
		
		if (isset($ban_hash)) {
			$tpl_page .= '<fieldset>
			<legend>Ban file</legend>
			<input type="hidden" name="hash" value="' . $ban_hash . '">
			
			<label for="banhashtime">Ban file hash for:</label>
			<input type="text" name="banhashtime">
			<div class="desc">The amount of time to ban the hash of the image which was posted under this ID.  Leave blank to not ban the image, 0 for a infinite global ban, or any number of seconds for that duration of a global ban.</div><br>
			</fieldset>';
		}
		
		$tpl_page .= '<fieldset>
		<legend>Ban duration, reason, and appeal information</legend>
		<label for="seconds">'._gettext('Seconds').':</label>
		<input type="text" name="seconds">
		<div class="desc">'._gettext('Presets').':&nbsp;<a href="#" onclick="document.banform.seconds.value=\'3600\';">1hr</a>&nbsp;<a href="#" onclick="document.banform.seconds.value=\'86400\';">1d</a>&nbsp;<a href="#" onclick="document.banform.seconds.value=\'604800\';">1w</a>&nbsp;<a href="#" onclick="document.banform.seconds.value=\'1209600\';">2w</a>&nbsp;<a href="#" onclick="document.banform.seconds.value=\'2592000\';">30d</a>&nbsp;<a href="#" onclick="document.banform.seconds.value=\'31536000\';">1yr</a>&nbsp;<a href="#" onclick="document.banform.seconds.value=\'0\';">never</a></div><br>
		<label for="reason">'._gettext('Reason').':</label>
		<input type="text" name="reason">
		<div class="desc">'._gettext('Presets').':&nbsp;<a href="#" onclick="document.banform.reason.value=\'Child Pornography\';">CP</a>&nbsp;<a href="#" onclick="document.banform.reason.value=\'Proxy\';">Proxy</a></div><br>';
		$tpl_page .= '<label for="reason">'._gettext('Moderator Note').':</label>
		<input type="text" name="note"><div class="desc">Note to moderators on why the user was banned</div><br/>';
		if (KU_APPEAL != '') {
			$tpl_page .= '<label for="appealdays">Appeal (days):</label>
			<input type="text" name="appealdays" value="5">
			<div class="desc">'._gettext('Presets').':&nbsp;<a href="#" onclick="document.banform.appealdays.value=\'0\';">No Appeal</a>&nbsp;<a href="#" onclick="document.banform.appealdays.value=\'5\';">5 days</a>&nbsp;<a href="#" onclick="document.banform.appealdays.value=\'10\';">10 days</a>&nbsp;<a href="#" onclick="document.banform.appealdays.value=\'30\';">30 days</a></div><br>';
		}
		if($isquickban) {
			$tpl_page .= '<b><span style="color: red">Check this IP is not already banned before banning! If "Single IP bans" is empty, this IP hasn\'t been banned.</span>';
		}
		$tpl_page .= '</fieldset>
		<input type="submit" value="'._gettext('Add ban').'">
		
		</form>
		<hr><br>';
		
		if (isset($_GET['allbans'])) {
			$results1 = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '1' ORDER BY `id` DESC");
			$results0 = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '0' ORDER BY `id` DESC");
		} else if (isset($_GET['getbans'])) {
		$getbans = mysql_real_escape_string($_GET['getbans']);
                $results1 = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '1' ORDER BY `id` DESC LIMIT $getbans");
				$results0 = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '0' ORDER BY `id` DESC LIMIT $getbans");
		} else {
		$results1 = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '1' ORDER BY `id` DESC LIMIT 5");
		if(isset($_GET['banboard']) && isset($_GET['banpost'])) {
			$results0 = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `ipmd5` = '" . md5($ban_ip) . "' AND `type` = '0' ORDER BY `id` DESC");
        }
		else {
			$results0 = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '0' ORDER BY `id` DESC LIMIT 5");
		}
		}
		//type 1
		$tpl_page .= '<b>IP Range bans:</b><br>';
		$tpl_page .= '<table border="1" width="100%"><tr><th>IP Range</th><th>Boards</th><th>Reason</th><th>Moderator Note</th><th>Date Added</th><th>Expires</th><th>Added By</th><th>&nbsp;</th></tr>';
		
		foreach ($results1 as $line) {
			$count += 1;
			if ($count%2 == false) {
                                $linecolour = "#9988EE";
                        } else {
                                $linecolour = "#D6DAF0";
			}

			$tpl_page .= "<tr>";
			$tpl_page .= '<td><a href="?action=bans&ip=' . md5_decrypt($line['ip'], KU_RANDOMSEED) . '">' . md5_decrypt($line['ip'], KU_RANDOMSEED) . '</a></td><td>';
			if ($line['globalban'] == '1') {
				$tpl_page .= '<b>All boards</b>';
			} else {
				if ($line['boards'] != '') {
					$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>&nbsp;';
				}
			}
			$tpl_page .= '</td><td>';
			if ($line['reason'] != '') {
				$tpl_page .= htmlentities(stripslashes($line['reason']));
			} else {
				$tpl_page .= '&nbsp;';
			}
			$tpl_page .= '</td><td>';
			if ($line['note'] != '') {
				$tpl_page .= htmlentities(stripslashes($line['note']));
			} else {
				$tpl_page .= '&nbsp;';
			}
			$tpl_page .= '</td><td>' . date("F j, Y, g:i a", $line['at']) . '</td><td>';
			if ($line['until'] == '0') {
				$tpl_page .= '<b>Does not expire</b>';
			} else {
				$tpl_page .= date("F j, Y, g:i a", $line['until']);
			}
			$tpl_page .= '</td><td>' . $line['by'] . '</td><td>[<a href="manage_page.php?action=bans&delban=' . $line['id'] . '">x</a>]</td></tr>';
		}
		$tpl_page .= '</table>';
		
		//type 0
		$tpl_page .= '<br><b>Single IP bans:</b><br>';
		$tpl_page .= '<table border="1" width="100%"><tr><th>IP Address</th><th>Boards</th><th>Reason</th><th>Moderator Note</th><th>Date Added</th><th>Expires</th><th>Added By</th><th>&nbsp;</th></tr>';
		
		foreach ($results0 as $line) {
			$count += 1;
                        if ($count%2 == false) {
                                $linecolour = "#9988EE";
                        } else {
                                $linecolour = "#D6DAF0";
                        }

			$tpl_page .= "<tr>";
			$tpl_page .= '<td><a href="?action=bans&ip=' . md5_decrypt($line['ip'], KU_RANDOMSEED) . '">' . md5_decrypt($line['ip'], KU_RANDOMSEED) . '</a></td><td>';

			if ($line['globalban'] == '1') {
				$tpl_page .= '<b>All boards</b>';
			} else {
				if ($line['boards'] != '') {
					$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>&nbsp;';
				}
			}
			$tpl_page .= '</td><td>';
			if ($line['reason'] != '') {
				$tpl_page .= htmlentities(stripslashes($line['reason']));
			} else {
				$tpl_page .= '&nbsp;';
			}
			$tpl_page .= '</td><td>';
			if ($line['note'] != '') {
				$tpl_page .= htmlentities(stripslashes($line['note']));
			} else {
				$tpl_page .= '&nbsp;';
			}
			$tpl_page .= '</td><td>' . date("F j, Y, g:i a", $line['at']) . '</td><td>';
			if ($line['until'] == '0') {
				$tpl_page .= '<b>Does not expire</b>';
			} else {
				$tpl_page .= date("F j, Y, g:i a", $line['until']);
			}
			$tpl_page .= '</td><td>' . $line['by'] . '</td><td>[<a href="manage_page.php?action=bans&delban=' . $line['id'] . '">x</a>]</td></tr>';
		}
		$tpl_page .= '</table>';

		if (isset($_GET['hashbans'])) {
		$tpl_page .= '<br><br><b>File hash bans:</b><br><table border="1" width="100%"><tr><th>Hash</th><th>Description</th><th>Ban time</th><th>&nbsp;</th></tr>';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `".KU_DBPREFIX."bannedhashes`");
		if (count($results) == 0) {
			$tpl_page .= '<tr><td colspan="4">None</td></tr>';
		} else {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['md5'] . '</td><td>' . $line['description'] . '</td><td>';
				if ($line['bantime'] == 0) {
					$tpl_page .= '<b>' . _gettext('Does not expire') . '</b>';
				} else {
					$tpl_page .= $line['bantime'] . ' seconds';
				}
				$tpl_page .= '</td><td>[<a href="?action=bans&delhashid=' . $line['id'] . '">x</a>]</td></tr>';
			}
		}
		$tpl_page .= '</table>';
		} else {
			$tpl_page .= 'Last <a href="?action=bans&getbans=10">10</a>, <a href="?action=bans&getbans=20">20</a>, <a href="?action=bans&getbans=30">30</a> Bans | <a href="?action=bans&allbans=1">All Bans</a> | <a href="?action=bans&hashbans=1">Hash Bans</a>';
		}
	}
	
	/* Delete a post, or multiple posts */
	function delposts($multidel=false) {
		global $tc_db, $smarty, $tpl_page, $board_class;
		if($this->CheckAccess() < 3) {
			exitWithErrorPage('You do not have permission to access this page');
		}
		$tpl_page .= '<h2>' . ucwords(_gettext('Delete thread/post')) . '</h2><br>';
		if (isset($_POST['boarddir']) || isset($_GET['boarddir'])) {
			if (isset($_GET['boarddir'])) {
				$isquickdel = true;
				$_POST['boarddir'] = $_GET['boarddir'];
				if (isset($_GET['delthreadid'])) {
					$_POST['delthreadid'] = $_GET['delthreadid'];
				}
				if (isset($_GET['delpostid'])) {
					$_POST['delpostid'] = $_GET['delpostid'];
				}
			}
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysql_real_escape_string($_POST['boarddir']) . "'");
			if (count($results) > 0) {
				if (!$this->CurrentUserIsModeratorOfBoard($_POST['boarddir'], $_SESSION['manageusername'])) {
					exitWithErrorPage(_gettext('You are not a moderator of this board.'));
				}
				foreach ($results as $line) {
					$board_id = $line['id'];
					$board_dir = $line['name'];
				}
				if (isset($_POST['delthreadid'])) {
					if ($_POST['delthreadid'] > 0) {
						$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts_" . $board_dir . "` WHERE `IS_DELETED` = '0' AND  `id` = '" . mysql_real_escape_string($_POST['delthreadid']) . "' AND `parentid` = '0'");
						if (count($results) > 0) {
							foreach ($results as $line) {
								$delthread_id = $line['id'];
							}
							$post_class = new Post($delthread_id, $board_dir);
							if (isset($_POST['archive'])) {
								$numposts_deleted = $post_class->Delete(true);
							} else {
								$numposts_deleted = $post_class->Delete();
							}
							$board_class = new Board($board_dir);
							$board_class->RegenerateAll();
							$tpl_page .= _gettext('Thread '.$delthread_id.' successfully deleted.');
							management_addlogentry(_gettext('Deleted thread') . ' #<a href="?action=viewdeletedthread&board='.$_POST['boarddir'].'&thread=' . $delthread_id . '">' . $delthread_id  . '</a> - ' . '/' . $_POST['boarddir'] . '/', 7);
							if ($_GET['postid'] != '') {
								$tpl_page .= '<br><br><meta http-equiv="refresh" content="1;url=' . KU_CGIPATH .  '/manage_page.php?action=bans&banboard=' . $_GET['boarddir'] . '&banpost=' . $_GET['postid'] . '"><a href="' . KU_CGIPATH . '/manage_page.php?action=bans&banboard=' . $_GET['boarddir'] . '&banpost=' . $_GET['postid'] . '">' . _gettext('Redirecting') . '</a> to ban page...';
							} elseif ($isquickdel) {
								$tpl_page .= '<br><br><meta http-equiv="refresh" content="1;url=' . KU_BOARDSPATH . '/' . $_GET['boarddir'] . '/"><a href="' . KU_BOARDSPATH . '/' . $_GET['boarddir'] . '/">' . _gettext('Redirecting') . '</a> back to board...';
							}
						} else {
							$tpl_page .= _gettext('Invalid thread ID '.$delpost_id.'.  This may have been caused by the thread recently being deleted.');
						}
					}
				} elseif (isset($_POST['delpostid'])) {
					if ($_POST['delpostid'] > 0) {
						$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts_" . $board_dir . "` WHERE `IS_DELETED` = '0' AND  `id` = '" . mysql_real_escape_string($_POST['delpostid']) . "'");
						if (count($results) > 0) {
							foreach ($results as $line) {
								$delpost_id = $line['id'];
								$delpost_parentid = $line['parentid'];
							}
							$post_class = new Post($delpost_id, $board_dir);
							$post_class->Delete();
							$board_class = new Board($board_dir);
							$board_class->RegenerateThread($delpost_parentid);
							$board_class->RegeneratePages();
							$tpl_page .= _gettext('Post '.$delpost_id.' successfully deleted.');
							management_addlogentry(_gettext('Deleted post') . ' #<a href="?action=viewdeletedthread&board=' . $_POST['boarddir'] . '&thread=' . $delpost_parentid . '#' . $delpost_id . '">' . $delpost_id . '</a> - /' . $board_dir . '/', 7);
							if ($_GET['postid'] != '') {
								$tpl_page .= '<br><br><meta http-equiv="refresh" content="1;url=' . KU_CGIPATH . '/manage_page.php?action=bans&banboard=' . $_GET['boarddir'] . '&banpost=' . $_GET['postid'] . '"><a href="' . KU_CGIPATH . '/manage_page.php?action=bans&banboard=' . $_GET['boarddir'] . '&banpost=' . $_GET['postid'] . '">' . _gettext('Redirecting') . '</a> to ban page...';
							} elseif ($isquickdel) {
								$tpl_page .= '<br><br><meta http-equiv="refresh" content="1;url=' . KU_BOARDSPATH . '/' . $_GET['boarddir'] . '/res/' . $delpost_parentid . '.html"><a href="' . KU_BOARDSPATH . '/' . $_GET['boarddir'] . '/res/' . $delpost_parentid . '.html">' . _gettext('Redirecting') . '</a> back to thread...';
							}
						} else {
							$tpl_page .= _gettext('Invalid thread ID '.$delpost_id.'.  This may have been caused by the thread recently being deleted.');
						}
					}
				}
			} else {
				$tpl_page .= _gettext('Invalid board directory.');
			}
			$tpl_page .= '<hr>';
		}
		if (!$multidel) {
			$tpl_page .= '<form action="manage_page.php?action=delposts" method="post">
			<label for="boarddir">'._gettext('Board').':</label>' .
			$this->MakeBoardListDropdown('boarddir', $this->BoardList($_SESSION['manageusername'])) .
			'<br>
			
			<label for="delthreadid">'._gettext('Thread').':</label>
			<input type="text" name="delthreadid"><br>
			
			<input type="submit" value="'._gettext('Delete thread').'">
			
			</form>
			<br><hr>
			
			<form action="manage_page.php?action=delposts" method="post">
			<label for="boarddir">'._gettext('Board').':</label>' .
			$this->MakeBoardListDropdown('boarddir', $this->BoardList($_SESSION['manageusername'])) .
			'<br>
			
			<label for="delpostid">'._gettext('Post').':</label>
			<input type="text" name="delpostid"><br>
			
			<label for="archive">Archive:</label>
			<input type="checkbox" name="archive"><br>
			
			<input type="submit" value="'._gettext('Delete post').'">
		
			</form>';
		}
	}
	
	function proxyban() {
		global $tpl_page;
		$this->AdministratorsOnly();
		
		$tpl_page .= '<h2>' . ucwords(_gettext('Ban proxy list')) . '</h2><br>';
		if (isset($_FILES['imagefile'])) {
			$bans_class = new Bans;
			$ips = 0;
			$successful = 0;
			$proxies = file($_FILES['imagefile']['tmp_name']);
			foreach($proxies as $proxy) {
				if (preg_match('/.[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+.*/', $proxy)) {
					//$proxy = trim($proxy, " \t\n\r\0\x0B");
					$proxy = trim($proxy);
					$ips++;
					if ($bans_class->BanUser(preg_replace('/:.*/', '', $proxy), 'SERVER', 1, 0, '', 'This IP address is banned (Proxy)', 0, 0, 0, 'Proxy')) {
						$successful++;
					}
				}
			}
			management_addlogentry(sprintf(_gettext('Banned %d IP addresses using an IP address list.'), $successful), 8);
			$tpl_page .= $successful . ' of ' . $ips . ' IP addresses banned.';
		} else {
			$tpl_page .= '<form id="postform" action="' . KU_CGIPATH . '/manage_page.php?action=proxyban" method="post" enctype="multipart/form-data">'._gettext('Proxy list').'<input type="file" name="imagefile" size="35" accesskey="f"><br>
			<input type="submit" value="Submit">
			<br>The proxy list is assumed to be in plaintext *.*.*.*:port or *.*.*.* format, one IP per line.<br><br>';
		}
	}
	
	/* Called from a board's page using the multidel button */
	function multidel() {
		global $tc_db, $smarty, $tpl_page, $bans_class;
		
		$multidel = TRUE;
		$_POST['seconds'] = 0;
		$multiban_query = 'WHERE `id` = "0 " ';
		foreach($_POST AS $TOAST) {
			if (ereg('POST*',$TOAST)){
				$_POST['boarddir'] = $_POST['board'];
				$_POST['delpostid'] = ereg_replace('POST','',$TOAST);
				$this->delposts($multidel);
				if (($_POST['multiban'])) { $multiban_query .= "OR `id` = '".mysql_real_escape_string($_POST['delpostid'])."'"; }
			}
		}
		
		if (isset($_POST['multiban'])) {
			$this->ModeratorsOnly();
			$ban_globalban = '1';
			$_POST['seconds'] = '0';
			$ban_boards = '';
			
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `ip` FROM `".KU_DBPREFIX . "posts_".mysql_real_escape_string($_POST['board'])."` ".$multiban_query);
			if (count($results) > 0) {
				foreach ($results as $line) {
					$ban_ip = md5_decrypt($line['ip'], KU_RANDOMSEED);
					$bans_class->BanUser($ban_ip, mysql_real_escape_string($_SESSION['manageusername']), $ban_globalban, 0, $ban_boards, mysql_real_escape_string($_POST['reason']), 0, 0, 1);
					$logentry = _gettext('Banned') . ' ' . $ban_ip . ' until ';
					if ($_POST['seconds'] == '0') {
						$logentry .= '<b>' . _gettext('Does not expire') . '</b>';
					} else {
						$logentry .= date('F j, Y, g:i a', time() + $_POST['seconds']);
					}
					$logentry .= ' - ' . _gettext('Reason') . ': ' . $_POST['reason'] . ' - ' . _gettext('Banned from') . ': ';
					if ($ban_globalban == '1') {
						$logentry .= _gettext('All boards') . ' ';
					} else {
						$logentry .= '/' . implode('/, /', explode('|', $ban_boards)) . '/ ';
					}
					management_addlogentry($logentry, 8);
				}
			} else {
				$tpl_page .= _gettext('A post with that ID does not exist.') . '<hr>';
			}
		}
	}
	
	/* Replace words in posts with something else */
	function wordfilter() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		
		$tpl_page .= '<h2>' . _gettext('Wordfilter') . '</h2><br>';
		if (isset($_POST['word'])) {
			if ($_POST['word'] != '' && $_POST['replacedby'] != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "wordfilter` WHERE `word` = '" . mysql_real_escape_string($_POST['word']) . "'");
				if (count($results) == 0) {
					$wordfilter_boards = array();
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
					if (isset($_POST['filterfromall'])) {
						foreach ($results as $line) {
							$wordfilter_new_boards[] = $line['name'];
						}
					}
					else {
						foreach ($results as $line) {
							$wordfilter_boards = array_merge($wordfilter_boards, array($line['name']));
						}
						$wordfilter_changed_boards = array();
						$wordfilter_new_boards = array();
						while (list($postkey, $postvalue) = each($_POST)) {
							if (substr($postkey, 0, 10) == 'wordfilter') {
								$wordfilter_changed_boards = array_merge($wordfilter_changed_boards, array(substr($postkey, 10)));
							}
						}
						while (list(, $wordfilter_thisboard_name) = each($wordfilter_boards)) {
							if (in_array($wordfilter_thisboard_name, $wordfilter_changed_boards)) {
								$wordfilter_new_boards = array_merge($wordfilter_new_boards, array($wordfilter_thisboard_name));
							}
						}
					}
					$is_regex = (isset($_POST['regex'])) ? '1' : '0';
					
					$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "wordfilter` ( `word` , `replacedby` , `boards` , `time` , `regex` ) VALUES ( '" . mysql_real_escape_string($_POST['word']) . "' , '" . mysql_real_escape_string($_POST['replacedby']) . "' , '" . mysql_real_escape_string(implode('|', $wordfilter_new_boards)) . "' , '" . time() . "' , '" . $is_regex . "' )");
					
					$tpl_page .= _gettext('Word successfully added.');
					management_addlogentry("Added word to wordfilter: " . $_POST['word'] . " - Changes to: " . $_POST['replacedby'] . " - Boards: /" . implode('/, /', explode('|', implode('|', $wordfilter_new_boards))) . "/", 11);
				} else {
					$tpl_page .= _gettext('That word already exists.');
				}
			} else {
				$tpl_page .= _gettext('Please fill in all required fields.');
			}
			$tpl_page .= '<hr>';
		} elseif (isset($_GET['delword'])) {
			if ($_GET['delword'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "wordfilter` WHERE `id` = '" . mysql_real_escape_string($_GET['delword']) . "'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$del_word = $line['word'];
					}
					$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "wordfilter` WHERE `id` = '" . mysql_real_escape_string($_GET['delword']) . "'");
					$tpl_page .= _gettext('Word successfully removed.');
					management_addlogentry(_gettext('Removed word from wordfilter') . ': ' . $del_word, 11);
				} else {
					$tpl_page .= _gettext('That ID does not exist.');
				}
				$tpl_page .= '<hr>';
			}
		} elseif (isset($_GET['editword'])) {
			if ($_GET['editword'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "wordfilter` WHERE `id` = '" . mysql_real_escape_string($_GET['editword']) . "'");
				if (count($results) > 0) {
					if (!isset($_POST['replacedby'])) {
						foreach ($results as $line) {
							$tpl_page .= '<form action="manage_page.php?action=wordfilter&editword='.$_GET['editword'].'" method="post">
							
							<label for="word">'._gettext('Word').':</label>
							<input type="text" name="word" value="'.$line['word'].'" disabled><br>
							
							<label for="replacedby">'._gettext('Is replaced by').':</label>
							<input type="text" name="replacedby" value="'.$line['replacedby'].'"><br>
							
							<label for="regex">'._gettext('Regular expression').':</label>
							<input type="checkbox" name="regex"';
							if ($line['regex'] == '1') {
								$tpl_page .= ' checked';
							}
							$tpl_page .= '><br>
				
							<label>'._gettext('Boards').':</label><br>';
							
							$array_boards = array();
							$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
							foreach ($resultsboard as $lineboard) {
								$array_boards = array_merge($array_boards, array($lineboard['name']));
							}
							foreach ($array_boards as $this_board_name) {
								$tpl_page .= '<label for="wordfilter' . $this_board_name . '">' . $this_board_name . '</label><input type="checkbox" name="wordfilter' . $this_board_name . '" ';
								if (in_array($this_board_name, explode("|", $line['boards'])) && explode("|", $line['boards']) != '') {
									$tpl_page .= 'checked ';
								}
								$tpl_page .= '><br>';
							}
							$tpl_page .= '<br>
							
							<input type="submit" value="'._gettext('Edit word').'">
							
							</form>';
						}
					} else {
						$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "wordfilter` WHERE `id` = '" . mysql_real_escape_string($_GET['editword']) . "'");
						if (count($results) > 0) {
							foreach ($results as $line) {
								$wordfilter_word = $line['word'];
							}
							$wordfilter_boards = array();
							$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
							foreach ($results as $line) {
								$wordfilter_boards = array_merge($wordfilter_boards, array($line['name']));
							}
							$wordfilter_changed_boards = array();
							$wordfilter_new_boards = array();
							while (list($postkey, $postvalue) = each($_POST)) {
								if (substr($postkey, 0, 10) == "wordfilter") {
									$wordfilter_changed_boards = array_merge($wordfilter_changed_boards, array(substr($postkey, 10)));
								}
							}
							while (list(, $wordfilter_thisboard_name) = each($wordfilter_boards)) {
								if (in_array($wordfilter_thisboard_name, $wordfilter_changed_boards)) {
									$wordfilter_new_boards = array_merge($wordfilter_new_boards, array($wordfilter_thisboard_name));
								}
							}
							$is_regex = (isset($_POST['regex'])) ? '1' : '0';
							
							if($tc_db->Execute("UPDATE `" . KU_DBPREFIX . "wordfilter` SET `replacedby` = '" . mysql_real_escape_string($_POST['replacedby']) . "' , `boards` = '" . mysql_real_escape_string(implode('|', $wordfilter_new_boards)) . "' , `regex` = '" . $is_regex . "' WHERE `id` = '" . mysql_real_escape_string($_GET['editword']) . "'")) {							
								$tpl_page .= _gettext('Word successfully updated.');
							}
							else {
								$tpl_page .= _gettext('Word failed to update') . mysql_error();
							}
							management_addlogentry(_gettext('Updated word on wordfilter') . ': ' . $wordfilter_word, 11);
						} else {
							$tpl_page .= _gettext('Unable to locate that word.');
						}
					}
				} else {
					$tpl_page .= _gettext('That ID does not exist.');
				}
				$tpl_page .= '<hr>';
			}
		} else {
			$tpl_page .= '<form action="manage_page.php?action=wordfilter" method="post">
			
			<label for="word">'._gettext('Word').'.:</label>
			<input type="text" name="word"><br>
		
			<label for="replacedby">'._gettext('Is replaced by').':</label>
			<input type="text" name="replacedby"><br>
			
			<label for="regex">'._gettext('Regular expression').':</label>
			<input type="checkbox" name="regex"><br>';
	
			$array_boards = array();
			$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
			$tpl_page .= '		<label for="filterfromall"><b>'._gettext('All boards').'</b></label>
			<input type="checkbox" name="filterfromall"><br>OR<br>';
			foreach ($resultsboard as $lineboard) {
				$array_boards = array_merge($array_boards, array($lineboard['name']));
			}
			$tpl_page .= $this->MakeBoardListCheckboxes('wordfilter', $array_boards) .
			'<br>
			
			<input type="submit" value="'._gettext('Add word').'">
			
			</form>
			<hr>';
		}
		$tpl_page .= '<br>';
		
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "wordfilter`");
		if ($results > 0) {
			$tpl_page .= '<table border="1" width="100%"><tr><th>' . _gettext('Word') . '</th><th>' . _gettext('Replacement') . '</th><th>' . _gettext('Boards') . '</th><th>&nbsp;</th></tr>' . "\n";
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['word'] . '</td><td>' . $line['replacedby'] . '</td><td>';
				if (explode('|', $line['boards']) != '') {
					$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>&nbsp;';
				} else {
					$tpl_page .= _gettext('No boards');
				}
				$tpl_page .= '</td><td>[<a href="manage_page.php?action=wordfilter&editword=' . $line['id'] . '">' . _gettext('Edit') . '</a>]&nbsp;[<a href="manage_page.php?action=wordfilter&delword=' . $line['id'] . '">del</a>]</td></tr>' . "\n";
			}
			$tpl_page .= '</table>';
		}
	}
	
	function addboard() {
		global $tc_db, $smarty, $tpl_page, $board_class;
		$this->AdministratorsOnly();
		//die('I don\'t think so, Jim');
		$tpl_page .= '<h2>' . ucwords(_gettext('Add board')) . '</h2><br>';
		if (isset($_POST['directory'])) {
			$_POST['directory'] = cleanBoardName($_POST['directory']);
			if ($_POST['directory'] != '' && $_POST['desc'] != '') {
				if (strtolower($_POST['directory']) != 'allboards') {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysql_real_escape_string($_POST['directory']) . "'");
					if (count($results) == 0) {
						exitWithErrorPage('I can\'t let you do that, Dave.');
						if (mkdir(KU_BOARDSDIR . $_POST['directory'], 0777) && mkdir(KU_BOARDSDIR . $_POST['directory'] . '/res', 0777) && mkdir(KU_BOARDSDIR . $_POST['directory'] . '/src', 0777) && mkdir(KU_BOARDSDIR . $_POST['directory'] . '/thumb', 0777)) {
							file_put_contents(KU_BOARDSDIR . $_POST['directory'] . '/.htaccess', 'DirectoryIndex board.html' . "\n" . 'php_value engine off');
							$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "boards` ( `name` , `desc` , `createdon` ) VALUES ( '" . mysql_real_escape_string($_POST['directory']) . "' , '" . mysql_real_escape_string($_POST['desc']) . "' , '" . time() . "' )");
							$boardid = $tc_db->Insert_Id();
							if ($_POST['firstpostid'] < 1) {
								$_POST['firstpostid'] = 1;
							}
							$tc_db->Execute("CREATE TABLE `" . KU_DBPREFIX . "posts_" . mysql_real_escape_string($_POST['directory']) . "` (
							  `id` int(10) NOT NULL auto_increment,
							  `parentid` int(10) NOT NULL default '0',
							  `name` varchar(255) NOT NULL,
							  `tripcode` varchar(30) NOT NULL,
							  `email` varchar(255) NOT NULL,
							  `subject` varchar(255) NOT NULL,
							  `message` text NOT NULL,
							  `filename` varchar(50) NOT NULL,
							  `filename_original` varchar(50) NOT NULL,
							  `filetype` varchar(20) NOT NULL,
							  `filemd5` char(32) NOT NULL,
							  `image_w` smallint(5) NOT NULL default '0',
							  `image_h` smallint(5) NOT NULL default '0',
							  `filesize` int(10) NOT NULL default '0',
							  `filesize_formatted` varchar(255) NOT NULL,
							  `thumb_w` smallint(5) NOT NULL default '0',
							  `thumb_h` smallint(5) NOT NULL default '0',
							  `password` varchar(255) NOT NULL,
							  `postedat` int(20) NOT NULL,
							  `lastbumped` int(20) NOT NULL default '0',
							  `ip` varchar(75) NOT NULL,
							  `ipmd5` char(32) NOT NULL,
							  `tag` varchar(5) NOT NULL,
							  `stickied` tinyint(1) NOT NULL default '0',
							  `locked` tinyint(1) NOT NULL default '0',
							  `posterauthority` tinyint(1) NOT NULL default '0',
							  `reviewed` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT '0',
							  `deletedat` int(20) NOT NULL default '0',
							  `IS_DELETED` tinyint(1) NOT NULL default '0',
							  UNIQUE KEY `id` (`id`),
							  KEY `parentid` (`parentid`),
							  KEY `lastbumped` (`lastbumped`),
							  KEY `filemd5` (`filemd5`),
							  KEY `stickied` (`stickied`)
							) ENGINE=InnoDB AUTO_INCREMENT=" . mysql_real_escape_string($_POST['firstpostid']) . ";");
							$filetypes = $tc_db->GetAll("SELECT " . KU_DBPREFIX . "filetypes.id FROM " . KU_DBPREFIX . "filetypes WHERE " . KU_DBPREFIX . "filetypes.filetype = 'JPG' OR " . KU_DBPREFIX . "filetypes.filetype = 'GIF' OR " . KU_DBPREFIX . "filetypes.filetype = 'PNG';");
							foreach ($filetypes AS $filetype) {
								$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "board_filetypes` ( `boardid` , `typeid` ) VALUES ( " . $boardid . " , " . $filetype['id'] . " );");
							}
							/* Sleep for five seconds, to ensure the table was created before attempting to initialize a board class with it */
							sleep(5);
							$board_class = new Board(mysql_real_escape_string($_POST['directory']));
							$board_class->RegenerateAll();
							$tpl_page .= _gettext('Board successfully added.') . '<br><br><a href="' . KU_BOARDSPATH . '/' . $_POST['directory'] . '/">/' . $_POST['directory'] . '/</a>!';
							management_addlogentry(_gettext('Added board') . ': /' . $_POST['directory'] . '/', 3);
						} else {
							$tpl_page .= '<br>' . _gettext('Unable to create directories.');
						}
					} else {
						$tpl_page .= _gettext('A board with that name already exists.');
					}
				} else {
					$tpl_page .= _gettext('That name is for internal use.  Please pick another.');
				}
			} else {
				$tpl_page .= _gettext('Please fill in all required fields.');
			}
		}
		$tpl_page .= '<form action="manage_page.php?action=addboard" method="post">
	
		<label for="directory">' . _gettext('Directory') . ':</label>
		<input type="text" name="directory">
		<div class="desc">' . _gettext('The directory of the board.') . '  <b>' . _gettext('Only put in the letter(s) of the board directory, no slashes!') . '</b></div><br>
		
		<label for="desc">' . _gettext('Description') . ':</label>
		<input type="text" name="desc"><div class="desc">' . _gettext('The name of the board.') . '</div><br>
		
		<label for="firstpostid">' . _gettext('First Post ID') . ':</label>
		<input type="text" name="firstpostid" value="1">
		<div class="desc">' . _gettext('The first post of this board will recieve this ID.') . '</div><br>
		
		<input type="submit" value="Add Board">
		
		</form>';
	}
	
	function delboard() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		//die('Stop right there criminal scum');
		$tpl_page .= '<h2>' . ucwords(_gettext('Delete board')) . '</h2><br>';
		if (isset($_POST['directory'])) {
			if ($_POST['directory'] != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysql_real_escape_string($_POST['directory']) . "'");
				foreach ($results as $line) {
					$board_id = $line['id'];
					$board_dir = $line['name'];
				}
				if (count($results) > 0) {
					if (isset($_POST['confirmation'])) {
						if (removeBoard($board_dir)) {
							$tc_db->Execute("DROP TABLE `" . KU_DBPREFIX . "posts_" . $board_dir . "`");
							$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "boards` WHERE `id` = '" . $board_id . "'");
							$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "board_filetypes` WHERE `boardid` = '" . $board_id . "'");
							require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
							$menu_class = new Menu();
							$menu_class->Generate();
							$tpl_page .= _gettext('Board successfully deleted.');
							management_addlogentry(_gettext('Deleted board').': /' . $_POST['directory'] . '/', 3);
						} else {
							/* Error */
							$tpl_page .= _gettext('Unable to delete board.');
						}
					} else {
						$tpl_page .= sprintf(_gettext('Are you absolutely sure you want to delete %s?'),'/' . $board_dir . '/') .
						'<br>
						<form action="manage_page.php?action=delboard" method="post">
						<input type="hidden" name="directory" value="' . $_POST['directory'] . '">
						<input type="hidden" name="confirmation" value="yes">
						
						<input type="submit" value="'._gettext('Continue').'">
						
						</form>';
					}
				} else {
					$tpl_page .= _gettext('A board with that name does not exist.');
				}
			}
			$tpl_page .= '<hr>';
		}
		$tpl_page .= '<form action="manage_page.php?action=delboard" method="post">
		
		<label for="directory">'._gettext('Directory').':</label>' .
		$this->MakeBoardListDropdown('directory', $this->BoardList($_SESSION['manageusername'])) .
		'<br>
		
		<input type="submit" value="'._gettext('Delete board').'">
		
		</form>';
	}
	
	function changepwd() {
		global $tc_db, $smarty, $tpl_page;
		
		$tpl_page .= '<h2>' . ucwords(_gettext('Change account password')) . '</h2><br>';
		if (isset($_POST['oldpwd']) && isset($_POST['newpwd']) && isset($_POST['newpwd2'])) {
			if ($_POST['oldpwd'] != '' && $_POST['newpwd'] != '' && $_POST['newpwd2'] != '') {
				if ($_POST['newpwd'] == $_POST['newpwd2']) {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . mysql_real_escape_string($_SESSION['manageusername']) . "'");
					foreach ($results as $line) {
						$staff_passwordenc = $line['password'];
					}
					if (md5($_POST['oldpwd']) == $staff_passwordenc) {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `password` = '" . md5($_POST['newpwd']) . "' WHERE `username` = '" . mysql_real_escape_string($_SESSION['manageusername']) . "'");
						$_SESSION['managepassword'] = md5($_POST['newpwd']);
						$tpl_page .= _gettext('Password successfully changed.');
					} else {
						$tpl_page .= _gettext('The old password you provided did not match the current one.');
					}
				} else {
					$tpl_page .= _gettext('The second password did not match the first.');
				}
			} else {
				$tpl_page .= _gettext('Please fill in all required fields.');
			}
			$tpl_page .= '<hr>';
		}
		$tpl_page .= '<form action="manage_page.php?action=changepwd" method="post">
		
		<label for="oldpwd">' . _gettext('Old password') . ':</label>
		<input type="password" name="oldpwd"><br>
	
		<label for="newpwd">' . _gettext('New password') . ':</label>
		<input type="password" name="newpwd"><br>
		
		<label for="newpwd2">' . _gettext('New password again') . ':</label>
		<input type="password" name="newpwd2"><br>
		
		<input type="submit" value="' ._gettext('Change account password') . '">
		
		</form>';
	}
	
	function staff() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		$tpl_page .= '<h2>' . _gettext('Staff') . '</h2><br>';
		if (isset($_POST['staffusername']) && isset($_POST['staffpassword'])) {
			if ($_POST['staffusername'] != '' && ($_POST['staffpassword'] != '' || $_POST['type'] == '3')) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . mysql_real_escape_string($_POST['staffusername']) . "'");
				if (count($results) == 0) {
					if ($_POST['type'] == '0' || $_POST['type'] == '1' || $_POST['type'] == '2' || $_POST['type'] == '3') {
						if(!is_numeric($_POST['access'])) {
							exitWithErrorPage('Invalid access level');
						}
						if($_POST['access'] > 9) {
							$accresults = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `access` > '9' AND `type` != '1'");
							if(count($accresults) > 0) {
								exitWithErrorPage('Head Moderator already added');
							}
						}
						$length = strlen($_POST['staffpassword']);
						if($length < 7) {
							exitWithErrorPage('Password must be 7 characters or more');
						}
						$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "staff` ( `username` , `password` , `type` , `addedon` , `access`) VALUES ( '" . mysql_real_escape_string($_POST['staffusername']) . "' , '" . md5($_POST['staffpassword']) . "' , '" . $_POST['type'] . "' , '" . time() . "' , '" . $_POST['access'] . "' )");
					} else {
						exitWithErrorPage('Invalid type.');
					}
					$tpl_page .= _gettext('Staff member successfully added.');
					if ($_POST['type'] != 3) {
						$logentry = _gettext('Added staff member') . ' - ';
						if ($_POST['type'] == '1') {
							$logentry .= _gettext('Administrator');
						} elseif ($_POST['type'] == '2') {
							$logentry .= _gettext('Moderator');
						} elseif ($_POST['type'] == '0') {
							$logentry .= _gettext('Janitor');
						} else {
							$logentry .= 'VIP';
						}
						$logentry .= ": " . $_POST['staffusername'];
					} else {
						$logentry = 'Added a VIP code';
					}
					management_addlogentry($logentry, 6);
				} else {
					$tpl_page .= _gettext('A staff member with that ID already exists.');
				}
				$tpl_page .= '<hr>';
			}
		} elseif (isset($_GET['del'])) {
			if ($_GET['del'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . mysql_real_escape_string($_GET['del']) . "'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$staff_username = $line['username'];
						$staff_type = $line['type'];
					}
					if($line['username'] == KU_ROOT) {
						exitWithErrorPage('Permission denied');
					}
					$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . mysql_real_escape_string($_GET['del']) . "'");
					$tpl_page .= _gettext('Staff successfully deleted');
					if ($staff_type != 3) {
						management_addlogentry(_gettext('Deleted staff member') . ': ' . $staff_username, 6);
					} else {
						management_addlogentry(_gettext('Deleted a VIP code'), 6);
					}
				} else {
					$tpl_page .= _gettext('Invalid staff ID.');
				}
				$tpl_page .= '<hr>';
			}
		} elseif (isset($_GET['suspend'])) {
			if ($_GET['suspend'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . mysql_real_escape_string($_GET['suspend']) . "'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$staff_username = $line['username'];
						$staff_type = $line['type'];
					}
					if($line['username'] == KU_ROOT) {
						exitWithErrorPage('Permission denied');
					}
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `suspended` = 1 WHERE `id` = '" . mysql_real_escape_string($_GET['suspend']) . "'");
					$tpl_page .= _gettext('Staff successfully suspended');
					if ($staff_type != 3) {
						management_addlogentry(_gettext('Suspended staff member') . ': ' . $staff_username, 6);
					} else {
						management_addlogentry(_gettext('Suspended a VIP code'), 6);
					}
				} else {
					$tpl_page .= _gettext('Invalid staff ID.');
				}
				$tpl_page .= '<hr>';
			}
		} elseif (isset($_GET['unsuspend'])) {
			if ($_GET['unsuspend'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . mysql_real_escape_string($_GET['unsuspend']) . "'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$staff_username = $line['username'];
						$staff_type = $line['type'];
					}
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `suspended` = 0 WHERE `id` = '" . mysql_real_escape_string($_GET['unsuspend']) . "'");
					$tpl_page .= _gettext('Staff successfully unsuspended');
					if ($staff_type != 3) {
						management_addlogentry(_gettext('Unsuspended staff member') . ': ' . $staff_username, 6);
					} else {
						management_addlogentry(_gettext('Unsuspended a VIP code'), 6);
					}
				} else {
					$tpl_page .= _gettext('Invalid staff ID.');
				}
				$tpl_page .= '<hr>';
			}
		} elseif (isset($_GET['edit'])) {
			if ($_GET['edit'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . mysql_real_escape_string($_GET['edit']) . "'");
				if (count($results) > 0) {
					if (isset($_POST['submitting'])) {
						if(isset($_POST['access'])) {
							if(!is_numeric($_POST['access'])) {
								exitWithErrorPage('Invalid access code');
							}
							else {
								if($_POST['access'] > 9) {
									$accresults = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `access` > '9' AND `type` != '1'");
									if(count($accresults) > 0) {
										exitWithErrorPage('Head Moderator already added');
									}
								}
								$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `access` = '" . $_POST['access'] . "' WHERE `id` = '" . mysql_real_escape_string($_GET['edit']) . "'");
								$n_results = $tc_db->GetAll("SELECT HIGH_PRIORITY `username` FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . mysql_real_escape_string($_GET['edit']) . "' LIMIT 1");
								foreach($n_results as $line) {
								if($line['username'] == KU_ROOT) {
									exitWithErrorPage('Permission denied');
								}								
									management_addlogentry(_gettext('Updated access level to ') . $_POST['access'] . ' for ' . $line['username'], 6);
								}
							}
						}
						foreach ($results as $line) {
							$staff_username = $line['username'];
							$staff_type = $line['type'];
						}
						if($line['username'] == KU_ROOT) {
							exitWithErrorPage('Permission denied');
						}
						$staff_boards = array();
						if (isset($_POST['moderatesallboards'])) {
							$staff_new_boards = array('allboards');
						} else {
							$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
							foreach ($results as $line) {
								$staff_boards = array_merge($staff_boards, array($line['name']));
							}
							$staff_changed_boards = array();
							$staff_new_boards = array();
							while (list($postkey, $postvalue) = each($_POST)) {
								if (substr($postkey, 0, 8) == "moderate") {
									$staff_changed_boards = array_merge($staff_changed_boards, array(substr($postkey, 8)));
								}
							}
							while (list(, $staff_thisboard_name) = each($staff_boards)) {
								if (in_array($staff_thisboard_name, $staff_changed_boards)) {
									$staff_new_boards = array_merge($staff_new_boards, array($staff_thisboard_name));
								}
							}
						}
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `boards` = '" . mysql_real_escape_string(implode('|', $staff_new_boards)) . "' WHERE `id` = '" . mysql_real_escape_string($_GET['edit']) . "'");
						$tpl_page .= _gettext('Staff successfully updated') . '<hr>';
						if ($_POST['type'] != '3') {
							$logentry = _gettext('Updated staff member') . ' - ';
							if ($_POST['type'] == '1') {
								$logentry .= _gettext('Administrator');
							} elseif ($_POST['type'] == '2') {
								$logentry .= _gettext('Moderator');
							} elseif ($_POST['type'] == '0') {
								$logentry .= _gettext('Janitor');
							} else {
								exitWithErrorPage('Something went wrong.');
							}
							$logentry .= ': ' . $staff_username;
							if ($_POST['type'] != '1') {
								$logentry .= ' - ' . _gettext('Moderates') . ': ';
								if (isset($_POST['moderatesallboards'])) {
									$logentry .= strtolower(_gettext('All boards'));
								} else {
									$logentry .= '/' . implode('/, /', $staff_new_boards) . '/';
								}
							}
						} else {
							$logentry = _gettext('Edited a VIP code');
						}
						management_addlogentry($logentry, 6);
					}
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . $_GET['edit'] . "'");
					foreach ($results as $line) {
						$staff_username = $line['username'];
						$staff_type = $line['type'];
						$staff_boards = explode('|', $line['boards']);
					}
					$tpl_page .= '<form action="manage_page.php?action=staff&edit=' . $_GET['edit'] . '" method="post">
					
					<label for="staffname">' . _gettext('Username') . ':</label>
					<input type="text" name="staffname" value="' . $staff_username . '" disabled><br>
					
					<label for="type">' . _gettext('Type') . ':</label>
					<select name="type">
					<option value="1"';
					if ($staff_type == '1') {
						$tpl_page .= 'selected';
					}
					$tpl_page .= '>' . _gettext('Administrator') . '</option>
					<option value="2"';
					if ($staff_type == '2') {
						$tpl_page .= 'selected';
					}
					$tpl_page .= '>' . _gettext('Moderator') . '</option>
					<option value="0"';
					if ($staff_type == '0') {
						$tpl_page .= 'selected';
					}
					$tpl_page .= '>' . _gettext('Janitor') . '</option>
					<option value="3"';
					if ($staff_type == '3') {
						$tpl_page .= 'selected';
					}
					$tpl_page .= '>VIP</option>
					</select><br><br>';
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `access` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $staff_username . "'LIMIT 1");
						foreach ($results as $line) {
							$tpl_page .= '<label for="moderatesallboards">'._gettext('Access level [<a href="manage_page.php?action=staff&accesshelp=1">?</a>]').':</label> <input type="text" maxlength="4" name="access" value="'.$line['access'].'"/><br/>';
						}
					$tpl_page .= _gettext('Moderates') . '<br>' .
					'<label for="moderatesallboards"><b>'._gettext('All boards').'</b></label>' .
					'<input type="checkbox" name="moderatesallboards"';
					if ($staff_boards == array('allboards')) {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '><br>' . _gettext('or') . '<br>';
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
					foreach ($results as $line) {
						$tpl_page .= '<label for="moderate' . $line['name'] . '">' . $line['name'] . '</label><input type="checkbox" name="moderate' . $line['name'] . '" ';
						if (in_array($line['name'], $staff_boards)) {
							$tpl_page .= 'checked ';
						}
						$tpl_page .= '><br>';
					}
					$tpl_page .= '<input type="submit" value="' . _gettext('Modify staff member') . '" name="submitting">
					
					</form>
					<br>';

				} else {
					$tpl_page .= _gettext('A staff member with that id does not appear to exist.');
				}
				$tpl_page .= '<hr>';
			}
		}
		if($_GET['accesshelp'] == 1) {
			$tpl_page .= '<span>Access levels are a new feature to limit the functions which Moderators can access. For example, if you set
			an access level of 5; the moderator can access all functions below and including Manage stickes/lock threads (which corresponds to level 5).</span><ol><li>Recently uploaded images</li><li>View Reports</li><li>Delete thread/post</li><li>Delete/find all posts by IP</li><li>Manage stickes/lock threads</li>
			<li>View/Add/Remove bans</li><li>All boards ban</li><li>Site ban</li><li>Manage Boards (Super Moderator)</li></ol><br/>';
		}
		$tpl_page .= '<form action="manage_page.php?action=staff" method="post">
		
		<label for="username">' . _gettext('Username') . ':</label>
		<input type="text" name="staffusername"><br>
	
		<label for="password">' . _gettext('Password') . ':</label>
		<input type="text" name="staffpassword"><br>
		
		<label for="type">' . _gettext('Type') . ':</label>
		<select name="type">
		<option value="1">' . _gettext('Administrator') . '</option>
		<option value="2" selected>' . _gettext('Moderator') . '</option>
		<option value="0">' . _gettext('Janitor') . '</option>
		<option value="3">VIP</option>
		</select><br>
		<label for="access">'._gettext('Access level [<a href="manage_page.php?action=staff&accesshelp=1">?</a>]').':</label> <input type="text" maxlength="4" name="access" value="'.$line['access'].'"/><br/>
		<input type="submit" value="' .  _gettext('Add staff member') . '">
		
		</form>
		<hr><br/>';
		$tpl_page .= '<br><br/>';
		
		$tpl_page .= '<table border="1" width="100%"><tr><th>' . _gettext('Username') . '</th><th>' . _gettext('Added on') . '</th><th>' . _gettext('Last active') . '</th><th>' . _gettext('Moderating boards') . '</th><th>&nbsp;</th></tr>' . "\n";
		$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><b>' . _gettext('Administrators') . '</b></font></td></tr>' . "\n";
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `type` = '1' ORDER BY `username` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['username'] . '</td><td>' . date("y/m/d(D)H:i", $line['addedon']) . '</td><td>';
				if ($line['lastactive'] == 0) {
					$tpl_page .= _gettext('Never');
				} elseif ((time() - $line['lastactive']) > 300) {
					$tpl_page .= timeDiff($line['lastactive'], false);
				} else {
					$tpl_page .= _gettext('Online now');
				}
				$tpl_page .= '</td><td>&nbsp;</td><td>[<a href="?action=staff&edit=' . $line['id'] . '">' . _gettext('Edit') . '</a>]';
				$tpl_page .= '[<a href="?action=staff&del=' . $line['id'] . '">x</a>]</td></tr>' . "\n";
			}
		} else {
			$tpl_page .= '<tr><td colspan="4">' . _gettext('None') . '</td></tr>' . "\n";
		}
		$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><b>' . _gettext('Head Moderator') . '</b></font></td></tr>' . "\n";
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `type` = '2' AND `suspended` != 1 AND `access` >= 10 ORDER BY `username` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['username'];
				$tpl_page .= '</td><td>' . date("y/m/d(D)H:i", $line['addedon']) . '</td><td>';
				if ($line['lastactive'] == 0) {
					$tpl_page .= _gettext('Never');
				} elseif ((time() - $line['lastactive']) > 300) {
					$tpl_page .= timeDiff($line['lastactive'], false);
				} else {
					$tpl_page .= _gettext('Online now');
				}
				$tpl_page .= '</td><td>';
				if ($line['boards'] != '') {
					if ($line['boards'] == 'allboards') {
						$tpl_page .= 'All boards';
					} else {
						$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>';
					}
				} else {
					$tpl_page .= _gettext('No boards');
				}
				$tpl_page .= '</td><td>[<a href="?action=staff&edit=' . $line['id'] . '">' . _gettext('Edit') . '</a>]&nbsp;[<a href="?action=staff&suspend=' . $line['id'] . '">' . _gettext('Suspend') . '</a>] [<a href="?action=staff&del=' . $line['id'] . '">x</a>]</td></tr>' . "\n";
			}
		} else {
			$tpl_page .= '<tr><td colspan="4">' . _gettext('None') . '</td></tr>' . "\n";
		}
		$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><b>' . _gettext('Super Moderators') . '</b></font></td></tr>' . "\n";
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `type` = '2' AND `suspended` != 1 AND `access` = 9 ORDER BY `username` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['username'];
				$tpl_page .= '</td><td>' . date("y/m/d(D)H:i", $line['addedon']) . '</td><td>';
				if ($line['lastactive'] == 0) {
					$tpl_page .= _gettext('Never');
				} elseif ((time() - $line['lastactive']) > 300) {
					$tpl_page .= timeDiff($line['lastactive'], false);
				} else {
					$tpl_page .= _gettext('Online now');
				}
				$tpl_page .= '</td><td>';
				if ($line['boards'] != '') {
					if ($line['boards'] == 'allboards') {
						$tpl_page .= 'All boards';
					} else {
						$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>';
					}
				} else {
					$tpl_page .= _gettext('No boards');
				}
				$tpl_page .= '</td><td>[<a href="?action=staff&edit=' . $line['id'] . '">' . _gettext('Edit') . '</a>]&nbsp;[<a href="?action=staff&suspend=' . $line['id'] . '">' . _gettext('Suspend') . '</a>] [<a href="?action=staff&del=' . $line['id'] . '">x</a>]</td></tr>' . "\n";
			}
		} else {
			$tpl_page .= '<tr><td colspan="4">' . _gettext('None') . '</td></tr>' . "\n";
		}
		$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><b>' . _gettext('Moderators') . '</b></font></td></tr>' . "\n";
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `type` = '2' AND `suspended` != 1 AND `access` < 9 ORDER BY `username` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['username'];
				$access = $tc_db->GetAll("SELECT HIGH_PRIORITY `access` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '". $line['username'] ."' AND `access` >= 9");
				foreach($access as $ac) {
					$tpl_page .= ' [<a style="color: red; text-decoration: none" href="manage_page.php?action=staff&supermod=1">*</a>]';
				}
				$tpl_page .= '</td><td>' . date("y/m/d(D)H:i", $line['addedon']) . '</td><td>';
				if ($line['lastactive'] == 0) {
					$tpl_page .= _gettext('Never');
				} elseif ((time() - $line['lastactive']) > 300) {
					$tpl_page .= timeDiff($line['lastactive'], false);
				} else {
					$tpl_page .= _gettext('Online now');
				}
				$tpl_page .= '</td><td>';
				if ($line['boards'] != '') {
					if ($line['boards'] == 'allboards') {
						$tpl_page .= 'All boards';
					} else {
						$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>';
					}
				} else {
					$tpl_page .= _gettext('No boards');
				}
				$tpl_page .= '</td><td>[<a href="?action=staff&edit=' . $line['id'] . '">' . _gettext('Edit') . '</a>]&nbsp;[<a href="?action=staff&suspend=' . $line['id'] . '">' . _gettext('Suspend') . '</a>] [<a href="?action=staff&del=' . $line['id'] . '">x</a>]</td></tr>' . "\n";
			}
		} else {
			$tpl_page .= '<tr><td colspan="4">' . _gettext('None') . '</td></tr>' . "\n";
		}
		$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><b>' . _gettext('Janitors') . '</b></font></td></tr>' . "\n";
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `type` = '0' AND `suspended` != 1 ORDER BY `username` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['username'] . '</td><td>' . date("y/m/d(D)H:i", $line['addedon']) . '</td><td>';
				if ($line['lastactive'] == 0) {
					$tpl_page .= _gettext('Never');
				} elseif ((time() - $line['lastactive']) > 300) {
					$tpl_page .= timeDiff($line['lastactive'], false);
				} else {
					$tpl_page .= _gettext('Online now');
				}
				$tpl_page .= '</td><td>';
				if ($line['boards'] != '') {
					if ($line['boards'] == 'allboards') {
						$tpl_page .= 'All boards';
					} else {
						$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>';
					}
				} else {
					$tpl_page .= _gettext('No boards');
				}
				$tpl_page .= '</td><td>[<a href="?action=staff&edit=' . $line['id'] . '">' . _gettext('Edit') . '</a>]&nbsp;[<a href="?action=staff&suspend=' . $line['id'] . '">' . _gettext('Suspend') . '</a>] [<a href="?action=staff&del=' . $line['id'] . '">x</a>]</td></tr>' . "\n";
			}
		} else {
			$tpl_page .= '<tr><td colspan="5">' . _gettext('None') . '</td></tr>' . "\n";
		}
				$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><b>' . _gettext('Suspended') . '</b></font></td></tr>' . "\n";
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `suspended` = 1 ORDER BY `username` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['username'] . '</td><td>' . date("y/m/d(D)H:i", $line['addedon']) . '</td><td>';
				if ($line['lastactive'] == 0) {
					$tpl_page .= _gettext('Never');
				} elseif ((time() - $line['lastactive']) > 300) {
					$tpl_page .= timeDiff($line['lastactive'], false);
				} else {
					$tpl_page .= _gettext('Online now');
				}
				$tpl_page .= '</td><td>';
				if ($line['boards'] != '') {
					if ($line['boards'] == 'allboards') {
						$tpl_page .= 'All boards';
					} else {
						$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>';
					}
				} else {
					$tpl_page .= _gettext('No boards');
				}
				$tpl_page .= '</td><td>[<a href="?action=staff&edit=' . $line['id'] . '">' . _gettext('Edit') . '</a>]&nbsp;[<a href="?action=staff&unsuspend=' . $line['id'] . '">' . _gettext('Unsuspend') . '</a>] [<a href="?action=staff&del=' . $line['id'] . '">x</a>]</td></tr>' . "\n";
			}
		} else {
			$tpl_page .= '<tr><td colspan="4">' . _gettext('None') . '</td></tr>' . "\n";
		}
		$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><b>VIP</b></font></td></tr>' . "\n";
		$tpl_page .= '<tr><th>' . _gettext('Posting password') . '</th><th colspan="3">' . _gettext('Added on') . '</th><th>&nbsp;</th>' . "\n";;
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `type` = '3' ORDER BY `username` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['username'] . '</td><td colspan="2">' . date("y/m/d(D)H:i", $line['addedon']) . '</td><td>[<a href="?action=staff&edit=' . $line['id'] . '">' . _gettext('Edit') . '</a>]&nbsp;[<a href="?action=staff&del=' . $line['id'] . '">x</a>]</td></tr>' . "\n";
			}
		} else {
			$tpl_page .= '<tr><td colspan="5">' . _gettext('None') . '</td></tr>' . "\n";
		}
		$tpl_page .= '</table>';
	}
	// Credits to Eman for this code 
	function viewdeletedthread() {
		global $tc_db, $smarty, $tpl_page;
		if($this->CheckAccess() < 10) {
			exitWithErrorPage('You do not have permission to access this page');
		}	
	
		$tpl_page .= '<h2>' . ucwords(_gettext('View deleted thread')) . '</h2><br>This will only work for imageboards.<br/><br/>';
		$board = mysql_real_escape_string($_GET['board']);
		$thread = mysql_real_escape_string($_GET['thread']);		
		if (!$thread) {
			$thread = "0";
		}	
		if (!$board) {				
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` WHERE 1 ORDER BY `name` ASC");
			$tpl_page .= "<form method=\"get\" action=\"\"><input type=\"hidden\" name=\"action\" value=\"viewdeletedthread\">Select Board: <select name=\"board\">";		
			foreach ($results as $line) {
				$name = $line['name'];
				$tpl_page .= "<option value=\"$name\">/$name/</option>";		
			}			
		$tpl_page .= "</select>&nbsp;<br/><input type=submit value=\"Go\">";		
		} 
		else {		
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name`, `postedat`,`id`,`parentid`,`filename`,`filetype`,`thumb_w`,`thumb_h`,`message`,`subject`,`filename_original`,`filesize_formatted`,`posterauthority` FROM `" . KU_DBPREFIX . "posts_$board` WHERE `id` = '$thread' OR `parentid` = '$thread' AND `IS_DELETED` = 1 ORDER BY `id` ASC LIMIT 200");		
			if(count($results) <= 0) {
				$tpl_page .= 'No results found.';
			}
			$tpl_page .= "
			<form method=\"get\" action=\"\">
			<input type=\"hidden\" name=\"action\" value=\"viewdeletedthread\">
			<input type=\"hidden\" name=\"board\" value=\"$board\">";
			if ($thread == "0" ) {
				$tpl_page .= "<b>Latest deleted threads on /$board/</b><br/>";
			} 
			else {
				$tpl_page .= "<b>Thread $thread on /$board/</b><br/>";
			}
			$tpl_page .= '<br/>';
			foreach ($results as $line) {
				$id = $line['id'];
				$filename = $line['filename'];
				$filename_original = $line['filename_original'];
				$filetype = $line['filetype'];
				$filesize_formatted = $line['filesize_formatted'];
				$image_w = $line['image_w'];
				$image_h = $line['image_h'];
				$message = htmlspecialchars($line['message']);
				$name = htmlspecialchars($line['name']);
				$tripcode = $line['tripcode'];
				$postedat = formatDate($line['postedat']);
				$subject = htmlspecialchars($line['subject']);
				$posterauthority = $line['posterauthority'];	
				$tpl_page .= '<div style="background: #D6DAF0; padding: 5px 10px 5px 10px;border: 1px solid #6f819b; width: 520px;height: auto;font-family:Arial">';
				if ($thread == "0") {
					$view = "<a href=\"?action=viewdeletedthread&board=$board&thread=$id\">[View]</a>";
				} else {
					$view = "";
				}
				if ($name == "") {
					$name = "Anonymous";
				} else {
					$name = "<font color=blue>$name</font>";
				}			
				if ($tripcode != "") {
					$tripcode = "<font color=green>!$tripcode</font>";
				}			
				if ($subject != "") {
					$subject = "<font color=red>$subject</font> - ";
				}			
				if ($posterauthority == "1") {
					$posterauthority = "<font color=purple><b>## Admin ##</b></font>";
				} elseif ($posterauthority == "2") {
					$posterauthority = "<font color=red><b>## Mod ##</b></font>";
				} elseif ($posterauthority == "4") {
					$posterauthority = "<font color=red><b>## Super Mod ## </b></font>";
				}
				else {
					$posterauthority = "";
				}						
				$tpl_page .= "$deleted" . '<span style="color:#117743;font-weight:bold">'. " $subject $name $tripcode $posterauthority | $postedat No. $id $view<br>";
				if ($filename != "") {
					$tpl_page .= "
							File: <img src=\"". KU_WEBPATH ."/$board/src/$filename.$filetype\"><a href=\"". KU_WEBPATH ."/$board/src/$filename.$filetype\" target=_new>$filename.$filetype</a> -( $filesize_formatted, {$image_w}x{$image_h}, $filename_original.$filetype )
					";
				}
				$tpl_page .= "<tr>";
				
				if ($filename != "") {
					$tpl_page .= "
						</span><br/><center><a href=\"". KU_WEBPATH ."/$board/src/$filename.$filetype\" target=_new><img style=\"float:left;margin-right: 5px\" src=\"". KU_WEBPATH ."/$board/thumb/{$filename}s.$filetype\" border=0></a></center>
					";
				}
				$message = stripslashes($message);
				$tpl_page .= "$message" . '<br style="clear: all"/></div><br/>';
			}
		}
	}
	function download() {
		global $tc_db, $smarty, $tpl_page;
		if($this->CheckAccess() < 9) {
			exitWithErrorPage('You do not have permission to access this page');
		}	
		$tpl_page .= '<h2>' . _gettext('Download') . '</h2><br>';
				$ressyz = $tc_db->GetAll("SELECT `ip`,`search`,`time` FROM `" . KU_DBPREFIX . "search_cache` ORDER BY `time` desc LIMIT 50");
		$tpl_page .= 'Latest searchs<br/><table width="400" cellpadding="3" style="border: 1px solid"><tr><th>IP</th><th>Search</th><th>Time</th></tr>';
		if(count($ressyz) > 0) {
			foreach($ressyz as $rezzy) {
				$tpl_page .= '<tr><td>' . $rezzy['ip'] . '</td><td>' . htmlentities($rezzy['search']) . '</td><td> ' . date('F j, Y, g:i a',$rezzy['time']) . "</td></tr>";
			}
		}
		$tpl_page .= '</table>';
		$ressyz2 = $tc_db->GetAll("SELECT `ip`,`flag_id`,`time` FROM `" . KU_DBPREFIX . "flag_cache` ORDER BY `time` desc LIMIT 50");
		$tpl_page .= 'Latest flagged<br/><table width="400" cellpadding="3" style="border: 1px solid"><tr><th>IP</th><th>Flag ID</th><th>Time</th><th>Count</th></tr>';
		if(count($ressyz2) > 0) {
			foreach($ressyz2 as $rezzy2) {
			$county = $tc_db->GetOne("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "flag_cache` WHERE `flag_id` = '".mysql_real_escape_string($rezzy2['flag_id'])."'");
				$tpl_page .= '<tr><td>' . $rezzy2['ip'] . '</td><td><a href="'. KU_WEBPATH .'/download.php?comment=' . htmlentities($rezzy2['flag_id']) . '">' . htmlentities($rezzy2['flag_id']) . '</a></td><td> ' . date('F j, Y, g:i a',$rezzy2['time']) . "</td><td>$county</td></tr>";
			}
		}
		if(isset($_POST['deletesub'])) {
			if(is_numeric($_POST['deletethis'])) {
				$tc_db->Execute("UPDATE `".KU_DBPREFIX."download` SET `deleted` = '1' WHERE `id` = '".mysql_real_escape_string($_POST['deletethis'])."' LIMIT 1") or die(mysql_error());
				$tpl_page .= 'Deleted '. htmlentities($_POST['deletethis']);
				management_addlogentry(_gettext('Deleted download ID ' . htmlentities($_POST['deletethis'])), 1);		
			}
		}
		if(isset($_POST['switchsub'])) {
			if(is_numeric($_POST['switchthis'])) {
				$tc_db->Execute("UPDATE `".KU_DBPREFIX."download` SET `switch` = '".mysql_real_escape_string($_POST['switchwith'])."' WHERE `id` = '".mysql_real_escape_string($_POST['switchthis'])."' LIMIT 1") or die(mysql_error());
				$tpl_page .= 'Switched '. htmlentities($_POST['switchthis']);
				management_addlogentry(_gettext('Switched download ID ' . htmlentities($_POST['switchthis'])), 1);		
			}
		}
		$tpl_page .= '</table><br/>';
				$tpl_page .= 'Delete<br/<br/><form enctype="multipart/form-data" action="' . KU_CGIPATH . '/manage_page.php?action=download" method="post"><label for="deletethis">Download ID:</label><input type="textbox" name="deletethis"/><br/><input type="submit" value="Delete" name="deletesub"/></form><br/>
				Switch-a-roo<br/<br/><form enctype="multipart/form-data" action="' . KU_CGIPATH . '/manage_page.php?action=download" method="post"><label for="switchthis">Switch ID:</label><input type="textbox" name="switchthis"/><br/><label for="switchwith">Switch with:</label><input type="textbox" name="switchwith"/><br/><input type="submit" value="Switch" name="switchsub"/></form><br/>
				Submit a download<br/><br/><form enctype="multipart/form-data" action="' . KU_CGIPATH . '/manage_page.php?action=download" method="post"><label for="download">URL: (with http://)</label> <input type="text" name="download"/><br/><label for="preview">Preview: </label><input type="file" name="preview"/><br/><label for="previewnail">Preview thumbnail: </label><input type="file" name="previewnail"/><br/><label for="desc">Description:</label> <input type="text" name="desc"/><br/><label for="source">Source:</label><input type="text" name="source"/><br/><input type="submit" value="Go" name="subby"/></form><br/><br/>';
				if(isset($_POST['subby'])) {
			if(empty($_POST['download']) || empty($_POST['desc']) || empty($_POST['source'])) {
				exitWithErrorPage("Something is missing");
			}			
			$randy = time();
			$nogger = explode(".",$_FILES['preview']['name']);
			$noggery = explode(".",$_FILES['previewnail']['name']);
			$new_w = 200;
			$new_h = 200;
			$name = $_FILES['previewnail']['name'];
			$filename = $thumb_path . $randy .'.'. $noggery[1];
			$upload_path = '/home/thepinkertons/99chan.org/download_src/' . $randy .'.'.$noggery[1];
			$thumb_path = '/home/thepinkertons/99chan.org/download_thumb/' . $randy .'.'.$noggery[1];
			if (!createThumbnail($_FILES['previewnail']['tmp_name'], $thumb_path, KU_THUMBWIDTH, KU_THUMBHEIGHT)) {
				exitWithErrorPage(_gettext('Could not create thumbnail.'));
			}
			//system('convert ' . $rawfile . ' -auto-orient -thumbnail ' . $size . 'x' . $size . ' -unsharp 0x.5 ' . $rawfile . '-' . $size);    
			$links = KU_WEBPATH . '/download_src/'. $randy .'.'.$noggery[1] . '|' . KU_WEBPATH . '/download_thumb/'. $randy .'.'.$noggery[1];
			$resultsy = $tc_db->GetAll("SELECT * from `".KU_DBPREFIX."download` WHERE `download` = '".mysql_real_escape_string($_POST['download'])."'");
			if(count($resultsy) == 0) {
				if(move_uploaded_file($_FILES['preview']['tmp_name'], $upload_path)) {
					$tc_db->Execute("INSERT INTO `".KU_DBPREFIX."download` (`download`,`source`,`link`,`desc`,`date`,`thumb`) VALUES ('".mysql_real_escape_string($_POST['download'])."','".mysql_real_escape_string($_POST['source'])."','".mysql_real_escape_string("Submitted")."', '".mysql_real_escape_string($_POST['desc'])."','".time()."','".mysql_real_escape_string($links)."')") or die(mysql_error());	
				}
			}
			else {
				exitWithErrorpage("Already uploaded!");
			}
		}
		if(isset($_POST['submit'])) {
			foreach($_POST as $del) {
				if(is_numeric($del)) {
				$tc_db->Execute("UPDATE `".KU_DBPREFIX."download` SET `deleted` = '1' WHERE `id` = '".mysql_real_escape_string($del)."' LIMIT 1") or die(mysql_error());
				//$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "reports` SET `cleared` = '1' WHERE `id` = '" . mysql_real_escape_string($_GET['clear']) . "' LIMIT 1");
				management_addlogentry(_gettext('Deleted download ID ' . htmlentities($del)), 1);
				}
			}
			$tpl_page .= 'Deleted';
		}
		if(isset($_POST['image'])) {
	for($i = 0; $i < 15; $i++) {
		if(eregi(".jpg", $_POST['image'])) {
		
			$im = imagecreatefromjpeg($_POST['image']);
		}
		if(eregi(".png", $_POST['image'])) {
	
			$im = imagecreatefrompng($_POST['image']);
		}
		if(eregi(".gif", $_POST['image'])) {
	
			$im = imagecreatefromgif($_POST['image']);
		}
		if($im) {
			imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
			imagefilter($im, IMG_FILTER_SELECTIVE_BLUR);
			imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
			imagefilter($im, IMG_FILTER_SELECTIVE_BLUR);
			imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
			imagefilter($im, IMG_FILTER_SELECTIVE_BLUR);
			imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
			imagefilter($im, IMG_FILTER_SELECTIVE_BLUR);
			imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
			imagefilter($im, IMG_FILTER_SELECTIVE_BLUR);
			imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
			imagefilter($im, IMG_FILTER_SELECTIVE_BLUR);
			imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
			imagefilter($im, IMG_FILTER_SELECTIVE_BLUR);
			imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
			imagefilter($im, IMG_FILTER_SELECTIVE_BLUR);
			if(eregi(".gif", $_POST['image'])) {
				imagegif($im, $_POST['image']);
			}
			if(eregi(".png", $_POST['image'])) {
				imagepng($im, $_POST['image']);
			}
			if(eregi(".jpg", $_POST['image'])) {
				imagejpg($im, $_POST['image']);
			}
		}
		$i++;
	}
	$black = imagecolorallocate($im, 255, 0, 0);
	imagettftext($im, 10, 0, 10, 20, $black, 'arial.ttf', "This image has been censored");
	if(eregi(".gif", $_POST['image'])) {
		imagegif($im, $_POST['image']);
	}
	if(eregi(".png", $_POST['image'])) {
		imagepng($im, $_POST['image']);
	}
	if(eregi(".jpg", $_POST['image'])) {
		imagejpg($im, $_POST['image']);
	}
		$tpl_page .= '<br/>Image censored<br/>';
	imagedestroy($im);
}
		if(isset($_GET['del'])) {
			$tc_db->Execute("UPDATE `".KU_DBPREFIX."download` SET `deleted` = '1' WHERE `id` = '".mysql_real_escape_string($_GET['del'])."' LIMIT 1") or die(mysql_error());
			//$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "reports` SET `cleared` = '1' WHERE `id` = '" . mysql_real_escape_string($_GET['clear']) . "' LIMIT 1");
			$tpl_page .= 'Deleted';
			//management_addlogentry(_gettext('Deleted download ID ' . htmlentities($_GET['clear'])), 0);
		}
		if(!empty($_GET['source'])) {
			$results = $tc_db->GetAll("SELECT DISTINCT * from `".KU_DBPREFIX."download` WHERE `source` = '".mysql_real_escape_string($_GET['source'])."' AND `deleted` = 0 ORDER BY `id` DESC");
			if(count($results) == 0) {
				exitWithErrorPage("Invalid source");
			}
		}
		else {
			$results = $tc_db->GetAll("SELECT * from `".KU_DBPREFIX."download` WHERE `deleted` = 0 ORDER BY `id` DESC ");
		}
		if(count($results) > 0) {
			$page = mysql_real_escape_string(htmlentities($_GET['page']));
			if(strlen($page) > 9) {
				exitWithErrorPage("Page too big");
			}
			if(!isset($page)) {
				$page = 0;
			}
				//$results2 = $tc_db->GetAll("SELECT DISTINCT source FROM `".KU_DBPREFIX."download`");
				//onclick="document.location.href = \''.KU_WEBPATH.'/download.php?source='.urlencode(htmlentities($result2['source'])).'\';"
				$tpl_page .= '<form action="' . KU_CGIPATH . '/manage_page.php?action=download" method="post"><div style="width: 780px; margin: 0 auto;"><table width="100%" cellpadding="3" align="center" style="border: 1px solid">' . '<tr><th align="left" style="background: #5D92B1;color: white">ID</th><th align="left" style="background: #5D92B1;color: white">Thumbnail</th><th align="left" style="background: #5D92B1;color: white">Download</th><th align="left" style="background: #5D92B1;color: white">Description</th><th align="left" style="background: #5D92B1;color: white">Source</th><th align="left" style="background: #5D92B1;color: white">Link</th><th align="left"  style="background: #5D92B1;color: white">Date added</th><th style="background: #5D92B1;color: white">Delete</th></tr>' . "\n";
				$trbreak = 0;
				$i = 0;
				$id = 0;
			foreach($results as $result) {
			if($result['download'] != '') {
				if($id >= ($page * 60)) {
					if($i >= 60) {
						break;
					}
					$trbreak++;
					if ($trbreak == 5) {
						$tpl_page .= '</tr>' . "\n" . '<tr>' . "\n";
						$trbreak = 1;
					}
					$tpl_page .= '<tr><td>'.$result['id'].'</td>';
					if($result['thumb'] == '') {
						$tpl_page .= '<td>None</td>';
					}
					else {
						$urlz = explode("|", $result['thumb']);
						/*
						$cunt = explode("src/", $result['thumb']);
						$filenumb = substr($cunt[1],0, -4); 
						$path = $cunt[0];							
						$end = explode(".", $result['thumb']);
						$type = $end[3];		
						if($type == '') {
							$type = $end[2];
						}
						*/
						$tpl_page .= '<td><a href="'.$urlz[0].'"><img src="'.$urlz[1].'" border="0"/></a></td>';
					}
					$durl = str_replace("www.","",$result['download']);
					$downloadurl = explode(".",$durl);
					$durl = str_replace("http://","",$downloadurl[0]);
					$tpl_page .= '<td ><small>[<a href="'.htmlentities($result['download']).'">'.ucfirst(htmlentities($durl)). '' . 
'</a>]</small></td><td>'.htmlentities($result['desc']).'</td><td><small><a 
href="'.KU_WEBPATH.'/manage_page.php?action=download&source='.htmlentities($result['source']);
					if(!empty($_GET['search'])) {
						$tpl_page .= '&search=' .htmlentities($_GET['search']);
					}
					$link = str_replace("www.","",htmlentities($result['link']));
					$downloadlink = explode(".",$link);
					$dlink = str_replace("http://","",$downloadlink[0]);
					$tpl_page .= '">'.htmlentities($result['source']).'</a></small></td><td><small>[<a href="'.htmlentities($result['link']).'">'.ucfirst(htmlentities($dlink)).'</a>]</small><td>'.date('F j, Y', $result['date']).'</td><td><input type="radio" id="delete_dl" name="delete_'.$result['id'].'" value="'.$result['id'].'"/></td></tr>';
					$i++;
				}
				$id++;
			}
		}
				if(isset($_GET['search'])) {
					if(empty($_GET['source'])) {
						$page_results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "download` WHERE `download` != '' AND `desc` LIKE '%".mysql_real_escape_string($_GET['search'])."%' AND `deleted` = 0 ");
					}
					else {
						$page_results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "download` WHERE `download` != '' AND `source` = '".mysql_real_escape_string($_GET['source'])."' AND `desc` LIKE '%".mysql_real_escape_string($_GET['search'])."%' AND `deleted` = 0");
					}
				}
				else {
					if(empty($_GET['source'])) {
						$page_results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "download` WHERE `download` != '' AND `deleted` = 0");
					}
					else {
						$page_results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "download` WHERE `download` != '' AND `source` = '".mysql_real_escape_string($_GET['source'])."' AND `deleted` = 0");
					}
				}
				$pages = ceil(count($page_results) / 60 - 1);
				$tpl_page .= '</table>Page: ';
				for($i = 0; $i <= $pages; $i++) {
					if(isset($_GET['search'])) {
						if(empty($_GET['source'])) {
							$tpl_page .= '[<a href="?action=download&page='.$i.'&search='.htmlentities($_GET['search']).'">'.$i.'</a>] ';
						}
						else {
							$tpl_page .= '[<a href="?action=download&page='.$i.'&search='.htmlentities($_GET['search']).'&source='.htmlentities($_GET['source']).'">'.$i.'</a>] ';
						}
					}
					else {
						if(empty($_GET['source'])) {
							$tpl_page .= '[<a href="?action=download&page='.$i.'">'.$i.'</a>] ';
						}
						else {
							$tpl_page .= '[<a href="?action=download&page='.$i.'&source='.htmlentities($_GET['source']).'">'.$i.'</a>] ';
						}
					}
				}
				$tpl_page .= '<br/><br/><input type="submit" name="submit" value="Delete"/></form></div><br/>';
		}
	}
	function gay() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		$tpl_page .= '<h2>' . _gettext('Download') . '</h2><br>';
				$ressyz = $tc_db->GetAll("SELECT `ip`,`search`,`time` FROM `" . KU_DBPREFIX . "search_cache_gay` ORDER BY `time` desc LIMIT 50");
		$tpl_page .= 'Latest searchs<br/><table width="400" cellpadding="3" style="border: 1px solid"><tr><th>IP</th><th>Search</th><th>Time</th></tr>';
		if(count($ressyz) > 0) {
			foreach($ressyz as $rezzy) {
				$tpl_page .= '<tr><td>' . $rezzy['ip'] . '</td><td>' . htmlentities($rezzy['search']) . '</td><td> ' . date('F j, Y, g:i a',$rezzy['time']) . "</td></tr>";
			}
		}
		$tpl_page .= '</table><br/>';
				$tpl_page .= 'Submit a download<br/><br/><form enctype="multipart/form-data" action="' . KU_CGIPATH . '/manage_page.php?action=gay" method="post"><label for="download">URL: (with http://)</label> <input type="text" name="download"/><br/><label for="preview">Preview: </label><input type="file" name="preview"/><br/><label for="previewnail">Preview thumbnail: </label><input type="file" name="previewnail"/><br/><label for="desc">Description:</label> <input type="text" name="desc"/><br/><label for="source">Source:</label><input type="text" name="source"/><br/><input type="submit" value="Go" name="subby"/></form><br/><br/>';
				if(isset($_POST['subby'])) {
			if(empty($_POST['download']) || empty($_POST['desc']) || empty($_POST['source'])) {
				exitWithErrorPage("Something is missing");
			}			
			$randy = time();
			$nogger = explode(".",$_FILES['preview']['name']);
			$noggery = explode(".",$_FILES['previewnail']['name']);
			$new_w = 200;
			$new_h = 200;
			$name = $_FILES['previewnail']['name'];
			$filename = $thumb_path . $randy .'.'. $noggery[1];
			$upload_path = '/home/thepinkertons/99chan.org/gay_src/' . $randy .'.'.$noggery[1];
			$thumb_path = '/home/thepinkertons/99chan.org/gay_thumb/' . $randy .'.'.$noggery[1];
			if (!createThumbnail($_FILES['previewnail']['tmp_name'], $thumb_path, KU_THUMBWIDTH, KU_THUMBHEIGHT)) {
				exitWithErrorPage(_gettext('Could not create thumbnail.'));
			}
			//system('convert ' . $rawfile . ' -auto-orient -thumbnail ' . $size . 'x' . $size . ' -unsharp 0x.5 ' . $rawfile . '-' . $size);    
			$links = KU_WEBPATH . '/gay_src/'. $randy .'.'.$noggery[1] . '|' . KU_WEBPATH . '/gay_thumb/'. $randy .'.'.$noggery[1];
			$resultsy = $tc_db->GetAll("SELECT * from `".KU_DBPREFIX."download_gay` WHERE `download` = '".mysql_real_escape_string($_POST['download'])."'");
			if(count($resultsy) == 0) {
				if(move_uploaded_file($_FILES['preview']['tmp_name'], $upload_path)) {
					$tc_db->Execute("INSERT INTO `".KU_DBPREFIX."download_gay` (`download`,`source`,`link`,`desc`,`date`,`thumb`) VALUES ('".mysql_real_escape_string($_POST['download'])."','".mysql_real_escape_string($_POST['source'])."','".mysql_real_escape_string("Submitted")."', '".mysql_real_escape_string($_POST['desc'])."','".time()."','".mysql_real_escape_string($links)."')") or die(mysql_error());	
				}
			}
			else {
				exitWithErrorpage("Already uploaded!");
			}
		}
		if(isset($_POST['submit'])) {
			foreach($_POST as $del) {
				if(is_numeric($del)) {
				$tc_db->Execute("UPDATE `".KU_DBPREFIX."download_gay` SET `deleted` = '1' WHERE `id` = '".mysql_real_escape_string($del)."' LIMIT 1") or die(mysql_error());
				//$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "reports` SET `cleared` = '1' WHERE `id` = '" . mysql_real_escape_string($_GET['clear']) . "' LIMIT 1");
				management_addlogentry(_gettext('Deleted download ID ' . htmlentities($del)), 1);
				}
			}
			$tpl_page .= 'Deleted';
		}
		if(isset($_POST['image'])) {
	for($i = 0; $i < 15; $i++) {
		if(eregi(".jpg", $_POST['image'])) {
		
			$im = imagecreatefromjpeg($_POST['image']);
		}
		if(eregi(".png", $_POST['image'])) {
	
			$im = imagecreatefrompng($_POST['image']);
		}
		if(eregi(".gif", $_POST['image'])) {
	
			$im = imagecreatefromgif($_POST['image']);
		}
		if($im) {
			imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
			imagefilter($im, IMG_FILTER_SELECTIVE_BLUR);
			imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
			imagefilter($im, IMG_FILTER_SELECTIVE_BLUR);
			imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
			imagefilter($im, IMG_FILTER_SELECTIVE_BLUR);
			imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
			imagefilter($im, IMG_FILTER_SELECTIVE_BLUR);
			imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
			imagefilter($im, IMG_FILTER_SELECTIVE_BLUR);
			imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
			imagefilter($im, IMG_FILTER_SELECTIVE_BLUR);
			imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
			imagefilter($im, IMG_FILTER_SELECTIVE_BLUR);
			imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
			imagefilter($im, IMG_FILTER_SELECTIVE_BLUR);
			if(eregi(".gif", $_POST['image'])) {
				imagegif($im, $_POST['image']);
			}
			if(eregi(".png", $_POST['image'])) {
				imagepng($im, $_POST['image']);
			}
			if(eregi(".jpg", $_POST['image'])) {
				imagejpg($im, $_POST['image']);
			}
		}
		$i++;
	}
	$black = imagecolorallocate($im, 255, 0, 0);
	imagettftext($im, 10, 0, 10, 20, $black, 'arial.ttf', "This image has been censored");
	if(eregi(".gif", $_POST['image'])) {
		imagegif($im, $_POST['image']);
	}
	if(eregi(".png", $_POST['image'])) {
		imagepng($im, $_POST['image']);
	}
	if(eregi(".jpg", $_POST['image'])) {
		imagejpg($im, $_POST['image']);
	}
		$tpl_page .= '<br/>Image censored<br/>';
	imagedestroy($im);
}
		if(isset($_GET['del'])) {
			$tc_db->Execute("UPDATE `".KU_DBPREFIX."download_gay` SET `deleted` = '1' WHERE `id` = '".mysql_real_escape_string($_GET['del'])."' LIMIT 1") or die(mysql_error());
			//$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "reports` SET `cleared` = '1' WHERE `id` = '" . mysql_real_escape_string($_GET['clear']) . "' LIMIT 1");
			$tpl_page .= 'Deleted';
			//management_addlogentry(_gettext('Deleted download ID ' . htmlentities($_GET['clear'])), 0);
		}
		if(!empty($_GET['source'])) {
			$results = $tc_db->GetAll("SELECT DISTINCT * from `".KU_DBPREFIX."download_gay` WHERE `source` = '".mysql_real_escape_string($_GET['source'])."' AND `deleted` = 0 ORDER BY `id` DESC");
			if(count($results) == 0) {
				exitWithErrorPage("Invalid source");
			}
		}
		else {
			$results = $tc_db->GetAll("SELECT * from `".KU_DBPREFIX."download_gay` WHERE `deleted` = 0 ORDER BY `id` DESC ");
		}
		if(count($results) > 0) {
			$page = mysql_real_escape_string(htmlentities($_GET['page']));
			if(strlen($page) > 9) {
				exitWithErrorPage("Page too big");
			}
			if(!isset($page)) {
				$page = 0;
			}
				//$results2 = $tc_db->GetAll("SELECT DISTINCT source FROM `".KU_DBPREFIX."download`");
				//onclick="document.location.href = \''.KU_WEBPATH.'/download.php?source='.urlencode(htmlentities($result2['source'])).'\';"
				$tpl_page .= '<form action="' . KU_CGIPATH . '/manage_page.php?action=gay" method="post"><div style="width: 780px; margin: 0 auto;"><table width="100%" cellpadding="3" align="center" style="border: 1px solid">' . '<tr><th align="left" style="background: #5D92B1;color: white">Thumbnail</th><th align="left" style="background: #5D92B1;color: white">Download</th><th align="left" style="background: #5D92B1;color: white">Description</th><th align="left" style="background: #5D92B1;color: white">Source</th><th align="left" style="background: #5D92B1;color: white">Link</th><th align="left"  style="background: #5D92B1;color: white">Date added</th><th style="background: #5D92B1;color: white">Delete</th></tr>' . "\n";
				$trbreak = 0;
				$i = 0;
				$id = 0;
			foreach($results as $result) {
			if($result['download'] != '') {
				if($id >= ($page * 60)) {
					if($i >= 60) {
						break;
					}
					$trbreak++;
					if ($trbreak == 5) {
						$tpl_page .= '</tr>' . "\n" . '<tr>' . "\n";
						$trbreak = 1;
					}
					$tpl_page .= '<tr>';
					if($result['thumb'] == '') {
						$tpl_page .= '<td>None</td>';
					}
					else {
						$urlz = explode("|", $result['thumb']);
						/*
						$cunt = explode("src/", $result['thumb']);
						$filenumb = substr($cunt[1],0, -4); 
						$path = $cunt[0];							
						$end = explode(".", $result['thumb']);
						$type = $end[3];		
						if($type == '') {
							$type = $end[2];
						}
						*/
						$tpl_page .= '<td><a href="'.$urlz[0].'"><img src="'.$urlz[1].'" border="0"/></a></td>';
					}
					$durl = str_replace("www.","",$result['download']);
					$downloadurl = explode(".",$durl);
					$durl = str_replace("http://","",$downloadurl[0]);
					$tpl_page .= '<td ><small>[<a href="'.htmlentities($result['download']).'">'.ucfirst(htmlentities($durl)). '' . 
'</a>]</small></td><td>'.htmlentities($result['desc']).'</td><td><small><a 
href="'.KU_WEBPATH.'/manage_page.php?action=gay&source='.htmlentities($result['source']);
					if(!empty($_GET['search'])) {
						$tpl_page .= '&search=' .htmlentities($_GET['search']);
					}
					$link = str_replace("www.","",htmlentities($result['link']));
					$downloadlink = explode(".",$link);
					$dlink = str_replace("http://","",$downloadlink[0]);
					$tpl_page .= '">'.htmlentities($result['source']).'</a></small></td><td><small>[<a href="'.htmlentities($result['link']).'">'.ucfirst(htmlentities($dlink)).'</a>]</small><td>'.date('F j, Y', $result['date']).'</td><td><input type="radio" id="delete_dl" name="delete_'.$result['id'].'" value="'.$result['id'].'"/></td></tr>';
					$i++;
				}
				$id++;
			}
		}
				if(isset($_GET['search'])) {
					if(empty($_GET['source'])) {
						$page_results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "download_gay` WHERE `download` != '' AND `desc` LIKE '%".mysql_real_escape_string($_GET['search'])."%' AND `deleted` = 0 ");
					}
					else {
						$page_results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "download_gay` WHERE `download` != '' AND `source` = '".mysql_real_escape_string($_GET['source'])."' AND `desc` LIKE '%".mysql_real_escape_string($_GET['search'])."%' AND `deleted` = 0");
					}
				}
				else {
					if(empty($_GET['source'])) {
						$page_results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "download_gay` WHERE `download` != '' AND `deleted` = 0");
					}
					else {
						$page_results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "download_gay` WHERE `download` != '' AND `source` = '".mysql_real_escape_string($_GET['source'])."' AND `deleted` = 0");
					}
				}
				$pages = ceil(count($page_results) / 60 - 1);
				$tpl_page .= '</table>Page: ';
				for($i = 0; $i <= $pages; $i++) {
					if(isset($_GET['search'])) {
						if(empty($_GET['source'])) {
							$tpl_page .= '[<a href="?action=gay&page='.$i.'&search='.htmlentities($_GET['search']).'">'.$i.'</a>] ';
						}
						else {
							$tpl_page .= '[<a href="?action=gay&page='.$i.'&search='.htmlentities($_GET['search']).'&source='.htmlentities($_GET['source']).'">'.$i.'</a>] ';
						}
					}
					else {
						if(empty($_GET['source'])) {
							$tpl_page .= '[<a href="?action=gay&page='.$i.'">'.$i.'</a>] ';
						}
						else {
							$tpl_page .= '[<a href="?action=gay&page='.$i.'&source='.htmlentities($_GET['source']).'">'.$i.'</a>] ';
						}
					}
				}
				$tpl_page .= '<br/><br/><input type="submit" name="submit" value="Delete"/></form></div><br/>';
		}
	}
	function spam() {
		global $tc_db, $smarty, $tpl_page;
		if($this->CheckAccess() < 9) {
			exitWithErrorPage('You do not have permission to access this page');
		}
	
		$spamact = $_GET['spamact'];
		$tpl_page .= '<h2>Spam Filter - '.$spamact.'</h2><br>';
		if ($spamact == "") {
			$tpl_page .= '<fieldset><legend>Spamfilter Address to Add</legend><br>
			<label for="ip">URL to Add:</label><form method="get" action="">
			<input type="text" size="40" maxlength="120" name="url">
			<input type="hidden" name="action" value="spam">
			<input type="hidden" name="spamact" value="add">
			</fieldset><input type="submit" value="Add URL"></form><br>';
			
			$tpl_page .= '<table cellspacing="2" cellpadding="1" border="1"><tr><th>ID</th><th>&nbsp;</th><th>URL</th></tr>';
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "spamfilter` ORDER BY `id` ASC");
			foreach ($results as $line) {
                        $count += 1;
                        if ($count%2 == false) {
                                $linecolour = "#fff";
                        } else {
                                $linecolour = "#dce3e8";
                        }
				$tpl_page .= "<tr bgcolor=$linecolour><td>" . $line['id'] . "</td>
				<td><a href='?action=spam&spamact=delete&id=" . $line['id'] . "&url=" .htmlentities($line['url']) . "'>[x]</a></td>
				<td width=100%>" . htmlentities($line['url']) . "</td></tr>";
			}
			$tpl_page .= '</table>';
		} 
		
		elseif ($spamact == "delete") {
			$id = $_GET['id'];
			$url = $_GET['url'];
                        $url = mysql_real_escape_string($url);
                        $id = mysql_real_escape_string($id);
			$tpl_page .= "DELETE $id";
			$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "spamfilter` WHERE `id` = '$id' LIMIT 1");
			
			$wut = $url;
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "spamfilter` ORDER BY `id` ASC");
			foreach ($results as $line) {
				$spamlines .= $line['url']."\n";
			}
			$tpl_page .= "<pre>$spamlines</pre>";
			$spamfile = fopen("spam.txt", 'w') or die("can't open file");
			fwrite($spamfile, $spamlines);
			fclose($spamfile);		
			management_addlogentry(_gettext('Deleted "'. $wut .'" from Spamfilter'), 0);	
		}
		
		elseif ($spamact == "add") {
			$url = $_GET['url'];
			$url = mysql_real_escape_string($url);
			$tpl_page .= "ADD $url";
			$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "spamfilter` ( `id` , `url` ) VALUES ('', '$url')");
			
			$wut = $url;
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "spamfilter` ORDER BY `id` ASC");
			foreach ($results as $line) {
				$spamlines .= $line['url']."\n";
			}
			$tpl_page .= "<pre>$spamlines</pre>";
			$spamfile = fopen("wankers.txt", 'w') or die("can't open file");
			fwrite($spamfile, $spamlines);
			fclose($spamfile);		
			management_addlogentry(_gettext('Added "'. $wut .'" to Spamfilter'), 0);	
		}
	}
	// Move thread
	function movethread() {
		global $tc_db, $smarty, $tpl_page, $board_from, $board_to;
		$this->AdministratorsOnly();		
		$tpl_page .= '<h2>' . ucwords(_gettext('Move thread')) . "</h2><br><b>Warning: Do not move threads across image board types, i.e. an imageboard thread to a text board thread.</b><br><br>";
		if (isset($_POST['id']) && isset($_POST['board_from']) && isset($_POST['board_to'])) {
			$board_from = mysql_real_escape_string($_POST['board_from']);
			$board_to = mysql_real_escape_string($_POST['board_to']);
			if(!is_numeric($_POST['id'])) {
				exitWithErrorPage('Invalid thread ID');
			}
			$id = mysql_real_escape_string($_POST['id']);
			$temp_id = 0;
			if(empty($_POST['board_from'])  || empty($_POST['board_to'])) {
				exitWithErrorPage('Please select a board');
			}
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `filename`, `filetype` FROM " . KU_DBPREFIX . "posts_" . $board_from . " WHERE `id` = '" . $id . "' AND `parentid` = 0 AND `IS_DELETED` = 0 LIMIT 1");
			if(count($results) <= 0) {
				exitWithErrorPage('Invalid thread ID');
			}
			$tc_db->Execute("START TRANSACTION");
			foreach ($results as $line) {
				if(is_numeric($line['filename'])) {
					$image_from = KU_ROOTDIR . $board_from . '/src/' . $line['filename'] . '.' . $line['filetype'];
					$image_to = KU_ROOTDIR . $board_to . '/src/' . $line['filename'] . '.' . $line['filetype'];
					$image_from_s = KU_ROOTDIR . $board_from . '/thumb/' . $line['filename'] . 's.' . $line['filetype'];
					$image_to_s = KU_ROOTDIR . $board_to . '/thumb/' . $line['filename'] . 's.' . $line['filetype'];
					$image_from_c = KU_ROOTDIR. $board_from . '/thumb/' . $line['filename'] . 'c.' . $line['filetype'];
					$image_to_c = KU_ROOTDIR . $board_to . '/thumb/' . $line['filename'] . 'c.' . $line['filetype'];
					if (file_exists($image_to) || file_exists($image_to_s) || file_exists($image_to_c)) {
						die("[1] File already exists, try running cleanup to remove unused files");
					}
					if(!rename($image_from, $image_to) || !rename($image_from_s, $image_to_s) || !rename($image_from_c, $image_to_c)) {
						die("Error moving files");
					}
				}
			}
			$tc_db->Execute("UPDATE " . KU_DBPREFIX . "posts_" . $board_from . " SET `id` = " . $temp_id . " WHERE `id` = '" . $id . "'");
			
			$tc_db->Execute("INSERT INTO " . KU_DBPREFIX . "posts_" . $board_to . " SELECT * FROM " . KU_DBPREFIX . "posts_" . $board_from . " WHERE `id` = " . $temp_id);
			$new_id = $tc_db->Insert_Id();	
			processPost($new_id, $new_id, $id);
			$tc_db->Execute("DELETE FROM " . KU_DBPREFIX . "posts_" . $board_from . " WHERE `id` = " . $temp_id);
			
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `filename`, `filetype` FROM " . KU_DBPREFIX . "posts_" . $board_from . " WHERE `parentid` = '" . $id . "' AND `IS_DELETED` = 0 ORDER BY `id` ASC");
			if(count($results) > 0) {
				foreach ($results as $line) {
					if(is_numeric($line['filename'])) {
						$image_from = KU_ROOTDIR . $board_from . '/src/' . $line['filename'] . '.' . $line['filetype'];
						$image_to = KU_ROOTDIR . $board_to . '/src/' . $line['filename'] . '.' . $line['filetype'];
						$image_from_s = KU_ROOTDIR . $board_from . '/thumb/' . $line['filename'] . 's.' . $line['filetype'];
						$image_to_s = KU_ROOTDIR . $board_to . '/thumb/' . $line['filename'] . 's.' . $line['filetype'];
						$image_from_c = KU_ROOTDIR. $board_from . '/thumb/' . $line['filename'] . 'c.' . $line['filetype'];
						$image_to_c = KU_ROOTDIR . $board_to . '/thumb/' . $line['filename'] . 'c.' . $line['filetype'];
						if (file_exists($image_to) || file_exists($image_to_s) || file_exists($image_to_c)) {
							die("[2] File already exists, try running cleanup to remove unused files");
						}
						if(!rename($image_from, $image_to) || !rename($image_from_s, $image_to_s) || !rename($image_from_c, $image_to_c)) {
							die("Error moving files");
						}
					}
					$tc_db->Execute("UPDATE " . KU_DBPREFIX . "posts_" . $board_from . " SET `id` = " . $temp_id. " WHERE `id` = " . $line['id']);			
					$tc_db->Execute("INSERT INTO " . KU_DBPREFIX . "posts_" . $board_to . " SELECT * FROM " . KU_DBPREFIX . "posts_" . $board_from . " WHERE `id` = " . $temp_id);
					$insert_id = $tc_db->Insert_Id();			
					processPost($insert_id, $new_id, $id);		
					$tc_db->Execute("UPDATE " . KU_DBPREFIX . "posts_" . $board_to . " SET `parentid` = " . $new_id . " WHERE `id` = " . $insert_id);
					$tc_db->Execute("DELETE FROM " . KU_DBPREFIX . "posts_" . $board_from . " WHERE `id` = " . $temp_id);
				}
			}
			$tc_db->Execute("COMMIT");
			$board_class = new Board($board_to);
			$board_class->RegenerateAll();
			unset($board_class);
			$board_class = new Board($board_from);
			$board_class->RegenerateAll();
			if(!$this->delspecunusedimages($board_from)) {
				exitWithErrorPage(_gettext('Could not delete unused images'));
			}
			$tpl_page .= _gettext('Move complete.') . '<br><hr>';
		}
		
		$tpl_page .= '<form action="?action=movethread" method="post">
		
		<label for="id">' . _gettext('ID') . ':</label>
		<input type="text" name="id">
		<br>
		
		<label for="board_from">' . _gettext('From') . ':</label>' .
		$this->MakeBoardListDropdown('board_from', $this->BoardList($_SESSION['manageusername'])) .
		'<br>
		
		<label for="board_to">'  ._gettext('To') . ':</label>' .
		$this->MakeBoardListDropdown('board_to', $this->BoardList($_SESSION['manageusername'])) .
		'<br>
		
		<input type="submit" value="' . _gettext('Move thread') . '">';
	}
	
	/* Search for text in posts */
	function search() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		
		if (isset($_GET['query'])) {
			$search_query = $_GET['query'];
			if (isset($_GET['s'])) {
				$s = $_GET['s'];
			} else {
				$s = 0;
			}
			$search_query_array = explode('KUSABA_AND', $search_query);
			$trimmed = trim($search_query);
			$limit = 10;
			if ($trimmed == '') {
				$tpl_page .= _gettext('Please enter a search query.');
				exit;
			}
			$boardlist = $this->BoardList($_SESSION['manageusername']);
			$likequery = '';
			foreach ($search_query_array as $search_split) {
				$likequery .= "`message` LIKE '" . mysql_real_escape_string(str_replace('_', '\_', $search_split)) . "' AND ";
			}
			$likequery = substr($likequery, 0, -4);
			$query = '';
			foreach ($boardlist as $board) {
				$query .= "SELECT *, '" . $board . "' as board FROM `posts_" . $board . "` WHERE `IS_DELETED` = 0 AND " . $likequery . " UNION ";
			}
			$query = substr($query, 0, -6) . 'ORDER BY `postedat` DESC';
			$numresults = $tc_db->GetAll($query);
			$numrows = count($numresults);
			if ($numrows == 0) {
				$tpl_page .= '<h4>' . _gettext('Results') . '</h4>';
				$tpl_page .= '<p>' . _gettext('Sorry, your search returned zero results.') . '</p>';
			} else {
				$query .= " LIMIT $s, $limit";
				$results = $tc_db->GetAll($query);
				$tpl_page .= '<p style="font-size: 1.5em;">Results for: <b>' . $search_query . '</b></p>';
				$count = 1 + $s;
				foreach ($results AS $line) {
					$tpl_page .= '<span style="font-size: 1.5em;">' . $count . '.</span> <span style="font-size: 1.3em;">Board: /' . $line['board'] . '/, <a href="'.KU_BOARDSPATH . '/' . $line['board'] . '/res/';
					if ($line['parentid'] == 0) {
						$tpl_page .= $line['id'] . '.html">';
					} else {
						$tpl_page .= $line['parentid'] . '.html#' . $line['id'] . '">';
					}
					
					if ($line['parentid'] == 0) {
						$tpl_page .= 'Thread #' . $line['id'];
					} else {
						$tpl_page .= 'Thread #' . $line['parentid'] . ', Post #' . $line['id'];
					}
					$tpl_page .= '</a></span>';
					
					$regexp = '/(';
					foreach ($search_query_array as $search_word) {
						$regexp .= preg_quote($search_word) . '|';
					}
					$regexp = substr($regexp, 0, -1) . ')/';
					//$line['message'] = preg_replace_callback($regexp, array(&$this, 'search_callback'), stripslashes($line['message']));
					$line['message'] = stripslashes($line['message']);
					$tpl_page .= '<fieldset>' . $line['message'] . '</fieldset><br>';
					$count++;
				}
				$currPage = (($s / $limit) + 1);
				$tpl_page .= '<br>';
				if ($s >= 1) {
					$prevs = ($s - $limit);
					$tpl_page .= "&nbsp;<a href=\"?action=search&s=$prevs&query=" . urlencode($search_query) . "\">&lt;&lt; Prev 10</a>&nbsp&nbsp;";
				}
				$pages = intval($numrows / $limit);
				if ($numrows % $limit) {
					$pages++;
				}
				if (!((($s + $limit) / $limit) == $pages) && $pages != 1) {
					$news = $s + $limit;
					$tpl_page .= "&nbsp;<a href=\"?action=search&s=$news&query=" . urlencode($search_query) . "\">Next 10 &gt;&gt;</a>";
				}
				
				$a = $s + ($limit);
				if ($a > $numrows) {
					$a = $numrows;
				}
				$b = $s + 1;
				
				$tpl_page .= $this->search_results_display($a, $b, $numrows);
			}
		}
		
		$tpl_page .= '<form action="?" method="get">
		<input type="hidden" name="action" value="search">
		<input type="hidden" name="s" value="0">
		
		<b>'._gettext('Query').'</b>:<br><input type="text" name="query" ';
		if (isset($_GET['query'])) {
			$tpl_page .= 'value="' . $_GET['query'] . '" ';
		}
		$tpl_page .= 'size="52"><br>
		
		<input type="submit" value="'._gettext('Search').'"><br><br>
		
		<h1>Search Help</h1>
		
		Separate search terms with the word <b>KUSABA_AND</b><br><br>
		
		To find a single phrase anywhere in a post\'s message, use:<br>
		%some phrase here%<br><br>
		
		To find a phrase at the beginning of a post\'s message:<br>
		some phrase here%<br><br>
		
		To find a phrase at the end of a post\'s message:<br>
		%some phrase here<br><br>
		
		To find two phrases anywhere in a post\'s message, use:<br>
		%first phrase here%KUSABA_AND%second phrase here%<br><br>
		
		</form>';
	}
	
	function search_callback($matches) {
		print_r($matches);
		return '<b>' . $matches[0] . '</b>';
	}
	
	function search_results_display($a, $b, $numrows) {
		return '<p>' . _gettext('Results') . ' <b>' . $b . '</b> to <b>' . $a . '</b> of <b>' . $numrows . '</b></p>' . "\n" .
		'<hr>';
	}
	
	/* View and delete reports */
	function reports() {
		global $tc_db, $smarty, $tpl_page;
		$this->ModeratorsOnly();
		if($this->CheckAccess() < 2) {
			exitWithErrorPage('You do not have permission to access this page');
		}
		$tpl_page .= '<h2>' . _gettext('Reports') . '</h2><br>';
		if (isset($_GET['clear'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "reports` WHERE `id` = '" . mysql_real_escape_string($_GET['clear']) . "' LIMIT 1");
			if (count($results) > 0) {
				$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "reports` SET `cleared` = '1' WHERE `id` = '" . mysql_real_escape_string($_GET['clear']) . "' LIMIT 1");
				$tpl_page .= 'Report successfully cleared.<hr>';
				foreach($results as $result) {
					management_addlogentry(_gettext('Cleared thread #<a 
href="?action=viewdeletedthread&board=' . $result['board'].'&thread='.$result['postid'].'">'.$result['postid'].'</a> (ID: 
'.$_GET['clear'].')'), 0);
				}
			}
		}
		$none = false;
		$query = "SELECT * FROM `" . KU_DBPREFIX . "reports` WHERE `cleared` = 0";
		if (!$this->CurrentUserIsAdministrator()) {
			$boardlist = $this->BoardList($_SESSION['manageusername']);
			if (empty($boardlist)) {
				$tpl_page .= 'You do not moderate any boards.';
				$none = true;
			}
		}
		if($none != true) {
			$resultsreport = $tc_db->GetAll($query);
			if (count($resultsreport) > 0) {
				$tpl_page .= '<table border="1" width="100%"><tr><th>IP address of the poster</th><th>Board</th><th>Post</th><th>File</th><th>Message</th><th>Reporter 
IP</th><th>Reason</th><th>Action</th></tr>';
				foreach ($resultsreport as $linereport) {
					$results = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts_" . $linereport['board'] . "` WHERE `id` = " . mysql_real_escape_string($linereport['postid']) . "");
					foreach ($results as $line) {
						if ($line['IS_DELETED'] == 0) {
							$tpl_page .= '<tr> <td>'. md5_decrypt($line['ip'], KU_RANDOMSEED).'</td><td>/' . 
$linereport['board'] 
. 
'/</td><td><a href="' . 
KU_BOARDSPATH 
. '/' . 
$linereport['board'] 
. '/res/';
							if ($line['parentid'] == '0') {
								$tpl_page .= $linereport['postid'];
								$post_threadorpost = 'thread';
							} else {
								$tpl_page .= $line['parentid'];
								$post_threadorpost = 'post';
							}
							$tpl_page .= '.html#' . $linereport['postid'] . '">' . $line['id'] . '</a></td><td>';
							if ($line['filename'] == 'removed') {
								$tpl_page .= 'removed';
							} elseif ($line['filename'] == '') {
								$tpl_page .= 'none';
							} elseif ($line['filetype'] == 'jpg' || $line['filetype'] == 'gif' || $line['filetype'] == 'png') {
								$tpl_page .= '<a href="' . KU_BOARDSPATH . '/' . $linereport['board'] . '/src/' . $line['filename'] . '.' . $line['filetype'] . '"><img src="' . KU_BOARDSPATH . '/' . $linereport['board'] . '/thumb/' . $line['filename'] . 's.' . $line['filetype'] . '" border="0"></a>';
							} else {
								$tpl_page .= '<a href="' . KU_BOARDSPATH . '/' . $linereport['board'] . '/src/' . $line['filename'] . '.' . $line['filetype'] . '">File</a>';
							}
							$tpl_page .= '</td><td>';
							if ($line['message'] != '') {
								$tpl_page .= stripslashes($line['message']);
							} else {
								$tpl_page .= '&nbsp;';
							}
							$tpl_page .= '</td><td>' . md5_decrypt($linereport['ip'], KU_RANDOMSEED) . '</td><td>';
														if(empty($linereport['reason'])) {
								$tpl_page .= '<span style="color: grey">No reason given</span></td>';
							}
							else {
								$tpl_page .= htmlentities($linereport['reason']) . '</td>';
							}
							if($this->CurrentUserIsModeratorOfBoard($linereport['board'], $_SESSION['manageusername'])) {
							$tpl_page .= '<td><a href="?action=reports&clear=' . $linereport['id'] . '">Clear</a>&nbsp;&#91;<a href="?action=delposts&boarddir=' . $linereport['board'] . '&del' . $post_threadorpost . 'id=' . $line['id'] . '" title="Delete" onclick="return confirm(\'Are you sure you want to delete this thread/post?\');">D</a>&nbsp;<a href="' . KU_CGIPATH . '/manage_page.php?action=delposts&boarddir=' . $linereport['board'] . '&del' . $post_threadorpost . 'id=' . $line['id'] . '&postid=' . $line['id'] . '" title="Delete &amp; Ban" onclick="return confirm(\'Are you sure you want to delete and ban this poster?\');">&amp;</a>&nbsp;<a href="?action=bans&banboard=' . $linereport['board'] . '&banpost=' . $line['id'] . '" title="Ban">B</a>&#93;</td></tr>';
							}
							else {
								$tpl_page .= '<td><span style="color: grey">Clear [D & B]</span></td></tr>';
							}
						} else {
							$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "reports` SET `cleared` = 1 WHERE id = " . $linereport['id'] . "");
						}
					}
				}
				$tpl_page .= '</table>';
			} else {
				$tpl_page .= 'No reports to show.';
			}
		}
	}
	
	/* View recent activity*/
	function recentactivity() {
		global $tc_db, $smarty, $tpl_page;
		$this->ModeratorsOnly();
		if($this->CheckAccess() < 1) {
			exitWithErrorPage('You do not have permission to access this page');
		}
		if (!isset($_SESSION['imagesperpage'])) {
			$_SESSION['imagesperpage'] = 5;
		}
		
		if (isset($_GET['show'])) {
			if ($_GET['show'] == '5' || $_GET['show'] == '25' || $_GET['show'] == '50' || $_GET['show'] == '75' || $_GET['show'] == '100') {
				$_SESSION['imagesperpage'] = $_GET['show'];
			}
		}
		
		$tpl_page .= '<h2>' . ucwords(_gettext('Recent Activity')) . '</h2><br>
		Limit activity to: <a href="?action=recentactivity&show=5">5</a>, <a href="?action=recentactivity&show=25">25</a>, <a href="?action=recentactivity&show=50">50</a>, <a href="?action=recentactivity&show=75">75</a>, <a href="?action=recentactivity&show=100">100</a> <br>';
		if (isset($_POST['clear'])) {
			if ($_POST['clear'] != '') {
				$clear_decrypted = md5_decrypt($_POST['clear'], KU_RANDOMSEED);
				if ($clear_decrypted != '') {
					$clear_unserialized = unserialize($clear_decrypted);
					
					foreach ($clear_unserialized as $clear_sql) {
						$tc_db->Execute($clear_sql);
					}
					$tpl_page .= 'Successfully marked previous posts as reviewed.<hr>';
				}
			}
		}
		
		$dayago = (time() - 86400);
		$reviewsql_array = array();	
		$postshown = 0;
		$boardlist = $this->BoardList($_SESSION['manageusername']);
		foreach ($boardlist as $board) {
			if ($postshown <= $_SESSION['imagesperpage']) {
				$post_list = $tc_db->GetAll("SELECT HIGH_PRIORITY `name`, `postedat`,`id`,`parentid`,`filename`,`filetype`,`thumb_w`,`thumb_h`,`message`,`subject`  FROM `" . KU_DBPREFIX . "posts_" . $board . "` WHERE `postedat` > " . $dayago . " AND `reviewed` = 0 AND `IS_DELETED` = 0  ORDER BY RAND() LIMIT " . mysql_real_escape_string($_SESSION['imagesperpage']));
				if(count($post_list) > 0) {
					$reviewsql = "UPDATE `" . KU_DBPREFIX . "posts_" . $board . "` SET `reviewed` = 1 WHERE ";
					foreach ($post_list as $post) {
						$reviewsql .= '`id` = ' . $post['id'] . ' OR ';
						if($post['name'] == '') {
							$post['name'] = 'Anonymous';
						}
						$tpl_page .= '<div style="background: #D6DAF0;border: 1px solid #6f819b;padding: 5px 10px 5px 10px; width:500px;height: auto;font-family:Arial"><span style="color:#117743;font-weight:bold;">';

						$tpl_page .= $post['name'];
						if($post['subject'] != '') {
							$tpl_page .= '<b><span style="background:inherit;font-size:18px;font-family:serif;color:#0F0C5D;font-weight:800"> ' . $post['subject'] . '</span></b>';
						}
						$tpl_page .= '</span> posted at ' . formatDate($post['postedat']) . ' on ' . "\n";
						$tpl_page .= '<a style="color:#34345c;font-family:sans-serif;font-size:14px;text-decoration: underline" href="'. KU_BOARDSPATH .'/'. $board .'/res/';
						if($post['parentid'] == 0) {
							$tpl_page .=  $post['id'] . '.html';
						}
						else {							
							$tpl_page .=  $post['parentid'] . '.html#'. $post['id'];
						}
						$tpl_page .= '"><b>/'.$board.'/</b></a>';
						$tpl_page .= '<br/><a href="'. KU_BOARDSPATH . '/' . $board . '/src/'.$post['filename'].'.'.$post['filetype'].'"><img width="'.$post['thumb_w'].'" height="'.$post['thumb_h'].'" src="'. KU_BOARDSPATH . '/' . $board . '/thumb/'.$post['filename'].'s.'.$post['filetype'].'" alt="" border="0" style="float: left;padding-right:10px;padding-top:5px;"/></a>'."\n".'<span>' .$post['message'] . '</span><br style="clear: both"/></div><br/>' . "\n";
					}
					$reviewsql = substr($reviewsql, 0, -3) . 'LIMIT ' . count($post_list);
					$reviewsql_array[] = $reviewsql;
					$postshown += count($post_list);
				}
			}	
		}
		if ($postshown > 0)  {
			$tpl_page .= '<br><br>' . $postshown . ' posts shown.<br>
			<form action="?action=recentactivity" method="post">
			<input type="hidden" name="clear" value="' . md5_encrypt(serialize($reviewsql_array), KU_RANDOMSEED) . '">
			<input type="submit" value="Clear All On Page As Reviewed">
			</form><br/>';
		} else {
			$tpl_page .= '<br/>No recent posts currently need review.';
		}
	}
	
	/* Display posting rates for the past hour */
	function posting_rates() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();	
		$tpl_page .= '<h2>' . _gettext('Posting rates (past hour)') . '</h2><br>';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` ORDER BY `order` ASC");
		if (count($results) > 0) {
			$tpl_page .= '<table border="1" cellspacing="2" cellpadding="2" width="100%"><tr><th>' . _gettext('Board') . '</th><th>' . _gettext('Threads') . '</th><th>' . _gettext('Replies') . '</th><th>' . _gettext('Posts') . '</th></tr>';
			foreach ($results as $line) {
				$rows_threads = $tc_db->GetOne("SELECT HIGH_PRIORITY count(id) FROM `" . KU_DBPREFIX . "posts_" . $line['name'] . "` WHERE `parentid` = 0 AND `postedat` >= " . (time() - 3600));
				$rows_replies = $tc_db->GetOne("SELECT HIGH_PRIORITY count(id) FROM `" . KU_DBPREFIX . "posts_" . $line['name'] . "` WHERE `parentid` != 0 AND `postedat` >= " . (time() - 3600));
				$rows_posts = $rows_threads + $rows_replies;
				$threads_perminute = $rows_threads;
				$replies_perminute = $rows_replies;
				$posts_perminute = $rows_posts;
				$tpl_page .= '<tr><td><b>' . $line['name'] . '</b></td><td>' . $threads_perminute . '</td><td>' . $replies_perminute . '</td><td>' . $posts_perminute . '</td></tr>';
			}
			$tpl_page .= '</table>';
		} else {
			$tpl_page .= _gettext('No boards');
		}
	}
	
	function statistics() {
		global $tc_db, $smarty, $tpl_page;
		if($this->CheckAccess() < 10) {
			exitWithErrorPage('You do not have permission to access this page');
		}				
		$tpl_page .= '<h2>Statistics</h2><br>';
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
		if (count($results) > 0) {
		$tpl_page .= '<h3>Posts per week</h2><table border="1" cellspacing="2" cellpadding="2" width="100%"><tr><th>Board</th><th>Thread</th>';
					foreach ($results as $line) {
						$posts = $tc_db->GetOne("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "posts_" . $line['name'] . "` WHERE `postedat` > " . (time() - 604800) . "");		
						$tpl_page .= '<tr><td>'.$line['name'] . '</td><td>';
						if($posts <= 10) {
							$tpl_page .= '<span style="color: grey">'.$posts .'</span></td></tr>';
						}
						elseif($posts > 10 && $posts <= 25) {
							$tpl_page .= '<span style="color: black">'.$posts .'</span></td></tr>';
						}
						elseif($posts > 25 && $posts <= 50) {
							$tpl_page .= '<span style="color: blue">'.$posts .'</span></td></tr>';
						}
						elseif($posts > 50) {
							$tpl_page .= '<span style="color: red; font-weight: bold">'.$posts .'</span></td></tr>';
						}
					}
					$tpl_page .= '</table>';
		}
		$tpl_page .= '<img src="manage_page.php?graph&type=day"> 
		<img src="manage_page.php?graph&type=week"> 
		<img src="manage_page.php?graph&type=postnum"> 
		<img src="manage_page.php?graph&type=unique"> 
		<img src="manage_page.php?graph&type=posttime">';
	}
	
	/* If the user logged in isn't an admin, kill the script */
	function AdministratorsOnly() {
		global $tc_db, $smarty, $tpl_page;
		
		if (!$this->CurrentUserIsAdministrator()) {
			exitWithErrorPage('That page is for admins only.');
		}
	}
	/* Root administrator defined in the config */
	function RootOnly() {
		global $tc_db, $smarty, $tpl_page;
		
		if (!$this->CurrentUserIsAdministrator() || $_SESSION['manageusername'] != KU_ROOT) {
			exitWithErrorPage('Permission denied.');
		}
	}
	
	/* If the user logged in isn't an moderator or higher, kill the script */
	function ModeratorsOnly() {
		global $tc_db, $smarty, $tpl_page;
		
		if ($this->CurrentUserIsAdministrator()) {
			return true;
		} else {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `type` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $_SESSION['manageusername'] . "' AND `password` = '" . $_SESSION['managepassword'] . "' LIMIT 1");
			foreach ($results as $line) {
				if ($line['type'] != 2) {
					exitWithErrorPage('That page is for moderators and administrators only.');
				}
			}
		}
	}
	function CheckAccess() {
		if ($this->CurrentUserIsAdministrator()) {
			return 9999;
		}
		global $tc_db, $smarty, $tpl_page;		
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `access` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $_SESSION['manageusername'] . "' AND `password` = '" . $_SESSION['managepassword'] . "' LIMIT 1");
		foreach ($results as $line) {
			return $line['access'];
		}
	}
	
	/* See if the user logged in is an admin */
	function CurrentUserIsAdministrator() {
		global $tc_db, $smarty, $tpl_page;
		
		if ($_SESSION['manageusername'] == '' || $_SESSION['managepassword'] == '') {
			$_SESSION['manageusername'] = '';
			$_SESSION['managepassword'] = '';
			return false;
		}
		
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `type` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $_SESSION['manageusername'] . "' AND `password` = '" . $_SESSION['managepassword'] . "' LIMIT 1");
		foreach ($results as $line) {
			if ($line['type'] == 1) {
				return true;
			} else {
				return false;
			}
		}
		
		/* If the function reaches this point, something is fishy.  Kill their session */
	}
	
	/* See if the user logged in is a moderator */
	function CurrentUserIsModerator() {
		global $tc_db, $smarty, $tpl_page;
		
		if ($_SESSION['manageusername'] == '' || $_SESSION['managepassword'] == '') {
			$_SESSION['manageusername'] = '';
			$_SESSION['managepassword'] = '';
			return false;
		}
		
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `type` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $_SESSION['manageusername'] . "' AND `password` = '" . $_SESSION['managepassword'] . "' LIMIT 1");
		foreach ($results as $line) {
			if ($line['type'] == 2) {
				return true;
			} else {
				return false;
			}
		}
		
		/* If the function reaches this point, something is fishy.  Kill their session */
		session_destroy();
		exitWithErrorPage('Invalid session, please log in again.');
	}
	
	/* See if the user logged in is a moderator of a specified board */
	function CurrentUserIsModeratorOfBoard($board, $username) {
		global $tc_db, $smarty, $tpl_page;
		
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `type`, `boards` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $username . "' LIMIT 1");
		if (count($results) > 0) {
			foreach ($results as $line) {
				if ($line['boards'] == 'allboards') {
					return true;
				} else {
					if ($line['type'] == '1') {
						return true;
					} else {
						$array_boards = explode('|', $line['boards']);
						if (in_array($board, $array_boards)) {
							return true;
						} else {
							return false;
						}
					}
				}
			}
		} else {
			return false;
		}
	}
	
	/* Generate a list of boards a moderator controls */
	function BoardList($username) {
		global $tc_db, $smarty, $tpl_page;
		
		$staff_boardsmoderated = array();
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `boards` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $username . "' LIMIT 1");
		if ($this->CurrentUserIsAdministrator() || $results[0][0] == 'allboards') {
			$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
			foreach ($resultsboard as $lineboard) {
				$staff_boardsmoderated = array_merge($staff_boardsmoderated, array($lineboard['name']));
			}
		} else {
			if ($results[0][0] != '') {
				foreach ($results as $line) {
					$array_boards = explode('|', $line['boards']);
				}
				foreach ($array_boards as $this_board_name) {
					$staff_boardsmoderated = array_merge($staff_boardsmoderated, array($this_board_name));
				}
			}
		}
		
		return $staff_boardsmoderated;
	}
	
	/* Generate a list of boards in query format */
	function sqlboardlist() {
		global $tc_db, $smarty, $tpl_page;
		
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
		$sqlboards = '';
		foreach ($results as $line) {
			$sqlboards .= 'posts_' . $line['name'] . ', ';
		}
		
		return substr($sqlboards, 0, -2);
	}
	
	/* Generate a dropdown box from a supplied array of boards */
	function MakeBoardListDropdown($name, $boards) {
		$output = '<select name="' . $name . '"><option value="">Select a Board</option>';
		if ($boards != '') {
			foreach ($boards as $board) {
				$output .= '<option value="' . $board . '">/' . $board . '/</option>';
			}
		}
		$output .= '</select>';
		
		return $output;
	}
		function MakeBoardListStaff($name, $staffs) {
		$output = '<select name="' . $name . '"><option value="">Select a staff member</option>';
		if ($staffs != '') {
			foreach ($staffs as $staff) {
				$output .= '<option value="' . $staff . '">' . $staff . '</option>';
			}
		}
		$output .= '</select>';
		
		return $output;
	}
	/* Generate a series of checkboxes from a supplied array of boards */
	function MakeBoardListCheckboxes($prefix, $boards) {
		$output = '';
		
		if ($boards != '') {
			foreach ($boards as $board) {
				$output .= '<label for="' . $prefix . $board . '">' . $board . '</label><input type="checkbox" name="' . $prefix . $board . '"> ';
			}
		}
		
		return $output;
	}
		
	/* Delete files without their md5 stored in the database */
	function delunusedimages($verbose = false) {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		
		$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards`");
		foreach ($resultsboard as $lineboard) {
			if ($verbose) {
				$tpl_page .= '<b>Looking for unused images in /' . $lineboard['name'] . '/</b><br>';
			}
			$filemd5list = array();
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `filemd5` FROM `" . KU_DBPREFIX . "posts_" . $lineboard['name'] . "` WHERE `IS_DELETED` = 0 AND `filename` != '' AND `filename` != 'removed' AND `filemd5` != ''");
			foreach ($results as $line) {
				$filemd5list[] = $line['filemd5'];
			}
			$dir = './' . $lineboard['name'] . '/src';
			$files = glob("$dir/{*.jpg, *.png, *.gif, *.swf}", GLOB_BRACE);
			if (is_array($files)) {
				foreach ($files as $file) {
					if (in_array(md5_file(KU_BOARDSDIR . $lineboard['name'] . '/src/' . basename($file)), $filemd5list) == false) {
						if (time() - filemtime(KU_BOARDSDIR . $lineboard['name'] . '/src/' . basename($file)) > 120) {
							if ($verbose == true) {
								$tpl_page .= 'A live record for ' . $file . ' was not found;  the file has been removed.<br>';
							}
							unlink(KU_BOARDSDIR . $lineboard['name'] . '/src/' . basename($file));
							@unlink(KU_BOARDSDIR . $lineboard['name'] . '/thumb/' . substr(basename($file), 0, -4) . 's' . substr(basename($file), strlen(basename($file)) - 4));
							@unlink(KU_BOARDSDIR . $lineboard['name'] . '/thumb/' . substr(basename($file), 0, -4) . 'c' . substr(basename($file), strlen(basename($file)) - 4));
						}
					}
				}
			}
		}
		
		return true;
	}
	
	/* Delete replies currently not marked as deleted who belong to a thread which is marked as deleted */
	function delorphanreplies($verbose = false) {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		
		$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards`");
		foreach ($resultsboard as $lineboard) {
			if ($verbose) {
				$tpl_page .= '<b>Looking for orphans in /' . $lineboard['name'] . '/</b><br>';
			}
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `parentid` FROM `" . KU_DBPREFIX . "posts_" . $lineboard['name'] . "` WHERE `parentid` != '0' AND `IS_DELETED` = 0");
			foreach ($results as $line) {
				$exists_rows = $tc_db->GetAll("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "posts_" . $lineboard['name'] . "` WHERE `id` = '" . $line['parentid'] . "' AND `IS_DELETED` = 0", 1);
				if ($exists_rows[0] == 0) {
					$post_class = new Post($line['id'], $lineboard['name']);
					$post_class->Delete;
					
					if ($verbose) {
						$tpl_page .= 'Reply #' . $line['id'] . '\'s thread (#' . $line['parentid'] . ') does not exist!  It has been deleted.<br>';
					}
				}
			}
		}
		
		return true;
	}
	function delspecorphanreplies($board) {
	global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `parentid` FROM `" . KU_DBPREFIX . "posts_" . $board . "` WHERE `parentid` != '0' AND `IS_DELETED` = 0");
		foreach ($results as $line) {
			$exists_rows = $tc_db->GetAll("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "posts_" . $board . "` WHERE `id` = '" . $line['parentid'] . "' AND `IS_DELETED` = 0", 1);
			if ($exists_rows[0] == 0) {
				$post_class = new Post($line['id'], $lineboard['name']);
				$post_class->Delete;
			}
		}		
		return true;
	}
	function delspecunusedimages($board) {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		$filemd5list = array();
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `filemd5` FROM `" . KU_DBPREFIX . "posts_" . $board . "` WHERE `IS_DELETED` = 0 AND `filename` != '' AND `filename` != 'removed' AND `filemd5` != ''");
			foreach ($results as $line) {
				$filemd5list[] = $line['filemd5'];
			}
			$dir = './' . $board . '/src';
			$files = glob("$dir/{*.jpg, *.png, *.gif, *.swf}", GLOB_BRACE);
			if (is_array($files)) {
				foreach ($files as $file) {
					if (in_array(md5_file(KU_BOARDSDIR . $board . '/src/' . basename($file)), $filemd5list) == false) {
						if (time() - filemtime(KU_BOARDSDIR . $board . '/src/' . basename($file)) > 120) {
							if ($verbose == true) {
								$tpl_page .= 'A live record for ' . $file . ' was not found;  the file has been removed.<br>';
							}
							unlink(KU_BOARDSDIR . $board . '/src/' . basename($file));
							@unlink(KU_BOARDSDIR . $board . '/thumb/' . substr(basename($file), 0, -4) . 's' . substr(basename($file), strlen(basename($file)) - 4));
							@unlink(KU_BOARDSDIR . $board . '/thumb/' . substr(basename($file), 0, -4) . 'c' . substr(basename($file), strlen(basename($file)) - 4));
						}
					}
				}
			}
		return true;		
	}
}
?>

