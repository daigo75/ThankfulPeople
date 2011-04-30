<?php if (!defined('APPLICATION')) exit();

$PluginInfo['ThankfulPeople'] = array(
	'Name' => 'Thankful People',
	//'Index' => 'ThankfulPeople', // used in Plugin::MakeMetaKey()
	'Description' => 'Instead of having people post appreciation and thankyou notes they can simply click the thanks link and have their username appear under that post (MySchizoBuddy).',
	'Version' => '2.0.3.Beta',
	'Date' => '30 Apr 2011',
	'Author' => 'Jerl Liandri',
	'AuthorUrl' => 'http://www.liandri-mining-corporation.com',
	'RequiredApplications' => array('Vanilla' => '>=2.1.0'),
	'RequiredTheme' => False, 
	'RequiredPlugins' => False,
	//'RegisterPermissions' => array('Plugins.ThankfulPeople.Thank'),
	//'SettingsPermission' => False,
	'License' => 'X.Net License'
);

// TODO: PERMISSION THANK FOR CATEGORY

class ThankfulPeoplePlugin extends Gdn_Plugin {
	
	protected $ThankForComment = array(); // UserIDs array
	protected $CommentGroup = array();
	protected $DiscussionData = array();
	private $Session;

	public function __construct() {
		$this->Session = Gdn::Session();
	}
	
	// TODO: _AttachMessageThankCount
/*   public function DiscussionController_AfterCommentMeta_Handler(&$Sender) {
		$this->AttachMessageThankCount($Sender);
	}
	
	protected function AttachMessageThankCount($Sender) {
		$ThankCount = mt_rand(1, 33);
		echo '<div class="ThankCount">'.Plural($Posts, 'Thanks: %s', 'Thanks: %s')), number_format($ThankCount, 0)).'</div>';
	}
	*/
	
	public function PluginController_ThankFor_Create($Sender) {
		$Session = $this->Session;
		//$Sender->Permission('Plugins.ThankfulPeople.Thank'); // TODO: PERMISSION THANK FOR CATEGORY
		$ThanksLogModel = new ThanksLogModel();
		$Type = GetValue(0, $Sender->RequestArgs);
		$ObjectID = GetValue(1, $Sender->RequestArgs);
		$Field = $ThanksLogModel->GetPrimaryKeyField($Type);
		$UserID = $ThanksLogModel->GetObjectInserUserID($Type, $ObjectID);
		if ($UserID == False) throw new Exception('Object has no owner.');
		// Make sure that user is not trying to say thanks twice.
		$Count = $ThanksLogModel->GetCount(array($Field => $ObjectID, 'InsertUserID' => $Session->User->UserID));
		if ($Count < 1) $ThanksLogModel->PutThank($Type, $ObjectID, $UserID);
		
		if ($Sender->DeliveryType() == DELIVERY_TYPE_ALL) {
			$Target = GetIncomingValue('Target', 'discussions');
			Redirect($Target);
		}
		
		$ThankfulPeopleDataSet = $ThanksLogModel->GetThankfulPeople($Type, $ObjectID);
		$Sender->SetData('NewThankedByBox', self::ThankedByBox($ThankfulPeopleDataSet->Result(), False));
		$Sender->Render();
	}
	
	public function DiscussionController_Render_Before($Sender) {
		$ThanksLogModel = new ThanksLogModel();
		$DiscussionID = $Sender->DiscussionID;
		// TODO: Permission view thanked
		$CommentIDs = ConsolidateArrayValuesByKey($Sender->CommentData->Result(), 'CommentID');
		$DiscussionCommentThankDataSet = $ThanksLogModel->GetDiscussionComments($DiscussionID, $CommentIDs);
		// Consolidate.
		foreach ($DiscussionCommentThankDataSet as $ThankData) {
			$CommentID = $ThankData->CommentID;
			if ($CommentID > 0) {
				$this->CommentGroup[$CommentID][] = $ThankData;
				$this->ThankForComment[$CommentID][] = $ThankData->UserID;
			} elseif ($ThankData->DiscussionID > 0) {
				$this->DiscussionData[$ThankData->UserID] = $ThankData;
			}
		}
		
		$Sender->AddCssFile('plugins/ThankfulPeople/design/thankfulpeople.css');
		//$Sender->AddJsFile('jquery.expander.js');
		// TODO: REMOVE WAITING FOR VANILLA 2.1.0
		$Sender->AddJsFile('plugins/ThankfulPeople/js/jquery.expander.js');
		$Sender->AddJsFile('plugins/ThankfulPeople/js/thankfulpeople.functions.js');
	}
	
	public function DiscussionController_CommentOptions_Handler($Sender) {
		$EventArguments =& $Sender->EventArguments;
		$Type = $EventArguments['Type'];
		$Object = $EventArguments['Object'];
		$Session = Gdn::Session();
		$SessionUserID = $this->Session->UserID;
		if ($SessionUserID <= 0 || $Object->InsertUserID == $SessionUserID) return;
		switch ($Type) {
			case 'Discussion': {
				$DiscussionID = $ObjectID = $Object->DiscussionID;
				if (array_key_exists($SessionUserID, $this->DiscussionData)) return;
				break;
			}
			case 'Comment': {
				$CommentID = $ObjectID = $Object->CommentID;
				if (array_key_exists($CommentID, $this->ThankForComment) && in_array($SessionUserID, $this->ThankForComment[$CommentID])) return;
				break;
			}
		}
		
		static $LocalizedThankButtonText;
		if ($LocalizedThankButtonText === Null) $LocalizedThankButtonText = T('ThankCommentOption', T('Thanks'));
		
		$ThankUrl = 'plugin/thankfor/'.strtolower($Type).'/'.$ObjectID.'?Target='.$Sender->SelfUrl;
		
		$Option = '<span class="Thank">'.Anchor($LocalizedThankButtonText, $ThankUrl).'</span>';
		$Sender->Options .= $Option;
	}
	
	public function DiscussionController_AfterCommentBody_Handler($Sender) {
		$Object = $Sender->EventArguments['Object'];
		if ($Object->ThankCount <= 0) return;
		$Type = $Sender->EventArguments['Type'];
		$ThankedByBox = '';
		switch ($Type) {
			case 'Comment': {
				$ThankedByCollection = GetValue($Object->CommentID, $this->CommentGroup);
				if ($ThankedByCollection) $ThankedByBox = self::ThankedByBox($ThankedByCollection);
				break;
			}
			case 'Discussion': {
				$ThankedByBox = self::ThankedByBox($this->DiscussionData);
				break;
			}
			default: throw new Exception('What...');
		}
		if ($ThankedByBox != '') echo $ThankedByBox;
	}
	
	public static function ThankedByBox($Collection, $Wrap = True) {
		$List = implode(' ', array_map('UserAnchor', $Collection));
		$ThankCount = count($Collection);
		$ThankCountHtml = Wrap($ThankCount);
		$LocalizedPluralText = Plural($ThankCountHtml, 'Thanked by %1$s', 'Thanked by %1$s');
		$Html = '<span class="ThankedBy">'
			.$LocalizedPluralText.'</span>'.$List;
		if ($Wrap) $Html = Wrap($Html, 'div', array('class' => 'ThankedByBox'));
		return $Html;
	}
	
	public function UserInfoModule_OnBasicInfo_Handler($Sender) {
		echo Wrap(T('UserInfoModule.Thanked'), 'dt', array('class' => 'ReceivedThankCount'));
		echo Wrap($Sender->User->ReceivedThankCount, 'dd', array('class' => 'ReceivedThankCount'));
	}
	
	public function ProfileController_Render_Before($Sender) {
		if (!($Sender->DeliveryType() == DELIVERY_TYPE_ALL && $Sender->SyndicationMethod == SYNDICATION_NONE)) return;
		$Sender->AddCssFile('plugins/ThankfulPeople/design/thankfulpeople.css');
	}
	
	public function ProfileController_AddProfileTabs_Handler($Sender) {
		$UserReference = ArrayValue(0, $Sender->RequestArgs, '');
		$Username = ArrayValue(1, $Sender->RequestArgs, '');
		$ReceivedThankCount = $Sender->User->ReceivedThankCount;
		$Thanked = T('Profile.Tab.Thanked', T('Thanked')).'<span>'.$ReceivedThankCount.'</span>';
		$Sender->AddProfileTab($Thanked, 'profile/receivedthanks/'.$UserReference.'/'.$Username, 'Thanked');
	}
	
	public function ProfileController_ReceivedThanks_Create($Sender) {
		$UserReference = ArrayValue(0, $Sender->RequestArgs, '');
		$Username = ArrayValue(1, $Sender->RequestArgs, '');
		$Sender->GetUserInfo($UserReference, $Username);
		$View = $this->GetView('receivedthanks.php');
		
		$ReceivedThankCount = $ReceivedThankCount = $Sender->User->ReceivedThankCount;
		$Thanked = T('Profile.Tab.Thanked', T('Thanked')).'<span>'.$ReceivedThankCount.'</span>';
		
		$Sender->SetTabView($Thanked, $View);
		$ViewingUserID = GetValue(0, $Sender->RequestArgs);
		$ThanksLogModel = new ThanksLogModel();
		// TODO: PAGINATION
		list($Sender->ThankData, $Sender->ThankObjects) = $ThanksLogModel->GetReceivedThanks(array('t.UserID' => $ViewingUserID), 0, 50);
		$Sender->Render();
	}
	
	public function Structure($Drop = False) {
		Gdn::Structure()
			->Table('Comment')
			->Column('ThankCount', 'usmallint', 0)
			->Set();
		
		Gdn::Structure()
			->Table('Discussion')
			->Column('ThankCount', 'usmallint', 0)
			->Set();
		
		Gdn::Structure()
			->Table('User')
			//->Column('ThankCount', 'usmallint', 0)
			->Column('ReceivedThankCount', 'usmallint', 0)
			->Set();
		
		Gdn::Structure()
			->Table('ThanksLog')
			->Column('UserID', 'umediumint', False, 'key')
			->Column('CommentID', 'umediumint', Null)
			->Column('DiscussionID', 'umediumint', Null)
			->Column('DateInserted', 'datetime')
			->Column('InsertUserID', 'umediumint', False, 'key')
			->Engine('MyISAM')
			->Set(False, $Drop);
			
		ThanksLogModel::RecalculateUserReceivedThankCount();
		ThanksLogModel::RecalculateCommentThankCount();
		ThanksLogModel::RecalculateDiscussionThankCount();
	}
		
	public function Setup() {
		$this->Structure();
	}
}