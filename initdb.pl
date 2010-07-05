#!/usr/bin/perl
#
# initdb.pl      Norbert Haider, University of Vienna, 2009-2010
#                norbert.haider@univie.ac.at
#
# This script is part of the MolDB5R package. Last change: 2010-06-15
#
# This script must be run before anything else in order to (re-)create 
# the MolDB5R database and to grant appropriate rights to the proxy users
#
# The "mysql" command must be in your search path.


$os  = "";
$win = 0;
$os = uc($ENV{OS});
if ($os eq "") { $os = uc($ENV{OSTYPE}); }
if (index($os,"WINDOWS")>=0) {
  #print "using Windows workaround\n";
  $win = 1;
}


use DBI();

$configfile="moldb5.conf";      # read all settings from this file
$initfpscript="initfpdef.pl";   # Perl script for fragment dictionary setup

$return     = do $configfile;

if (!defined $return) { die("ERROR: cannot read configuration file $configfile!"); }	
if ( ! -f $initfpscript) { die("ERROR: cannot find auxiliary script $initfpscript!"); }
if ($database eq "") { die("ERROR: no database specified"); }
if ($hostname eq "") { $hostname = "localhost"; }
if ($clientname eq "") { $clientname = "localhost"; }
if ($mysql_admin eq "") { $mysql_admin = "root"; }
if ($metatable eq "") { $metatable = "moldb_meta"; }
if ($rw_user eq "") { die("ERROR: no rw_user specified"); }
if ($rw_password eq "") { die("ERROR: no rw_password specified"); }
if ($ro_user eq "") { die("ERROR: no ro_user specified"); }
if ($ro_password eq "") { die("ERROR: no ro_password specified"); }


print "\nCreating a MolDB5R database with the following configuration:\n";
print "database:    $database\n";
print "hostname:    $hostname\n";
print "clientname:  $clientname\n";
print "mysql_admin: $mysql_admin\n";
print "rw_user:     $rw_user\n";
print "ro_user:     $ro_user\n";
print "drop_db:     $drop_db\n\n";

print "do you want to continue (y/n)? ";
chomp($word = <STDIN>);
if (lc($word) eq 'y') {
    print "OK\n";
} else {
  print "aborting\n";
  exit;
}
$cmd = "";

if ($drop_db eq "y") {
  print "\nATTENTION! An already existing database $database will be erased!\n";
  $cmd ="DROP DATABASE IF EXISTS $database; ";
}
print "\nYou will be prompted for the password of the MySQL administrator ($mysql_admin)\n";
print "If you want to cancel, just hit <Return>\n";

$cmd  .= "CREATE DATABASE IF NOT EXISTS $database; \n";
$cmd .= "GRANT ALL on $database.* TO '" . $rw_user . "'\@'" . $clientname . "' IDENTIFIED BY '" . $rw_password . "';\n";
$cmd .= "GRANT FILE ON *.* TO '" . $rw_user . "'\@'" . $clientname . "';\n";
$cmd .= "GRANT SELECT ON $database.* TO '" . $ro_user . "'\@'" . $clientname ."' IDENTIFIED BY '" . $ro_password ."';\n";

$cmd .= "USE $database; DROP TABLE IF EXISTS $metatable;
CREATE TABLE $metatable (
  db_id INT(11) NOT NULL,
  type TINYINT(4) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1 = substance, 2 = reaction, 3 = combined',
  access TINYINT(4) UNSIGNED NOT NULL DEFAULT '1' COMMENT '0=hidden, 1=read-only, 2=add/update, 3=full access',
  name VARBINARY(255) NOT NULL,
  description TINYTEXT CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  usemem ENUM('T','F') NOT NULL DEFAULT 'F',
  memstatus TINYINT(4) UNSIGNED NOT NULL DEFAULT '0',
  digits TINYINT(4) UNSIGNED NOT NULL DEFAULT '8',
  subdirdigits TINYINT(4) UNSIGNED NOT NULL DEFAULT '4',
  trustedIP VARBINARY(255) NOT NULL,
  PRIMARY KEY  (db_id)
) ENGINE=MyISAM DEFAULT CHARSET=binary COMMENT='meta information about MolDB5R data collections';";

#print "$cmd\n";

if ($win eq 1) {
  open(SQL,">initdb.sql");
  print SQL "$cmd";
  close(SQL);
  $result=`mysql -h $hostname -u $mysql_admin -p < initdb.sql 2>&1`;
} else {
  $result=`echo \"$cmd\" | mysql -h $hostname -u $mysql_admin -p 2>&1`;
}

if (index($result,"ERROR") >= 0) {
  die("creating table $metatable in database $database failed\n  $result");
} else {
  print "setup of database $database OK\n";
}

#print "setting up fragment dictionary in database $database...... ";
$result=`perl $initfpscript 2>&1`;
if (index($result,"ERROR") >= 0) {
  die("setting up fragment dictionary in database $database failed\n  $result");
} else {
  print "setup of fragment dictionary OK\n";
}


#system("echo \"$cmd\" | mysql -h $hostname -u $mysql_admin -p") || die("ERROR: operation failed!");;
#print "\n\n done\n";
