rasp-get-class
==============

PHP Class for pulling RASP Weather data

There are two enclosed applications to create a textual output of a lat/long and a PNG image.

Files:

(1) config.ini

This is used to turn debugging on and off, maintenance mode is to permit all uses (incase something upstream is broken)

(2) turnpoints.dat

This file provides BGA turnpoint information so that a textual three character point can be defined instead of using a latitude/longitude pair. This must stay even if you don't use this functionality.

(3) raspclass.php

The class itself. The comments document what it does. For usage information look at the two examples.


(4) Testing The class

Assuming you have a local CLI version of PHP (i.e. php-cli or php-cgi), you can test the class from the command line using: 

"php-cli txtfcst.php tp=LAS res=4Km"

The output should preovide some html of forecast information. If you cannot use a local (shell-based) php invocation, try running as 
