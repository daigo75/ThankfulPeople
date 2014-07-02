<?php
namespace Aelia\Plugins\ThankfulPeople;
if (!defined('APPLICATION')) exit();

use Gdn;

class DiscussionModel extends \DiscussionModel {
	/**
	 * Updates the amount of thanks received by a Discussion.
	 *
	 * @param int DiscussionID The target discussion's ID.
	 * @param int Value The value to add (when positive) or subtract (when
	 * negative).
	 */
	public function UpdateReceivedThanksCount($DiscussionID, $Value) {
		$Value = (int)$Value;
		// No need to run a query for a zero value
		if($Value == 0) {
			return;
		}
		$Value = ((int)$Value < 0) ? "- $Value" : "+ $Value";

		Gdn::SQL()
			->Update('Discussion')
			->Set('ReceivedThanksCount', 'ReceivedThanksCount' . $Value, false)
			->Where('DiscussionID', $DiscussionID)
			->Put();
	}
}
