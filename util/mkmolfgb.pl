#!/usr/bin/perl
#
# mkmolfgb.pl   Norbert Haider, University of Vienna, 2009-2010
#               norbert.haider@univie.ac.at
#
# This script is part of the MolDB5R package. Last change: 2010-04-29
#
# Example script which (re-)generates the molfgb MySQL table
# within the MolDB5R database: molfgb contains 32-bit integer 
# numbers which together represent a bitstring describing the
# functional groups contained in each molecule;
# the meaning of each bit position is described in checkmol.pas;
# the checkmol binary (version 0.4 or higher) must be installed 
# (e.g., in /usr/local/bin)
# ATTENTION: an already existing molfgb table will be erased!
# Whenever you install a new version of checkmol, it is
# recommended to run this script.

use DBI();
$ostype = getostype();
if ($ostype eq 2) { use File::Temp qw/ tempfile tempdir /; }

$use_fixed_fields = 0;   # 0 or 1, should be 1 for older versions of checkmol

$configfile = "../moldb5.conf";
$verbose    = 0;  # 0 = silent operation, 
                  # 1 = report each data collection, 
                  # 2 = report each molecule

$return     = do $configfile;
if (!defined $return) {
  die ("ERROR: cannot read configuration file $configfile!\n");
}	

$user     = $rw_user;    # from configuration file
$password = $rw_password;

$dbh = DBI->connect("DBI:mysql:database=$database;host=$hostname",
                    $user, $password,
                    {'RaiseError' => 1});

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
  $molfgbtable  = $dbprefix . $molfgbsuffix;
  $molstructable = $dbprefix . $molstrucsuffix;
  
  if ($verbose > 0) {
    print "processing data collection $dbnum: (re-)creating table $molfgbtable ";
  }
  # drop molfgb table if it exists already
  $dbh->do("DROP TABLE IF EXISTS $molfgbtable");

  # create a new molfgb table

  # create a new molfgb table
  $createcmd="CREATE TABLE IF NOT EXISTS $molfgbtable (mol_id INT(11) NOT NULL DEFAULT '0', 
  fg01 INT(11) UNSIGNED NOT NULL,
  fg02 INT(11) UNSIGNED NOT NULL,
  fg03 INT(11) UNSIGNED NOT NULL,
  fg04 INT(11) UNSIGNED NOT NULL,
  fg05 INT(11) UNSIGNED NOT NULL,
  fg06 INT(11) UNSIGNED NOT NULL,
  fg07 INT(11) UNSIGNED NOT NULL,
  fg08 INT(11) UNSIGNED NOT NULL,
  n_1bits SMALLINT NOT NULL,
  PRIMARY KEY mol_id (mol_id)) ENGINE=MyISAM COMMENT='Functional group patterns'";
  $dbh->do($createcmd);

  # read all molecules from molstructable and pipe the MDL molfiles
  # through checkmol to get the functional-group decimal codes
  #
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
      if ($ostype eq 2) {
        $molfgb  = filterthroughcmd2($mol,"$CHECKMOL -b -");   # must be version 0.4 or higher
      } else {
        $molfgb  = filterthroughcmd($mol,"$CHECKMOL -b -");   # must be version 0.4 or higher
      }
      chomp($molfgb);
      $molfgb =~ s/\;/\,/g;
      if ($verbose > 1) {
        print ("  $dbnum:$mol_id $molfgb\n");
      }
      if ((index($molfgb,"unknown") < 0) && (index($molfgb,"invalid") < 0 )) {
        $dbh->do("INSERT INTO $molfgbtable VALUES ($mol_id, $molfgb )");
      }
    }                                    # end while ($ref...
    $sth->finish;
  }  # end "for ($j" loop

}   # end "for ($i.." loop

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
