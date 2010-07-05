<?php
// index.php        Norbert Haider, University of Vienna, 2009-2010
// part of MolDB5R  last change: 2010-06-09

$myname = $_SERVER['PHP_SELF'];
include("moldb5conf.php");
require_once("functions.php");

if (config_quickcheck() > 0) { die(); }

if (!isset($sitename) || ($sitename == "")) {
  $sitename = "MolDB5R demo";
}

$db   = $_REQUEST['db'];
$dbl     = $_POST['dbl'];

$link = mysql_pconnect($hostname,"$ro_user", "$ro_password")
  or die("Could not connect to database server!");
mysql_select_db($database)
  or die("Could not select database!");    


if (!isset($dbl)) {
  $dbl = array();
  $dbl = explode(",",$db);
}

$dba = array();
$dbstr = "";

$ndbsel = 0;
foreach ($dbl as $id) {
  $db_id = check_db($id);
  if (($db_id > 0) && (($ndbsel < 1) || ($multiselect == "y"))) {
    $ndbsel++;
    $dba[($ndbsel - 1)] = $dbl[($ndbsel - 1)];
    if (strlen($dbstr)>0) { $dbstr .= ","; }
    $dbstr .= "$db_id";
  }
}

if ($ndbsel < 1) {
  $ndbl = 1;
  $dba[0] = $default_db;
  $ndbsel = 1;
  $dbstr = "$defaultdb";
  $db_id = $default_db;  
}

//echo "$dba[0] $dbl[1] ($ndbsel): $dbstr<br>";

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<link href="moldb.css" rel="stylesheet" type="text/css">
<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">
<meta name="author" content="Norbert Haider, University of Vienna">
<title><?php echo "$sitename"; ?></title>
</head>
<body>

<?php
  show_header($myname,$dbstr);
  echo "<h1>$sitename</h1>\n";
?>

This is an example application which demonstrates the use of the "<a
 href="http://merian.pch.univie.ac.at/~nhaider/cheminf/cmmm.html">checkmol/matchmol</a>"
utility program in order to create a web-based, searchable molecular structure 
database. More information about the underlying technology can be found 
<a href="http://merian.pch.univie.ac.at/~nhaider/cheminf/moldb5.html">here</a>.<br />
<br />

<?php

if ($enablereactions == "y") { $onlysd = ""; } else { $onlysd = " AND (type = 1) "; }

$result = mysql_query("SELECT db_id, name FROM $metatable WHERE (access > 0) $onlysd ORDER BY db_id")
  or die("Query failed! (1)");
$ndb = mysql_num_rows($result);
$db_id = $dba[0];

if ($ndb == 0) {
  echo "There is no data collection available in the moment. The administrator can add ";
  echo "new collections via the <a href=\"admin/\" target=\"blank\">administration page</a> ";
  echo "or via import of SD files.<p />\n";
} elseif ($multiselect == "n") {
  echo "<h3>Available data collections:</h3>\n";
  echo "<form action=\"$myname\" method=\"post\">\n";
  echo "<select size=\"1\" name=\"db\">\n";
  while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $db   = $line["db_id"];
    $name = $line["name"];
    echo "<option value=\"$db\"";
    if ($db == $db_id) { echo " selected"; }
    echo ">$db: $name</option>\n";
  }
  mysql_free_result($result);
  echo "</select>\n";
  echo "<input type=\"Submit\" name=\"select\" value=\"Apply selection\">\n";
  echo "</form>\n";
} else {         // multiselect
  if ($ndb <= 5) { $maxlines = $ndb; } else { $maxlines = 5; }
  echo "<h3>Available data collections:</h3>\n";
  echo "<form action=\"$myname\" method=\"post\">\n";
  echo "<select size=\"$maxlines\" name=\"dbl[]\" multiple>\n";
  while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $db   = $line["db_id"];
    $name = $line["name"];
    echo "<option value=\"$db\"";
    if (in_array($db,$dba)) { echo " selected"; }
    echo ">$db: $name</option>\n";
  }
  mysql_free_result($result);
  echo "</select>\n";
  echo "<input type=\"Submit\" name=\"select\" value=\"Apply selection\">\n";
  echo "</form>\n";
}

?>
<br />

<table width="100%" bgcolor="#EEEEEE">
<tr align="left"><th><h3>Current&nbsp;selection:</h3></th><th></th></tr>
<?php

  $qstr = "SELECT db_id, name, description FROM $metatable WHERE ";
  for ($i = 0; $i < $ndbsel; $i++) {
    if ($i > 0) { $qstr .= " OR"; }
    $qstr .= " (db_id = " . $dba[$i] . ")";
  }
  $qstr .= " ORDER BY db_id";

  $result2 = mysql_query($qstr)
    or die("Query failed! (2)");
  while ($line2 = mysql_fetch_array($result2, MYSQL_ASSOC)) {
    $db   = $line2["db_id"];
    $name = $line2["name"];
    $description = $line2["description"];
    echo "<tr align=\"left\"><td><b>$name</b></td><td>$description</td></tr>\n";
  }
  mysql_free_result($result2);

?>
</table>
<p>&nbsp;</p>

<a href="admin/?db=<?php echo "$db_id"; ?>" target="admin">Administration</a>
<hr>
<small>MolDB5R 2010</small>
<br />
</body>
</html>
