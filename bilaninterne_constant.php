<?php 
//This file is part of NOALYSS and is under GPL 
//see licence.txt
/**
 *@file
 *Contains all the needed variable for the plugin
 *is name is plugin_name_constant.php
 * You can use some globale variable, especially for the database
 *  connection
 */

if ( !defined("BILAN_INTERNE_HOME")) define ("BILAN_INTERNE_HOME",__DIR__);
require_once NOALYSS_INCLUDE .'/class/database.class.php';

global $cn,$errcode;

?>
