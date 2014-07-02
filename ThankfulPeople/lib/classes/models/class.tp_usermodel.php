<?php
namespace Aelia\Plugins\ThankfulPeople;
if (!defined('APPLICATION')) exit();

use Gdn;

class UserModel extends \UserModel {
	/**
	 * Updates the amount of thanks received by a User.
	 *
	 * @param int UserID The target user's ID.
	 * @param int Value The value to add (when positive) or subtract (when
	 * negative).
	 */
	public function UpdateReceivedThanksCount($UserID, $Value) {
		$Value = (int)$Value;
		// No need to run a query for a zero value
		if($Value == 0) {
			return;
		}

		$Value = ((int)$Value < 0) ? "- $Value" : "+ $Value";
		Gdn::SQL()
			->Update('User')
			->Set('ReceivedThanksCount', 'ReceivedThanksCount' . $Value, false)
			->Where('UserID', $UserID)
			->Put();
	}
}