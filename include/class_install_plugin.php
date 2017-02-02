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
 * \file class_install_plugin.php
 * \brief Installation and upgrade of "bilaninterne" plugin. 
 * 
 * It targets to addpaths in "menu_ref" table so that export functions 
 * (CSV/PDF) can be launched by "export.php"
 * \author T. Nancy
 * \version 0.1
 * \date 10 janvier 2017
*/
require_once NOALYSS_INCLUDE.'/database/class_menu_ref_sql.php';

class Install_Plugin extends Menu_Ref_SQL
{
    function __construct($p_cn,$version){
        $this->cn = $p_cn;
        $this->version = $version;
    }
    /*!
     * \brief Upgrades the 'menu_ref' table with adhoc paths  
     */
    function upgrade($p_dest = 0)
    {
        return;
    }
    /*!
    * \brief Installs the plugin
    * 
    * It creates a 'bilaninterne' schema, and a 'version' table
    * in the current folder. It adds two records in "menu_ref" table (schema 'public')
    * so that the path for including export fucntions (CSV or PDF) is settled.
    */
    function install() 
    {
        return;
    }
}