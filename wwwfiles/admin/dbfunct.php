<?php
// admin/dbfunct.php    Norbert Haider, University of Vienna, 2010
// a collection of database functions for MolDB5R, last change: 2010-06-10

function check_db_all($id) {
  global $metatable;
  $db_id = -1;
  if (is_numeric($id)) {
    $result = mysql_query("SELECT db_id, name FROM $metatable WHERE (db_id = $id)")
      or die("Query failed! (1)");
    while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
      $db_id = $line["db_id"];
    }
    mysql_free_result($result);
  }
  if ($db_id == -1) {   // check if there is any data collection at all
    $result = mysql_query("SELECT COUNT(db_id) AS dbcount FROM $metatable")
      or die("Query failed! (2)");
    $dbcount = 0;
    while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
      $dbcount = $line["dbcount"];
    }
    mysql_free_result($result);
    if ($dbcount == 0) { $db_id = 0; }
  }
  return($db_id);
}

function unregister_db($kill_db) {
  global $metatable;
  $result  = 0;
  $killstr = "DELETE FROM " . $metatable . " WHERE db_id = " . $kill_db;
  mysql_query($killstr);
  $result = mysql_affected_rows();
  return($result);
}

function set_memstatus_dirty($id) {
  global $metatable;
  $qstr = "UPDATE $metatable SET memstatus = 0 WHERE db_id = $id";
  $result = mysql_query($qstr);
  $err = 0;
  $err = mysql_errno();
  return($err);
}

function drop_moltables($kill_db) {
  global $prefix;
  global $molstrucsuffix;
  global $moldatasuffix;
  global $molfgbsuffix;
  global $molstatsuffix;
  global $molcfpsuffix;
  global $pic2dsuffix;
  global $memsuffix;

  $result = "";
  $dbprefix = $prefix . "db" . $kill_db . "_";
  $killstr = "DROP TABLE IF EXISTS ";
  $killstr .= $dbprefix . $molstrucsuffix . ", ";
  $killstr .= $dbprefix . $moldatasuffix . ", ";
  $killstr .= $dbprefix . $molfgbsuffix . ", ";
  $killstr .= $dbprefix . $molstatsuffix . ", ";
  $killstr .= $dbprefix . $molstatsuffix . $memsuffix . ", ";
  $killstr .= $dbprefix . $molcfpsuffix . ", ";
  $killstr .= $dbprefix . $molcfpsuffix . $memsuffix . ", ";
  $killstr .= $dbprefix . $pic2dsuffix;
  mysql_query($killstr);
  $result = mysql_error();
  return($result);
}

function drop_rxntables($kill_db) {
  global $prefix;
  global $rxnstrucsuffix;
  global $rxndatasuffix;
  global $rxncfpsuffix;
  global $rxnfgbsuffix;

  $result = "";
  $dbprefix = $prefix . "db" . $kill_db . "_";
  $killstr = "DROP TABLE IF EXISTS ";
  $killstr .= $dbprefix . $rxnstrucsuffix . ", ";
  $killstr .= $dbprefix . $rxndatasuffix . ", ";
  $killstr .= $dbprefix . $rxnfgbsuffix . ", ";
  $killstr .= $dbprefix . $rxncfpsuffix;
  mysql_query($killstr);
  $result = mysql_error();
  return($result);
}

function create_moltables($db_id) {
  global $fpdeftable;
  global $prefix;
  global $molstrucsuffix;
  global $moldatasuffix;
  global $molfgbsuffix;
  global $molstatsuffix;
  global $molcfpsuffix;
  global $pic2dsuffix;
  global $use_cmmmsrv;
  global $cmmmsrv_addr;
  global $cmmmsrv_port;
  global $CHECKMOL;
  global $socket;

  $dbprefix = $prefix . "db" . $db_id . "_";
  //molstruc
  $createcmd = "CREATE TABLE IF NOT EXISTS ${dbprefix}$molstrucsuffix (";
  $createcmd .= "mol_id INT(11) NOT NULL DEFAULT '0', struc MEDIUMBLOB NOT NULL, PRIMARY KEY mol_id (mol_id)";
  $createcmd .= ") ENGINE = MYISAM CHARACTER SET latin1 COLLATE latin1_bin COMMENT='Molecular structures'";
  $result = mysql_query($createcmd)
    or die("Create failed! (create_moltables 1)");
  #mysql_free_result($result);
  
  //moldatatable
  $createcmd = "CREATE TABLE IF NOT EXISTS ${dbprefix}$moldatasuffix (";
  $createcmd .= "mol_id INT(11) NOT NULL DEFAULT '0', mol_name TEXT NOT NULL, PRIMARY KEY mol_id (mol_id)";
  $createcmd .= ") ENGINE = MYISAM CHARACTER SET latin1 COLLATE latin1_swedish_ci COMMENT='Molecular data'";
  $result = mysql_query($createcmd)
    or die("Create failed! (4b)");
  #mysql_free_result($result);

  //molstattable
  if ($use_cmmmsrv == 'y') {
    $msdef = filterthroughcmmm("\$\$\$\$","#### checkmol:l");
  } else {
    $msdef = filterThroughCmd("","$CHECKMOL -l");
  }
  $createcmd = "CREATE TABLE IF NOT EXISTS ${dbprefix}$molstatsuffix (
mol_id int(11) NOT NULL DEFAULT '0', \n";
  $msdef = rtrim($msdef);    
  $msline = explode("\n",$msdef);
  $nfields = count($msline);
  foreach ($msline as $line) {
    $element = explode(":",$line);
    $createcmd = $createcmd . "  $element[0]" . " SMALLINT(6) NOT NULL DEFAULT '0',\n";
  }  
  $createcmd = $createcmd . "  PRIMARY KEY  (mol_id)
) ENGINE = MYISAM COMMENT='Molecular statistics';";

  //echo "<pre>$createcmd</pre>\n";
  $result = mysql_query($createcmd)
    or die("Create failed! (4c)");
  #mysql_free_result($result);

  //molfgbtable
  $createcmd = "CREATE TABLE IF NOT EXISTS ${dbprefix}$molfgbsuffix (mol_id INT(11) NOT NULL DEFAULT '0', 
  fg01 INT(11) UNSIGNED NOT NULL,
  fg02 INT(11) UNSIGNED NOT NULL,
  fg03 INT(11) UNSIGNED NOT NULL,
  fg04 INT(11) UNSIGNED NOT NULL,
  fg05 INT(11) UNSIGNED NOT NULL,
  fg06 INT(11) UNSIGNED NOT NULL,
  fg07 INT(11) UNSIGNED NOT NULL,
  fg08 INT(11) UNSIGNED NOT NULL,
  n_1bits SMALLINT NOT NULL,
  PRIMARY KEY mol_id (mol_id)) ENGINE = MYISAM COMMENT='Functional group patterns'";
  $result6 = mysql_query($createcmd)
    or die("Create failed! (4d)");
  #mysql_free_result($result6);

  //molcfptable
  //  first step: analyse the fingerprint dictionary (how many dict.?
  $createstr = "";
  $n_dict = 0;
  $result = mysql_query("SELECT fp_id, fpdef, fptype FROM $fpdeftable")
    or die("Query failed! (fpdef)");
  $fpdef  = "";
  $fptype = 1;
  while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $fp_id  = $line["fp_id"];
    $fpdef  = $line["fpdef"];
    $fptype = $line["fptype"];

    if (strlen($fpdef)>20) {
      $n_dict++;
      $dictnum = $n_dict;
      while (strlen($dictnum) < 2) { $dictnum = "0" . $dictnum;  }
      if ($fptype == 1) {
        $createstr .= "  dfp$dictnum BIGINT NOT NULL,\n";
      } else {
        $createstr .= "  dfp$dictnum INT(11) UNSIGNED NOT NULL,\n";
      }
    }
  }
  mysql_free_result($result);
  $createstr = trim($createstr);
  if ($n_dict < 1) {
    die("ERROR: could not retrieve fingerprint definition from table $fpdeftable\n");
  }
  $tblname = $dbprefix . $molcfpsuffix;
  $idname = "mol_id";
  $keystr = "PRIMARY KEY mol_id (mol_id)";

  $createcmd = "CREATE TABLE IF NOT EXISTS $tblname 
  ($idname INT(11) NOT NULL DEFAULT '0', $createstr
  hfp01 INT(11) UNSIGNED NOT NULL,
  hfp02 INT(11) UNSIGNED NOT NULL,
  hfp03 INT(11) UNSIGNED NOT NULL,
  hfp04 INT(11) UNSIGNED NOT NULL,
  hfp05 INT(11) UNSIGNED NOT NULL,
  hfp06 INT(11) UNSIGNED NOT NULL,
  hfp07 INT(11) UNSIGNED NOT NULL,
  hfp08 INT(11) UNSIGNED NOT NULL,
  hfp09 INT(11) UNSIGNED NOT NULL,
  hfp10 INT(11) UNSIGNED NOT NULL,
  hfp11 INT(11) UNSIGNED NOT NULL,
  hfp12 INT(11) UNSIGNED NOT NULL,
  hfp13 INT(11) UNSIGNED NOT NULL,
  hfp14 INT(11) UNSIGNED NOT NULL,
  hfp15 INT(11) UNSIGNED NOT NULL,
  hfp16 INT(11) UNSIGNED NOT NULL,
  n_h1bits SMALLINT NOT NULL, $keystr) 
  ENGINE = MYISAM COMMENT='Combined dictionary-based and hash-based fingerprints'";
  $result6 = mysql_query($createcmd)
    or die("Create failed! (4e)");
  #mysql_free_result($result6);

  //pic2dtyble
  $createcmd = "CREATE TABLE ${dbprefix}$pic2dsuffix (
  `mol_id` INT(11) NOT NULL DEFAULT '0',
  `type` TINYINT NOT NULL DEFAULT '1' COMMENT '1 = png',
  `status` TINYINT NOT NULL DEFAULT '0' COMMENT '0 = does not exist, 1 = OK, 2 = OK, but do not show, 3 = to be created/updated, 4 = to be deleted',
  PRIMARY KEY (mol_id)
  ) ENGINE = MYISAM CHARACTER SET latin1 COLLATE latin1_bin COMMENT='Housekeeping for 2D depiction'";
  $result6 = mysql_query($createcmd)
    or die("Create failed! (4f)");
  #mysql_free_result($result6);
}

function create_rxntables($db_id) {
  global $fpdeftable;
  global $prefix;
  global $rxnstrucsuffix;
  global $rxndatasuffix;
  global $rxncfpsuffix;
  global $rxnfgbsuffix;
  
  $dbprefix = $prefix . "db" . $db_id . "_";

  //rxnstruc
  $createcmd = "CREATE TABLE IF NOT EXISTS ${dbprefix}$rxnstrucsuffix (";
  $createcmd .= "rxn_id INT(11) NOT NULL DEFAULT '0', struc MEDIUMBLOB NOT NULL, ";
  $createcmd .= "map TEXT CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, PRIMARY KEY rxn_id (rxn_id)";
  $createcmd .= ") ENGINE = MYISAM CHARACTER SET latin1 COLLATE latin1_bin COMMENT='Reaction structures'";
  $result6 = mysql_query($createcmd)
    or die("Create failed! (rxnstructable)");

  //rxndata
  $createcmd = "CREATE TABLE IF NOT EXISTS ${dbprefix}$rxndatasuffix (";
  $createcmd .= "rxn_id INT(11) NOT NULL DEFAULT '0', rxn_name TEXT NOT NULL, PRIMARY KEY rxn_id (rxn_id)";
  $createcmd .= ") ENGINE = MYISAM CHARACTER SET latin1 COLLATE latin1_swedish_ci COMMENT='Reaction data'";
  $result6 = mysql_query($createcmd)
    or die("Create failed! (rxndatatable)");

  //rxnfgbtable
  $createcmd = "CREATE TABLE IF NOT EXISTS ${dbprefix}$rxnfgbsuffix (rxn_id INT(11) NOT NULL DEFAULT '0', 
  role CHAR(1) NOT NULL,
  fg01 INT(11) UNSIGNED NOT NULL,
  fg02 INT(11) UNSIGNED NOT NULL,
  fg03 INT(11) UNSIGNED NOT NULL,
  fg04 INT(11) UNSIGNED NOT NULL,
  fg05 INT(11) UNSIGNED NOT NULL,
  fg06 INT(11) UNSIGNED NOT NULL,
  fg07 INT(11) UNSIGNED NOT NULL,
  fg08 INT(11) UNSIGNED NOT NULL,
  n_1bits SMALLINT NOT NULL,
  PRIMARY KEY rxn_id (rxn_id,role)) ENGINE = MYISAM COMMENT='Summarized functional group patterns'";
  $result6 = mysql_query($createcmd)
    or die("Create failed! (rxnfgbtable)");

  //rxncfptable
  //  first step: analyse the fingerprint dictionary (how many dict.?
  $createstr = "";
  $n_dict = 0;
  $result = mysql_query("SELECT fp_id, fpdef, fptype FROM $fpdeftable")
    or die("Query failed! (fpdef)");
  $fpdef  = "";
  $fptype = 1;
  while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $fp_id  = $line["fp_id"];
    $fpdef  = $line["fpdef"];
    $fptype = $line["fptype"];
    if (strlen($fpdef)>20) {
      $n_dict++;
      $dictnum = $n_dict;
      while (strlen($dictnum) < 2) { $dictnum = "0" . $dictnum;  }
      if ($fptype == 1) {
        $createstr .= "  dfp$dictnum BIGINT NOT NULL,\n";
      } else {
        $createstr .= "  dfp$dictnum INT(11) UNSIGNED NOT NULL,\n";
      }
    }
  }
  mysql_free_result($result);
  $createstr = trim($createstr);
  if ($n_dict < 1) {
    die("ERROR: could not retrieve fingerprint definition from table $fpdeftable\n");
  }
  $tblname = $dbprefix . $rxncfpsuffix;
  $idname = "rxn_id";
  $keystr = "PRIMARY KEY rxn_id (rxn_id,role)";
  $createstr = "role CHAR(1) NOT NULL, " . $createstr;
  $createcmd = "CREATE TABLE IF NOT EXISTS $tblname 
  ($idname INT(11) NOT NULL DEFAULT '0', $createstr
  hfp01 INT(11) UNSIGNED NOT NULL,
  hfp02 INT(11) UNSIGNED NOT NULL,
  hfp03 INT(11) UNSIGNED NOT NULL,
  hfp04 INT(11) UNSIGNED NOT NULL,
  hfp05 INT(11) UNSIGNED NOT NULL,
  hfp06 INT(11) UNSIGNED NOT NULL,
  hfp07 INT(11) UNSIGNED NOT NULL,
  hfp08 INT(11) UNSIGNED NOT NULL,
  hfp09 INT(11) UNSIGNED NOT NULL,
  hfp10 INT(11) UNSIGNED NOT NULL,
  hfp11 INT(11) UNSIGNED NOT NULL,
  hfp12 INT(11) UNSIGNED NOT NULL,
  hfp13 INT(11) UNSIGNED NOT NULL,
  hfp14 INT(11) UNSIGNED NOT NULL,
  hfp15 INT(11) UNSIGNED NOT NULL,
  hfp16 INT(11) UNSIGNED NOT NULL,
  n_h1bits SMALLINT NOT NULL, $keystr) 
  ENGINE = MYISAM COMMENT='Combined dictionary-based and hash-based fingerprints'";
  $result6 = mysql_query($createcmd)
    or die("Create failed! (4e)");
  #mysql_free_result($result6);
}

function get_numentries($db_id,$dbtype_id) {
  global $prefix;
  global $molstrucsuffix;
  global $rxnstrucsuffix;

  $n_entries = 0;
  $table = "";
  $dbprefix = $prefix . "db" . $db_id . "_";
  if ($dbtype_id == 1) {
    $table = $dbprefix . $molstrucsuffix;
    $idname = "mol_id";
  } elseif ($dbtype_id == 2) {
    $table = $dbprefix . $rxnstrucsuffix;
    $idname = "rxn_id";
  }
  if (strlen($table) > 0) {
    $result1 = mysql_query("SELECT COUNT($idname) AS entrycount FROM $table")
      or die("Query failed! (get_numentries)");
    $line1 = mysql_fetch_row($result1);
    mysql_free_result($result1);
    $n_entries = $line1[0];
  }
  return($n_entries);
}

function get_next_mol_id($db_id) {
  global $prefix;
  global $molstrucsuffix;
  
  $result = 0;
  $dbprefix      = $prefix . "db" . $db_id . "_";
  $molstructable = $dbprefix . $molstrucsuffix;

  $result1 = mysql_query("SELECT COUNT(mol_id) AS molcount FROM $molstructable")
    or die("Query failed! (get_next_mol_id #1)");
  $line = mysql_fetch_row($result1);
  mysql_free_result($result1);
  $molcount = $line[0];
  if ($molcount == 0) { 
    $result = 1; 
  } else {
    $result1 = mysql_query("SELECT MAX(mol_id) AS molcount FROM $molstructable")
      or die("Query failed! (get_next_mol_id #2)");
    $line = mysql_fetch_row($result1);
    mysql_free_result($result1);
    $molcount = $line[0];
    $result = $molcount + 1;
  }
  return($result);
}

function get_next_rxn_id($db_id) {
  global $prefix;
  global $rxnstrucsuffix;
  
  $result = 0;
  $dbprefix      = $prefix . "db" . $db_id . "_";
  $rxnstructable = $dbprefix . $rxnstrucsuffix;

  $result1 = mysql_query("SELECT COUNT(rxn_id) AS rxncount FROM $rxnstructable")
    or die("Query failed! (get_next_rxn_id #1)");
  $line = mysql_fetch_row($result1);
  mysql_free_result($result1);
  $rxncount = $line[0];
  if ($rxncount == 0) { 
    $result = 1; 
  } else {
    $result1 = mysql_query("SELECT MAX(rxn_id) AS rxncount FROM $rxnstructable")
      or die("Query failed! (get_next_rxn_id #2)");
    $line = mysql_fetch_row($result1);
    mysql_free_result($result1);
    $rxncount = $line[0];
    $result = $rxncount + 1;
  }
  return($result);
}


?>