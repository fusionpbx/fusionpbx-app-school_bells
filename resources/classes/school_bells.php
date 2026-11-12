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

//define the school bells class
	class school_bells {

		/**
		 * declare constant variables
		 */
		const app_name = 'school_bells';
		const app_uuid = 'aadeda26-f3b5-4114-96f4-a7591f069fdf';

		/**
		 * Set in the constructor. Must be a database object and cannot be null.
		 * @var database Database Object
		 */
		private $database;

		/**
		 * Settings object set in the constructor. Must be a settings object and cannot be null.
		 * @var settings Settings Object
		 */
		private $settings;

		/**
		 * User UUID set in the constructor. This can be passed in through the $setting_array associative array or set in the session global array
		 * @var string
		 */
		private $user_uuid;

		/**
		 * Domain UUID set in the constructor. This can be passed in through the $setting_array associative array or set in the session global array
		 * @var string
		 */
		private $domain_uuid;

		/**
		 * declare private variables
		 */
		private $permission_prefix;
		private $list_page;
		private $table;
		private $uuid_prefix;
		private $toggle_field;
		private $toggle_values;

		/**
		 * Initializes the object with settings and default values.
		 *
		 * @param array $setting_array Associative array of setting keys to their respective values (optional)
		 */
		public function __construct(array $setting_array = []) {
			//set domain and user UUIDs
			$this->domain_uuid = $setting_array['domain_uuid'] ?? $_SESSION['domain_uuid'] ?? '';
			$this->user_uuid = $setting_array['user_uuid'] ?? $_SESSION['user_uuid'] ?? '';

			//set objects
			$config = $setting_array['config'] ?? config::load();
			$this->database = $setting_array['database'] ?? database::new(['config' => $config]);
			$this->settings = $setting_array['settings'] ?? new settings(['database' => $this->database, 'domain_uuid' => $this->domain_uuid, 'user_uuid' => $this->user_uuid]);

			//assign private variables
			$this->permission_prefix = 'school_bell_';
			$this->list_page = 'school_bells.php';
			$this->table = 'school_bells';
			$this->uuid_prefix = 'school_bell_';
			$this->toggle_field = 'school_bell_enabled';
			$this->toggle_values = ['true','false'];

		}

		/**
		 * Deletes one or multiple records from the school bells table.
		 *
		 * @param array $records An array of records to delete, where each record is an associative array
		 *                       containing 'uuid' and 'checked' keys. The 'checked' value indicates
		 *                       whether the corresponding checkbox was checked for deletion.
		 *
		 * @return void No return value; this method modifies the database state and sets a message.
		 */
		public function delete($records) {
			if (permission_exists($this->permission_prefix.'delete')) {

				//add multi-lingual support
					$language = new text;
					$text = $language->get();

				//validate the token
					$token = new token;
					if (!$token->validate($_SERVER['PHP_SELF'])) {
						message::add($text['message-invalid_token'],'negative');
						header('Location: '.$this->list_page);
						exit;
					}

				//delete multiple records
					if (is_array($records) && @sizeof($records) != 0) {

						//build the delete array
							foreach ($records as $x => $record) {
								if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['uuid'])) {
									$array[$this->table][$x][$this->uuid_prefix.'uuid'] = $record['uuid'];
									$array[$this->table][$x]['domain_uuid'] = $this->domain_uuid;
								}
							}

						//delete the checked rows
							if (is_array($array) && @sizeof($array) != 0) {

								//execute delete
									$this->database->delete($array);
									unset($array);

								//set message
									message::add($text['message-delete']);
							}
							unset($records);
					}
			}
		}

		/**
		 * Toggles the enabled state of the specified records.
		 *
		 * @param array $records An array of records to toggle, where each record is an associative array
		 *                       containing 'uuid' and 'checked' keys.
		 *
		 * @return void No return value; this method modifies the database state and sets a message.
		 */
		public function toggle($records) {
			if (permission_exists($this->permission_prefix.'edit')) {

				//add multi-lingual support
					$language = new text;
					$text = $language->get();

				//validate the token
					$token = new token;
					if (!$token->validate($_SERVER['PHP_SELF'])) {
						message::add($text['message-invalid_token'],'negative');
						header('Location: '.$this->list_page);
						exit;
					}

				//toggle the checked records
					if (is_array($records) && @sizeof($records) != 0) {

						//get current toggle state
							foreach ($records as $x => $record) {
								if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['uuid'])) {
									$uuids[] = "'".$record['uuid']."'";
								}
							}
							if (is_array($uuids) && @sizeof($uuids) != 0) {
								$sql = "select ".$this->uuid_prefix."uuid as uuid, ".$this->toggle_field." as toggle from v_".$this->table." ";
								$sql .= "where domain_uuid = :domain_uuid ";
								$sql .= "and ".$this->uuid_prefix."uuid in (".implode(', ', $uuids).") ";
								$parameters['domain_uuid'] = $this->domain_uuid;
								$rows = $this->database->select($sql, $parameters, 'all');
								if (is_array($rows) && @sizeof($rows) != 0) {
									foreach ($rows as $row) {
										$states[$row['uuid']] = $row['toggle'];
									}
								}
								unset($sql, $parameters, $rows, $row);
							}

						//build update array
							$x = 0;
							foreach ($states as $uuid => $state) {
								$array[$this->table][$x][$this->uuid_prefix.'uuid'] = $uuid;
								$array[$this->table][$x][$this->toggle_field] = self::is_enabled($state) ? $this->toggle_values[1] : $this->toggle_values[0];
								$x++;
							}

						//save the changes
							if (is_array($array) && @sizeof($array) != 0) {

								//save the array
									$this->database->save($array);
									unset($array);

								//set message
									message::add($text['message-toggle']);
							}
							unset($records, $states);
					}
			}
		}

		/**
		 * Copies one or more records.
		 *
		 * @param array $records An array of records to copy, where each record is an associative array
		 *                       containing 'uuid' and 'checked' keys.
		 *
		 * @return void No return value; this method modifies the database state and sets a message.
		 */
		public function copy($records) {
			if (permission_exists($this->permission_prefix.'add')) {

				//add multi-lingual support
					$language = new text;
					$text = $language->get();

				//validate the token
					$token = new token;
					if (!$token->validate($_SERVER['PHP_SELF'])) {
						message::add($text['message-invalid_token'],'negative');
						header('Location: '.$this->list_page);
						exit;
					}

				//copy the checked records
					if (is_array($records) && @sizeof($records) != 0) {

						//get checked records
							foreach ($records as $x => $record) {
								if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['uuid'])) {
									$uuids[] = "'".$record['uuid']."'";
								}
							}

						//create insert array from existing data
							if (is_array($uuids) && @sizeof($uuids) != 0) {
								$sql = "select * from v_".$this->table." ";
								$sql .= "where domain_uuid = :domain_uuid ";
								$sql .= "and ".$this->uuid_prefix."uuid in (".implode(', ', $uuids).") ";
								$parameters['domain_uuid'] = $this->domain_uuid;
								$rows = $this->database->select($sql, $parameters, 'all');
								if (is_array($rows) && @sizeof($rows) != 0) {
									foreach ($rows as $x => $row) {

										//convert boolean values to a string
											foreach ($row as $key => $value) {
												if (gettype($value) == 'boolean') {
													$row[$key] = $value ? 'true' : 'false';
												}
											}

										//copy data
											$array[$this->table][$x] = $row;

										//overwrite the uuid so a new record is created and add
										//the copy suffix to the name so the copies can be told apart
											$array[$this->table][$x][$this->uuid_prefix.'uuid'] = uuid();
											$array[$this->table][$x][$this->uuid_prefix.'name'] = trim($row[$this->uuid_prefix.'name'].' ('.$text['label-copy'].')');

									}
								}
								unset($sql, $parameters, $rows, $row);
							}

						//save the changes and set the message
							if (is_array($array) && @sizeof($array) != 0) {

								//save the array
									$this->database->save($array);
									unset($array);

								//set message
									message::add($text['message-copy']);

							}
							unset($records);
					}
			}
		}

		/**
		 * Saves a school bell record using the database class so that insert and update
		 * audit fields are maintained consistently with the rest of FusionPBX.
		 *
		 * @param array $bell An associative array of school bell field names and values. The
		 *                    school_bell_uuid is generated when it is not a valid UUID.
		 *
		 * @return string|false Returns the school bell uuid on success, false otherwise.
		 */
		public function save(array $bell) {
			//set defaults
				$bell['domain_uuid'] = $this->domain_uuid;
				if (empty($bell[$this->uuid_prefix.'uuid']) || !is_uuid($bell[$this->uuid_prefix.'uuid'])) {
					$bell[$this->uuid_prefix.'uuid'] = uuid();
				}

			//prepare the array
				$array[$this->table][0] = $bell;

			//save to the database
				$this->database->save($array);
				$message = $this->database->message;
				unset($array);

			//return the result
				if (!empty($message['detail']['result'])) {
					return $bell[$this->uuid_prefix.'uuid'];
				}

			return false;
		}

		/**
		 * Normalizes the enabled field. Databases return booleans differently, PostgreSQL
		 * returns 'true'/'false', SQLite can return '1'/'0', and older rows can hold any
		 * text. An empty value is treated as enabled so a bell never silently stops ringing.
		 *
		 * @param mixed $value The value stored in school_bell_enabled.
		 *
		 * @return bool True when the bell is enabled.
		 */
		public static function is_enabled($value): bool {
			if ($value === null || $value === '') {
				return true;
			}
			if (is_bool($value)) {
				return $value;
			}
			return !in_array(strtolower(trim((string)$value)), ['false', 'f', '0', 'no', 'off'], true);
		}

		/**
		 * Formats the schedule fields of a bell into a human readable cron style string.
		 * A field stored as -1 (the interface shows it as *) is drawn as *.
		 *
		 * @param array $bell An associative array with school_bell_min, school_bell_hour,
		 *                    school_bell_dom, school_bell_mon and school_bell_dow keys.
		 *
		 * @return string The schedule, for example "30 8 * * *" or "* * * * *".
		 */
		public static function schedule_text(array $bell): string {
			$fields = [
				'school_bell_min',
				'school_bell_hour',
				'school_bell_dom',
				'school_bell_mon',
				'school_bell_dow',
			];

			$parts = [];
			foreach ($fields as $field) {
				$value = $bell[$field] ?? -1;
				$parts[] = (is_numeric($value) && (int)$value != -1) ? (string)(int)$value : '*';
			}

			return implode(' ', $parts);
		}

		/**
		 * Determines whether a bell should ring at the given timestamp. Fields set to -1 (the
		 * interface shows them as *) always match. All fields must match, cron style AND logic,
		 * evaluated in the timezone stored with the bell.
		 *
		 * @param array  $bell     An associative array with min, hour, dom, mon, dow and timezone keys.
		 * @param int    $timestamp The timestamp to compare against, defaults to now.
		 * @param string $minute    The minute of the hour to match, defaults to the timestamp minute.
		 *
		 * @return bool True when every schedule field matches.
		 */
		public static function should_ring(array $bell, ?int $timestamp = null, ?string $minute = null): bool {
			$timestamp = $timestamp ?? time();

			//set the timezone of the bell
				$timezone = (string)($bell['timezone'] ?? '');
				if (!in_array($timezone, timezone_identifiers_list(), true)) {
					$timezone = date_default_timezone_get();
				}

			//get the current values in the timezone of the bell
				try {
					//the timezone passed to the constructor is ignored for unix timestamps, set it after creation
					$date = (new DateTimeImmutable('@'.$timestamp))->setTimezone(new DateTimeZone($timezone));
				} catch (Exception $e) {
					$date = new DateTimeImmutable('@'.$timestamp);
				}
				$minute = ($minute !== null && is_numeric($minute)) ? (int)$minute : (int)$date->format('i');
				$hour = (int)$date->format('G');
				$day_of_month = (int)$date->format('j');
				$month = (int)$date->format('n');
				$day_of_week = (int)$date->format('w');

			//compare each field, -1 means every value
				$fields = [
					'min' => $minute,
					'hour' => $hour,
					'dom' => $day_of_month,
					'mon' => $month,
					'dow' => $day_of_week,
				];
				foreach ($fields as $field => $current) {
					$value = is_numeric($bell[$field] ?? null) ? (int)$bell[$field] : -1;
					if ($value != -1 && $value != $current) {
						return false;
					}
				}

			return true;
		}

	}
