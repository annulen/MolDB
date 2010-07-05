#!/usr/bin/perl
#
# cp2mem_slow.pl     Norbert Haider, University of Vienna, 2009-2010
#                    norbert.haider@univie.ac.at
#
# This script is part of the MolDB5R package. Last change: 2010-04-29
#
# Example script which copies the persistent molstat and molcfp
# MySQL tables to heap-based MySQL tables, which are accessed 
# faster than the disk-based tables.
#
# Unlike cp2mem.pl, this script does not require write privileges
# in a scratch directory on disk, as it directly copies the content
# of one MySQL table to another. This is achieved with the "INSERT"
# command, however at the expense of speed.
#
# After successful copying of the two tables to their memory-based
# counterparts, appropriate flags are set in the moldb_meta table
# (the two least significant bits of "memstatus").
#
# NOTE: the MySQL variable "max_heap_table_size" must be large enough
# to accomodate the molstat table. Default is 16M which may be (much)
# too low. This variable is typically set in /etc/my.cnf, but it may
# be also set on the MySQL prompt, using the "SET GLOBAL variable=value"
# syntax (the value must be specified as number of bytes, because the
# server does not understand "K", "M", or "G").

use DBI();

$configfile = "../moldb5.conf";
$use_fixed_fields = 0;   # 0 or 1, should be 1 for older versions of checkmol
$verbose    = 0;


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
$sth0 = $dbh->prepare("SELECT db_id FROM $metatable WHERE (type = 1) AND (usemem = \"T\") ");
$sth0->execute();
while ($ref0 = $sth0->fetchrow_hashref()) {
  $db_id = $ref0->{'db_id'};
  $ndb++;
  @db[($ndb-1)] = $db_id;
}
$sth0->finish;

# read moldb_fpdef table and find out how many dictionaries are used (and which size)
$createstr = "";
$n_dict    = 0;
$sth0 = $dbh->prepare("SELECT fpdef, fptype FROM $fpdeftable");
$sth0->execute();
while ($ref0 = $sth0->fetchrow_hashref()) {
  $fpdef   = $ref0->{'fpdef'};
  if (length($fpdef) > 20) {
    $n_dict++;
    $dictnum = $n_dict;
    while (length($dictnum) < 2) { $dictnum = "0" . $dictnum;  }
    $fptype = $ref0->{'fptype'};
    if ($fptype == 1) {
      $createstr .= "  dfp$dictnum BIGINT NOT NULL,\n";
    } else {
      $createstr .= "  dfp$dictnum INT(11) UNSIGNED NOT NULL,\n";
    }
  }
}                 # end while ($ref...
$sth0->finish;
chomp($createstr);
if ($n_dict < 1) {
  die("ERROR: could not retrieve fingerprint definition from table $fpdeftable");
}


for ($i = 0; $i < $ndb; $i++) {
  $dbnum = @db[$i];
  $dbprefix = $prefix . "db" . $dbnum . "_";

  if ($verbose > 0) {
    print "processing data collection $dbnum\n";
  }

  #===========================molstat===================================

  # disable use of this table
  $updstr = "UPDATE $metatable SET memstatus = (memstatus ^ 1) WHERE db_id = $dbnum";
  $dbh->do($updstr);	

  $molstattable = $dbprefix . $molstatsuffix;
  $molcfptable  = $dbprefix . $molcfpsuffix;

  $mem_molstattable = $molstattable . '_mem';
  $mem_molcfptable  = $molcfptable . '_mem';
  
  # drop molstat_mem table if it exists already
  $dbh->do("DROP TABLE IF EXISTS $mem_molstattable");
  
  # create a new molstat_mem table
  $createcmd = "CREATE TABLE IF NOT EXISTS $mem_molstattable (
    mol_id int(11) NOT NULL DEFAULT '0'";
  $sth1 = $dbh->prepare("DESCRIBE $molstattable");
  $sth1->execute();
  while ($ref1 = $sth1->fetchrow_hashref()) {
    $field = $ref1->{'Field'};
    $type  = $ref1->{'Type'};
    if (!($field eq "mol_id")) {
      $createcmd .= ",\n    $field $type NOT NULL DEFAULT '0'";
    }
  }
  $sth1->finish;
  $createcmd = $createcmd . ",\n  PRIMARY KEY  (mol_id)
  ) ENGINE = MEMORY COMMENT='Molecular statistics';";
  
  $dbh->do($createcmd);

  # first, get the number of rows and chop the whole operation into
  # suitable chunks
  $sth0 = $dbh->prepare("SELECT COUNT(mol_id) AS molcount FROM $molstattable ");
  $sth0->execute();
  while ($ref0 = $sth0->fetchrow_hashref()) {
    $molcount = $ref0->{'molcount'};
  }
  $nchunks = int( (($molcount + 999) / 1000) );
  
  for ($k = 0; $k < $nchunks; $k++) {
    $offset = $k * 1000;
    $sth = $dbh->prepare("SELECT * FROM $molstattable LIMIT $offset,1000");
    $sth->execute();
    $nfields = $sth->{'NUM_OF_FIELDS'};
    while ($ref = $sth->fetchrow_arrayref()) {
      $insertstr = "";
      for ($j=0; $j<$nfields; $j++){
        if (length($insertstr)>0) { $insertstr .= ","; }
        $insertstr .= $$ref[$j];
      }
      $insertstr = "INSERT INTO $mem_molstattable VALUES (" . $insertstr . ")";
      $dbh->do($insertstr);
    }                                    # end while ($ref...
    $sth->finish;
  }  # end "for" loop

  # enable use of memory-based table (again)
  $sth1 = $dbh->prepare("SELECT COUNT(mol_id) AS molcount FROM $molstattable ");
  $sth1->execute();
  while ($ref1 = $sth1->fetchrow_hashref()) {
    $molcount = $ref1->{'molcount'};
  }
  $sth1->finish;
  $sth1 = $dbh->prepare("SELECT COUNT(mol_id) AS mem_molcount FROM $mem_molstattable ");
  $sth1->execute();
  while ($ref1 = $sth1->fetchrow_hashref()) {
    $mem_molcount = $ref1->{'mem_molcount'};
  }
  $sth1->finish;
  if ($verbose > 0) {
    print "  molstat entries (disk/memory): $molcount/$mem_molcount\n";
  }
  if ($molcount == $mem_molcount) {
    $updstr = "UPDATE $metatable SET memstatus = (memstatus | 1) WHERE db_id = $dbnum";
    $dbh->do($updstr);	
  }


  #===========================molcfp===================================

  # disable use of this table
  $updstr = "UPDATE $metatable SET memstatus = (memstatus ^ 2) WHERE db_id = $dbnum";
  $dbh->do($updstr);	
  
  # drop molcfp_mem table if it exists already
  $dbh->do("DROP TABLE IF EXISTS $mem_molcfptable");
  
  # create a new molcfp_mem table
  #$createcmd = "CREATE TABLE IF NOT EXISTS $mem_molcfptable (
  #  mol_id int(11) NOT NULL DEFAULT '0'";
  #$sth1 = $dbh->prepare("DESCRIBE $molcfptable");
  #$sth1->execute();
  #while ($ref1 = $sth1->fetchrow_hashref()) {
  #  $field = $ref1->{'Field'};
  #  $type  = $ref1->{'Type'};
  #  if (!($field eq "mol_id")) {
  #    $createcmd .= ",\n    $field $type NOT NULL DEFAULT '0'";
  #  }
  #}
  #$sth1->finish;
  #$createcmd = $createcmd . ",\n  PRIMARY KEY  (mol_id)
  #) ENGINE = MEMORY COMMENT='hash-based fingerprints';";
  $createcmd="CREATE TABLE $mem_molcfptable (mol_id INT(11), $createstr
  hfp01 INT(11) UNSIGNED NOT NULL,
  hfp02 INT(11) UNSIGNED NOT NULL,
  hfp03 INT(11) UNSIGNED NOT NULL,
  hfp04 INT(11) UNSIGNED NOT NULL,
  hfp05 INT(11) UNSIGNED NOT NULL,
  hfp06 INT(11) UNSIGNED NOT NULL,
  hfp07 INT(11) UNSIGNED NOT NULL,
  hfp08 INT(11) UNSIGNED NOT NULL,
  hfp09 INT(11) UNSIGNED NOT NULL,
  hfp10 INT(11) UNSIGNED NOT NULL,
  hfp11 INT(11) UNSIGNED NOT NULL,
  hfp12 INT(11) UNSIGNED NOT NULL,
  hfp13 INT(11) UNSIGNED NOT NULL,
  hfp14 INT(11) UNSIGNED NOT NULL,
  hfp15 INT(11) UNSIGNED NOT NULL,
  hfp16 INT(11) UNSIGNED NOT NULL,
  n_h1bits SMALLINT NOT NULL ) 
  ENGINE = MEMORY COMMENT='Combined dictionary-based and hash-based fingerprints'";
  $dbh->do($createcmd);

  # first, get the number of rows and chop the whole operation into
  # suitable chunks
  $sth0 = $dbh->prepare("SELECT COUNT(mol_id) AS molcount FROM $molcfptable");
  $sth0->execute();
  while ($ref0 = $sth0->fetchrow_hashref()) {
    $molcount = $ref0->{'molcount'};
  }
  $nchunks = int( (($molcount + 999) / 1000) );
  
  for ($k = 0; $k < $nchunks; $k++) {
    $offset = $k * 1000;
    $sth = $dbh->prepare("SELECT * FROM $molcfptable LIMIT $offset,1000");
    $sth->execute();
    $nfields = $sth->{'NUM_OF_FIELDS'};
    while ($ref = $sth->fetchrow_arrayref()) {
      $insertstr = "";
      for ($j=0; $j<$nfields; $j++){
        if (length($insertstr)>0) { $insertstr .= ","; }
        $insertstr .= $$ref[$j];
      }
      $insertstr = "INSERT INTO $mem_molcfptable VALUES (" . $insertstr . ")";
      $dbh->do($insertstr);
    }                                    # end while ($ref...
    $sth->finish;
  }  # end "for" loop

  # enable use of memory-based table (again)
  $sth1 = $dbh->prepare("SELECT COUNT(mol_id) AS molcount FROM $molcfptable");
  $sth1->execute();
  while ($ref1 = $sth1->fetchrow_hashref()) {
    $molcount = $ref1->{'molcount'};
  }
  $sth1->finish;
  $sth1 = $dbh->prepare("SELECT COUNT(mol_id) AS mem_molcount FROM $mem_molcfptable");
  $sth1->execute();
  while ($ref1 = $sth1->fetchrow_hashref()) {
    $mem_molcount = $ref1->{'mem_molcount'};
  }
  $sth1->finish;

  if ($verbose > 0) {
    print "  molcfp  entries (disk/memory): $molcount/$mem_molcount\n";
  }
  if ($molcount == $mem_molcount) {
    $updstr = "UPDATE $metatable SET memstatus = (memstatus | 2) WHERE db_id = $dbnum";
    $dbh->do($updstr);	
  }


}   # for $i = ...

$dbh->disconnect();
