#!/usr/bin/perl
#
# mkmolstat.pl  Norbert Haider, University of Vienna, 2009-2010
#               norbert.haider@univie.ac.at
#
# This script is part of the MolDB5R package. Last change: 2010-04-29
#
# Example script which (re-)generates the molstat MySQL table
# within the MolDB5R database: molstat contains (e.g.) the number
# of atoms, bonds, rings, etc. for each molecule;
# field identification codes are listed and described in checkmol.pas;
# the checkmol binary must be installed (e.g., in /usr/local/bin)
# ATTENTION: an already existing molstat table will be erased!
# Whenever you install a new version of checkmol, it is recommended 
# to run this script.

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
  $molstattable  = $dbprefix . $molstatsuffix;
  $molstructable = $dbprefix . $molstrucsuffix;
  
  if ($verbose > 0) {
    print "processing data collection $dbnum: (re-)creating table $molstattable ";
  }
  # drop molstat table if it exists already
  $dbh->do("DROP TABLE IF EXISTS $molstattable");

  # create a new molstat table
  # use field listing from checkmol > v0.3l, else use static list
  
  $createcmd = "CREATE TABLE IF NOT EXISTS $molstattable (
    mol_id int(11) NOT NULL DEFAULT '0', \n";
  open (MSDEF, "$CHECKMOL -l |");
  $nfields = 0;
  while ($line = <MSDEF>) {
    chomp($line);
    @valid = split (/:/, $line);  # ignore everything behind the colon
    $line  = $valid[0];
    if (index($line,'n_') == 0) {
      $nfields ++;
      $createcmd = $createcmd . "  $line" . " SMALLINT(6) NOT NULL DEFAULT '0',\n";
    }
  }
  $createcmd = $createcmd . "  PRIMARY KEY  (mol_id)
  ) ENGINE=MyISAM COMMENT='Molecular statistics';";
  
  # check if we have an older version of checkmol; if yes,
  # revert to fixed field definitions
  if (($nfields < 50) || ($use_fixed_fields == 1)) {
    print "reverting to fixed molstat fields\n";
    $createcmd="CREATE TABLE IF NOT EXISTS $molstattable (
    mol_id int(11) NOT NULL DEFAULT '0',
    n_atoms smallint(6) NOT NULL DEFAULT '0',
    n_bonds smallint(6) NOT NULL DEFAULT '0',
    n_rings smallint(6) NOT NULL DEFAULT '0',
    n_QA smallint(6) NOT NULL DEFAULT '0',
    n_QB smallint(6) NOT NULL DEFAULT '0',
    n_chg smallint(6) NOT NULL DEFAULT '0',
    n_C1 smallint(6) NOT NULL DEFAULT '0',
    n_C2 smallint(6) NOT NULL DEFAULT '0',
    n_C smallint(6) NOT NULL DEFAULT '0',
    n_CHB1p smallint(6) NOT NULL DEFAULT '0',
    n_CHB2p smallint(6) NOT NULL DEFAULT '0',
    n_CHB3p smallint(6) NOT NULL DEFAULT '0',
    n_CHB4 smallint(6) NOT NULL DEFAULT '0',
    n_O2 smallint(6) NOT NULL DEFAULT '0',
    n_O3 smallint(6) NOT NULL DEFAULT '0',
    n_N1 smallint(6) NOT NULL DEFAULT '0',
    n_N2 smallint(6) NOT NULL DEFAULT '0',
    n_N3 smallint(6) NOT NULL DEFAULT '0',
    n_S smallint(6) NOT NULL DEFAULT '0',
    n_SeTe smallint(6) NOT NULL DEFAULT '0',
    n_F smallint(6) NOT NULL DEFAULT '0',
    n_Cl smallint(6) NOT NULL DEFAULT '0',
    n_Br smallint(6) NOT NULL DEFAULT '0',
    n_I smallint(6) NOT NULL DEFAULT '0',
    n_P smallint(6) NOT NULL DEFAULT '0',
    n_B smallint(6) NOT NULL DEFAULT '0',
    n_Met smallint(6) NOT NULL DEFAULT '0',
    n_X smallint(6) NOT NULL DEFAULT '0',
    n_b1 smallint(6) NOT NULL DEFAULT '0',
    n_b2 smallint(6) NOT NULL DEFAULT '0',
    n_b3 smallint(6) NOT NULL DEFAULT '0',
    n_bar smallint(6) NOT NULL DEFAULT '0',
    n_C1O smallint(6) NOT NULL DEFAULT '0',
    n_C2O smallint(6) NOT NULL DEFAULT '0',
    n_CN smallint(6) NOT NULL DEFAULT '0',
    n_XY smallint(6) NOT NULL DEFAULT '0',
    n_r3 smallint(6) NOT NULL DEFAULT '0',
    n_r4 smallint(6) NOT NULL DEFAULT '0',
    n_r5 smallint(6) NOT NULL DEFAULT '0',
    n_r6 smallint(6) NOT NULL DEFAULT '0',
    n_r7 smallint(6) NOT NULL DEFAULT '0',
    n_r8 smallint(6) NOT NULL DEFAULT '0',
    n_r9 smallint(6) NOT NULL DEFAULT '0',
    n_r10 smallint(6) NOT NULL DEFAULT '0',
    n_r11 smallint(6) NOT NULL DEFAULT '0',
    n_r12 smallint(6) NOT NULL DEFAULT '0',
    n_r13p smallint(6) NOT NULL DEFAULT '0',
    n_rN smallint(6) NOT NULL DEFAULT '0',
    n_rN1 smallint(6) NOT NULL DEFAULT '0',
    n_rN2 smallint(6) NOT NULL DEFAULT '0',
    n_rN3p smallint(6) NOT NULL DEFAULT '0',
    n_rO smallint(6) NOT NULL DEFAULT '0',
    n_rO1 smallint(6) NOT NULL DEFAULT '0',
    n_rO2p smallint(6) NOT NULL DEFAULT '0',
    n_rS smallint(6) NOT NULL DEFAULT '0',
    n_rX smallint(6) NOT NULL DEFAULT '0',
    n_rar smallint(6) NOT NULL DEFAULT '0',
    PRIMARY KEY  (mol_id)
    ) ENGINE=MyISAM COMMENT='Molecular statistics';";
  }
  $dbh->do($createcmd);

  # read all molecules from molstructable and pipe the MDL molfiles
  # through checkmol to get the molecular statistics
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
      $mol_id   = $ref->{'mol_id'};
      $mol      = $ref->{'struc'};
      if ($ostype eq 2) {
        $molstat  = filterthroughcmd2($mol,"$CHECKMOL -aX -");   # must be version 0.4 or higher
      } else {
        $molstat  = filterthroughcmd($mol,"$CHECKMOL -aX -");   # must be version 0.4 or higher
      }
      $molstat  =~ s/\n//g;
      if ($verbose > 1) {
        print ("  $dbnum:$mol_id $molstat\n");
      }
      if ((index($molstat,"unknown") < 0) && (index($molstat,"invalid") < 0 )) {
        $dbh->do("INSERT INTO $molstattable VALUES ( $mol_id, $molstat ) ");
      }
    }                 # end while ($ref...
    $sth->finish;
  }                   # end "for ($j.." loop

}  # end "for ($i.." loop

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
