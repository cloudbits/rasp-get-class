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



