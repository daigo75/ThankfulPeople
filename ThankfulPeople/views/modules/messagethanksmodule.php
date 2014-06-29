<?php if (!defined('APPLICATION')) exit();

$Form = $this->_Sender->Form;
$MessageID = $this->Data('MessageID');
$Message = $this->Data('Message');

?>
<div class="MessageThanks">
	<div><?php
		echo "Message thanks count: " . $Message->ThanksCount;
	?></div>
</div>
