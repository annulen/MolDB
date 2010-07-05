#!/usr/bin/perl
#
# moldb2rdf.pl   Norbert Haider, University of Vienna, 2010
#                norbert.haider@univie.ac.at
#
# This script is part of the MolDB5R package. Last change: 2010-04-15
#
# This script dumps the content of the rxndata and rxnstruc tables
# of a moldb database to standard output in RDF format. It uses the
# same definition file "rdf2moldb.def" which can be used for data
# re-import (see "rdfcheck.pl" and "rdf2moldb.pl"). This definition 
# file can also be generated with the script "dumpdef.pl".
#
# A record number (rxn_id) or range of numbers can be specified as the 
# command line argument, e.g. "moldb2rdf.pl 24" or "moldb2rdf.pl 1200-2400".
# Usually, the output of this script is redirected into a file by using
# the ">" operator, e.g. "perl moldb2rdf.pl > mydata.rdf"

$userinput = $ARGV[0];
$numinput = getnum($userinput);

@numrec = split (/-/, $numinput);
$firstitem = $numrec[0];
$lastitem  = $numrec[1];

if ($lastitem == '') {
  if (index($numinput,'-') >= 0) {
    $lastitem = 0;
  } else {
    $lastitem = $firstitem;
  }
}

if (($firstitem == '') || ($firstitem < 1)) {
  $firstitem = 1;
}

if (($lastitem > 0) && ($lastitem < $firstitem)) {
  $lastitem = $firstitem;
}

$wherestr = '';
if (($firstitem > 1) || ($lastitem > 0)) {
  if (($lastitem > 0) || ($lastitem != $firstitem)) {
    if ($lastitem == 0) {
      $wherestr = " WHERE rxn_id >= " . $firstitem;
    } else {
      if ($lastitem != $firstitem) {
        $wherestr = " WHERE rxn_id >= " . $firstitem . " AND rxn_id <= " . $lastitem;
      } else {
        $wherestr = " WHERE rxn_id = " . $firstitem;
      }
    }
  } else {
    $wherestr = " WHERE rxn_id = " . $firstitem;
  }
}

if (($firstitem == 1) && ($lastitem <= $firstitem)) {
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

$deffile = "rdf2moldb.def";
open (DEF, "<$deffile") || die("ERROR: cannot open definition file $deffile!");
$nfields = 0;
while ($line = <DEF>) {
  chomp($line);
  @valid = split (/#/, $line);  # ignore everything behind the first pound sign
  $line  = $valid[0];
  $line  = ltrim($line);
  $line  = rtrim($line);
  if ((index($line,'rdfilename') >= 0) && (index($line,'=') >= 9)) {
    @defrec = split (/=/, $line);
    $rdfile = $defrec[1];
    $rdfile = ltrim($rdfile);
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
    $rdf_label   = $defrec[0];
    $mysql_label = $defrec[1];
    $mysql_type  = $defrec[2];
    @afield[($nfields)] = [ $rdf_label, $mysql_label, $mysql_type, "" ];
    $nfields++;
  }
}
close(DEF);

if ((!defined $user) || ($user eq "")) {
  die("ERROR: no username specified!\n");
}

$dbprefix = $prefix . "db" . $db_id . "_";
$rxnstructable = $dbprefix . $rxnstrucsuffix;
$rxndatatable  = $dbprefix . $rxndatasuffix;

$dbh = DBI->connect("DBI:mysql:database=$database;host=$hostname",
                    $user, $password,
                    { RaiseError => 1}
                    ) || die("ERROR: database connection failed: $DBI::errstr");

$sth0 = $dbh->prepare("SELECT COUNT(rxn_id) AS rxncount FROM $rxnstructable $wherestr");
$sth0->execute();
while ($ref0 = $sth0->fetchrow_hashref()) {
  $rxncount = $ref0->{'rxncount'};
}
$nchunks = int( (($rxncount + 999) / 1000) );

# print RDF file header
$nowstr = localtime;
print "\$RDFILE 1\n";
print "\$DATM $nowstr\n";

for ($i = 0; $i < $nchunks; $i++) {
  $offset = $i * 1000;
  $sth = $dbh->prepare("SELECT rxn_id, struc FROM $rxnstructable $wherestr LIMIT $offset,1000");
  $sth->execute();
  while ($ref = $sth->fetchrow_hashref()) {
    $rxn_id   = $ref->{'rxn_id'};
    # print record separator
    print "\$RFMT \$RIREG $rxn_id\n";
    $struc      = $ref->{'struc'};
    chomp($struc);
    $struc =~ s/\r\n/\n/g;
    print "$struc\n";
    for ($k = 0; $k <= $#afield; $k++) {
      $mysql_label = $afield[$k][1];
      $rdf_label   = $afield[$k][0];
      $qstr = "SELECT $mysql_label FROM $rxndatatable WHERE rxn_id = $rxn_id";
      $sth2 = $dbh->prepare("$qstr");
      $sth2->execute();
      while ($ref2 = $sth2->fetchrow_hashref()) {
        $item = "";
        foreach my $name (sort keys %$ref2) {
          $item = $ref2->{$name};
        } # foreach
        if (length($item) > 0) {
          chomp($item);
          $item =~ s/\r\n/\n/g;
          print "\$DTYPE ${rdf_label}\n";
          $dline = "\$DATUM " . $item;
          while (length($dline) > 0) {
            $oline = substr($dline,0,80);
            print "$oline\n";
            substr($dline,0,80) = "";
          }
        }
      }
      $sth2->finish;
    }  # for
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