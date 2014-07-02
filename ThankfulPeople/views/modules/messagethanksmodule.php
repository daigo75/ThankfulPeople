<?php if (!defined('APPLICATION')) exit();

$ObjectType = $this->Data('ObjectType');
$ObjectID = $this->Data('ObjectID');
$Object = $this->Data('Object');

//$ActionArguments .= '/' . $ObjectType . '/' . $ObjectID . '?Target=' .
$Form = new \Aelia\Form();
$Form->InputPrefix = 'ThanksLog';
?>
<div class="ThanksWidget">
	<div class="ReceivedThanks"><?php
		if((int)$Object->ReceivedThanksCount > 0) {
			printf(T('ThankfulPeople_ThanksWidget_ObjectThanksCount',
							 'Received %d thanks.'),
						 $Object->ReceivedThanksCount);
		}
		else {
			echo T('ThankfulPeople_ThanksWidget_SayFirstThanks', 'Be the first to say thanks!');
		}
	?></div>
	<div class="Action">
		<div class="SayThanks <?= !empty($Object->ThankID) ? 'Hidden' : ''; ?>"><?php
			echo $Form->Open(array(
				'action' => Url('/plugin/thankfulpeople/givethanks', true) . $ActionArguments,
			));
			echo $Form->Hidden('ObjectType', array('value' => $ObjectType));
			echo $Form->Hidden('ObjectID', array('value' => $ObjectID));
			echo $Form->Hidden('UserID', array('value' => $Object->InsertUserID));
			echo $Form->Hidden('Target', array('value' => $this->_Sender->SelfUrl));
			echo $Form->Button(T('ThankfulPeople_ThanksWidget_SayThanks', 'Thanks!'));
			echo $Form->Close();
		?></div>
		<div class="Thanked <?= empty($Object->ThankID) ? 'Hidden' : ''; ?>">
			<span><?php
				echo T('ThankfulPeople_ThanksWidget_Thanked', 'Thanked!');
			?></span>
		</div>

		<?php
		// TODO Implement UI to allow revoking a thanks.
		/*
		<span class="RevokeThanks <?= empty($Object->ThankID) ? 'Hidden' : ''; ?>"><?php
			echo Anchor(T('ThankfulPeople_ThanksWidget_RevokeThanks', 'Revoke thanks'),
									Url('/plugin/revokethanks', true) . $ActionArguments);
		?></span>
		*/
		?>
	</div>
</div>
