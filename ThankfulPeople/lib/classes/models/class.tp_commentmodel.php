<?php
namespace Aelia\Plugins\ThankfulPeople;
if (!defined('APPLICATION')) exit();

use Gdn;

class CommentModel extends \CommentModel {
	/**
	 * Updates the amount of thanks received by a Comment.
	 *
	 * @param int CommentID The target comment's ID.
	 * @param int Value The value to add (when positive) or subtract (when
	 * negative).
	 */
	public function UpdateReceivedThanksCount($CommentID, $Value) {
		$Value = (int)$Value;
		// No need to run a query for a zero value
		if($Value == 0) {
			return;
		}
		$Value = ((int)$Value < 0) ? "- $Value" : "+ $Value";


		Gdn::SQL()
			->Update('Comment')
			->Set('ReceivedThanksCount', 'ReceivedThanksCount' . $Value, false)
			->Where('CommentID', $CommentID)
			->Put();
	}
}
