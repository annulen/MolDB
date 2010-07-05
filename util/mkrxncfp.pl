#!/usr/bin/perl
#
# mkrxncfp.pl         Norbert Haider, University of Vienna, 2010
#                     norbert.haider@univie.ac.at
#
# This script is part of the MolDB5R package. Last change: 2010-04-29
#
# Example script which (re-)creates the combined table for dictionary-based 
# fingerprints and hash-based fingerprints.
# The checkmol/matchmol binary must be installed (e.g., in /usr/local/bin).
# ATTENTION: an already existing rxncfp table will be erased!
# Whenever you install a new version of checkmol/matchmol, it is recommended 
# to run this script. The same applies whenever you change the fragment
# dictionary (by editing/adding structures in fp01.sdf and running 
# initfpdef.pl)

use DBI();
$ostype = getostype();
if ($ostype eq 2) { use File::Temp qw/ tempfile tempdir /; }

$configfile = "../moldb5.conf";
$verbose    = 1;  # 0 = silent operation, 
                  # 1 = report each data collection, 
                  # 2 = report each reaction

$return     = do $configfile;
if (!defined $return) {
  die("ERROR: cannot read configuration file $configfile!\n");
}	

$user     = $rw_user;    # from configuration file
$password = $rw_password;

$dbh = DBI->connect("DBI:mysql:database=$database;host=$hostname",
                    $user, $password,
                    {'RaiseError' => 1});

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
  die("ERROR: could not retrieve fingerprint definition from table $fpdeftable");
}

# read moldb_meta table and find out which data collections are to be processed

$ndb = 0;
@db = [""];
$sth0 = $dbh->prepare("SELECT db_id FROM $metatable WHERE type = 2 ORDER BY db_id");
$sth0->execute();
while ($ref0 = $sth0->fetchrow_hashref()) {
  $db_id = $ref0->{'db_id'};
  $ndb++;
  @db[($ndb-1)] = $db_id;
}
$sth0->finish;

for ($i = 0; $i < $ndb; $i++) {
  $dbnum = @db[$i];
  $dbprefix = $prefix . "db" . $dbnum . "_";
  $rxncfptable = $dbprefix . $rxncfpsuffix;
  $rxnstructable = $dbprefix . $rxnstrucsuffix;
  
  if ($verbose > 0) {
    print "processing data collection $dbnum: (re-)creating table $rxncfptable ";
  }
  # drop rxncfp table if it exists already
  $dbh->do("DROP TABLE IF EXISTS $rxncfptable");
  
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

  # first, get the number of rows and chop the whole operation into
  # suitable chunks
  $sth0 = $dbh->prepare("SELECT COUNT(rxn_id) AS rxncount FROM $rxnstructable ");
  $sth0->execute();
  while ($ref0 = $sth0->fetchrow_hashref()) {
    $rxncount = $ref0->{'rxncount'};
  }
  $sth0->finish();
  if ($verbose > 0) {
    print " ($rxncount reactions)\n";
  }
  $nchunks = int( (($rxncount + 999) / 1000) );
  
  for ($j = 0; $j < $nchunks; $j++) {
    $offset = $j * 1000;
    $sth = $dbh->prepare("SELECT rxn_id, struc FROM $rxnstructable LIMIT $offset,1000");
    $sth->execute();
    while ($ref = $sth->fetchrow_hashref()) {
      $rxn_id = $ref->{'rxn_id'};
      $rxn    = $ref->{'struc'};
      $rxn    =~ s/\r\n/\n/g;
      if ($verbose > 1) {
        print ("  $dbnum:$rxn_id\n");
      }
      $entry = $rxn_id;
      insert_rxncfp($rxn);
    }                                    # end while ($ref...
    $sth->finish;
  }  # end "for ($j ...)" loop
}  # for...

$dbh->disconnect();


#============================================================

sub filterthroughcmd {
  my $input   = shift;
  my $cmd     = shift;
  open(FHSUB, "echo \"$input\"|$cmd |");
  $output     = '';
  $res        = "";
  while($line = <FHSUB>) {
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

sub insert_rxncfp() {
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
  $rdfp = ""; $rdfpsum = "";
  $pdfp = ""; $pdfpsum = "";
  $rhfp = ""; $rhfpsum = "";
  $phfp = ""; $phfpsum = "";
  for (my $i = 0; $i < $nrmol; $i++) {
    $molecule = $allmol[($i + 1)];
    if ($ostype eq 2) {
      $molhfp  = filterthroughcmd2($molecule,"$CHECKMOL -aH -");   # must be version 0.4 or higher
    } else {
      $molhfp  = filterthroughcmd($molecule,"$CHECKMOL -aH -");   # must be version 0.4 or higher
    }
    my @tmparr = split(/;/,$molhfp);
    $rhfp = $tmparr[0];
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
    #print " reactant $i: rdfpsum = $rdfpsum \n";
    #print " reactant $i: rhfpsum = $rhfpsum \n";
    $rhfp = "";
    $rdfp = "";
  }  # end for $i ...
  for (my $i = 0; $i < $npmol; $i++) {
    $molecule = $allmol[($i + $nrmol + 1)];
    if ($ostype eq 2) {
      $molhfp  = filterthroughcmd2($molecule,"$CHECKMOL -aH -");   # must be version 0.4 or higher
    } else {
      $molhfp  = filterthroughcmd($molecule,"$CHECKMOL -aH -");   # must be version 0.4 or higher
    }
    my @tmparr = split(/;/,$molhfp);
    $phfp = $tmparr[0];
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
    #print " product $i: pdfpsum = $pdfpsum \n";
    #print " product $i: phfpsum = $phfpsum \n";
    $phfp = "";
    $pdfp = "";
  }  # end for $i ...
  $insertstr = "INSERT INTO " . $rxncfptable . " VALUES (" . $entry . ",'R',";
  $insertstr .= $rdfpsum . "," . $rhfpsum . ",0)";
  #print "$insertstr\n";
  $dbh->do($insertstr);
  $insertstr = "INSERT INTO " . $rxncfptable . " VALUES (" . $entry . ",'P',";
  $insertstr .= $pdfpsum . "," . $phfpsum . ",0)";
  #print "$insertstr\n";
  $dbh->do($insertstr);
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
