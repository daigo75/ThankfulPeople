<?php
namespace Aelia\Plugins\ThankfulPeople;
if (!defined('APPLICATION')) exit();

use Gdn;

/**
 * Extends the base DiscussionModel by adding functions to handle the thanks received
 * by a discussion.
 */
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

	/**
	 * Returns the amount of thanks received by a discussion.
	 *
	 * @param int DiscussionID The discussion ID.
	 * @return int
	 */
	public function GetThanksCount($DiscussionID) {
		return $this->SQL
			->Select('D.ReceivedThanksCount')
			->From('Discussion D')
			->Where('D.DiscussionID', $DiscussionID)
			->Get()
			->Value('ReceivedThanksCount');
	}
}
