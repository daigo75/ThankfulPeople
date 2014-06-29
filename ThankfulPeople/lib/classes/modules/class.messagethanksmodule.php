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

	protected function SetData($MessageID, $Message) {
		if(!is_object($Message)) {
			$ErrMsg = sprintf(T('Argument "Message" must be either a discussion or a comment object. ' .
													'Received value (JSON): "%s".'),
												json_encode($Message));
			$this->Log()->error($ErrMsg);

			if(Gdn::Session()->CheckPermissions('Garden.Settings.Manage')) {
				throw new InvalidArgumentException($ErrMsg);
			}
			return;
		}

		$this->EventArguments['Message'] = $Message;
		$this->EventArguments['MessageID'] = $MessageID;
		$this->FireEvent('MessageThanks_AfterLoadData');

		$this->SetData('MessageID', $MessageID);
		$this->SetData('Message', $Message);
	}

	public function ToString() {
		if($this->Data('Message', null) == null) {
			$ErrMsg = sprintf(T('No discussion or comment was passed to the module. You must call ' .
													'%s::SetData() before rendering this module.'),
												get_class());
			$this->Log()->error($ErrMsg);
			if(Gdn::Session()->CheckPermissions('Garden.Settings.Manage')) {
				throw new InvalidArgumentException($ErrMsg);
			}
		}

		include $this->_Sender->FetchViewLocation('MessageThanksModule', 'modules', 'plugins/ThankfulPeople');
	}
}
