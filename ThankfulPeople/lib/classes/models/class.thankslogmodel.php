<?php if (!defined('APPLICATION')) exit();

/**
 * Model for the ThanksLog table. It implements the logic to manage thanks
 * received by users and objects (discussions, comments, etc) created by them.
 */
class ThanksLogModel extends \Aelia\Model {
	protected $DiscussionModel;
	protected $CommentModel;
	protected $UserModel;

	const PLUS_ONE_THANK = 1;
	const MINUS_ONE_THANK = -1;

	protected static $TableFields = array(
		'comment' => 'CommentID',
		'discussion' => 'DiscussionID'
	);

	protected static $TableNames = array();

	public function __construct() {
		parent::__construct('ThanksLog');

		$this->DiscussionModel = new \Aelia\Plugins\ThankfulPeople\DiscussionModel();
		$this->CommentModel = new \Aelia\Plugins\ThankfulPeople\CommentModel();
		$this->UserModel = new \Aelia\Plugins\ThankfulPeople\UserModel();
	}

	/**
	 * Returns the amount of thanks received by a specific object.
	 *
	 * @param string ObjectType The object type (Discussion, Comment, etc).
	 * @param int ObjectID The object ID.
	 * @param array ExtraWheres Additional WHERE clauses to be used by the query.
	 * This parameter can be used, for example, to filter by recipient, or by sender.
	 * @return int|false
	 */
	public function GetThanksCountByObjectID($ObjectType, $ObjectID, $ExtraWheres = array()) {
		$Result = $this->SQL
			->Select('TL.ThankID', 'COUNT(%s)', 'ThanksCount')
			->From('ThanksLog TL')
			->Where('TL.ObjectType', $ObjectType)
			->Where('TL.ObjectID', $ObjectID)
			->Where($ExtraWheres)
			->GroupBy('TL.ObjectType')
			->GroupBy('TL.ObjectID')
			->Get()
			->Value('ThanksCount');

		return $Result;
	}

	/**
	 * Retrieves an entry from the Thanks Log, using the details of the thanked
	 * object and the ID of the thanking user.
	 *
	 * @param string ObjectType The object type (Discussion, Comment, etc).
	 * @param int ObjectID The object ID.
	 * @param string SenderUserID The ID of the user who sent the Thanks.
	 * @return object|false
	 */
	public function GetByObjectKey($ObjectType, $ObjectID, $SenderUserID) {
		$Wheres = array(
			'ObjectType' => $ObjectType,
			'ObjectID' => $ObjectID,
			'SenderUserID' => $SenderUserID,
		);
		return $this->Get($Wheres)->FirstRow();
	}

	/**
	 * Retrieves an entry from the Thanks Log, using the ThankID as the search
	 * key.
	 *
	 * @param int ThankID The thank ID.
	 * @return object|false
	 */
	public function GetByID($ThankID) {
		$Wheres = array(
			'ThankID' => $ThankID,
		);
		return $this->Get($Wheres)->FirstRow();
	}

	/**
	 * Retrieves data from the thanks log table.
	 *
	 * @param array Wheres A set of where clauses to filter the results.
	 * @param int Offset The offset do determine from which row to take the data.
	 * @param int Limit The maximum amount of rows to return.
	 * @return Gdn_DataSet|false
	 */
	public function Get($Wheres = array(), $Offset = false, $Limit = false) {
		// TODO Refactor method so that this call to parent is no longer needed.
		// Ensure that callers are aware of the new method signature
		return parent::Get($Wheres, null, $Limit, $Offset);
	}

	public static function GetPrimaryKeyField($Name) { // Type, Table name
		$Name = strtolower($Name);
		if (array_key_exists($Name, self::$TableFields)) return self::$TableFields[$Name];
		return self::GetTableName($Name).'ID';
	}

	public static function GetTableName($Name) {
		$Name = strtolower($Name);
		return ArrayValue($Name, self::$TableNames, ucfirst($Name));
	}

	/**
	 * Retrieves the User ID of the person who sent a thanks.
	 *
	 * @param string ObjectType The object type (Discussion, Comment, etc).
	 * @param int ObjectID The object ID.
	 * @return int|null
	 */
	public function GetObjectInsertUserID($ObjectType, $ObjectID) {
		$this->EventArguments['ObjectType'] = $ObjectType;
		$this->EventArguments['ObjectID'] = $ObjectID;
		$this->FireEvent('BeforeGetObjectInsertUserID');

		if(isset($this->EventArguments['ObjectInsertUserID'])) {
			return $this->EventArguments['ObjectInsertUserID'];
		}

		$Result = null;
		$SQL = clone $this->SQL;
		switch(strtolower($ObjectType)) {
			case 'discussion':
			case 'question':
				$SQL
					->Select('InsertUserID')
					->From('Discussion')
					->Where('DiscussionID', $ObjectID);
				break;
			case 'comment':
				$SQL
					->Select('InsertUserID')
					->From('Comment')
					->Where('CommentID', $ObjectID);
				break;
			default:
				$SQL = null;
		}

		if(!empty($SQL)) {
			$Result = $SQL->Get()->Value('InsertUserID');
		}
		return $Result;
	}

	/**
	 * Deletes a "thank".
	 *
	 * @param int ThankID The thank ID.
	 * @return int
	 */
	public function Delete($ThankID) {
		$ObjectToDelete = $this->GetByID($ThankID);
		return $this->DeleteThank($ObjectToDelete);
	}

	/**
	 * Deletes an entry from the Thanks Log, using the object key.
	 *
	 * @param string ObjectType The object type (Discussion, Comment, etc).
	 * @param int ObjectID The object ID.
	 * @param string SenderUserID The ID of the user who sent the Thanks. This
	 * is used to ensure that the correct thanks is deleted.
	 * @return int|null
	 */
	public function DeleteByObjectKey($ObjectType, $ObjectID, $SenderUserID) {
		$ObjectToDelete = $this->GetByObjectKey($ObjectType, $ObjectID, $SenderUserID);
		return $this->DeleteThank($ObjectToDelete);
	}

	/**
	 * Deletes a Thank from the thanks log table and updates all object to which
	 * the thank was related.
	 *
	 * @param object $ThanksObject An object representing a thanks.
	 * @return int
	 */
	protected function DeleteThank($ThanksObject) {
		if(empty($ThanksObject) ||
			 !isset($ThanksObject->ThankID)) {
			return null;
		}

		$Result = array();
		$this->Database->BeginTransaction();
		try {
			$DeleteResult = $this->SQL->Delete($this->Name, array('ThankID' => $ThanksObject->ThankID,));
			$RowsAffected = $DeleteResult->PDOStatement()->rowCount();

			if($RowsAffected > 0) {
				// Update the amount of thanks received by the thanked user
				$Result['UserThanksCount'] = $this->UpdateThankedObject('User', $ThanksObject->UserID, self::MINUS_ONE_THANK);
				// Update the amount of thanks received by the object on which the "thanks" was placed
				$Result['ObjectThanksCount'] = $this->UpdateThankedObject($ThanksObject->ObjectType,$ThanksObject->ObjectID, self::MINUS_ONE_THANK);
			}
		}
		catch(Exception $e) {
			$this->Database->RollbackTransaction();
			$ErrMsg = sprintf(T('ThanksLogModel_Delete_Exception',
													'Unexpected exception occurred while deleting from ThanksLog table. Received arguments ' .
													'(JSON): "%s". Exception message: "%s".'),
												json_encode(func_get_args()),
												$e->getMessage());
			$this->Log()->error($ErrMsg);
			return false;
		}
		$this->Database->CommitTransaction();
		return $RowsAffected;
	}

	/**
	 * Recalculates the thanks received by all users.
	 *
	 * @return int
	 */
	public function RecalculateUserReceivedThanksCount() {
		return $this->UserModel->UpdateReceivedThanksCount();
	}

	protected function PrepareGetQuery() {
		$this->SQL
			->Select('T.CommentID')
			->Select('T.DiscussionID')
			->Select('T.DateInserted')
			->Select('T.InsertUserID as UserID')
			->Select('U.Name')
			->Join('User U', '(U.UserID = T.InsertUserID)');
	}

	/**
	 * Retrieves a list of all users who thanked for a specific object.
	 *
	 * @param string ObjectType The object type (Discussion, Comment, etc).
	 * @param int ObjectID The object ID.
	 * @return Gdn_DataSet|false
	 */
	public function GetThankfulPeople($ObjectType, $ObjectID) {
		$Wheres = array(
			'ObjectType' => $ObjectType,
			'ObjectID' => $ObjectID,
		);

		$Result = $this->Get(array($Field => $ObjectID));
		return $Result;
	}

	public function GetReceivedThanks($Where = false, $Offset = false, $Limit = false) {
		$this->PrepareGetQuery();
		$this->SQL
			->OrderBy('t.DateInserted', 'desc');
		$ReceivedThanks = $this->Get($Where, $Offset, $Limit);
		$ThankData = array();
		$this->EventArguments['ReceivedThanks'] = $ReceivedThanks;
		$this->EventArguments['ThankData'] =& $ThankData;
		while($Data = $ReceivedThanks->NextRow()) {
			if ($Data->CommentID > 0) $ThankData['Comment'][$Data->CommentID][] = $Data;
			elseif ($Data->DiscussionID > 0) $ThankData['Discussion'][$Data->DiscussionID][] = $Data;
		}
		$this->FireEvent('BeforeRetreiveThankObjects');
		if (count($ThankData) == 0) return array(array(), array());
		foreach (array_keys($ThankData) as $Type) {
			$ObjectIDs = array_keys($ThankData[$Type]);
			$ObjectPrimaryKey = self::GetPrimaryKeyField($Type);
			$Table = self::GetTableName($Type);
			$ExcerptTextField = 'Body';
			switch ($Table) {
				case 'Comment': $this->SQL->Select('CommentID', "concat('discussion/comment/', %s)", 'Url'); break;
				case 'Discussion': $this->SQL->Select('DiscussionID', "concat('discussion/', %s)", 'Url'); break;
			}
			$this->EventArguments['ObjectPrimaryKey'] =& $ObjectPrimaryKey;
			$this->EventArguments['ObjectTable'] =& $Table;
			$this->EventArguments['ExcerptTextField'] =& $ExcerptTextField;
			$this->FireEvent('RetreiveThankObject');

			$ObjectIDs = implode(',', array_map('intval', $ObjectIDs)); // TODO: REMOVE

			$Sql = $this->SQL
				->Select("'$Type'", '', 'Type')
				->Select($ObjectPrimaryKey, '', 'ObjectID')
				->Select($ExcerptTextField, 'mid(%s, 1, 255)', 'ExcerptText') // TODO: Config how many first chars get
				->Select('DateInserted')
				->From($Table)
				->Where($ObjectPrimaryKey .' in ('.$ObjectIDs.')', Null, false, false)
				->GetSelect();
			//$Sql = $this->SQL->ApplyParameters($Sql); // TODO: WAITING FOR APPLYING COMMITS
			$this->SQL->Reset();
			$SqlCollection[] = $Sql;
		}

		$this->EventArguments['SqlCollection'] =& $SqlCollection;
		$this->FireEvent('AfterRetreiveThankObjects');

		$ResultSql = implode("\n union \n", $SqlCollection);
		$Objects = $this->SQL->Query("select * from (\n$ResultSql\n) as t order by DateInserted desc")->Result();
		$Result = array($ThankData, $Objects);
		return $Result;
	}

	protected function RunDelete($SQL) {
		$Result = $this->SQL->Query($SQL, 'delete');
		$RowsAffected = $Result->PDOStatement()->rowCount();

		return $RowsAffected;
	}

	protected function DeleteOrphanThanks() {
		$Px = $this->Database->DatabasePrefix;
		$Result = array();
		$CleanupQueries = array(
			// Delete orphan thanks for Discussions
			'Discussions' => "
				DELETE {$Px}ThanksLog
				FROM
					{$Px}ThanksLog
					LEFT JOIN
					{$Px}Discussion D on
						(D.DiscussionID = {$Px}ThanksLog.ObjectID)
				WHERE
					({$Px}ThanksLog.ObjectType in ('Discussion', 'Question')) AND
					(D.DiscussionID is null);",
			// Delete orphan thanks for Comments
			'Comments' => "
				DELETE {$Px}ThanksLog
				FROM
					{$Px}ThanksLog
					LEFT JOIN
					{$Px}Comment C on
						(C.CommentID = {$Px}ThanksLog.ObjectID)
				WHERE
					({$Px}ThanksLog.ObjectType = 'Comment') AND
					(C.CommentID is null);",
		);

		$this->Database->BeginTransaction();
		try {
			// Run each cleanup query, storing the number rows by it
			foreach($CleanupQueries as $ObjectType => $DeleteSQL) {
				$Result[$ObjectType] = $this->RunDelete($DeleteSQL);
			}

			$this->EventArguments['CleanupResult'] = $Result;
			$this->FireEvent('DeleteOrphanThanks');
			$Result = $this->EventArguments['CleanupResult'];

			$this->Database->CommitTransaction();
		}
		catch(Exception $e) {
			$this->Database->RollbackTransaction();
			$ErrMsg = sprintf(T('ThanksLogModel_OrphanThanksCleanup_Exception',
													'Unexpected exception occurred while deleting orphan thanks from ' .
													'ThanksLog table Exception message: "%s".'),
												$e->getMessage());
			$this->Log()->error($ErrMsg);
			return false;
		}

		return $Result;
	}

	public function Cleanup() {
		$SQL = Gdn::SQL();
		$Px = $SQL->Database->DatabasePrefix;
		$SQL->Query("delete t.* from {$Px}ThanksLog t
			left join {$Px}Comment c on c.CommentID = t.CommentID
			where c.commentID is null and t.commentID > 0");
		$SQL->Query("delete t.* from {$Px}ThanksLog t
			left join {$Px}Discussion d on d.DiscussionID = t.DiscussionID
			where d.DiscussionID is null and t.DiscussionID > 0");
	}

	/**
	 * Updates the thanks count for the specified object.
	 *
	 * @param string ObjectType The object type (User, Discussion, Comment, etc).
	 * @param int ObjectID The object ID.
	 * @param int Value The amount of thanks to add or subtract.
	 * @return int The amount of thanks received by the object, including the new
	 * ones just applied.
	 */
	protected function UpdateThankedObject($ObjectType, $ObjectID, $Value) {
		switch($ObjectType) {
			case 'Question':
			case 'Discussion':
				$this->DiscussionModel->UpdateReceivedThanksCount($ObjectID, $Value);
				$Result = $this->DiscussionModel->GetThanksCount($ObjectID);

				break;
			case 'Comment':
				$this->CommentModel->UpdateReceivedThanksCount($ObjectID, $Value);
				$Result = $this->CommentModel->GetThanksCount($ObjectID);

				break;
			case 'User':
				$this->UserModel->UpdateReceivedThanksCount($ObjectID, $Value);
				$Result = $this->UserModel->GetThanksCount($ObjectID);

				break;
		}

		$this->EventArguments['ObjectType'] = $Object;
		$this->EventArguments['ObjectID'] = $ObjectID;
		$this->EventArguments['Value'] = $Value;
		$this->EventArguments['ReceivedThanksCount'] = $Result;
		$this->FireEvent('UpdateThankedObject');

		$Result = $this->EventArguments['ReceivedThanksCount'];

		return $Result;
	}

	/**
   * Takes a set of form data ($Form->_PostValues), validates them, and
   * inserts or updates them to the datatabase.
   *
   * @param array $FormPostValues An associative array of $Field => $Value pairs that represent data posted
   * from the form in the $_POST or $_GET collection.
   * @param array $Settings If a custom model needs special settings in order to perform a save, they
   * would be passed in using this variable as an associative array.
   * @return array|bool An array with the page ID and Url ID, or false on failure.
   *
	 * @see \AeliaBaseModel\Save()
	 */
  public function Save($FormPostValues, $Settings = false) {
		$this->Database->BeginTransaction();

		$Result = false;
		try {
			$SaveResult = parent::Save($FormPostValues, $Settings);
			if($SaveResult) {
				$Result = array(
					'ThanksSaveResult' => $Result,
				);

				// Update the amount of thanks received by the thanked user
				$Result['UserThanksCount'] = $this->UpdateThankedObject('User', $FormPostValues['UserID'], self::PLUS_ONE_THANK);
				// Update the amount of thanks received by the object on which the "thanks" was placed
				$Result['ObjectThanksCount'] = $this->UpdateThankedObject($FormPostValues['ObjectType'], $FormPostValues['ObjectID'], self::PLUS_ONE_THANK);

				$this->Database->CommitTransaction();
			}
			else {
				$this->Database->RollbackTransaction();
			}
		}
		catch(Exception $e) {
			$this->Database->RollbackTransaction();
			$ErrMsg = sprintf(T('ThanksLogModel_Save_Exception',
													'Unexpected exception occurred while saving on ThanksLog table. Received arguments ' .
													'(JSON): "%s". Exception message: "%s".'),
												json_encode(func_get_args()),
												$e->getMessage());
			$this->Log()->error($ErrMsg);
			return false;
		}

		return $Result;
	}
}
