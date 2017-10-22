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
 * \date 10 janvier 2017
*/
require_once NOALYSS_INCLUDE . '/lib/database.class.php';
require_once NOALYSS_INCLUDE . '/class/acc_bilan.class.php';
require_once BILAN_INTERNE_HOME . '/row_descriptor.class.php';


/*! 
 * \class Acc_Bilaninterne
 * \brief Generates a table containing lines data of a detailled balance sheet according to 
 * 
 * - a set of Noalyss formulas (./templates/bilaninterne.form file) 
 * - a set of structure infos (./templates/bilaninterne.rtf file). 
 * 
 * An array is first generated ("$result") and converted as HTML output. 
 */
class Acc_Bilaninterne extends Acc_Bilan
{
    public $bilan_table =[];
 
    function __construct($p_cn) 
    {
        parent::__construct($p_cn);
    }
    /*!Reads the variable value computed from the formula description file  
     * (bilaninterne.form) and stored in the object property with name "$variable_name"
     * \return the float value stored when right line type or 0.0
     */
    function get_montant($variable_name,$linetype)
    {
        if (($linetype ==="parent") || ($linetype==="combined"))
        {
            // if the prorperty is set, so a well formed formula has been parsed
            // from the "bilaninterne.form" file and a value is available
            if (isset($this->$variable_name))
            {
               $montant = floatval($this->$variable_name);
            }
            else
            {
                echo 'Undefinded variable in associated .form file';
                throw new Exception(_('Variable non définie dans le fichier .form associé à '.$this->filename));                       
            }
        }     
        else 
        {
            $montant = 0.0;
        }
        return($montant);
    }
    
    
     /*! Parses the "bilaninterne.csv" description file 
      * \param $csv the handle to the csv template file  
      * \return The table data to be displayed or exported
      * \brief For each line of the description file checks content coherency and extracts data.
      * It spreads a "code" row when a "flatten" flag is set in the desciption file 
      * and get from the database tables the associated info ans solde
      * 
      * The table result is stored in the "bilan_table" property
     */

    function parse_csvtemplate($csv)
    {        
        try
        {
            $descriptor = new row_descriptor();
            $line_num   = 0;
            while (($row = fgetcsv($csv, 0,",", '"')) !== false)
            {
                $line_num++;
                $bilan_row=[];
                $descriptor = new row_descriptor();
                $consistency=$descriptor->check_consistency($row, $line_num);
                if ($consistency)
                {  
                    $bilan_row = $descriptor->get_bilan_row();
                    $bilan_row['solde'] = $this->get_montant($descriptor->variable_name,$bilan_row['linetype']);
                    $this->bilan_table [] =$bilan_row;
                    if ($descriptor->flatten){
                        foreach ($descriptor->code_ranges as $range)
                        {
                            $code_left   = $range["left"];
                            $code_right  = $range["right"];
                            $leaf_postes = $this->get_range_postes($code_left,$code_right);
                            if (! is_null($leaf_postes)){
                                $this->bilan_table =array_merge($this->bilan_table,$leaf_postes);
                            }
                            else {
                            }
                        }
                    }
                    else{
                    }
                }
                else 
                {
                //first line and blank lines are skipped                     
                }
            }
        }
        catch (Exception $exc) {
            echo 'Erreur dans le fichier '.$this->csvfilename.' en ligne '.$line_num.' : '.$exc->getMessage();
        }
        return;
    }
        /*!Compute the "solde" amount of a "poste" and its sign depending on the pcmn type of the poste 
         * \param $pcm_type  : pcmn type associated with the poste 
         * \param $solde_deb 
         * \param $solde_deb
         * \return $solde 
        */
    function get_solde($pcm_type,$solde_deb,$solde_cred)
    {
        if ($pcm_type === 'CHA' || $pcm_type === 'CHAINV' || $pcm_type === 'ACT' || $pcm_type === 'ACTINV' || $pcm_type === 'CON'){
            $solde = $solde_deb - $solde_cred;
        }
        elseif ($pcm_type === 'PRO' || $pcm_type === 'PROINV' || $pcm_type === 'PAS' || $pcm_type === 'PASINV' ){
            $solde = $solde_cred - $solde_deb;
        }
        else {
            throw new Exception(_('Undefined PCMN TYPE for poste: '.$pcm_type));
        }
        return($solde);

    }
    /*!Creates sql request for getting the fields of "postes" inside an interval of poste
     * \param $code_left : low poste bound
     * \param $code_left : high poste bound
     * \return sql query
     */
    function get_sql_postes_range($code_left,$code_right)
    {
        global $g_user;
        $filter_sql=$g_user->get_ledger_sql('ALL',3);
        $from = $code_left;
        //$to   = $code_right;
        $to   = strval(intval($code_right)+1);

        // filter on requested periode
        $per_sql=sql_filter_per($this->db,$this->from,$this->to,'p_id','j_tech_per');

        $jrn ="";
        $and ="";
        //from_poste and to_poste complement queries
        $from_poste = " $and j_poste::text >= '".$from."'";
        $and =" and ";
        $to_poste   = " $and j_poste::text < '".$to."'";//TN "<=" -> "<"
        $and=" and ";
        $sql="select j_poste as poste, pcm_type, sum(deb) as sum_deb, sum(cred) as sum_cred from
              ( select j_poste, pcm_type,
              case when j_debit='t' then j_montant else 0 end as deb,
              case when j_debit='f' then j_montant else 0 end as cred
              from jrnx join tmp_pcmn on (j_poste=pcm_val)
              left join parm_periode on (j_tech_per = p_id)
                     join jrn_def on (j_jrn_def=jrn_def_id)
                     where
                     $jrn $from_poste $to_poste
                     $and $filter_sql
                     and
                     $per_sql ) as m group by j_poste,pcm_type order by 1";
        return($sql);

    }
    
    /*!generates a table of rows comptabile with bilan_table property,
     * \brief Each row is associated to a "poste" and contains usefull fields; 
     * each poste with non zero "solde" has its own row 
     * \param $code_left :low poste bound
     * \param $code_left : high poste bound
     * \return 2D array compatible with "bilan_table" property
     */
    function get_range_postes($code_left,$code_right)
    {
        $sql=$this->get_sql_postes_range($code_left,$code_right);
        $cn=clone $this->db;
        $Res=$this->db->exec_sql($sql);
        $M=$this->db->size();
        // Load the array
        $array =  null;
        for ($i=0; $i <$M;$i++)
        {
            $r=$this->db->fetch($i);
            $poste=new Acc_Account($cn,$r['poste']);
            $a['poste']=$r['poste'];
            $a['label']=mb_substr($poste->get_lib(),0,80);
            $sum_deb    =round($r['sum_deb'],2);
            $sum_cred   =round($r['sum_cred'],2);
            $solde_deb  =round(( $sum_deb >=  $sum_cred )? $sum_deb  - $sum_cred:0,2);
            $solde_cred =round(( $sum_deb <=  $sum_cred )? $sum_cred - $sum_deb :0,2); 
            $a['solde']=$this->get_solde($r['pcm_type'],$solde_deb,$solde_cred);
            $a['linetype']='leaf';
            $a['linestyle']=0;
            $array[$i]=$a;
        }//for i
        return $array;
    }

    /*! Check file before processing
     */
    function open_check($filename)
    {
        $file = fopen($filename, 'r');
        if ( $file == false)
        {
           echo 'Cannot open file ' . $filename;
           throw new Exception(_('Echec ouverture fichier ' . $filename));
        }
        return $file;
    }
     /*!Generates data to be output as a bilaninterne and stores it in table_bilan property
     */
    function generate()
    {
        // Process formulas from the ".form" file, store it as object properties
        $formulasfile =  BILAN_INTERNE_HOME.'/templates/bilaninterne.form';
        $formulas= $this->open_check($formulasfile); 
        $this->compute_formula($formulas);
        fclose($formulas);
        // Read, parse and filter the ".csv" template file producing an array
        $csvfilename = BILAN_INTERNE_HOME.'/templates/bilaninterne.csv';
        $this->csvfilename = $csvfilename;
        $csv= $this->open_check($csvfilename);
        $this->parse_csvtemplate($csv);
        fclose($csv);
        return;
    } 
 /*!
     * \brief return a string with the form for selecting the periode and
     * the type of bilan
     * \param $p_filter_year filter on a year
     *
     * \return a string
     */
    function display_form($p_filter_year="")
    {
        $r="";
        $r.=dossier::hidden();
        $r.= '<TABLE>';

        $r.='<TR>';
// filter on the current year
        $w=new ISelect();
        $w->table=1;

        $periode_start=$this->db->make_array("select p_id,to_char(p_start,'DD-MM-YYYY') from parm_periode $p_filter_year order by p_start,p_end");

        $periode_end=$this->db->make_array("select p_id,to_char(p_end,'DD-MM-YYYY') from parm_periode $p_filter_year order by p_end,p_start");

        $w->label=_("Depuis");
        $w->value=$this->from;
        $w->selected=$this->from;
        $r.= td($w->input('from_periode',$periode_start));
        $w->label=_(" jusque ");
        $w->value=$this->to;
        $w->selected=$this->to;
        $r.= td($w->input('to_periode',$periode_end));
        $r.= "</TR>";

        $r.= '</TABLE>';
        return $r;
    }    
    /*!
     * \brief get data from the $_GET
     *
     */
    function get_request_get()
    {
        $this->b_id=0;
        $this->from=( isset ($_GET['from_periode']))?$_GET['from_periode']:-1;
        $this->to=( isset ($_GET['to_periode']))?$_GET['to_periode']:-1;
    }
}