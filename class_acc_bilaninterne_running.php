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
    /*!
    * \brief Checks line consistency from the csv description file ("bilaninterne.csv")
    * \param $row, the current csv line
    * \param $line_num, the csv line number
    * \return true or false
    */
    function check_consistency($row,$line_num)
    {
        //process empty lines and first sight problems
        if($line_num==1)  {return(false);}
        if (count($row) == 1) {throw new Exception(_('Ligne vide'));}
        if (count($row)!=5)   {throw new Exception(_('Le nombre de champs est de '.count($row).' au lieu de 4'));}
        //check consistency of fields
        $empty0 = (trim($row[0]) == '');
        $empty1 = (trim($row[1]) == '');
        $empty2 = (trim($row[2]) == '');
        $empty3 = (trim($row[3]) == '');
        if ($empty0){
            //No line style so MUST BE empty line
            $empty_fields = $empty1 and $empty2 and $empty3;
            if ($empty_fields){
                return(false);
            }
            else {
                throw new Exception(_('Type de ligne vide mais autres valeurs présentes'));
            }
        }
        elseif ($empty2 xor $empty3){
            throw new Exception(_('Les champs code et varaibles doivent être vides simultanément'));
        }            
        return(true);
    }
    /*!
    * \brief Analyzes and converts cell content for processing 
    * 
    * - linestyle, rubrique, flatten cells    
    * Variable field is replaced by its value
    * \param $row, the current csv line
    * \param $line_num, the csv line number
    * \return array with line values and useful properties
    */
    function analyze_line($row,$line_num)
    { 
        //process "linestyle" field
        $linestyle=trim($row[0]);
        if (!is_numeric($linestyle)) {throw new Exception(_('Le style de ligne doit être un nombre'));}
        //Process "rubrique" field
        $rubrique=trim($row[1]);
        //Process "flatten" field
        $flatten=  (trim($row[4]) == 'yes') ? true : false;
        //Process "code" field and compute "linetype"
        $code=trim($row[2]);
        $code_left  = $code;
        $code_right = $code;
        $code_range["left"]  = $code;
        $code_range["right"] = $code;
        $code_ranges[0] = $code_range;
        if ($code == ''){
            // so $variable is also an empty string
            $linetype = 'tittle';
            $variable = '';
            $flatten  = false;
        }
        else {
            $pattern = "/([0-9]*)\/([0-9]*)/";
            if (is_numeric($code)){
                $linetype = 'parent';
              }
            elseif (preg_match($pattern,$code,$matches,0)>0){
                //BUILDING LEFT POSTE IS MORE SOPHISTICATED : ex: 172/3 !! exceptions !!
                // Normal building of left range from combined code ex: 172/3
                $linetype = 'combined';
                $code_left  = $matches[1];
                $code_right = $matches[2];
                $base_length= strlen($code_left)-strlen($code_right);
                $code_right = substr($code_left,0,$base_length).$code_right;
                if ($code_left == '174' && $code_right == '170'){
                    // special 'contextual dependent' syntax of code :-(
                    $code_ranges = array(
                        array("left"=>'170',"right"=>'171'),
                        array("left"=>'174',"right"=>'174'));
                }
                elseif ($code_left == '440' && $code_right == '444'){
                    // another special 'contextual dependent' syntax of code :-(
                    $code_ranges = array(
                        array("left"=>'440',"right"=>'441'),
                        array("left"=>'442',"right"=>'444'));
                }
                else{
                    //normal case
                    $code_ranges = array(
                    array("left"=>$code_left,"right"=>$code_right));                    
                }

            }
            else {
                //"code" field badly formed
                throw new Exception(_('Code malformé dans le fichier '));
            }
            //Process "variable" field
            $field3=trim($row[3]);
            $pattern="/<<\\$([a-zA-Z]*[0-9]*)>>/";
            if (preg_match($pattern,$field3,$matches,0)>0){
                //match found, framing characters ignored
                $variable = $matches[1];
            }
            else {
                throw new Exception(_('Variable malformée dans le fichier '));                       
            }
        }   
        return(
                array(
                    "linetype"   => $linetype,
                    "linestyle"  => intval($linestyle),
                    "rubrique"   => $rubrique,
                    "code"       => $code,
                    "code_ranges"=> $code_ranges,
                    "variable"   => $variable,
                    "flatten"    => $flatten,
                    "montant"    => 0.00));
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
    function parse_csvtemplate($csv){
        
        /*parse_csvfile body*/
        try
        {
            //Get header row
            $line_num=0;
            $bilan_array=NULL;
            while (($row = fgetcsv($csv, 0,",", '"')) !== false){
                $line_num++;
                if ($this->check_consistency($row, $line_num)){
                    $result = $this->analyze_line($row,$line_num);
                    if (($result['linetype']=='parent') || ($result['linetype']=='combined')){
                        $variable_name = $result['variable'];
                        if (isset($this->$variable_name)){
                           $result['montant'] = floatval($this->$variable_name);
                        }
                        else{
                            echo 'Undefinded variable in associated .form file';
                            throw new Exception(_('Variable non définie dans le fichier .form associé à '.$filename));                       
                        }
                    }
                    $bilan_array[]=$result;
                }
                else {
                //first line and blank lines are skipped                     
                }
            }
        }
        catch (Exception $exc) {
            echo 'Erreur dans le fichier '.$filename.' en ligne '.$line_num.' : '.$exc->getMessage();
        }
        return($bilan_array);
    }
       /*!
       * \brief Expand the "bilan" array with associated "leaf postes" 
        */
    function get_range_postes($code_left,$code_right){
        global $g_user;
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
            $a['sum_deb']=round($r['sum_deb'],2);
            $a['sum_cred']=round($r['sum_cred'],2);
            $a['solde_deb']=round(( $r['sum_deb']  >=  $r['sum_cred'] )? $r['sum_deb']- $r['sum_cred']:0,2);
            $a['solde_cred']=round(( $r['sum_deb'] <=  $r['sum_cred'] )? $r['sum_cred']-$r['sum_deb']:0,2); //(TN)$r is right member would be better
            //if ($p_previous_exc==0 && $this->unsold==true && $a['solde_cred']==0 && $a['solde_deb']==0) continue;
            //$pcm_type = $a('type');
            $pcm_type = $r['pcm_type'];
            if ($pcm_type === 'CHA' || $pcm_type === 'CHAINV' || $pcm_type === 'ACT' || $pcm_type === 'ACTINV' || $pcm_type === 'CON'){
                $a['solde'] = $a['solde_deb'] - $a['solde_cred'];
            }
            elseif ($pcm_type === 'PRO' || $pcm_type === 'PROINV' || $pcm_type === 'PAS' || $pcm_type === 'PASINV' ){
                $a['solde'] = $a['solde_cred'] - $a['solde_deb'];
            }
            else {
                throw new Exception(_('Undefined PCMN TYPE for poste: '.$pcm_type));
            }
            $a['linetype']='leaf';
            $a['linestyle']=0;
            $array[$i]=$a;
        }//for i
        return $array;
    }
    function add_leaf_postes($table_bilan){        
        /*!
        * \brief For a given "parent poste" generates the associated "leaf postes" 
         * and saldos as an array
        */
        //main
        $result=array();
        foreach ($table_bilan as $row){
            //print_r($row);
            $bnb_line = array(
                array(
                    'linetype'   => $row['linetype'],
                    'linestyle'  => $row['linestyle'],
                    'poste'      => $row['code'],
                    'label'      => $row['rubrique'],
                    'sum_cred'   => 0.00,
                    'sum_deb'    => 0.00,
                    'solde_cred' => 0.00,
                    'solde_deb'  => 0.00,
                    'solde'      => round(floatval($row['montant']),2)
                    )
                );
            $result =array_merge($result,$bnb_line);
            if ($row["flatten"]){
                foreach ($row["code_ranges"] as $range){
                    $code_left  = $range["left"];
                    $code_right = $range["right"];
                    $leaf_postes = $this->get_range_postes($code_left,$code_right);
                    if (! is_null($leaf_postes)){
                        $result =array_merge($result,$leaf_postes);
                    }
                    else {
                    }
                }
            }
            else{
            }
        }
        return($result);
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
        // Process formulas from the ".form" file
        $formulasfile =  NOALYSS_PLUGIN. '/bilan_interne/templates/bilaninterne.form';
        $formulas= $this->open_check($formulasfile); 
        $this->compute_formula($formulas);
        fclose($formulas);

        // Read, parse and filter the ".csv" template file producing an array
        $csvfilename = NOALYSS_PLUGIN. '/bilan_interne/templates/bilaninterne.csv';
        $csv= $this->open_check($csvfilename);
        $table_bilan = $this->parse_csvtemplate($csv);
        fclose($csv);
        // Expand the leaves "postes" from the parents posted defined in template
        $result = $this->add_leaf_postes($table_bilan);
        return($result);
    }  
    
    function output_html($cn,$result){
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
            foreach ($result as $r){
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
    
    function output_pdf($array,$cn){
        $gDossier=dossier::id();
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
        foreach ($array as $r){
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
    
    function output_csv($result,$cn){
        //echo 'CSV output of Bilan Interne is under development <br>';
        require_once NOALYSS_INCLUDE. '/lib/class_noalyss_csv.php'; 
        $csv = new Noalyss_Csv("bilaninterne");
        $csv->send_header();
        $line_header =  array('linetype','linestyle','label','poste','solde');
        $csv->write_header($line_header);
        $r = "";
        //print_r($result);
        foreach ($result as $row) {
            foreach ($line_header as $key) {
                $csv->add($row[$key],"text");
            }
            //echo "\n";
            $csv->write();
        }
        return($r);
    }
}

