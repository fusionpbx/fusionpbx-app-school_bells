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
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('school_bell_delete')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//keep the current view of the list page when returning
	$page = is_numeric($_GET['page'] ?? '') ? max(0, (int)$_GET['page']) : 0;
	$order_by = preg_replace('#[^a-zA-Z0-9_\-]#', '', $_GET['order_by'] ?? '');
	$order = ($_GET['order'] ?? '') === 'desc' ? 'desc' : 'asc';
	$search = $_GET['search'] ?? '';

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

//delete the data
	if (isset($_GET["id"]) && is_uuid($_GET["id"])) {

		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: school_bells.php');
				exit;
			}

		//get the id
			$id = $_GET["id"];

		//verify the record belongs to the current domain
			$sql = "select school_bell_uuid from v_school_bells ";
			$sql .= "where school_bell_uuid = :school_bell_uuid ";
			$sql .= "and domain_uuid = :domain_uuid ";
			$parameters['school_bell_uuid'] = $id;
			$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
			$row = $database->select($sql, $parameters, 'row');
			unset($sql, $parameters);
			if (empty($row)) {
				header('Location: school_bells.php'.($query_string ? '?'.$query_string : ''));
				exit;
			}

		//delete the school bell
			$sql = "delete from v_school_bells ";
			$sql .= "where school_bell_uuid = :school_bell_uuid ";
			$sql .= "and domain_uuid = :domain_uuid ";
			$parameters['school_bell_uuid'] = $id;
			$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
			$database->execute($sql, $parameters);
			unset($sql, $parameters);

		//set the message
			message::add($text['message-delete']);
	}

//redirect the user
	header('Location: school_bells.php'.($query_string ? '?'.$query_string : ''));

?>
