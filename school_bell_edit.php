<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2008-2026
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
	Igor Olhovskiy <igorolhovskiy@gmail.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!(permission_exists('school_bell_edit') || permission_exists('school_bell_add'))) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//set variables from the get parameters
	$page = is_numeric($_GET['page'] ?? '') ? max(0, (int)$_GET['page']) : 0;
	$order_by = preg_replace('#[^a-zA-Z0-9_\-]#', '', $_GET['order_by'] ?? '');
	$order = ($_GET['order'] ?? '') === 'desc' ? 'desc' : 'asc';
	$search = $_GET['search'] ?? '';

//build the query string, used to return to the same view of the list page
	$param = [];
	if (!empty($page)) {
		$param['page'] = $page;
	}
	if (!empty($_GET['order_by'])) {
		$param['order_by'] = $order_by;
	}
	if (!empty($_GET['order'])) {
		$param['order'] = $order;
	}
	if (!empty($search)) {
		$param['search'] = $search;
	}
	$query_string = http_build_query($param);

//action add or update
	if (!empty($_REQUEST['id']) && is_uuid($_REQUEST['id'])) {
		$action = 'update';
		$school_bell_uuid = $_REQUEST['id'];
	}
	else {
		$action = 'add';
	}

//set the defaults
	$school_bell_uuid = $school_bell_uuid ?? '';
	$school_bell_name = '';
	$school_bell_leg_a_type = 'loopback/';
	$school_bell_leg_a_data = '';
	$school_bell_leg_b_type = '';
	$school_bell_leg_b_data = '';
	$school_bell_ring_timeout = 3;
	$school_bell_min = -1;
	$school_bell_hour = -1;
	$school_bell_dom = -1;
	$school_bell_mon = -1;
	$school_bell_dow = -1;
	$school_bell_timezone = date_default_timezone_get();
	$school_bell_enabled = 'true';
	$school_bell_description = '';

//the recordings directory, used to store the full path of the selected recording
	$recordings_dir = $settings->get('switch', 'recordings', '/var/lib/freeswitch/recordings')."/".$_SESSION['domain_name'];

//get the timezones
	$timezone_identifiers_list = timezone_identifiers_list();

//get http post variables and set them to php variables
	if (!empty($_POST)) {
		$school_bell_name = $_POST['school_bell_name'] ?? null;
		$school_bell_leg_a_data = $_POST['school_bell_leg_a_data'] ?? null;
		$school_bell_leg_b_data = $_POST['school_bell_leg_b_data'] ?? null;
		$school_bell_ring_timeout = (int)($_POST['school_bell_ring_timeout'] ?? 3);
		$school_bell_min = (int)($_POST['school_bell_min'] ?? -1);
		$school_bell_hour = (int)($_POST['school_bell_hour'] ?? -1);
		$school_bell_dom = (int)($_POST['school_bell_dom'] ?? -1);
		$school_bell_mon = (int)($_POST['school_bell_mon'] ?? -1);
		$school_bell_dow = (int)($_POST['school_bell_dow'] ?? -1);
		$school_bell_timezone = $_POST['school_bell_timezone'] ?? null;
		$school_bell_enabled = $_POST['school_bell_enabled'] ?? 'true';
		$school_bell_description = $_POST['school_bell_description'] ?? null;

		//the leg types are fixed for now, the extension is called with loopback and
		//the recording is played back from its full path on the switch
			$school_bell_leg_a_type = 'loopback/';
			$school_bell_leg_b_type = '';
			if (strlen($school_bell_leg_b_data ?? '') > 0) {
				//a value that is already an absolute path is stored as it is, a bare file
				//name gets the recordings directory of the domain prepended
				if ($school_bell_leg_b_data[0] === '/') {
					$school_bell_leg_b_type = $school_bell_leg_b_data;
				}
				else {
					$school_bell_leg_b_type = $recordings_dir."/".$school_bell_leg_b_data;
				}
			}

		//set the default ring timeout to 3 seconds and keep it in a sane range
			if ($school_bell_ring_timeout < 0) {
				$school_bell_ring_timeout = 3;
			}
			if ($school_bell_ring_timeout > 3600) {
				$school_bell_ring_timeout = 3600;
			}

		//validate the schedule fields, -1 means every value
			if ($school_bell_min != -1 && ($school_bell_min > 59 || $school_bell_min < 0)) {
				$school_bell_min = 0;
			}
			if ($school_bell_hour != -1 && ($school_bell_hour < 0 || $school_bell_hour > 23)) {
				$school_bell_hour = 0;
			}
			if ($school_bell_dom != -1 && ($school_bell_dom < 1 || $school_bell_dom > 31)) {
				$school_bell_dom = 1;
			}
			if ($school_bell_mon != -1 && ($school_bell_mon < 1 || $school_bell_mon > 12)) {
				$school_bell_mon = 1;
			}
			if ($school_bell_dow != -1 && ($school_bell_dow < 0 || $school_bell_dow > 6)) {
				$school_bell_dow = 0;
			}

		//validate the timezone
			if (!in_array($school_bell_timezone, $timezone_identifiers_list, true)) {
				$school_bell_timezone = date_default_timezone_get();
			}

		//normalize the enabled field
			if (strlen($school_bell_enabled ?? '') == 0) {
				$school_bell_enabled = 'true';
			}
	}

//process the user data and save it to the database
	if (!empty($_POST) && empty($_POST['persistformvar'])) {

		//delete the school bell
			if (permission_exists('school_bell_delete')) {
				if (($_POST['action'] ?? '') == 'delete' && is_uuid($school_bell_uuid)) {
					//prepare
						$array[0]['checked'] = 'true';
						$array[0]['uuid'] = $school_bell_uuid;
					//delete
						$obj = new school_bells;
						$obj->delete($array);
					//redirect
						header('Location: school_bells.php'.($query_string ? '?'.$query_string : ''));
						exit;
				}
			}

		//get the uuid from the post
			if ($action == 'update') {
				$school_bell_uuid = $_POST['school_bell_uuid'] ?? $school_bell_uuid;
			}

		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: school_bells.php'.($query_string ? '?'.$query_string : ''));
				exit;
			}

		//check for all required data
			$msg = '';
			if (empty($school_bell_name)) { $msg .= $text['message-required']." ".$text['label-school_bell_name']."<br>\n"; }
			if (empty($school_bell_leg_a_data)) { $msg .= $text['message-required']." ".$text['label-school_bell_leg_a_data']."<br>\n"; }
			if (!empty($msg) && empty($_POST['persistformvar'])) {
				require_once "resources/header.php";
				require_once "resources/persist_form_var.php";
				echo "<div align='center'>\n";
				echo "<table><tr><td>\n";
				echo $msg."<br />";
				echo "</td></tr></table>\n";
				persistformvar($_POST);
				echo "</div>\n";
				require_once "resources/footer.php";
				return;
			}

		//add the school_bell_uuid
			if (empty($school_bell_uuid) || !is_uuid($school_bell_uuid)) {
				$school_bell_uuid = uuid();
			}

		//the called extension is used to build the originate command, keep it clean
			$school_bell_leg_a_data = preg_replace('/[^0-9a-zA-Z_*#+@.-]/', '', (string)$school_bell_leg_a_data);

		if ($action == 'add') {

			//add the record with bound parameters so a stored value cannot change the statement
				$sql = "INSERT INTO v_school_bells ";
				$sql .= "(";
				$sql .= "domain_uuid, ";
				$sql .= "school_bell_uuid, ";
				$sql .= "school_bell_name, ";
				$sql .= "school_bell_leg_a_type, ";
				$sql .= "school_bell_leg_a_data, ";
				$sql .= "school_bell_leg_b_type, ";
				$sql .= "school_bell_leg_b_data, ";
				$sql .= "school_bell_ring_timeout, ";
				$sql .= "school_bell_min, ";
				$sql .= "school_bell_hour, ";
				$sql .= "school_bell_dom, ";
				$sql .= "school_bell_mon, ";
				$sql .= "school_bell_dow, ";
				$sql .= "school_bell_timezone, ";
				$sql .= "school_bell_enabled, ";
				$sql .= "school_bell_description, ";
				$sql .= "insert_date, ";
				$sql .= "insert_user, ";
				$sql .= "update_date, ";
				$sql .= "update_user ";
				$sql .= ") ";
				$sql .= "VALUES ";
				$sql .= "(:domain_uuid, :school_bell_uuid, :school_bell_name, :school_bell_leg_a_type, ";
				$sql .= ":school_bell_leg_a_data, :school_bell_leg_b_type, :school_bell_leg_b_data, ";
				$sql .= ":school_bell_ring_timeout, :school_bell_min, :school_bell_hour, :school_bell_dom, ";
				$sql .= ":school_bell_mon, :school_bell_dow, :school_bell_timezone, :school_bell_enabled, ";
				$sql .= ":school_bell_description, now(), :insert_user, now(), :update_user)";

			//set the parameters
				$parameters['domain_uuid'] = $domain_uuid;
				$parameters['school_bell_uuid'] = $school_bell_uuid;
				$parameters['school_bell_name'] = $school_bell_name;
				$parameters['school_bell_leg_a_type'] = $school_bell_leg_a_type;
				$parameters['school_bell_leg_a_data'] = $school_bell_leg_a_data;
				$parameters['school_bell_leg_b_type'] = $school_bell_leg_b_type;
				$parameters['school_bell_leg_b_data'] = $school_bell_leg_b_data;
				$parameters['school_bell_ring_timeout'] = $school_bell_ring_timeout;
				$parameters['school_bell_min'] = $school_bell_min;
				$parameters['school_bell_hour'] = $school_bell_hour;
				$parameters['school_bell_dom'] = $school_bell_dom;
				$parameters['school_bell_mon'] = $school_bell_mon;
				$parameters['school_bell_dow'] = $school_bell_dow;
				$parameters['school_bell_timezone'] = $school_bell_timezone;
				$parameters['school_bell_enabled'] = $school_bell_enabled;
				$parameters['school_bell_description'] = $school_bell_description;
				$parameters['insert_user'] = $_SESSION['user_uuid'] ?? null;
				$parameters['update_user'] = $_SESSION['user_uuid'] ?? null;

			//execute the insert
				$database->execute($sql, $parameters);
				unset($sql, $parameters);

			//set the message and redirect the user
				$_SESSION['message'] = $text['message-add'];
				header('Location: school_bells.php'.($query_string ? '?'.$query_string : ''));
				return;

		} //if ($action == "add")

		if ($action == 'update') {

			//update the record, scoped to the current domain with bound parameters
				$sql = "UPDATE v_school_bells SET ";
				$sql .= "school_bell_name = :school_bell_name, ";
				$sql .= "school_bell_leg_a_type = :school_bell_leg_a_type, ";
				$sql .= "school_bell_leg_a_data = :school_bell_leg_a_data, ";
				$sql .= "school_bell_leg_b_type = :school_bell_leg_b_type, ";
				$sql .= "school_bell_leg_b_data = :school_bell_leg_b_data, ";
				$sql .= "school_bell_ring_timeout = :school_bell_ring_timeout, ";
				$sql .= "school_bell_min = :school_bell_min, ";
				$sql .= "school_bell_hour = :school_bell_hour, ";
				$sql .= "school_bell_dom = :school_bell_dom, ";
				$sql .= "school_bell_mon = :school_bell_mon, ";
				$sql .= "school_bell_dow = :school_bell_dow, ";
				$sql .= "school_bell_timezone = :school_bell_timezone, ";
				$sql .= "school_bell_enabled = :school_bell_enabled, ";
				$sql .= "school_bell_description = :school_bell_description, ";
				$sql .= "update_date = now(), ";
				$sql .= "update_user = :update_user ";
				$sql .= "WHERE domain_uuid = :domain_uuid ";
				$sql .= "AND school_bell_uuid = :school_bell_uuid";

			//set the parameters
				$parameters['school_bell_name'] = $school_bell_name;
				$parameters['school_bell_leg_a_type'] = $school_bell_leg_a_type;
				$parameters['school_bell_leg_a_data'] = $school_bell_leg_a_data;
				$parameters['school_bell_leg_b_type'] = $school_bell_leg_b_type;
				$parameters['school_bell_leg_b_data'] = $school_bell_leg_b_data;
				$parameters['school_bell_ring_timeout'] = $school_bell_ring_timeout;
				$parameters['school_bell_min'] = $school_bell_min;
				$parameters['school_bell_hour'] = $school_bell_hour;
				$parameters['school_bell_dom'] = $school_bell_dom;
				$parameters['school_bell_mon'] = $school_bell_mon;
				$parameters['school_bell_dow'] = $school_bell_dow;
				$parameters['school_bell_timezone'] = $school_bell_timezone;
				$parameters['school_bell_enabled'] = $school_bell_enabled;
				$parameters['school_bell_description'] = $school_bell_description;
				$parameters['update_user'] = $_SESSION['user_uuid'] ?? null;
				$parameters['domain_uuid'] = $domain_uuid;
				$parameters['school_bell_uuid'] = $school_bell_uuid;

			//execute the update
				$database->execute($sql, $parameters);
				unset($sql, $parameters);

			//set the message and redirect the user
				$_SESSION['message'] = $text['message-update'];
				header('Location: school_bells.php'.($query_string ? '?'.$query_string : ''));
				return;

		} //if ($action == "update")
	} //(count($_POST)>0 && strlen($_POST["persistformvar"]) == 0)

//pre-populate the form
	if (isset($_GET["id"]) && is_uuid($_GET["id"]) && (empty($_POST['persistformvar']) || $_POST['persistformvar'] != 'true')) {
		$school_bell_uuid = $_GET['id'];
		$sql = "SELECT * FROM v_school_bells";
		$sql .= " WHERE domain_uuid = :domain_uuid";
		$sql .= " AND school_bell_uuid = :school_bell_uuid";
		$sql .= " LIMIT 1";
		$parameters['domain_uuid'] = $domain_uuid;
		$parameters['school_bell_uuid'] = $school_bell_uuid;
		$row = $database->select($sql, $parameters, 'row');
		if (!empty($row)) {
			$school_bell_name = $row['school_bell_name'];
			$school_bell_leg_a_type = $row['school_bell_leg_a_type'];
			$school_bell_leg_a_data = $row['school_bell_leg_a_data'];
			$school_bell_leg_b_type = $row['school_bell_leg_b_type'];
			$school_bell_leg_b_data = $row['school_bell_leg_b_data'];
			$school_bell_ring_timeout = (int)$row['school_bell_ring_timeout'];
			$school_bell_min = (int)$row['school_bell_min'];
			$school_bell_hour = (int)$row['school_bell_hour'];
			$school_bell_dom = (int)$row['school_bell_dom'];
			$school_bell_mon = (int)$row['school_bell_mon'];
			$school_bell_dow = (int)$row['school_bell_dow'];
			$school_bell_timezone = $row['school_bell_timezone'];
			$school_bell_enabled = school_bells::is_enabled($row['school_bell_enabled'] ?? null) ? 'true' : 'false';
			$school_bell_description = $row['school_bell_description'];
		}
		unset($sql, $parameters, $row);
	}

//get the recordings
	$sql = "SELECT recording_name, recording_filename FROM v_recordings ";
	$sql .= "WHERE domain_uuid = :domain_uuid ";
	$sql .= "ORDER BY recording_name ASC";
	$parameters['domain_uuid'] = $domain_uuid;
	$recordings = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//build the schedule selectors, honoring the time format of the domain
	$school_bell_selector = new school_bell_selector(($settings->get('domain', 'time_format', '24h') == '12h'));

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//show the header
	$document['title'] = $action == 'add' ? $text['title-school_bells-add'] : $text['title-school_bells-edit'];
	require_once "resources/header.php";

//show the content
	echo "<form method='post' name='frm' id='frm' action=''>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".($action == 'add' ? $text['title-school_bells-add'] : $text['title-school_bells-edit'])."</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$settings->get('theme', 'button_icon_back'),'id'=>'btn_back','style'=>'margin-right: 15px;','link'=>'school_bells.php'.($query_string ? '?'.$query_string : '')]);
	if ($action == 'update' && permission_exists('school_bell_delete')) {
		echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'name'=>'btn_delete','style'=>'margin-right: 15px;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$settings->get('theme', 'button_icon_save'),'id'=>'btn_save','name'=>'action','value'=>'save']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if ($action == 'update' && permission_exists('school_bell_delete')) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'submit','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','name'=>'action','value'=>'delete','onclick'=>"modal_close();"])]);
	}

	echo "<div class='card'>\n";
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	//show the name
		echo "<tr>\n";
		echo "<td width='30%' class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-school_bell_name']."\n";
		echo "</td>\n";
		echo "<td width='70%' class='vtable' align='left'>\n";
		echo "	<input class='formfld' type='text' name='school_bell_name' maxlength='255' value=\"".escape($school_bell_name)."\" required='required'>\n";
		echo "<br />\n";
		echo $text['description-school_bell_name']."\n";
		echo "</td>\n";
		echo "</tr>\n";

	//show the called extension
		echo "<tr>\n";
		echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-school_bell_leg_a_data']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<input class='formfld' type='text' name='school_bell_leg_a_data' maxlength='255' value=\"".escape($school_bell_leg_a_data)."\" required='required'>\n";
		echo "<br />\n";
		echo $text['description-school_bell_leg_a_data']."\n";
		echo "</td>\n";
		echo "</tr>\n";

	//show the ring timeout
		echo "<tr>\n";
		echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-school_bell_ring_timeout']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<input class='formfld' type='number' name='school_bell_ring_timeout' min='0' max='3600' value=\"".escape($school_bell_ring_timeout)."\">\n";
		echo "<br />\n";
		echo $text['description-school_bell_ring_timeout']."\n";
		echo "</td>\n";
		echo "</tr>\n";

	//show the recording to play when the extension answers
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-school_bell_leg_b_data']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<select name='school_bell_leg_b_data' id='school_bell_leg_b_data' class='formfld'>\n";
		echo "		<option value=''></option>\n";
		$tmp_selected = false;
		if (!empty($recordings) && is_array($recordings)) {
			echo "	<optgroup label='".$text['label-recordings']."'>\n";
			foreach ($recordings as $row) {
				$full_path = $recordings_dir."/".$row['recording_filename'];
				if (strlen($school_bell_leg_b_data ?? '') > 0 && $school_bell_leg_b_data == $full_path) {
					$tmp_selected = true;
					echo "		<option value='".escape($full_path)."' selected='selected'>".escape($row['recording_name'])."</option>\n";
				}
				else if (strlen($school_bell_leg_b_data ?? '') > 0 && $school_bell_leg_b_data == $row['recording_filename']) {
					$tmp_selected = true;
					echo "		<option value='".escape($row['recording_filename'])."' selected='selected'>".escape($row['recording_name'])."</option>\n";
				}
				else {
					echo "		<option value='".escape($row['recording_filename'])."'>".escape($row['recording_name'])."</option>\n";
				}
			}
			echo "	</optgroup>\n";
		}

		//show the stored value when it is not in the list of recordings so it is never lost
			if (!$tmp_selected && if_group("superadmin") && strlen($school_bell_leg_b_data ?? '') > 0) {
				echo "	<optgroup label='".$text['label-selected']."'>\n";
				echo "		<option value='".escape($school_bell_leg_b_data)."' selected='selected'>".escape($school_bell_leg_b_data)."</option>\n";
				echo "	</optgroup>\n";
			}

		echo "	</select>\n";
		echo "<br />\n";
		echo $text['description-school_bell_leg_b_data']."\n";
		echo "</td>\n";
		echo "</tr>\n";

	//show the schedule divider
		echo "<tr><td colspan='2'><br /></td></tr>\n";

	//show the minute
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-school_bell_min']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo $school_bell_selector->draw_min('school_bell_min', $school_bell_min);
		echo "<br />\n";
		echo $text['description-school_bell_min']."\n";
		echo "</td>\n";
		echo "</tr>\n";

	//show the hour
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-school_bell_hour']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo $school_bell_selector->draw_hou('school_bell_hour', $school_bell_hour);
		echo "<br />\n";
		echo $text['description-school_bell_hour']."\n";
		echo "</td>\n";
		echo "</tr>\n";

	//show the day of the month
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-school_bell_dom']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo $school_bell_selector->draw_dom('school_bell_dom', $school_bell_dom);
		echo "<br />\n";
		echo $text['description-school_bell_dom']."\n";
		echo "</td>\n";
		echo "</tr>\n";

	//show the month
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-school_bell_mon']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo $school_bell_selector->draw_mon('school_bell_mon', $school_bell_mon);
		echo "<br />\n";
		echo $text['description-school_bell_mon']."\n";
		echo "</td>\n";
		echo "</tr>\n";

	//show the day of the week
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-school_bell_dow']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo $school_bell_selector->draw_dow('school_bell_dow', $school_bell_dow);
		echo "<br />\n";
		echo $text['description-school_bell_dow']."\n";
		echo "</td>\n";
		echo "</tr>\n";

	//show the timezone
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-school_bell_timezone']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<select class='formfld' name='school_bell_timezone'>\n";
		foreach ($timezone_identifiers_list as $timezone_identifier) {
			echo "		<option value='".escape($timezone_identifier)."' ".(($school_bell_timezone == $timezone_identifier) ? "selected='selected'" : null).">".escape($timezone_identifier)."</option>\n";
		}
		echo "	</select>\n";
		echo "<br />\n";
		echo $text['description-school_bell_timezone']."\n";
		echo "</td>\n";
		echo "</tr>\n";

	//show the schedule divider
		echo "<tr><td colspan='2'><br /></td></tr>\n";

	//show the enabled toggle
		echo "<tr>\n";
		echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-school_bell_enabled']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		if ($input_toggle_style_switch) {
			echo "	<span class='switch'>\n";
		}
		echo "		<select class='formfld' id='school_bell_enabled' name='school_bell_enabled'>\n";
		echo "			<option value='true' ".($school_bell_enabled == 'true' ? "selected='selected'" : null).">".$text['option-true']."</option>\n";
		echo "			<option value='false' ".($school_bell_enabled != 'true' ? "selected='selected'" : null).">".$text['option-false']."</option>\n";
		echo "		</select>\n";
		if ($input_toggle_style_switch) {
			echo "		<span class='slider'></span>\n";
			echo "	</span>\n";
		}
		echo "<br />\n";
		echo $text['description-school_bell_enabled']."\n";
		echo "</td>\n";
		echo "</tr>\n";

	//show the description
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-school_bell_description']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<input class='formfld' type='text' name='school_bell_description' maxlength='255' value=\"".escape($school_bell_description)."\">\n";
		echo "<br />\n";
		echo $text['description-school_bell_description']."\n";
		echo "</td>\n";
		echo "</tr>\n";

	echo "</table>\n";
	echo "</div>\n";
	echo "<br /><br />\n";

//show the schedule help
	// echo "<div class='card' style='opacity: 0.8;'>\n";
	// echo $text['description-school_bells_schedule_templates']."\n";
	// echo "</div>\n";
	// echo "<br /><br />\n";

	if (!empty($school_bell_uuid)) {
		echo "<input type='hidden' name='school_bell_uuid' value='".escape($school_bell_uuid)."'>\n";
	}
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>";

//include the footer
	require_once "resources/footer.php";

?>
