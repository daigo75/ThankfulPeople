<?php if (!defined('APPLICATION')) exit();

$PluginInfo['ThankfulPeople'] = array(
	'Name' => 'Thankful People',
	'Description' => 'Remake of classic Vanilla One extension. Instead of having people post appreciation and thankyou notes they can simply click the thanks link and have their username appear under that post (MySchizoBuddy).',
	'Version' => '1.0.0.140629',
	'Author' => 'Diego Zanella (original by Jerl Liandri)',
	'AuthorUrl' => 'http://www.aelia.co',
	'RequiredApplications' => array(
		'Vanilla' => '>=2.0.18'
	),
	'RequiredTheme' => false,
	'RequiredPlugins' => array(
		'AeliaFoundationClasses' => '14.03.21.001',
	),
	'License' => 'X.Net License'
);

// TODO: PERMISSION THANK FOR CATEGORY
// TODO: AttachMessageThankCount

use Aelia\Plugins\ThankfulPeople\Schema;
use Aelia\Plugins\ThankfulPeople\Definitions;

class ThankfulPeoplePlugin extends Gdn_Plugin {
	protected $ThankForComment = array(); // UserIDs array
	protected $CommentGroup = array();
	protected $DiscussionData = array();
	private $Session;

	public function __construct() {
		require_once(__DIR__ . '/vendor/autoload.php');
		$this->Session = Gdn::Session();
		$this->ThanksLogModel = new ThanksLogModel();
	}

	protected function DisallowedObjectTypes() {
		if(empty($this->_DisallowedObjectTypes)) {
			$this->_DisallowedObjectTypes = C('Plugins.ThankfulPeople.DisallowedObjectTypes', null);
		}

		return $this->_DisallowedObjectTypes;
	}

	protected function AllowedObjectTypes() {
		if(empty($this->_AllowedObjectTypes)) {
			$this->_AllowedObjectTypes = C('Plugins.ThankfulPeople.AllowedObjectTypes', null);
		}

		return $this->_AllowedObjectTypes;
	}

//  public function DiscussionController_AfterCommentMeta_Handler(&$Sender) {
//		$this->AttachMessageThankCount($Sender);
//	}
//
//	protected function AttachMessageThankCount($Sender) {
//		$ThankCount = mt_rand(1, 33);
//		echo '<div class="ThankCount">'.Plural($Posts, 'Thanks: %s', 'Thanks: %s'), number_format($ThankCount, 0).'</div>';
//	}

	public function PluginController_UnThankFor_Create($Sender) {
		$SessionUserID = GetValue('UserID', Gdn::Session());
		if($SessionUserID > 0 && C('Plugins.ThankfulPeople.AllowTakeBack', false)) {
			$ThanksLogModel = new ThanksLogModel();
			$Type = GetValue(0, $Sender->RequestArgs);
			$ObjectID = GetValue(1, $Sender->RequestArgs);
			$ThanksLogModel->RemoveThank($Type, $ObjectID, $SessionUserID);
			if ($Sender->DeliveryType() == DELIVERY_TYPE_ALL) {
				$Target = GetIncomingValue('Target', 'discussions');
				Redirect($Target);
			}
			$ThankfulPeopleDataSet = $ThanksLogModel->GetThankfulPeople($Type, $ObjectID);
			$Sender->SetData('NewThankedByBox', self::ThankedByBox($ThankfulPeopleDataSet->Result(), false));
			$Sender->Render();
		}
	}

	/**
	 * Adda a "thanks" to the specified object.
	 *
	 * @param Gdn_Controller Sender Sending controller instance.
	 * @param string ObjectType The object type (Discussion, Comment, etc).
	 * @param int ObjectID The object ID.
	 */
	public function PluginController_ThankFor_Create($Sender, $ObjectType, $ObjectID) {
		$Session = Gdn::Session();
		if(!$Session->IsValid()) {
			return;
		}
		$Result = Definitions::RES_OK;

		//$Sender->Permission('Plugins.ThankfulPeople.Thank'); // TODO: PERMISSION THANK FOR CATEGORY
		//$ObjectType = GetValue(0, $Sender->RequestArgs);
		//$ObjectID = GetValue(1, $Sender->RequestArgs);
		//$Field = $this->ThanksLogModel->GetPrimaryKeyField($ObjectType);
		$ObjectInsertUserID = $ThanksLogModel->GetObjectInsertUserID($ObjectType, $ObjectID);

		if($ObjectInsertUserID == $Session->UserID) {
			$Sender->SetData('Error', T('ThankfulPeople_Thanks_CannotThankYourOwn',
																	'You cannot thank yourself.'));
			$Result = Definitions::RES_ERR_CANNOT_THANK_YOUR_OWN;
		}

		if($Result == Definitions::RES_OK) {
			if(!$this->IsThankable($ObjectType)) {
				$Sender->SetData('Error', sprintf(T('ThankfulPeople_Thanks_ObjectThankDisallowed',
																						'Thanks not allowed for object type "%s".'),
																					$ObjectType));
				$Result = Definitions::RES_ERR_CANNOT_THANK_OBJECT_TYPE;
			}
		}

		if($Result == Definitions::RES_OK) {
			$Wheres = array(
				'TL.InsertUserID' => $Session->UserID,
			);
			$ThanksCount = $this->ThanksLogModel->GetThanksCountByObjectID($ObjectType, $ObjectID, $Wheres);

			if($ThanksCount > 0) {
				$Result = Definitions::RES_ERR_OBJECT_ALREADY_THANKED;
			}
		}
		// Make sure that user is not trying to say thanks twice.
		//$Count = $ThanksLogModel->GetCount(array($Field => $ObjectID, 'InsertUserID' => $Session->User->UserID));

		if($Result == Definitions::RES_OK) {
			// TODO Implement saving of the thanks
			$this->ThanksLogModel->Save($ObjectType, $ObjectID, $UserID);
		}

		//if ($Count < 1) $ThanksLogModel->PutThank($ObjectType, $ObjectID, $UserID);

		// If Delivery Type = ALL, redirect to the specified target, defaulting to
		// the referer and, as a last resort, to the Discussions list page
		if($Sender->DeliveryType() == DELIVERY_TYPE_ALL) {
			$Target = Gdn::Request()->Get('Target', GetValue('HTTP_REFERER', $Server, Url('/discussions', true)));
			Redirect($Target);
		}

		// TODO Get all the thanks received by the object and set it to the controller's data
		$ThankfulPeopleDataSet = $ThanksLogModel->GetThankfulPeople($ObjectType, $ObjectID);
		$Sender->SetData('NewThankedByBox', self::ThankedByBox($ThankfulPeopleDataSet->Result(), false));
		$Sender->Render();
	}

	public function DiscussionController_Render_Before($Sender) {
		if(empty($Sender->CommentData)) {
			return;
		}

		if (!($Sender->DeliveryType() == DELIVERY_TYPE_ALL && $Sender->SyndicationMethod == SYNDICATION_NONE)) return;
		$ThanksLogModel = new ThanksLogModel();
		$DiscussionID = $Sender->DiscussionID;
		// TODO: Permission view thanked
		$CommentIDs = ConsolidateArrayValuesByKey($Sender->CommentData->Result(), 'CommentID');
		$DiscussionCommentThankDataSet = $ThanksLogModel->GetDiscussionComments($DiscussionID, $CommentIDs);

		// TODO: FireEvent here to allow collect thanks from other objects

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

		$Sender->AddJsFile('jquery.expander.js');
		$Sender->AddCssFile('plugins/ThankfulPeople/design/thankfulpeople.css');
		$Sender->AddJsFile('plugins/ThankfulPeople/js/thankfulpeople.functions.js');

		$Sender->AddDefinition('ExpandThankList', T('ExpandThankList'));
		$Sender->AddDefinition('CollapseThankList', T('CollapseThankList'));
	}

	public static function IsThankable($ObjectType) {
		static $ThankOnly, $ThankDisabled;
		$ObjectType = strtolower($ObjectType);
		if (is_null($ThankOnly)) $ThankOnly = C('Plugins.ThankfulPeople.Only');
		if (is_array($ThankOnly)) {
			if (!in_array($ObjectType, $ThankOnly)) return false;
		}
		if (is_null($ThankDisabled)) $ThankDisabled = C('Plugins.ThankfulPeople.Disabled');
		if (is_array($ThankDisabled)) {
			if (in_array($ObjectType, $ThankDisabled)) return false;
		}
		return True;
	}

	public function DiscussionController_CommentOptions_Handler($Sender) {
		$EventArguments =& $Sender->EventArguments;
		$Type = $EventArguments['Type'];
		$Object = $EventArguments['Object'];
		//$Session = Gdn::Session();
		$SessionUserID = $this->Session->UserID;
		if ($SessionUserID <= 0 || $Object->InsertUserID == $SessionUserID) return;

		if (!$this->IsThankable($Type)) return;

		static $AllowTakeBack;
		if (is_null($AllowTakeBack)) $AllowTakeBack = C('Plugins.ThankfulPeople.AllowTakeBack', false);
		$AllowThank = True;

		switch ($Type) {
			case 'Discussion': {
				$DiscussionID = $ObjectID = $Object->DiscussionID;
				if (array_key_exists($SessionUserID, $this->DiscussionData)) $AllowThank = false;
				break;
			}
			case 'Comment': {
				$CommentID = $ObjectID = $Object->CommentID;
				if (array_key_exists($CommentID, $this->ThankForComment) && in_array($SessionUserID, $this->ThankForComment[$CommentID])) $AllowThank = false;
				break;
			}
		}


		if ($AllowThank) {
			static $LocalizedThankButtonText;
			if ($LocalizedThankButtonText === Null) $LocalizedThankButtonText = T('ThankCommentOption', T('Thanks'));
			$ThankUrl = 'plugin/thankfor/'.strtolower($Type).'/'.$ObjectID.'?Target='.$Sender->SelfUrl;
			$Option = '<span class="Thank">'.Anchor($LocalizedThankButtonText, $ThankUrl).'</span>';
			$Sender->Options .= $Option;
		} elseif ($AllowTakeBack) {
			// Allow unthank
			static $LocalizedUnThankButtonText;
			if (is_null($LocalizedUnThankButtonText)) $LocalizedUnThankButtonText = T('UnThankCommentOption', T('Unthank'));
			$UnThankUrl = 'plugin/unthankfor/'.strtolower($Type).'/'.$ObjectID.'?Target='.$Sender->SelfUrl;
			$Option = '<span class="UnThank">'.Anchor($LocalizedUnThankButtonText, $UnThankUrl).'</span>';
			$Sender->Options .= $Option;
		}
	}

	public function DiscussionController_AfterCommentBody_Handler($Sender) {
		$Object = $Sender->EventArguments['Object'];
		$Type = $Sender->EventArguments['Type'];
		$ThankedByBox = false;
		switch ($Type) {
			case 'Comment': {
				$ThankedByCollection =& $this->CommentGroup[$Object->CommentID];
				if ($ThankedByCollection) $ThankedByBox = self::ThankedByBox($ThankedByCollection);
				break;
			}
			case 'Discussion': {
				if (count($this->DiscussionData) > 0) $ThankedByBox = self::ThankedByBox($this->DiscussionData);
				break;
			}
			default: throw new Exception('What...');
		}
		if ($ThankedByBox !== false) echo $ThankedByBox;
	}

	public static function ThankedByBox($Collection, $Wrap = True) {
		$List = implode(' ', array_map('UserAnchor', $Collection));
		$ThankCount = count($Collection);
		//$ThankCountHtml = Wrap($ThankCount);
		$LocalizedPluralText = Plural($ThankCount, 'Thanked by %1$s', 'Thanked by %1$s');
		$Html = '<span class="ThankedBy">'.$LocalizedPluralText.'</span>'.$List;
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
		$ReceivedThankCount = GetValue('ReceivedThankCount', $Sender->User);
		if ($ReceivedThankCount > 0) {
			$UserReference = ArrayValue(0, $Sender->RequestArgs, '');
			$Username = ArrayValue(1, $Sender->RequestArgs, '');
			$Thanked = T('Profile.Tab.Thanked', T('Thanked')).'<span>'.$ReceivedThankCount.'</span>';
			$Sender->AddProfileTab($Thanked, 'profile/receivedthanks/'.$UserReference.'/'.$Username, 'Thanked');
		}
	}

	public function ProfileController_ReceivedThanks_Create($Sender) {
		$UserReference = ArrayValue(0, $Sender->RequestArgs, '');
		$Username = ArrayValue(1, $Sender->RequestArgs, '');
		$Sender->GetUserInfo($UserReference, $Username);
		$ViewingUserID = $Sender->User->UserID;

		$ReceivedThankCount = $Sender->User->ReceivedThankCount;
		$Thanked = T('Profile.Tab.Thanked', T('Thanked')).'<span>'.$ReceivedThankCount.'</span>';
		$View = $this->GetView('receivedthanks.php');
		$Sender->SetTabView($Thanked, $View);
		$ThanksLogModel = new ThanksLogModel();
		// TODO: PAGINATION
		list($Sender->ThankData, $Sender->ThankObjects) = $ThanksLogModel->GetReceivedThanks(array('t.UserID' => $ViewingUserID), 0, 50);
		$Sender->Render();
	}

	/**
	 * Plugin setup
	 *
	 * This method is fired only once, immediately after the plugin has been enabled in the /plugins/ screen,
	 * and is a great place to perform one-time setup tasks, such as database structure changes,
	 * addition/modification ofconfig file settings, filesystem changes, etc.
	 */
	public function Setup() {
		// Set up the plugin's default values

		// Create Database Objects needed by the Plugin
		require('install/thankfulpeople.schema.php');
		Schema::Install();
	}

	/**
	 * Cleanup operations to be performend when the Plugin is disabled, but not
	 * permanently removed.
	 */
	public function OnDisable() {
	}

	/**
	* Plugin cleanup
	*
	* This method is fired only once, when the plugin is removed, and is a great place to
	* perform cleanup tasks such as deletion of unsued files and folders.
	*/
	public function CleanUp() {
		// Drop Database Objects created by the Plugin
		require('install/thankfulpeople.schema.php');
		Schema::Uninstall();
	}

	/**
	 * Recalculates the thanks received by users.
	 */
	protected function Recalculate_Thanks() {
		ThanksLogModel::CleanUp();
		ThanksLogModel::RecalculateUserReceivedThankCount();
	}

	/**
	 * This function will be called by Cron Plugin. All operations that need to be
	 * executed periodically should be entered here.
	 */
	public function Cron() {
		$UpdateInterval = C('Plugin.TankfulPeople.ThanksCountUpdateInterval', Definitions::DEFAULT_RECALC_INTERVAL);
		// Retrieves the date and time of when the processing of User Titles ran last
		$LastRecalcRun = C('Plugin.TankfulPeople.LastRecalculationRun', 0);

		// If last processing occurred more than X hours ago (as specified by the
		// UpdateInterval), run the processing again
		if(strtotime('-' . $UpdateInterval . 'hours') > $LastRecalcRun) {
			// Process (and assign) User Titles
			$this->Recalculate_Thanks();

			// Save last time of Titles Processing
			SaveToConfig('Plugin.TankfulPeople.LastRecalculationRun', now());
		}
	}

	/**
	 * Register plugin for Cron Jobs.
	 */
	public function CronJobsPlugin_CronJobRegister_Handler($Sender){
		$Sender->RegisterCronJob($this);
	}
}
