#!/usr/bin/perl
#
# moldb2sdf.pl   Norbert Haider, University of Vienna, 2009
#                norbert.haider@univie.ac.at
#
# This script is part of the MolDB5 package. Last change: 2009-02-26
#
# This script dumps the content of the moldata and molstruc tables
# of a moldb database to standard output in SDF format. It uses the
# same definition file "sdf2moldb.def" which had been used for data
# import (see "sdfcheck.pl" and "sdf2moldb.pl"). This definition file
# can also be generated with the script "dumpdef.pl".
#
# A record number (mol_id) or range of numbers can be specified as the 
# command line argument, e.g. "moldb2sdf.pl 24" or "moldb2sdf.pl 1200-2400".
# Usually, the output of this script is redirected into a file by using
# the ">" operator, e.g. "perl moldb2sdf.pl > mydata.sdf"

$userinput = $ARGV[0];
$numinput = getnum($userinput);

@numrec = split (/-/, $numinput);
$firstmol = $numrec[0];
$lastmol  = $numrec[1];

if ($lastmol == '') {
  if (index($numinput,'-') >= 0) {
    $lastmol = 0;
  } else {
    $lastmol = $firstmol;
  }
}

if (($firstmol == '') || ($firstmol < 1)) {
  $firstmol = 1;
}

if (($lastmol > 0) && ($lastmol < $firstmol)) {
  $lastmol = $firstmol;
}

$wherestr = '';
if (($firstmol > 1) || ($lastmol > 0)) {
  if (($lastmol > 0) || ($lastmol != $firstmol)) {
    if ($lastmol == 0) {
      $wherestr = " WHERE mol_id >= " . $firstmol;
    } else {
      if ($lastmol != $firstmol) {
        $wherestr = " WHERE mol_id >= " . $firstmol . " AND mol_id <= " . $lastmol;
      } else {
        $wherestr = " WHERE mol_id = " . $firstmol;
      }
    }
  } else {
    $wherestr = " WHERE mol_id = " . $firstmol;
  }
}

if (($firstmol == 1) && ($lastmol <= $firstmol)) {
  $wherestr = '';
}


use DBI();

$configfile = "../moldb5.conf";

$return     = do $configfile;
if (!defined $return) {
  die("ERROR: cannot read configuration file $configfile!\n");
}	

$user     = $ro_user;    # from configuration file
$password = $ro_password;

$deffile = "sdf2moldb.def";
open (DEF, "<$deffile") || die("ERROR: cannot open definition file $deffile!");
$nfields = 0;
while ($line = <DEF>) {
  chomp($line);
  @valid = split (/#/, $line);  # ignore everything behind the first pound sign
  $line  = $valid[0];
  $line  = ltrim($line);
  $line  = rtrim($line);
  if ((index($line,'sdfilename') >= 0) && (index($line,'=') >= 9)) {
    @defrec = split (/=/, $line);
    $sdfile = $defrec[1];
    $sdfile = ltrim($sdfile);
  }
  if ((index($line,'db_id') >= 0) && (index($line,'=') >= 5)) {
    @defrec = split (/=/, $line);
    $db_id = $defrec[1];
    $db_id = ltrim($db_id);
  }
  $lpos = index($line,':');
  $rpos = rindex($line,':');
  if (($lpos >= 1) && ($rpos >= 3) && ($rpos > $lpos)) {
    # this should be a definition line
    @defrec = split (/:/, $line);
    $sdf_label   = $defrec[0];
    $mysql_label = $defrec[1];
    $mysql_type  = $defrec[2];
    @afield[($nfields)] = [ $sdf_label, $mysql_label, $mysql_type, "" ];
    $nfields++;
  }
}
close(DEF);

if ((!defined $user) || ($user eq "")) {
  die("ERROR: no username specified!\n");
}

$dbprefix = $prefix . "db" . $db_id . "_";
$molstructable = $dbprefix . $molstrucsuffix;
$moldatatable  = $dbprefix . $moldatasuffix;

$dbh = DBI->connect("DBI:mysql:database=$database;host=$hostname",
                    $user, $password,
                    { RaiseError => 1}
                    ) || die("ERROR: database connection failed: $DBI::errstr");

$sth0 = $dbh->prepare("SELECT COUNT(mol_id) AS molcount FROM $molstructable $wherestr");
$sth0->execute();
while ($ref0 = $sth0->fetchrow_hashref()) {
  $molcount = $ref0->{'molcount'};
}
$nchunks = int( (($molcount + 999) / 1000) );

for ($i = 0; $i < $nchunks; $i++) {
  $offset = $i * 1000;
  $sth = $dbh->prepare("SELECT mol_id, struc FROM $molstructable $wherestr LIMIT $offset,1000");
  $sth->execute();
  while ($ref = $sth->fetchrow_hashref()) {
    $mol_id   = $ref->{'mol_id'};
    $mol      = $ref->{'struc'};
    print "$mol\n";
    for ($k = 0; $k <= $#afield; $k++) {
      $mysql_label = $afield[$k][1];
      $sdf_label   = $afield[$k][0];
      $qstr = "SELECT $mysql_label FROM $moldatatable WHERE mol_id = $mol_id";
      $sth2 = $dbh->prepare("$qstr");
      $sth2->execute();
      while ($ref2 = $sth2->fetchrow_hashref()) {
        $item = "";
        foreach my $name (sort keys %$ref2) {
          $item = $ref2->{$name};
        } # foreach
        if (length($item) > 0) {
          print "\> \<${sdf_label}\>\n";
          print "$item\n\n";
        }
      }
      $sth2->finish;
    }  # for
    print "\$\$\$\$\n";
  }        # while ($ref....
  $sth->finish;
}      # for $i

$dbh->disconnect();

#===================== subroutines =======================================

sub ltrim() {
  $subline1 = shift;
  $subline1 =~ s/^\ +//g;
  return $subline1;
}

sub rtrim() {
  $subline2 = shift;
  $subline2 =~ s/\ +$//g;
  return $subline2;
}

sub getnum() {
  $str = shift;
  $numstr = '';
  for ($nn = 0; $nn < length($str); $nn++ ) {
    if (index('0123456789-',substr($str,$nn,1)) >= 0) {
      $numstr = $numstr . substr($str,$nn,1);
    }
  }
  return $numstr;
}