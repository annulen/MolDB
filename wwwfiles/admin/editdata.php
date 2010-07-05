<?php
// admin/editdata.php    Norbert Haider, University of Vienna, 2009-2010
// part of MolDB5R       last change: 2010-06-14

$myname = $_SERVER['PHP_SELF'];
@include("moldb5conf.php");    // if moldb5conf.php is in the PHP include path
@include("../moldb5conf.php"); // if moldb5conf.php is where it should *not* be...
require_once("../functions.php");
require_once("../rxnfunct.php");
require_once("dbfunct.php");

$debug = 1;        // 0: remain silent, higher values: be more verbose
                   // odd numbers: output as HTML comments, 
                   // even numbers: output as clear-text messages

if (config_quickcheck() > 0) { die(); }

if (!isset($sitename) || ($sitename == "")) {
  $sitename = "MolDB5R demo";
}

$db_id    = $_REQUEST['db'];
$datatype = $_REQUEST['datatype'];
$action   = $_REQUEST['action'];
$smiles   = $_POST['smiles'];
$jme      = $_POST['jme'];
$mol      = $_POST['mol'];

$ostype = getostype();

$link = mysql_pconnect($hostname,"$rw_user", "$rw_password")
  or die("Could not connect to database server!");
mysql_select_db($database)
  or die("Could not select database!");    

$db_id = check_db($db_id);
if ($db_id < 0) {
  $db_id = $default_db;
  $db_id = check_db_all($db_id);
  if ($db_id < 0) {
    $db_id = get_lowestdbid();
  }
}

$mydb = get_dbproperties($db_id);
$dbtype = $mydb['type'];
if (($dbtype == 1) && ($datatype != 1)) { $datatype = 1; }
if (($dbtype == 2) && ($datatype != 2)) { $datatype = 2; }

$ip = $_SERVER['REMOTE_ADDR'];
$trusted = is_trustedIP($ip);
if ($trusted == false) {
  $trusted = is_db_trustedIP($db_id,$ip);   // try if IP is trusted for this db
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<link href="../moldb.css" rel="stylesheet" type="text/css">
<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">
<meta name="author" content="Norbert Haider, University of Vienna">
<title><?php echo "$sitename (administration page)"; ?></title>
</head>
<body>
<?php
echo "<!-- MolDB5R, OS type $ostype --!>\n";
echo "<h1>$sitename: data input/editing</h1>\n";
echo "On this page, you can add new MolDB5R entries and erase or edit existing ones.<br />\n";
echo "<hr />\n";
echo "<small>selected data collection: $db_id</small><p />\n";

$dbprefix      = $prefix . "db" . $db_id . "_";
$moldatatable  = $dbprefix . $moldatasuffix;
$molfgbtable   = $dbprefix . $molfgbsuffix;
$molstattable  = $dbprefix . $molstatsuffix;
$molstructable = $dbprefix . $molstrucsuffix;
$molcfptable   = $dbprefix . $molcfpsuffix;
$pic2dtable    = $dbprefix . $pic2dsuffix;

$rxnstructable = $dbprefix . $rxnstrucsuffix;
$rxndatatable  = $dbprefix . $rxndatasuffix;
$rxnfgbtable   = $dbprefix . $rxnfgbsuffix;
$rxncfptable   = $dbprefix . $rxncfpsuffix;


$mydb = get_dbproperties($db_id);
$db     = $mydb['db_id'];
$access = $mydb['access'];
$usemem = $mydb['usemem'];
$dbtype = $mydb['type'];


if ($dbtype == 1) {
  $structable = $molstructable;
  $idname = "mol_id";
  $namename = "mol_name";
} elseif ($dbtype == 2) {
  $structable = $rxnstructable;
  $idname = "rxn_id";
  $namename = "rxn_name";
}

if (($access < 2) && ($trusted == FALSE)) {
  echo "Your client IP is not authorized to perform the requested operation!<br />\n";
  //echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
  echo "</body></html>\n";
  die();
}


if ($action == "addstruc1") {
  if ($dbtype == 1) { $what = "structure"; } elseif ($dbtype == 2) { $what = "reaction"; }
  echo "<h3>Add a new $what to data collection $db_id</h3>\n";

  echo "<table>\n<tr>\n<td valign=\"top\">\n";

  if ($dbtype == 1) {
    echo "<small>draw the molecule in the applet window....</small><br />\n";
    echo "<applet name=\"JME\" code=\"JME.class\" \n";
    echo "archive=\"../JME.jar\" width=\"350\" height=\"290\">\n";
    echo "<param name=\"jme\" value=\"$jme\">\n";
    //echo "<param name=\"options\" value=\"xbutton, query, hydrogens\">\n";
    echo "<param name=\"options\" value=\"xbutton, hydrogens, multipart\">\n";
    echo "You have to enable Java in your browser.\n";
    echo "</applet>\n";
  } elseif ($dbtype == 2) {
    
    echo "<small>draw the reaction in the applet window....</small><br />\n";
    echo "<applet name='JME' code='JME.class' \n";
    echo "archive='../JME.jar' width=450 height=288>\n";
    if (strlen($jme) > 0) {
      echo "<param name=\"jme\" value=\"$jme\">\n";
    }
    echo "<param name=\"options\" value=\"xbutton, hydrogens, reaction, multipart\">\n";
    echo "You have to enable Java in your browser.\n";
    echo "</applet>\n";
  }
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
      document.form.submit();
    }
  }
</script>

<p />
<form name="form" action="<?php echo $myname; ?>" method="post">
<input type="button" value="&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Submit&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" onClick="check_ss()">
<input type="hidden" name="smiles">
<input type="hidden" name="jme">
<input type="hidden" name="mol">
<input type="hidden" name="action" value="addstruc2">
<input type="hidden" name="db" value="<?php echo "$db_id";?>">
</form>

  <?php
  
  echo "</td>\n<td valign=\"top\">\n";
  if ($dbtype == 1) {
    echo "<small>...or paste it in MDL (V2000) molfile format into the text window</small><br />\n";
  } elseif ($dbtype == 2) {
    echo "<small>...or paste it in MDL (V2000) rxnfile format into the text window</small><br />\n";
  }
  echo "<form name=\"form2\" action=\"$myname\" method=\"post\">\n";
  echo "<textarea name=\"mol\" cols=\"70\" rows=\"17\"></textarea>\n";
  echo "<input type=\"hidden\" name=\"action\" value=\"addstruc2\">\n";
  echo "<input type=\"hidden\" name=\"db\" value=\"$db_id\"><p />\n";
  echo "<input type=\"Submit\" name=\"select\" value=\"&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Submit&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\">\n";
  echo "</form>\n";
  
  echo "</td></tr>\n</table>\n";
  
  echo "<p /><a href=\"$myname?db=$db_id\">Continue without saving</a>\n";
  echo "</body></html>\n";
  die();
}

if ($action == "addstruc2") {
  if ($dbtype == 1) { 
    $what = "structure"; 
    $reserved_mol_id = get_next_mol_id($db_id);
  } elseif ($dbtype == 2) { 
    $what = "reaction"; 
    $reserved_mol_id = get_next_rxn_id($db_id);
  }
  // find out the next available mol_id
  // get next available mol_id 


  // check if we got a valid molfile (either from JME or from the text area)
  if (is_validmol($mol) == FALSE) {
    echo "<h3>ERROR: this is not a valid input MDL (V2000) molfile!</h3>\n";
    echo "<pre>$safemol </pre>\n";
    echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
    echo "</body></html>\n";
    die();
  }
  // if in reaction mode, check if we have the rxn file header (if not, add it)
  if ($dbtype == 2) {
    if (strpos($mol, '$RXN') === FALSE) {
      $mol = "\$RXN\n\n\n\n  0  1\n\$MOL\n" . $mol;
    }
  }

  echo "<h3>Do you really want to add this $what to data collection $db_id?</h3>\n";

  // JME needs MDL molfiles with the "|" character instead of linebreaks
  $jmehitmol = strtr($mol,"\n","|");
      
  echo "<applet name=\"JME\" code=\"JME.class\" archive=\"../JME.jar\" \n";
  if ($dbtype == 1) {
    echo "width=\"250\" height=\"220\">";
  } elseif ($dbtype == 2) {
    echo "width=\"450\" height=\"220\">";
  }
  
  echo "<param name=\"options\" value=\"depict\"> \n";
  echo "<param name=\"mol\" value=\"$jmehitmol\">\n";
  echo "</applet>\n";
?>

<script> 
  function submit1_ss() {
    var smiles = document.JME.smiles();
    var jme = document.JME.jmeFile(); 
    var mol = document.JME.molFile();
    if (smiles.length < 1) {
      alert("No molecule!");
    }
    else {
      document.addstruc1.smiles.value = smiles;
      document.addstruc1.jme.value = jme;
      document.addstruc1.mol.value = mol;
      document.addstruc1.submit();
    }
  }
</script>

<script> 
  function submit2_ss() {
    var smiles = document.JME.smiles();
    var jme = document.JME.jmeFile(); 
    var mol = document.JME.molFile();
    if (smiles.length < 1) {
      alert("No molecule!");
    }
    else {
      document.addstruc2.smiles.value = smiles;
      document.addstruc2.jme.value = jme;
      document.addstruc2.mol.value = mol;
      document.addstruc2.submit();
    }
  }
</script>
<?php

  echo "<form name=\"addstruc1\" action=\"$myname\" method=\"post\">\n";
  echo "<input type=\"hidden\" name=\"action\" value=\"addstruc3\">\n";
  echo "<input type=\"hidden\" name=\"db\" value=\"$db_id\">\n";
  echo "<input type=\"hidden\" name=\"nextid\" value=\"$reserved_mol_id\">\n";
  echo "<input type=\"hidden\" name=\"mol\" value=\"$mol\">\n";
  echo "<input type=\"hidden\" name=\"jme\" value=\"$jme\">\n";
  echo "<input type=\"hidden\" name=\"smiles\" value=\"$smiles\">\n";
//  echo "<input type=\"Submit\" name=\"select\" value=\"&nbsp;Yes, add new structure!\">\n";
  echo "<input type=\"button\" value=\"&nbsp;Yes, add new structure!\" onClick=\"submit1_ss()\">\n";

  echo "</form><p />\n";

  echo "<form name=\"addstruc2\" action=\"$myname\" method=\"post\">\n";
  echo "<input type=\"hidden\" name=\"action\" value=\"addstruc1\">\n";
  echo "<input type=\"hidden\" name=\"db\" value=\"$db_id\">\n";
  echo "<input type=\"hidden\" name=\"mol\" value=\"$mol\">\n";
  echo "<input type=\"hidden\" name=\"jme\" value=\"$jme\">\n";
  echo "<input type=\"hidden\" name=\"smiles\" value=\"$smiles\">\n";
//  echo "<input type=\"Submit\" name=\"select\" value=\"&nbsp;No, edit again\">\n";
  echo "<input type=\"button\" value=\"&nbsp;No, edit again\" onClick=\"submit2_ss()\">\n";
  echo "</form><p />\n";

  echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
  echo "</body></html>\n";
  die();
}

if (($action == "addstruc3") && ($dbtype == 1)) {    // structure input
  $errorcount = 0;
  $proposed_mol_id = $_POST['nextid'];
  if ($use_cmmmsrv == 'y') {
    /* create a TCP/IP socket */
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket < 0) {
      //echo "socket_create() failed.\nreason: " . socket_strerror ($socket) . "\n";
      echo "<!-- could not connect to cmmmsrv - reverting to checkmol/matchmol --!>\n";
      $use_cmmmsrv = "n";
    }
    $sockresult = socket_connect ($socket, $cmmmsrv_addr, $cmmmsrv_port);
    if ($sockresult < 0) {
      //echo "socket_connect() failed.\nreason: ($sockresult) " . socket_strerror($sockresult) . "\n";
      echo "<!-- could not connect to cmmmsrv - reverting to checkmol/matchmol --!>\n";
      $use_cmmmsrv = "n";
    }
  }
  if ($use_cmmmsrv == 'y') {
    $a = socket_read($socket, 250, PHP_NORMAL_READ);
    //echo "$a\n";
    $pos = strpos($a,"READY");
    if ($pos === false) {
      echo "<!-- could not connect to cmmmsrv - reverting to checkmol/matchmol --!>\n";
      $use_cmmmsrv = "n";
    }
    $pos = 0;
  }

  // remove CR if present (IE, Mozilla et al.) and add it again (for Opera)
  $mol = str_replace("\r\n","\n",$mol);
  $mol = str_replace("\n","\r\n",$mol);

  //$safemol = escapeshellcmd($mol);
  $safemol = str_replace(";"," ",$mol);
  $safemol = str_replace("\""," ",$safemol);
  $safemol = str_replace("\'"," ",$safemol);
  $safemol = str_replace("\´"," ",$safemol);
  $safemol = str_replace("\`"," ",$safemol);
  $safemol = str_replace("\|"," ",$safemol);
  
  // first, tweak the molfile (if enabled in config file)
  if ($tweakmolfiles == "y") {
    if ($use_cmmmsrv == 'y') {
      $safemol = filterthroughcmmm($safemol,"#### checkmol:m");
    } else {
      if ($ostype == 1) {$safemol = filterthroughcmd($safemol,"$CHECKMOL -m - "); }
  	if ($ostype == 2) {$safemol = filterthroughcmd2($safemol,"$CHECKMOL -m - "); }
    }
  }
  // check if we (still) have a valid MDL molfile
  if (is_validmol($safemol) == FALSE) {
    echo "<h3>ERROR: this is not a valid input MDL (V2000) molfile!</h3>\n";
    echo "<pre>$safemol </pre>\n";
    echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
    echo "</body></html>\n";
    die();
  }

  // next step: get the molecular statistics of the input structure
  // by piping it through the checkmol program

  //echo "adding this structure:\<br />\n<pre>$safemol</pre>\n";

  // get next available mol_id 
  $result = mysql_query("SELECT COUNT(mol_id) AS molcount FROM $molstructable")
    or die("Query failed! (1c3)");
  $line = mysql_fetch_row($result);
  mysql_free_result($result);
  $molcount = $line[0];
  if ($molcount == 0) { 
    $next_mol_id = 1; 
  } else {
    $result = mysql_query("SELECT MAX(mol_id) AS molcount FROM $molstructable")
      or die("Query failed! (1c4)");
    $line = mysql_fetch_row($result);
    mysql_free_result($result);
    $molcount = $line[0];
    $next_mol_id = $molcount + 1;
  }
 if ($proposed_mol_id != $next_mol_id) {
   echo "This entry exists already!<br />\n";
   echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
   echo "</body></html>\n";
   die();
 }

  // add structure to molstruc
  $qstr = "INSERT INTO $molstructable (`mol_id`, `struc`) VALUES ($next_mol_id, \"$safemol\" )";

  if ($debug > 0) { debug_output("adding structure as no. $next_mol_id to data collection $db_id <br />\n"); }

  $result = mysql_query($qstr);
  $err = 0;
  $err = mysql_errno();
  if ($err != 0) { 
    echo "<br />Action failed (#1/$err: " . mysql_error() . ")<br />\n"; 
    $errorcount++;
  } #else { echo "."; }

  if ($use_cmmmsrv == 'y') {
    $chkresult = filterthroughcmmm("$safemol", "#### checkmol:aXbH"); 
  } else {
    if ($ostype == 1) { $chkresult = filterthroughcmd("$safemol", "$CHECKMOL -aXbH - ");  }
	if ($ostype == 2) { 
      $safemol = str_replace("\n","\r\n",$safemol);
	  $chkresult = filterthroughcmd2("$safemol", "$CHECKMOL -aXbH - ");  
	}
  }
  #echo "<pre>$chkresult</pre>\n";

  if (strlen($chkresult) < 2) {
    echo "no response from checkmol (maybe a server configuration problem?)\n</body></html>\n";
    exit;
  }

  $cr      = explode("\n", $chkresult);
  $molstat = trim($cr[0]);
  $molfgb  = trim($cr[1]);
  $molfgb  = str_replace(";",",",$molfgb);
  $molhfp  = trim($cr[2]);
  $molhfp  = str_replace(";",",",$molhfp);

  $qstr = "INSERT INTO $molstattable VALUES ( $next_mol_id,$molstat )";
  $result = mysql_query($qstr);
  $err = 0;
  $err = mysql_errno();
  if ($err != 0) { 
    echo "<br />Action failed (#2/$err: " . mysql_error() . ")<br />\n"; 
    $errorcount++;
  } #else { echo "."; }

  $qstr = "INSERT INTO $molfgbtable VALUES ($next_mol_id,$molfgb )";
  $result = mysql_query($qstr);
  $err = 0;
  $err = mysql_errno();
  if ($err != 0) { 
    echo "<br />Action failed (#3/$err: " . mysql_error() . ")<br />\n"; 
    $errorcount++;
  } #else { echo "."; }

  // get the fingerprint dictionary
  $fpdefqstr = "SELECT fp_id, fpdef FROM $fpdeftable;";
  $fpdefresult = mysql_query($fpdefqstr)
      or die("Could not get fingerprint definition!"); 
  $i = -1;
  $n_dict = 0;
  $fpdef = array();
  while ($fpdefline = mysql_fetch_array($fpdefresult, MYSQL_ASSOC)) {
    $i++;
    $n_dict++;
    $fpdef[$i] = $fpdefline["fpdef"];
  } 
  mysql_free_result($fpdefresult);

  //create the dictionary-based fingerprints
  $moldfp = "";
  for ($k = 0; $k < $n_dict; $k++) {
    $dict = $fpdef[$k];
    if ($ostype == 1) { $cand = $safemol . "\n" . '$$$$' ."\n" . $dict; }
	if ($ostype == 2) {
	  $dict = str_replace("\r","",$dict);
	  $dict = str_replace("\n","\r\n",$dict);
	  $cand = $safemol . "\r\n" . '$$$$' ."\r\n" . $dict;
	}
    if ($use_cmmmsrv == 'y') {
      $dfpstr = filterthroughcmmm($cand,"#### MATCHMOL:F");
    } else {
      $cand   = str_replace("\$","\\\$",$cand);
      if ($ostype == 1) { $dfpstr = filterthroughcmd($cand,"$MATCHMOL -F - "); }
	  if ($ostype == 2) { $dfpstr = filterthroughcmd2($cand,"$MATCHMOL -F - "); }
    }
    $dfpstr = trim($dfpstr);
    if ($k > 0) { $moldfp .= ","; }
    $moldfp .= " " . $dfpstr;
  }  // for..

  //now insert dictionary-based and hash-based fingerprints into molcfptable
  $qstr = "INSERT INTO $molcfptable VALUES ($next_mol_id, $moldfp, $molhfp )";
  //echo "$qstr<br />\n";
  //echo "adding combined fingerprints for no. $next_mol_id to table $molcfptable.... ";
  $result = mysql_query($qstr);
  $err = 0;
  $err = mysql_errno();
  if ($err != 0) { 
    echo "<br />Action failed (#4i/$err: " . mysql_error() . ")<br />\n"; 
    $errorcount++;
  } #else { echo "."; }

  // add new record to pic2dtable
  $qstr = "INSERT INTO $pic2dtable VALUES ($next_mol_id, \"1\", \"3\" )"; // 0 = does not exist, 1 = OK, 2 = OK, but do not show, 3 = to be created/updated, 4 = to be deleted
  //echo "$qstr<br />\n";
  //echo "adding 2D depiction information for no. $next_mol_id to table $pic2dtable.... ";
  $result = mysql_query($qstr);
  $err = 0;
  $err = mysql_errno();
  if ($err != 0) { 
    echo "<br />Action failed (#5/$err: " . mysql_error() . ")<br />\n"; 
    $errorcount++;
  } #else { echo "."; }

  // add new record to moldatatable
  $qstr = "INSERT INTO $moldatatable (`mol_id`) VALUES (\"$next_mol_id\")";
  //echo "$qstr<br />\n";
  //echo "adding mol data for no. $next_mol_id to table $moldatatable.... ";
  $result = mysql_query($qstr);
  $err = 0;
  $err = mysql_errno();
  if ($err != 0) { 
    echo "<br />Action failed (#6/$err: " . mysql_error() . ")<br />\n"; 
    $errorcount++;
  } #else { echo "."; }

  // MySQL syntax for table description, including comments:
  // SHOW FULL COLUMNS FROM db3_pic2d;

  // MySQL syntax for changing just a comment:
  // ALTER TABLE `db3_pic2d` CHANGE `status` `status` TINYINT( 4 ) NOT NULL DEFAULT '0' 
  // COMMENT '0 = does not exist, 1 = OK, 2 = OK, but do not show, 3 = to be created/updated, 4 = to be deleted, 5 = in progress' 

  // finished.....
  if ($use_cmmmsrv == 'y') {
    socket_write($socket,'#### bye');
    socket_close($socket);
  }

  // for now, disable use of memory-based tables
  set_memstatus_dirty($db_id);

  if ($debug > 0) { debug_output("Finished with $errorcount errors.<br />\n"); }

  if ($err == 0) {
    
    // JME needs MDL molfiles with the "|" character instead of linebreaks
    $jmehitmol = strtr($safemol,"\n","|");
        
    echo "<applet code=\"JME.class\" archive=\"../JME.jar\" \n";
    echo "width=\"250\" height=\"120\">";
    echo "<param name=\"options\" value=\"depict\"> \n";
    echo "<param name=\"mol\" value=\"$jmehitmol\">\n";
    echo "</applet>\n";

    echo "<p />\n";
    mk_dataeditform($next_mol_id,1);
  } else {
    echo "something went wrong....<br />\n";
    echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
  }
  echo "</body></html>\n";
  die();
}  // action=addstruc3

if (($action == "addstruc3") && ($dbtype == 2)) {    // reaction input
  $errorcount = 0;
  $proposed_rxn_id = $_POST['nextid'];

  if (strpos($mol,"\$RXN") === FALSE) {
    echo "this is not a valid reaction file!\n";
    echo "</body></html>\n";
    die();
  }
  $errorcount = 0;
  $proposed_rxn_id = $_POST['nextid'];
  if ($use_cmmmsrv == 'y') {
    /* create a TCP/IP socket */
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket < 0) {
      //echo "socket_create() failed.\nreason: " . socket_strerror ($socket) . "\n";
      echo "<!-- could not connect to cmmmsrv - reverting to checkmol/matchmol --!>\n";
      $use_cmmmsrv = "n";
    }
    $sockresult = socket_connect ($socket, $cmmmsrv_addr, $cmmmsrv_port);
    if ($sockresult < 0) {
      //echo "socket_connect() failed.\nreason: ($sockresult) " . socket_strerror($sockresult) . "\n";
      echo "<!-- could not connect to cmmmsrv - reverting to checkmol/matchmol --!>\n";
      $use_cmmmsrv = "n";
    }
  }
  if ($use_cmmmsrv == 'y') {
    $a = socket_read($socket, 250, PHP_NORMAL_READ);
    //echo "$a\n";
    $pos = strpos($a,"READY");
    if ($pos === false) {
      echo "<!-- could not connect to cmmmsrv - reverting to checkmol/matchmol --!>\n";
      $use_cmmmsrv = "n";
    }
    $pos = 0;
  }

  // remove CR if present (IE, Mozilla et al.) and add it again (for Opera)
  $mol = str_replace("\r\n","\n",$mol);
  $mol = str_replace("\n","\r\n",$mol);
  //$saferxn = escapeshellcmd($mol);
  $saferxn = str_replace(";"," ",$mol);
  $saferxn = str_replace("\""," ",$saferxn);
  $saferxn = str_replace("\'"," ",$saferxn);
  $saferxn = str_replace("\´"," ",$saferxn);
  $saferxn = str_replace("\`"," ",$saferxn);
  $saferxn = str_replace("\|"," ",$saferxn);
  $rxndescr = analyze_rxnfile($saferxn);
  $nrmol = get_nrmol($rxndescr);
  $npmol = get_npmol($rxndescr);
  //echo "there are $nrmol reactants and $npmol products<br>\n";
  if (($nrmol == 0) || ($npmol == 0)) {
    echo "incomplete reaction file!\n";
    echo "</body></html>\n";
    die();
  }
  $allmol = array();
  $rmol = array();
  $pmol = array();
  $label_list = array();
  $map_list = array();
  $n_labels = 0;
  $n_maps = 0;
  $mapstr = "";
  
  echo "<pre>\n";
  $allmol = explode("\$MOL\r\n",$saferxn);
  $header = $allmol[0];

  // get the fingerprint dictionary
  $fpdefqstr = "SELECT fp_id, fpdef FROM $fpdeftable;";
  $fpdefresult = mysql_query($fpdefqstr)
      or die("Could not get fingerprint definition!"); 
  $i = -1;
  $n_dict = 0;
  $fpdef = array();
  while ($fpdefline = mysql_fetch_array($fpdefresult, MYSQL_ASSOC)) {
    $i++;
    $n_dict++;
    $fpdef[$i] = $fpdefline["fpdef"];
  } 
  mysql_free_result($fpdefresult);

  // get next available rxn_id 
  $result = mysql_query("SELECT COUNT(rxn_id) AS molcount FROM $rxnstructable")
    or die("Query failed! (1c3a)");
  $line = mysql_fetch_row($result);
  mysql_free_result($result);
  $rxncount = $line[0];
  if ($rxncount == 0) { 
    $next_rxn_id = 1; 
  } else {
    $result = mysql_query("SELECT MAX(rxn_id) AS molcount FROM $rxnstructable")
      or die("Query failed! (1c4a)");
    $line = mysql_fetch_row($result);
    mysql_free_result($result);
    $rxncount = $line[0];
    $next_rxn_id = $rxncount + 1;
  }
  if ($proposed_rxn_id != $next_rxn_id) {
    echo "This entry exists already!<br />\n";
    echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
    echo "</body></html>\n";
    die();
  }

  $mapstr = get_maps($saferxn);

  if ($nrmol > 0) {
    $moldfpsum = "";
    $molhfpsum = "";
    $molfgbsum = "";
    for ($i = 0; $i < $nrmol; $i++) {
      $rmol[$i] = $allmol[($i+1)];
      $mnum = $i + 1;
      //echo "processing reactant no. $mnum ...";
      $labels = get_atomlabels($rmol[$i]);
      $mid = "r" . $mnum;
      if (strlen($labels) > 0) {
        add_labels($mid,$labels);
      }
      $safemol = $rmol[$i];
      // now tweak each molecule
      if ($tweakmolfiles == "y") {
        if ($use_cmmmsrv == 'y') {
          $safemol = filterthroughcmmm($safemol,"#### checkmol:m");
        } else {
          if ($ostype == 1) { $safemol = filterthroughcmd($safemol,"$CHECKMOL -m - "); }
      	  if ($ostype == 2) { 
      	    $safemol = filterthroughcmd2($safemol,"$CHECKMOL -m - "); 
      	    $safemol = str_replace("\n","\r\n",$safemol);
          }
        }
        $rmol[$i] = $safemol;
      }  // end tweakmolefiles = y
      
      //create the dictionary-based fingerprints
      $moldfp = "";
      for ($k = 0; $k < $n_dict; $k++) {
        $dict = $fpdef[$k];
        if ($ostype == 1) { $cand = $safemol . "\n" . '$$$$' ."\n" . $dict; }
    	if ($ostype == 2) {
    	  $dict = str_replace("\r","",$dict);
    	  $dict = str_replace("\n","\r\n",$dict);
    	  $cand = $safemol . "\r\n" . '$$$$' ."\r\n" . $dict;
    	}
        if ($use_cmmmsrv == 'y') {
          $dfpstr = filterthroughcmmm($cand,"#### MATCHMOL:F");
        } else {
          $cand   = str_replace("\$","\\\$",$cand);
          if ($ostype == 1) { $dfpstr = filterthroughcmd($cand,"$MATCHMOL -F - "); }
    	  if ($ostype == 2) { $dfpstr = filterthroughcmd2($cand,"$MATCHMOL -F - "); }
        }
        $dfpstr = trim($dfpstr);
        if ($k > 0) { $moldfp .= ","; }
        $moldfp .= $dfpstr;
        //echo "dictionary-based fingerprints for reactant $i + dictionary $k\n$dfpstr\n";
      }  // for..
      //echo "moldfp: $moldfp\n";
      $moldfpsum = add_molfp($moldfpsum,$moldfp);
      
      // create the hash-based fingerprints
      if ($use_cmmmsrv == 'y') {
        $chkresult = filterthroughcmmm($safemol,"#### checkmol:bH");
      } else {
        if ($ostype == 1) {$chkresult = filterthroughcmd($safemol,"$CHECKMOL -bH - "); }
    	if ($ostype == 2) {$chkresult = filterthroughcmd2($safemol,"$CHECKMOL -bH - "); }
      }

      if (strlen($chkresult) < 2) {
        echo "no response from checkmol (maybe a server configuration problem?)\n</body></html>\n";
        exit;
      }

      $cr      = explode("\n", $chkresult);
      $molfgb  = trim($cr[0]);
      $fgbarr = explode(";",$molfgb);   // cut off the n1bits value
      $molfgb = $fgbarr[0];
      $molhfp  = trim($cr[1]);
      $hfparr = explode(";",$molhfp);   // cut off the n1bits value
      $molhfp = $hfparr[0];
      //echo "molhfp: $molhfp\n";
      $molhfpsum = add_molfp($molhfpsum,$molhfp);
      $molfgbsum = add_molfp($molfgbsum,$molfgb);
      
    }  // end for ($i = 0; $i < $nrmol; $i++) ...
    //echo "added moldfp: $moldfpsum\n";
    //echo "added molhfp: $molhfpsum\n";
    
    // insert combined reaction fingerprints for reactant(s)
    $qstr = "INSERT INTO $rxncfptable VALUES ($next_rxn_id,'R',$moldfpsum,$molhfpsum,0)";
    //echo "adding combined fingerprints for no. $next_rxn_id to table $rxncfptable.... ";
    $result = mysql_query($qstr);
    $err = 0;
    $err = mysql_errno();
    if ($err != 0) { 
      echo "<br />Action failed (#4a/$err: " . mysql_error() . ")<br />\n"; 
      $errorcount++;
    } // else { echo "done\n"; }

    // insert combined functional group bitstring for reactant(s)
    $qstr = "INSERT INTO $rxnfgbtable VALUES ($next_rxn_id,'R',$molfgbsum,0)";
    //echo "adding combined reactant functional group codes for no. $next_rxn_id to table $rxncfptable.... ";
    $result = mysql_query($qstr);
    $err = 0;
    $err = mysql_errno();
    if ($err != 0) { 
      echo "<br />Action failed (#4b/$err: " . mysql_error() . ")<br />\n"; 
      $errorcount++;
    } // else { echo "done\n"; }
  }

  if ($npmol > 0) {
    $moldfpsum = "";
    $molhfpsum = "";
    $molfgbsum = "";
    for ($i = 0; $i < $npmol; $i++) {
      $pmol[$i] = $allmol[($i+1+$nrmol)];
      $mnum = $i + 1;
      //echo "processing product no. $mnum ...";
      $labels = get_atomlabels($pmol[$i]);
      $mid = "p" . $mnum;
      if (strlen($labels) > 0) {
        add_labels($mid,$labels);
      }
      $safemol = $pmol[$i];
      // tweak the molfile
      if ($tweakmolfiles == "y") {
        if ($use_cmmmsrv == 'y') {
          $safemol = filterthroughcmmm($safemol,"#### checkmol:m");
        } else {
          if ($ostype == 1) { $safemol = filterthroughcmd($safemol,"$CHECKMOL -m - "); }
      	  if ($ostype == 2) { 
      	    $safemol = filterthroughcmd2($safemol,"$CHECKMOL -m - "); 
      	    $safemol = str_replace("\n","\r\n",$safemol);
          }
        }
        $pmol[$i] = $safemol;
      }  // end tweakmolefiles = y
      
      //create the dictionary-based fingerprints
      $moldfp = "";
      for ($k = 0; $k < $n_dict; $k++) {
        $dict = $fpdef[$k];
        if ($ostype == 1) { $cand = $safemol . "\n" . '$$$$' ."\n" . $dict; }
    	if ($ostype == 2) {
    	  $dict = str_replace("\r","",$dict);
    	  $dict = str_replace("\n","\r\n",$dict);
    	  $cand = $safemol . "\r\n" . '$$$$' ."\r\n" . $dict;
    	}
        if ($use_cmmmsrv == 'y') {
          $dfpstr = filterthroughcmmm($cand,"#### MATCHMOL:F");
        } else {
          $cand   = str_replace("\$","\\\$",$cand);
          if ($ostype == 1) { $dfpstr = filterthroughcmd($cand,"$MATCHMOL -F - "); }
    	  if ($ostype == 2) { $dfpstr = filterthroughcmd2($cand,"$MATCHMOL -F - "); }
        }
        $dfpstr = trim($dfpstr);
        if ($k > 0) { $moldfp .= ","; }
        $moldfp .= $dfpstr;
        //echo "dictionary-based fingerprints for product $i + dictionary $k\n$dfpstr\n";
      }  // for..
      //echo "moldfp: $moldfp\n";
      $moldfpsum = add_molfp($moldfpsum,$moldfp);
      
      // create the hash-based fingerprints
      if ($use_cmmmsrv == 'y') {
        $chkresult = filterthroughcmmm($safemol,"#### checkmol:bH");
      } else {
        if ($ostype == 1) {$chkresult = filterthroughcmd($safemol,"$CHECKMOL -bH - "); }
    	if ($ostype == 2) {$chkresult = filterthroughcmd2($safemol,"$CHECKMOL -bH - "); }
      }
      
      if (strlen($chkresult) < 2) {
        echo "no response from checkmol (maybe a server configuration problem?)\n</body></html>\n";
        exit;
      }
      
      $cr      = explode("\n", $chkresult);
      $molfgb  = trim($cr[0]);
      $fgbarr = explode(";",$molfgb);   // cut off the n1bits value
      $molfgb = $fgbarr[0];
      $molhfp  = trim($cr[1]);
      $hfparr = explode(";",$molhfp);   // cut off the n1bits value
      $molhfp = $hfparr[0];
      //echo "molhfp: $molhfp\n";
      $molhfpsum = add_molfp($molhfpsum,$molhfp);
      $molfgbsum = add_molfp($molfgbsum,$molfgb);
    }  // end for ($i = 0; $i < $npmol; $i++) ...
    //echo "added moldfp: $moldfpsum\n";
    //echo "added molhfp: $molhfpsum\n";
    
    // insert combined reaction fingerprints for product(s)
    $qstr = "INSERT INTO $rxncfptable VALUES ($next_rxn_id,'P',$moldfpsum,$molhfpsum,0)";
    //echo "adding combined fingerprints for no. $next_rxn_id to table $rxncfptable.... ";
    $result = mysql_query($qstr);
    $err = 0;
    $err = mysql_errno();
    if ($err != 0) { 
      echo "<br />Action failed (#4c/$err: " . mysql_error() . ")<br />\n"; 
      $errorcount++;
    } // else { echo "done"; }

    // insert combined functional group bitstring for product(s)
    $qstr = "INSERT INTO $rxnfgbtable VALUES ($next_rxn_id,'P',$molfgbsum,0)";
    //echo "adding combined product functional group codes for no. $next_rxn_id to table $rxncfptable.... ";
    $result = mysql_query($qstr);
    $err = 0;
    $err = mysql_errno();
    if ($err != 0) { 
      echo "<br />Action failed (#4d/$err: " . mysql_error() . ")<br />\n"; 
      $errorcount++;
    } // else { echo "done\n"; }
  }

  // re-apply the reaction maps after tweaking  (checkmol strips the atom labels)
  if ($tweakmolfiles == "y") {
    $saferxn = $header;
    for ($nm = 0; $nm < $nrmol; $nm++) {
      $saferxn .= "\$MOL\r\n" . $rmol[$nm];
    }
    for ($nm = 0; $nm < $npmol; $nm++) {
      $saferxn .= "\$MOL\r\n" . $pmol[$nm];
    }
    $saferxn = apply_maps($saferxn,$mapstr);
  }

  $qstr = "INSERT INTO $rxnstructable (`rxn_id`, `struc`, `map`) VALUES ($next_rxn_id, \"$saferxn\", \"$mapstr\" )";
  //echo "adding reaction as no. $next_rxn_id to data collection $db_id ...";
  //echo "<pre>$qstr</pre>\n";
  $result = mysql_query($qstr);
  $err = 0;
  $err = mysql_errno();
  if ($err != 0) { 
    echo "<br />Action failed (#1/$err: " . mysql_error() . ")<br />\n"; 
    $errorcount++;
  } // else { echo "done\n"; }

  // add new record to rxndatatable
  $qstr = "INSERT INTO $rxndatatable (`rxn_id`) VALUES (\"$next_rxn_id\")";
  //echo "adding reaction data for no. $next_rxn_id  ...";
  $result = mysql_query($qstr);
  $err = 0;
  $err = mysql_errno();
  if ($err != 0) { 
    echo "<br />Action failed (#6/$err: " . mysql_error() . ")<br />\n"; 
    $errorcount++;
  } // else { echo "done\n"; }
  echo "</pre>\n";

  if ($debug > 0) { debug_output("Action finished with $errorcount errors.<br />\n"); }

  if ($err == 0) {
    // JME needs MDL molfiles with the "|" character instead of linebreaks
    $jmehitmol = strtr($saferxn,"\n","|");
    echo "<applet code=\"JME.class\" archive=\"../JME.jar\" \n";
    echo "width=\"250\" height=\"120\">";
    echo "<param name=\"options\" value=\"depict\"> \n";
    echo "<param name=\"mol\" value=\"$jmehitmol\">\n";
    echo "</applet>\n";
    echo "<p />\n";
    mk_dataeditform($next_rxn_id,2);
  } else {
    echo "something went wrong....<br />\n";
    echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
  }
  echo "</body></html>\n";
  die();
}

if ($action == "savedata") {
  global $db_id;
  global $datatype;
  global $moldatatable; 
  global $rxndatatable; 
  $item_id = $_POST["id"];
  if ($datatype == 2) {
    $idname = "rxn_id";
    $datatable = $rxndatatable;
  } else {
    $idname = "mol_id";
    $datatable = $moldatatable;
  }
  echo "<h3>Saving data for entry no. $item_id in data collection $db_id</h3>\n";
  echo "<table bgcolor=\"#eeeeee\">\n";
  echo "<tr align=\"left\"><th>Field:</th><th>Value:</th></tr>\n";
  $i = 0;
  $qstr = "SHOW FULL COLUMNS FROM $datatable";
  $result = mysql_query($qstr)
    or die("Query failed! (savedata)");
  $updstr = "UPDATE $datatable SET ";
  while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $field   = $line["Field"];
    $type    = $line["Type"];
    $comment = $line["Comment"];
    if ($field != $idname) {
      $i++;
      $newval = "";   //preliminary....
      $f = "f" . $i;
      $newval = $_POST[$f];
      echo "<tr><td>$field</td><td>$newval</td></tr>\n";
      if ($i > 1) { $updstr .= ","; }
      $updstr .= " $field = \"$newval\"";
    }  // if...
  }  // while
  $updstr .= " WHERE $idname = $item_id";
  echo "</table>\n";
  $result = mysql_query($updstr)
    or die("Save data failed!");
  echo "<h3>Done.</h3>\n";
  echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
  echo "</body></html>\n";
  die();
}  // action = savedata


if ($action == "duplcopy") {
  $item_id = $_REQUEST["id"];
  if ($datatype == 2) {
    $idname = "rxn_id";
    $datatable = $rxndatatable;
    $structable = $rxnstructable;
    $dtstr = "RD";
    $what = "reaction";
  } else {
    $idname = "mol_id";
    $datatable = $moldatatable;
    $structable = $molstructable;
    $dtstr = "SD";
    $what = "structure";
  }

  // sanity checks...
  if (!is_numeric($item_id)) {
    echo "invalid input!</body></html>";
  } else { 
    $qstr = "SELECT COUNT($idname) AS itemcount FROM $structable WHERE $idname = $item_id";
    $result = mysql_query($qstr) or die("Query failed! (struc 1)");    
    while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
      $itemcount = $line["itemcount"];
    }
    mysql_free_result($result);
    if ($itemcount < 1) {
      echo "<h2>Entry no. $item_id in data collection $db_id ($dtstr)</h2>\n<hr />\n";
      echo "This entry does not exist!<br />\n";
      echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
      echo "</body></html>\n";
      die();
    }
    echo "<h2>Entry no. $item_id in data collection $db_id ($dtstr)</h2>\n<hr />\n";

    $molstruc = "";   // can be MOL or RXN
    $qstr = "SELECT struc FROM $structable WHERE $idname = $item_id";
    $result = mysql_query($qstr) or die("Query failed! (struc 2)");    
    while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
      $molstruc = $line["struc"];
    }
    mysql_free_result($result);

    if ($molstruc == "") {
      echo "empty structure<br />\n";
    } else {
      // JME needs MDL molfiles with the "|" character instead of linebreaks
      $jmehitmol = strtr($molstruc,"\n","|");
      if ($datatype == 1) { 
        $what1 = "Structure:";
        $what2 = "structure";
        $jmewidth = 250;
      } elseif ($datatype == 2) { 
        $what1 = "Reaction:";
        $what2 = "reaction";
        $jmewidth = 450;
      }
      echo "<h3>$what1</h3>\n";
      echo "<applet code=\"JME.class\" archive=\"../JME.jar\" \n";
      echo "width=\"$jmewidth\" height=\"120\">";
      echo "<param name=\"options\" value=\"depict\"> \n";
      echo "<param name=\"mol\" value=\"$jmehitmol\">\n";
      echo "</applet>\n";
    }
    echo "<p />\n";
    
   // show data fields
   
    $result4 = mysql_query("SELECT * FROM $datatable WHERE $idname = $item_id")
      or die("Query failed! (duplcopy)");
    $y = mysql_num_fields($result4);
    echo "<table bgcolor=\"#eeeeee\">\n";
    while ($line4 = mysql_fetch_array($result4, MYSQL_BOTH)) {
      for ($x = 0; $x < $y; $x++) {
        $fieldname = mysql_field_name($result4, $x);
        //$fieldtype = mysql_field_type($result4, $x);
        if (($fieldname != $idname) && ($line4[$fieldname] != "")) {
          //echo  "<b>$fieldname:</b> \t$line4[$fieldname] <br />\n";
          echo "<tr>\n";
          echo "  <td><b>$fieldname</b></td><td>$line4[$fieldname]</td>\n";
          echo "</tr>\n";
        }
      }
      echo "<br />\n";
    }
    echo "</table>\n";
    echo "<p />\n<hr />\n";

    // and now the buttons for duplicate and copy
    echo "<form name=\"edform1\" action=\"$myname\" method=post>\n";
    echo "<input type=\"hidden\" name=\"action\" value=\"duplicate\">\n";
    echo "<input type=\"hidden\" name=\"db\" value=\"$db_id\">\n";
    echo "<input type=\"hidden\" name=\"datatype\" value=\"$datatype\">\n";
    echo "<input type=\"hidden\" name=\"id\" value=\"$item_id\">\n";
    echo "<input type=\"Submit\" name=\"select\" value=\"Duplicate\"> this $what + all data fields in current data collection ($db_id)\n";
    echo "</form><p />\n";

    // now, get number of available data collections
    $db_list = array();
    $n_db = 0;
    $qstr5 = "SELECT db_id FROM $metatable WHERE (type = $datatype) AND (db_id != $db_id) ORDER BY db_id";
    $result5 = mysql_query($qstr5)
      or die("Query failed! (duplcopy 2)");
    while ($line5 = mysql_fetch_array($result5, MYSQL_BOTH)) {
      $db_list[$n_db] = $line5["db_id"];
      $n_db++;
    }
    if ($n_db > 0) {
      echo "<form name=\"edform1\" action=\"$myname\" method=post>\n";
      echo "<input type=\"hidden\" name=\"action\" value=\"copy\">\n";
      echo "<input type=\"hidden\" name=\"sourcedb\" value=\"$db_id\">\n";
      echo "<input type=\"hidden\" name=\"datatype\" value=\"$datatype\">\n";
      echo "<input type=\"hidden\" name=\"id\" value=\"$item_id\">\n";
      echo "<input type=\"Submit\" name=\"select\" value=\"&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Copy&nbsp;&nbsp;&nbsp;\"> this $what + name to data collection ";
  
      echo "<select size=\"1\" name=\"db\">\n";
      for ($i = 0; $i < $n_db; $i++) {
        $db   = $db_list[$i];
        echo "<option value=\"$db\"";
        //if ($db == $db_id) { echo " selected"; }
        echo ">$db</option>\n";
      }
      echo "</select>\n";
      echo "</form><p />\n";
    } else { echo "no other data collections available for copying..."; }


  }  // end   if is_numeric(item_id)
  echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
  echo "</body></html>\n";
  die();
}


if (($action == "editdata") || ($action == "duplicate") || ($action == "copy")) {
  $item_id = $_REQUEST["id"];
  $sourcedb_id = $db_id;
  $targetdb_id = $db_id;
  if ($datatype == 2) {
    $idname = "rxn_id";
    $datatable = $rxndatatable;
    $datasuffix = $rxndatasuffix;
    $structable = $rxnstructable;
    $strucsuffix = $rxnstrucsuffix;
    $dtstr = "RD";
  } else {
    $idname = "mol_id";
    $datatable = $moldatatable;
    $datasuffix = $moldatasuffix;
    $structable = $molstructable;
    $strucsuffix = $molstrucsuffix;
    $dtstr = "SD";
  }

  // sanity checks...
  if (!is_numeric($item_id)) {
    echo "invalid input!</body></html>";
  } else { 

    if ($action == "copy") {
      $sourcedb_id = $_REQUEST['sourcedb'];
      $db_id = $sourcedb_id;
      //echo "db_id: $db_id<br>sourcedb_id: $sourcedb_id<br>targetdb_id: $targetdb_id<br>";
    }

    $structable = $prefix . "db" . $db_id . "_" . $strucsuffix;
    $qstr = "SELECT COUNT($idname) AS itemcount FROM $structable WHERE $idname = $item_id";
    $result = mysql_query($qstr) or die("Query failed! (struc 1)");    
    while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
      $itemcount = $line["itemcount"];
    }
    mysql_free_result($result);
    if ($itemcount < 1) {
      echo "<h2>Entry no. $item_id in data collection $db_id ($dtstr)</h2>\n<hr />\n";
      echo "This entry does not exist!<br />\n";
      $db_id = $targetdb_id;
      echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
      echo "</body></html>\n";
      die();
    }
  
    if ($action == "duplicate") {
      if ($datatype == 1) {
        $new_item_id = copy_mol($db_id,$item_id,$db_id);
        $item_id = $new_item_id;
      } elseif ($datatype == 2) {
        $new_item_id = copy_rxn($db_id,$item_id,$db_id);
        $item_id = $new_item_id;
      }
    }
    if ($action == "copy") {
      if ($datatype == 1) {
        $new_item_id = copy_mol($db_id,$item_id,$targetdb_id);
        if ($new_item_id > 0) {
          $item_id = $new_item_id;
          $db_id = $targetdb_id;
        }
      } elseif ($datatype == 2) {
        $new_item_id = copy_rxn($db_id,$item_id,$targetdb_id);
        if ($new_item_id > 0) {
          $item_id = $new_item_id;
          $db_id = $targetdb_id;
        }
      }
      $structable = $prefix . "db" . $db_id . "_" . $strucsuffix;
    }

    echo "<h2>Entry no. $item_id in data collection $db_id ($dtstr)</h2>\n<hr />\n";

    $molstruc = "";   // can be MOL or RXN
    $qstr = "SELECT struc FROM $structable WHERE $idname = $item_id";
    $result = mysql_query($qstr) or die("Query failed! (struc 2)");    
    while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
      $molstruc = $line["struc"];
    }
    mysql_free_result($result);

    if ($molstruc == "") {
      echo "empty structure<br />\n";
    } else {
      // JME needs MDL molfiles with the "|" character instead of linebreaks
      $jmehitmol = strtr($molstruc,"\n","|");
      if ($datatype == 1) { 
        $what1 = "Structure:";
        $what2 = "structure";
        $jmewidth = 250;
      } elseif ($datatype == 2) { 
        $what1 = "Reaction:";
        $what2 = "reaction";
        $jmewidth = 450;
      }
      echo "<h3>$what1</h3>\n";
      echo "<applet code=\"JME.class\" archive=\"../JME.jar\" \n";
      echo "width=\"$jmewidth\" height=\"120\">";
      echo "<param name=\"options\" value=\"depict\"> \n";
      echo "<param name=\"mol\" value=\"$jmehitmol\">\n";
      echo "</applet>\n";
    }
    
    echo "<form name=\"strucform\" action=\"$myname\" method=\"post\">\n";
    echo "<input type=\"hidden\" name=\"action\" value=\"editstruc\">\n";
    echo "<input type=\"hidden\" name=\"db\" value=\"$db_id\">\n";
    echo "<input type=\"hidden\" name=\"datatype\" value=\"$datatype\">\n";
    echo "<input type=\"hidden\" name=\"id\" value=\"$item_id\">\n";
    echo "<input type=\"Submit\" name=\"select\" value=\"&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Edit $what2&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\">\n";
    echo "</form>";
    if ($datatype == 2) {
      echo "<form name=\"strucform\" action=\"$myname\" method=\"post\">\n";
      echo "<input type=\"hidden\" name=\"action\" value=\"extract\">\n";
      echo "<input type=\"hidden\" name=\"db\" value=\"$db_id\">\n";
      echo "<input type=\"hidden\" name=\"datatype\" value=\"$datatype\">\n";
      echo "<input type=\"hidden\" name=\"id\" value=\"$item_id\">\n";
      echo "<input type=\"Submit\" name=\"select\" value=\"Extract molecules\">\n";
      echo "</form>";
    }    
    
    if ($datatype == 1) {
      echo "&nbsp;&nbsp;<small><a href=\"../showmol.php?mol=${item_id}&db=${db_id}&mode=txt\"";
      echo " target=\"blank\">display molfile</a></small><p />\n";
    } elseif ($datatype == 2) {
      echo "&nbsp;&nbsp;<small><a href=\"../showmol.php?rxn=${item_id}&db=${db_id}&mode=txt\"";
      echo " target=\"blank\">display rxnfile</a></small><p />\n";
    }

    echo "<p />\n<hr />\n";
    mk_dataeditform($item_id,$datatype);

  }
  echo "<p /><a href=\"$myname?db=$sourcedb_id\">Continue without saving</a>\n";
  echo "</body></html>\n";
  die();
}


if (($action == "extract") && ($datatype == 2)) {
  $item_id = $_REQUEST["id"];
  $idname = "rxn_id";
  $datatable = $rxndatatable;
  $structable = $rxnstructable;
  $dtstr = "RD";

  // sanity checks...
  if (!is_numeric($item_id)) {
    echo "invalid input!</body></html>";
  } else { 

    $qstr = "SELECT COUNT($idname) AS itemcount FROM $structable WHERE $idname = $item_id";
    $result = mysql_query($qstr) or die("Query failed! (extract 1)");    
    while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
      $itemcount = $line["itemcount"];
    }
    mysql_free_result($result);
    if ($itemcount < 1) {
      echo "<h2>Entry no. $item_id in data collection $db_id ($dtstr)</h2>\n<hr />\n";
      echo "This entry does not exist!<br />\n";
      echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
      echo "</body></html>\n";
      die();
    }
  
    echo "<h2>Entry no. $item_id in data collection $db_id ($dtstr)</h2>\n<hr />\n";

    $molstruc = "";   // can be MOL or RXN
    $qstr = "SELECT struc FROM $structable WHERE $idname = $item_id";
    $result = mysql_query($qstr) or die("Query failed! (extract 2)");    
    while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
      $rxnstruc = $line["struc"];
    }
    mysql_free_result($result);

    if ($rxnstruc == "") {
      echo "empty reaction<br />\n";
    } else {
      // JME needs MDL molfiles with the "|" character instead of linebreaks
      $jmehitmol = strtr($rxnstruc,"\n","|");
      $what1 = "Reaction:";
      $what2 = "reaction";
      $jmewidth = 450;

      echo "<h3>$what1</h3>\n";
      echo "<applet code=\"JME.class\" archive=\"../JME.jar\" \n";
      echo "width=\"$jmewidth\" height=\"120\">";
      echo "<param name=\"options\" value=\"depict\"> \n";
      echo "<param name=\"mol\" value=\"$jmehitmol\">\n";
      echo "</applet>\n";
    }

    echo "<hr />\n";

    // get a list of all structure databases
    $db_list = array();
    $result1 = mysql_query("SELECT db_id FROM $metatable WHERE (type = 1) ORDER BY db_id")
      or die("Query failed! (extract 3)");
    $ndb = mysql_num_rows($result1);
    $i = 0;
    while ($line = mysql_fetch_array($result1, MYSQL_ASSOC)) {
      $db   = $line["db_id"];
      $db_list[$i] = $db;
      $i++;
    }
    mysql_free_result($result1);
    
    // now split the rxnfile into pieces
    $rxnstruc = str_replace("\n","\r\n",$rxnstruc);
    $rxnstruc = str_replace("\r\r\n","\r\n",$rxnstruc);
    $rxnstruc = strip_labels($rxnstruc);
    $rxndescr = analyze_rxnfile($rxnstruc);
    $nrmol = get_nrmol($rxndescr);
    $npmol = get_npmol($rxndescr);
    $allmol = array();
    $allmol = explode("\$MOL\r\n",$rxnstruc);
    $header = $allmol[0];
    if ($nrmol > 0) {
      echo "<h3>Reactants:</h3>\n";
      for ($i = 0; $i < $nrmol; $i++) {
        $rmol[$i] = $allmol[($i+1)];
        $mnum = $i + 1;
        echo "  reactant no. $mnum<br>\n";
        $jmehitmol = strtr($rmol[$i],"\n","|");
        
        echo "<table cellpadding=\"2\" cellspacing=\"2\" border=\"0\" width=\"100%\">\n";
        echo "<tr align=\"left\">\n";
        echo "<td width=\"20%\">\n";
        
        echo "<applet code=\"JME.class\" archive=\"../JME.jar\" \n";
        echo "width=\"250\" height=\"120\">";
        echo "<param name=\"options\" value=\"depict\"> \n";
        echo "<param name=\"mol\" value=\"$jmehitmol\">\n";
        echo "</applet>\n";
        
        echo "</td>\n<td align=\"left\">\n";
        echo "<br />\n";

        if ($ndb > 0) {
          echo "<form name=\"searchform\" action=\"../moldbsss.php\" method=\"post\">\n";
          echo "<input type=\"hidden\" name=\"mode\" value=\"1\">\n";
          echo "<input type=\"hidden\" name=\"mol\" value=\"$rmol[$i]\">\n";
          //echo "<input type=\"hidden\" name=\"db\" value=\"2\">\n";
          echo "<input type=\"Submit\" name=\"select\" value=\"Search\"> in data collection ";
          echo "<select size=\"1\" name=\"db\">\n";
          $j = 0;
          foreach ($db_list as $db) {
            echo "<option value=\"$db\"";
            if ($j == 0) { echo " selected"; }
            echo "> $db </option>\n";
            $j++;
          }
          echo "</select>\n";
          echo "</form>\n";
          //echo "<pre>$rmol[$i]</pre>";
          echo "<form name=\"copyform\" action=\"$myname\" method=\"post\">\n";
          echo "<input type=\"hidden\" name=\"action\" value=\"addstruc2\">\n";
          echo "<input type=\"hidden\" name=\"mol\" value=\"$rmol[$i]\">\n";
          //echo "<input type=\"hidden\" name=\"db\" value=\"2\">\n";
          echo "<input type=\"Submit\" name=\"select\" value=\"&nbsp;&nbsp;Copy&nbsp;&nbsp;\"> to data collection ";
          echo "<select size=\"1\" name=\"db\">\n";
          $i = 0;
          foreach ($db_list as $db) {
            echo "<option value=\"$db\"";
            if ($i == 0) { echo " selected"; }
            echo "> $db </option>\n";
            $i++;
          }
          echo "</select>\n";
          echo "</form><br /></td>";
        }  // end if ndb > 0
        echo "</tr>\n</table>\n";
      }
      echo "<hr />\n";
    }  // end if nrmol > 0
    
    if ($npmol > 0) {
      echo "<h3>Products:</h3>\n";
      for ($i = 0; $i < $npmol; $i++) {
        $pmol[$i] = $allmol[($i+1+$nrmol)];
        $mnum = $i + 1;
        echo "  product no. $mnum<br>\n";
        $jmehitmol = strtr($pmol[$i],"\n","|");
        
        echo "<table cellpadding=\"2\" cellspacing=\"2\" border=\"0\" width=\"100%\">\n";
        echo "<tr align=\"left\">\n";
        echo "<td width=\"20%\">\n";
        
        echo "<applet code=\"JME.class\" archive=\"../JME.jar\" \n";
        echo "width=\"250\" height=\"120\">";
        echo "<param name=\"options\" value=\"depict\"> \n";
        echo "<param name=\"mol\" value=\"$jmehitmol\">\n";
        echo "</applet>\n";
        
        echo "</td>\n<td align=\"left\">\n";
        echo "<br />\n";

        if ($ndb > 0) {
          echo "<form name=\"searchform\" action=\"../moldbsss.php\" method=\"post\">\n";
          echo "<input type=\"hidden\" name=\"mode\" value=\"1\">\n";
          echo "<input type=\"hidden\" name=\"mol\" value=\"$pmol[$i]\">\n";
          //echo "<input type=\"hidden\" name=\"db\" value=\"2\">\n";
          echo "<input type=\"Submit\" name=\"select\" value=\"Search\"> in data collection ";
          echo "<select size=\"1\" name=\"db\">\n";
          $j = 0;
          foreach ($db_list as $db) {
            echo "<option value=\"$db\"";
            if ($j == 0) { echo " selected"; }
            echo "> $db </option>\n";
            $j++;
          }
          echo "</select>\n";
          echo "</form>\n";
          echo "<form name=\"copyform\" action=\"$myname\" method=\"post\">\n";
          echo "<input type=\"hidden\" name=\"action\" value=\"addstruc2\">\n";
          echo "<input type=\"hidden\" name=\"mol\" value=\"$pmol[$i]\">\n";
          //echo "<input type=\"hidden\" name=\"db\" value=\"2\">\n";
          echo "<input type=\"Submit\" name=\"select\" value=\"&nbsp;&nbsp;Copy&nbsp;&nbsp;\"> to data collection ";
          echo "<select size=\"1\" name=\"db\">\n";
          $i = 0;
          foreach ($db_list as $db) {
            echo "<option value=\"$db\"";
            if ($i == 0) { echo " selected"; }
            echo "> $db </option>\n";
            $i++;
          }
          echo "</select>\n";
          echo "</form><br /></td>";
        }
        echo "</tr>\n</table>\n";
      }
      echo "<hr />\n";
    }  // end if npmol > 0
  }
  echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
  echo "</body></html>\n";
  die();
}


if ($action == "editstruc") {
  $item_id = $_POST["id"];
  if ($datatype == 1) {
    $idname = "mol_id";
    $structable = $molstructable;
    $jmewidth = 350;
    $jmeheigth = 288;
    $jmeopt = "xbutton, hydrogens, multipart";
    $btnlabel = "Save structure";
  } elseif ($datatype == 2) {
    $idname = "rxn_id";
    $structable = $rxnstructable;
    $jmewidth = 640;
    $jmeheigth = 480;
    $jmeopt = "xbutton, hydrogens, reaction, multipart";
    $btnlabel = "Save reaction";
  }
  
  echo "<h2>Entry no. $item_id in data collection $db_id</h2>\n";

  // sanity checks...
  if (!is_numeric($item_id)) {
    echo "invalid input!";
    echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
    echo "</body></html>\n";
    die();
  } else { 

    $qstr = "SELECT COUNT($idname) AS itemcount FROM $structable WHERE $idname = $item_id";
    $result = mysql_query($qstr) or die("Query failed! (struc 3)");    
    while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
      $itemcount = $line["itemcount"];
    }
    mysql_free_result($result);
    if ($itemcount < 1) {
      echo "This entry does not exist!<br />\n";
      echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
      echo "</body></html>\n";
      die();
    }

    $molstruc = "";
    $qstr = "SELECT struc FROM $structable WHERE $idname = $item_id";
    $result = mysql_query($qstr) or die("Query failed! (struc 4)");    
    while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
      $molstruc = $line["struc"];
    }
    mysql_free_result($result);

    // JME needs MDL molfiles with the "|" character instead of linebreaks
    $jmehitmol = strtr($molstruc,"\n","|");
    echo "<applet name =\"JME\" code=\"JME.class\" archive=\"../JME.jar\" \n";
    echo "width=\"$jmewidth\" height=\"$jmeheigth\">\n";
    echo "<param name=\"options\" value=\"$jmeopt\"> \n";
    if ($molstruc != "") {
      echo "<param name=\"mol\" value=\"$jmehitmol\">\n";
    }
    echo "</applet>\n";
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
      document.form.submit();
    }
  }
</script>
<p />
<form name="form" action="<?php echo $myname; ?>" method="post">
<input type="button" value="&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php echo "$btnlabel"; ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" onClick="check_ss()">
<input type="hidden" name="smiles">
<input type="hidden" name="jme">
<input type="hidden" name="mol">
<input type="hidden" name="action" value="savestruc">
<input type="hidden" name="db" value="<?php echo "$db_id";?>">
<input type="hidden" name="datatype" value="<?php echo "$datatype";?>">
<input type="hidden" name="id" value="<?php echo "$item_id";?>">
</form>

<form name="cancel" action="<?php echo "$myname"; ?>" method="post">
<input type="hidden" name="smiles">
<input type="hidden" name="jme">
<input type="hidden" name="mol">
<input type="hidden" name="action" value="editdata">
<input type="hidden" name="db" value="<?php echo "$db_id";?>">
<input type="hidden" name="datatype" value="<?php echo "$datatype";?>">
<input type="hidden" name="id" value="<?php echo "$item_id";?>">
<input type="Submit" name="select" value="&nbsp;&nbsp;&nbsp;&nbsp;Cancel&nbsp;&nbsp;&nbsp;&nbsp;">
</form>

<?php

    echo "<p />\n";
    echo "</body></html>\n";
    exit;
  }
}


if (($action == "savestruc") && ($datatype == 1)) {
  $errorcount = 0;
  $mol_id = $_POST['id'];
  if (!is_numeric($mol_id)) {
    echo "invalid input!";
    echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
    echo "</body></html>\n";
    die();
  }
  $qstr = "SELECT COUNT(mol_id) AS molcount FROM $molstructable WHERE mol_id = $mol_id";
  //echo "$qstr<br />\n";
  $result = mysql_query($qstr) or die("Query failed! (struc 5)");    
  while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $molcount = $line["molcount"];
  }
  mysql_free_result($result);
  if ($molcount < 1) {
    echo "This entry does not exist!<br />\n";
    echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
    echo "</body></html>\n";
    die();
  }
  
  if ($mol == "") {
    echo "empty structure, nothing saved!<br />\n";
    echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
    echo "</body></html>\n";
    die();    
  }

  if ($use_cmmmsrv == 'y') {
    /* create a TCP/IP socket */
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket < 0) {
      //echo "socket_create() failed.\nreason: " . socket_strerror ($socket) . "\n";
      echo "<!-- could not connect to cmmmsrv - reverting to checkmol/matchmol --!>\n";
      $use_cmmmsrv = "n";
    }
    $sockresult = socket_connect ($socket, $cmmmsrv_addr, $cmmmsrv_port);
    if ($sockresult < 0) {
      //echo "socket_connect() failed.\nreason: ($sockresult) " . socket_strerror($sockresult) . "\n";
      echo "<!-- could not connect to cmmmsrv - reverting to checkmol/matchmol --!>\n";
      $use_cmmmsrv = "n";
    }
  }
  if ($use_cmmmsrv == 'y') {
    $a = socket_read($socket, 250, PHP_NORMAL_READ);
    //echo "$a\n";
    $pos = strpos($a,"READY");
    if ($pos === false) {
      echo "<!-- could not connect to cmmmsrv - reverting to checkmol/matchmol --!>\n";
      $use_cmmmsrv = "n";
    }
    $pos = 0;
  }

  $safemol = str_replace(";"," ",$mol);
  $safemol = str_replace("\""," ",$safemol);
  $safemol = str_replace("\'"," ",$safemol);
  $safemol = str_replace("\´"," ",$safemol);
  $safemol = str_replace("\`"," ",$safemol);
  $safemol = str_replace("\|"," ",$safemol);
  
  // first, tweak the molfile
  if ($use_cmmmsrv == 'y') {
    $safemol = filterthroughcmmm($safemol,"#### checkmol:m");
  } else {
    if ($ostype == 1) { $safemol = filterthroughcmd($safemol,"$CHECKMOL -m - "); }
	if ($ostype == 2) { 
	  $safemol = filterthroughcmd2($safemol,"$CHECKMOL -m - "); 
	  #$safemol = str_replace("\n","\r\n",$safemol);
	}
  }

  // next step: get the molecular statistics of the input structure
  // by piping it through the checkmol program, then do a search
  // in molstat and the fingerprint table(s) ==> this gives a list of candidates

  // update structure in molstruc
  $qstr = "UPDATE $molstructable SET struc =\"$safemol\" WHERE mol_id = $mol_id";
  echo "<h2>Entry no. $mol_id in data collection $db_id</h2><hr />\n";
  
  //echo "<small>updating structure no. $mol_id in data collection $db_id ";
  $result = mysql_query($qstr);
  $err = 0;
  $err = mysql_errno();
  if ($err != 0) { 
    echo "<br />Action failed (#7/$err: " . mysql_error() . ")<br />\n"; 
    $errorcount++;
  } #else { echo "."; }

  if ($use_cmmmsrv == 'y') {
    $chkresult = filterthroughcmmm("$safemol", "#### checkmol:aXbH"); 
  } else {
    if ($ostype == 1) { $chkresult = filterthroughcmd("$safemol", "$CHECKMOL -aXbH - "); }
      if ($ostype == 2) { 
        $safemol = str_replace("\n","\r\n",$safemol);
        $chkresult = filterthroughcmd2("$safemol", "$CHECKMOL -aXbH - "); 
      }
  }
  //echo "<pre>$chkresult</pre>\n";
  
  if (strlen($chkresult) < 2) {
    echo "no response from checkmol (maybe a server configuration problem?)\n</body></html>\n";
    exit;
  }
  
  $cr = explode("\n", $chkresult);
  $molstat = trim($cr[0]);
  $molfgb  = trim($cr[1]);
  $molfgb  = str_replace(";",",",$molfgb);
  $molhfp  = trim($cr[2]);
  $molhfp  = str_replace(";",",",$molhfp);

  //echo "molstat ($next_mol_id): $molstat<br />\n";
  //echo "molfgb ($next_mol_id): $molfgb<br />\n";
  //echo "molhfp ($next_mol_id): $molhfp<br />\n";

  // delete old molstat record first
  $qstr = "DELETE FROM $molstattable WHERE mol_id = $mol_id";
  $result = mysql_query($qstr);
  $err = mysql_errno();
  if ($err != 0) { 
    echo "<br />Action failed (#8/$err: " . mysql_error() . ")<br />\n"; 
    $errorcount++;
  } #else { echo "."; }
  // add new molstat record
  $qstr = "INSERT INTO $molstattable VALUES ( $mol_id,$molstat )";
  $result = mysql_query($qstr);
  $err = mysql_errno();
  if ($err != 0) { 
    echo "<br />Action failed (#9/$err: " . mysql_error() . ")<br />\n"; 
    $errorcount++;
  } #else { echo "."; }

  // delete old molfgb record first
  $qstr = "DELETE FROM $molfgbtable WHERE mol_id = $mol_id";
  $result = mysql_query($qstr);
  $err = mysql_errno();
  if ($err != 0) { 
    echo "<br />Action failed (#10/$err: " . mysql_error() . ")<br />\n"; 
    $errorcount++;
  } #else { echo "."; }
  // add new molfgb record
  $qstr = "INSERT INTO $molfgbtable VALUES ($mol_id,$molfgb )";
  $result = mysql_query($qstr);
  $err = mysql_errno();
  if ($err != 0) { 
    echo "<br />Action failed (#11/$err: " . mysql_error() . ")<br />\n"; 
    $errorcount++;
  } #else { echo "."; }

  // delete old molcfp record first
  $qstr = "DELETE FROM $molcfptable WHERE mol_id = $mol_id";
  $result = mysql_query($qstr);
  $err = mysql_errno();
  if ($err != 0) { 
    echo "<br />Action failed (#12/$err: " . mysql_error() . ")<br />\n"; 
    $errorcount++;
  } #else { echo "."; }

  // add new molcfp record
  // get the fingerprint dictionary
  $fpdefqstr = "SELECT fp_id, fpdef FROM $fpdeftable;";
  $fpdefresult = mysql_query($fpdefqstr)
      or die("Could not get fingerprint definition!"); 
  $i = -1;
  $n_dict = 0;
  $fpdef = array();
  while ($fpdefline = mysql_fetch_array($fpdefresult, MYSQL_ASSOC)) {
    $i++;
    $n_dict++;
    $fpdef[$i] = $fpdefline["fpdef"];
  } 
  mysql_free_result($fpdefresult);

  //create the dictionary-based fingerprints
  $moldfp = "";
  for ($k = 0; $k < $n_dict; $k++) {
    $dict = $fpdef[$k];
    if ($ostype == 1) { $cand = $safemol . "\n" . '$$$$' ."\n" . $dict; }
	if ($ostype == 2) {
	  $dict = str_replace("\r","",$dict);
	  $dict = str_replace("\n","\r\n",$dict);
	  $cand = $safemol . "\r\n" . '$$$$' ."\r\n" . $dict;
	}
    if ($use_cmmmsrv == 'y') {
      $dfpstr = filterthroughcmmm($cand,"#### MATCHMOL:F");
    } else {
      $cand   = str_replace("\$","\\\$",$cand);
      if ($ostype == 1) { $dfpstr = filterthroughcmd($cand,"$MATCHMOL -F - "); }
	  if ($ostype == 2) { $dfpstr = filterthroughcmd2($cand,"$MATCHMOL -F - "); }
    }
    $dfpstr = trim($dfpstr);
    if ($k > 0) { $moldfp .= ","; }
    $moldfp .= " " . $dfpstr;
  }  // for..

  //now insert dictionary-based and hash-based fingerprints into molcfptable
  $qstr = "INSERT INTO $molcfptable VALUES ($mol_id, $moldfp, $molhfp )";
  //echo "adding combined fingerprints for no. $mol_id to table $molcfptable.... ";
  $result = mysql_query($qstr);
  $err = 0;
  $err = mysql_errno();
  if ($err != 0) { 
    echo "<br />Action failed (#13/$err: " . mysql_error() . ")<br />\n"; 
    $errorcount++;
  } #else { echo "."; }

  // update record in pic2dtable
  $qstr = "UPDATE $pic2dtable SET status = \"3\" WHERE mol_id = $mol_id"; // 0 = does not exist, 1 = OK, 2 = OK, but do not show, 3 = to be created/updated, 4 = to be deleted
  $result = mysql_query($qstr);
  $err = mysql_errno();
  if ($err != 0) { 
    echo "<br />Action failed (#14/$err: " . mysql_error() . ")<br />\n"; 
    $errorcount++;
  } #else { echo "."; }

  // finished.....

  if ($use_cmmmsrv == 'y') {
    socket_write($socket,'#### bye');
    socket_close($socket);
  }

  if ($debug > 0) { debug_output("Action finished with $errorcount errors.<br />\n"); }

  if ($err == 0) {
    echo "<h3>Structure:</h3>\n";
    // JME needs MDL molfiles with the "|" character instead of linebreaks
	$jmehitmol = strtr($safemol,"\n","|");
        
    echo "<applet code=\"JME.class\" archive=\"../JME.jar\" \n";
    echo "width=\"250\" height=\"120\">";
    echo "<param name=\"options\" value=\"depict\"> \n";
    echo "<param name=\"mol\" value=\"$jmehitmol\">\n";
    echo "</applet>\n";

    echo "<p />\n";
    echo "<form name=\"strucform\" action=\"$myname\" method=\"post\">\n";
    echo "<input type=\"hidden\" name=\"action\" value=\"editstruc\">\n";
    echo "<input type=\"hidden\" name=\"db\" value=\"$db_id\">\n";
    echo "<input type=\"hidden\" name=\"datatype\" value=\"1\">\n";
    echo "<input type=\"hidden\" name=\"id\" value=\"$mol_id\">\n";
    echo "<input type=\"Submit\" name=\"select\" value=\"Edit structure\">\n";
    echo "</form><p />\n";

    echo "<p />\n<hr />\n";
    mk_dataeditform($mol_id,1);
  } else {
    echo "something went wrong....<br />\n";
  }
  set_memstatus_dirty($db_id);
  echo "<p /><a href=\"$myname?db=$db_id\">Continue without saving</a>\n";
  echo "</body></html>\n";
  die();
}


if (($action == "savestruc") && ($datatype == 2)) {
  $errorcount = 0;
  $rxn_id = $_POST['id'];
  if (!is_numeric($rxn_id)) {
    echo "invalid input!";
    echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
    echo "</body></html>\n";
    die();
  }
  $qstr = "SELECT COUNT(rxn_id) AS rxncount FROM $rxnstructable WHERE rxn_id = $rxn_id";
  $result = mysql_query($qstr) or die("Query failed! (struc 6)");    
  while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $rxncount = $line["rxncount"];
  }
  mysql_free_result($result);
  if ($rxncount < 1) {
    echo "This entry does not exist!<br />\n";
    echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
    echo "</body></html>\n";
    die();
  }
  if ($mol == "") {
    echo "empty reaction, nothing saved!<br />\n";
    echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
    echo "</body></html>\n";
    die();    
  }
  if (strpos($mol,"\$RXN") === FALSE) {
    echo "this is not a valid reaction file!\n";
    echo "</body></html>\n";
    die();
  }

  if ($use_cmmmsrv == 'y') {
    /* create a TCP/IP socket */
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket < 0) {
      //echo "socket_create() failed.\nreason: " . socket_strerror ($socket) . "\n";
      echo "<!-- could not connect to cmmmsrv - reverting to checkmol/matchmol --!>\n";
      $use_cmmmsrv = "n";
    }
    $sockresult = socket_connect ($socket, $cmmmsrv_addr, $cmmmsrv_port);
    if ($sockresult < 0) {
      //echo "socket_connect() failed.\nreason: ($sockresult) " . socket_strerror($sockresult) . "\n";
      echo "<!-- could not connect to cmmmsrv - reverting to checkmol/matchmol --!>\n";
      $use_cmmmsrv = "n";
    }
  }
  if ($use_cmmmsrv == 'y') {
    $a = socket_read($socket, 250, PHP_NORMAL_READ);
    //echo "$a\n";
    $pos = strpos($a,"READY");
    if ($pos === false) {
      echo "<!-- could not connect to cmmmsrv - reverting to checkmol/matchmol --!>\n";
      $use_cmmmsrv = "n";
    }
    $pos = 0;
  }

  // remove CR if present (IE, Mozilla et al.) and add it again (for Opera)
  $mol = str_replace("\r\n","\n",$mol);
  $mol = str_replace("\n","\r\n",$mol);
  //$saferxn = escapeshellcmd($mol);
  $saferxn = str_replace(";"," ",$mol);
  $saferxn = str_replace("\""," ",$saferxn);
  $saferxn = str_replace("\'"," ",$saferxn);
  $saferxn = str_replace("\´"," ",$saferxn);
  $saferxn = str_replace("\`"," ",$saferxn);
  $saferxn = str_replace("\|"," ",$saferxn);
  $rxndescr = analyze_rxnfile($saferxn);
  $nrmol = get_nrmol($rxndescr);
  $npmol = get_npmol($rxndescr);
  //echo "there are $nrmol reactants and $npmol products<br>\n";
  if (($nrmol == 0) || ($npmol == 0)) {
    echo "incomplete reaction file!\n";
    echo "</body></html>\n";
    die();
  }
  $allmol = array();
  $rmol = array();
  $pmol = array();
  $label_list = array();
  $map_list = array();
  $n_labels = 0;
  $n_maps = 0;
  $mapstr = "";

  $allmol = explode("\$MOL\r\n",$saferxn);
  $header = $allmol[0];

  // get the fingerprint dictionary
  $fpdefqstr = "SELECT fp_id, fpdef FROM $fpdeftable;";
  $fpdefresult = mysql_query($fpdefqstr)
      or die("Could not get fingerprint definition!"); 
  $i = -1;
  $n_dict = 0;
  $fpdef = array();
  while ($fpdefline = mysql_fetch_array($fpdefresult, MYSQL_ASSOC)) {
    $i++;
    $n_dict++;
    $fpdef[$i] = $fpdefline["fpdef"];
  } 
  mysql_free_result($fpdefresult);

  $mapstr = get_maps($saferxn);
  
  // delete old rxnfgb record first
  $qstr = "DELETE FROM $rxnfgbtable WHERE rxn_id = $rxn_id";
  $result = mysql_query($qstr);
  $err = mysql_errno();
  if ($err != 0) { 
    echo "<br />Action failed (#11/$err: " . mysql_error() . ")<br />\n"; 
    $errorcount++;
  } #else { echo "."; }

  // delete old rxncfp record first
  $qstr = "DELETE FROM $rxncfptable WHERE rxn_id = $rxn_id";
  $result = mysql_query($qstr);
  $err = mysql_errno();
  if ($err != 0) { 
    echo "<br />Action failed (#12/$err: " . mysql_error() . ")<br />\n"; 
    $errorcount++;
  } #else { echo "done\n"; }

  if ($nrmol > 0) {
    $moldfpsum = "";
    $molhfpsum = "";
    $molfgbsum = "";
    for ($i = 0; $i < $nrmol; $i++) {
      $rmol[$i] = $allmol[($i+1)];
      $mnum = $i + 1;
      #echo "processing reactant no. $mnum ...";
      $labels = get_atomlabels($rmol[$i]);
      $mid = "r" . $mnum;
      if (strlen($labels) > 0) {
        add_labels($mid,$labels);
      }
      $safemol = $rmol[$i];
      // now tweak each molecule
      if ($tweakmolfiles == "y") {
        if ($use_cmmmsrv == 'y') {
          $safemol = filterthroughcmmm($safemol,"#### checkmol:m");
        } else {
          if ($ostype == 1) { $safemol = filterthroughcmd($safemol,"$CHECKMOL -m - "); }
      	  if ($ostype == 2) { 
      	    $safemol = filterthroughcmd2($safemol,"$CHECKMOL -m - "); 
      	    $safemol = str_replace("\n","\r\n",$safemol);
          }
        }
        $rmol[$i] = $safemol;
      }  // end tweakmolefiles = y
      
      //create the dictionary-based fingerprints
      $moldfp = "";
      for ($k = 0; $k < $n_dict; $k++) {
        $dict = $fpdef[$k];
        if ($ostype == 1) { $cand = $safemol . "\n" . '$$$$' ."\n" . $dict; }
    	if ($ostype == 2) {
    	  $dict = str_replace("\r","",$dict);
    	  $dict = str_replace("\n","\r\n",$dict);
    	  $cand = $safemol . "\r\n" . '$$$$' ."\r\n" . $dict;
    	}
        if ($use_cmmmsrv == 'y') {
          $dfpstr = filterthroughcmmm($cand,"#### MATCHMOL:F");
        } else {
          $cand   = str_replace("\$","\\\$",$cand);
          if ($ostype == 1) { $dfpstr = filterthroughcmd($cand,"$MATCHMOL -F - "); }
    	  if ($ostype == 2) { $dfpstr = filterthroughcmd2($cand,"$MATCHMOL -F - "); }
        }
        $dfpstr = trim($dfpstr);
        if ($k > 0) { $moldfp .= ","; }
        $moldfp .= $dfpstr;
        //echo "dictionary-based fingerprints for reactant $i + dictionary $k\n$dfpstr\n";
      }  // for..
      $moldfpsum = add_molfp($moldfpsum,$moldfp);
      
      // create the hash-based fingerprints
      if ($use_cmmmsrv == 'y') {
        $chkresult = filterthroughcmmm($safemol,"#### checkmol:bH");
      } else {
        if ($ostype == 1) {$chkresult = filterthroughcmd($safemol,"$CHECKMOL -bH - "); }
    	if ($ostype == 2) {$chkresult = filterthroughcmd2($safemol,"$CHECKMOL -bH - "); }
      }
      
      if (strlen($chkresult) < 2) {
        echo "no response from checkmol (maybe a server configuration problem?)\n</body></html>\n";
        exit;
      }
      
      $cr      = explode("\n", $chkresult);
      $molfgb  = trim($cr[0]);
      $fgbarr = explode(";",$molfgb);   // cut off the n1bits value
      $molfgb = $fgbarr[0];
      $molhfp  = trim($cr[1]);
      $hfparr = explode(";",$molhfp);   // cut off the n1bits value
      $molhfp = $hfparr[0];
      //echo "molhfp: $molhfp\n";
      $molhfpsum = add_molfp($molhfpsum,$molhfp);
      $molfgbsum = add_molfp($molfgbsum,$molfgb);
      
    }  // end for ($i = 0; $i < $nrmol; $i++) ...
    //echo "added moldfp: $moldfpsum\n";
    //echo "added molhfp: $molhfpsum\n";
    
    // insert combined reaction fingerprints for reactant(s)
    $qstr = "INSERT INTO $rxncfptable VALUES ($rxn_id,'R',$moldfpsum,$molhfpsum,0)";
    #echo "adding combined fingerprints (reactants) for no. $next_rxn_id to table $rxncfptable.... ";
    $result = mysql_query($qstr);
    $err = 0;
    $err = mysql_errno();
    if ($err != 0) { 
      echo "<br />Action failed (#4e/$err: " . mysql_error() . ")<br />\n"; 
      $errorcount++;
    } #else { echo "done\n"; }

    // insert combined functional group bitstring for reactant(s)
    $qstr = "INSERT INTO $rxnfgbtable VALUES ($rxn_id,'R',$molfgbsum,0)";
    #echo "adding combined reactant functional group codes for no. $next_rxn_id to table $rxncfptable.... ";
    $result = mysql_query($qstr);
    $err = 0;
    $err = mysql_errno();
    if ($err != 0) { 
      echo "<br />Action failed (#4f/$err: " . mysql_error() . ")<br />\n"; 
      $errorcount++;
    } #else { echo "done\n"; }
  }

  if ($npmol > 0) {
    $moldfpsum = "";
    $molhfpsum = "";
    $molfgbsum = "";
    for ($i = 0; $i < $npmol; $i++) {
      $pmol[$i] = $allmol[($i+1+$nrmol)];
      $mnum = $i + 1;
      #echo "processing product no. $mnum ...";
      $labels = get_atomlabels($pmol[$i]);
      $mid = "p" . $mnum;
      if (strlen($labels) > 0) {
        add_labels($mid,$labels);
      }
      $safemol = $pmol[$i];
      // tweak the molfile
      if ($tweakmolfiles == "y") {
        if ($use_cmmmsrv == 'y') {
          $safemol = filterthroughcmmm($safemol,"#### checkmol:m");
        } else {
          if ($ostype == 1) { $safemol = filterthroughcmd($safemol,"$CHECKMOL -m - "); }
      	  if ($ostype == 2) { 
      	    $safemol = filterthroughcmd2($safemol,"$CHECKMOL -m - "); 
      	    $safemol = str_replace("\n","\r\n",$safemol);
          }
        }
        $pmol[$i] = $safemol;
      }  // end tweakmolefiles = y
      
      //create the dictionary-based fingerprints
      $moldfp = "";
      for ($k = 0; $k < $n_dict; $k++) {
        $dict = $fpdef[$k];
        if ($ostype == 1) { $cand = $safemol . "\n" . '$$$$' ."\n" . $dict; }
    	if ($ostype == 2) {
    	  $dict = str_replace("\r","",$dict);
    	  $dict = str_replace("\n","\r\n",$dict);
    	  $cand = $safemol . "\r\n" . '$$$$' ."\r\n" . $dict;
    	}
        if ($use_cmmmsrv == 'y') {
          $dfpstr = filterthroughcmmm($cand,"#### MATCHMOL:F");
        } else {
          $cand   = str_replace("\$","\\\$",$cand);
          if ($ostype == 1) { $dfpstr = filterthroughcmd($cand,"$MATCHMOL -F - "); }
    	  if ($ostype == 2) { $dfpstr = filterthroughcmd2($cand,"$MATCHMOL -F - "); }
        }
        $dfpstr = trim($dfpstr);
        if ($k > 0) { $moldfp .= ","; }
        $moldfp .= $dfpstr;
        //echo "dictionary-based fingerprints for product $i + dictionary $k\n$dfpstr\n";
      }  // for..
      $moldfpsum = add_molfp($moldfpsum,$moldfp);
      
      // create the hash-based fingerprints
      if ($use_cmmmsrv == 'y') {
        $chkresult = filterthroughcmmm($safemol,"#### checkmol:bH");
      } else {
        if ($ostype == 1) {$chkresult = filterthroughcmd($safemol,"$CHECKMOL -bH - "); }
    	if ($ostype == 2) {$chkresult = filterthroughcmd2($safemol,"$CHECKMOL -bH - "); }
      }
      
      if (strlen($chkresult) < 2) {
        echo "no response from checkmol (maybe a server configuration problem?)\n</body></html>\n";
        exit;
      }
      
      $cr      = explode("\n", $chkresult);
      $molfgb  = trim($cr[0]);
      $fgbarr = explode(";",$molfgb);   // cut off the n1bits value
      $molfgb = $fgbarr[0];
      $molhfp  = trim($cr[1]);
      $hfparr = explode(";",$molhfp);   // cut off the n1bits value
      $molhfp = $hfparr[0];
      //echo "molhfp: $molhfp\n";
      $molhfpsum = add_molfp($molhfpsum,$molhfp);
      $molfgbsum = add_molfp($molfgbsum,$molfgb);

    }  // end for ($i = 0; $i < $npmol; $i++) ...
    //echo "added moldfp: $moldfpsum\n";
    //echo "added molhfp: $molhfpsum\n";
    
    // insert combined reaction fingerprints for product(s)
    $qstr = "INSERT INTO $rxncfptable VALUES ($rxn_id,'P',$moldfpsum,$molhfpsum,0)";
    //echo "adding combined fingerprints for no. $next_rxn_id to table $rxncfptable.... ";
    $result = mysql_query($qstr);
    $err = 0;
    $err = mysql_errno();
    if ($err != 0) { 
      echo "<br />Action failed (#4g/$err: " . mysql_error() . ")<br />\n"; 
      $errorcount++;
    } #else { echo "done"; }
    
    // insert combined functional group bitstring for product(s)
    $qstr = "INSERT INTO $rxnfgbtable VALUES ($rxn_id,'P',$molfgbsum,0)";
    //echo "adding combined product functional group codes for no. $next_rxn_id to table $rxncfptable.... ";
    $result = mysql_query($qstr);
    $err = 0;
    $err = mysql_errno();
    if ($err != 0) { 
      echo "<br />Action failed (#4h/$err: " . mysql_error() . ")<br />\n"; 
      $errorcount++;
    } #else { echo "done\n"; }
  }
  // re-apply the reaction maps after tweaking  (checkmol strips the atom labels)
  if ($tweakmolfiles == "y") {
    $saferxn = $header;
    for ($nm = 0; $nm < $nrmol; $nm++) {
      $saferxn .= "\$MOL\r\n" . $rmol[$nm];
    }
    for ($nm = 0; $nm < $npmol; $nm++) {
      $saferxn .= "\$MOL\r\n" . $pmol[$nm];
    }
    $saferxn = apply_maps($saferxn,$mapstr);
  }

  // update structure in rxnstruc
  $qstr = "UPDATE $rxnstructable SET struc =\"$saferxn\", map = \"$mapstr\"  WHERE rxn_id = $rxn_id";

  //echo "updating reaction no. $rxn_id in data collection $db_id ...";
  $result = mysql_query($qstr);
  $err = 0;
  $err = mysql_errno();
  if ($err != 0) { 
    echo "<br />Action failed (#1/$err: " . mysql_error() . ")<br />\n"; 
    $errorcount++;
  } #else { echo "done\n"; }

  // finished.....

  if ($use_cmmmsrv == 'y') {
    socket_write($socket,'#### bye');
    socket_close($socket);
  }

  if ($debug > 0) { debug_output("Action finished with $errorcount errors.<br />\n"); }

  if ($err == 0) {
    echo "<h3>Reaction:</h3>\n";
    // JME needs MDL molfiles with the "|" character instead of linebreaks
	$jmehitmol = strtr($saferxn,"\n","|");
        
    echo "<applet code=\"JME.class\" archive=\"../JME.jar\" \n";
    echo "width=\"350\" height=\"120\">";
    echo "<param name=\"options\" value=\"depict\"> \n";
    echo "<param name=\"mol\" value=\"$jmehitmol\">\n";
    echo "</applet>\n";

    echo "<p />\n";
    echo "<form name=\"strucform\" action=\"$myname\" method=\"post\">\n";
    echo "<input type=\"hidden\" name=\"action\" value=\"editstruc\">\n";
    echo "<input type=\"hidden\" name=\"db\" value=\"$db_id\">\n";
    echo "<input type=\"hidden\" name=\"datatype\" value=\"2\">\n";
    echo "<input type=\"hidden\" name=\"id\" value=\"$rxn_id\">\n";
    echo "<input type=\"Submit\" name=\"select\" value=\"Edit reaction\">\n";
    echo "</form><p />\n";

    echo "<p />\n<hr />\n";
    mk_dataeditform($rxn_id,2);
  } else {
    echo "something went wrong....<br />\n";
  }
  echo "<p /><a href=\"$myname?db=$db_id\">Continue without saving</a>\n";
  echo "</body></html>\n";
  die();

}  // end action = savestruc && datatype = 2


if ($action == "eraserecord") {
  if ($datatype == 1) {
    $idname = "mol_id";
    $structable = $molstructable;
    $datatable = $moldatatable;
    $jmewidth = 250;
  } elseif ($datatype == 2) {
    $idname = "rxn_id";
    $structable = $rxnstructable;
    $datatable = $rxndatatable;
    $jmewidth = 350;
  }
  $item_id = $_POST['id'];
  if (!is_numeric($item_id)) {
    echo "invalid input!";
    echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
    echo "</body></html>\n";
    die();
  }
  $qstr = "SELECT COUNT($idname) AS itemcount FROM $structable WHERE $idname = $item_id";
  $result = mysql_query($qstr) or die("Query failed! (struc 7)");    
  while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $itemcount = $line["itemcount"];
  }
  mysql_free_result($result);
  if ($itemcount < 1) {
    echo "This entry does not exist!<br />\n";
    echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
    echo "</body></html>\n";
    die();
  }
  $molstruc = "";
  $md5str = "";
  $qstr = "SELECT struc FROM $structable WHERE $idname = $item_id";
  $result = mysql_query($qstr) or die("Query failed! (struc 8)");    
  while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $molstruc = $line["struc"];
  }
  mysql_free_result($result);

  if ($molstruc == "") {
    echo "empty structure<br />\n";
  } else {
    $md5str = md5($molstruc);
  }  
  echo "<h3>Do you really want to erase entry no. $item_id from data collection $db_id?</h3>\n";
  // JME needs MDL molfiles with the "|" character instead of linebreaks
  $jmehitmol = strtr($molstruc,"\n","|");
      
  echo "<applet code=\"JME.class\" archive=\"../JME.jar\" \n";
  echo "width=\"$jmewidth\" height=\"120\">";
  echo "<param name=\"options\" value=\"depict\"> \n";
  echo "<param name=\"mol\" value=\"$jmehitmol\">\n";
  echo "</applet>\n";

  // show data fields
  echo "<table bgcolor=\"#eeeeee\">\n";
  echo "<tr align=\"left\"><th>Field:</th><th>Value:</th></tr>\n";
  $i = 0;
  $qstr = "SHOW FULL COLUMNS FROM $datatable";
  $result = mysql_query($qstr)
    or die("Query failed! (1c5)");
  while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $field   = $line["Field"];
    $type    = $line["Type"];
    $comment = $line["Comment"];
    if ($field != $idname) {
      $i++;
      $oldval = "";   //preliminary....
      $qstr2 = "SELECT $field FROM $datatable WHERE $idname = $item_id";
      $result2 = mysql_query($qstr2)
        or die("Query failed! (oldval)");
      $line2 = mysql_fetch_row($result2);
      mysql_free_result($result2);
      $oldval = $line2[0];
      echo "<tr><td>$field</td><td>$oldval</td></tr>\n"; 
    }  // if...

  }
  echo "</table><p />\n";
  
  // show yes/no buttons
  
  echo "<table>\n<tr>\n<td>\n";
  echo "<form name=\"erase\" action=\"$myname\" method=\"post\">\n";
  echo "<input type=\"hidden\" name=\"action\" value=\"eraseconfirm\">\n";
  echo "<input type=\"hidden\" name=\"db\" value=\"$db_id\">\n";
  echo "<input type=\"hidden\" name=\"id\" value=\"$item_id\">\n";
  echo "<input type=\"hidden\" name=\"token\" value=\"$md5str\">\n";
  echo "<input type=\"Submit\" name=\"select\" value=\"&nbsp;Yes, erase it!\">\n";
  echo "</form><p />\n";

  echo "</td>\n<td>\n";

  echo "<form name=\"goback\" action=\"$myname\" method=\"post\">\n";
  echo "<input type=\"hidden\" name=\"action\" value=\"\">\n";
  echo "<input type=\"hidden\" name=\"db\" value=\"$db_id\">\n";
  echo "<input type=\"Submit\" name=\"select\" value=\"&nbsp;No, keep it!\">\n";
  echo "</form><p />\n";
  echo "</td>\n</tr>\n</table>\n";

  echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
  echo "</body></html>\n";
  die();
}


if ($action == "eraseconfirm") {
  if (($access < 3) && ($trusted == false)) {
    echo "Your client IP is not authorized to perform the requested operation!<br />\n";
    //echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
    echo "</body></html>\n";
    die();
  }
  if ($datatype == 1) {
    $idname = "mol_id";
    $structable = $molstructable;
    $datatable = $moldatatable;
    $cfptable = $molcfptable;
    $fgbtable = $molfgbtable;
    $jmewidth = 250;
  } elseif ($datatype == 2) {
    $idname = "rxn_id";
    $structable = $rxnstructable;
    $datatable = $rxndatatable;
    $cfptable = $rxncfptable;
    $fgbtable = $rxnfgbtable;
    $jmewidth = 350;
  }
  $item_id = $_POST['id'];
  $md5str = $_POST['token'];
  if (!is_numeric($item_id)) {
    echo "invalid input!";
    echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
    echo "</body></html>\n";
    die();
  }
  $qstr = "SELECT COUNT($idname) AS itemcount FROM $structable WHERE $idname = $item_id";
  $result = mysql_query($qstr) or die("Query failed! (struc 9)");    
  while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $itemcount = $line["itemcount"];
  }
  mysql_free_result($result);
  if ($itemcount < 1) {
    echo "This entry does not exist!<br />\n";
    echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
    echo "</body></html>\n";
    die();
  }
  $molstruc = "";
  $mymd5str = "";
  $qstr = "SELECT struc FROM $structable WHERE $idname = $item_id";
  $result = mysql_query($qstr) or die("Query failed! (struc 10)");    
  while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $molstruc = $line["struc"];
  }
  mysql_free_result($result);

  if ($molstruc == "") {
    echo "empty structure<br />\n";
  } else {
    $mymd5str = md5($molstruc);
  }  
  
  echo "<h3>Erasing entry $item_id from data collection $db_id...</h3>\n";
  //echo "hash value (requested): $md5str<br />\n";
  //echo "hash value (calculated): $mymd5str<br />\n";
  
  if ($md5str == $mymd5str) {
    // erase everything
    // delete old molstruc record
    $qstr = "DELETE FROM $structable WHERE $idname = $item_id";
    $result = mysql_query($qstr);
    $err = mysql_errno();
    if ($err != 0) { $errorcount++; }
  
    // delete old moldata record
    $qstr = "DELETE FROM $datatable WHERE $idname = $item_id";
    $result = mysql_query($qstr);
    $err = mysql_errno();
    if ($err != 0) { $errorcount++; }

    // delete old molcfp record
    $qstr = "DELETE FROM $cfptable WHERE $idname = $item_id";
    $result = mysql_query($qstr);
    $err = mysql_errno();
    if ($err != 0) { $errorcount++; }

    // delete old molfgb record
    $qstr = "DELETE FROM $fgbtable WHERE $idname = $item_id";
    $result = mysql_query($qstr);
    $err = mysql_errno();
    if ($err != 0) { $errorcount++; }
  
    if ($datatype == 1) {
      // delete old molstat record
      $qstr = "DELETE FROM $molstattable WHERE mol_id = $item_id";
      $result = mysql_query($qstr);
      $err = mysql_errno();
      if ($err != 0) { $errorcount++; }
    
      // delete old pic2d record
      $qstr = "DELETE FROM $pic2dtable WHERE mol_id = $item_id";
      $result = mysql_query($qstr);
      $err = mysql_errno();
      if ($err != 0) { $errorcount++; }
    
      if ($usemem == 'T') {
        $molstattable  = str_replace($memsuffix, "", $molstattable);
        $molcfptable   = str_replace($memsuffix, "", $molcfptable);
      
        // delete old molststat record first
        $qstr = "DELETE FROM $molstattable WHERE mol_id = $item_id";
        $result = mysql_query($qstr);
        $err = mysql_errno();
        if ($err != 0) { $errorcount++; }
      
        // delete old molcfp record first
        $qstr = "DELETE FROM $molcfptable WHERE mol_id = $item_id";
        $result = mysql_query($qstr);
        $err = mysql_errno();
        if ($err != 0) { $errorcount++; }
      }
    }
  
    echo "<h3>Done.</h3>\n";
  }
  if ($debug > 0) { debug_output("Action finished with $errorcount errors.<br />\n"); }
  $errorcount = 0;

  echo "<p /><a href=\"$myname?db=$db_id\">Continue</a>\n";
  echo "</body></html>\n";
  die();
}

function mk_inputline($iname,$idefault) {
  echo "<input type=\"text\" name=\"$iname\" size=\"106\" value=\"$idefault\">";
}

function mk_inputarea($iname,$idefault) {
  echo "<textarea name=\"$iname\" cols=\"80\" rows=\"4\">$idefault</textarea>\n";
}

function mk_inputselect($iname,$itype,$idefault) {
  $lpos = strpos($itype,"(");
  $itype = substr($itype,($lpos+1));
  $rpos = strrpos($itype,")");
  $list1 = substr($itype,0,$rpos);
  $aitems = explode(",",$list1);
  echo "<select size=\"1\" name=\"$iname\">\n";
  foreach ($aitems as $item) {
    $ditem = str_replace("'","",$item);
    echo "<option value=\"$ditem\""; if ($idefault == $ditem) {echo " selected";} echo ">&nbsp;$ditem&nbsp;</option>\n";
  }  // foreach
  echo "</select>";
}


function mk_dataeditform($item_id,$formtype) {
  global $db_id;
  global $moldatatable; 
  global $rxndatatable; 
  global $myname;
  if ($formtype == 1) {
    $idname = "mol_id";
    $datatable = $moldatatable;
  } elseif ($formtype == 2) {
    $idname = "rxn_id";
    $datatable = $rxndatatable;
  }
  echo "<h3>Edit textual data for entry no. $item_id in data collection $db_id</h3>\n";
  echo "<form name=\"dataform\" action=\"$myname\" method=post>\n";
  echo "<table bgcolor=\"#eeeeee\">\n";
  echo "<tr align=\"left\"><th>Field:</th><th>Value:</th></tr>\n";
  $i = 0;
  $qstr = "SHOW FULL COLUMNS FROM $datatable";
  $result = mysql_query($qstr)
    or die("Query failed! (1c6)");
  while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $field   = $line["Field"];
    $type    = $line["Type"];
    $comment = $line["Comment"];
    if ($field != $idname) {
      $i++;
      $oldval = "";   //preliminary....
      $qstr2 = "SELECT $field FROM $datatable WHERE $idname = $item_id";
      $result2 = mysql_query($qstr2)
        or die("Query failed! (oldval)");
      $line2 = mysql_fetch_row($result2);
      mysql_free_result($result2);
      $oldval = $line2[0];
      $ifmt = "line";
      if (strlen($comment)>4) {
        $pos = strpos($comment, ">>>>");
        if ($pos !== false) {
          if ($pos == 0) {
            $comment = str_replace(">>>>","",$comment);
            $acomment = explode("<",$comment);
            $label  = $acomment[0];
            $nformat = $acomment[1];
            if ($nformat == 0) { $ifmt = "line"; }
            if ($nformat == 1) { $ifmt = "line"; }
            if ($nformat == 2) { $ifmt = "text"; }
            if ($nformat == 3) { $ifmt = "line"; }
          }
        }
      }
      if(stristr($type, 'text') != FALSE) { $ifmt = "text"; }
      if(stristr($type, 'enum') != FALSE) { $ifmt = "select"; }
      if(stristr($type, 'set')  != FALSE) { $ifmt = "select"; }
      
      echo "<tr><td>$field</td><td>";
      if ($ifmt == "line")   { mk_inputline("f${i}",$oldval); }
      if ($ifmt == "text")   { mk_inputarea("f${i}",$oldval); }
      if ($ifmt == "select") { mk_inputselect("f${i}",$type,$oldval); }
      echo "</td></tr>\n";
    }  // if...
  }
  echo "</table><p />\n";
  echo "<input type=\"hidden\" name=\"action\" value=\"savedata\">\n";
  echo "<input type=\"hidden\" name=\"db\" value=\"$db_id\">\n";
  echo "<input type=\"hidden\" name=\"datatype\" value=\"$formtype\">\n";
  echo "<input type=\"hidden\" name=\"id\" value=\"$item_id\">\n";
  echo "<input type=\"hidden\" name=\"nf\" value=\"$i\">\n";
  echo "<input type=\"Submit\" name=\"select\" value=\"&nbsp;&nbsp;Save data&nbsp;&nbsp;\">\n";
  echo "</form><p />\n";
  mysql_free_result($result);
}

function copy_mol($db_id,$mol_id,$targetdb_id) {
  global $prefix;
  global $molstrucsuffix;
  global $moldatasuffix;
  global $molfgbsuffix;
  global $molstatsuffix;
  global $molcfpsuffix;
  global $pic2dsuffix;
  global $memsuffix;
  global $metatable;

  $dbprefix = $prefix . "db" . $db_id . "_";
  $targetdbprefix = $prefix . "db" . $targetdb_id . "_";
  $moldatatable  = $dbprefix . $moldatasuffix;
  $molfgbtable   = $dbprefix . $molfgbsuffix;
  $molstattable  = $dbprefix . $molstatsuffix;
  $molstructable = $dbprefix . $molstrucsuffix;
  $molcfptable   = $dbprefix . $molcfpsuffix;
  $pic2dtable    = $dbprefix . $pic2dsuffix;

  $next_mol_id = get_next_mol_id($targetdb_id);

  if (intval($db_id) == intval($targetdb_id)) {
    $table_list = array($moldatatable,$molfgbtable,$molstattable,$molstructable,$molcfptable);
  } else {
    $table_list = array($molfgbtable,$molstattable,$molstructable,$molcfptable);
  }

  $field_name = array();
  $field_val = array();
  foreach ($table_list as $table) {
    $targettable = str_replace($dbprefix,$targetdbprefix,$table);
    $qstr1 = "DESCRIBE $table";
    $result1 = mysql_query($qstr1) or die("Query failed! (copy_mol #1)");
    $i = 0;
    while ($line1 = mysql_fetch_array($result1, MYSQL_ASSOC)) {
      $field_name[$i] = $line1["Field"];
      $i++;
    } 
    $n_fields = $i;
    #mysql_free_result($result1);
    $qstr2 = "SELECT * FROM $table WHERE mol_id = $mol_id";
    $result2 = mysql_query($qstr2) or die("Query failed! (copy_mol #2)");
    while ($line2 = mysql_fetch_array($result2, MYSQL_ASSOC)) {
      for ($i = 0; $i < $n_fields; $i++) {
        $field = $field_name[$i];
        $field_val[$i] = $line2[$field];
        if ($field == "mol_id") {
          $field_val[$i] = $next_mol_id;
        }
      }   // end for ...
    }   // end while ...
    $valstr = "";
    for ($i = 0; $i < $n_fields; $i++) {
      $val = $field_val[$i];
      if (strlen($valstr) > 0) { $valstr .= ","; }
      $valstr .= "\"" . $val . "\"";
    }
    $qstr3 = "INSERT INTO $targettable VALUES (" . $valstr . ")";
    $result3 = mysql_query($qstr3);
    $err = 0;
    $err = mysql_errno();
    if ($err != 0) { 
      echo "<br />Action failed (duplicate_mol #3/$err: " . mysql_error() . ")<br />\n"; 
    } #else { echo "."; }
  }  // end foreach table ...
  
  // if sourcedb and targetdb are different, copy only mol_name in moldatatable
  if (intval($db_id) != intval($targetdb_id)) {
    $qstr4 = "SELECT mol_name FROM $moldatatable WHERE mol_id = $mol_id";
    $result4 = mysql_query($qstr4) or die("Query failed! (copy_mol #4)");
    while ($line4 = mysql_fetch_array($result4, MYSQL_ASSOC)) {
      $mol_name = $line4["mol_name"];
    }
    $qstr5 = "INSERT INTO " . $targetdbprefix . $moldatasuffix . "(mol_id,mol_name) ";
    $qstr5 .= "VALUES (" . $next_mol_id . ",'" . $mol_name . "')";
    $result5 = mysql_query($qstr5);
    $err = 0;
    $err = mysql_errno();
    if ($err != 0) { 
      echo "<br />Action failed (copy_mol #5/$err: " . mysql_error() . ")<br />\n"; 
    } #else { echo "."; }
  }
  // handle pic2dtable separately...
  $qstr6 = "INSERT INTO " . $targetdbprefix . $pic2dsuffix . " VALUES ($next_mol_id, \"1\", \"3\" )"; // 0 = does not exist, 1 = OK, 2 = OK, but do not show, 3 = to be created/updated, 4 = to be deleted
  $result6 = mysql_query($qstr6);
  $err = 0;
  $err = mysql_errno();
  if ($err != 0) { 
    echo "<br />Action failed (copy_mol #6/$err: " . mysql_error() . ")<br />\n"; 
  } #else { echo "."; }
  set_memstatus_dirty($db_id);
  return($next_mol_id);
}

function copy_rxn($db_id,$rxn_id,$targetdb_id) {
  global $prefix;
  global $rxnstrucsuffix;
  global $rxndatasuffix;
  global $rxnfgbsuffix;
  global $rxncfpsuffix;

  $dbprefix = $prefix . "db" . $db_id . "_";
  $targetdbprefix = $prefix . "db" . $targetdb_id . "_";
  $rxndatatable  = $dbprefix . $rxndatasuffix;
  $rxnfgbtable   = $dbprefix . $rxnfgbsuffix;
  $rxnstructable = $dbprefix . $rxnstrucsuffix;
  $rxncfptable   = $dbprefix . $rxncfpsuffix;

  $next_rxn_id = get_next_rxn_id($targetdb_id);

  if (intval($db_id) == intval($targetdb_id)) {
    $table_list = array($rxndatatable,$rxnfgbtable,$rxnstructable,$rxncfptable);
  } else {
    $table_list = array($rxnfgbtable,$rxnstructable,$rxncfptable);
  }

  $field_name = array();
  $field_val = array();
  foreach ($table_list as $table) {
    $targettable = str_replace($dbprefix,$targetdbprefix,$table);
    $qstr1 = "DESCRIBE $table";
    $result1 = mysql_query($qstr1) or die("Query failed! (copy_rxn #1)");
    $i = 0;
    while ($line1 = mysql_fetch_array($result1, MYSQL_ASSOC)) {
      $field_name[$i] = $line1["Field"];
      $i++;
    } 
    $n_fields = $i;
    $qstr2 = "SELECT * FROM $table WHERE rxn_id = $rxn_id";
    $result2 = mysql_query($qstr2) or die("Query failed! (copy_rxn #2)");
    while ($line2 = mysql_fetch_array($result2, MYSQL_ASSOC)) {
      for ($i = 0; $i < $n_fields; $i++) {
        $field = $field_name[$i];
        $field_val[$i] = $line2[$field];
        if ($field == "rxn_id") {
          $field_val[$i] = $next_rxn_id;
        }
      }   // end for ...
    }   // end while ...
    $valstr = "";
    for ($i = 0; $i < $n_fields; $i++) {
      $val = $field_val[$i];
      if (strlen($valstr) > 0) { $valstr .= ","; }
      $valstr .= "\"" . $val . "\"";
    }
    $qstr3 = "INSERT INTO $targettable VALUES (" . $valstr . ")";
    $result3 = mysql_query($qstr3);
    $err = 0;
    $err = mysql_errno();
    if ($err != 0) { 
      echo "<br />Action failed (copy_rxn #3/$err: " . mysql_error() . ")<br />\n"; 
    } #else { echo "."; }
  }  // end foreach table ...
  if (intval($db_id) != intval($targetdb_id)) {
    $qstr4 = "SELECT rxn_name FROM $rxndatatable WHERE rxn_id = $rxn_id";
    $result4 = mysql_query($qstr4) or die("Query failed! (copy_rxn #4)");
    while ($line4 = mysql_fetch_array($result4, MYSQL_ASSOC)) {
      $rxn_name = $line4["rxn_name"];
    }
    $qstr5 = "INSERT INTO " . $targetdbprefix . $rxndatasuffix . "(rxn_id,rxn_name) ";
    $qstr5 .= "VALUES (" . $next_rxn_id . ",'" . $rxn_name . "')";
    $result5 = mysql_query($qstr5);
    $err = 0;
    $err = mysql_errno();
    if ($err != 0) { 
      echo "<br />Action failed (copy_rxn #5/$err: " . mysql_error() . ")<br />\n"; 
    } #else { echo "."; }
  }
  return($next_rxn_id);
}


$result = mysql_query("SELECT COUNT($idname) AS molcount, MAX($idname) AS max_mol_id FROM $structable")
  or die("Query failed! (1c7)");
$line = mysql_fetch_row($result);
mysql_free_result($result);
$molcount   = $line[0];
$max_mol_id = $line[1];
if ($molcount == 0) { $max_mol_id = 0; }
if ($molcount > 0) {
  echo "<p><small>current entries: $molcount, highest entry no.: $max_mol_id</small></p>\n";

  echo "<form name=\"edform1\" action=\"$myname\" method=post>\n";
  echo "<input type=\"hidden\" name=\"action\" value=\"editdata\">\n";
  echo "<input type=\"hidden\" name=\"db\" value=\"$db_id\">\n";
  echo "<input type=\"hidden\" name=\"datatype\" value=\"$formtype\">\n";
  echo "<input type=\"Submit\" name=\"select\" value=\"&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Edit entry no.&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\">\n";
  echo "<input type=\"text\" name=\"id\" size=\"12\"><br />\n";
  echo "</form><p />\n";

  echo "<form name=\"edform1\" action=\"$myname\" method=post>\n";
  echo "<input type=\"hidden\" name=\"action\" value=\"duplcopy\">\n";
  echo "<input type=\"hidden\" name=\"db\" value=\"$db_id\">\n";
  echo "<input type=\"hidden\" name=\"datatype\" value=\"$formtype\">\n";
  echo "<input type=\"Submit\" name=\"select\" value=\"&nbsp;&nbsp;&nbsp;&nbsp;Copy entry no.&nbsp;&nbsp;&nbsp;&nbsp;\">\n";
  echo "<input type=\"text\" name=\"id\" size=\"12\"><br />\n";
  echo "</form><p />\n";

  echo "<form name=\"edform1\" action=\"$myname\" method=post>\n";
  echo "<input type=\"hidden\" name=\"action\" value=\"eraserecord\">\n";
  echo "<input type=\"hidden\" name=\"db\" value=\"$db_id\">\n";
  echo "<input type=\"Submit\" name=\"select\" value=\"&nbsp;&nbsp;&nbsp;&nbsp;Erase entry no.&nbsp;&nbsp;&nbsp;\">\n";
  echo "<input type=\"text\" name=\"id\" size=\"12\"><br />\n";
  echo "</form><p />\n";

}
echo "<form name=\"edform2\" action=\"$myname\" method=\"post\">\n";
echo "<input type=\"hidden\" name=\"action\" value=\"addstruc1\">\n";
echo "<input type=\"hidden\" name=\"db\" value=\"$db_id\">\n";
echo "<input type=\"Submit\" name=\"select\" value=\"&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Add new entry&nbsp;&nbsp;&nbsp;&nbsp;\">\n";
echo "</form><p />\n";

echo "Back to <a href=\"./?db=$db_id\">database administration</a>\n";
?>

<br />
<hr />
<br />
</body>
</html>
