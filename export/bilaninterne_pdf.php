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
 * \file bilan_interne_pdf.php
 * \brief Generates the the detailled balace sheet and outputs PDF file 
 * 
 * It regenerates de detailled balance sheet from user data and creates a PDF output.
 * The file is launched by the "export.php" file (NOALYSS_HOME/htdocs/html/)
 * in charge of managing all the export features in Noalyss.  
 * \author T. Nancy
 * \version 0.1
 * \date 10 janvier 2017
*/

if ( ! defined ('ALLOWED') ) die('Appel direct ne sont pas permis');
require_once NOALYSS_INCLUDE.'/ext/bilan_interne/class_acc_bilaninterne.php';
require_once NOALYSS_INCLUDE. '/ext/bilan_interne/class_output_bilaninterne.php';

extract ($_GET);
$cn=Dossier::connect();
$bilaninterne=new Acc_Bilaninterne($cn);
$bilaninterne->b_id =$b_id;
$bilaninterne->from =$periode_from;
$bilaninterne->to   =$periode_to;
$bilaninterne->load();
$bilaninterne->generate();
//Ouput PDF
$output = new output_bilaninterne;
$output->from=$periode_from;
$output->to =$periode_to;
$output->output_pdf($bilaninterne->bilan_table,$cn);