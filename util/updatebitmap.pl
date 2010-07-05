#!/usr/bin/perl
#
# updatebitmap.pl   Norbert Haider, University of Vienna, 2009-2010
#                   norbert.haider@univie.ac.at
#
# This script is part of the MolDB5R package. Last change: 2010-04-29
#
# Example script which (re-)generates the bitmap files for 2D depiction
# of the molecules in the MolDB5R database: this gives better performance 
# and a better quality of displayed structures than using the JME
# applet in "depict" mode for each hit structure. Usually, this script
# is run as a cron job.

# Requirements: mol2ps and Ghostscript must be installed.

use DBI();

#$MOL2PS       = "mol2ps";   # read from configuration file
##$GHOSTSCRIPT  = "/usr/bin/gs";  # for Linux
#$GHOSTSCRIPT  = "gswin32c";   # for Windows

$configfile = "../moldb5.conf";
#$configfile = "/data/moldb/moldb5/moldb5.conf";  # better use absolute path
$verbose    = 2;  # 0 = silent operation, 
                  # 1 = report each data collection, 
                  # 2 = report each molecule

$ostype = getostype();
if ($ostype eq 2) { use File::Temp qw/ tempfile tempdir /; }

$return     = do $configfile;
if (!defined $return) {
  die("ERROR: cannot read configuration file $configfile!\n");
}	

$user     = $rw_user;    # from configuration file
$password = $rw_password;

if ((defined $bitmapdir) && ($bitmapdir ne "")) {
  if ( ! -d $bitmapdir) {
    mkdir $bitmapdir || die("ERROR: cannot create directory $bitmapdir !");
    if ($verbose > 0) {
      print "created directory: $bitmapdir\n";
    }
  }
  if ( ! -W $bitmapdir) {
    die("ERROR: cannot write to directory $bitmapdir !");
  }
  # check for mol2ps
  $return = `$MOL2PS -v`;
  if (index($return,"Usage:") < 0) {
    die("ERROR: could not find 'mol2ps', make sure it is installed and in your search path");	
  }  
  # check for Ghostscript
  $return = `$GHOSTSCRIPT -v`;
  if (index($return,"Ghostscript") < 0) {
    die("ERROR: could not find ghostscript, make sure it is installed and in your search path");	
  }  
} else {
  die("ERROR: bitmap directory is not defined in your configuration file!");
}


$dbh = DBI->connect("DBI:mysql:database=$database;host=$hostname",
                    $user, $password,
                    {'RaiseError' => 1});

# read moldb_meta table and find out which data collections are to be processed
$ndb = 0;
@db = [""];
$sth0 = $dbh->prepare("SELECT db_id FROM $metatable WHERE type = 1 ORDER BY db_id");
$sth0->execute();
while ($ref0 = $sth0->fetchrow_hashref()) {
  $db_id = $ref0->{'db_id'};
  $ndb++;
  @db[($ndb-1)] = $db_id;
}
$sth0->finish;

$badmols = 0;
$counter = 0;
$delcounter = 0;

for ($idb = 0; $idb < $ndb; $idb++) {
  $dbnum = @db[$idb];
  $dbprefix = $prefix . "db" . $dbnum . "_";
  $pic2dtable    = $dbprefix . $pic2dsuffix;
  $molstructable = $dbprefix . $molstrucsuffix;

  # get number of digits and subdirdigits for PNG filenames of this data collection
  $sth0 = $dbh->prepare("SELECT digits, subdirdigits FROM $metatable WHERE db_id = $dbnum");
  $sth0->execute();
  while ($ref0    = $sth0->fetchrow_hashref()) {
    $digits       = $ref0->{'digits'};
    $subdirdigits = $ref0->{'subdirdigits'};
  }
  $sth0->finish;
  # check if the number of PNG filename digits is OK
  if (!defined $digits) {
    $digits = 8;
    if ($verbose > 0) {
      print "could not get number of digits, using default($digits)\n";
    }
  }
  # check if subdirectories are to be used
  if (!defined $subdirdigits) {
    $subdirdigits = 0;
    if ($verbose > 0) {
      print "could not get number of subdir digits, using default($subdirdigits)\n";
    }
  }
  
  # read all pending molecules from molstructable and pipe the MDL molfiles
  # through mol2ps and Ghostscript to produce the PNG graphics files
  #
  # first, get the number of rows and chop the whole operation into
  # suitable chunks
  $sth0 = $dbh->prepare("SELECT COUNT(mol_id) AS molcount FROM $pic2dtable WHERE status = 3 ");
  $sth0->execute();
  while ($ref0 = $sth0->fetchrow_hashref()) {
    $molcount = $ref0->{'molcount'};
  }
  $sth0->finish();
  if ($verbose > 0) {
    print "number of molecules in data collection $dbnum to be processed: $molcount \n";
  }
  $nchunks = int( (($molcount + 99) / 100) );
  $li  = 0;
  $buf = "";
  $mol = "";
  $txt = "";
  $lbl = "";
  $ct  = 1;
  if ($molcount > 0) {
    $dbbitmapdir = $bitmapdir . "/" . $dbnum;
    if ( ! -d $dbbitmapdir) {
      mkdir $dbbitmapdir || die("ERROR: cannot create directory $dbbitmapdir !");
      if ($verbose > 1) { print "created subdirectory: $dbbitmapdir\n"; }
    }
    if ( ! -W $dbbitmapdir) {
      die("ERROR: cannot write to directory $dbbitmapdir !");
    }
    for ($ii = 0; $ii < $nchunks; $ii++) {
      #$offset = $ii * 100;
      $offset = 0;    # no offset as the searched item (status) is permanently updated
      $qstr = "SELECT " . ${molstructable} . ".mol_id, " . ${molstructable} . ".struc FROM ";
      $qstr .= $molstructable . ", " . $pic2dtable . " WHERE (" . ${pic2dtable} . ".status = 3) AND ";
      $qstr .= "(" . ${molstructable} . ".mol_id = " .${pic2dtable} . ".mol_id) LIMIT " . $offset . ", 100";
      $sth = $dbh->prepare($qstr);
      $sth->execute();
      while ($ref = $sth->fetchrow_hashref()) {
        $mol_id   = $ref->{'mol_id'};
        $mol      = $ref->{'struc'};
        $counter ++;
        if (valid_mol($mol) eq 1) {
          $fname = $mol_id;
          while (length($fname) < $digits) { $fname = "0" . $fname; }
          $subdir = '';
          if ($subdirdigits > 0) {
            $subdir = substr($fname,0,$subdirdigits);
            $newdir = $dbbitmapdir . "/" . $subdir;
            if ( ! -d $newdir) {
              mkdir $newdir || die("ERROR: cannot create directory $newdir !");
              if ($verbose > 1) { print "created subdirectory: $newdir\n"; }
            }
            if ( ! -W $newdir) {
              die("ERROR: cannot write to directory $newdir !");
            }
            $subdir = $subdir . "/";        
          }
          $fname = $dbbitmapdir . "/" . $subdir . $fname . ".png";
          # mark this mol_id as "in progress" (30) in pic2dtable.status
          $sth2 = $dbh->prepare("UPDATE $pic2dtable SET status = 30 WHERE mol_id = $mol_id");
          $sth2->execute();
          $sth2->finish();
          process_mol($mol,$fname,$mol2psopt,$scalingfactor);
          #if exists(fname)...
          if ( -f $fname) {
            # mark this mol_id as "done" (1) in pic2dtable.status
            $sth2 = $dbh->prepare("UPDATE $pic2dtable SET status = 1 WHERE mol_id = $mol_id");
            $sth2->execute();
            $sth2->finish();
          }
        } else {
          $counter --;
          $badmols ++;
        }
      }                 # end while ($ref...
      $sth->finish;
    }                   # end "for" loop
  }   # if ...    

  #===================================check for bitmap files for deletion
  $dbbitmapdir = $bitmapdir . "/" . $dbnum;
  if ($verbose > 0) {
    print " checking for orphan bitmap files in $dbbitmapdir...\n";
  }
  if ($ostype eq 1) { open(FIND,"find $dbbitmapdir -name \"*.png\" -print |"); }
  if ($ostype eq 2) { 
    $dbbitmapdir_win = $dbbitmapdir;
	$dbbitmapdir_win =~ s/\//\\/g;
	$fullpath = $dbbitmapdir_win . "\\\*.png";
	open(FIND,"dir $fullpath /b /s |"); 
  }
  while ($fullname = <FIND>) {
    chomp($fullname);  
	if ($ostype eq 2) { $fullname =~ s/\\/\//g; }
    $slashpos = rindex($fullname,"/");
    $fname = substr($fullname,($slashpos + 1));
    $mol_id = $fname;
    $mol_id =~ s/\.png//g;
    # check if this mol_id exists in our data collection
    $molcount = 1;
    $sth0 = $dbh->prepare("SELECT COUNT(mol_id) AS molcount FROM $pic2dtable WHERE mol_id = $mol_id ");
    $sth0->execute();
    while ($ref0 = $sth0->fetchrow_hashref()) {
      $molcount = $ref0->{'molcount'};
    }
    $sth0->finish();
    if ($molcount < 1) {
      if ($verbose > 1) {
        print " $fullname marked to be deleted\n";
      }
      $delcounter++;
      $delname = $fullname . ".to_be_deleted";
      (rename $fullname, $delname) || print STDERR "ERROR: renaming failed for $fullname\n";
    }
  }
  #=================================end deletion

} # for ($idb ....


$dbh->disconnect();

if ($verbose > 0) {
  print "$counter records processed in total\n";
  print "$badmols records ignored\n";
  print "$delcounter bitmap file(s) marked to be deleted\n";
}

#============================================================

sub valid_mol() {
  $testmol = shift;
  $zerolines = 0;
  @xyzline = split(/\n/, $testmol);
  for ($i = 0; $i <= $#xyzline; $i++) {
    $testline = $xyzline[$i];
    $testline =~ s/\ +/:/g;
    @xyz = split(/:/, $testline);
    $xval = $xyz[1];
    $yval = $xyz[2];
    $zval = $xyz[3];
    if ((index($xval,"0.0000") >= 0) && (index($yval,"0.0000") >= 0) && 
       (index($zval,"0.0000") >= 0)) { $zerolines ++; }
  }
  if ($zerolines > 1) {
    return 0;
  } else {
    return 1;
  }
}

sub process_mol() {
  $molecule = shift;
  $filename = shift;
  $mopt = shift;
  $sf = shift;
  $gsdevice = "pnggray";
  if (index($mopt,"--color=") >= 0) { $gsdevice = "png256"; }
  $molecule =~ s/\"/\\\"/g;
  if (index($molecule,"M  END") < 0) { $molecule = $molecule . "M  END\n"; }	

  if ($ostype eq 2) {
    $molps = filterthroughcmd2($molecule,"$MOL2PS $mopt - ");
    $bb =  filterthroughcmd2($molps,"$GHOSTSCRIPT -q -sDEVICE=bbox -dNOPAUSE -dBATCH  -r300 -g500000x500000 - ");
  } else {
    $molps = filterthroughcmd($molecule,"$MOL2PS $mopt - ");
    $bb =  filterthroughcmd($molps,"$GHOSTSCRIPT -q -sDEVICE=bbox -dNOPAUSE -dBATCH  -r300 -g500000x500000 - ");
  }
  @bbrec =   split(/\n/, $bb);
  $bblores = $bbrec[0];
  $bblores =~ s/%%BoundingBox://g;
  chomp($bblores);
  $bblores = ltrim($bblores);
  @bbcorner = split(/\ /, $bblores);
  $bbleft = $bbcorner[0];
  $bbbottom = $bbcorner[1];
  $bbright = $bbcorner[2];
  $bbtop = $bbcorner[3];
  $xtotal = ($bbright + $bbleft) * $sf;
  $ytotal = ($bbtop + $bbbottom) * $sf;
  if (($xtotal > 0) && ($ytotal > 0)) {
    $molps = $sf . " " . $sf . " scale\n" . $molps;  ## insert the PS "scale" command
    #print "low res: $bblores  .... max X: $bbright, max Y: $bbtop \n";
    if ($verbose > 1) {
      print "$filename  $xtotal x $ytotal pt\n";
    }
  } else {
    $xtotal = 99;
    $ytotal = 55;
    $molps = "%!PS-Adobe
    /Helvetica findfont 14 scalefont setfont
    10 30 moveto
    (2D structure) show
    10 15 moveto
    (not available) show
    showpage\n";
    if ($verbose > 1) {
      print "writing empty file\n";
    }
  }	
  $gsopt1 = " -r300 -dGraphicsAlphaBits=4 -dTextAlphaBits=4 -dDEVICEWIDTHPOINTS=";
  $gsopt1 = $gsopt1 . $xtotal . " -dDEVICEHEIGHTPOINTS=" . $ytotal;
  $gsopt1 = $gsopt1 . " -sOutputFile=" . $filename;
  $gscmd = "$GHOSTSCRIPT -q -sDEVICE=$gsdevice -dNOPAUSE -dBATCH " . $gsopt1 . " - ";
  if ($ostype eq 2) {
    $dummy = filterthroughcmd2($molps, $gscmd);
  } else {
    system("echo \"$molps\" \| $gscmd");
  }
  #  system("echo \"$molps\" \| $gscmd");
}

sub filterthroughcmd {
  $input   = shift;
  $cmd     = shift;
  open(FHSUB, "echo \"$input\"|$cmd 2>&1 |");   # stderr must be redirected to stdout
  $res      = "";                               # because the Ghostscript "bbox" device
  while($line = <FHSUB>) {                      # writes to stderr
    $res = $res . $line;
  }
  return $res;
}

sub filterthroughcmd2 {                         # workaround for Windows 
  $input   = shift;
  $cmd     = shift;
  ($tmpfh, $tmpfilename) = tempfile(UNLINK => 1);
  $input =~ s/\\\$/\$/g;
  $input =~ s/\r//g;
  $input =~ s/\n/\r\n/g;
  print $tmpfh "$input\n";
  #open(FHSUB, "type $tmpfilename |$cmd 2>&1 |");   # stderr must be redirected to stdout
  open(FHSUB, "$cmd < $tmpfilename 2>&1 |");   # stderr must be redirected to stdout
  $res      = "";                               # because the Ghostscript "bbox" device
  while($line = <FHSUB>) {                      # writes to stderr
    $res = $res . $line;
  }
  close $tmpfh;
  return $res;
}

sub ltrim() {
  $subline1 = shift;
  $subline1 =~ s/^\ +//g;
  return $subline1;
}

sub getostype() {
  $os  = "";
  $osresult = 1;
  $os  = uc($ENV{OS});
  if ($os eq "") { $os = uc($ENV{OSTYPE}); }
  if (index($os,"WINDOWS")>=0) {
    $osresult = 2;
  }
  return $osresult;
}
