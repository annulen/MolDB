#!/usr/bin/perl
#
# mkmolcfp.pl         Norbert Haider, University of Vienna, 2009-2010
#                     norbert.haider@univie.ac.at
#
# This script is part of the MolDB5R package. Last change: 2010-04-29
#
# Example script which (re-)creates the combined table for dictionary-based 
# fingerprints and hash-based fingerprints.
# The checkmol/matchmol binary must be installed (e.g., in /usr/local/bin).
# ATTENTION: an already existing molcfp table will be erased!
# Whenever you install a new version of checkmol/matchmol, it is recommended 
# to run this script. The same applies whenever you change the fragment
# dictionary (by editing/adding structures in fp01.sdf and running 
# initfpdef.pl)

use DBI();
$ostype = getostype();
if ($ostype eq 2) { use File::Temp qw/ tempfile tempdir /; }

$configfile = "../moldb5.conf";
$verbose    = 0;  # 0 = silent operation, 
                  # 1 = report each data collection, 
                  # 2 = report each molecule

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
$sth0 = $dbh->prepare("SELECT db_id FROM $metatable WHERE type = 1 ORDER BY db_id");
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
  $molcfptable = $dbprefix . $molcfpsuffix;
  $molstructable = $dbprefix . $molstrucsuffix;
  
  if ($verbose > 0) {
    print "processing data collection $dbnum: (re-)creating table $molcfptable ";
  }
  # drop molcfp table if it exists already
  $dbh->do("DROP TABLE IF EXISTS $molcfptable");
  
  # create a new molcfp table
  $createcmd="CREATE TABLE IF NOT EXISTS $molcfptable (
  mol_id INT(11) NOT NULL DEFAULT '0',
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
  n_h1bits SMALLINT NOT NULL, PRIMARY KEY (mol_id) 
  ) ENGINE=MyISAM COMMENT='Combined dictionary-based and hash-based fingerprints'";
  $dbh->do($createcmd);

  # first, get the number of rows and chop the whole operation into
  # suitable chunks
  $sth0 = $dbh->prepare("SELECT COUNT(mol_id) AS molcount FROM $molstructable ");
  $sth0->execute();
  while ($ref0 = $sth0->fetchrow_hashref()) {
    $molcount = $ref0->{'molcount'};
  }
  $sth0->finish();
  if ($verbose > 0) {
    print " ($molcount molecules)\n";
  }
  $nchunks = int( (($molcount + 999) / 1000) );
  
  for ($j = 0; $j < $nchunks; $j++) {
    $offset = $j * 1000;
    $sth = $dbh->prepare("SELECT mol_id, struc FROM $molstructable LIMIT $offset,1000");
    $sth->execute();
    while ($ref = $sth->fetchrow_hashref()) {
      $mol_id = $ref->{'mol_id'};
      $mol    = $ref->{'struc'};
      $moldfp = "";
      for ($k = 0; $k < $n_dict; $k++) {
        $dict = @fpstruc[$k];
        $cand = $mol . "\n" . '$$$$' ."\n" . $dict;
        $cand =~ s/\$/\\\$/g;
        if ($ostype eq 2) { 
          $dfpstr = filterthroughcmd2($cand,"$MATCHMOL -F -");
        } else {
          $dfpstr = filterthroughcmd($cand,"$MATCHMOL -F -");
        }
        chomp($dfpstr);
        if ($k > 0) { $moldfp .= ","; }
        $moldfp .= " " . $dfpstr;
      }
      if ($ostype eq 2) {
        $molhfp  = filterthroughcmd2($mol,"$CHECKMOL -H -");   # must be version 0.4 or higher
      } else {
        $molhfp  = filterthroughcmd($mol,"$CHECKMOL -H -");   # must be version 0.4 or higher
      }
      chomp($molhfp);
      $molhfp =~ s/\;/\,/g;
      if ($verbose > 1) {
        print ("  $dbnum:$mol_id $moldfp,$molhfp\n");
      }
      $dbh->do("INSERT INTO $molcfptable VALUES ($mol_id, $moldfp, $molhfp)");
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
