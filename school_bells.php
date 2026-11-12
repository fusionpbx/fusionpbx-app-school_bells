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
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('school_bell_view')) {
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

//build the query string, used to keep the current view when moving between pages
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

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//get the http post data
	if (!empty($_POST['school_bells'])) {
		$action = $_POST['action'];
		$school_bells = $_POST['school_bells'];
	}

//process the http post data by action
	if (!empty($action) && !empty($school_bells)) {
		$obj = new school_bells;
		switch ($action) {
			case 'copy':
				if (permission_exists('school_bell_add')) {
					$obj->copy($school_bells);
				}
				break;
			case 'toggle':
				if (permission_exists('school_bell_edit')) {
					$obj->toggle($school_bells);
				}
				break;
			case 'delete':
				if (permission_exists('school_bell_delete')) {
					$obj->delete($school_bells);
				}
				break;
		}

		header('Location: school_bells.php'.($query_string ? '?'.$query_string : ''));
		exit;
	}

//add the search string
	if (!empty($search)) {
		$sql_search = "(";
		$sql_search .= "	lower(school_bell_name) like :search ";
		$sql_search .= "	or lower(school_bell_leg_a_data) like :search ";
		$sql_search .= "	or lower(school_bell_description) like :search ";
		$sql_search .= ") ";
		$parameters['search'] = '%'.lower_case($search).'%';
	}

//get the count
	$sql = "select count(school_bell_uuid) from v_school_bells ";
	$sql .= "where domain_uuid = :domain_uuid ";
	if (isset($sql_search)) {
		$sql .= "and ".$sql_search;
	}
	$parameters['domain_uuid'] = $domain_uuid;
	$num_rows = $database->select($sql, $parameters, 'column');

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	list($paging_controls, $rows_per_page) = paging($num_rows, $query_string, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $query_string, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = "select school_bell_uuid, school_bell_name, school_bell_leg_a_data, ";
	$sql .= "school_bell_leg_b_data, cast(school_bell_enabled as text) as school_bell_enabled, ";
	$sql .= "school_bell_min, school_bell_hour, school_bell_dom, school_bell_mon, school_bell_dow, ";
	$sql .= "school_bell_timezone, school_bell_ring_timeout, school_bell_description ";
	$sql .= "from v_school_bells ";
	$sql .= "where domain_uuid = :domain_uuid ";
	if (isset($sql_search)) {
		$sql .= "and ".$sql_search;
	}
	$sql .= order_by($order_by, $order, 'school_bell_name', 'asc');
	$sql .= limit_offset($rows_per_page, $offset);
	$school_bells = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//create tokens, one for the list form bulk actions and one for the row delete links
	$object = new token;
	$list_token = $object->create($_SERVER['PHP_SELF']);
	$token = $object->create(dirname($_SERVER['PHP_SELF']) . '/school_bell_delete.php');

//include the header
	$document['title'] = $text['title-school_bells'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['title-school_bells']."</b><div class='count'>".number_format($num_rows)."</div></div>\n";
	echo "	<div class='actions'>\n";
	if (permission_exists('school_bell_add')) {
		echo button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'id'=>'btn_add','link'=>'school_bell_edit.php']);
	}
	if (permission_exists('school_bell_add') && $school_bells) {
		echo button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'id'=>'btn_copy','name'=>'btn_copy','style'=>'display: none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
	}
	if (permission_exists('school_bell_edit') && $school_bells) {
		echo button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display: none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
	}
	if (permission_exists('school_bell_delete') && $school_bells) {
		echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	echo "		<form id='form_search' class='inline' method='get'>\n";
	foreach ($param as $key => $value) {
		if (in_array($key, ['order_by', 'order'])) {
			echo "		<input type='hidden' name='".escape($key)."' value='".escape($value)."'>\n";
		}
	}
	echo "		<input type='text' class='txt list-search' name='search' id='search' value=\"".escape($search)."\" placeholder=\"".$text['label-search']."\">";
	echo button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);
	if ($paging_controls_mini != '') {
		echo "	<span style='margin-left: 15px;'>".$paging_controls_mini."</span>\n";
	}
	echo "		</form>\n";
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if (permission_exists('school_bell_add') && $school_bells) {
		echo modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
	}
	if (permission_exists('school_bell_edit') && $school_bells) {
		echo modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
	}
	if (permission_exists('school_bell_delete') && $school_bells) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

	echo $text['description-school_bells']."\n";
	echo "<br /><br />\n";

	echo "<div class='card'>\n";
	echo "<form id='form_list' method='post'>\n";
	echo "<input type='hidden' id='action' name='action' value=''>\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	if (permission_exists('school_bell_add') || permission_exists('school_bell_edit') || permission_exists('school_bell_delete')) {
		echo "	<th class='checkbox'>\n";
		echo "		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);' ".(empty($school_bells) ? "style='visibility: hidden;'" : null).">\n";
	echo "	</th>\n";
	}
	echo th_order_by('school_bell_name', $text['label-school_bell_name'], $order_by, $order, null, null, $param);
	echo th_order_by('school_bell_leg_a_data', $text['label-school_bell_leg_a_data'], $order_by, $order, null, null, $param);
	echo "	<th class='center nowrap'>".$text['label-school_bell_schedule']."</th>\n";
	echo th_order_by('school_bell_enabled', $text['label-school_bell_enabled'], $order_by, $order, null, "class='center'", $param);
	echo "	<th class='hide-sm-dn'>".$text['label-school_bell_description']."</th>\n";
	// if ((permission_exists('school_bell_edit') || permission_exists('school_bell_delete')) && $list_row_edit_button) {
	// 	echo "	<th class='action-button'>&nbsp;</th>\n";
	// }
	echo "</tr>\n";

	if (!empty($school_bells)) {
		$x = 0;
		foreach ($school_bells as $row) {
			//normalize the enabled value so it can be used as a language key
			$row['school_bell_enabled'] = school_bells::is_enabled($row['school_bell_enabled'] ?? null) ? 'true' : 'false';
			$list_row_url = '';
			if (permission_exists('school_bell_edit')) {
				$list_row_url = "school_bell_edit.php?id=".urlencode($row['school_bell_uuid']).($query_string ? '&'.$query_string : '');
			}
			echo "<tr class='list-row' href='".$list_row_url."'>\n";
			if (permission_exists('school_bell_add') || permission_exists('school_bell_edit') || permission_exists('school_bell_delete')) {
				echo "	<td class='checkbox'>\n";
				echo "		<input type='checkbox' name='school_bells[$x][checked]' id='checkbox_".$x."' value='true' onclick=\"checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }\">\n";
				echo "		<input type='hidden' name='school_bells[$x][uuid]' value='".escape($row['school_bell_uuid'])."' />\n";
				echo "	</td>\n";
			}
			echo "	<td>\n";
			if (permission_exists('school_bell_edit')) {
				echo "	<a href='".$list_row_url."' title=\"".$text['button-edit']."\">".escape($row['school_bell_name'])."</a>\n";
			}
			else {
				echo "	".escape($row['school_bell_name']);
			}
			echo "	</td>\n";
			echo "	<td>".escape($row['school_bell_leg_a_data'])."</td>\n";
			echo "	<td class='no-link center nowrap'>".school_bells::schedule_text($row)."</td>\n";
			if (permission_exists('school_bell_edit')) {
				echo "	<td class='no-link center'>\n";
				echo button::create(['type'=>'submit','class'=>'link','label'=>$text['label-'.$row['school_bell_enabled']],'title'=>$text['button-toggle'],'onclick'=>"list_self_check('checkbox_".$x."'); list_action_set('toggle'); list_form_submit('form_list')"]);
			}
			else {
				echo "	<td class='center'>";
				echo $text['label-'.$row['school_bell_enabled']];
			}
			echo "	</td>\n";
			echo "	<td class='description overflow hide-sm-dn'>".escape($row['school_bell_description'])."</td>\n";
			// if (permission_exists('school_bell_edit') || permission_exists('school_bell_delete')) {
			// 	echo "	<td class='action-button'>\n";
			// 	if (permission_exists('school_bell_edit')) {
			// 		echo button::create(['type'=>'button','title'=>$text['button-edit'],'icon'=>$settings->get('theme', 'button_icon_edit'),'link'=>$list_row_url]);
			// 	}
			// 	if (permission_exists('school_bell_delete')) {
			// 		echo "<a href='school_bell_delete.php?id=".$row['school_bell_uuid']."&".$token['name']."=".$token['hash']."' alt='".$text['button-delete']."' title='".$text['button-delete']."' onclick=\"return confirm('".$text['confirm-delete']."')\"><span class='fa-solid fa-trash'></span></a>";
			// 	}
			// 	echo "	</td>\n";
			// }
			echo "</tr>\n";
			$x++;
		}
		unset($school_bells);
	}

	echo "</table>\n";
	echo "<br />\n";
	echo "<div align='center'>".$paging_controls."</div>\n";
	echo "<input type='hidden' name='".$list_token['name']."' value='".$list_token['hash']."'>\n";
	echo "</form>\n";
	echo "</div>\n";

//include the footer
	require_once "resources/footer.php";

?>
