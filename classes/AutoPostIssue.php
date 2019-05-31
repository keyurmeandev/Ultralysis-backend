<?php

namespace classes;
use db;
use config;
use lib;

class AutoPostIssue extends config\UlConfig{

	public $action;
		
	public function go($settingVars) {
        $this->initiate($settingVars);
        $this->settingVars = $settingVars;

        $this->action = $_REQUEST['action'];
        if($this->action == "ErrorToIssueTracker")
        {
            $this->ErrorToIssueTracker();
        }
        
		return $this->jsonOutput;
	}
	
    public function ErrorToIssueTracker()
    {
        $ultraUtility = lib\UltraUtility::getInstance();

        $userName = $_SESSION["username"];
        $accountID = $this->settingVars->aid; // 52
        $account = $this->settingVars->userID; // 288
        $projectID = $this->settingVars->projectID;
        $projectName = $this->settingVars->pageArray["PROJECT_NAME"];
        
        $error = $_REQUEST['error'];
        $errorDesc = "";
        foreach($error as $data)
            $errorDesc .= $data."<br />";
        
        $stepsToReproduce = "Menu Name: <br /> Page Name: ";
        $pageID = $_REQUEST['pageID'];
        if($pageID != "" && $pageID != "undefined") 
        {
            $query = "select pm_pages.pagetitle as pageName, pm_menus.menutitle as menuName from pm_menus, pm_pages, pm_assignpages WHERE pm_pages.pageID = pm_assignpages.pageID AND pm_menus.menuID = pm_assignpages.menuID AND pm_pages.pageID = ".$pageID." AND pm_pages.accountID = ".$accountID." AND pm_pages.projectID = ".$projectID." AND pm_menus.projectType = ".$this->settingVars->projectType." ";
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
            
            if(is_array($result) && !empty($result))
            {
                $menuName = $result[0]['menuName'];
                $pageName = $result[0]['pageName'];
                $stepsToReproduce = "Menu Name: ".$menuName." <br /> Page Name: ".$pageName;
            }
        }
        
        $userID = $ultraUtility->checkAndCreateUser($account, "user", $userName);
        
        if($userID > 0)
        {
            $issueParams = array(
                'userID' => $userID,
                'accountID' => $accountID,
                'projectID' => $projectID,
                'summary'   => 'Project Configuration Missing',
                'page_url'  => '',
                'platform'  => $_REQUEST['platform'],
                'browser'  => $_REQUEST['browser'],
                'description' => $errorDesc,
                'steps_to_reproduce' => $stepsToReproduce,
                'priority' => 5,
                'status' => 1,
                'fullRequest' => '',
                'created' => date('Y-m-d H:i:s', time()),
                'updated' => date('Y-m-d H:i:s', time())
            );
            
            $historyParams = array(
                'modified_by' => $userID,
                'modified_date' => date('Y-m-d H:i:s', time()),
                'action' => "New Issue",
                'description' => ''
            );
            
            $status = $ultraUtility->addNewIssue($issueParams, $historyParams);
            if($status)
                $this->jsonOutput['status'] = 1;
            else
                $this->jsonOutput['status'] = 0;
        }
    }
  
}
?>