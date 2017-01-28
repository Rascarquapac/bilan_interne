<?php
/*
 *   This file is part of NOALYSS.
 *
 *   NOALYSS is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 *   NOALYSS is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with NOALYSS; if not, write to the Free Software
 *   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/*!
 * \file class_acc_bilaninterne.php
 * \brief Description of the bilaninterne subclass of bilan class
 * \author T. Nancy
 * \version 0.1
 * \date 10 janvier 201
*/
require_once NOALYSS_INCLUDE.'/lib/class_database.php';
require_once NOALYSS_INCLUDE.'/class/class_acc_bilan.php';

/*! 
 * \class Acc_Bilaninterne
 * \brief Generates an detailled balance sheet according to 
 * 
 * - a set of Noalyss formulas (./templates/bilaninterne.form file) 
 * - a set of structure infos (./templates/bilaninterne.rtf file). 
 * 
 * An array is first generated ("$result") and converted as HTML output. 
 */
class row_descriptor
{
    public $linestyle   = 0;
    public $label       = "";
    public $code        = "";
    public $code_ranges =[];
    public $variable_form = "";
    public $variable_name = "";
    public $flatten = false;
    public $solde =0.0;

    function __construct() 
    {
    }
    /*!
    * \brief Checks line consistency of csv row (file "bilaninterne.csv")
    * \param $row, the current csv line
    * \param $line_num, the csv line number
    * \return true or false
    */
    function check_consistency($row,$line_num)
    {
        //Forget first row (header)
        if($line_num==1)  {return(false);}
        //Abort when empty row
        if (count($row) == 1) {throw new Exception(_('Ligne vide'));}
        //Abort when incorrect field number
        if (count($row)!=5)   {throw new Exception(_('Le nombre de champs est de '.count($row).' au lieu de 4'));}
        //check consistency of empty fields or abort
        $linestyle = trim($row[0]);
        $rubrique  = trim($row[1]);
        $code      = trim($row[2]);
        $variable  = trim($row[3]);
        $flatten   = trim($row[4]);
        if ($linestyle == ''){
            //First field is empty string so all fields must be empty ... or abort
            $empty_fields = ($rubrique == '') and ($code == '') and ($variable == '');
            if ($empty_fields){
                return(false);
            }
            else {
                throw new Exception(_('Champ "linetype" vide mais autres valeurs présentes'));
            }
        }
        elseif (($code== '') xor ($variable== '')){
            throw new Exception(_('Les champs "code" et "variable" doivent être vides simultanément'));
        }  
        //first field must be a number or abort
        if (!is_numeric($linestyle)) {throw new Exception(_('Le style de ligne doit être un nombre'));}
        
        $this->linestyle     = intval($linestyle);
        $this->label         = $rubrique;
        $this->code          = $code;
        $this->variable_form = $variable;
        $this->flatten       = ($flatten == 'yes') ? true : false;
        return(true);
    }
                //BUILDING LEFT POSTE IS MORE SOPHISTICATED : ex: 172/3 !! exceptions !!
                // Normal building of left range from combined code ex: 172/3
    function composed_ranges($code_left,$code_right)
    {
        $base_length= strlen($code_left)-strlen($code_right);
        $code_right = substr($code_left,0,$base_length).$code_right;
        $code_ranges = [['left' => $code_left,'right' => $code_right]];
        if ($code_left == '174' && $code_right == '170'){
            // special 'contextual dependent' syntax of code :-(
            $code_ranges = [
                ['left'=>'170','right'=>'171'],
                ['left'=>'174','right'=>'174']];
        }
        elseif ($code_left == '440' && $code_right == '444'){
            // another special 'contextual dependent' syntax of code :-(
            $code_ranges = [
                ['left'=>'440','right'=>'440'],
                ['left'=>'442','right'=>'444']];
        }
        return($code_ranges);
    }
    
    function code_ranges($code)
    {
        if ($code == '') {
            $code_ranges  = [];
        }
        else {
            $pattern = "/([0-9]*)\/([0-9]*)/";
            $matches =[];
            if (is_numeric($code)) {
                $code_ranges  = [["left"=>$code, "right"=>$code]];
            }
            elseif (preg_match($pattern,$code,$matches,0)>0){
                $code_ranges = $this->composed_ranges($matches[1],$matches[2]);
            }
            else {
                //"code" field badly formed
                throw new Exception(_('Code malformé dans le fichier '));
            }
        }
        return ($code_ranges);
    }
    function linetype($code_ranges)
    {
        $count = count($code_ranges);
        if     ($count === 0) {return("tittle");}
        elseif ($count > 1)   {return("combined");}
        elseif ($code_ranges[0]["left"] === $code_ranges[0]["right"])  {return("parent");}
        else {return ("combined");}
    }
    function variable_name($linetype,$variable_form)
    {
        if ($linetype === "tittle")
        {
            $variable_name = "";
        }
        else
        {
            $pattern="/<<\\$([a-zA-Z]*[0-9]*)>>/";
            $matches=[];
            if (preg_match($pattern,$variable_form,$matches,0)>0){
                //match found, framing characters ignored
                $variable_name = $matches[1];
            }
            else {
                throw new Exception(_('Variable malformée dans le fichier '));                       
            }
        } 
        return($variable_name);
    }
    
    function get_bilan_row()
    {
        $bilan_row =[];
        $this->code_ranges = $this->code_ranges($this->code);
        $linetype= $this->linetype($this->code_ranges);
        $this->variable_name = $this->variable_name($linetype,$this->variable_form);
        $bilan_row ['linestyle'] =$this->linestyle;
        $bilan_row ['linetype'] =$linetype;
        $bilan_row ['label'] =$this->label;
        $bilan_row ['poste'] =$this->code;
        $bilan_row ['solde'] = 0.0;
        
        return($bilan_row);    
    }
}