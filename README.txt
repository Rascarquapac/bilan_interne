This README file describes the bilaninterne plugin for Noalyss software
The Bilaninterne plugin aims to provide Noalyss with an interactive "detailled balance sheet"
V0.1
*********************************************
The result provides a synhtetic view of the assets, liabilities and results for a selected period 
with details define in a description file. Each "leaf poste" can be inspected 
by clicking on the displayed link.
It allows a synthetic review of the company accounting tracking and correcting anomalies 

The result can be exported as pdf file, csv file or print output 

The structure, the displayed detailled, and the layout is described in a csv file (bilaninterne.csv)
*********************************************
Bilaninterne.csv contents
Common rows of the file have 5 fields
- linetype is a number >= 0 used for layout and indicating the depth in the structure. 0 indicates a tittle line 
- Rubrique describe row tittle
- Code is a "BNB like" decription of postes composing the Rubrique
- Montant field contains a variable name framed with <<>> signes. 
  The variable formula (Noalyss formula syntax for bilan feature) is providen
  in the bilaninterne.form file 
- Ventilation fileds is a 'yes'/'no' string indicating if the row is to be spreaded

Empty lines are allowed, Header line must be the first one of the csv file
************************************************
Plugin folders
- bilan_interne/export: export php files according to Noalyss export strategy
- bilan_interne/include: currently, plugin installation file for "menu_ref" table initialisation
- bilan_interne/templates: row description file (bilaninterne.csv) and formulas 
  description file (bilaninterne.form)
- bilan_interne/test: PHPunit test files
- bilan_interne: index.php plugin file, xml description of the plugin and main 
 php classes