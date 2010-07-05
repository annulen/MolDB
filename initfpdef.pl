#!/usr/bin/perl
#
# initfpdef.pl  Norbert Haider, University of Vienna, 2009-2010
#               norbert.haider@univie.ac.at
#
# This script is part of the MolDB5R package. Last change: 2010-06-15
#
# Example script which initializes the fpdef MySQL table
# within the MolDB5R database: fpdef contains the structures
# which are used for creating the dictionary-based fingerprints
# from each molecule. The standard fragment dictionary which
# is supplied in the file fp01.sdf is then loaded into the
# fpdef table (fp01.sdf is located in the same directory).
# ATTENTION: an already existing table "fpdef" will be erased!
#
# Whenever you want to make any changes to the fragment 
# dictionary (by default, it contains 62 common ring systems), 
# you have to do the following:
# a) modify one or more of the structures in fp01.sdf or
#    create a completely new set of typical structures
#    which are likely to be present in your molecules (the 
#    previous limit of max. 62 structures does no longer apply)
# b) load the new fp01.sdf file into the fpdef table by
#    running this script (initfpdef.pl)
# c) re-create the fingerprints for all the molecules
#    which are already stored in your moldb database by
#    running the scripts mkmolcfp.pl and mkrxncfp.pl

use DBI();

# defaults

$fpdict_mode   = 1;  # 1 = auto adjust, 2 = force 64 bit, 3 = force 32 bit

$configfile = "moldb5.conf";
$fpfile     = "fp01.sdf";

$return     = do $configfile;
if (!defined $return) {
  die("ERROR: cannot read configuration file $configfile!\n");
}	


open (FPF, "<$fpfile") || die ("cannot open fingerprint file $fpfile!");

$user     = $rw_user;    # from configuration file
$password = $rw_password;

$dbh = DBI->connect("DBI:mysql:database=$database;host=$hostname",
                    $user, $password,
                    {'RaiseError' => 1});

# drop fpdef table if it exists already
$dbh->do("DROP TABLE IF EXISTS $fpdeftable");

# create a new fpdef table
$createcmd="CREATE TABLE $fpdeftable (fp_id INT(11), fpdef MEDIUMBLOB, fptype SMALLINT) TYPE=MyISAM";
$dbh->do($createcmd);

$fpstruc = "";
$n_dict  = 0;
$n_mol   = 0;
$molbuf  = "";
$fptype  = 1;  # 1 = 64-bit, 2 = 32-bit
if ($fpdict_mode < 3) { $max_bs = 62; } else { $max_bs = 31; }

while ($line = <FPF>) {
  if ((substr($line,0,4) eq '$$$$') || eof(FPF)) {
    if (length($molbuf)>20) {  
      if (length($fpstruc) > 20) { $fpstruc .= '$$$$ ' . "\r\n"; }
      if (index($molbuf,"M  END")<0) { $molbuf .= "M  END\r\n"; }
      $fpstruc .= $molbuf;
      $molbuf = "";
      $n_mol++;
    }
    if (($n_mol % $max_bs == 0) || eof(FPF)) {
      # insert dictionary chunk into fpdef table
      if ($n_mol > 31) {
      	$fptype = 1;
      } else {
        if ($fpdict_mode == 2) { $fptype = 1; } else { $fptype = 2; }
      }
      $n_dict++;
      #print "$fpstruc\n";
      #print "chunk $n_dict contains $n_mol structures (type $fptype)\n";
      $dbh->do("INSERT INTO $fpdeftable VALUES ($n_dict, \"$fpstruc\", $fptype )");      
      $fpstruc = "";
      $molbuf = "";
      $n_mol = 0;
    }
  } else {
    $molbuf = $molbuf . $line;
  }
}
close(FPF);

$dbh->disconnect();

