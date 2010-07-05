<?php 
// incsim.php       Norbert Haider, University of Vienna, 2009-2010
// part of MolDB5R  last change: 2010-06-09

$maxhits = 250;               // maximum number of hits we want to display
$maxcand = 50000;             // maximum number of candidate structures we want to allow
$default_threshold = 0.60;    // minimum similraity (according to Tanimoto index)
$default_ms_maxfactor = 2;    // determines max. number of each molstat feature
$default_ms_minfactor = 0.5;  // determines min. number of each molstat feature
$default_n1_maxfactor = 1.8;  // determines max. number of 1-bits in candidate hfp
$default_n1_minfactor = 0.7;  // determines min. number of 1-bits in candidate hfp
//$hits = 0;

echo "<h3>Found similar structures:</h3>\n";


function count1bits($number) {
  if (!is_numeric($number)) {
    return 0;
  }
  $n1bits = 0;
  $number = $number + 0;  // dirty trick to force PHP to use 32-bit unsigned integers, see
  // http://at.php.net/manual/en/language.types.integer.php#language.types.integer.casting
  for ($i = 0; $i < 32; $i++) {
    $testnum = $number >> $i;
    if ($testnum & 1) {
      $n1bits++;
    }
  }
  return $n1bits;
}

$hit[0][0] = 0;       // mol_id
$hit[0][1] = 0.000;   // s
$hit[0][2] = 0;       // db

function insertHit($id,$s,$db) {
  global $maxhits;
  global $hits;
  global $hit;
  $hits++;
  if ($hits == 1) {
    $hit[0][0] = $id;
    $hit[0][1] = $s;
    $hit[0][2] = $db;
  } else {
    $s_test = $hit[($hits - 1)][1];
    if ($s <= $s_test) {
      if ($hits <= $maxhits) {
        $hit[$hits][0] = $id;
        $hit[$hits][1] = $s;
        $hit[$hits][2] = $db;
      } else { $hits--; }
    } else {
      $newpos = 0;
      for ($j = 0; $j < $hits; $j++) {
        $s_test = $hit[$j][1];
        if ($s <= $s_test) { $newpos = $j + 1; }
      }
      for ($k = $hits; $k > $newpos; $k--) {
        $hit[$k][0] = $hit[($k-1)][0];
        $hit[$k][1] = $hit[($k-1)][1];
        $hit[$k][2] = $hit[($k-1)][2];
      }
      $hit[$newpos][0] = $id;
      $hit[$newpos][1] = $s;
      $hit[$newpos][2] = $db;
      if ($hits > $maxhits) {
        $hits--;
      }
    }
  }
}

$options = '-axH';

// remove CR if present (IE, Mozilla et al.) and add it again (for Opera)
$mol = str_replace("\r\n","\n",$mol);
$mol = str_replace("\n","\r\n",$mol);

//$safemol = escapeshellcmd($mol);
$safemol = str_replace(";"," ",$mol);

$origmolstattable = $molstattable;
$origmolhfptable  = $molcfptable;
$origmolbfptable  = $molbfptable;


if ($mol !='') { 
  echo "<table width=\"100%\">\n";
  $time_start = getmicrotime();  

  if ($use_cmmmsrv == 'y') {
    /* create a TCP/IP socket */
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket < 0) {
      //echo "socket_create() failed.\nreason: " . socket_strerror ($socket) . "\n";
      echo "<!-- could not connect to cmmmsrv - reverting to checkmol/matchmol --!>\n";
      $use_cmmmsrv = "n";
    }
    $result = socket_connect ($socket, $cmmmsrv_addr, $cmmmsrv_port);
    if ($result === FALSE) {
      //echo "socket_connect() failed.<p />\n";
      echo "<!-- could not connect to cmmmsrv - reverting to checkmol/matchmol --!>\n";
      $use_cmmmsrv = "n";
    }
    }
  if ($use_cmmmsrv == 'y') {
    $a = socket_read($socket, 250, PHP_NORMAL_READ);
    //echo "the socket says: $a\n";
    $pos = strpos($a,"READY");
    if ($pos === false) {
      echo "<!-- could not connect to cmmmsrv - reverting to checkmol/matchmol --!>\n";
      $use_cmmmsrv = "n";
    }
  }

  if ($use_cmmmsrv == 'y') {
    $safemol .= "\$\$\$\$\n";
    $chkresult = filterthroughcmmm("$safemol", "#### checkmol:axH");
    socket_write($socket,'#### bye');
    socket_close($socket);
  } else {
    if ($ostype == 1) { $chkresult = filterthroughcmd("$safemol", "$CHECKMOL -axH - "); }
    if ($ostype == 2) { $chkresult = filterthroughcmd2("$safemol", "$CHECKMOL -axH - "); }
    //$chkresult = filterThroughCmd("$safemol", "$CHECKMOL -H - ");
  }
  //echo "<pre>$chkresult</pre>";

  if (strlen($chkresult) < 2) {
    echo "no response from checkmol (maybe a server configuration problem?)\n</body></html>\n";
    exit;
  }

  // first part of output: molstat, second part: hashed fingerprints
  $myres = explode("\n", $chkresult);
  $chkresult1 = $myres[0];
  $chkresult2 = $myres[1];
  
  // strip trailing "newline"
  $chkresult1 = str_replace("\n","",$chkresult1);
  $len = strlen($chkresult1);
  // strip trailing semicolon
  if (substr($chkresult1,($len-1),1) == ";") {
    $chkresult1 = substr($chkresult1,0,($len-1));
  }  

  // determine number of atoms in query structure,
  // reject queries with less than 3 atoms ==>
  // $chkresult contains as the first 2 entries: "n_atoms:nn;n_bonds:nn;"
  $scpos = strpos($chkresult1,";");
  $na_str = substr($chkresult1,8,$scpos-8);
  $na_val = intval($na_str);
  if ( $na_val < 3 ) {
    echo "</table>\n<hr>\n";
    echo "Query structure must contain at least 3 atoms!<br />\n</body></html>\n";
    exit;
  }

  $chkresult2 = str_replace("\n","",$chkresult2);

  $myres2 = explode(";", $chkresult2);
  $chkresult3 = $myres2[0];
  $chkresult4 = $myres2[1];
  $hfp = explode(",", $chkresult3);

  $molstat = explode(";",$chkresult1);

  $hits = 0;
  $total_cand_sum = 0;
  $n_structures_sum = 0;
  $nsel = 0;
  $nastr = "";

  foreach ($dba as $db_id) {
    $dbtype        = 0;   // initialize at beginning of each loop!
    $dbprefix      = $prefix . "db" . $db_id . "_";
    $molstructable = $dbprefix . $molstrucsuffix;
    $moldatatable  = $dbprefix . $moldatasuffix;
    $molstattable  = $dbprefix . $molstatsuffix;
    $molcfptable   = $dbprefix . $molcfpsuffix;
    $pic2dtable    = $dbprefix . $pic2dsuffix;
    $qstr01        = "SELECT * FROM $metatable WHERE (db_id = $db_id) AND (type = 1)";
    $result01      = mysql_query($qstr01)
      or die("Query failed (#1)!");    
    while($line01 = mysql_fetch_array($result01)) {
      $db_id        = $line01['db_id'];
      $dbtype       = $line01['type'];
      $dbname       = $line01['name'];
      $usemem       = $line01['usemem'];
      $memstatus    = $line01['memstatus'];
      $digits       = $line01['digits'];
      $subdirdigits = $line01['subdirdigits'];
    }
    mysql_free_result($result01);
  
    // use only SD data collections 
    if ($dbtype == 1) {
      $nsel++;
      if (!isset($digits) || (is_numeric($digits) == false)) { $digits = 8; }
      if (!isset($subdirdigits) || (is_numeric($subdirdigits) == false)) { $subdirdigits = 0; }
      if ($subdirdigits < 0) { $subdirdigits = 0; }
      if ($subdirdigits > ($digits - 1)) { $subdirdigits = $digits - 1; }
      if ($usemem == 'T') {
        if (($memstatus & 1) == 1) { $molstattable  .= $memsuffix; }
        if (($memstatus & 2) == 2) { $molcfptable   .= $memsuffix; }
      }
    
      // dynamically adjust molstat selectivity, depending on number of structures
    
      $qstrmc        = "SELECT COUNT(mol_id) AS molcount FROM $molstructable";
      $resultmc      = mysql_query($qstrmc)
        or die("Query failed (#1a)!");    
      while($linemc = mysql_fetch_array($resultmc)) {
        $molcount   = $line01['molcount'];
      } 
      mysql_free_result($resultmc);
      
      $threshold    = $default_threshold;
      $ms_maxfactor = $default_ms_maxfactor;
      $ms_minfactor = $default_ms_minfactor;
      $n1_maxfactor = $default_n1_maxfactor;
      $n1_minfactor = $default_n1_minfactor;
    
      if ($molcount < 10000) {
        $threshold    = $default_threshold - 0.02;
        $ms_maxfactor = $default_ms_maxfactor * 1.5;
        $ms_minfactor = $default_ms_minfactor * 0.6;
        $n1_maxfactor = $default_n1_maxfactor * 1.3;
        $n1_minfactor = $default_n1_minfactor * 0.80;
      }
      if ($molcount < 1000) {
        $threshold    = $default_threshold - 0.04;
        $ms_maxfactor = $default_ms_maxfactor * 2.5;
        $ms_minfactor = $default_ms_minfactor * 0.4;
        $n1_maxfactor = $default_n1_maxfactor * 1.6;
        $n1_minfactor = $default_n1_minfactor * 0.60;
      }
      if ($molcount < 100) {
        $threshold    = $default_threshold - 0.06;
        $ms_maxfactor = $default_ms_maxfactor * 3.5;
        $ms_minfactor = $default_ms_minfactor * 0.3;
        $n1_maxfactor = $default_n1_maxfactor * 1.9;
        $n1_minfactor = $default_n1_minfactor * 0.50;
      }
      
      $msqstr = "";
      foreach ($molstat as $nline) {
        $narr = explode(":",$nline);
        $nkey = $narr[0];
        $nval = $narr[1];
        $minval = intval($nval * $ms_minfactor);
        $maxval = intval($nval * $ms_maxfactor);
        if ($minval > 0) {
          $msqstr .= " AND (${molstattable}.$nkey >= $minval)";
        }
        $msqstr .= " AND (${molstattable}.$nkey <= $maxval)";
      }
      $n1bits_q = 0;
      $n1 = 0;
      $total_cand = 0;
      $fpnum = 0;
      $fplbl = "";
      $filter_str = "";
      for ($i = 0; $i < 16; $i++) {
        $n1 = count1bits($hfp[$i]);
        $n1bits_q = $n1bits_q + $n1;
        if ($n1 > 3) {
          $fpnum = $i + 1;
          $fplbl = $fpnum;
          while (strlen($fplbl) < 2) { $fplbl = "0" . $fplbl; }
          $filter_str = $filter_str . " AND (${molcfptable}.hfp" . $fplbl . " & " . $hfp[$i] . " > 0)";
        }
      }
    
      // get total number of structures in the database
      $n_qstr = "SELECT COUNT(mol_id) AS count FROM $molstructable;";
      $n_result = mysql_query($n_qstr)
          or die("Could not get number of entries!"); 
      while ($n_line = mysql_fetch_array($n_result, MYSQL_ASSOC)) {
        $n_structures = $n_line["count"];
      } 
      mysql_free_result($n_result);
    
      $n1min = intval($n1bits_q * $n1_minfactor);
      $n1max = intval($n1bits_q * $n1_maxfactor);
      
      // get number of candidates
      $n_qstr = "SELECT COUNT(${molcfptable}.mol_id) AS count FROM $molcfptable, $molstattable";
      $n_qstr .= " WHERE (${molcfptable}.n_h1bits >= " . $n1min . ") AND (${molcfptable}.n_h1bits <= " . $n1max . ")";
    
      $n_qstr = $n_qstr . $filter_str;
      $n_qstr = $n_qstr . $msqstr . " AND (${molcfptable}.mol_id = ${molstattable}.mol_id)";
      
      $n_result = mysql_query($n_qstr)
          or die("Could not get number of candidates!"); 
      while ($n_line = mysql_fetch_array($n_result, MYSQL_ASSOC)) {
        $cand_count = $n_line["count"];
      } 
      mysql_free_result($n_result);
    
      if ($cand_count > $maxcand ) {
        $n1min = intval($n1bits_q * (1 - $n1_minfactor*0.5));
        $n1max = intval($n1bits_q * (1 + ($n1_maxfactor*0.5 - 1)));
        // get reduced number of candidates
        //$n_qstr = "SELECT COUNT(mol_id) AS count FROM $molcfptable WHERE (n_h1bits >= " . $n1min . ") AND (n_h1bits <= " . $n1max . ")";
        //$n_qstr = $n_qstr . $filter_str;
        $n_qstr = "SELECT COUNT(${molcfptable}.mol_id) AS count FROM $molcfptable, $molstattable";
        $n_qstr .= " WHERE (${molcfptable}.n_h1bits >= " . $n1min . ") AND (${molcfptable}.n_h1bits <= " . $n1max . ")";
        $n_qstr = $n_qstr . $filter_str;
        $n_qstr = $n_qstr . $msqstr . " AND (${molcfptable}.mol_id = ${molstattable}.mol_id)";
        $n_result = mysql_query($n_qstr)
            or die("Could not get number of candidates!"); 
        while ($n_line = mysql_fetch_array($n_result, MYSQL_ASSOC)) {
          $cand_count = $n_line["count"];
        } 
        mysql_free_result($n_result);
        echo "reduced number of candidates: $cand_count<br>\n";
        if ($cand_count > $maxcand ) {
          echo "</table>\n<hr>\n";
          echo "Still too many candidate structures ($cand_count)!<br />\n"; 
          echo "Please enter a more specific query.<br />\n</body></html>\n";
          exit; 
        }
      }
    
      $sqlbs       = 10000;
      $offsetcount = 0;
      $total_cand  = 0;
      #$hits       = 0;
      $n_cand      = 1;
      $dbhfp       = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);
    
      //=============== begin outer loop
    
      while ($n_cand > 0) {
        $offset  = $offsetcount * $sqlbs;
        $qstrlim = " LIMIT $offset, $sqlbs";
      
        $c_qstr = "SELECT ${molcfptable}.hfp01, ${molcfptable}.hfp02, ${molcfptable}.hfp03, ${molcfptable}.hfp04,";
        $c_qstr .= " ${molcfptable}.hfp05, ${molcfptable}.hfp06, ${molcfptable}.hfp07, ${molcfptable}.hfp08,";
        $c_qstr .= " ${molcfptable}.hfp09, ${molcfptable}.hfp10, ${molcfptable}.hfp11, ${molcfptable}.hfp12,";
        $c_qstr .= " ${molcfptable}.hfp13, ${molcfptable}.hfp14, ${molcfptable}.hfp15, ${molcfptable}.hfp16,";
        $c_qstr .= " ${molcfptable}.n_h1bits, ${molcfptable}.mol_id";
        $c_qstr .= " FROM $molcfptable, $molstattable WHERE (${molcfptable}.n_h1bits >= $n1min) AND (${molcfptable}.n_h1bits <= $n1max)";
        $c_qstr .= $filter_str;
        $c_qstr = $c_qstr . $msqstr . " AND (${molcfptable}.mol_id = ${molstattable}.mol_id)";
        $c_qstr .= $qstrlim;
        //echo "$c_qstr<br>\n";
        
        $c_result = mysql_query($c_qstr)
            or die("Could not retrieve data!"); 
        $offsetcount ++;
        $n_cand  = mysql_num_rows($c_result);     // number of candidate structures
    
        while ($c_line = mysql_fetch_array($c_result, MYSQL_ASSOC)) {
          $total_cand++;
          $mol_id = $c_line["mol_id"];
          //echo "$total_cand: $mol_id<br>\n";
          $dbhfp = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);
          $dbhfp[0] = $c_line["hfp01"];  $dbhfp[1] = $c_line["hfp02"];
          $dbhfp[2] = $c_line["hfp03"];  $dbhfp[3] = $c_line["hfp04"];
          $dbhfp[4] = $c_line["hfp05"];  $dbhfp[5] = $c_line["hfp06"];
          $dbhfp[6] = $c_line["hfp07"];  $dbhfp[7] = $c_line["hfp08"];
          $dbhfp[8] = $c_line["hfp09"];  $dbhfp[9] = $c_line["hfp10"];
          $dbhfp[10] = $c_line["hfp11"]; $dbhfp[11] = $c_line["hfp12"];
          $dbhfp[12] = $c_line["hfp13"]; $dbhfp[13] = $c_line["hfp14"];
          $dbhfp[14] = $c_line["hfp15"]; $dbhfp[15] = $c_line["hfp16"];
          $dbn1bits = $c_line["n_h1bits"];
          
          $n1 = 0;
          $n2 = 0;
          $n3 = 0;
          $a  = 0;
          $b  = 0;
          $c  = 0;
          $a_sum = 0;
          $b_sum = 0;
          $c_sum = 0;
      
          for ($ii = 0; $ii < 16; $ii++) {
            $n1 = $hfp[$ii];
            $n2 = $dbhfp[$ii];
            $n1 = $n1 + 0;     // force treatment as integer number
            $n2 = $n2 + 0;
            $n3 = $n1 & $n2;   // bitwise AND
            $n3 = $n3 + 0;
            $a = count1bits($n1);
            $b = count1bits($n2);
            $c = count1bits($n3);
            $a_sum = $a_sum + $a;
            $b_sum = $b_sum + $b;
            $c_sum = $c_sum + $c;
          }
          $tanimoto = $c_sum / ($a_sum + $b_sum - $c_sum);
          //echo "mol_id: $mol_id  tanimoto: $tanimoto<br />\n";
          if ($tanimoto >= $threshold) {
            insertHit($mol_id,$tanimoto,$db_id);
            //echo "hit $hits: $mol_id ($tanimoto), candidate no. $total_cand<br>\n";
          }
        } 
        mysql_free_result($c_result);
    
      }  // end of outer loop
    
      $total_cand_sum = $total_cand_sum + $total_cand;
      $n_structures_sum = $n_structures_sum + $n_structures;
  
    } else { $nastr = "similarity search is not supported for reaction data collections<br>"; }  // end if ($dbtype == 1)
  
  }  // foreach

  echo "<small>search finished with $hits hits";
  if ($hits > 1) {
    echo ", sorted by similarity";
  }
  echo " (Tanimoto index in parentheses)</small><p />\n";

  if ($hits > 0) {
    for ($h = 0; $h < $hits; $h++) {
      $hit_id = $hit[$h][0];
      $hit_s  = $hit[$h][1];
      $hit_s_formatted = "(" . sprintf("%1.4f", $hit_s) . ")";
      $db_id = $hit[$h][2];
      $dbprefix      = $prefix . "db" . $db_id . "_";
      $molstructable = $dbprefix . $molstrucsuffix;
      $moldatatable  = $dbprefix . $moldatasuffix;
      $pic2dtable    = $dbprefix . $pic2dsuffix;
      showHit($hit_id,$hit_s_formatted);
      //echo "hit $h: $hit_id ($hit_s)<br>\n";
    }
  }

  echo "</table>\n<hr>\n";
  if ($nsel == 0) {
    echo "no structure data collection selected!<br>\n<hr>\n";
  }
  echo "$nastr";

  $time_end = getmicrotime();  
  print "<p><small>number of hits: <b>$hits</b> (out of $total_cand_sum candidate structures)<br />\n";
  $time = $time_end - $time_start;
  print "total number of structures in data collection(s): $n_structures_sum <br />\n";
  printf("time used for query: %2.3f seconds</small></p>\n", $time);
}                  // if ($mol != '')...
?>
