<?php
namespace config;

class PreventInjection{

    private $check_inject_reasult;
    private $blackInput;
    
    public function __construct(){
        $this->check_inject_reasult = 0;
    }
    
    public function user_injection_check($key,$input){
			$this->blackInput = array();
			$this->blackInput[] = preg_match('/(\bor\b|\bnull\b)/i', $input); // no sqli boolean keywords
			$this->blackInput[] = preg_match('/(\bunion|\bselect\b|\bwhere\b)/i', $input); // no sqli select keywords
			$this->blackInput[] = preg_match('/(\border\b|\bhaving\b|\blimit\b)/i', $input); //  no sqli select keywords
			$this->blackInput[] = preg_match('/(\binto\b|\bfile\b)/i', $input); // no sqli operators
			$this->blackInput[] = preg_match('/(\b--\b|\/\*)/', $input);  // no sqli comments 
			$this->blackInput[] = preg_match('/(=|\|)/', $input); // no boolean operators

            foreach ($this->blackInput as $value) {
                    if ($value == 1){
                            $this->check_inject_reasult = 1;
                            die("Invalid Input: $key = <font color='red'>$input</font>");
                    }
            }
        return $input;
    }
}	
?>