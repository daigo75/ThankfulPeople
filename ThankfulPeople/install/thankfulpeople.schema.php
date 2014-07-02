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
			->Column('InsertUserID', 'int', null, 'key')
			->Column('DateInserted', 'datetime', null, 'key')
			->Engine('InnoDB')
			->Set(false, false);

		$this->CreateIndex('ThanksLog', 'IX_Objects', array('ObjectType', 'ObjectID'));
		$this->AddForeignKey('ThanksLog', 'FK_Thanks_Users', array('UserID'),
												 'User', array('UserID'));
	}

	/**
	 * Adds a "thanks count" field to the specified table.
	 *
	 * @param string Table The target table.
	 */
	protected function add_thanks_count_field($Table) {
		Gdn::Structure()
			->Table($Table)
			->Column('ReceivedThanksCount', 'int', 0)
			->Set(false, false);
	}

	/**
	 * Adds a "thanks counter" field to several tables.
	 */
	protected function add_thanks_counter_fields() {
		$this->add_thanks_count_field('User');
		$this->add_thanks_count_field('Discussion');
		$this->add_thanks_count_field('Comment');
	}

	/**
	 * Creates a view to access the ThanksLog table.
	 *
	 * What's the purpose of this view?
	 * This view comes useful when joining with tables that have fields with the
	 * same names, such as Discussion.UserID. Some plugins add their own JOIN and
	 * WHERE clauses, but they don't specify a table prefix. That is, they can add
	 * a clauses such as "WHERE (UserID = 123)". If joining with ThanksLog, such
	 * clause becomes ambiguous, as both Discussion and ThanksLog table contain
	 * a UserID field, and the query fails.
	 *
	 * Why not renaming the ThanksLog.UserID field?
	 * That was eveluated, but other applications and plugins already use such
	 * field. Using a view when joining with ThanksLog allows to alias the field,
	 * preventing the issue described above, without having to touch 3rd party
	 * entities.
	 */
	protected function create_thankslog_view() {
		$Px = $this->Px;
		$Sql = "
			SELECT
				TL.`ThankID`
				,TL.`UserID` as RecipientUserID
				,TL.`ObjectType`
				,TL.`ObjectID`
				,TL.`DateInserted`
				,TL.`InsertUserID`
			FROM
				{$Px}ThanksLog TL
		";
		$this->Construct->View('v_TP_ThanksLog', $Sql);
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
		$this->add_thanks_counter_fields();

		$this->create_userreceivedthanks_view();
		$this->create_objectreceivedthanks_view();
		$this->create_thankslog_view();
	}

	/**
	 * Delete the Database Objects.
	 */
	protected function DropObjects() {
		$this->DropView('v_TP_UserReceivedThanks');
		$this->DropView('v_TP_ObjectReceivedThanks');
		$this->DropView('v_TP_ThanksLog');

		$this->DropTable('ThanksLog');
	}
}
