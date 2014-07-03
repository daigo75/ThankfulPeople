<?php if (!defined('APPLICATION')) exit();

$Sender = $this->_Sender;
$Sender->AddDefinition('ThankfulPeople_ThanksWidget_Thanking', T('ThankfulPeople_ThanksWidget_Thanking', 'Thanking...'));
$Sender->AddDefinition('ThankfulPeople_ThanksWidget_ObjectThanksCount', T('ThankfulPeople_ThanksWidget_ObjectThanksCount', 'Received thanks:'));

$ObjectType = $this->Data('ObjectType');
$ObjectID = $this->Data('ObjectID');
$Object = $this->Data('Object');
$ObjectSignature = md5($ObjectType . $ObjectID);

$UserCanThank = $this->Data('UserCanThank');

//$ActionArguments = '?DeliveryMethod=JSON&DeliveryType=DATA';
$Form = new \Aelia\Form();
$Form->InputPrefix = 'ThanksLog';
?>
<div id="<?= $ObjectSignature ?>" class="ThanksWidget">
	<div class="ReceivedThanks">
		<span><?php
			if((int)$Object->ReceivedThanksCount > 0) {
				echo T('ThankfulPeople_ThanksWidget_ObjectThanksCount', 'Received thanks:') . ' ' . $Object->ReceivedThanksCount;
			}
			else {
				echo T('ThankfulPeople_ThanksWidget_SayFirstThanks', 'Be the first to say thanks!');
			}
		?></span>
	</div>
	<div class="Actions">
		<div class="SayThanks <?= $UserCanThank ? '' : 'Hidden' ?>"><?php
			echo $Form->Open(array(
				'object-signature' => $ObjectSignature,
				'action' => Url('/plugin/thankfulpeople/givethanks', true),
			));
			echo $Form->Hidden('ObjectType', array('value' => $ObjectType));
			echo $Form->Hidden('ObjectID', array('value' => $ObjectID));
			echo $Form->Hidden('UserID', array('value' => $Object->InsertUserID));
			echo $Form->Hidden('Target', array('value' => $Sender->SelfUrl));
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
