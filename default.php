<?php if (!defined('APPLICATION')) exit();
/**
 * Vanilla AntiSpam
 * GIT: http://github.com/dkobia/VanillaAntiSpam
 *
 * @package		VanillaAntiSpam
 * @author		David Kobia, {@link http://www.dkfactor.com http://www.dkfactor.com}
 * @version		0.6
 * @copyright	David Kobia, {@link http://www.dkfactor.com http://www.dkfactor.com}
 * @license		http://www.gnu.org/licenses/gpl-3.0.txt GPL License
 */


// Define the plugin:
$PluginInfo['VanillaAntiSpam'] = array(
	'Name' => 'Vanilla Anti Spam',
	'Description' => "<a href=\"http://github.com/dkobia/VanillaAntiSpam\" target=\"_blank\">Anti Spam via Akismet and StopForumSpam.com</a>",
	'Version' => '0.6',
	'SettingsUrl' => '/dashboard/plugin/vanillaantispam',
	'Author' => "David Kobia",
	'AuthorEmail' => 'david@kobia.net',
	'AuthorUrl' => 'http://www.dkfactor.com',
);

class VanillaAntiSpamPlugin extends Gdn_Plugin {
	
	private $stopforumspam_active = FALSE;
	private $akismet_active = FALSE;
	private $akismet_key = NULL;
	private $ip_address = NULL;
	
	public function Base_GetAppSettingsMenuItems_Handler(&$Sender)
	{
		$LinkText = 'Anti-Spam';
		$Menu = &$Sender->EventArguments['SideMenu'];
		$Menu->AddItem('Forum', T('Forum'));
		$Menu->AddLink('Forum', $LinkText, 'plugin/vanillaantispam', 'Garden.Settings.Manage');
	}	
	
	// Create the Settings Controller
	public function PluginController_Vanillaantispam_Create(&$Sender)
	{
		$Sender->Title('Anti Spam');
		$Sender->AddSideMenu('plugin/vanillaantispam');
		$Sender->Form = new Gdn_Form();
		$this->Dispatch($Sender, $Sender->RequestArgs);
	}
	
	public function Controller_Notspam(&$Sender)
	{
		// Does this User Have Permissions?
		if ( ! Gdn::Session()->CheckPermission('Garden.Settings.Manage'))
		{
			Redirect('/');
		}
		
		$Arguments = $Sender->RequestArgs;
		if (sizeof($Arguments) != 2) return;
		list($Controller, $ID) = $Arguments;

		Gdn::SQL()->Delete('AntiSpamLog',array(
			'ID' => $ID
		));

		$this->Controller_Index($Sender);
	}
	
	// Render The AntiSpam Settings Controller
	public function Controller_Index(&$Sender)
	{
		// Does this User Have Permissions?
		if ( ! Gdn::Session()->CheckPermission('Garden.Settings.Manage'))
		{
			Redirect('/');
		}
		
		include_once(PATH_PLUGINS.'/VanillaAntiSpam/class.akismet.php');
		
		$Sender->AddCssFile('admin.css');
		$Sender->AddCssFile($this->GetResource('design/vanillaantispam.css', FALSE, FALSE));
		
		if ($_POST)
		{
			$FormPostValues = $Sender->Form->FormValues();
			$stopforumspam = GetValue('StopForumSpam', $FormPostValues, '');
			$akismet = GetValue('Akismet', $FormPostValues, '');
			$akismetkey = GetValue('AkismetKey', $FormPostValues, '');
			$deletespam = GetValue('DeleteSpam', $FormPostValues, '');
			
			$true_false = array(0,1);
			// Perform Simple Validation
			if ( ! in_array($stopforumspam, $true_false) )
			{
				$Sender->Form->AddError('Invalid Selection for StopForumSpam.com');
			}
			
			if ( ! in_array($akismet, $true_false) )
			{
				$Sender->Form->AddError('Invalid Selection for Akismet');
			}
			
			if ( $akismetkey AND ! preg_match("/^[a-z0-9]+$/i", $akismetkey) )
			{
				$Sender->Form->AddError('Invalid Akismet Key');
			}
			
			if ( $akismet AND ! $akismetkey)
			{
				$Sender->Form->AddError('An Akismet Key is Required to Activate Akismet');
			}
			
			if ($akismetkey)
			{
				// Test Akismet Key
				$akismetcheck = new Akismet(Gdn::Request()->Url("/", TRUE), $akismetkey);
				if( ! $akismetcheck->isKeyValid())
				{
					$Sender->Form->AddError('Your Akismet Key has been checked at Akismet.com and appears to be invalid');
				}
			}
			
			if ( ! in_array($deletespam, $true_false) )
			{
				$Sender->Form->AddError('Invalid Selection for Delete Spam');
			}
			
			if ( ! $Sender->Form->Errors())
			{
				$SQL = Gdn::SQL();
				$SQL->Update('AntiSpam')
					->Set('StopForumSpam', $stopforumspam)
					->Set('Akismet', $akismet)
					->Set('AkismetKey', $akismetkey)
					->Set('DeleteSpam', $deletespam)
					->Where('ID', '1')
					->Put();
				$Sender->StatusMessage = T("Your settings have been saved.");
			}
		}
		
		// Get the Settings
		$rows = Gdn::SQL()->Select('*')
			->From('AntiSpam')
			->Where('ID', '1')
			->Get();
		
		// Get the Spam Log
		$Sender->SpamLog = Gdn::SQL()->Select('*')
			->From('AntiSpamLog')
			->OrderBy('DateInserted', 'DESC')
			->Get();
		
		$Sender->Settings = $rows->FirstRow();
		$Sender->Render($this->GetView('vanillaantispam.php'));
	}
	
	// Tag Discussions As They Are Created
	public function PostController_AfterDiscussionSave_Handler($Sender)
	{
		$this->_GetSettings();
		
		// Signed in users only.
		if (!($UserID = Gdn::Session()->UserID)) return;
	
		$Discussion = GetValue('Discussion', $Sender->EventArguments, 0);
		$DiscussionID = $Discussion->DiscussionID;
		$UserID = $Discussion->InsertUserID;
		$CommentID = 0;
		
		$Author = $this->_GetAuthor($UserID);
		$URL = "/discussion/".$DiscussionID."/".Gdn_Format::Url($Discussion->Name);
		
		if ($this->_IsSpam($Author->Name, $Author->Email, $Discussion->Body))
		{
			$SQL = Gdn::SQL();
			$SQL->Insert('AntiSpamLog',
				array(
					'UserID' => $UserID,
					'Name' => $Author->Name,
					'ForeignURL' => $URL,
					'ForeignID' => $DiscussionID,
					'ForeignType' => 'discussion',
					'IpAddress' => $this->ip_address,
					'DateInserted' => date('Y-m-d H:i:s')
				)
			);
		}
	}
	
	// Tag Comments As They Are Created
	public function PostController_AfterCommentSave_Handler($Sender)
	{
		$this->_GetSettings();
		
		// Signed in users only.
		if (!($UserID = Gdn::Session()->UserID)) return;
		
		$Comment = GetValue('Comment', $Sender->EventArguments, 0);
		$DiscussionID = $Comment->DiscussionID;
		$UserID = $Comment->InsertUserID;
		$CommentID = $Comment->CommentID;
		
		$Author = $this->_GetAuthor($UserID);
		$URL = "/discussion/comment/$CommentID/#Comment_$CommentID";
		
		if ($this->_IsSpam($Author->Name, $Author->Email, $Comment->Body))
		{
			$SQL = Gdn::SQL();		
			$SQL->Insert('AntiSpamLog',
				array(
					'UserID' => $UserID,
					'Name' => $Author->Name,
					'ForeignURL' => $URL,
					'ForeignID' => $CommentID,
					'ForeignType' => 'comment',
					'IpAddress' => $this->ip_address,
					'DateInserted' => date('Y-m-d H:i:s')
				)
			);
		}
	}
	
	private function _IsSpam($author = NULL, $email = NULL, $content = NULL)
	{		
		if ($this->stopforumspam_active AND $this->_SFS($email))
		{
			return TRUE;
		}
		
		if ($this->akismet_active AND $this->_Akismet($author, $email, $content))
		{
			return TRUE;
		}
		
		return FALSE;
	}
	
	private function _SFS($email = NULL)
	{
		include_once(PATH_PLUGINS.'/VanillaAntiSpam/class.stopforumspam.php');
		$stopforumspam = new StopForumSpam();
		$stopforumspam->setEmail($email);
		$stopforumspam->setUserIP($this->ip_address);
		if($stopforumspam->isCommentSpam())
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	private function _Akismet($author = NULL, $email = NULL, $content = NULL)
	{
		include_once(PATH_PLUGINS.'/VanillaAntiSpam/class.akismet.php');
		$akismet = new Akismet();
		
		$akismet = new Akismet(Gdn::Request()->Url("/", TRUE), $this->akismet_key);
		$akismet->setCommentAuthor($author);
		$akismet->setCommentAuthorEmail($email);
		$akismet->setUserIP($this->ip_address);
		$akismet->setCommentAuthorURL("");
		$akismet->setCommentContent($content);
		$akismet->setPermalink("");
		if($akismet->isCommentSpam())
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	public function Setup()
	{
		// Set UP Database Tables
		$Structure = Gdn::Structure();
		$Structure
			->PrimaryKey('ID')
			->Table('AntiSpam')
			->Column('StopForumSpam', 'tinyint(1)', '1')
			->Column('Akismet', 'tinyint(1)', '0')
			->Column('AkismetKey', 'varchar(150)')
			->Column('DeleteSpam', 'tinyint(1)', '0')
			->Set(FALSE, FALSE);

		$Structure
			->PrimaryKey('ID')
			->Table('AntiSpamLog')
			->Column('UserID', 'int(11)', FALSE, 'key')
			->Column('Name', 'varchar(64)')
			->Column('ForeignURL', 'varchar(255)', FALSE, 'key')
			->Column('ForeignID', 'int(11)')
			->Column('ForeignType', 'varchar(32)')
			->Column('IpAddress', 'varchar(150)')
			->Column('DateInserted', 'datetime')
			->Set(FALSE, FALSE);
		
		// Insert Defaults
		$SQL = Gdn::SQL();
		$exists = $SQL->Select('*')->From('AntiSpam')->Where('ID', '1')->Get();
		if ($exists->NumRows() == 0)
		{
			$SQL->Insert('AntiSpam',
				array(
					'StopForumSpam' => "1",
					'Akismet' => "0"
				)
			);
		}		
	}
	
	private function _GetAuthor($UserID = NULL)
	{
		$UserModel = new UserModel();
		return $UserModel->Get($UserID);
	}
	
	private function _GetSettings()
	{
		// Get Settings
		$rows = Gdn::SQL()->Select('*')
			->From('AntiSpam')
			->Where('ID', '1')
			->Get();
		
		$settings = $rows->FirstRow();
		$this->stopforumspam_active = $settings->StopForumSpam;
		$this->akismet_active = $settings->Akismet;
		$this->akismet_key = $settings->AkismetKey;
		$this->ip_address = $_SERVER['REMOTE_ADDR'] != getenv('SERVER_ADDR') ? $_SERVER['REMOTE_ADDR'] : getenv('HTTP_X_FORWARDED_FOR');
	}
	
	// Enable AntiSpam
	protected function _Enable() {
		SaveToConfig('Plugins.VanillaAntiSpam.Enabled', TRUE);
	}

	// Disable AntiSpam
	protected function _Disable() {
		RemoveFromConfig('Plugins.VanillaAntiSpam.Enabled');
	}	
}