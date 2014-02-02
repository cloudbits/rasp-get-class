<?php
// ------------------------------------------------------------------------------
        error_reporting(E_ALL);
        ini_set("display_errors", 1);
// ------------------------------------------------------------------------------
        require 'raspclass.php';
// ------------------------------------------------------------------------------

        // lets parse for somewhere to check for
        if (isset($_GET['tp'])) {
                $gTurnpoint  = strtoupper ($_GET['tp']); // make sure upper case for turnpoint data to line up
        } else {
                echo "No turnpoint provided - please provide one for a forecast like: ?tp=LAS";
                exit(1);
        }
        if (isset($_GET['day'])) {
                $gDay = strtoupper ($_GET['day']);
        } else {
                echo "No day provided - please provide one for a forecast like: ?day=Monday";
                exit(1);
        }
        $fcst = new RASPForecast();
        $sRes = $fcst->CheckResolution( $_GET['res'] );                 // make sure it is valid - exit if not
        $sTurnpoint = $fcst->CheckTurnpoint( $gTurnpoint );             // make sure TP is valid - exit if not

        $fcst->BuildByTP($sTurnpoint, $sRes, $gDay );   // location, resolution, day
        $fcst->GetResults();                                            // get the results

        // Assuming all ok, we now need to present the output

        $iHighestTemp = $fcst->GetMax("temp");
        $fTotalRain = $fcst->GetTotalRain();
        $iHighestWindSpd = round($fcst->GetMax("surface_wind_speed") * 1.15077944802354);

        $sForecastDate = $fcst->GetDateOfForecast();
        $sForecastLatLong = round($fcst->gLat,4)." ".round($fcst->gLong,4);

        list( $fHighestWindSpd2, $iDir, $sTime ) = $fcst->GetMaxWindSpeedDir("surface_wind_speed");
        $sStrDir = $fcst->ResolveWindDirection($iDir);

        $tTime_end = microtime(true);
        $fTimeTaken = round($tTime_end - $fcst->tTime_start,2);
        $sFooterLine1 = "From ".$fcst->gServer." ".date('dmy H:i:s')." in ".$fTimeTaken."s";

        // now lets print it out
        header("Content-Type: image/png");
        $im = @imagecreate(195, 82)
                or die("Cannot Initialize new GD image stream");

        $background_color = imagecolorallocate($im, 0xFF, 0xFF, 0xFF);

        $RED_COLOUR = imagecolorallocate($im,255,0,0);        // RED
        $GREEN_COLOUR = imagecolorallocate($im,0,128,0);      // GREEN
        $BLUE_COLOUR = imagecolorallocate($im,0,0,255);       // BLUE
        $BLACK_COLOUR = imagecolorallocate($im,0,0,0);        // BLACK
        $WHITE_COLOUR = imagecolorallocate($im,255,255,255);  // WHITE

        $iX = 5; $iSize = 3;
        imagestring($im, $iSize, 5, $iX, "Date: $sForecastDate", $BLACK_COLOUR);
        $iX += 15; $iSize = 2;
        imagestring($im, $iSize, 5, $iX, "Lat/Long: $sForecastLatLong", $BLACK_COLOUR);
        $iX += 10;
        imagestring($im, $iSize, 5, $iX, "Max Surface Temp: $iHighestTemp C", $RED_COLOUR);
        $iX += 10;
        imagestring($im, $iSize, 5, $iX, "Rainfall today: $fTotalRain mm", $BLUE_COLOUR);
        $iX += 10;
        imagestring($im, $iSize, 5, $iX, "Max Wind $iHighestWindSpd MPH $sStrDir at $sTime", $BLUE_COLOUR);
        $iX += 18;
        $iSize = 1;
        imagestring($im, $iSize, 5, $iX, $sFooterLine1, $BLACK_COLOUR);
        imagepng($im);
        imagedestroy($im);

// ------------------------------------------------------------------------------
?>
