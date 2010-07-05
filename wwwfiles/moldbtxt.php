<?php 
// moldbtxt.php     Norbert Haider, University of Vienna, 2005-2010
// part of MolDB5R  last change: 2010-06-09

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
  $sitename = "MolDB5R demo";
}

$dbstr   = $_REQUEST['db'];
$dbl     = explode(",",$dbstr);
$dbstr_orig = $dbstr;

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

//echo "$ndbsel elements: $dbstr<br>";

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
$maxhits = 500;               // maximum number of hits we want to allow

$maxtextlen = 80;             //  maximum number of characters we want to allow
$action = $_POST['action'];
$mode = $_POST['mode'];
if ($mode != "2") { $mode = 1; }
$textinput = substr($_POST['name'],0,$maxtextlen);

show_header($myname,$dbstr);

echo "<h1>${sitename}: text search</h1>\n";
echo "<p>Enter search term (chemical name or name fragment):</p>\n";

echo "<form method=\"post\" action=\"$myname\">\n";
echo "<input type=\"text\" size=\"40\" name=\"name\">\n";
echo "<input type=\"hidden\" name=\"action\" value=\"search\">\n";
echo "<input type=\"Submit\" name=\"Submit\" value=\"Search\"><br />\n";
echo "<input type=\"hidden\" name=\"db\" value=\"$dbstr\">\n";
echo "<input type=\"radio\" name=\"mode\" value=\"1\"";
if ($mode == 1) { echo " checked"; }
echo ">only name\n";
echo "<input type=\"radio\" name=\"mode\" value=\"2\"";
if ($mode == 2) { echo " checked"; }
echo ">include other searchable fields\n";
echo "</form>\n";

echo "<hr />\n";
echo "<h3>Found entries:</h3>\n";
echo "<table width=\"100%\">\n";

if (($action=="search") && (strlen($textinput) > 2)) {
  $time_start = getmicrotime();  
  if (get_magic_quotes_gpc()) {
    $textinput = stripslashes($textinput);
  }     
  $nhitstotal = 0;

  foreach ($dba as $db_id) {
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
    if ($enablereactions == "y") { $onlysd = ""; } else { $onlysd = " AND (type = 1) "; }
    $qstr01 = "SELECT * FROM $metatable WHERE (db_id = $db_id) $onlysd";
    
    $result01 = mysql_query($qstr01)
      or die("Query failed (#1a)!");    
    while($line01=mysql_fetch_array($result01)) {
      $db_id        = $line01['db_id'];
      $dbtype       = $line01['type'];
      $dbname       = $line01['name'];
      $digits       = $line01['digits'];
      $subdirdigits = $line01['subdirdigits'];
    }
    mysql_free_result($result01);
    if ($dbtype == 1) {
      $idname = "mol_id";
      $namename = "mol_name";
      $datatable = $moldatatable;
    } elseif ($dbtype == 2) {
      $idname = "rxn_id";
      $namename = "rxn_name";
      $datatable = $rxndatatable;
    }
    
    if (!isset($digits) || (is_numeric($digits) == false)) { $digits = 8; }
    if (!isset($subdirdigits) || (is_numeric($subdirdigits) == false)) { $subdirdigits = 0; }
    if ($subdirdigits < 0) { $subdirdigits = 0; }
    if ($subdirdigits > ($digits - 1)) { $subdirdigits = $digits - 1; }

    $searchtext = str_replace(";"," ",$textinput);
    $searchtext = "%" . mysql_real_escape_string($searchtext) . "%";
    //$searchtext = "%" . mysql_escape_string($searchtext) . "%";  // use this for older PHP versions

    if ($mode == 2) {   // get other searchable fields
      $addstr = "";
      $qstr02 = "SHOW FULL COLUMNS FROM $datatable";
      $result02 = mysql_query($qstr02)
        or die("Query failed (#1a)!");    
      while($line02=mysql_fetch_array($result02)) {
        $fieldname = $line02["Field"];
        $fieldtype = $line02["Type"];
        $comment   = $line02["Comment"];
        $pos = strpos($comment, ">>>>");
        if ($pos !== false) {
          $fieldprop = getfieldprop($comment);
          $searchabletype = is_stringtype($fieldtype);  // should be checked!  (only char, varchar, text, enum, set)
          if (($fieldprop["searchmode"] == 1) && ($searchabletype ==1)) {
            $addstr .= " OR (" . $fieldname . " LIKE \"$searchtext\")";
          }
        }
      }
      mysql_free_result($result02);
    }  // if ($mode == 2)

    $limit = $maxhits + 1;
    $qstr = "SELECT $idname FROM $datatable WHERE ($namename LIKE \"$searchtext\") $addstr GROUP BY $idname LIMIT $limit";
  
    //echo "$qstr <br />\n";
  
    $result = mysql_query($qstr)
      or die("Query failed (#1b)!");    
    $hits = 0;
  
    $nhits = mysql_num_rows($result);
    $nhitstotal = $nhitstotal + $nhits; 
  
    while($line=mysql_fetch_array($result)) {
      $item_id = $line[$idname];
      $hits ++;
      // output of the hits, if they are not too many...
      if ( $hits > $maxhits ) {
        echo "</table>\n";
        echo "<p>Too many hits (>$maxhits)! Aborting....</p>\n";
        echo "</body>\n";
        echo "</html>\n";
        exit;
      }
      if ($dbtype == 1) { showHit($item_id,""); }
      if ($dbtype == 2) { showHitRxn($item_id,""); }
      //echo "Hit: $mol_id<br>\n";
    } // end while($line)...
    mysql_free_result($result);

  }  // foreach

  echo "</table>\n<hr>\n";

  $time_end = getmicrotime();  
  print "<p><small>number of hits: <b>$nhitstotal</b><br />\n";
  $time = $time_end - $time_start;
  printf("time used for query: %2.3f seconds </small></p>\n", $time);
}

echo "\n";
echo "</body>\n";
echo "</html>\n";
?>
