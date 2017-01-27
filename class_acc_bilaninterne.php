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
require_once NOALYSS_INCLUDE.'/lib/class_database.php';
require_once NOALYSS_INCLUDE.'/class/class_acc_bilan.php';
require_once NOALYSS_INCLUDE.'class_row_descriptor.php';


/*! 
 * \class Acc_Bilaninterne
 * \brief Generates an detailled balance sheet according to 
 * 
 * - a set of Noalyss formulas (./templates/bilaninterne.form file) 
 * - a set of structure infos (./templates/bilaninterne.rtf file). 
 * 
 * An array is first generated ("$result") and converted as HTML output. 
 */
class Acc_Bilaninterne extends Acc_Bilan
{
    private $csv_row   = [];
    private $bilan_row = [];
    function __construct($p_cn) 
    {
        parent::__construct($p_cn);
        $csv_row = [
            "linestyle" => 0,
            "rubrique"  => "",
            "code"      => "",
            "variable"  => "",
            "flatten"   => false
        ];
        $bilan_row = [
                    "linestyle"   => 0,
                    "linetype"    => "",
                    "label"       =>"",
                    "poste"       => "",
                    "montant"     => 0.00 ];
        $bilan_table =[];
    }
    /*!
    * \brief Analyzes and converts content of csv row  (file "bilaninterne.csv") 
    * 
    * - linestyle, rubrique, flatten cells    
    * Variable field is replaced by its value
    * \param $row, the current csv line
    * \param $line_num, the csv line number
    * \return array with line values and useful properties
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
        return($montant);
    }
    
    
    /*!
     * \brief Parses a csv template file describing the structure of the detailled balance sheet
     * The first row of the csv file is a header used for documentation.
     * The following rows describe the formatting and data to be output for associated accounting item range. Blank rows are allowed
     * The first data column contains a the depth of the item in the structured balance sheet (0 is the top level). Its used to format the output.
     * The second column is the text to be display for the given item
     * The third column  column describes the items range associated the row
     * The fourth column indicates the formula to be used to compute the amount associated to the item range.
     * This formula must be describe in the "bilaninterne.form" file. The syntax of the is the one used by Noalyss for the bilan
     * The fifth column must be either the "yes" either "no" according to the item range must be spreaded or not.
     * \param $csv the handle to the csv template file  
     * \return A filtered array of csv template content
     */
    function parse_csvtemplate($csv)
    {        

        try
        {
            $line_num=0;
            while (($row = fgetcsv($csv, 0,",", '"')) !== false)
            {
                $line_num++;
                $bilan_row = new row_descriptor($row);
                if ($bilan_row->csv_check_consistency($row, $line_num))
                {   
                    $code_ranges = $bilan_row->get_code_ranges();
                    $variable_name = $this->variable_name();
                    $this->bilan_row["montant"] = get_montant($variable_name,$code_ranges);
                    $this->bilan_table[]=$this->bilan_row;
                    if ($this->csv_row["flatten"]){
                        foreach ($code_ranges as $range){
                            $code_left  = $range["left"];
                            $code_right = $range["right"];
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
            echo 'Erreur dans le fichier '.$filename.' en ligne '.$line_num.' : '.$exc->getMessage();
        }
        return;
    }
        /*!
        * \brief Expand the "bilan" array with associated "leaf postes" 
        */
    //!!!!!!!!!!!!!!!!!!!!!
    function get_sql_postes_range($code_left,$code_right)
    {
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
        //build filter_sql (?)
        $filter_sql=$g_user->get_ledger_sql('ALL',3);
        // build query
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
        retturn($sql);
    }
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
    function get_range_postes($code_left,$code_right){
        $cn=clone $this->db;
        $this->db->exec_sql($this->get_sql_postes_range($code_left,$code_right));
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
            $a["solde"]=$this->get_solde($r['pcm_type'],$solde_deb,$solde_cred);
            $a["linetype"]='leaf';
            $a["linestyle"]=0;
            $array[$i]=$a;
        }//for i
        return $array;
    }
    function open_check($filename){
        $file = fopen($filename, 'r');
        if ( $file == false)
        {
           echo 'Cannot open file ' . $filename;
           throw new Exception(_('Echec ouverture fichier ' . $filename));
        }
        return $file;
    }
    function generate(){
        // Process formulas from the ".form" file, store it as object properties
        $formulasfile =  NOALYSS_PLUGIN. '/bilan_interne/templates/bilaninterne.form';
        $formulas= $this->open_check($formulasfile); 
        $this->compute_formula($formulas);
        fclose($formulas);

        // Read, parse and filter the ".csv" template file producing an array
        $csvfilename = NOALYSS_PLUGIN. '/bilan_interne/templates/bilaninterne.csv';
        $csv= $this->open_check($csvfilename);
        $this->parse_csvtemplate($csv);
        fclose($csv);
        //$result = $this->add_leaf_postes($table_bilan);
        return;
    }  
    
    function output_html($cn){
        $gDossier=dossier::id();
        $periode=new Periode($cn);
        $date_from=$periode->first_day($this->from);
        $date_to=$periode->last_day($this->to);
        echo '<div class="content">';
            echo '<hr>';
            // Display results

            echo "<h2 class=\"info\"> période du ".$date_from." au ".$date_to."</h2>";
            echo '<table id="t_balance" width="90%">';
                echo '<th>Libell&eacute;</th>';
                echo '<th>Poste Comptable</th>';
                echo '<th>Solde</th>';
            $i=0;
            bcscale(2);
            foreach ($this->bilan_table as $r){
                //print_r($r);echo '<br>';
                $i++;
                //Line background
                if ( $i%2 == 0 )
                    $tr="even";
                else
                    $tr="odd";
                //Javascript "view_history" set
                $view_history= sprintf('<A class="detail" style="text-decoration:underline" HREF="javascript:view_history_account(\'%s\',\'%s\')" >%s</A>',
                                       $r['poste'], $gDossier, $r['poste']);

                if ($r['linetype'] != 'leaf'){
                    if ($r['linetype'] == 'tittle'){
                        $tittle_style = 'style="font-weight:bold;font-size:150%;text-align:center;"';
                        //echo $style.'<br>';
                        echo '<TR >';
                            echo td($r['label'], $tittle_style);
                            echo td('','style="font-weight:bold;"');
                            echo td('','style="font-weight:bold;text-align:right;"');
                        echo '</TR>';
                    }
                    else {
                        $indent_step= 20;
                        $indent_length = $indent_step * ($r['linestyle'] - 1);
                        $indent_style = 'padding-left:'.strval($indent_length) . 'px;';
                        $indent_leaf_style = 'padding-left:' . strval($indent_length+$indent_step) . 'px;';
                        echo '<TR >';
                            echo td($r['label'],'style="font-weight:bold;'.$indent_style.'"');
                            echo td($r['poste'],'style="font-weight:bold;"');
                            echo td(nbm($r['solde']),'style="font-weight:bold;text-align:right;"');
                        echo '</TR>';
                    }
                }
                else {
                    echo '<TR class="'.$tr.'">';
                        //echo td(h($justification.$decalage.$r['label']));
                        echo td(h($r['label']),'style="' . $indent_leaf_style . '"');
                        echo td($view_history);
                        echo td(nbm($r['solde']),'style="text-align:right;"');
                    echo '</TR>';
                }
            }
            echo '</table>';
        echo '</div>';
    }    
    
    function output_pdf($cn){
        $id = dossier::id();
        $periode=new Periode($cn);
        $date_limit_start=$periode->first_day($this->from);
        $date_limit_end=$periode->last_day($this->to);

        $per_text="  du ".$date_limit_start." au ".$date_limit_end;
        $pdf= new PDF($cn);
        $pdf->setDossierInfo(" Bilan interne  ".$per_text);
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetAuthor('NOALYSS');
        $pdf->SetFont('DejaVuCond','',7);
        $pdf->setTitle("Balance interne",true);

        $pdf->LongLine(140,6,'Libellé');
        $pdf->Cell(25,6,'poste');
        $pdf->Cell(25,6,'Montant',0,0,'R');
        $pdf->Ln();

        bcscale(2);
        $i=0;
        foreach ($this->bilan_table as $r){
            $pdf->SetFont('DejaVuCond','',8);
            //Line background
            if ( $i%2 == 0 ){
                $pdf->SetFillColor(220,221,255);
                $fill="even";
            }
            else {
                $fill="odd";
                $pdf->SetFillColor(255,255,255);
            }
            $i++;
            if ($r['linetype'] != 'leaf'){
                $i=0;
                if ($r['linetype'] == 'tittle'){
                    //Tittle line
                    $pdf->SetFont('DejaVu','B',8);
                    $pdf->LongLine(190,6,$r['label']);
                }
                else {
                    //BNB synthetic line
                    $pdf->SetFont('DejaVu','BI',7);                
                    $pdf->Cell(140,6,$r['label'],0,0,'L',0,0);
                    $pdf->Cell(25,6,$r['poste'],0,0,'L',0);
                    $pdf->Cell(25,6,nbm($r['solde']),0,0,'R',0);
                }
            }
            else {
                //Leaf rows building
                $pdf->Cell(140,6,$r['label'],0,0,'L',$fill);
                $pdf->Cell(25,6,$r['poste'],0,0,'L',$fill);
                $pdf->Cell(25,6,nbm($r['solde']),0,0,'R',$fill);
            }
            $pdf->Ln();
        }
        $fDate=date('dmy-Hi');
        $pdf->Output('bilaninterne-'.$fDate.'.pdf','D');
    }
    
    function output_csv($cn){
        //echo 'CSV output of Bilan Interne is under development <br>';
        require_once NOALYSS_INCLUDE. '/lib/class_noalyss_csv.php'; 
        $csv = new Noalyss_Csv("bilaninterne");
        $csv->send_header();
        $line_header =  array('linetype','linestyle','label','poste','solde');
        $csv->write_header($line_header);
        $r = "";
        //print_r($result);
        foreach ($this->bilan_table as $row) {
            foreach ($line_header as $key) {
                $csv->add($row[$key],"text");
            }
            //echo "\n";
            $csv->write();
        }
        return($r);
    }
}

