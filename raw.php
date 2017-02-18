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
/* $Revision$ */

/*!\file
 * \brief raw file for PDF ewa
 */
include 'bilaninterne_constant.php';
require_once BILAN_INTERNE_HOME . '/class_acc_bilaninterne.php';
require_once BILAN_INTERNE_HOME . '/class_output_bilaninterne.php';


extract($_REQUEST, EXTR_SKIP);
$cn=Dossier::connect();
$bilaninterne=new Acc_Bilaninterne($cn);
$bilaninterne->from =$from_periode;
$bilaninterne->to   =$to_periode;
$bilaninterne->generate();
$output = new output_bilaninterne;
$output->from=$from_periode;
$output->to  =$to_periode;
if ($act == 'export_bilaninterne_csv')
{
    $output->output_csv($bilaninterne->bilan_table,$cn);
    exit();
}
if ($act == 'export_bilaninterne_pdf')
{
    $output->output_pdf($bilaninterne->bilan_table,$cn);
    exit();
}
if ($act == 'export_bilaninterne_print')
{
    $output->output_html($bilaninterne->bilan_table,$cn);
    exit();
}
