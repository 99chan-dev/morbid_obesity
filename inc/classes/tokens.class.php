/*
	* +------------------------------------------------------------------------------+
	* Manage pages
	* +------------------------------------------------------------------------------+
	*/

function tokens() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		$tpl_page .= '<h2>' . ucwords(_gettext('Module Editor')) . '</h2><br>';
		if(isset($_GET["delete"])) {
			if(!is_numeric($_GET['delete'])) {
				exitWithErrorPage('Invalid ID');
			}
			if($tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "module` WHERE `id` = 
'".$_GET['delete']."'")) {
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
						$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . 
KU_DBPREFIX . "boards`");
						$module_boards = array();
						foreach ($results as $line) {
							$module_boards = array_merge($module_boards, 
array($line['name']));
						}
						$module_changed_boards = array();
						$module_new_boards = array();
						while (list($postkey, $postvalue) = each($_POST)) {
							if (substr($postkey, 0, 10) == "modulefrom") {
								$module_changed_boards = 
array_merge($module_changed_boards, array(substr($postkey, 10)));
							}
						}
						while (list(, $module_thisboard_name) = each($module_boards)) 
{
							if (in_array($module_thisboard_name, 
$module_changed_boards)) {
								$module_new_boards = 
array_merge($module_new_boards, array($module_thisboard_name));
							}
						}
						if ($module_new_boards == array()) {
							exitWithErrorPage(_gettext('Please select a board.'));
						}
						$module_boards = implode('|', $module_new_boards);
				if($tc_db->Execute("UPDATE`" . KU_DBPREFIX . "module` SET `trigger` = 
".$tc_db->qstr($_POST['trigger']).", `html` = ".$tc_db->qstr($_POST['html']).", 
`boards` = ".$tc_db->qstr($module_boards)." WHERE `id` = ".$tc_db->qstr($_POST['id'])." ")) {
					$tpl_page .= '<b>Module edited</b><br/><br/>';
				}
				else {
					die(mysql_error());
				}
			}
			else {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "module` 
WHERE `id` = '".$_GET['edit']."'");
			if (count($results) > 0) {
				foreach($results as $line) {
					$tpl_page .= '<div>Editing module '.$line['id'].'.</div><br/><form 
action="?action=addmodule&edit" method="post"><input type="hidden" name="id" value="'.$line['id'].'"/><label 
for="trigger">Trigger:</label><input type="text" name="trigger" value="'.$line['trigger'].'"/><br/><label 
for="html">HTML:</label><textarea name="html" rows="5" cols="50">'.$line['html'].'</textarea>';
												$tpl_page 
.='<label>'._gettext('Boards').':</label><br>';
							
							$array_boards = array();
							$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY * 
FROM `" . KU_DBPREFIX . "boards`");
							foreach ($resultsboard as $lineboard) {
								$array_boards = array_merge($array_boards, 
array($lineboard['name']));
							}
							foreach ($array_boards as $this_board_name) {
								$tpl_page .= '<label for="module' . 
$this_board_name . '">' . $this_board_name . '</label><input type="checkbox" name="modulefrom' . 
$this_board_name . '" ';
								if (in_array($this_board_name, explode("|", 
$line['boards'])) && explode("|", $line['boards']) != '') {
									$tpl_page .= 'checked ';
								}
								$tpl_page .= '><br>';
							}
					$tpl_page .= '<input type="submit" name="submit" 
value="Edit"/></form><br/>';
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
		  $tpl_page .= 'Uploaded to <a href="' . "module_images/" . $_FILES["file"]["name"] . 
'">/module_images/ '. $_FILES["file"]["name"] .'</a><br/><br/>';
	   }
	   else {
			exitWithErrorPage('Invalid filetype');
		}
      }
    }


		}
		$tpl_page .= '<form action="" method="post" 
enctype="multipart/form-data">
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
						$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . 
KU_DBPREFIX . "boards`");
						$module_boards = array();
						if (isset($_POST['addfromall'])) {
							foreach ($results as $line) {
								$module_new_boards[] = $line['name'];
							}
						}
						else {
						foreach ($results as $line) {
							$module_boards = array_merge($module_boards, 
array($line['name']));
						}
						$module_changed_boards = array();
						$module_new_boards = array();
						while (list($postkey, $postvalue) = each($_POST)) {
							if (substr($postkey, 0, 10) == "modulefrom") {
								$module_changed_boards = 
array_merge($module_changed_boards, array(substr($postkey, 10)));
							}
						}
						while (list(, $module_thisboard_name) = each($module_boards)) 
{
							if (in_array($module_thisboard_name, 
$module_changed_boards)) {
								$module_new_boards = 
array_merge($module_new_boards, array($module_thisboard_name));
							}
						}
						if ($module_new_boards == array() && $_POST['addfromall'] != 
'on') {
							exitWithErrorPage(_gettext('Please select a board.'));
						}
						}
						$module_boards = implode('|', $module_new_boards);
			if($tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "module` (`trigger`,`html`,`boards`) 
VALUES(".$tc_db->qstr($_POST['trigger']).", ".$tc_db->qstr($_POST['html']).", ".$tc_db->qstr($module_boards).")")) {
				$tpl_page .= '<b>Token inserted</b><br/><br/>';
			}
			else {
				die(mysql_error());
			}
		}
	if(!$_GET['edit'] && !$_GET['do']) {
		$tpl_page .= '<div><form action="" method="post"><label 
for="trigger">Trigger</label><input type="text" name="trigger"/><br/><label for="html">HTML to 
append</label><textarea name="html" rows="5" cols="50"></textarea>';
		$tpl_page .= '<br/>
		'._gettext('Boards').':
		
		<label for="banfromall"><b>'._gettext('All boards').'</b></label>
		<input type="checkbox" name="addfromall"><br>OR<br>' .
		$this->MakeBoardListCheckboxes('modulefrom', $this->BoardList($_SESSION['manageusername'])) .
		'<br/>
		<input type="submit" value="Add" name="submit"/></form></div><br/><hr/><br/>';
	    $tpl_page .= '<table cellspacing="2" cellpadding="2" frame="hsides" 
width="100%"><tr><th>Trigger</th><th>Boards</th><th width="50%">HTML</th><th>Action</th></tr>';
      $results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "module` ORDER BY `id` DESC");
    if (count($results) > 0) {
     foreach($results as $line) {
      $count += 1;
      if ($count%2 == false ? $linecolour = "#fff" : $linecolour = "#dce3e8");
      $tpl_page .= "<tr bgcolor=\"$linecolour\"><td>" . $line['trigger'] . "</td><td>";
      $modboards = explode("|",$line['boards']);
      foreach($modboards as $board) $tpl_page .= '/' .$board . "/ ";
     $tpl_page .= "</td><td align=justify>" . wordwrap(htmlentities(substr($line['html'], 0 ,400))) . "</td>";
      $tpl_page .= '<td>[<a href="">'. 'Edit' .'</a>] [<a 
href="">'. 'x' .'</a>]</td>';
      $tpl_page .= '</tr>';
   }
   }
   else {
     $tpl_page .= 'No tokens added';
    }
  $tpl_page .= '</table>';
		$tpl_page .= '<br/><h1>Files</h1><a href="">Upload a 
file</a><br/><br/>';
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
			$tpl_page .= '<a href="'. $imgdir . '/' . $a_img[$x].'"><img src="'.$imgdir . '/' . 
$a_img[$x].'" width="50" height="50" alt="" style="border: 1px solid #000; float: left; margin-right: 
5px"/></a>';
		}
			$tpl_page .= '<br/><br/>' . $totimg .' images uploaded.<br/>';
		}
	}