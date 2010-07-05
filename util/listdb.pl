#!/usr/bin/perl
#
# listdb.pl           Norbert Haider, University of Vienna, 2009-2010
#                     norbert.haider@univie.ac.at
#
# This script is part of the MolDB5R package. Last change: 2010-04-29
#
# Example script which lists all data collections
# of an existing MolDB5 database.

use DBI();

$configfile = "../moldb5.conf";

$return     = do $configfile;
if (!defined $return) {
  die("ERROR: cannot read configuration file $configfile!\n");
}	


$user     = $ro_user;    # from configuration file
$password = $ro_password;

$dbh = DBI->connect("DBI:mysql:database=$database;host=$hostname",
                    $user, $password,
                    {'RaiseError' => 1});


# read moldb_meta table
print "reading meta information for MolDB5R database $database\n";
print "================================================================================\n";


$ndb = 0;
$sth0 = $dbh->prepare("SELECT db_id, type, access, name, description, digits, subdirdigits,
usemem, memstatus, trustedIP FROM $metatable ORDER BY db_id");
$sth0->execute();
while ($ref0 = $sth0->fetchrow_hashref()) {
  $dbnum = $ref0->{'db_id'};
  $type = $ref0->{'type'};
  $access = $ref0->{'access'};
  $name = $ref0->{'name'};
  $description = $ref0->{'description'};
  $digits = $ref0->{'digits'};
  $subdirdigits = $ref0->{'subdirdigits'};
  $usemem = $ref0->{'usemem'};
  $memstatus = $ref0->{'memstatus'};
  $trustedIP = $ref0->{'trustedIP'};

  $ndb++;
  $dbprefix = $prefix . "db" . $dbnum . "_";
  $moldatatable = $dbprefix . $moldatasuffix;
  $rxndatatable = $dbprefix . $rxndatasuffix;
  $shortdescr = $description;
  if (length($shortdescr) > 45) {
    $shortdescr = substr($description,0,45) . "..."; 
  }
  print "db_id:                     $dbnum\n";
  print "name of data collection:   $name\n";
  print "type:                      $type (1=SD, 2=RD)\n";
  print "description:               \"$shortdescr\"\n";
  print "access mode:               $access (0=disabled,1=read-only,2=add/update,3=full access)\n";
  print "bitmapfile digits:         $digits\n";
  print "bitmapfile subdir digits:  $subdirdigits\n";
  print "bitmap directory:          $bitmapdir/$dbnum\n";
  print "use memory-based tables:   $usemem (F=only disk-based tables,T=memory-based tables)\n";
  print "memory-based table status: $memstatus (0=not synchronized,3=fully synchronized)\n";
  print "trusted IP addresses:      $trustedIP\n";

  if ($type eq 1) {
    $qstr1 = "SHOW FULL COLUMNS FROM " . $moldatatable;
  } else {
    $qstr1 = "SHOW FULL COLUMNS FROM " . $rxndatatable;
  }
  $sth1 = $dbh->prepare($qstr1);
  $sth1->execute();
  $nfields = 0;
  while ($ref1 = $sth1->fetchrow_hashref()) {
    $field    = $ref1->{'Field'};
    $type     = $ref1->{'Type'};
    $null     = $ref1->{'Null'};
    $default  = $ref1->{'Default'};
    $extra    = $ref1->{'Extra'};
    $comment  = $ref1->{'Comment'};
    $options = "";
    $nfields++;
  }
  $sth1->finish;
  $userfields = $nfields - 2;
  print "user-defined data fields:  $userfields\n";
  print "--------------------------------------------------------------------------------\n";
}
$sth0->finish;

$dbh->disconnect();

print "total number of data collections: $ndb\n";


#============================================================

sub rtrim() {
  $subline2 = shift;
  $subline2 =~ s/\ +$//g;
  return $subline2;
}
