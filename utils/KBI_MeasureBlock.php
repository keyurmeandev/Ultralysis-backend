<?php
namespace utils;
class KBI_MeasureBlock
{    
    public $measureName;
    
    public $ty;
    public $ly;
    public $pp;
    
    public $ly_var_varPct;
    public $pp_var_varPct;    
    
    
    public function __construct(){
    }
    
    public function createMeasureBlock($measureName,$data){
        $this->measureName  = $measureName;
        
        $this->ty     = $data['TY'.$measureName];
        $this->ly     = $data['LY'.$measureName];
        $this->pp     = $data['PP'.$measureName];
        
        $this->ly_var_varPct = new var_varPct($this->ty,$this->ly);
        $this->pp_var_varPct = new var_varPct($this->ty,$this->pp);   
    }
}


class var_varPct
{
    public $var;
    public $varPct;
    public function __construct($ty,$ly){
       $this->var = $ty - $ly;
       $this->varPct = $ly != 0 ? ($this->var/$ly)*100 : 0;
    }
};

?>