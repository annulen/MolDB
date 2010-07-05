<?php 
// functions.php    Norbert Haider, University of Vienna, 2009-2010
// a collection of common functions for MolDB5R, last change: 2010-06-09


function getmicrotime() {
  list($usec, $sec) = explode(" ", microtime());
  return ((float)$usec + (float)$sec);
}

function getostype() {
  if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $ostype = 2;   // Windows
  } else {
    $ostype = 1;  // Linux
  }
  return $ostype;
}

function show_header($myname,$dbstr) {
  global $enablereactions;
  $item = array();
  $item[0][0] = "index.php";    $item[0][1] = "Home";
  $item[1][0] = "moldblst.php"; $item[1][1] = "Browse";
  $item[2][0] = "moldbtxt.php"; $item[2][1] = "Text Search";
  $item[3][0] = "moldbfg.php";  $item[3][1] = "Functional Group Search";
  $item[4][0] = "moldbsss.php"; $item[4][1] = "Structure Search";
  if ($enablereactions == "y") {
    $item[5][0] = "moldbrss.php"; $item[5][1] = "Reaction Search";
    $nitems = 6;  
  } else { $nitems = 5; }
  for ($i = 0; $i < $nitems; $i++) {
    $url   = $item[$i][0];
    $label = $item[$i][1];
    //echo "$i: $url : $label <br>\n";
    $pos = strpos($myname,$url);
    if ($pos !== false) {
      echo "[$label]";
    } else {
      echo "[<a href=\"${url}?db=${dbstr}\">$label</a>]";
    }
  }
  echo "<br /><hr />\n";
  echo "<small>selected data collection: $dbstr</small><p />\n";
}

function filterthroughcmmm($input,$commandline) {
  global $socket;
  $input = $commandline . "\n" . $input . "####" . "\n";
  socket_write ($socket, $input, strlen ($input));
  $output = '';
  $a = '';
  while (($a = socket_read($socket, 250, PHP_NORMAL_READ)) && (strpos($a,'####') === false)) {
    if (strpos($a,'####') === false) {
      $output = $output . $a;
    }
  }
  return $output;
}

function filterthroughcmd($input, $commandLine) {
  $pipe = popen("echo \"$input\"|$commandLine" , 'r');
  if (!$pipe) {
    print "pipe failed.";
    return "";
  }
  $output = '';
  while(!feof($pipe)) {
    $output .= fread($pipe, 1024);
  }
  pclose($pipe);
  return $output;
}

function filterthroughcmd2($input, $commandLine) {     // Windows version
  global $tempdir;  // if not set, use system temporary directory
  $tempdir = realpath($tempdir);
  $tmpfname = tempnam($tempdir, "mdb"); 
  #$tmpfname = tempnam(realpath("C:/temp/"), "mdb"); // for testing (directory must exist!)
  $tfhandle = fopen($tmpfname, "wb");
  $myinput = str_replace("\n","",$input);
  $myinput = str_replace("\\\$","\$",$myinput);
  $inputlines = explode("\r",$myinput);
  $newinput = implode("\r\n",$inputlines);
  fwrite($tfhandle, $newinput);
  fclose($tfhandle);
  $output = `type $tmpfname | $commandLine `;
  #$output = `$commandLine < $tmpfname |`;
  unlink($tmpfname);
  return $output;
}

function showHit($id,$s) {
  global $bitmapURLdir;
  global $molstructable;
  global $moldatatable;
  global $digits;
  global $subdirdigits;
  global $db_id;
  global $pic2dtable;
  $result2 = mysql_query("SELECT mol_name FROM $moldatatable WHERE mol_id = $id")
    or die("Query failed! (1a)");
  while ($line2 = mysql_fetch_array($result2, MYSQL_ASSOC)) {
    $txt = $line2["mol_name"];
  }
  mysql_free_result($result2);

  echo "<tr>\n<td bgcolor=\"#EEEEEE\">\n";
  print "<a href=\"details.php?mol=${id}&db=${db_id}\" target=\"blank\">$db_id:$id</a></td>\n";
  echo "<td bgcolor=\"#EEEEEE\"> <b>$txt</b>";
  if ($s != '') {
    echo " $s";
  }
  echo "</td>\n</tr>\n";
  
  // for faster display, we should have bitmap files (GIF or PNG) of the 2D structures
  // instead of invoking the JME applet:

  $qstr = "SELECT status FROM $pic2dtable WHERE mol_id = $id";
  $result2 = mysql_query($qstr)
    or die("Query failed! (pic2d)");
  while ($line2 = mysql_fetch_array($result2, MYSQL_ASSOC)) {
    $status = $line2["status"];
  }
  mysql_free_result($result2);
  if ($status != 1) { $usebmp = false; } else { $usebmp = true; }

  echo "<tr>\n<td colspan=\"2\">\n";
  
  if ((isset($bitmapURLdir)) && ($bitmapURLdir != "") && ($usebmp == true)) {
    while (strlen($id) < $digits) { $id = "0" . $id; }
    $subdir = '';
    if ($subdirdigits > 0) { $subdir = substr($id,0,$subdirdigits) . '/'; }
    print "<img src=\"${bitmapURLdir}/${db_id}/${subdir}${id}.png\" alt=\"hit structure\">\n";
  } else {  
    // if no bitmaps are available, we must invoking another instance of JME 
    // in "depict" mode for structure display of each hit
    $qstr = "SELECT struc FROM $molstructable WHERE mol_id = $id";
    $result3 = mysql_query($qstr) or die("Query failed! (struc)");    
    while ($line3 = mysql_fetch_array($result3, MYSQL_ASSOC)) {
      $molstruc = $line3["struc"];
    }
    mysql_free_result($result3);
  
    // JME needs MDL molfiles with the "|" character instead of linebreaks
    $jmehitmol = strtr($molstruc,"\n","|");
        
    echo "<applet code=\"JME.class\" archive=\"JME.jar\" \n";
    echo "width=\"250\" height=\"120\">";
    echo "<param name=\"options\" value=\"depict\"> \n";
    echo "<param name=\"mol\" value=\"$jmehitmol\">\n";
    echo "</applet>\n";
  }
  echo "</td>\n</tr>\n";
}

function showHitRxn($id,$s) {
  global $rxnstructable;
  global $rxndatatable;
  global $db_id;
  $result2 = mysql_query("SELECT rxn_name FROM $rxndatatable WHERE rxn_id = $id")
    or die("Query failed! (showHitRxn #1)");
  while ($line2 = mysql_fetch_array($result2, MYSQL_ASSOC)) {
    $txt = $line2["rxn_name"];
  }
  mysql_free_result($result2);

  echo "<tr>\n<td bgcolor=\"#EEEEEE\">\n";
  print "<a href=\"details.php?rxn=${id}&db=${db_id}\" target=\"blank\">$db_id:$id</a></td>\n";
  echo "<td bgcolor=\"#EEEEEE\"> <b>$txt</b>";
  if ($s != '') {
    echo " $s";
  }
  echo "</td>\n</tr>\n";
  
  echo "<tr>\n<td colspan=\"2\">\n";
  
  // use JME in "depict" mode for reaction display
  $qstr = "SELECT struc FROM $rxnstructable WHERE rxn_id = $id";
  $result3 = mysql_query($qstr) or die("Query failed! (struc)");    
  while ($line3 = mysql_fetch_array($result3, MYSQL_ASSOC)) {
    $molstruc = $line3["struc"];
  }
  mysql_free_result($result3);

  // remove reaction map labels
  $molstruc = strip_labels($molstruc);

  // JME needs MDL molfiles with the "|" character instead of linebreaks
  $jmehitmol = strtr($molstruc,"\n","|");
      
  echo "<applet code=\"JME.class\" archive=\"JME.jar\" \n";
  echo "width=\"450\" height=\"120\">";
  echo "<param name=\"options\" value=\"depict\"> \n";
  echo "<param name=\"mol\" value=\"$jmehitmol\">\n";
  echo "</applet>\n";
  echo "</td>\n</tr>\n";
}

function check_db($id) {
  global $metatable;
  $db_id = -1;
  if (is_numeric($id)) {
    $result = mysql_query("SELECT db_id, name FROM $metatable WHERE (db_id = $id) AND (access > 0)")
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

function get_numdb() {
  global $metatable;
  $numdb = 0;
  $result1 = mysql_query("SELECT COUNT(db_id) AS numdb FROM $metatable")
    or die("Query failed! (get_numdb)");
  $line1 = mysql_fetch_row($result1);
  mysql_free_result($result1);
  $numdb = $line1[0];
  return($numdb);
}

function exist_db($db_id) {
  global $metatable;
  $numdb = 0;
  $result1 = mysql_query("SELECT COUNT(db_id) AS numdb FROM $metatable WHERE db_id = $db_id")
    or die("Query failed! (get_numdb)");
  $line1 = mysql_fetch_row($result1);
  mysql_free_result($result1);
  $numdb = $line1[0];
  if ($numdb > 0) { $result = TRUE; } else { $result = FALSE; }
  return($result);
}

function get_highestdbid() {
  global $metatable;
  $dbmax = 0;
  $result1 = mysql_query("SELECT MAX(db_id) AS dbmax FROM $metatable")
    or die("Query failed! (get_highestdbid)");
  $line1 = mysql_fetch_row($result1);
  $dbmax = $line1[0];
  mysql_free_result($result1);
  return($dbmax);
}

function get_lowestdbid() {
  global $metatable;
  $dbmin = 0;
  $result1 = mysql_query("SELECT MIN(db_id) AS dbmin FROM $metatable")
    or die("Query failed! (get_lowestdbid)");
  $line1 = mysql_fetch_row($result1);
  $dbmin = $line1[0];
  mysql_free_result($result1);
  return($dbmin);
}

function get_dbproperties($db_id) {
  global $metatable;
  $prop = array();
  $result1 = mysql_query("SELECT db_id, type, access, name, description, usemem,
    memstatus, digits, subdirdigits, trustedIP FROM $metatable WHERE (db_id = $db_id)")
    or die("Query failed! (1a)");
  while ($line1 = mysql_fetch_assoc($result1)) {
    $prop['db_id']        = $line1['db_id'];
    $prop['type']         = $line1['type'];
    $prop['access']       = $line1['access'];
    $prop['name']         = $line1['name'];
    $prop['description']  = $line1['description'];
    $prop['usemem']       = $line1['usemem'];
    $prop['memstatus']    = $line1['memstatus'];
    $prop['digits']       = $line1['digits'];
    $prop['subdirdigits'] = $line1['subdirdigits'];
    $prop['trustedIP']    = $line1['trustedIP'];
  }
  mysql_free_result($result1);
  return($prop);
}

function mfreformat($instring) {
  $outstring = "";
  $firstnum = 1;
  $sub = 0;
  $instring = trim($instring);
  for ($l = 0; $l < strlen($instring); $l++) {
    $c = substr($instring,$l,1);
    if (is_numeric($c)) {
      if (($firstnum == 0) && ($sub == 0)) {
        $outstring = $outstring . "<sub>";
        $sub = 1;
      }
      $outstring = $outstring . $c;
    } else {
      $firstnum = 0;
      if ($c == ".") { $firstnum = 1; }
      if ($sub == 1) {
        $outstring = $outstring . "</sub>";
        $sub = 0;
      }
      $outstring = $outstring . $c;
    }
  }  // for
  if ($sub == 1) {
    $outstring = $outstring . "</sub>";
  }
  return($outstring);
}

function urlreformat($instring) {
  $outstring = "";
  $instring = trim($instring);
  $urlarr = explode("|",$instring);
  $url_addr = $urlarr[0];
  if (count($urlarr) > 1) {
    $url_label = $urlarr[1];
  } else { 
    $url_label = $url_addr;
  }
  $url_label = trim($url_label);
  if (strlen($url_label) == 0) {
    $url_label = $url_addr;
  }
  $outstring = "<a href=\"" . $url_addr . "\">" . $url_label . "</a>";
  return($outstring);
}

function is_trustedIP($ip) {
  global $trustedIP;
  $IPlist = str_replace(";",",",$trustedIP);
  $IPlist = preg_replace("/,\ +/",",",$IPlist);
  $IParray = explode(",",$IPlist);
  $result = false;
  foreach($IParray as $value) {
    $value = trim($value);
    $pos = strpos($ip,$value);
    if ($pos !== false) { $result = true; }
  }
  return($result);
}

function is_db_trustedIP($db_id,$ip) {
  global $metatable;
  $result = false;
  if (is_numeric($db_id)) {
    $res = mysql_query("SELECT db_id, trustedIP FROM $metatable WHERE (db_id = $db_id)")
      or die("Query failed! (1)");
    while ($line = mysql_fetch_array($res, MYSQL_ASSOC)) {
      $trustedIP = $line["trustedIP"];
    }
    mysql_free_result($res);
    $IPlist = str_replace(";",",",$trustedIP);
    $IPlist = preg_replace("/,\ +/",",",$IPlist);
    if (strlen($IPlist)>0) {
      $IParray = explode(",",$IPlist);
      $result = false;
      foreach($IParray as $value) {
        $value = trim($value);
        $pos = strpos($ip,$value);
        if ($pos !== false) { $result = true; }
      }
    }
  }
  return($result);
}

function clean_fieldstr($instr) {
  $outstr = "";
  $allowedstr = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_";
  $instr = trim($instr);
  #$instr = str_replace(".","_",$instr);
  if ($instr != "") {
    $c = "";
    $pos = false;
    for ($i = 0; $i < strlen($instr); $i++) {
      $c = substr($instr,$i,1);
      $pos = strpos($allowedstr,$c);
      if ($pos !== false) {
        $outstr .= $c;
      }
    }
  }
  return($outstr);
}

function getfieldprop($comment) {
  $prop = array();
  $pos = strpos($comment, ">>>>");
  if ($pos !== false) {
    if ($pos == 0) {
      $comment = str_replace(">>>>","",$comment);
      $acomment = explode("<",$comment);
      $label  = $acomment[0];
      $prop["label"] = $label;
      $format = 1;
      $nformat = $acomment[1];
      if ($nformat == 0) { $format = 0; }
      if ($nformat == 1) { $format = 1; }
      if ($nformat == 2) { $format = 2; }
      if ($nformat == 3) { $format = 3; }
      $prop["format"] = $format;
      $sdflabel   = $acomment[2];
      $prop["sdflabel"] = $sdflabel;
      $searchmode = $acomment[3];
      if ($searchmode != 1) { $searchmode = 0; }
      $prop["searchmode"] = $searchmode;
    }
  }
  return $prop;
}

function is_stringtype($columntype) {
  $res = FALSE;
  $columntype = strtoupper($columntype);
  if (strpos($columntype,"CHAR") !== FALSE) { $res = TRUE; }
  if (strpos($columntype,"TEXT") !== FALSE) { $res = TRUE; }
  if (strpos($columntype,"ENUM") !== FALSE) { $res = TRUE; }
  if (strpos($columntype,"SET") !== FALSE)  { $res = TRUE; }
  if (strpos($columntype,"VARBINARY") !== FALSE) { $res = TRUE; }
  return $res;
}

function is_validmol($mol) {
  $res = FALSE;
  if ((strpos($mol,'M  END') > 40) && 
      (strpos($mol,'V2000') > 30)) { $res = TRUE; }  // rather simple, for now
  return $res;
}

function strip_labels($myrxn) {
  //$myrxn = str_replace("\r\n","\n",$myrxn);
  $line_arr = array();
  $line_arr = explode("\n",$myrxn);
  $myrxn = "";
  foreach ($line_arr as $line) {
    if ((strlen($line) > 68) && (strpos($line,"0  0",63) !== FALSE)) {
      $line = substr_replace($line,"  0  0  0",60,9);
    }
    $myrxn .= $line . "\n";
  }
  return($myrxn);
}

function mk_fpqstr($colname,$fplist) {
  $result = "";
  $fpa = explode(",",$fplist);
  $n_el = count($fpa);
  for ($i = 0; $i < $n_el; $i++) {
    $fpval = $fpa[$i];
    $fpnum = $i + 1;
    while (strlen($fpnum) < 2) { $fpnum = "0" . $fpnum; }
    $fpcol = $colname . $fpnum;
    if (is_numeric($fpval)) {
      if ($fpval > 0) {
        if (strlen($result) > 0) { $result .= " AND"; }
        $result .= " ($fpcol & $fpval = $fpval)";
      }
    }
  }
  return($result);
}

function debug_output($msg) {
  global $debug;
  if (($debug & 1) == 1) {
    $begin = "<!-- ";
    $end = " --!>";
  } else {
    $begin = "<pre>\n";
    $end = "</pre>\n";
  }
  echo "$begin"; 
  echo "$msg"; 
  echo "$end";
}

function config_quickcheck() {
  global $database;
  global $ro_user;
  global $ro_password;
  $result = 0;
  if ((!isset($database)) || (strlen($database) == 0)) { $result = 1; }
  if ((!isset($ro_user)) || (strlen($ro_user) == 0)) { $result = 1; }
  if ((!isset($ro_password)) || (strlen($ro_password) == 0)) { $result = 1; }
  if ($result > 0) {
    echo "<h3>Attention! Missing, invalid, or unreadable configuration file!</h3>\n";
  }
  return($result);
}

?>