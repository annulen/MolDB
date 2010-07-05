<?php 
// moldbsss.php     Norbert Haider, University of Vienna, 2004-2010
// part of MolDB5R  last change: 2010-06-09

$myname = $_SERVER['PHP_SELF'];
require_once("functions.php");
require_once("rxnfunct.php");

$debug = 1;        // 0: remain silent, higher values: be more verbose
                   // odd numbers: output as HTML comments, 
                   // even numbers: output as clear-text messages

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

$user     = $ro_user;           # from configuration file
$password = $ro_password;

if ($user == "") {
  die("no username specified!\n");
}

if (!isset($sitename) || ($sitename == "")) {
  $sitename = "MolDB5R demo";
}

$ostype = getostype();

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
    //$dba[($ndbsel - 1)] = $dbl[($ndbsel - 1)];
    $dba[($ndbsel - 1)] = $db_id;
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

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<link href="moldb.css" rel="stylesheet" type="text/css">
<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">
<meta http-equiv="imagetoolbar" content="no"> 
<meta name="author" content="Norbert Haider, University of Vienna">
<title><?php echo "$sitename"; ?>: structure search</title>
</head>
<body>
<?php
if ($debug > 0) { debug_output("MolDB5R, OS type $ostype \n"); }
$defaultmode = 2;              // 1 = exact, 2 = substructure, 3 = similarity
$exact  = "n";                 // search options
$strict = "n";
$stereo = "n";

$maxhits = 1000;               // maximum number of hits we want to allow
$maxcand = 5000;               // maximum number of candidate structures we want to allow

$smiles  = $_POST['smiles'];
$jme     = $_POST['jme'];
$mol     = $_POST['mol'];
$rinfo   = $_POST['rinfo'];
$rmode   = $_POST['mode'];
$strict  = $_POST['strict'];
$stereo  = $_POST['stereo'];
# $db_id   = $_REQUEST['db'];

$usebfp  = 'y';
$usehfp  = 'y';

$mode = $defaultmode;
if (!isset($rmode)) { $mode   = $defaultmode; }       # 1 = exact, 2 = substructure, 3 = similarity
if ($rmode == 1) { $mode = 1; }
if ($rmode == 2) { $mode = 2; }
if ($rmode == 3) { $mode = 3; }

if ($mode == 1) {
  $exact = "y";
}

if ($exact == 'y') {
  $usebfp  = 'n';
  $usehfp  = 'n';
}

show_header($myname,$dbstr);

echo "<h1>${sitename}: structure search</h1>\n";

$jmemol = "";
if (strlen($mol) > 10) {
  $jmemol = str_replace("\n","|",$mol);
}

echo "<table cellpadding=\"2\" cellspacing=\"2\" border=\"0\" width=\"100%\">\n";
echo "<tr>\n";
echo "<td>\n";
echo "<applet name=\"JME\" code=\"JME.class\" \n";
echo "archive=\"JME.jar\" width=\"350\" height=\"288\">\n";
if (strlen($jme) > 2) {
  echo "<param name=\"jme\" value=\"$jme\">\n";
} else {
  if (strlen($mol) > 10) {
    echo "<param name=\"mol\" value=\"$jmemol\">\n";
  }
}
//echo "<param name=\"options\" value=\"xbutton, query, hydrogens\">\n";
echo "<param name=\"options\" value=\"xbutton, hydrogens\">\n";
echo "You have to enable Java in your browser.\n";
echo "</applet>\n";

echo "</td>\n<td>\n";
echo "<br />\n";
echo "<small>\n";
echo "special symbols (to be entered via X-button):<br />\n";
echo "<b>A</b>: any atom except H<br />\n";
echo "<b>Q</b>: any atom except H and C<br />\n";
echo "<b>X</b>: any halogen atom<br />\n";
echo "<b>H</b>: explicit hydrogen<br />\n";
echo "<br />\n"; 
echo "<a href=\"http://www.molinspiration.com/jme/\" target=\"blank\">JME applet</a> \n";
echo "courtesy of Peter Ertl, Novartis\n";

echo "</small>\n";
// echo "<small><a href=\"jmehints.html\">JME help</a></small><br />\n"; 
echo "</tr>\n</table>\n";

echo "<p>\n";
?>
 
<script> 
  function check_ss() {
    var smiles = document.JME.smiles();
    var jme = document.JME.jmeFile(); 
    var mol = document.JME.molFile();
    if (smiles.length < 1) {
      alert("No molecule!");
    }
    else {
      document.form.smiles.value = smiles;
      document.form.jme.value = jme;
      document.form.mol.value = mol;
      var info = document.referrer;
      info += " - " + navigator.appName + " - " + navigator.appVersion;
      info += " " + screen.width + "x" + screen.height;
      document.form.rinfo.value = info;
      document.form.submit();
    }
  }
</script>

<form name="form" action="<?php echo $myname;?>" method="post">
<input type="radio" name="mode" value="1" <?php if ($mode == 1) { echo "checked"; } ?>>exact search
<input type="radio" name="mode" value="2" <?php if ($mode == 2) { echo "checked"; } ?>>substructure search
<input type="radio" name="mode" value="3" <?php if ($mode == 3) { echo "checked"; } ?>>similarity search<br />
<input type="checkbox" name="strict" value="y" <?php if ($strict == "y") { echo "checked"; } ?>>strict atom/bond type comparison<br />
<input type="checkbox" name="stereo" value="y" <?php if ($stereo == "y") { echo "checked"; } ?>>check configuration (E/Z and R/S)<br />&nbsp;<br />
<input type="button" value="&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Search&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" onClick="check_ss()">
<input type="hidden" name="smiles">
<input type="hidden" name="jme">
<input type="hidden" name="mol">
<input type="hidden" name="rinfo">
<input type="hidden" name="db" value="<?php echo "$dbstr"?>">
</form>

<?php

echo "<hr />\n";

$options = '';
if ($strict == 'y') {
  $options = $options . 'ais';  // 'a' for charges, 'i' for isotopes (checkmol v0.3p)
}
if ($exact == 'y') {
  $options = $options . 'x';
}
if ($stereo == 'y') {
  $options = $options . 'gG';
}

if (strlen($options) > 0) {
  $options = '-' . $options;
}

// remove CR if present (IE, Mozilla et al.) and add it again (for Opera)
$mol = str_replace("\r\n","\n",$mol);
$mol = str_replace("\n","\r\n",$mol);

//$safemol = escapeshellcmd($mol);
$safemol = str_replace(";"," ",$mol);

if ($mode < 3) {
  include("incss.php");
} else {
  include("incsim.php");
}

?>
</body>
</html>
