<?php
if (!defined('APPLICATION')) exit();

/**
 * Renders a box displaying the amount of "Thanks" received by a Discussion or
 * a comment.
 */
class MessageThanksModule extends \Aelia\Module {
	protected $ThanksData;

	public function AssetTarget() {
		return 'Content';
	}

	/**
	 * Sets the parameters used to render the module.
	 *
	 * @param string ObjectType The object type (Discussion, Comment, etc).
	 * @param int ObjectID The object ID.
	 * @param object Object The object for which the module should be rendered.
	 */
	public function SetParams($ObjectType, $ObjectID, $Object) {
		if(!is_object($Object)) {
			$ErrMsg = sprintf(T('Argument "Object" must be either a discussion or a comment object. ' .
													'Received value (JSON): "%s".'),
												json_encode($Object));
			$this->Log()->error($ErrMsg);

			if(Gdn::Session()->CheckPermission('Garden.Settings.Manage')) {
				throw new InvalidArgumentException($ErrMsg);
			}
			return;
		}

		$this->EventArguments['ObjectType'] = $ObjectType;
		$this->EventArguments['ObjectID'] = $ObjectID;
		$this->EventArguments['Object'] = $Object;
		$this->FireEvent('MessageThanks_AfterLoadData');

		$this->SetData('ObjectType', $ObjectType);
		$this->SetData('ObjectID', $ObjectID);
		$this->SetData('Object', $Object);
	}

	public function ToString() {
		if($this->Data('Object', null) == null) {
			$ErrMsg = sprintf(T('No object was passed to the module. You must call ' .
													'%s::SetData() before rendering this module.'),
												get_class());
			$this->Log()->error($ErrMsg);
			if(Gdn::Session()->CheckPermission('Garden.Settings.Manage')) {
				throw new InvalidArgumentException($ErrMsg);
			}
		}

		$Object = $this->Data('Object');
		$UserCanThank = true;
		$SessionUserID = Gdn::Session()->IsValid() ? Gdn::Session()->UserID : null;
		// Only user with proper permissions can send a thanks to their own objects
		if(!empty($Object->ThankID) ||
			 (($Object->InsertUserID == $SessionUserID) && !Gdn::Session()->CheckPermission('ThankfulPeople.Thanks.SendToOwn'))) {
			$UserCanThank = false;
		}

		$this->SetData('UserCanThank', $UserCanThank);
		include $this->_Sender->FetchViewLocation('MessageThanksModule', 'modules', 'plugins/ThankfulPeople');
	}
}
