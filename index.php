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
 * \file index.php
 * \brief Generation of a detailled balance sheet with export in CSV or PDF. This file 
 * manages
 * 
 * 1. display of HTML form for user choices
 * 2. installation/update of the plugin "bilaninterne"
 * 3. generation of the HTML output
 * 4. redirection to the csv and pdf export functions
 * \author T. Nancy
 * \version 0.1
 * \date 10 janvier 2017
 */
if ( ! defined ('ALLOWED') ) {die('Appel direct non permis');}
require_once NOALYSS_INCLUDE.'/class/class_exercice.php';
require_once NOALYSS_INCLUDE.'/lib/class_database.php';
require_once NOALYSS_INCLUDE.'/ext/bilan_interne/class_acc_bilaninterne.php';
require_once NOALYSS_INCLUDE.'/ext/bilan_interne/class_output_bilaninterne.php';
require_once NOALYSS_INCLUDE.'/ext/bilan_interne/include/class_install_plugin.php';
   
global $g_user;
global $cn;

function exercice_selection_form($exercice)
/*! Builds the form dedicated to the exercice selection 
 * \param $exercice The current exercice
 * \return The resulting HTML form
 */
{
    global $cn;
    // Build $wex, an ISelect widget for exercice selection
    $ex=new Exercice($cn);
    $wex=$ex->select('exercice',$exercice,' onchange="submit(this)"');
 
    $form  = '<fieldset><legend>'._('Exercice').'</legend>';
        $form .= '<form method="GET">';
            $form .= _('Choisissez un autre exercice');
            $form .= $wex->input();
            $form .= dossier::hidden();
            $form .= HtmlInput::get_to_hidden(array('ac','type'));
        $form .= '</form>';
    $form .= '</fieldset>';
    return ($form);
 }    
function periods_selection_form($bilan,$exercice)
/*!Builds the form dedicated to periods selection 
 * \param $bilan The bilan object and the selected exercice
 * \param $cn The exercice to be processed object and the selected exercice
 * \return The resulting HTML form
 */
{
    $filter_year=" where p_exercice='".sql_string($exercice)."'";
    $form  = '<form  method="GET">';
        $form .= HtmlInput::hidden('type','bilan');
        $form .= $bilan->display_form ($filter_year);
        $form .= HtmlInput::submit('bilaninterne',_('Etablir bilan interne'));
        $form .= HtmlInput::get_to_hidden(array('ac','exercice'));
    $form .= '</form>';
    return ($form);
}
function exportpdf_submit_button($bilan)
/*! Builds form dedicated to "Export PDF" submit button
 * \brief Builds form dedicated to "Export PDF" submit button
 * \brief It stores parameters $_GET parameters for export.php processing
 * \param $bilan The bilan object
 * \return The resulting HTML form
 * \brief Store parameters $_GET parameters for export.php processing
 */
{
    $form  =  '<form method="GET" action=' .'export.php' .'>';
    $form .=    dossier::hidden();
    $form .=    HtmlInput::hidden("ac",$_REQUEST['ac']);
    $form .=    HtmlInput::hidden("act","PDF:bilaninterne");
    $form .=    HtmlInput::hidden("b_id",$bilan->b_id);
    $form .=    HtmlInput::hidden("periode_from",$bilan->from);
    $form .=    HtmlInput::hidden("periode_to",$bilan->to) ;
    $form .=    HtmlInput::submit('bt_pdf',"Export PDF");
    $form .=  '</form>';
    return($form);
}
function exportcsv_submit_button($bilan)
/*!Builds form dedicated to "Export CSV" submit button
 * \param $bilan The bilan object
 * \brief Store parameters $_GET parameters for export.php processing
 */
{
    $form  =  '<form method="GET" action=' .'export.php' .'>';
    $form .=    dossier::hidden();
    $form .=    HtmlInput::hidden("ac",$_REQUEST['ac']);
    $form .=    HtmlInput::hidden("act","CSV:bilaninterne");
    $form .=    HtmlInput::hidden("b_id",$bilan->b_id);
    $form .=    HtmlInput::hidden("periode_from",$bilan->from);
    $form .=    HtmlInput::hidden("periode_to",$bilan->to) ;
    $form .=    HtmlInput::submit('bt_pdf',"Export CSV");
    $form .=  '</form>';
    return($form);
}


$bilaninterne_version = 6960;
$bilaninterne=new Acc_Bilaninterne($cn);
//Exercice and Period selection forms
echo '<div class="content">';
    $exercice=(isset($_GET['exercice']))?$_GET['exercice']:$g_user->get_exercice();
    echo exercice_selection_form($exercice);
    echo periods_selection_form($bilaninterne, $exercice);
echo '</div>';
if ( !isset($_GET['bilaninterne']))
{   //installs plugin, update plugin, if needed ...
    if ( $cn->exist_schema('bilaninterne') == false )
    {
        require_once('include/class_install_plugin.php');
        $plugin=new install_plugin($cn,$bilaninterne_version);
        $plugin->install();
    }
    elseif ( $cn->get_value('select max(val) from bilaninterne.version') < $bilaninterne_version )
    {
        require_once('include/class_install_plugin.php');
	$plugin = new install_plugin($cn,$bilaninterne_version);
        $plugin->upgrade();
    }
} 
else
{
    // Exports submit buttons forms
    $bilaninterne->get_request_get();
    $bilaninterne->load();
    echo '<div class="content">';
        echo "<table>";
            echo '<TR>';
                echo '<td>';
                    echo exportpdf_submit_button($bilaninterne);
                echo "</td>";
                echo '<td>';
                    echo exportcsv_submit_button($bilaninterne);
                echo "</td>";
                echo '<td style="vertical-align:top">';
                    echo HtmlInput::print_window();
                echo '</td>';
            echo "</TR>";
        echo "</table>";
    echo '</div>';
    // generates the HTML output
    $bilaninterne->generate();
    
    $output = new output_bilaninterne;
    $output->from=$bilaninterne->from;
    $output->to  =$bilaninterne->to;

    $output->output_html($bilaninterne->bilan_table,$cn);
}