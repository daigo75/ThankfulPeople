<?php
namespace Aelia\Plugins\ThankfulPeople;
if (!defined('APPLICATION')) exit();

use Gdn;

/**
 * Extends the base UserModel by adding functions to handle the thanks received
 * by a user.
 */
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

	/**
	 * Recalculates the amount of thanks received by a User.
	 *
	 * @return int
	 */
	public function RecalculateReceivedThanksCount() {
		$Px = $this->Px;
		$UpdateSQL = "
			UPDATE
				{$Px}User U
			LEFT JOIN
				{$Px}v_TP_UserReceivedThanks URT ON
					(URT.UserID = U.UserID)
			SET
				(U.ReceivedThanksCount = URT.ThanksCount)
		";

		$Result = $this->SQL->Query($UpdateSQL, null);

		// Return the amount of affected rows
		return $Result->PDOStatement()->rowCount();
	}
}
