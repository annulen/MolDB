#!/usr/bin/perl
#
# sdf2rdf.pl     Norbert Haider, University of Vienna, 2010
#                norbert.haider@univie.ac.at
#
# This script reads an SD file and writes to standard output in
# RDF format.
#
$mode = 3;  #    1: treat molecule as reactant
            #    2: treat molecule as product
            #    3: both

if ($#ARGV < 0) {
  print "Usage: sdf2rdf.pl <inputfile> [ > <outputfile> ]\n";
  exit;
}

$infile = $ARGV[0];
open (SDF, "<$infile") || die ("cannot open SD file $infile!");


$counter  = 0;
$buf      = '';
$mol      = '';
$txt      = '';
$ct       = 1;

if ($mode == 1) { $nreact = 1; $nprod = 0; }
if ($mode == 2) { $nreact = 0; $nprod = 1; }
if ($mode == 3) { $nreact = 1; $nprod = 1; }

# print RDF file header
$nowstr = localtime;
print "\$RDFILE 1\n";
print "\$DATM $nowstr\n";

while ($line = <SDF>) {
  $line =~ s/\r//g;      # remove carriage return characters (DOS/Win)
  if ((substr($line,0,4) eq '$$$$') || eof(SDF)) {
    $counter ++;
    $entry ++;
    if ($ct == 1) {
      $mol = $buf;
      $txt = "";
    } else {
      $txt = $buf;
    }
    if (valid_mol($mol) == 1) {
      chomp($mol);
      $mol =~ s/\"/\\\"/g;        # escape any quote characters
      if (index($mol,"M  END") < 0) {
        $mol = $mol . "M  END\n"; # some SD files are lacking the "M  END" 
      }	
      print "\$RFMT \$RIREG $counter\n";
      print "\$RXN\n\n\n\n  $nreact  $nprod\n\$MOL\n";
      print "$mol\n";;
      if ($mode == 3) {
        print "\$MOL\n$mol\n";
      }
      write_data($txt);
    } else {
      $counter --;
      $entry --;
      $badmols ++;
    }
    $buf = "";
    $txt = "";
    $mol = "";
    $ct  = 1;
  } else {
    if (substr($line,0,1) eq '>') { 
      if ($ct == 1) {
        $mol = $buf;
        $buf = $line;
      }
      $ct = 0; 
    }
    $buf = $buf . $line;
  }
}        # end while ($line....


#===================== subroutines =======================================

sub write_data() {
  $data = shift;
  $databuf  = "";
  @rec = split (/\n/, $data);
  for ($i = 0; $i <= $#rec+1; $i++) {
    $element  = $rec[$i];
    #$element =~ s/\n//g;
    chomp($element);
    $element =~ s/\ +$//g;
    $lblchars = 0;
    if (substr($element,0,1) eq '>') {
      @lblrec = split(/\</, $element);
      $lblname = $lblrec[1];
      @lblrec = split(/\>/, $lblname);
      $lblname = $lblrec[0];
    } else {
      if (length($element) > 0) {
        $databuf = $databuf . $element;
        #print "adding element\n";
      } else {
        $lblchars = length($databuf);
        
        print "\$DTYPE $lblname\n";
        chomp($databuf);
        print "\$DATUM $databuf\n";
        $databuf = "";
      }
    }
  }
}

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


