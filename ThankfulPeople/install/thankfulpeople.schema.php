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
			->Column('ObjectType', 'varchar(50)', false)
			->Column('ObjectID', 'int', false)
			->Column('InsertUserID', 'int', false, 'key')
			->Column('DateInserted', 'datetime', null, 'key')
			->Engine('InnoDB')
			->Set(false, false);

		$this->CreateIndex('ThanksLog', 'IX_Objects', array('ObjectType', 'ObjectID'));
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
	 * Creates a view that returns the amount of thanks received by a user.
	 */
	protected function create_userreceivedthanks_view() {
		$Px = $this->Px;
		$Sql = "
			SELECT
				TL.`UserID`
				,TL.`ObjectType`
				-- ,TL.`ObjectID`
				,COUNT(TL.`ThankID`) AS ThanksCount
			FROM
				{$Px}ThanksLog TL
				-- JOIN
				-- {$Px}User U ON
				-- 	(U.UserID = TL.UserID)
			GROUP BY
				TL.`UserID`
				,TL.`ObjectType`
		";
		$this->Construct->View('v_TP_UserReceivedThanks', $Sql);
	}

	/**
	 * Creates a view that returns the amount of thanks received by an object.
	 */
	protected function create_objectreceivedthanks_view() {
		$Px = $this->Px;
		$Sql = "
			SELECT
				TL.`ObjectType`
				,TL.`ObjectID`
				,COUNT(TL.`ThankID`) AS ThanksCount
			FROM
				{$Px}ThanksLog TL
			GROUP BY
				TL.`ObjectType`
				,TL.`ObjectID`
		";
		$this->Construct->View('v_TP_ObjectReceivedThanks', $Sql);
	}

	/**
	 * Create all the Database Objects in the appropriate order.
	 */
	protected function CreateObjects() {
		$this->create_thankslog_table();
		$this->alter_users_table();
		$this->create_userreceivedthanks_view();
		$this->create_objectreceivedthanks_view();
	}

	/**
	 * Delete the Database Objects.
	 */
	protected function DropObjects() {
		$this->DropView('v_TP_UserReceivedThanks');
		$this->DropView('v_TP_ObjectReceivedThanks');

		$this->DropTable('ThanksLog');
	}
}
