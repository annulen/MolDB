#!/usr/bin/perl
#
# rdf2moldb.pl   Norbert Haider, University of Vienna, 2010
#                norbert.haider@univie.ac.at
#
# This script is part of the MolDB5R package. Last change: 2010-06-15
#
# This script reads an RDF file which was previously analyzed by the script 
# "rdfcheck.pl" and adds its content (structures and data) into a MySQL-
# based MolDB5R database. The auxiliary tables are also created.
#
# ===========================================================================
#
# Purpose: setting up a web-based, fully searchable molecular structure 
# database (MolDB5R); for a more detailed description see
# http:#merian.pch.univie.ac.at/~nhaider/cheminf/moldb.html
#
# IMPORTANT: all data are _appended_, no redundancy checks for already 
# existing records are made.
#

$verbose = 2;            # 0, 1 or 2
$askuser = 0;            # 0 or 1, change to 0 for skipping the confirmation
$use_fixed_fields = 0;   # 0 or 1, should be 1 for older versions of checkmol
$append  = 1;            # 0 or 1 (if 0, all existing data will be erased)

if ($#ARGV < 0) {
  print "Usage: rdf2moldb.pl <inputfile>\n";
  exit;
}

$infile = $ARGV[0];

use DBI();
$ostype = getostype();
if ($ostype eq 2) { use File::Temp qw/ tempfile tempdir /; }

$configfile = "moldb5.conf";

$return     = do $configfile;
if (!defined $return) {
  die("ERROR: cannot read configuration file $configfile!\n");
}	

$user     = $rw_user;    # from configuration file
$password = $rw_password;

$db_id                   = 1;  # default data collection number
$db_type                 = 2;  # default data collection type (2 = reaction+data)
$db_access               = 1;  # default access mode (0 = hidden, 1 = read-only, 2 = read/write)
$db_name                 = "";

$deffile = "rdf2moldb.def";
$found_rxn_id   = 0;
$found_rxn_name = 0;
open (DEF, "<$deffile") || die("ERROR: cannot open definition file $deffile!");
$nfields = 0;
while ($line = <DEF>) {
  chomp($line);
  @valid = split (/#/, $line);  # ignore everything behind the first pound sign
  $line  = $valid[0];
  $line  = ltrim($line);
  $line  = rtrim($line);
  if ((index($line,'rdfilename') == 0) && (index($line,'=') >= 10)) {
    @defrec = split (/=/, $line);
    $rdfile = $defrec[1];
    $rdfile = ltrim($rdfile);
  }
  if ((index($line,'db_id') == 0) && (index($line,'=') >= 5)) {
    @defrec = split (/=/, $line);
    $db_id = $defrec[1];
    $db_id = ltrim($db_id);
  }
  if ((index($line,'db_type') == 0) && (index($line,'=') >= 7)) {
    @defrec = split (/=/, $line);
    $db_type = $defrec[1];
    $db_type = ltrim($db_type);
  }
  if ((index($line,'db_name') == 0) && (index($line,'=') >= 7)) {
    @defrec = split (/=/, $line);
    $db_name = $defrec[1];
    $db_name = ltrim($db_name);
    $db_name =~ s/\"//g;
  }
  if ((index($line,'db_description') == 0) && (index($line,'=') >= 14)) {
    @defrec = split (/=/, $line);
    $db_description = $defrec[1];
    $db_description = ltrim($db_description);
    $db_description =~ s/\"//g;
  }
  if ((index($line,'db_access') == 0) && (index($line,'=') >= 9)) {
    @defrec = split (/=/, $line);
    $tmp_access = $defrec[1];
    $tmp_access = ltrim($tmp_access);
    if ($tmp_access == 0) { $db_access = 0; }
    if ($tmp_access == 1) { $db_access = 1; }
    if ($tmp_access == 2) { $db_access = 2; }
    if ($tmp_access == 3) { $db_access = 3; }    
  }
  
  $lpos = index($line,':');
  $rpos = rindex($line,':');
  if (($lpos >= 1) && ($rpos >= 3) && ($rpos > $lpos)) {
    # this should be a definition line
    @defrec = split (/:/, $line);
    $rdf_label   = $defrec[0];
    $rdf_label   =~ s/\!/\:/g;    # convert ! back into :
    $mysql_label = $defrec[1];
    $mysql_type  = $defrec[2];
    $html_label  = $defrec[3];
    $html_format = $defrec[4];
    @afield[($nfields)] = [ $rdf_label, $mysql_label, $mysql_type, $html_label, $html_format, "" ];
    $nfields++;
    if ($mysql_label eq "rxn_name") { $found_rxn_name = 1; }
    if ($mysql_label eq "rxn_id") { $found_rxn_id = 1; print "$line\n"; }
  }
}

if ($verbose > 0) { 
  print "\nAppending data to MolDB5R database '$database', using configuration\n";
  print "file '$configfile'.\n\n";
  print "Your RDF input file must have the same format as the one which\n";
  print "has been used previously for analysis with the rdfcheck.pl script:\n";
  print "$rdfile\n\n";
  if ($append eq 0) {
    print "WARNING: all existing data in this collection will be erased!\n\n";
  }
}

if ($found_rxn_id > 0) {
  print STDERR "\nERROR: your definition file $deffile contains a field named\n";
  print STDERR "'rxn_id'! This label must not be used, as it is automatically created\n";
  print STDERR "by the system. Please edit $deffile and change 'rxn_id' into some\n";
  print STDERR "other name.\n";
  exit;
}

if ($found_rxn_name == 0) {
  print "\nWARNING: your definition file $deffile does not contain a field\n";
  print "named 'rxn_name'. It is highly recommended to rename the most descriptive\n";
  print "field into 'rxn_name', please read the instructions in $deffile!\n";
  print "Otherwise, a field 'rxn_name' will be automatically created, but it\n";
  print "will remain empty during the RDF import operation.\n\n";
  $askuser = 1;
}

if ($askuser > 0) {
  print "Do you really want to continue (y/n)? ";
  chomp($word = <STDIN>);
  if (lc($word) eq 'y') {
    if ($verbose > 0) { 
      print "OK. This will take some time, please be patient.\n";
    }
  } else {
    print "aborting...\n";
    exit;
  }
}

if ($verbose > 1) { 
  print "using RD file $infile\n"; 
  print "found the following data field definitions:\n";
  for ($i = 0; $i <= $#afield; $i++) {
    $l1  = $afield[$i][0];
    $l2  = $afield[$i][1];
    $l3  = $afield[$i][2];
    $l4  = $afield[$i][3];
    $l5  = $afield[$i][4];
    while (length($l1) < 25) { $l1 = $l1 . " "; }
    while (length($l2) < 25) { $l2 = $l2 . " "; }
    while (length($l3) < 25) { $l3 = $l3 . " "; }
    while (length($l4) < 5) { $l4 = $l4 . " "; }
    print "$l1   $l2  $l3 $l4 $l5\n";
  } 
}

if ($verbose > 0) { 
  print "\nthese settings are used:\n";
  print "  MySQL database:           $database\n";
  print "  hostname:                 $hostname\n";
  print "  admin user:               $user\n";
  print "  RDF file:                 $infile\n";
  print "  data collection number:   $db_id\n";
  print "  data collection type:     $db_type\n";
  print "  data collection name:     $db_name\n";
  print "  description:              $db_description\n";
  print "  access mode (0,1,2,3):    $db_access\n";
}

# check for checkmol
$return = `$CHECKMOL -v`;
if (index($return,"Usage: checkmol") < 0) {
  die("ERROR: could not find 'checkmol', make sure it is installed");	
}  

# check for matchmol
$return = `$MATCHMOL -v`;
if (index($return,"Usage: matchmol") < 0) {
  die("ERROR: could not find 'matchmol', make sure it is installed");	
}  

if ((!defined $user) || ($user eq "")) {
  die("ERROR: no username specified!\n");
}

# open the MySQL database and check if data collection exists already

$dbh = DBI->connect("DBI:mysql:database=$database;host=$hostname",
                    $user, $password,
                    { RaiseError => 1}
                    ) || die("ERROR: database connection failed: $DBI::errstr");

$qstr = "SELECT db_id, type, name FROM $metatable WHERE db_id = $db_id";
$sth0 = $dbh->prepare($qstr);
$sth0->execute();
$n = 0;
while ($ref0 = $sth0->fetchrow_hashref()) {
  $n++;
  $id   = $ref0->{'db_id'};
  $type = $ref0->{'type'};
  $name = $ref0->{'name'};
}

if ($n > 0) {
  if ($name eq $db_name) {
    if ($verbose > 0) {
      print "INFORMATION: appending data to an already existing collection\n";
    }
    $isnewdb = 0;
  } else {
    print STDERR "ERROR: a data collection with this number, but with a different\n";
    print STDERR "name ($name) exists already: check your settings in $deffile!\n";
    exit;
  }	
} else {
  if ($verbose > 0) {
    print "INFORMATION: this is a new data collection\n";	
  }
  $isnewdb = 1;
}

$dbprefix      = $prefix . "db" . $db_id . "_";
$rxnstructable = $dbprefix . $rxnstrucsuffix;
$rxndatatable  = $dbprefix . $rxndatasuffix;
$rxnfgbtable   = $dbprefix . $rxnfgbsuffix;
$rxncfptable   = $dbprefix . $rxncfpsuffix;


open (RDF, "<$infile") || die("ERROR: cannot open input file $infile!");

if ($isnewdb == 1) {
  $insertcmd = "INSERT INTO $metatable VALUES ( $db_id, 2, $db_access, \"$db_name\", ";
  $insertcmd .= " \"$db_description\", \"F\", 0, 0, 0, \"\")";
  $dbh->do($insertcmd);
}

# read the fragment dictionary from the fpdef table

$createstr = "";
$n_dict = 0;
@fpstruc = [""];
$sth = $dbh->prepare("SELECT fpdef, fptype FROM $fpdeftable");
$sth->execute();
while ($ref = $sth->fetchrow_hashref()) {
  $fpdef   = $ref->{'fpdef'};
  if (length($fpdef)>20) {
    $n_dict++;
    @fpstruc[($n_dict - 1)] = $fpdef;
    $dictnum = $n_dict;
    while (length($dictnum) < 2) { $dictnum = "0" . $dictnum;  }
    $fptype             = $ref->{'fptype'};
    if ($fptype == 1) {
      $createstr .= "  dfp$dictnum BIGINT NOT NULL,\n";
    } else {
      $createstr .= "  dfp$dictnum INT(11) UNSIGNED NOT NULL,\n";
    }
  }
}                 # end while ($ref...
$sth->finish;
chomp($createstr);
if ($n_dict < 1) {
  die("ERROR: could not retrieve fingerprint definition from table $fpdeftable\n");
}


# create tables if they do not exist already

$createcmd = "CREATE TABLE IF NOT EXISTS $rxnstructable (
  rxn_id INT(11) NOT NULL DEFAULT '0', 
  struc MEDIUMBLOB NOT NULL,
  map TEXT CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  PRIMARY KEY rxn_id (rxn_id)
  ) ENGINE = MYISAM CHARACTER SET latin1 COLLATE latin1_bin COMMENT='Reaction structures';";
$dbh->do($createcmd);

$createcmd = "CREATE TABLE IF NOT EXISTS $rxndatatable (
  rxn_id INT(11) NOT NULL DEFAULT '0',\n" ;
if ($found_rxn_name == 0) {
  $createcmd = $createcmd . "  rxn_name TEXT NOT NULL,\n";
}  
for ($i = 0; $i <= $#afield; $i++) {
  $l1  = $afield[$i][0];       # RDF field name
  $l2  = $afield[$i][1];       # MySQL field name
  $l3  = $afield[$i][2];       # MySQL field type
  $l4  = $afield[$i][3];       # HTML field name
  $l5  = $afield[$i][4];       # HTML format option
  $l6  = $afield[$i][5];       # search mode
  $l7  = $afield[$i][6];       # reserved
  if ($l4 eq "") { $l4 = $l2; }
  if ($l5 eq "") { $l5 = 1; }
  $createcmd = $createcmd . "  $l2 $l3 COMMENT '>>>>$l4<$l5<$l1<$l6<$l7<',\n";
} 
$createcmd = $createcmd . "  PRIMARY KEY rxn_id (rxn_id)
) ENGINE = MYISAM CHARACTER SET latin1 COLLATE latin1_swedish_ci COMMENT='Reaction data';";
$dbh->do($createcmd);


# create a new rxnfgb table
$createcmd="CREATE TABLE IF NOT EXISTS $rxnfgbtable (rxn_id INT(11) NOT NULL DEFAULT '0', 
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
$dbh->do($createcmd);

  
# create a new rxncfp table if not yet present
$createcmd="CREATE TABLE IF NOT EXISTS $rxncfptable (
  rxn_id INT(11) NOT NULL DEFAULT '0',
  role CHAR(1) NOT NULL,
$createstr
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
  n_h1bits SMALLINT NOT NULL, PRIMARY KEY rxn_id (rxn_id,role) 
  ) ENGINE = MYISAM COMMENT='Combined dictionary-based and hash-based fingerprints'";
$dbh->do($createcmd);


# Now the tables have been created, if necessary

# Next, disable use of memory-based tables
$updstr = "UPDATE $metatable SET memstatus = 0 WHERE db_id = $db_id";
$dbh->do($updstr);	


# Remove any existing data if $append is set to 0

if ($append eq 0) {
  if ($verbose > 0) {
    print "erasing existing data... \n";
  }
  $delcmd = "TRUNCATE TABLE $rxnstructable; ";
  $dbh->do($delcmd);
  $delcmd = "TRUNCATE TABLE $rxndatatable; ";
  $dbh->do($delcmd);
  $delcmd = "TRUNCATE TABLE $rxnfgbtable; ";
  $dbh->do($delcmd);
  $delcmd = "TRUNCATE TABLE $rxncfptable; ";
  $dbh->do($delcmd);
}


# Then, get the next available rxn_id number

$entry = 0;
$sth0  = $dbh->prepare("SELECT rxn_id FROM $rxnstructable ORDER BY rxn_id DESC LIMIT 0,1 ");
$sth0->execute();
while ($ref0 = $sth0->fetchrow_hashref()) {
  $entry = $ref0->{'rxn_id'};
}

if ($verbose > 1) { print "number of reactions already in the database: $entry \n"; }

$counter  = 0;
$li       = 0;
$mol      = '';
$txt      = '';
$lbl      = '';
$ct       = 1;
$badmols  = 0;
$allmaps = "";

# process the input file line by line

$fheader = "";
$ct = 1;

while ($line = <RDF>) {
  $line =~ s/\r//g;
  if (index($line,'$RFMT') != 0) {
    if (substr($line,0,6) eq '$DTYPE') { $ct = 0; }
    if (substr($line,0,4) eq '$RXN')   { $ct = 1; }
    if ($ct == 1) { $rxn .= $line; }
    if ($ct == 0) { $txt .= $line; }
  }
  if ((substr($line,0,5) eq '$RFMT') || eof) {
    $ct = 1;
    if (length($fheader) == 0) {
      $fheader = $rxn;
      $rxn = "";
      $txt = "";
    }
    if (length($rxn) > 0) {
      if (valid_rxn($rxn) == 1) {
        $counter++;
        $entry++;
        if ($verbose > 1) { 
          print "adding entry $entry\n"; 
        } else {
          if ((($counter % 100) == 0) && ($verbose > 0)) { print "$counter records processed\n"; }
        }
        $allmaps = get_maps($rxn);
        insert_rxn($rxn); 
        insert_rxnfgb_rxncfp($rxn);
        insert_data($txt);
      } else { $badmols++; }
      $rxn = "";
      $txt = "";
    }  
  }
}  # end while line


$dbh->disconnect();

if ($verbose > 0) {
  print "==============================================================================\n";
  print "$counter records processed in total\n";
  print "$badmols records ignored\n\n";
}

#===================== subroutines =======================================

sub insert_rxn() {
  my $reaction = shift;
  #print "$reaction\n";	
  if ($tweakmolfiles eq "y") {
    my @mol = split (/\$MOL\n/, $reaction);
    for ($i = 1; $i <= $#mol; $i++) {
      $element  = $mol[$i];
      if ($ostype eq 2) {
        $element = filterthroughcmd2($element,"$CHECKMOL -m -");
      } else {
        $element = filterthroughcmd($element,"$CHECKMOL -m -");
      }
      chomp($element);
      $element .= "\n";
      $mol[$i] = $element;
    }
    my $newrxn = join("\$MOL\n",@mol);
    #print "after tweaking:\n$newrxn\n";
    # now apply the maps....
    $myrxn = $newrxn;
    $mymap = $allmaps;
    #print "mymap: $mymap\n";
    if (length($mymap) > 0) { 
      $newrxn = apply_maps(); 
      #print "after tweaking and re-mapping:\n$newrxn\n";
      $reaction = $newrxn;
    }
    $allmaps = "";
    #$maps = "";
    #print " using maps $mymap\n";
  }
  $qstr = "INSERT INTO " . $rxnstructable . " VALUES ( ";
  $qstr .= $entry . ", \"" . $reaction . "\", \"" . $mymap . "\" ) ";
  #print "$qstr\n";
  $dbh->do($qstr);
}



sub insert_data() {
  $data = shift;
  my $what = "`rxn_id`";
  $databuf  = "";
  @rec = split (/\n/, $data);
  $indata = 1;
  for ($i = 0; $i <= $#rec + 1; $i++) {
    $element  = $rec[$i];
    #$element =~ s/\n//g;
    chomp($element);
    $element =~ s/\ +$//g;
    $lblchars = 0;
    if ((index($element,'$DTYPE') == 0) || ($i == $#rec)) {
      # if anything is pending, flush it
      if ((index($element,'$DTYPE') != 0) && ($i == $#rec)) {
        if (substr($element,0,6) eq '$DATUM') {
          substr($element,0,6) = "";
          $element =~ s/^\ +//g;
        }
        $databuf .= $element;
      }
      if ((length($lblname) > 0) && (length($databuf) > 0)) {
        #print "lblname: $lblname\n";
        #print "databuf: $databuf\n";
        # find the correct field name and insert the data into the array
        for ($j = 0; $j <= $#afield; $j++) {
          $knownlbl = $afield[$j][0];
          if ($lblname eq $knownlbl) { 
            chomp($databuf);   # remove last new-line
            $afield[$j][3] = $databuf;
            #print " === lblname: $lblname \n === databuf: $databuf \n";
          }
        }
      }
      $lblname = "";
      $databuf = "";
      # if not the last line, make the new label
      if (index($element,'$DTYPE') == 0) {
        $lblname = $element;
        substr($lblname,0,6) = "";
        $lblname =~ s/^\ +//g;
      }
    } else {   # this must be a data line (but not the last one)
      if (substr($element,0,6) eq '$DATUM') {
        substr($element,0,6) = "";
        $element =~ s/^\ +//g;
      }
      $databuf .= $element;
    }   # end else
  }   # for $i ...
  $insertcmd1 = "INSERT INTO $rxndatatable (";
  $insertcmd2 = ") VALUES ( $entry";
  #if ($found_rxn_name == 0) {
  #  $insertcmd2 = $insertcmd2 . ", \"\"";
  #  $what .= ",`rxn_name`";
  #}  
  for ($j = 0; $j <= $#afield; $j++) {
    $fname = $afield[$j][1];
    $what .= ",`" . $fname . "`";
    $item = $afield[$j][3];
    $item =~ s/\"/\\\"/g;
    $insertcmd2 = $insertcmd2 . ",\n \"$item\"";
  }
  $insertcmd2 = $insertcmd2 . " )";
  $insertcmd = $insertcmd1 . $what . $insertcmd2;
  for ($j = 0; $j <= $#afield; $j++) {
    $afield[($j)][3] = "";
  }
  #print "$insertcmd\n";
  $dbh->do($insertcmd);
}

sub insert_rxnfgb_rxncfp() {
  my $reaction = shift;
  my @allmol = split(/\$MOL\n/, $reaction);
  my $header = $allmol[0];
  my @harr = split(/\n/,$header);
  $statline = $harr[4];
  my $nrmol = substr($statline,0,3);
  $nrmol =~ s/^\ +//g;
  my $npmol = substr($statline,3,3);
  $npmol =~ s/^\ +//g;
  my @rmol = "";
  my @pmol = "";
  $rfgb = ""; $rfgbsum = "";
  $pfgb = ""; $pfgbsum = "";
  $rdfp = ""; $rdfpsum = "";
  $pdfp = ""; $pdfpsum = "";
  $rhfp = ""; $rhfpsum = "";
  $phfp = ""; $phfpsum = "";
  #$rfgbsum = "1,2,3,4,5,6,7,8";
  for (my $i = 0; $i < $nrmol; $i++) {
    $molecule = $allmol[($i + 1)];
    if ($ostype eq 2) {
      $molfgbhfp  = filterthroughcmd2($molecule,"$CHECKMOL -abH -");   # must be version 0.4 or higher
    } else {
      $molfgbhfp  = filterthroughcmd($molecule,"$CHECKMOL -abH -");   # must be version 0.4 or higher
    }
    my @molfgbhfparray = split(/\n/, $molfgbhfp);
    my $molfgb   = $molfgbhfparray[0];
    my @tmparr = split(/;/,$molfgb);
    $rfgb = $tmparr[0];
    my $molhfp   = $molfgbhfparray[1];
    my @tmparr = split(/;/,$molhfp);
    $rhfp = $tmparr[0];
    $rfgbsum = add_molfp($rfgbsum,$rfgb);
    $rhfpsum = add_molfp($rhfpsum,$rhfp);
    # and now the dfp....
    for ($k = 0; $k < $n_dict; $k++) {
      $dict = @fpstruc[$k];
      $cand = $molecule . "\n" . '$$$$' ."\n" . $dict;
      $cand =~ s/\$/\\\$/g;
      if ($ostype eq 2) {
  	    $dfpstr = filterthroughcmd2($cand,"$MATCHMOL -F -");
	  } else {
	    $dfpstr = filterthroughcmd($cand,"$MATCHMOL -F -");
	  }
      chomp($dfpstr);
      if ($k > 0) { $moldfp .= ","; }
      $rdfp .= " " . $dfpstr;
    }
    $rdfpsum = add_molfp($rdfpsum,$rdfp);
    #print " reactant $i: rfgbsum = $rfgbsum \n";
    #print " reactant $i: rdfpsum = $rdfpsum \n";
    #print " reactant $i: rhfpsum = $rhfpsum \n";
    $rfgb = "";
    $rhfp = "";
    $rdfp = "";
  }  # end for $i ...
  for (my $i = 0; $i < $npmol; $i++) {
    $molecule = $allmol[($i + $nrmol + 1)];
    if ($ostype eq 2) {
      $molfgbhfp  = filterthroughcmd2($molecule,"$CHECKMOL -abH -");   # must be version 0.4 or higher
    } else {
      $molfgbhfp  = filterthroughcmd($molecule,"$CHECKMOL -abH -");   # must be version 0.4 or higher
    }
    my @molfgbhfparray = split(/\n/, $molfgbhfp);
    my $molfgb   = $molfgbhfparray[0];
    my @tmparr = split(/;/,$molfgb);
    $pfgb = $tmparr[0];
    my $molhfp   = $molfgbhfparray[1];
    my @tmparr = split(/;/,$molhfp);
    $phfp = $tmparr[0];
    $pfgbsum = add_molfp($pfgbsum,$pfgb);
    $phfpsum = add_molfp($phfpsum,$phfp);
    # and now the dfp....
    for ($k = 0; $k < $n_dict; $k++) {
      $dict = @fpstruc[$k];
      $cand = $molecule . "\n" . '$$$$' ."\n" . $dict;
      $cand =~ s/\$/\\\$/g;
      if ($ostype eq 2) {
  	    $dfpstr = filterthroughcmd2($cand,"$MATCHMOL -F -");
	  } else {
	    $dfpstr = filterthroughcmd($cand,"$MATCHMOL -F -");
	  }
      chomp($dfpstr);
      if ($k > 0) { $moldfp .= ","; }
      $pdfp .= " " . $dfpstr;
    }
    $pdfpsum = add_molfp($pdfpsum,$pdfp);
    #print " product $i: pfgbsum = $pfgbsum \n";
    #print " product $i: pdfpsum = $pdfpsum \n";
    #print " product $i: phfpsum = $phfpsum \n";
    $pfgb = "";
    $phfp = "";
    $pdfp = "";
  }  # end for $i ...
  $insertstr = "INSERT INTO " . $rxnfgbtable . " VALUES (" . $entry . ",'R',";
  $insertstr .= $rfgbsum . ",0)";
  #print "$insertstr\n";
  $dbh->do($insertstr);
  $insertstr = "INSERT INTO " . $rxnfgbtable . " VALUES (" . $entry . ",'P',";
  $insertstr .= $pfgbsum . ",0)";
  #print "$insertstr\n";
  $dbh->do($insertstr);
  $insertstr = "INSERT INTO " . $rxncfptable . " VALUES (" . $entry . ",'R',";
  $insertstr .= $rdfpsum . "," . $rhfpsum . ",0)";
  #print "$insertstr\n";
  $dbh->do($insertstr);
  $insertstr = "INSERT INTO " . $rxncfptable . " VALUES (" . $entry . ",'P',";
  $insertstr .= $pdfpsum . "," . $phfpsum . ",0)";
  #print "$insertstr\n";
  $dbh->do($insertstr);
}

sub analyze_rxnfile() {
  my $result = "";
  my @rxnarr = split(/\n/,$myrxn);
  my $nrmol = 0;
  my $npmol = 0;
  my $lcount = $#rxnarr;
  if ($lcount > 4) {
    my $tmpline = $rxnarr[4];
    $nrmol = substr($tmpline,0,3);
    $nrmol =~ s/^\ +//g;;
    $npmol = substr($tmpline,3,3);
    $npmol =~ s/^\ +//g;
  }
  $result = "nrmol=" . $nrmol . ";npmol=" . $npmol;
  return($result);
}

sub get_nrmol() {
  #$mydescr = shift;
  my $result = 0;
  my @arr1 = split(/;/,$mydescr);
  #print "  now in get_nrmol: mydescr = $mydescr\n number of elements in arr1: $#arr1 \n";
  #print " arr1_0: $arr1[0]\n arr1_1: $arr1[1]\n";
  if ($#arr1 >= 1) {
    my $tmp1 = $arr1[0];
    #print "  now in get_nrmol: tmp1 = $tmp1\n";
    if (index($tmp1,"nrmol") >= 0) {
      my @arr2 = split(/=/,$tmp1);
      if ($#arr2 >= 1) {
        my $tmp2 = $arr2[1];    
        $tmp2 =~ s/^\ +//g;;
        $result = $tmp2;
      }    
    }
  }
  #print "result of get_nrmol: $result\n";
  return($result);
}

sub get_npmol() {
  #my $mydescr = shift;
  my $result = 0;
  my @arr1 = split(/;/,$mydescr);
  if ($#arr1 >= 1) {
    my $tmp1 = $arr1[1];
    if (index($tmp1,"npmol") >= 0) {
      my @arr2 = split(/=/,$tmp1);
      if ($#arr2 >= 1) {
        my $tmp2 = $arr2[1];    
        $tmp2 =~ s/^\ +//g;;
        $result = $tmp2;
      }    
    }
  }
  #print "result of get_npmol: $result\n";
  return($result);
}

sub get_atomlabels() {
  #$mylblmol = shift;
  my $result  = "";
  my $n_atoms = 0;
  my $n_bonds = 0;
  my @mrec = split(/\n/,$mylblmol);
  $statline = $mrec[3];
  $n_atoms = substr($statline,0,3);
  $n_atoms =~ s/^\ +//g;
  $n_bonds = substr($statline,3,3);
  $n_bonds =~ s/^\ +//g;
  for (my $i = 0; $i < $n_atoms; $i++) {
    my $aline = $mrec[4+$i];
    my $lbl = substr($aline,60,3);  # attention: old JME used wrong column (66-69)
    $lbl =~ s/^\ +//g;
    $lbl = rtrim($lbl);
    if (($lbl ne "0") && (length($lbl) > 0)) {
      my $anum = $i + 1;
      if (length($result) > 0) { $result .= ","; }
      $result .= $anum . "(" . $lbl . ")";
    }
  }
  return($result);
}

sub get_labelstr() {
  #my $mystring = shift;
  my $result = "";
  my $opos = index($mystring,"(");
  my $cpos = index($mystring,")");
  if (($opos > 0) && ($cpos > $opos)) {
    $result = substr($mystring,$opos+1,$cpos - $opos - 1);
  }
  return($result);
}

sub get_anumstr() {
  #$mystring = shift;
  my $result = $mystring;
  $opos = index($mystring,"(");
  if ($opos > 0) {
    $result = substr($mystring,0,$opos);
  }
  return($result);
}

sub get_maps() {
  $myrxn = shift;
  my $result = "";
  $mydescr = "";
  $mydescr = analyze_rxnfile();
  $nrmol = get_nrmol();
  $npmol = get_npmol();
  my @allmol = split(/\$MOL\n/,$myrxn);
  $header = "";
  $header = $allmol[0];
  $n_labels = 0;
  my @label_list = "";
  my @map_list = "";
  my $n_maps = 0;
  if (($nrmol > 0) && ($npmol > 0)) {
    if ($nrmol > 0) {
      for (my $i = 0; $i < $nrmol; $i++) {
        $rmol[$i] = $allmol[($i+1)];
        my $mnum = $i + 1;
        $mylblmol = $rmol[$i];
        my $labels = get_atomlabels();
        #print " in get_maps: reactant $mnum labels = $labels\n";
        $mylblmol = "";
        my $mid = "r" . $mnum;
        if (length($labels) > 0) {
          my @l2arr = split(/,/,$labels);
          for my $item (@l2arr) {
            $label_list[$n_labels] = $mid . ":" . $item;
            #print "==== item in get_maps: $item\n";
            $n_labels++;
          }
        }
      }
    }
    if ($npmol > 0) {
      for (my $i = 0; $i < $npmol; $i++) {
        $pmol[$i] = $allmol[($i+1+$nrmol)];
        my $mnum = $i + 1;
        $mylblmol = $pmol[$i];
        my $labels = get_atomlabels();
        #print " in get_maps: product $mnum labels = $labels\n";
        $mylblmol = "";
        my $mid = "p" . $mnum;
        if (length($labels) > 0) {
          my @larr = split(/,/,$labels);
          for my $item (@larr) {
            $label_list[$n_labels] = $mid . ":" . $item;
            $n_labels++;
          }
        }
      }
    }
    #$label_list = clear_ambigouslabels($label_list);
    # now make the maps
    #print " ----------------------n_labels: $n_labels\n";
    for (my $il = 0; $il < $n_labels; $il++) {
      my $item = $label_list[$il];
      $mystring = $item;
      my $lblstr = get_labelstr();
      my $a1 = get_anumstr();
      for (my $jl = 0; $jl < $n_labels; $jl++) {
        if ($il != $jl) {
          my $item2 = $label_list[$jl];
          $mystring = $item2;
          my $a2 = get_anumstr();
          my $lblstr2 = get_labelstr();
          if ($lblstr eq $lblstr2) {
            if ((substr($a1,0,1) eq "r") && (substr($a2,0,1) eq "p") &&
                ($lblstr ne "0") && ($lblstr2 ne "0")) {
              my $mapstr = $a1 . "=" . $a2;
              $map_list[$n_maps] = $mapstr;
              $n_maps++;
            }
          }
        }
      }
    }
    #print " ---------------------- n_maps: $n_maps\n";
    if ($n_maps > 0) {
      for my $item (@map_list) {
        if (length($result) > 0) { $result .= ","; }
        $result .= $item;
      }
    }
  }   # end if there are both reactants and products
  return($result);
}

sub apply_labels() {
  if (length($mylblmol) > 40) {
    @mol = "";
    @mol = split(/\n/,$mylblmol);
    my $statline = $mol[3];
    my $natoms = substr($statline,0,3);
    $natoms =~ s/^\ +//g;
    if (length($mylabels) > 0) {
      my @label_list = split(/,/,$mylabels);
      for my $label (@label_list) {
        my @larr = split(/-/,$label);
        my $a = $larr[0];
        my $l = $larr[1];
        while (length($l) < 3) { $l = " " . $l; }
        my $molline = $mol[($a + 3)];
        if (length($molline) > 60) { substr($molline,60,3) = $l; }
        $mol[($a + 3)] = $molline;
      }
    }
  }
  my $newmol = join("\n",@mol);
  return($newmol);
}

sub apply_maps() {
  my $result = "";
  $mydescr = analyze_rxnfile();
  $nrmol = get_nrmol();
  $npmol = get_npmol();
  my @allmol = split(/\$MOL\n/,$myrxn);
  my $header = $allmol[0];
  $n_rlabels = 0;
  $n_plabels = 0;
  $n_maps = 0;
  if (length($mymap) > 0) {
    @map_list = split(/,/,$mymap);
    $n_maps = $#map_list + 1;
  }
  if (($nrmol > 0) && ($npmol > 0)) {
    if ($nrmol > 0) {
      for (my $i = 0; $i < $nrmol; $i++) {
        $rmol[$i] = $allmol[($i+1)];
        my $mnum = $i + 1;
        my $mid = "r" . $mnum;
      }
    }
    if ($npmol > 0) {
      for (my $i = 0; $i < $npmol; $i++) {
        $pmol[$i] = $allmol[($i+1+$nrmol)];
        my $mnum = $i + 1;
        $mylblmol = $pmol[$i];
        my $labels = get_atomlabels();
        my $mid = "p" . $mnum;
      }
    }
    my $l = 1;
    for my $item (@map_list) {
      #print " item: $item\n";
      my @marr1 = split(/=/,$item);
      my $rpart = $marr1[0];
      my @rarr1 = split(/:/,$rpart);
      my $rm = $rarr1[0];
      $rm =~ s/r//g;
      $rm =~ s/^\ +//g;
      my $ra = $rarr1[1];
      my $rl = $l;
      my $ppart = $marr1[1];
      @parr1 = split(/:/,$ppart);
      my $pm = $parr1[0];
      $pm =~ s/p//g;
      $pm =~ s/^\ +//g;
      my $pa = $parr1[1];
      my $pl = $l;
      my $rlbl = $rlabel_list[($rm - 1)];
      if (length($rlbl) > 0) { $rlbl .= ","; }
      $rlbl .= $ra . "-" . $rl;
      $rlabel_list[($rm - 1)] = $rlbl;
      $plbl = $plabel_list[($pm - 1)];
      if (length($plbl) > 0) { $plbl .= ","; }
      $plbl .= $pa . "-" . $pl;
      $plabel_list[($pm - 1)] = $plbl;
      $l++;
    }
    my $newrxn = $header;
    for (my $i = 0; $i < $nrmol; $i++) {
      $mylblmol = $rmol[$i];
      $mylabels = $rlabel_list[$i];
      $rmol[$i] = apply_labels();
      chomp($rmol[$i]);
      $rmol[$i] .= "\n";
      $mylabels = "";
      $mylblmol = "";
      $newrxn .= "\$MOL\n" . $rmol[$i];
    }
    for ($i = 0; $i < $npmol; $i++) {
      $mylblmol = $pmol[$i];
      $mylabels = $plabel_list[$i];
      $pmol[$i] = apply_labels();
      chomp($pmol[$i]);
      $pmol[$i] .= "\n";
      $mylblmol = "";
      $mylabels = "";
      $newrxn .= "\$MOL\n" . $pmol[$i];
    }
    $result = $newrxn;
  }   # end if there are both reactants and products
  return($result);
}

sub valid_rxn() {
  my $testrxn = shift;
  my $result = 0;
  if ((index($testrxn,'$RXN') == 0) && (index($testrxn,'$MOL') > 0) && (index($testrxn,'M  END') > 0)) {
    $result = 1;
  }
  return($result);
}


sub add_molfp() {
  $molfpsum = shift;
  $molfp = shift;
  my $result = $molfp;
  if (length($molfpsum) > 0) {
    my @oldarr1 = split(/;/,$molfpsum);
    my $molfpsum = $oldarr1[0];
    @oldarr = split(/,/,$molfpsum);
    @newarr1 = split(/;/,$molfp);
    $molfp = $newarr1[0];
    @newarr = split(/,/,$molfp);
    if ($#oldarr != $#newarr) { 
      return($result); 
      print STDERR "ERROR in add_molfp(): unequal number of array elements\n";
      #exit;
    }
    my $n = $#oldarr;
    $tmpstr = "";
    for (my $i = 0; $i <= $n; $i++) {
      my $tmpint1 = $oldarr[$i];
      my $tmpint2 = $newarr[$i];
      #$tmpint3 = $tmpint1 | $tmpint2;   // this does not work with large numbers
      $addqstr = "SELECT " . $tmpint1 . " | " . $tmpint2 . " AS bitsum";
      $sth0 = $dbh->prepare($addqstr);
      $sth0->execute();
      while ($ref0 = $sth0->fetchrow_hashref()) {
        $tmpint3 = $ref0->{'bitsum'};
      }
      if (length($tmpstr) > 0) { $tmpstr .= ","; }
      $tmpstr .= $tmpint3;
    }
    $result = $tmpstr;
  }
  return($result);
}

sub filterthroughcmd {
  $input   = shift;
  $cmd     = shift;
  open(FHSUB, "echo \"$input\"|$cmd 2>&1 |");   # stderr must be redirected to stdout
  $res      = "";                               # because the Ghostscript "bbox" device
  while($line = <FHSUB>) {                      # writes to stderr
    $res = $res . $line;
  }
  return $res;
}

sub filterthroughcmd2 {                         # workaround for Windows 
  $input   = shift;
  $cmd     = shift;
  ($tmpfh, $tmpfilename) = tempfile(UNLINK => 1);
  $input =~ s/\\\$/\$/g;
  $input =~ s/\r//g;
  $input =~ s/\n/\r\n/g;
  print $tmpfh "$input\n";
  #open(FHSUB, "type $tmpfilename |$cmd 2>&1 |");   # stderr must be redirected to stdout
  open(FHSUB, "$cmd < $tmpfilename 2>&1 |");   # stderr must be redirected to stdout
  $res      = "";                               # because the Ghostscript "bbox" device
  while($line = <FHSUB>) {                      # writes to stderr
    $res = $res . $line;
  }
  close $tmpfh;
  return $res;
}

sub ltrim() {
  $subline1 = shift;
  $subline1 =~ s/^\ +//g;
  return $subline1;
}

sub rtrim() {
  $subline2 = shift;
  $subline2 =~ s/\ +$//g;
  return $subline2;
}

sub getostype() {
  $os  = "";
  $osresult = 1;
  $os  = uc($ENV{OS});
  if ($os eq "") { $os = uc($ENV{OSTYPE}); }
  if (index($os,"WINDOWS")>=0) {
    $osresult = 2;
  }
  return $osresult;
}