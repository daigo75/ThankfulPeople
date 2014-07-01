<?php if (!defined('APPLICATION')) exit();

$PluginInfo['ThankfulPeople'] = array(
	'Name' => 'Thankful People',
	'Description' => 'Remake of classic Vanilla One extension. Instead of having people post appreciation and thankyou notes they can simply click the thanks link and have their username appear under that post (MySchizoBuddy).',
	'Version' => '1.1.0.140701',
	'Author' => 'Diego Zanella (original by Jerl Liandri)',
	'AuthorUrl' => 'http://www.aelia.co',
	'RequiredApplications' => array(
		'Vanilla' => '>=2.0.18'
	),
	'RequiredTheme' => false,
	'RequiredPlugins' => array(
		'AeliaFoundationClasses' => '14.06.24.001',
	),
	'License' => 'GPLv3',
	'RegisterPermissions' => array(
		'ThankfulPeople.Thanks.Send',
		'ThankfulPeople.Thanks.Revoke',
		'ThankfulPeople.Thanks.SendToOwn',
	),
);

use Aelia\Plugins\ThankfulPeople\Schema;
use Aelia\Plugins\ThankfulPeople\Definitions;

class ThankfulPeoplePlugin extends Gdn_Plugin {
	private $Session;

	public function __construct() {
		require_once(__DIR__ . '/vendor/autoload.php');
		parent::__construct();

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

	protected function AllowRevokingThanks() {
		if(empty($this->_AllowRevokingThanks)) {
			$this->_AllowRevokingThanks = C('Plugins.ThankfulPeople.AllowRevoke', false);
		}

		return $this->_AllowRevokingThanks;
	}

	/**
	 * Given an object (Discussion, Comment, etc), returns its ID.
	 *
	 * @param string ObjectType The object type (Discussion, Comment, etc).
	 * @param int ObjectID The object ID.
	 * @return int|null
	 */
	protected function GetObjectID($ObjectType, $Object) {
		$ObjectID = null;
		switch($ObjectType) {
			case 'Question':
			case 'Discussion':
				$ObjectID = $Object->DiscussionID;
				break;
			case 'Comment':
				$ObjectID = $Object->CommentID;
				break;
		}

		$this->EventArguments['ObjectType'] = $Object;
		$this->EventArguments['Object'] = $Object;
		$this->EventArguments['ObjectID'] = $ObjectID;
		$this->FireEvent('GetObjectID');

		$ObjectID = $this->EventArguments['ObjectID'];
		if(empty($ObjectID)) {
			$ErrMsg = sprintf(T('ThankfulPeople_GetObjectID_UnsupportedType',
													'Could not retrieve ID of unsupported object type "%s". Object (JSON): "%s".'),
												$ObjectType,
												json_encode($Object));
			$this->Log()->warn($ErrMsg);
		}

		return $ObjectID;
	}

	// TODO Implement "revoke thanks" method
	//public function Controller_RevokeThanks($Sender) {
	//	$SessionUserID = GetValue('UserID', Gdn::Session());
	//	if($SessionUserID > 0 && C('Plugins.ThankfulPeople.AllowTakeBack', false)) {
	//		$ThanksLogModel = new ThanksLogModel();
	//		$Type = GetValue(0, $Sender->RequestArgs);
	//		$ObjectID = GetValue(1, $Sender->RequestArgs);
	//		$ThanksLogModel->RemoveThank($Type, $ObjectID, $SessionUserID);
	//		if ($Sender->DeliveryType() == DELIVERY_TYPE_ALL) {
	//			$Target = GetIncomingValue('Target', 'discussions');
	//			Redirect($Target);
	//		}
	//		$ThankfulPeopleDataSet = $ThanksLogModel->GetThankfulPeople($Type, $ObjectID);
	//		$Sender->SetData('NewThankedByBox', self::ThankedByBox($ThankfulPeopleDataSet->Result(), false));
	//		$Sender->Render();
	//	}
	//}

	/**
	* Create a method called "TopContributors" on the PluginController
	*
	* One of the most powerful tools at a plugin developer's fingertips is the ability to freely create
	* methods on other controllers, effectively extending their capabilities. This method creates the
	* TopContributors() method on the PluginController, effectively allowing the plugin to be invoked via the
	* URL: http://www.yourforum.com/plugin/Example/
	*
	* From here, we can do whatever we like, including turning this plugin into a mini controller and
	* allowing us an easy way of creating a dashboard settings screen.
	*
	* @param $Sender Sending controller instance
	*/
	public function PluginController_ThankfulPeople_Create($Sender) {
		$Sender->Permission('Vanilla.Settings.Manage');
		$Sender->Title('Thankful People');
		$Sender->AddSideMenu('plugin/thankfulpeople');

		$this->Dispatch($Sender, $Sender->RequestArgs);
	}

	/**
	 * Adda a "thanks" to the specified object.
	 *
	 * @param Gdn_Controller Sender Sending controller instance.
	 * @param string ObjectType The object type (Discussion, Comment, etc).
	 * @param int ObjectID The object ID.
	 */
	public function Controller_GiveThanks($Sender) {
		$Session = Gdn::Session();
		if(!$Session->IsValid()) {
			return;
		}

		// Check that the user has the permission to say "thanks"
		$Sender->Permission('ThankfulPeople.Thanks.Send');
		$Sender->Form->SetModel($this->ThanksLogModel);

		// Only authenticated users can post a Thanks
		if(!$Sender->Form->AuthenticatedPostback()) {
			return;
		}
		$Result = Definitions::RES_OK;

		$ObjectType = $Sender->Form->GetFormValue('ObjectType');
		$ObjectID = $Sender->Form->GetFormValue('ObjectID');
		$ObjectInsertUserID = $this->ThanksLogModel->GetObjectInsertUserID($ObjectType, $ObjectID);

		if(($ObjectInsertUserID == $Session->UserID) && !$Session->CheckPermission('ThankfulPeople.Thanks.SendToOwn')) {
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
			$ThanksCount = (int)$this->ThanksLogModel->GetThanksCountByObjectID($ObjectType, $ObjectID, $Wheres);

			if($ThanksCount > 0) {
				$Result = Definitions::RES_ERR_OBJECT_ALREADY_THANKED;
			}
		}

		if($Result == Definitions::RES_OK) {
			// TODO Implement saving of the thanks
			$SaveResult = $Sender->Form->Save();
		}

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
		// If not rendering a page or a view, do nothing
		if(!($Sender->DeliveryType() == DELIVERY_TYPE_ALL && $Sender->SyndicationMethod == SYNDICATION_NONE)) {
			return;
		}

		$Sender->AddJsFile('jquery.expander.js');
		$Sender->AddCssFile('plugins/ThankfulPeople/design/thankfulpeople.css');
		$Sender->AddJsFile('plugins/ThankfulPeople/js/thankfulpeople.functions.js');

		$Sender->AddDefinition('ExpandThankList', T('ExpandThankList'));
		$Sender->AddDefinition('CollapseThankList', T('CollapseThankList'));
	}

	/**
	 * Determines if thanks can be sent for specific object type.
	 *
	 * @param string ObjectType The object type candidate to receive a thanks.
	 * @return bool
	 */
	protected function IsThankable($ObjectType) {
		$AllowedObjectTypes = $this->AllowedObjectTypes();
		$DisallowedObjectTypes = $this->DisallowedObjectTypes();

		// If "disallowed objects" list is populated, and object type is in it, then
		// the "thanks" cannot be sent
		if(is_array($DisallowedObjectTypes) && InArrayI($ObjectType, $DisallowedObjectTypes)) {
			return false;
		}

		// If "allowed objects" list is NOT populated, or object type is in it, then
		// the "thanks" can be sent
		if(!is_array($AllowedObjectTypes) || InArrayI($ObjectType, $AllowedObjectTypes)) {
			return true;
		}
	}

	/**
	 * Alters the SQL of a DiscussionModel to join with the ThanksLog, in order
	 * to retrieve the Thanks received by it
	 *
	 * @param Gdn_Model Sender Sending controller instance.
	 * @param string ObjectType The object type (Discussion, Comment, etc).
	 * @param int ObjectID The object ID.
	 * @param int InsertUserID If specified, it's used to further narrow down the
	 * JOIN.
	 * @param array ExtraWheres An array of additional WHERE clauses.
	 */
	protected function JoinWithThanksLog(Gdn_Model $Sender, $ObjectType, $KeyField, $InsertUserID = null, $ExtraWheres = array()) {
		$JoinClause = "(TL.ObjectType = $ObjectType) AND (TL.ObjectID = $KeyField)";

		$InsertUserID = (int)$InsertUserID;
		if($InsertUserID > 0) {
			$JoinClause .= "AND (TL.InsertUserID = $InsertUserID)";
		}

		$Sender->SQL
			->Select('TL.ThankID', '', 'ThankID')
			->Select('TL.DateInserted', '', 'DateThanked')
			->LeftJoin('v_TP_ThanksLog TL', $JoinClause)
			->BeginWhereGroup()
			->Where($ExtraWheres)
			->EndWhereGroup();
	}

	/**
	 * Handler of Event DiscussionModel::BeforeGet.
	 * Alter SQL of Discussions Model to add "thanks" information.
	 *
	 * @param DiscussionModel Sender Sending controller instance.
	 */
	public function DiscussionModel_BeforeGet_Handler($Sender) {
		$this->JoinWithThanksLog($Sender, 'd.Type', 'd.DiscussionID', Gdn::Session()->UserID);
	}

	/**
	 * Handler of Event DiscussionModel::BeforeGetID.
	 * Alter SQL of Discussions Model to add "thanks" information.
	 *
	 * @param DiscussionModel Sender Sending controller instance.
	 */
	public function DiscussionModel_BeforeGetID_Handler($Sender) {
		$this->JoinWithThanksLog($Sender, 'd.Type', 'd.DiscussionID', Gdn::Session()->UserID);
	}

	/**
	 * Handler of Event CommentModel::BeforeGet.
	 * Alter SQL of Discussions Model to add "thanks" information.
	 *
	 * @param CommentModel Sender Sending controller instance.
	 */
	public function CommentModel_BeforeGet_Handler($Sender) {
		$this->JoinWithThanksLog($Sender, "'Comment'", 'c.CommentID', Gdn::Session()->UserID);
	}

	/**
	 * Handler of Event CommentModel::BeforeGetIDData.
	 * Alter SQL of Discussions Model to add "thanks" information.
	 *
	 * @param CommentModel Sender Sending controller instance.
	 */
	public function CommentModel_BeforeGetIDData_Handler($Sender) {
		$this->JoinWithThanksLog($Sender, "'Comment'", 'c.CommentID', Gdn::Session()->UserID);
	}

	public function DiscussionController_CommentOptions_Handler($Sender) {
		$EventArguments = &$Sender->EventArguments;
		$Object = $EventArguments['Object'];

		$ObjectType = empty($Object->Type) ? $EventArguments['Type'] : $Object->Type;

		if(!Gdn::Session()->IsValid()) {
			return;
		}

		// If thanks are not allowed for this object, move on
		if(!$this->IsThankable($ObjectType)) {
			return;
		}

		// Cannot send a "thanks" for unsupported object types
		$ObjectID = $this->GetObjectID($ObjectType, $Object);
		if(empty($ObjectID)) {
			return;
		}

		// Debug
		//var_dump($Object);

		$SessionUserID = Gdn::Session()->IsValid() ? Gdn::Session()->UserID : null;
		// Only user with proper permissions can send a thanks to their own objects
		if(($Object->InsertUserID == $SessionUserID) && !Gdn::Session()->CheckPermission('ThankfulPeople.Thanks.SendToOwn')) {
			return;
		}

		$MessageThanksModule = new MessageThanksModule($Sender);
		$MessageThanksModule->SetParams($ObjectType, $ObjectID, $Object);

		echo $MessageThanksModule->ToString();
	}

	public function DiscussionController_AfterCommentBody_Handler($Sender) {
		return;

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
