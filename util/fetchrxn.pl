#!/usr/bin/perl
#
# fetchrxn.pl   Norbert Haider, University of Vienna, 2010
#               norbert.haider@univie.ac.at
#
# This script is part of the MolDB5R package. Last change: 2010-04-29
#
# Example script which retrieves one reaction structure (as an rxnfile)
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
$rxn_id   = getnum($inputrec[1]);


$user     = $ro_user;    # from configuration file
$password = $ro_password;

$dbh = DBI->connect("DBI:mysql:database=$database;host=$hostname",
                    $user, $password,
                    {'RaiseError' => 1}
                    ) || die ("database connection failed: $DBI::errstr");

# read moldb_meta table

$ndb = 0;
$sth0 = $dbh->prepare("SELECT db_id FROM $metatable WHERE (type = 2) AND (db_id = $db_id)");
$sth0->execute();
while ($ref0 = $sth0->fetchrow_hashref()) {
  $dbnum     = $ref0->{'db_id'};
  $ndb++;
}
$sth0->finish;

if ($ndb < 1) {
  print "no such reaction data collection ($db_id)\n";
  $dbh->disconnect();
  exit;
}

$dbprefix = $prefix . "db" . $dbnum . "_";
$rxnstructable = $dbprefix . $rxnstrucsuffix;

$rxn  = "";
$nrxn = 0;

$sth = $dbh->prepare("SELECT rxn_id, struc FROM $rxnstructable WHERE rxn_id = $rxn_id");
$sth->execute();
while ($ref = $sth->fetchrow_hashref()) {
  $rxn_id   = $ref->{'rxn_id'};
  $rxn      = $ref->{'struc'};
  print "$rxn\n";
  $nrxn++;
}                 # end while ($ref...
$sth->finish;

if ($nrxn eq 0) {
  print "no such reaction ($db_id:$rxn_id)\n";
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
  print "Usage: fetchrxn.pl <n:m> (where <n> is the data collection id number\n";
  print "       and <m> is the reaction id number, e.g. fetchrxn.pl 2:345)\n";
}