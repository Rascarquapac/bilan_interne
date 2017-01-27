<?php
//This file is part of NOALYSS and is under GPL 
//see licence.txt
$file=HtmlInput::default_value_get('act',null);
if ($file==null) die(_('No action'));
require_once 'ajax_'.$file.'.php';   
echo "</data>";
?>
