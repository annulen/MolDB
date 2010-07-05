<?php 
// details.php     Norbert Haider, University of Vienna, 2006-2010
// part of MolDB5R last change: 2010-06-11

$myname = $_SERVER['PHP_SELF'];
require_once("functions.php");

// read UID and password from an include file (which should
// be stored _outside_ the web server's document_root directory
// somewhere in the include path of PHP!!!!!!!!!!!!!)

include("moldb5conf.php");  // contains $uid and $pw of a proxy user 
                            // with read-only access to the moldb database;
                            // contains $bitmapURLdir (location of .png files);
                            // the conf file must have valid PHP start and end tags!

//$configfile = "moldb5conf.php";  // just an alternative to "include" for
                                   // plain-text conf files
//$conf = `cat $configfile`;    # read content of configuration file
//eval($conf);                  # and treat as valid PHP statements

if (config_quickcheck() > 0) { die(); }

$user     = $ro_user;         # from configuration file
$password = $ro_password;

if ($user == "") {
  die("no username specified!\n");
}
if (!isset($sitename) || ($sitename == "")) {
  $sitename = "MolDB demo";
}

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<link href="moldb.css" rel="stylesheet" type="text/css">
<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">
<meta name="author" content="N. Haider">
<title><?php echo "$sitename"; ?>: compound details</title>
</head>
<body>

<?php
$mol_id  = $_REQUEST['mol'];
$rxn_id  = $_REQUEST['rxn'];
$db_id   = $_REQUEST['db'];

$link = mysql_pconnect($hostname,"$ro_user", "$ro_password")
  or die("Could not connect to database server!");
mysql_select_db($database)
  or die("Could not select database!");    

if (exist_db($default_db) == FALSE) {
  $default_db = get_lowestdbid(); 
}

$db_id = check_db($db_id);
if ($db_id < 0) {
  $db_id = $default_db;
  $db_id = check_db($db_id);
  if ($db_id < 0) {
    $db_id = get_lowestdbid();
  }
}

if ($db_id == 0) { die(); }

$qstr01 = "SELECT * FROM $metatable WHERE (db_id = $db_id)";

$result01 = mysql_query($qstr01)
  or die("Query failed (#1)!");    
while($line01   = mysql_fetch_array($result01)) {
  $db_id        = $line01['db_id'];
  $dbtype       = $line01['type'];
  $access       = $line01['access'];
  $dbname       = $line01['name'];
  $usemem       = $line01['usemem'];
  $digits       = $line01['digits'];
  $subdirdigits = $line01['subdirdigits'];
}
mysql_free_result($result01);

if (!isset($digits) || (is_numeric($digits) == false)) { $digits = 8; }
if (!isset($subdirdigits) || (is_numeric($subdirdigits) == false)) { $subdirdigits = 0; }
if ($subdirdigits < 0) { $subdirdigits = 0; }
if ($subdirdigits > ($digits - 1)) { $subdirdigits = $digits - 1; }

$dbprefix      = $prefix . "db" . $db_id . "_";
$molstructable = $dbprefix . $molstrucsuffix;
$moldatatable  = $dbprefix . $moldatasuffix;
$molstattable  = $dbprefix . $molstatsuffix;
$molcfptable   = $dbprefix . $molcfpsuffix;
$molfgbtable   = $dbprefix . $molfgbsuffix;
$pic2dtable    = $dbprefix . $pic2dsuffix;
$rxnstructable = $dbprefix . $rxnstrucsuffix;
$rxndatatable  = $dbprefix . $rxndatasuffix;
if ($usemem == 'T') {
  $molstattable  = $molstattable . $memsuffix;
  $molcfptable   = $molcfptable  . $memsuffix;
}

$safemol_id = escapeshellcmd($mol_id);
$saferxn_id = escapeshellcmd($rxn_id);


function showHit2($id) {
  global $bitmapURLdir;
  global $molstructable;
  global $moldatatable;
  global $pic2dtable;
  global $digits;
  global $subdirdigits;
  global $db_id;
  global $access;
  $result3 = mysql_query("SELECT mol_name FROM $moldatatable WHERE mol_id = $id")
    or die("Query failed! (showHit2)");
  while ($line3 = mysql_fetch_array($result3, MYSQL_ASSOC)) {
    $txt = $line3["mol_name"];
  }
  mysql_free_result($result3);

  echo "<table width=\"100%\">\n";
  echo "<tr>\n<td bgcolor=\"#EEEEEE\">\n";
  print "<b>$txt</b> (<a href=\"showmol.php?mol=${id}&db=${db_id}\" target=\"blank\">$id</a>)\n";
  echo "</td>\n</tr>\n";
  echo "</table>\n";

  if ($access >= 2) {    // display an "edit" link for read/write data collections
    print "[<a href=\"admin/editdata.php?db=${db_id}&id=${id}&action=editdata\" target=\"admin\">edit</a>]<br />\n";
  }
  
  // for faster display, we should have GIF files of the 2D structures
  // instead of invoking the JME applet:

  $qstr = "SELECT status FROM $pic2dtable WHERE mol_id = $id";
  //echo "SQL: $qstr<br />\n";
  $result2 = mysql_query($qstr)
    or die("Query failed! (pic2d)");
  while ($line2 = mysql_fetch_array($result2, MYSQL_ASSOC)) {
    $status = $line2["status"];
  }
  mysql_free_result($result2);
  if ($status != 1) { $usebmp = false; } else { $usebmp = true; }


  if ((isset($bitmapURLdir)) && ($bitmapURLdir != "") && ($usebmp == true)) {
    while (strlen($id) < $digits) { $id = "0" . $id; }
    $subdir = '';
    if ($subdirdigits > 0) { $subdir = substr($id,0,$subdirdigits) . '/'; }
    print "<img src=\"${bitmapURLdir}/${db_id}/${subdir}${id}.png\" alt=\"selected structure\">\n";
  } else {  
    // if no bitmaps are available, we must invoking another instance of JME 
    // in "depict" mode for structure display of each hit
    $result4 = mysql_query("SELECT struc FROM $molstructable WHERE mol_id = $id") or die("Query failed!");    
    while ($line4 = mysql_fetch_array($result4, MYSQL_ASSOC)) {
      $molstruc = $line4["struc"];
    }
    mysql_free_result($result4);
    // JME needs MDL molfiles with the "|" character instead of linebreaks
    $jmehitmol = strtr($molstruc,"\n","|");
    echo "<applet code=\"JME.class\" archive=\"JME.jar\" \n";
    echo "width=\"450\" height=\"300\">";
    echo "<param name=\"options\" value=\"depict\"> \n";
    echo "<param name=\"mol\" value=\"$jmehitmol\">\n";
    echo "</applet>\n";
  }
}

function showHit2rxn($id) {
  global $rxnstructable;
  global $rxndatatable;
  global $db_id;
  global $access;
  $result3 = mysql_query("SELECT rxn_name FROM $rxndatatable WHERE rxn_id = $id")
    or die("Query failed! (showHit2rxn)");
  while ($line3 = mysql_fetch_array($result3, MYSQL_ASSOC)) {
    $txt = $line3["rxn_name"];
  }
  mysql_free_result($result3);

  echo "<table width=\"100%\">\n";
  echo "<tr>\n<td bgcolor=\"#EEEEEE\">\n";
  print "<b>$txt</b> (<a href=\"showmol.php?rxn=${id}&db=${db_id}\" target=\"blank\">$id</a>)\n";
  echo "</td>\n</tr>\n";
  echo "</table>\n";

  if ($access >= 2) {    // display an "edit" link for read/write data collections
    print "[<a href=\"admin/editdata.php?db=${db_id}&id=${id}&action=editdata\" target=\"admin\">edit</a>]<br />\n";
  }
  
  // use JME in "depict" mode for reaction display
  $result4 = mysql_query("SELECT struc FROM $rxnstructable WHERE rxn_id = $id") or die("Query failed! (showHit2rxn)");    
  while ($line4 = mysql_fetch_array($result4, MYSQL_ASSOC)) {
    $molstruc = $line4["struc"];
  }
  mysql_free_result($result4);
  $molstruc = strip_labels($molstruc);
  // JME needs MDL molfiles with the "|" character instead of linebreaks
  $jmehitmol = strtr($molstruc,"\n","|");
  echo "<applet code=\"JME.class\" archive=\"JME.jar\" \n";
  echo "width=\"550\" height=\"300\">";
  echo "<param name=\"options\" value=\"depict\"> \n";
  echo "<param name=\"mol\" value=\"$jmehitmol\">\n";
  echo "</applet>\n";
}


function showData_old($id) {
  echo "<p />\n";
  global $moldatatable;
  $result4 = mysql_query("SELECT * FROM $moldatatable WHERE mol_id = $id")
    or die("Query failed! (showData_old)");
  $y = mysql_num_fields($result4);
  echo "<table>\n";
  while ($line4 = mysql_fetch_array($result4, MYSQL_BOTH)) {
    for ($x = 0; $x < $y; $x++) {
      $fieldname = mysql_field_name($result4, $x);
      //$fieldtype = mysql_field_type($result4, $x);
      if ($fieldname != "mol_name" && $fieldname != "mol_id" && $line4[$fieldname] != "") {
        //echo  "<b>$fieldname:</b> \t$line4[$fieldname] <br />\n";
        echo "<tr>\n";
        echo "  <td><b>$fieldname</b></td><td>$line4[$fieldname]</td>\n";
        echo "</tr>\n";
      }
    }
    echo "<br />\n";
  }
  echo "</table>\n";
  mysql_free_result($result4);
}

function showData($id) {
  echo "<p />\n";
  global $dbtype;
  global $moldatatable;
  global $rxndatatable;
  if ($dbtype == 1) {
    $idname = "mol_id";
    $namename = "mol_name";
    $datatable = $moldatatable;
  } elseif ($dbtype == 2) {
    $idname = "rxn_id";
    $namename = "rxn_name";
    $datatable = $rxndatatable;
  }
  //echo "<table bgcolor=\"#eeeeee\">\n";
  echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"4\">\n";
  $qstr = "SHOW FULL COLUMNS FROM $datatable";
  $result = mysql_query($qstr)
    or die("Query failed! (showData)");
  while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $field   = $line["Field"];
    $label = $field;
    $type    = $line["Type"];
    $comment = $line["Comment"];
    if (($field != $idname) && ($field != $namename)) {
      $format = 1;
      if (strlen($comment)>4) {
        $pos = strpos($comment, ">>>>");
        if ($pos !== false) {
          if ($pos == 0) {
            $comment = str_replace(">>>>","",$comment);
            $acomment = explode("<",$comment);
            $label  = $acomment[0];
            $nformat = $acomment[1];
            if ($nformat == 0) { $format = 0; }
            if ($nformat == 1) { $format = 1; }
            if ($nformat == 2) { $format = 2; }
            if ($nformat == 3) { $format = 3; }
            if ($nformat == 4) { $format = 4; }
          }
        }
      }
      $dataval = "";   //preliminary....
      $qstr2 = "SELECT $field FROM $datatable WHERE $idname = $id";
      $result2 = mysql_query($qstr2)
        or die("Query failed! (dataval)");
      $line2 = mysql_fetch_row($result2);
      mysql_free_result($result2);
      $dataval = $line2[0];
      if (($format > 0) && (strlen($dataval) > 0)) {
        if ($label != "") { $field = $label; }
        echo "<tr><td valign=\"top\"><b>$field</b></td>";
        if ($format == 1) { echo "<td valign=\"top\">$dataval</td></tr>\n"; }
        if ($format == 2) { echo "<td valign=\"top\"><pre>$dataval</pre></td></tr>\n"; }
        if ($format == 3) { 
          $mfdata = mfreformat($dataval);
          echo "<td valign=\"top\">$mfdata</td></tr>\n";
        }
        if ($format == 4) { 
          $urldata = urlreformat($dataval);
          echo "<td valign=\"top\">$urldata</td></tr>\n";
        }
      } // if ($format > 0)...
    }  // if...
  }
  echo "</table>\n";
  mysql_free_result($result);
}


if (($safemol_id !='') || ($saferxn_id !=''))  { 
  print "<h2>${sitename}: details for selected entry</h2>\n";
  if (($dbtype == 1) && ($safemol_id !='')) {
    showHit2($safemol_id);
    showData($safemol_id);
  } elseif (($dbtype == 2) && ($saferxn_id !='')) {
    showHit2rxn($saferxn_id);
    showData($saferxn_id);
  }
} else {
  echo "<h2>No molecule/reaction ID specified!</h2>\n";
}

?>
</body>
</html>
