<?php
namespace classes;
use db;
use config;
use lib;

class SendMail extends config\UlConfig{

	// EMAIL SENDING VARIABLE
    public $to         ="data@ultralysis.com";
	public $subject    ="Ask a question";
	public $mailSmtp;
	public $action;
	public $settingVars;
	
	// USER INFORMATION VARIABLE
	public $name;
	public $email;
	public $mobile;
	public $message;
		
	public function go($settingVars) {
        $this->settingVars = $settingVars;

        $this->action = $_REQUEST['action'];
        if($this->action == "ErrorToIssueTracker")
        {
            $this->prepareAndSendErrorReportEmail();
        }
        else
        {
            // SET USER INFORMATION
            $this->name 	= $_REQUEST['userName'];
            $this->email 	= $_REQUEST['userEmail'];
            $this->mobile 	= $_REQUEST['userMobile'];
            $this->message 	= $_REQUEST['userMessage'];

            $this->prepareAndSendEmail();
        }
		return $this->jsonOutput;
	}
	
	/**
     * cofigureEmailBody()
     * It will prepare email body dynamically as per parameter received
     *
     * @return string
     */
	public function cofigureEmailBody() 
	{
        $projectName = $this->settingVars->pageArray["PROJECT_NAME"];
		$msg = "<p>Please find below details posted through \"Ask a question?\" form.</p>";
        $msg .= "<p>Name: ".$this->name."</p>";
        $msg .= "<p>Email: ".$this->email."</p>";
        $msg .= "<p>Mobile: ".$this->mobile."</p>";
        $msg .= "<p>Message: ".$this->message."</p>";
        $msg .= "<p></p><p>This question is generated from ".$projectName.".</p>";
        $msg .= "<p></p><p></p><p>Thanks.</p>";
		
		return $msg;
	}
	
	/**
     * setMailBody()
     * It will set email body to SMTP PHPMailer
     *
     * @return void
     */
	private function setMailBody()
	{
		$this->mailSmtp->Body = $this->cofigureEmailBody();
	}
	
	/**
     * setMailSubject()
     * It will set email subject to SMTP PHPMailer
     *
     * @return void
     */
	private function setMailSubject()
	{
		$this->mailSmtp->Subject = $this->subject;
	}
	
	/**
     * setRecipient()
     * It will add recipients to SMTP PHPMailer
     *
     * @return void
     */
	private function setRecipient()
	{
		$this->mailSmtp->addAddress($this->to);
	}
	
	/**
     * setMailBody()
     * It will set email body to SMTP PHPMailer
     *
     * @return void
     */
	private function setFrom()
	{
        $this->mailSmtp->From       = $this->email;
        $this->mailSmtp->FromName   = $this->name;
	}
			
	/**
     * sendMail()
     * It will send email as per configuration using SMTP PHPMailer
     *
     * @return boolean
     */
	private function sendMail()
	{
		$this->mailSmtp->WordWrap = 50;
        $this->mailSmtp->IsHTML(true);
		
		if (!$this->mailSmtp->Send())
            $this->jsonOutput['status'] = 0;
        else
			$this->jsonOutput['status'] = 1;

        return;
	}
	
	/**
     * prepareAndSendEmail()
     * It will prepare email parts and send email
     *
     * @return boolean
     */
	public function prepareAndSendEmail() 
	{
		if($this->name != '' && $this->email != '' && $this->mobile != '' && $this->message != '') {
			$smtp = new lib\Smtpconfig();
			$this->mailSmtp = $smtp->getInstance();
			$this->setFrom();
			$this->setMailBody();
			$this->setMailSubject();
			$this->setRecipient();
			$this->sendMail();
		}
		else
			$this->jsonOutput['message'] = "Please enter required fields.";

		return;
	}	
	
    public function prepareAndSendErrorReportEmail()
    {
        $smtp = new lib\Smtpconfig();
        $this->mailSmtp = $smtp->getInstance();
        $this->ContentType  = 'MIME-Version: 1.0' . "\r\n";
        $this->ContentType .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
        
        $this->projectName = $this->settingVars->pageArray["PROJECT_NAME"];
        
        $cid = $this->settingVars->aid;
        $ultraUtility    = lib\UltraUtility::getInstance();
        $result = $ultraUtility->getClients(array($cid), true);
        if (is_array($result) && !empty($result))
            $this->companyName = $result[0]["name"];
        else
            $this->companyName = "";        
        
        // set From
        $this->mailSmtp->From       = $_SESSION["email"];
        $this->mailSmtp->FromName   = $this->companyName;
        
        // set email body
        $this->mailSmtp->Body = $this->cofigureErrorReportEmailBody();
        
        // set email subject
        $this->mailSmtp->Subject = "Project Error - " . $this->companyName." - ".$this->projectName;
        
        // set to
        $this->mailSmtp->addAddress($this->to); // jewelmajumder@gmail.com
        
        $this->sendMail();
    }
  
	public function cofigureErrorReportEmailBody()
	{
        $email = $_SESSION["email"];
        $username = $_SESSION["username"];
        $accountID = $_SESSION["accountID"];
        $account = $_SESSION["account"];
        $projectType = $_REQUEST['projectType'];
        $projectID = $this->settingVars->projectID;
        $link = $_REQUEST['url'];
        $menuName = "";
        $pageName = "";
        
        $pageID = $_REQUEST['pageID'];
        if($pageID != "" && $pageID != "undefined") 
        {
            $query = "select pm_pages.pagetitle as pageName, pm_menus.menutitle as menuName from pm_menus, pm_pages, pm_assignpages WHERE pm_pages.pageID = pm_assignpages.pageID AND pm_menus.menuID = pm_assignpages.menuID AND pm_pages.pageID = ".$pageID." AND pm_pages.accountID = ".$accountID." AND pm_pages.projectID = ".$projectID." AND pm_menus.projectType = ".$this->settingVars->projectType." ";
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
            
            if(is_array($result) && !empty($result))
            {
                $menuName = $result[0]['menuName'];
                $pageName = $result[0]['pageName'];
            }
        }
        
		$msg = "<p>Please find below details posted through \"Error Report\".</p>";
        $msg .= "<p>Email: ".$email."</p>";
        $msg .= "<p>Username: ".$username."</p>";
        $msg .= "<p>User ID: ".$account."</p>";
        $msg .= "<p>Company ID: ".$accountID."</p>";
        $msg .= "<p>Company Name: ".$this->companyName."</p>";
        $msg .= "<p>Project Type: ".$projectType."</p>";
        $msg .= "<p>Project ID: ".$projectID."</p>";
        $msg .= "<p>Project Name: ".$this->projectName."</p>";
        $msg .= "<p>Menu Name: ".$menuName."</p>";
        $msg .= "<p>Page Name: ".$pageName."</p>";
        //$msg .= "<p>Project URL: <a href='".$link."' title='Click to open project'>Project URL</a></p>";
        $msg .= "<p></p><p></p><p>Thanks.</p>";
        
		return $msg;
	}
    
    public function ErrorToIssueTracker()
    {
        $email = "lcl@arla.com"; //$_SESSION["email"];
        $username = "Demo User"; //$_SESSION["username"];
        $accountID = 52; //$_SESSION["accountID"];
        $account = 288; //$_SESSION["account"];
        $projectID = $this->settingVars->projectID;
        $projectName = $this->settingVars->pageArray["PROJECT_NAME"];
    }
  
}
?>