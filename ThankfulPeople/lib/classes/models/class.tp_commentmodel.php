<?php
namespace Aelia\Plugins\ThankfulPeople;
if (!defined('APPLICATION')) exit();

use Gdn;

/**
 * Extends the base CommentModel by adding functions to handle the thanks received
 * by a comment.
 */
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

	/**
	 * Returns the amount of thanks received by a comment.
	 *
	 * @param int DiscussionID The discussion ID.
	 * @return int
	 */
	public function GetThanksCount($CommentID) {
		return $this->SQL
			->Select('C.ReceivedThanksCount')
			->From('Comment C')
			->Where('C.CommentID', $CommentID)
			->Get()
			->Value('ReceivedThanksCount');
	}
}
