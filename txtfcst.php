<?php
// ------------------------------------------------------------------------------
        error_reporting(E_ALL);
        ini_set("display_errors", 1);
// ------------------------------------------------------------------------------
        require 'raspclass.php';
// ------------------------------------------------------------------------------

        // set the default timezone to use. Available since PHP 5.1
        date_default_timezone_set('UTC');

        // lets parse for somewhere to check for
        if (isset($_GET['tp'])) {
                $gTurnpoint  = strtoupper ($_GET['tp']); // make sure upper case for turnpoint data to line up
        } else {
                echo "No turnpoint provided - please provide one for a forecast like: ?tp=LAS";
                exit(1);
        }

        $fcst = new RASPForecast();
        $sRes = $fcst->CheckResolution( $_GET['res'] );      // make sure it is valid - exit if not
        $sTurnpoint = $fcst->CheckTurnpoint( $_GET['tp'] );  // make sure it is valid - exit if not

        $fcst->BuildByTP($sTurnpoint, $sRes, "UK4");         // location, resolution, day
        $fcst->GetResults();                                 // get the results
        $fcst->PrintBasicTextResults();                      // print a forecast summary

// ------------------------------------------------------------------------------
?>
