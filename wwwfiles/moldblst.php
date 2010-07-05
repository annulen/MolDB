<?php 
// moldblst.php     Norbert Haider, University of Vienna, 2008-2010
// part of MolDB5R  last change: 2010-06-09

$myname = $_SERVER['PHP_SELF'];
require_once("functions.php");

$increment = 25;

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
  $sitename = "MolDB5R demo";
}

$dbstr   = $_REQUEST['db'];
$dbl     = explode(",",$dbstr);
$idx     = $_REQUEST['idx'];

$link = mysql_pconnect($hostname,"$ro_user", "$ro_password")
  or die("Could not connect to database server!");
mysql_select_db($database)
  or die("Could not select database!");    

if (!isset($dbl)) {
  $dbl = array();
  $dbl[0] = $db;
}

$dba    = array();
$dbstr  = "";
$dbstr2 = "";

$ndbsel = 0;
foreach ($dbl as $id) {
  $db_id = check_db($id);
  if (($db_id > 0) && (($ndbsel < 1) || ($multiselect == "y"))) {
    $ndbsel++;
    $dba[($ndbsel - 1)] = $dbl[($ndbsel - 1)];
    if (strlen($dbstr)>0) { $dbstr .= ","; $dbstr2 .= " "; }
    $dbstr .= "$db_id"; $dbstr2 .= "$db_id";
  }
}

if (exist_db($default_db) == FALSE) {
  $default_db = get_lowestdbid(); 
}

if ($ndbsel < 1) {
  $ndbl = 1;
  $dba[0] = $default_db;
  $ndbsel = 1;
  $dbstr = "$default_db";
  $db_id = $default_db;
}

$dbindex = 1;
if ((isset($idx)) && (is_numeric($idx))) {
  if (($idx > 0) && ($idx <= $ndbsel)) {
    $dbindex = $idx;
  }
}

$offset = intval($_REQUEST['offset']);
if (!isset($offset)) {
  $offset = 0;
} else {
  if (!is_numeric($offset)) {
    $offset = 0;
  } else {
    if (!is_integer($offset)) {
      $offset = 0;
    }
  }
}
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<link href="moldb.css" rel="stylesheet" type="text/css">
<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">
<meta name="author" content="Norbert Haider, University of Vienna">
<title><?php echo "$sitename"; ?>: text search</title>
</head>
<body>

<?php
$action = $_POST['action'];
$db_id  = $dba[($dbindex - 1)];

if ($enablereactions == "y") { $onlysd = ""; } else { $onlysd = " AND (type = 1) "; }
$qstr01 = "SELECT * FROM $metatable WHERE (db_id = $db_id) $onlysd";

$result01 = mysql_query($qstr01)
  or die("Query failed (#1)!");    
while($line01 = mysql_fetch_array($result01)) {
  $db_id = $line01['db_id'];
  $dbtype = $line01['type'];
  $dbname = $line01['name'];
  $digits = $line01['digits'];
  $subdirdigits = $line01['subdirdigits'];
}
mysql_free_result($result01);

if (!isset($digits) || (is_numeric($digits) == false)) { $digits = 8; }
if (!isset($subdirdigits) || (is_numeric($subdirdigits) == false)) { $subdirdigits = 0; }
if ($subdirdigits < 0) { $subdirdigits = 0; }
if ($subdirdigits > ($digits - 1)) { $subdirdigits = $digits - 1; }

$dbprefix       = "db" . $db_id . "_";
$molstructable = $prefix . $dbprefix . $molstrucsuffix;
$moldatatable  = $prefix . $dbprefix . $moldatasuffix;
$molstattable  = $prefix . $dbprefix . $molstatsuffix;
$molcfptable   = $prefix . $dbprefix . $molcfpsuffix;
$pic2dtable    = $prefix . $dbprefix . $pic2dsuffix;
$rxnstructable = $prefix . $dbprefix . $rxnstrucsuffix;
$rxndatatable  = $prefix . $dbprefix . $rxndatasuffix;
if ($usemem == 'T') {
  $molstattable  = $molstattable . $memsuffix;
  $molcfptable   = $molcfptable  . $memsuffix;
}

show_header($myname,$dbstr);

echo "<h1>${dbname}: browse content</h1>\n";
echo "<hr />\n";

if ($dbtype == 1) {
  $idname = "mol_id";
  $structable = $molstructable;
  $datatable = $moldatatable;
} elseif ($dbtype == 2) {
  $idname = "rxn_id";
  $structable = $rxnstructable;
  $datatable = $rxndatatable;
}

$qstr = "SELECT COUNT($idname) AS itemcount FROM $structable";
$result = mysql_query($qstr)
  or die("Query failed (#1a)!");    
$line = mysql_fetch_row($result);
mysql_free_result($result);
$itemcount = $line[0];
if ($itemcount > 0) { 
  shownavigation($offset,$increment,$itemcount);
  echo "<hr />\n<table width=\"100%\">\n";
  $qstr1 = "SELECT $idname FROM $structable LIMIT $offset,$increment";
  $result1 = mysql_query($qstr1)
    or die("Query failed ($idname)!");    
  while ($line1 = mysql_fetch_array($result1,MYSQL_ASSOC)) {
    $item_id = $line1[$idname];
    if ($dbtype == 1) { showHit($item_id,""); }
    if ($dbtype == 2) { showHitRxn($item_id,""); }
  }
  mysql_free_result($result1);
  echo "</table>\n<hr />\n";
  shownavigation($offset,$increment,$itemcount);
}   // if $itemcount > 0....

echo "\n<hr />\n";

echo "<small>entries in data collection: $itemcount</small><br />\n";
echo "</body>\n";
echo "</html>\n";

function shownavigation($offset,$increment,$itemcount) {
  global $myname;
  global $db_id;
  global $dbstr;
  global $dbindex;
  global $dba;
  global $ndbsel;
  // GET version
  echo "<div align=\"left\">";
  $urldbidx = 0;
  foreach ($dba as $id) {
    $dburlidx++;
    if ($id == $db_id) {
      echo " <b>$id</b>";
    } else {
      echo " <a href=\"$myname?db=$dbstr&idx=$dburlidx&offset=0 \">$id</a>";
    }
  }
  echo "</div>\n<div align=\"center\">\n";
  if ($offset <= 0) {
    echo "&nbsp;&nbsp;&lt;&lt;&nbsp;&nbsp;";
    echo "&nbsp;&nbsp;&lt;&nbsp;&nbsp;";
  } else {
    echo "&nbsp;&nbsp;<a href=\"$myname?db=$dbstr&idx=$dbindex&offset=0\">&lt;&lt;</a>&nbsp;&nbsp;";
    $newoffset = $offset - $increment;
    if ($newoffset < 0) {$newoffset = 0; }
    echo "&nbsp;&nbsp;<a href=\"$myname?db=$dbstr&idx=$dbindex&offset=$newoffset\">&lt;</a>&nbsp;&nbsp;";
  }
  if ($offset + $increment >= $itemcount) {
    echo "&nbsp;&nbsp;&gt;&nbsp;&nbsp;";
    echo "&nbsp;&nbsp;&gt;&gt;&nbsp;&nbsp;";
  } else {
    $newoffset = $offset + $increment;
    if ($newoffset > $itemcount) {$newoffset = $itemcount; }
    echo "&nbsp;&nbsp;<a href=\"$myname?db=$dbstr&idx=$dbindex&offset=$newoffset\">&gt;</a>&nbsp;&nbsp;";
    $newoffset = $itemcount - $increment;
    echo "&nbsp;&nbsp;<a href=\"$myname?db=$dbstr&idx=$dbindex&offset=$newoffset\">&gt;&gt;</a>&nbsp;&nbsp;";
  }
  echo "</div>\n";
}  

?>
