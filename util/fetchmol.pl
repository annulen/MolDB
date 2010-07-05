#!/usr/bin/perl
#
# fetchmol.pl   Norbert Haider, University of Vienna, 2010
#               norbert.haider@univie.ac.at
#
# This script is part of the MolDB5R package. Last change: 2010-04-29
#
# Example script which retrieves one molecule structure (as a molfile)
# from the MolDB5R database. The data collection number (dbid) and the
# record number (id) must be specified as a command-line argument with
# a colon as separator.


if ($#ARGV < 0) {
  show_usage();
  exit;
}


use DBI();

$configfile = "../moldb5.conf";

$return     = do $configfile;
if (!defined $return) {
  die ("ERROR: cannot read configuration file $configfile!\n");
}	

$userinput = $ARGV[0];

if (index($userinput,':') < 1) {
  show_usage();
  exit;
}

@inputrec = split (/:/, $userinput);
$db_id    = getnum($inputrec[0]);
$mol_id   = getnum($inputrec[1]);


$user     = $ro_user;    # from configuration file
$password = $ro_password;

$dbh = DBI->connect("DBI:mysql:database=$database;host=$hostname",
                    $user, $password,
                    {'RaiseError' => 1}
                    ) || die ("database connection failed: $DBI::errstr");

# read moldb_meta table

$ndb = 0;
$sth0 = $dbh->prepare("SELECT db_id FROM $metatable WHERE (type = 1) AND (db_id = $db_id)");
$sth0->execute();
while ($ref0 = $sth0->fetchrow_hashref()) {
  $dbnum     = $ref0->{'db_id'};
  $ndb++;
}
$sth0->finish;

if ($ndb < 1) {
  print "no such structure data collection ($db_id)\n";
  $dbh->disconnect();
  exit;
}

$dbprefix = $prefix . "db" . $dbnum . "_";
$molstructable = $dbprefix . $molstrucsuffix;

$mol  = "";
$nmol = 0;

$sth = $dbh->prepare("SELECT mol_id, struc FROM $molstructable WHERE mol_id = $mol_id");
$sth->execute();
while ($ref = $sth->fetchrow_hashref()) {
  $mol_id   = $ref->{'mol_id'};
  $mol      = $ref->{'struc'};
  print "$mol\n";
  $nmol++;
}                 # end while ($ref...
$sth->finish;

if ($nmol eq 0) {
  print "no such molecule ($db_id:$mol_id)\n";
}

$dbh->disconnect();

#============================================================

sub getnum() {
  $str = shift;
  $numstr = '';
  for ($nn = 0; $nn < length($str); $nn++ ) {
    if (index('0123456789',substr($str,$nn,1)) >= 0) {
      $numstr = $numstr . substr($str,$nn,1);
    }
  }
  return $numstr;
}

sub show_usage() {
  print "Usage: fetchmol.pl <n:m> (where <n> is the data collection id number\n";
  print "       and <m> is the molecule id number, e.g. fetchmol.pl 2:345)\n";
}