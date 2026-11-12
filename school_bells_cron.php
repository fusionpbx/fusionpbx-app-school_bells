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
	Portions created by the Initial Developer are Copyright (C) 2018-2026
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
	Igor Olhovskiy <igorolhovskiy@gmail.com>
*/

//restrict to command line only
	if (php_sapi_name() !== 'cli') {
		die('access denied');
	}

//includes files
	$document_root = dirname(__DIR__, 2);
	set_include_path($document_root);
	$_SERVER["DOCUMENT_ROOT"] = $document_root;
	require_once "resources/require.php";

//show debug, run with: php school_bells_cron.php debug
	$debug = !empty($_SERVER['argv'][1]) && $_SERVER['argv'][1] === 'debug';

//cannot run more than 55 seconds, cron runs once a minute
	set_time_limit(55);

//set the current time
	$current_timestamp = time();
	$current_minute = (int)date('i', $current_timestamp);

//get the bells that can ring this minute, disabled bells are not selected
	$sql = "select d.domain_name as context, ";
	$sql .= "b.domain_uuid, ";
	$sql .= "b.school_bell_leg_a_data as extension, ";
	$sql .= "b.school_bell_leg_b_type as full_path, ";
	$sql .= "b.school_bell_ring_timeout as ring_timeout, ";
	$sql .= "b.school_bell_min as min, ";
	$sql .= "b.school_bell_hour as hour, ";
	$sql .= "b.school_bell_dom as dom, ";
	$sql .= "b.school_bell_mon as mon, ";
	$sql .= "b.school_bell_dow as dow, ";
	$sql .= "b.school_bell_timezone as timezone ";
	$sql .= "from v_school_bells as b ";
	$sql .= "join v_domains as d on d.domain_uuid = b.domain_uuid ";
	$sql .= "where (b.school_bell_min = :current_minute or b.school_bell_min = -1) ";
	$sql .= "and (b.school_bell_enabled is null or lower(cast(b.school_bell_enabled as text)) not in ('false', 'f', '0', 'no', 'off')) ";
	$parameters['current_minute'] = $current_minute;
	$school_bells = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//nothing to do
	if (empty($school_bells)) {
		echo "no bells\n";
		return;
	}

//connect to the event socket
	$event_socket = event_socket::create();
	if (!$event_socket->connected()) {
		die("Cannot connect to Event socket\n");
	}

//ring each bell that is due
	foreach ($school_bells as $school_bell) {

		//check hour, day of month, month and day of week in the timezone of the bell
		if (!school_bells::should_ring($school_bell, $current_timestamp, (string)$current_minute)) {
			if ($debug) {
				echo "Schedule is not matched\n";
			}
			continue;
		}

		//sanitize the values used to build the command
		$context = preg_replace('/[^a-zA-Z0-9._-]/', '', $school_bell['context']);
		$domain_uuid = (is_uuid($school_bell['domain_uuid'])) ? $school_bell['domain_uuid'] : null;
		$extension = preg_replace('/[^0-9a-zA-Z_*#+@.-]/', '', $school_bell['extension']);
		$full_path = preg_replace('/[^a-zA-Z0-9._\/-]/', '', $school_bell['full_path']);

		//skip the bell when the values are not valid
		if ($domain_uuid === null || strlen($context) == 0 || strlen($extension) == 0 || strpos($full_path, '..') !== false) {
			if ($debug) {
				echo "Invalid bell parameters\n";
			}
			continue;
		}

		//set the ring timeout, keeping it within a range that cannot overlap the next cron run
		$school_bell_ring_timeout = (int)$school_bell['ring_timeout'];
		if ($school_bell_ring_timeout > 60 && $school_bell['min'] == "-1") {
			$school_bell_ring_timeout = 55;
		}
		$school_bell_ring_timeout = ($school_bell_ring_timeout < 1) ? 5 : $school_bell_ring_timeout;

		//build the command, the call is placed into the domain dialplan with loopback
		$switch_cmd = "bgapi ";
		$switch_cmd .= "originate {ignore_early_media=true,";
		$switch_cmd .= "hangup_after_bridge=true,";
		$switch_cmd .= "domain_name=".$context.",";
		$switch_cmd .= "domain_uuid=".$domain_uuid.",";
		$switch_cmd .= "call_timeout=".$school_bell_ring_timeout."}";
		$switch_cmd .= "loopback/".$extension."/".$context;
		$switch_cmd .= ($full_path !== '') ? " &playback(".$full_path.")" : "";
		if ($debug) {
			echo $switch_cmd."\n";
		}

		//send the command
		event_socket_request($event_socket, $switch_cmd);
	}

?>
