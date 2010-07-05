#!/usr/bin/perl
#
# setmem.pl           Norbert Haider, University of Vienna, 2009-2010
#                     norbert.haider@univie.ac.at
#
# This script is part of the MolDB5R package. Last change: 2010-04-29
#
# Example script which enables/disables use of memory-based
# MySQL database tables for individual MolDB5 data collections
# in order to speed up substructure searches. Enabling memory-
# based tables is recommended only for read-only data collections.

use DBI();

$configfile = "../moldb5.conf";

$return     = do $configfile;
if (!defined $return) {
  die("ERROR: cannot read configuration file $configfile!\n");
}	

if ($#ARGV < 0) {
  show_usage();
  exit;
}

$userinput = $ARGV[0];

if ((index($userinput,'=') < 1) || ((index($userinput,'T') < 2) && (index($userinput,'F') < 2))) {
  show_usage();
  exit;
}

@inputrec = split (/=/, $userinput);
$db_id    = getnum($inputrec[0]);
$memflag  = $inputrec[1];
if ($memflag eq "T") {
  $setusemem = "T"; 
} else {
  if ($memflag eq "F") {
    $setusemem = "F";
  } else {
    show_usage();
    exit;
  }
}

$user     = $rw_user;    # from configuration file
$password = $rw_password;

$dbh = DBI->connect("DBI:mysql:database=$database;host=$hostname",
                    $user, $password,
                    {'RaiseError' => 1});

# read moldb_meta table

$ndb = 0;
$sth0 = $dbh->prepare("SELECT db_id, access, usemem, memstatus FROM $metatable WHERE (type = 1) AND (db_id = $db_id)");
$sth0->execute();
while ($ref0 = $sth0->fetchrow_hashref()) {
  $dbnum     = $ref0->{'db_id'};
  $access    = $ref0->{'access'};
  $usemem    = $ref0->{'usemem'};
  $memstatus = $ref0->{'memstatus'};
  $ndb++;
}
$sth0->finish;

if ($ndb < 1) {
  print "no such structure data collection ($db_id)\n";
  exit;
}

if ($setusemem eq $usemem) {
  print "setting of 'usemem' for data collection $dbnum is already '" . $usemem . "'\n"	;
  exit;
} else {
  $updstr = "UPDATE $metatable SET usemem = '" . $setusemem . "' WHERE db_id = $dbnum";
  $dbh->do($updstr);	
  $updstr = "UPDATE $metatable SET memstatus = '0' WHERE db_id = $dbnum";
  $dbh->do($updstr);	
}

if ($setusemem eq "T") {
  print "OK. Do not forget to run 'cp2mem.pl' in order to synchronize memory tables!\n";
  if ($access > 1) {
    print "Attention: using memory-based tables is NOT recommended for read/write\n";
    print "data collections!\n";
  }
} else {
  print "OK.\n";
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
  print "Usage: setmem.pl n=[T|F]\n";
  print " e.g., setmem.pl 1=T \n";
  print "This utility can be used to switch the 'usemem' flag in the\n";
  print "moldb_meta table either to 'T' or 'F'. With 'usemem' set to 'T',\n";
  print "this data collection can use memory-based molstat and molcfp\n";
  print "tables instead of disk-based tables. This will permit faster\n";
  print "substructure searches, but it is recommended only for data collections\n";
  print "with 'read-only' access mode. After enabling memory-based tables,\n";
  print "you have to run the script 'cp2mem.pl' in order to synchronize the\n";
  print "memory-based tables to the disk-based ones.\n";
}