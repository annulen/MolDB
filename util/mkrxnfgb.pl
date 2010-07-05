#!/usr/bin/perl
#
# mkrxnfgb.pl         Norbert Haider, University of Vienna, 2010
#                     norbert.haider@univie.ac.at
#
# This script is part of the MolDB5R package. Last change: 2010-04-29
#
# Example script which (re-)generates the rxnfgb MySQL table
# within the MolDB5R database: rxnfgb contains 32-bit integer 
# numbers which together represent a bitstring describing the
# functional groups contained in each molecule (one bit sum for
# all reactants + one bit sum for all products);
# the meaning of each bit position is described in checkmol.pas;
# the checkmol binary (version 0.4 or higher) must be installed 
# (e.g., in /usr/local/bin)
# ATTENTION: an already existing rxnfgb table will be erased!
# Whenever you install a new version of checkmol, it is
# recommended to run this script.

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
  $rxnfgbtable = $dbprefix . $rxnfgbsuffix;
  $rxnstructable = $dbprefix . $rxnstrucsuffix;
  
  if ($verbose > 0) {
    print "processing data collection $dbnum: (re-)creating table $rxnfgbtable ";
  }
  # drop rxnfgb table if it exists already
  $dbh->do("DROP TABLE IF EXISTS $rxnfgbtable");
  
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
      insert_rxnfgb($rxn);
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

sub insert_rxnfgb() {
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
  for (my $i = 0; $i < $nrmol; $i++) {
    $molecule = $allmol[($i + 1)];
    if ($ostype eq 2) {
      $molfgb  = filterthroughcmd2($molecule,"$CHECKMOL -ab -");   # must be version 0.4 or higher
    } else {
      $molfgb  = filterthroughcmd($molecule,"$CHECKMOL -ab -");   # must be version 0.4 or higher
    }
    my @tmparr = split(/;/,$molfgb);
    $rfgb = $tmparr[0];
    $rfgbsum = add_molfp($rfgbsum,$rfgb);
    #print " reactant $i: rfgbsum = $rfgbsum \n";
    $pfgb = "";
  }  # end for $i ...
  for (my $i = 0; $i < $npmol; $i++) {
    $molecule = $allmol[($i + $nrmol + 1)];
    if ($ostype eq 2) {
      $molfgb  = filterthroughcmd2($molecule,"$CHECKMOL -ab -");   # must be version 0.4 or higher
    } else {
      $molfgb  = filterthroughcmd($molecule,"$CHECKMOL -ab -");   # must be version 0.4 or higher
    }
    my @tmparr = split(/;/,$molfgb);
    $pfgb = $tmparr[0];
    $pfgbsum = add_molfp($pfgbsum,$pfgb);
    #print " product $i: pfgbsum = $pfgbsum \n";
    $pfgb = "";
  }  # end for $i ...
  $insertstr = "INSERT INTO " . $rxnfgbtable . " VALUES (" . $entry . ",'R',";
  $insertstr .= $rfgbsum . ",0)";
  #print "$insertstr\n";
  $dbh->do($insertstr);
  $insertstr = "INSERT INTO " . $rxnfgbtable . " VALUES (" . $entry . ",'P',";
  $insertstr .= $pfgbsum . ",0)";
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
