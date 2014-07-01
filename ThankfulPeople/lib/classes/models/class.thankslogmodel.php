<?php if (!defined('APPLICATION')) exit();

class ThanksLogModel extends \Aelia\Model {

	protected static $TableFields = array(
		'comment' => 'CommentID',
		'discussion' => 'DiscussionID'
	);

	protected static $TableNames = array();

	public function __construct() {
		parent::__construct('ThanksLog');
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

	public function Get($Where = false, $Offset = false, $Limit = false) {

		$bCountQuery = GetValue('bCountQuery', $Where, false, True);
		$this->EventArguments['WhereOptions'] = $Where;
		$this->EventArguments['bCountQuery'] = $bCountQuery;

		if ($bCountQuery) {
			$this->SQL->Select('*', 'count', 'RowCount');
			$Offset = $Limit = false;
		}
		if ($CommentData = GetValue('Comments', $Where, false, True)) {
			if ($CommentData instanceof Gdn_DataSet) $CommentData = ConsolidateArrayValuesByKey($CommentData->Result(), 'CommentID');
			if (!is_array($CommentData)) trigger_error('Unexpected type: '.gettype($CommentData), E_USER_ERROR);
			$this->SQL
				->WhereIn('t.CommentID', $CommentData);
		}
		if ($WithDiscussionID = GetValue('WithDiscussionID', $Where, false, True)) {
			$this->SQL->OrWhere('t.DiscussionID', $WithDiscussionID);
		}

		$this->FireEvent('BeforeGet');

		// Final where and return dataset or row count
		if (is_array($Where)) $this->SQL->Where($Where);
		$Result = $this->SQL
			->From('ThanksLog t')
			->Limit($Limit, $Offset)
			->Get();
		if ($bCountQuery) $Result = $Result->FirstRow()->RowCount;
		return $Result;
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
	 * Deletes an entry from the Thanks Log.
	 *
	 * @param string ObjectType The object type (Discussion, Comment, etc).
	 * @param int ObjectID The object ID.
	 * @param string SenderUserID The ID of the user who sent the Thanks. This
	 * is used to ensure that the correct thanks is deleted.
	 * @return int|null
	 */
	public function Delete($ObjectType, $ObjectID, $SenderUserID) {
		$this->Database->BeginTransaction();

		try {
			$Result = $this->SQL->Delete('ThanksLog', array(
				'ObjectType' => $ObjectType,
				'ObjectID' => $ObjectID,
				'InsertUserID' => $SenderUserID,
			));

			if(($RowsAffected = $Result->PDOStatement()->rowCount()) > 0) {
				$this->UpdateUserReceivedThankCount($UserID, $RowsAffected * -1);
			}
		}
		catch(Exception $e) {
			$ErrMsg = sprintf(T('ThanksLogModel_Delete_Exception',
													'Unexpected exception occurred while deleting from ThanksLog table. Received arguments ' .
													'(JSON): "%s". Exception message: "%s".'),
												json_encode(func_get_args()),
												$e->getMessage());
			$this->Log()->error($ErrMsg);
			return null;
		}
		$this->Database->CommitTransaction();
		return $RowsAffected;
	}

	/**
	 * Updates the amount of thanks received by a User.
	 *
	 * @param int UserID The user's ID.
	 * @param int Value The value to add (when positive) or subtract (when
	 * negative).
	 */
	public function UpdateUserReceivedThankCount($UserID, $Value) {
		$Value = (int)$Value;

		Gdn::SQL()
			->Update('User')
			->Set('ReceivedThankCount', 'ReceivedThankCount' . $Value, false)
			->Where('UserID', $UserID)
			->Put();
	}

	public function RecalculateUserReceivedThankCount() {
		$SQL = Gdn::SQL();
		$SqlCount = $SQL
			->Select('*', 'count', 'Count')
			->From('ThanksLog t')
			->Where('t.UserID', 'u.UserID', false, false)
			->GetSelect();
		$SQL->Reset();
		$SQL
			->Update('User u')
			->Set('u.ReceivedThankCount', "($SqlCount)", false, false)
			->Put();
	}

	public function GetDiscussionComments($DiscussionID, $CommentData, $Where = Null) {
		$Where['WithDiscussionID'] = $DiscussionID;
		$Result = $this->GetComments($CommentData, $Where);
		return $Result;
	}

	public function BaseQuery() {
		$this->SQL
			->Select('t.CommentID, t.DiscussionID, t.DateInserted, t.InsertUserID as UserID, u.Name') // TODO: Select photo?
			->Join('User u', 'u.UserID = t.InsertUserID', 'inner');
	}

	public function GetThankfulPeople($Type, $ObjectID) {
		$this->BaseQuery();
		$Field = self::GetPrimaryKeyField($Type);
		$Result = $this->Get(array($Field => $ObjectID));
		return $Result;
	}

	public function GetComments($CommentData, $Where = Null) {
		$Where['Comments'] = $CommentData;
		$this->BaseQuery();
		$Result = $this->Get($Where);
		return $Result;
	}

	public function GetReceivedThanks($Where = false, $Offset = false, $Limit = false) {
		$this->BaseQuery();
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

	public static function CleanUp() {
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
		$Result = parent::Save($FormPostValues, $Settings);

		//var_dump($FormPostValues, $this->ValidationResults());
		return $Result;
	}
}
