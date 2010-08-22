<?php if (!defined('APPLICATION')) exit(); ?>
<h1><?php echo T($this->Data['Title']); ?></h1>
<?php
echo $this->Form->Open();
echo $this->Form->Errors();
$this->Form->SetValue("StopForumSpam", $this->Settings->StopForumSpam);
$this->Form->SetValue("Akismet", $this->Settings->Akismet);
$this->Form->SetValue("DeleteSpam", $this->Settings->DeleteSpam);
?>
<ul>
	<li>
		<div class="Info">More Information here...</div>
	</li>
	<li>
		<?php
		echo $this->Form->CheckBox('StopForumSpam', "Enable StopForumSpam.com?", array("value"=>"1"));
		?>
	</li>
	<li>
		<?php
		echo $this->Form->CheckBox('Akismet', "Enable Akismet?", array("value"=>"1"));
		?>
	</li>
	<li>
		<?php
		echo $this->Form->Label('Akistmet Key', 'Akismet Key');
		echo $this->Form->TextBox('AkismetKey', array("value"=>$this->Settings->AkismetKey));
		?>
	</li>
	<li>
		<?php
		echo $this->Form->CheckBox('DeleteSpam', "Delete Spam Automatically?", array("value"=>"1"));
		?>
	</li>		
</ul>
<?php echo $this->Form->Close('Save'); ?>

<div class="spam">
<?php
$SpamCount = $this->SpamLog->NumRows();
if ( ! $SpamCount)
{
	echo "There are no spam items at this time.";
}
else
{
	echo "<h3>".$SpamCount." ".Plural($SpamCount,"item","items")." in queue</h3>\n";
	foreach ($this->SpamLog as $Spam)
	{
		?>
		<div class="spam_item">
			<div><span>User:</span> <?php echo "<strong>".Anchor($Spam->Name,"profile/{$Spam->UserID}/{$Spam->Name}")."</strong> ".T('on').' '.$Spam->DateInserted; ?></div>
			<div><span>IP Address:</span> <?php echo $Spam->IpAddress; ?></div>
			<div><span>Link:</span> <?php echo Anchor(Url($Spam->ForeignURL,TRUE),$Spam->ForeignURL); ?></div>
			<div class="spam_action">
				<?php
				// Is this a comment or discussion?
				echo $this->Form->Button('Not Spam',array(
					'onclick' => "window.location.href='".Url('plugin/vanillaantispam/notspam/'.$Spam->ID,TRUE)."'",
					'class' => 'SmallButton'
				));
				echo $this->Form->Button('Take Action',array(
					'onclick' => "window.location.href='".Url($Spam->ForeignURL,TRUE)."'",
					'class' => 'SmallButton'
				));
				?>
			</div>
		</div>
		<?php
	}
}
?>
</div>