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

/**
 * Builds the Minute, Hour, Day of month, Month and Day of week selects used by
 * the school bell edit form. Each select accepts one value only; the value -1
 * is drawn as * and means every value for that field.
 */
class school_bell_selector {

	/**
	 * Option lists keyed by the stored value.
	 * @var array
	 */
	private $min = [];
	private $hou = [];
	private $dom = [];
	private $mon = [];
	private $dow = [];

	/**
	 * Initializes the option lists.
	 *
	 * @param bool $time12h Draw the hours as 12 hour clock values with AM/PM.
	 */
	public function __construct($time12h = false) {

		//minute
			$this->min[-1] = '*';
			for ($i = 0; $i <= 59; $i++) {
				$this->min[$i] = sprintf('%02d', $i);
			}

		//hour
			$this->hou[-1] = '*';
			if ($time12h) {
				$this->hou[0] = '12 AM';
				for ($i = 1; $i <= 12; $i++) {
					$this->hou[$i] = sprintf('%02d', $i).' AM';
				}
				for ($i = 13; $i <= 23; $i++) {
					$this->hou[$i] = sprintf('%02d', $i - 12).' PM';
				}
			}
			else {
				for ($i = 0; $i <= 23; $i++) {
					$this->hou[$i] = sprintf('%02d', $i);
				}
			}

		//day of the month
			$this->dom[-1] = '*';
			for ($i = 1; $i <= 31; $i++) {
				$this->dom[$i] = sprintf('%02d', $i);
			}

		//month
			$this->mon[-1] = '*';
			$months = [
				1 => 'January', 'February', 'March', 'April', 'May', 'June',
				'July', 'August', 'September', 'October', 'November', 'December'];
			foreach ($months as $i => $name) {
				$this->mon[$i] = $name;
			}

		//day of the week, Sunday first when the 12 hour clock is used
			$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
			if ($time12h) {
				$this->dow[-1] = '*';
				foreach ($days as $i => $name) {
					$this->dow[$i] = $name;
				}
			}
			else {
				$this->dow[-1] = '*';
				for ($i = 1; $i <= 6; $i++) {
					$this->dow[$i] = $days[$i];
				}
				$this->dow[0] = $days[0];
			}
	}

	/**
	 * Draws a select list.
	 *
	 * @param string   $name     The name and id of the field.
	 * @param array    $options  The option list keyed by value.
	 * @param mixed    $selected The value to mark as selected.
	 *
	 * @return string The select element.
	 */
	private function draw_options(string $name, array $options, $selected = ''): string {
		$select = "<select name='".escape($name)."' id='".escape($name)."' class='formfld'>\n";
		foreach ($options as $value => $label) {
			$select .= "	<option value='".escape($value)."'".(($selected !== '' && (string)$selected === (string)$value) ? " selected='selected'" : null).">".escape($label)."</option>\n";
		}
		$select .= "</select>";

		return $select;
	}

	public function draw_min(string $name, $selected = ''): string {
		return $this->draw_options($name, $this->min, $selected);
	}

	public function draw_hou(string $name, $selected = ''): string {
		return $this->draw_options($name, $this->hou, $selected);
	}

	public function draw_dom(string $name, $selected = ''): string {
		return $this->draw_options($name, $this->dom, $selected);
	}

	public function draw_mon(string $name, $selected = ''): string {
		return $this->draw_options($name, $this->mon, $selected);
	}

	public function draw_dow(string $name, $selected = ''): string {
		return $this->draw_options($name, $this->dow, $selected);
	}

}
