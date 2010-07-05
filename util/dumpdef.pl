#!/usr/bin/perl
#
# dumpdef.pl          Norbert Haider, University of Vienna, 2009-2010
#                     norbert.haider@univie.ac.at
#
# This script is part of the MolDB5R package. Last change: 2010-04-23
#
# Example script which dumps the sdf2moldb.def/rdf2moldb.def definition 
# file(s) from the content of an existing MolDB5 database.

use DBI();

$configfile = "../moldb5.conf";
$verbose    = 1;  # 0 = silent operation, 
                  # 1 = report each data collection, 
                  # 2 = report each molecule

$return     = do $configfile;
if (!defined $return) {
  die("ERROR: cannot read configuration file $configfile!\n");
}	


$user     = $ro_user;    # from configuration file
$password = $ro_password;

$dbh = DBI->connect("DBI:mysql:database=$database;host=$hostname",
                    $user, $password,
                    {'RaiseError' => 1});


# read moldb_meta table and find out which data collections are to be processed
if ($verbose > 0) {
  print "reading data structures from MolDB5 database and writing definition files\n";
}

$ndb = 0;
$sth0 = $dbh->prepare("SELECT db_id, access, name, description, digits, subdirdigits FROM $metatable WHERE type = 1 ORDER BY db_id");
$sth0->execute();
while ($ref0 = $sth0->fetchrow_hashref()) {
  $dbnum = $ref0->{'db_id'};
  $access = $ref0->{'access'};
  $name = $ref0->{'name'};
  $description = $ref0->{'description'};
  $digits = $ref0->{'digits'};
  $subdirdigits = $ref0->{'subdirdigits'};
  $ndb++;
  $dbprefix = $prefix . "db" . $dbnum . "_";
  $moldatatable = $dbprefix . $moldatasuffix;
  if ($verbose > 0) {
    print " sdf2moldb.def.$dbnum\n";
  }
  open(DEF,">sdf2moldb.def.$dbnum");
  print(DEF "# sdf2moldb.def file created from structure of existing database\n#\n");
  print(DEF "sdfilename=export-db$dbnum.sdf\n#\n");
  print(DEF "# database definitions:\n");
  print(DEF "db_id=$dbnum\n");
  print(DEF "db_type=1\n");
  print(DEF "db_name=\"$name\"\n");
  print(DEF "db_description=\"$description\"\n");
  print(DEF "db_access=$access\n");
  print(DEF "bitmapfile_digits=$digits\n");
  print(DEF "bitmapfile_subdirdigits=$subdirdigits\n");
  print(DEF "#\n");
  print(DEF "# Format of definition lines:\n");
  print(DEF "# SDF_field_name:MySQL_field_name:MySQL_field_type:HTML_field_name:HTML_format:::comment\n");
  print(DEF "#\n");

  $sth1 = $dbh->prepare("SHOW FULL COLUMNS FROM $moldatatable");
  $sth1->execute();
  while ($ref1 = $sth1->fetchrow_hashref()) {
    $field    = $ref1->{'Field'};
    $type     = $ref1->{'Type'};
    $null     = $ref1->{'Null'};
    $default  = $ref1->{'Default'};
    $extra    = $ref1->{'Extra'};
    $comment  = $ref1->{'Comment'};
    $options = "";
    if ($null eq "NO") { $options = "NOT NULL "; } 
    if (length($default) > 0) { $options .= "DEFAULT '" . $default . "'"; }
    if (length($extra) > 0) { $options .= "$extra"; }
    $htmlfield  = "";
    $htmlformat = "";
    $sdffield   = "";
    if ((length($comment) > 0) && (index($comment,'>>>>') eq 0)) { 
      $comment =~ s/>>>>//g;
      @a = split("<",$comment);
      $htmlfield = $a[0];
      $htmlformat = $a[1];
      $sdffield = $a[2];
      $searchmode = $a[3];
      $reserved = $a[4];
    }
    if ($htmlfield eq "") { $htmlfield = $field; }
    if ($sdffield eq "") { $sdffield = $field; }
    if (length($options) > 0) { $type .= " $options"; }
    $type = rtrim($type);
    if (!($field eq "mol_id")) {
      print(DEF "$sdffield:$field:$type:$htmlfield:$htmlformat:$searchmode:$reserved:\n");
    }
  }
  $sth1->finish;
 
  close(DEF);
}
$sth0->finish;

# and now the same for reaction data collections...

$sth0 = $dbh->prepare("SELECT db_id, access, name, description FROM $metatable WHERE type = 2 ORDER BY db_id");
$sth0->execute();
while ($ref0 = $sth0->fetchrow_hashref()) {
  $dbnum = $ref0->{'db_id'};
  $access = $ref0->{'access'};
  $name = $ref0->{'name'};
  $description = $ref0->{'description'};
  $ndb++;
  $dbprefix = $prefix . "db" . $dbnum . "_";
  $rxndatatable = $dbprefix . $rxndatasuffix;
  if ($verbose > 0) {
    print " rdf2moldb.def.$dbnum\n";
  }
  open(DEF,">rdf2moldb.def.$dbnum");
  print(DEF "# rdf2moldb.def file created from structure of existing database\n#\n");
  print(DEF "rdfilename=export-db$dbnum.rdf\n#\n");
  print(DEF "# database definitions:\n");
  print(DEF "db_id=$dbnum\n");
  print(DEF "db_type=2\n");
  print(DEF "db_name=\"$name\"\n");
  print(DEF "db_description=\"$description\"\n");
  print(DEF "db_access=$access\n");
  print(DEF "#\n");
  print(DEF "# Format of definition lines:\n");
  print(DEF "# RDF_field_name:MySQL_field_name:MySQL_field_type:HTML_field_name:HTML_format:::comment\n");
  print(DEF "#\n");

  $sth1 = $dbh->prepare("SHOW FULL COLUMNS FROM $rxndatatable");
  $sth1->execute();
  while ($ref1 = $sth1->fetchrow_hashref()) {
    $field    = $ref1->{'Field'};
    $type     = $ref1->{'Type'};
    $null     = $ref1->{'Null'};
    $default  = $ref1->{'Default'};
    $extra    = $ref1->{'Extra'};
    $comment  = $ref1->{'Comment'};
    $options = "";
    if ($null eq "NO") { $options = "NOT NULL "; } 
    if (length($default) > 0) { $options .= "DEFAULT '" . $default . "'"; }
    if (length($extra) > 0) { $options .= "$extra"; }
    $htmlfield  = "";
    $htmlformat = "";
    $rdffield   = "";
    if ((length($comment) > 0) && (index($comment,'>>>>') eq 0)) { 
      $comment =~ s/>>>>//g;
      @a = split("<",$comment);
      $htmlfield = $a[0];
      $htmlformat = $a[1];
      $rdffield = $a[2];
      $searchmode = $a[3];
      $reserved = $a[4];
    }
    if ($htmlfield eq "") { $htmlfield = $field; }
    if ($rdffield eq "") { $rdffield = $field; }
    # translate : into ! for rdf field names
    $rdffield =~ s/\:/\!/g;
    if (length($options) > 0) { $type .= " $options"; }
    $type = rtrim($type);
    if (!($field eq "rxn_id")) {
      print(DEF "$rdffield:$field:$type:$htmlfield:$htmlformat:$searchmode:$reserved:\n");
    }
  }
  $sth1->finish;
 
  close(DEF);
}
$sth0->finish;



$dbh->disconnect();

if ($verbose > 0) {
  print "if you want to export your structures+data from the MolDB5 database,\n";
  print "rename the appropriate definition file of your data collection\n";
  print "(e.g., \"sdf2moldb.def.1\") into \"sdf2moldb.def\" and run the Perl\n";
  print "script moldb2sdf.pl\n";

}


#============================================================

sub rtrim() {
  $subline2 = shift;
  $subline2 =~ s/\ +$//g;
  return $subline2;
}
