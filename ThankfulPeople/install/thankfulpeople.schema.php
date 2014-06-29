<?php
namespace Aelia\Plugins\ThankfulPeople;
if(!defined('APPLICATION')) exit();

use \Gdn;

class Schema extends \Aelia\Schema {
	/**
	 * Create the table which will store the list of configured Award Classes.
	 */
	protected function create_thankslog_table() {
		Gdn::Structure()
			->Table('ThanksLog')
			->PrimaryKey('ThankID')
			->Column('UserID', 'int', false, 'key')
			->Column('CommentID', 'int', null, 'key')
			->Column('DiscussionID', 'int', null, 'key')
			->Column('InsertUserID', 'int', false, 'key')
			->Column('DateInserted', 'datetime', 'key')
			->Engine('InnoDB')
			->Set(false, false);

		$this->AddForeignKey('ThanksLog', 'FK_Thanks_Users', array('UserID'),
												 'User', array('UserID'));
	}

	protected function alter_users_table() {
		Gdn::Structure()
			->Table('User')
			->Column('ReceivedThankCount', 'int', 0)
			->Set(false, false);
	}

	/**
	 * Creates a View that returns ThankFrank referral pages.
	 */
	protected function create_referralpages_view() {
		$Px = $this->Px;
		$Sql = "
			SELECT
				RP.`ReferralPageID`
				,RP.`UrlID`
				,RP.`UserID`
				,RP.`Recipient`
				,RP.`Title`
				,RP.`Recommendations`
				,RP.`DateInserted`
				,RP.`InsertUserID`
				,RP.`DateUpdated`
				,RP.`UpdateUserID`
				,U.`Name` AS UserName
			FROM
				{$Px}TF_ReferralPages RP
				JOIN
				{$Px}User U ON
					(U.UserID = RP.UserID)
		";
		$this->Construct->View('v_TF_ReferralPages', $Sql);
	}

	protected function populate_transactionstatuses_table() {
		$TransactionStatuses = array(
			TransDefs::STATUS_APPROVED => T('Approved'),
			TransDefs::STATUS_PENDING => T('Pending'),
			TransDefs::STATUS_DECLINED => T('Declined'),
		);


		$TransactionStatusesModel = new \ThankFrank\TransactionStatusesModel();

		foreach($TransactionStatuses as $Code => $Description) {
			$TransactionStatusesModel->Save(array(
				'TransactionStatusCode' => $Code,
				'Description' => $Description,
			));
		}
	}

	/**
	 * Create all the Database Objects in the appropriate order.
	 */
	protected function CreateObjects() {
		$this->create_thankslog_table();
		$this->alter_users_table();
	}

	/**
	 * Delete the Database Objects.
	 */
	protected function DropObjects() {
		$this->DropTable('ThanksLog');
	}
}
