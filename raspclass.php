<?php
// -------------------------------------------------------------------------------------
/*
	CLASS API:
	class.GetForecastByTP($turnpoint, $day, $res)
	class.GetForecastByLatLong($lat, $long, $day, $res)
	class.GetForecastByIK($i, $k, $day, $res)

	class.PrintTextResults()
*/
class RASPForecast
{
	// property declarations
	const gTurnpointFile = "turnpoints.dat";	// This is used to find a Lat/Long to a BGA turnpoint
	const sINIFile = "config.ini";				// This is the configuration file name for the class
	private $gGetLocatorCoords = FALSE;			// Flag used to process if using TP or I/J
	private	$bDebugMode = FALSE;				// Debug flag - if true all the debug text is printed
	
	private	$gParsePtStart = 2;					// default start parse point in the RASP output line
	private	$gParsePtEnd = 22;					// we use this to split the data out - whether starting at 07:00 or 06:00
												// this is the end point (of the string)
	private $gRes= "12km";						// default processing resolution
	private $gCubase = 'temp'; 					// default to calculate the cloudbase (or not)
	private $gDay = 'UK4'; 						// default model to search for is today

	protected $gTurnpoint = "<empty>";			// The next few data items are the placeholders
	protected $gLat = "<empty>";				// used by the class for processing
	protected $gLong = "<empty>";				// these get filled if the processing works correctly
	protected $gTPName = "<empty>";				
	protected $gGrid_i = "1332";				// default "i"
	protected $gGrid_k = "1547";				// default "k"
	protected $gArchiveDate = "<empty>";
	public $gServer = "stratus";				// default server source
	
	public $gSource = "<empty>";				// where we store pulled results - read only
	public $headertime = "<empty>";
	public $dateline = "<empty>";
	public $gridline = "<empty>";
	public $latlongline = "<empty>";
	public $forecast_period = "<empty>";
	public $wstar = "<empty>";
	public $bl_top = "<empty>";
	public $thermal_top = "<empty>";
	public $hcrit = "<empty>";
	public $surfacesun = "<empty>";
	public $temp = "<empty>";
	public $dewtemp = "<empty>";
	public $mslpressure = "<empty>";
	public $surface_wind_dir = "<empty>";
	public $surface_wind_speed = "<empty>";
	public $upper_wind_speed = "<empty>";
	public $upper_wind_dir = "<empty>";
	public $max_conv = "<empty>";
	public $CU_potential = "<empty>";
	public $rain = "<empty>";
	
	public $gaResults = "";					// This is the global array array of returned results ($ga)

	private $g_url_front = "<empty>";	// 193.113.58.173 = www.mrs.labs.bt.com / www.mrs.bt.co.uk
	private	$g_url_back = "<empty>";

	private	$gModelData="";
	private	$gModelParams="";
	private	$gGraphTitle = "Title";
	private	$gGraphYAxis = "YAxis";
	private	$gGraphLegend = "Legend";
	private	$gCompressYAxis = 0;
	private	$gLowest_Y = 0;
	private	$gHighest_Y = 0;

	private $ini_array = "";			// This holds the configuration from the INI file
	
// -------------------------------------------------------------------------------------
	private function my_str_split($string,$string_length=1) 
	{
		if(strlen($string)>$string_length || !$string_length) {
	            do {
	                $c = strlen($string);
	                $parts[] = substr($string,0,$string_length);
	                $string = substr($string,$string_length);
	            } while($string !== false);
	        } else {
	            $parts = array($string);
	        }
	        return $parts;
	}

// -------------------------------------------------------------------------------------
	private function LoadConfigFile ()
	{
		// Parse with sections
		$this->ini_array = parse_ini_file(self::sINIFile,true);
		if ($this->ini_array == FALSE)
		{
			//can't find ini file!
			echo "<pre>Please check the general configuration file exists in the installation.</pre>";
			exit(1);
		} else {
			// all is well (ok we at least loaded something)			
			// set the debugging mode
			if ($this->ini_array["System"]["debug_mode"] == 1)
			{
				$this->bDebugMode = TRUE;
			} else {
				$this->bDebugMode = FALSE;
			}
			if ($this->bDebugMode) {print "Loaded config file\n";}
		}
	}
// -------------------------------------------------------------------------------------
	//Check we're not in maintenance mode
	private function CheckMaintenanceMode ()
	{
		// This function checks if blip script are not to run or not
		// this means onward systems can be excluded if not online
		
		if ($this->ini_array["System"]["maintenance_mode"] == 1)
		{
			//can't find ini file!
			header ('Content-type: image/png');
			$im = @imagecreatetruecolor(700, 300)
				or die('<pre>Currently undergoing maintenance - please come back later.</pre>'); // use text instead
			$BLACK = imagecolorallocate($im,0,0,0);        
			$ROYAL_BLUE = imagecolorallocate($im,065,105,225);
			$WHITE = imagecolorallocate($im,255,255,255);    
			
			imagefilledrectangle( $im, 1, 1, 698, 298, $WHITE );
			imagestring($im, 7, 100, 135, "Currently undergoing maintenance - please come back later.", $ROYAL_BLUE );
			imagepng($im);
			imagedestroy($im);
			exit(0);
		}
		if ($this->ini_array["System"]["maintenance_mode"] == 2)
		{
			//can't find ini file!
			echo "<pre>Currently undergoing maintenance - please come back later.</pre>";
			exit(0);
		}	
	}
// -------------------------------------------------------------------------------------
   	function __construct() {
   		/*
   		The constructor  
			loads the config file (we need this)
			checks if we are in a mainteance mode that stops the processing of data if true.
			checks we've asked for a valid day
			checks for a valid region (RASP Model). 
		
   		*/
			$this->LoadConfigFile ();
			
	    	$this->CheckMaintenanceMode ();
	    	if ($this->bDebugMode) {print "In BaseClass constructor\n";}
	   	
	    	$this->gGetLocatorCoords = FALSE; // set this in case we need to recalc the i/k coords
			
	} // end constructor
// -------------------------------------------------------------------------------------
	private function BuildByIK($i, $k, $res, $region)
	{
		// get the data from RASP Source ...
		// Now check and HTTP parameters 
		if ( $this->gGetLocatorCoords == FALSE ) { // i.e use the command line ones
	
			// Do we have an "I" to use?
			if (isset($i)) {
	    			$this->gGrid_i = $i;
	    			// check here if <9999 or > 0
			}
	
			// Do we have an "K" to use?
			if (isset($k)) {
		    		$this->gGrid_k = $k;
	    			// check here if <9999 or > 0
			}
			$this->gSource = $this->g_url_front."&i=".$this->gGrid_i."&k=".$this->gGrid_k.$g_url_back;
			$this->gParams = "";
		} else {
			echo "Coordinates Not set properly in BuildByIK";
			exit(1);
		}
	}
#------------------------------------------------------------------------------
	public function TurnPointDetails($TP, $Resolution)
	{
	        // Input is three character BGA turnpoint name and a map resolution of "12km", "5.1km" or "4km"
	        // File is setup as:
	        // TP, Lat, Long, Name
	
	        $gFound = FALSE;
	        if ($this->bDebugMode) {echo "<br>Looking for: ".$TP;}
	
	        if (($handle = fopen(self::gTurnpointFile, "r")) != FALSE)
	        {
	                while ( ($data = fgetcsv($handle, 1000, ",")) != FALSE )
	                {
	                        if ( $data[0] == $TP ) {
	                            $gFound = TRUE;
		                        // Get i/k
		                        list($i, $k ) = $this->latlon2ij($data[1], $data[2], $Resolution);
		                        if ($this->bDebugMode) { echo "<br>latlon2ij returned ... i=".$i." k=".$k; }
		                        return array ($data[0], $data[1], $data[2], $data[3], $i, $k );	
	                        }
	                }
	                fclose($handle);
	        } else {
				echo "<br>Unable to find the class turnpoint file: ".self::gTurnpointFile. " - stopping here.";
				exit(1);
			}
			
	        if ( $gFound == FALSE ) {
	                if ($this->bDebugMode) { echo "<br>".$TP." not found."; }
	                return array ("NF ", 99, 199, "Not Found", -1, -1 );
	        }
	}	
// -------------------------------------------------------------------------------------
	public function BuildByTP($tp, $res, $region)
	{
		if (isset($tp)) {
			$this->gTurnpoint  = strtoupper ($tp); // make sure upper case for turnpoint data to line up
			$this->gGetLocatorCoords = TRUE;
		} else {
			echo "Turnpoint not set - stopping here.";
			exit(1);
		}
		if (isset($res)) {
			$this->gRes = $res;
		} else {
	    		echo "Resolution not set - stopping here.";
	    		exit(1);		
	    }
	    		
		if ( $this->gGetLocatorCoords == TRUE ) { // i.e use the command line ones
			list($this->gTurnpoint,$this->gLat,$this->gLong,$this->gTPName,$this->gGrid_i,$this->gGrid_k) = 
				$this->TurnPointDetails($tp, $res);
			if ($this->bDebugMode) { echo "<br>Input: Turnpoint=$tp Resolution=$res"; }
			if ($this->bDebugMode) { echo "<br>Output: tp=$this->gTurnpoint lat=$this->gLat long=$this->gLong name=$this->gTPName i=$this->gGrid_i k=$this->gGrid_k"; }
			if ($this->gLat > 90){ // impossible lat, then the lookup failed so now reset getting URL parameters
				$this->gGetLocatorCoords = FALSE;
			} 
		}
	
		if (isset($region)) {
			$this->gDay = $this->GetDay ( $region );
		} else {
	    		echo "Region/Day not set - stopping here.";
	    		exit(1);		
	    }
		
		$this->gArchiveDate = "0"; // this must be set or the request will fail
	
		// 193.113.58.173 = www.mrs.labs.bt.com / www.mrs.bt.co.uk
		$this->g_url_front = "http://193.113.58.173/cgi-bin/get_rasp_blipspot.cgi?region=";
		$this->g_url_front .= $this->gDay."&grid=d2&day=".$this->gArchiveDate;
		$this->g_url_back = "&width=2000&height=2000&linfo=1&param=";
		
		$this->gSource = $this->g_url_front."&i=".$this->gGrid_i."&k=".$this->gGrid_k.$this->g_url_back;
		$this->gParams = "";
	} // end function
// -------------------------------------------------------------------------------------	
	public function GetResults()
	{
		// Now go and get the data 		
		// BLIPSPOT:  14 Sep 2009
		// UK12 d2 gridpt= 47,18
		// Lat,Lon= 52.18756,0.95882		
		
		$file = fopen ($this->gSource, "r");
		usleep(500);
		
		if (!$file) {
			echo "<p>Unable to open BLIP source URL: ".$this->gSource;
			exit;
		}		
		// Assumes basic blip output from the standard default script		
		$this->dateline = fgets ($file, 1024);
		$this->gaResults[ "dateline" ] = array_slice(explode(" ", $this->dateline),3);
		
		$this->gridline = fgets ($file, 1024);
		$this->gaResults[ "gridline" ] = array_slice(explode(" ", $this->gridline),1);
		
		$this->latlongline = fgets ($file, 1024);
		$this->gaResults[ "latlongline" ] = array_slice(explode(" ", $this->latlongline),1);
		
		$eat = fgets ($file, 1024); // get the dashes
		
		$this->headertime = fgets ($file, 1024);
		$this->gaResults[ "headertime" ] = array_slice(array_slice(explode(" ", $this->headertime),4),0,27);
		foreach ($this->gaResults[ "headertime" ] as &$value) {
			$value = str_replace('lst','',$value);
			
		}
		$this->forecast_period = $header = fgets ($file, 1024);
		$this->gaResults[ "forecast_period" ] = $this->SplitEntry ( $this->forecast_period );
		
		$eat = fgets ($file, 1024); // get the dashes
		$this->wstar = fgets ($file, 1024);
		$this->gaResults[ "wstar" ] = $this->SplitEntry ( $this->wstar );		
		$this->bl_top = fgets ($file, 1024);
		$this->gaResults[ "bl_top" ] = $this->SplitEntry ( $this->bl_top );		
		$this->thermal_top = fgets ($file, 1024);
		$this->gaResults[ "thermal_top" ] = $this->SplitEntry ( $this->thermal_top );				
		$this->hcrit = fgets ($file, 1024);
		$this->gaResults[ "hcrit" ] = $this->SplitEntry ( $this->hcrit );
		$this->surfacesun = fgets ($file, 1024);
		$this->gaResults[ "surfacesun" ] = $this->SplitEntry ( $this->surfacesun );
		$this->temp = fgets ($file, 1024);
		$this->gaResults[ "temp" ] = $this->SplitEntry ( $this->temp );
		$this->dewtemp = fgets ($file, 1024);
		$this->gaResults[ "dewtemp" ] = $this->SplitEntry ( $this->dewtemp );
		$this->mslpressure = fgets ($file, 1024);
		$this->gaResults[ "mslpressure" ] = $this->SplitEntry ( $this->mslpressure );
		$this->surface_wind_dir = fgets ($file, 1024);
		$this->gaResults[ "surface_wind_dir" ] = $this->SplitEntry ( $this->surface_wind_dir );
		$this->surface_wind_speed = fgets ($file, 1024);
		$this->gaResults[ "surface_wind_speed" ] = $this->SplitEntry ( $this->surface_wind_speed );
		$this->upper_wind_speed = fgets ($file, 1024);
		$this->gaResults[ "upper_wind_speed" ] = $this->SplitEntry ( $this->upper_wind_speed );
		$this->upper_wind_dir = fgets ($file, 1024);
		$this->gaResults[ "upper_wind_dir" ] = $this->SplitEntry ( $this->upper_wind_dir );
		$this->max_conv = fgets ($file, 1024);
		$this->gaResults[ "max_conv" ] = $this->SplitEntry ( $this->max_conv );
		$this->CU_potential = fgets ($file, 1024);
		$this->gaResults[ "CU_potential" ] = $this->SplitEntry ( $this->CU_potential );
		$this->rain = fgets ($file, 1024);
		$this->gaResults[ "rain" ] = $this->SplitEntry ( $this->rain );
		$this->stars = fgets ($file, 1024);
		$this->gaResults[ "stars" ] = $this->SplitEntry ( $this->stars );
		$this->cubase = fgets ($file, 1024);
		$this->gaResults[ "cubase" ] = $this->SplitEntry ( $this->cubase );
		$this->bl_cloud = fgets ($file, 1024);
		$this->gaResults[ "bl_cloud" ] = $this->SplitEntry ( $this->bl_cloud );
		$this->sfc_heating = fgets ($file, 1024);	
		$this->gaResults[ "sfc_heating" ] = $this->SplitEntry ( $this->sfc_heating );
		
	}
// -------------------------------------------------------------------------------------
	public function PrintOutput()
	{
	
	    echo "<br>source=".$this->gSource;
	    echo "<br>dateline=".$this->dateline;
	
	    echo "<br>gridline=".$this->gridline;
	    echo "<br>latlongline=".$this->latlongline;
	    echo "<br>headertime=".$this->headertime;
	    echo "<br>forecast_period=".$this->forecast_period;
	    echo "<br>wstar=".$this->wstar;
	    echo "<br>bltop=".$this->bl_top;
	    echo "<br>thermal_top=".$this->thermal_top;
	    echo "<br>hcrit=".$this->hcrit;
	    echo "<br>surfacesun=".$this->surfacesun;
	    echo "<br>temp=".$this->temp;
	    echo "<br>dewtemp=".$this->dewtemp;
	    echo "<br>mslpressure=".$this->mslpressure;
	    echo "<br>surface_wind_dir=".$this->surface_wind_dir;
	    echo "<br>surface_wind_speed=".$this->surface_wind_speed;
	    echo "<br>upper_wind_speed=".$this->upper_wind_speed;
	    echo "<br>upper_wind_dir=".$this->upper_wind_dir;
	    echo "<br>max_conv=".$this->max_conv;
	    echo "<br>CU_potential=".$this->CU_potential;
	    echo "<br>rain=".$this->rain;
	}
// -------------------------------------------------------------------------------------
	// result($i, $k ) = latlon2ij(51.1, 0.2, "12km")
	// result($i, $k ) = latlon2ij(51.1, -0.2, "5.1km")
	// result($i, $k ) = latlon2ij(53.1, -0.2, "4km")
	
	public function latlon2ij($ALAT, $ELON, $GridSpacing)
	{
	          
	//                var ix, iy, Alat, ELON1, DX, ELONV, ALATAN, H, RERTH, RADPD, REBYDX, ALATN1, an;
	//               var ELON1L, ELONL, ELONVR, ALA1, RMLL, ELO1, ARG, POLEI, POLEJ, ALA, RM, ELO;
	//                var v2i, v2j;
	//                var image_mapwidth, image_mapheight, image_maporiginx, image_maporiginy;
	//                var grid_imin, grid_imax, grid_jmin, grid_jmax;
	
		//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		//Sub W3FB11()
		////// CALC LAMBERT I,J (decimal) FROM LONG,LAT FOR GIVEN LAMBERT PARAMETERS
		//////  Args: ALAT,ELON, Alat,ELON1,DX,ELONV,ALATAN
		//
		// SUBPROGRAM:  W3FB11        LAT/LON TO LAMBERT(I,J) FOR GRIB
		//   PRGMMR: STACKPOLE        ORG: NMC42       DATE:88-11-28

		switch (strtolower($GridSpacing)) {
		case "12km":
			//Indexs= 1 57 1 90 Proj= lambert 12000 12000 60.000 60.000 17.000 54.501 -3.500
			//elon1 = -5.621
			//alat = 49.037
			$Alat = 49.037;
			$ELON1 = -5.621;
			$DX = 12000;
			$ELONV = 17;
			$ALATAN = 60;
			$image_mapwidth = 0.515955;
			$image_mapheight = 0.82;
			$image_maporiginx = 0.242022;
			$image_maporiginy = 0.89;
			$grid_imin = 1;
			$grid_imax = 57;
			$grid_jmin = 1;
			$grid_jmax = 90;
			break;
		case "4km":
			// Indexs= 1 153 1 252 Proj= lambert 4000 4000 60.000 60.000 17.000 54.501 -3.500
			// longitude pt1 = -5.356
			//lat pt1 = 49.398
			$Alat = 49.398;
			$ELON1 = -5.356;
			$DX = 4000;
			$ELONV = 17;
			$ALATAN = 60;
			$image_mapwidth = 0.496574;
			$image_mapheight = 0.82;
			$image_maporiginx = 0.251713;
			$image_maporiginy = 0.89;
			$grid_imin = 1;
			$grid_imax = 153;
			$grid_jmin = 1;
			$grid_jmax = 252;
			break;
		case "5.1km":
			//Indexs= 1 119 1 196 Proj= lambert 5143 5143 60.000 60.000 17.000 54.501 -3.500
			//alat = 49.405
			//elon1 = -5.352
			$Alat = 49.405;
			$ELON1 = -5.352;
			$DX = 5143;
			$ELONV = 17;
			$ALATAN = 60;
			$image_mapwidth = 0.496206;
			$image_mapheight = 0.82;
			$image_maporiginx = 0.251897;
			$image_maporiginy = 0.890;
			$grid_imin = 1;
			$grid_imax = 119;
			$grid_jmin = 1;
			$grid_jmax = 196;
			break;
		default:
			echo "<br>Grid spacing not found";
			return array ( 99, 199 ); // impossible LAT/LONG
		}
		$RERTH = 6371200;
		// Pi = Atn(1) * 4    //3.14159
		//
		//        PRELIMINARY VARIABLES AND REDifINITIONS
		//
		//        H = 1 FOR NORTHERN HEMISPHERE = -1 FOR SOUTHERN
		//
		if ($ALATAN > 0) {
			$H = 1;
		} else {
			$H = -1;
		}
		//
		$RADPD = 3.141592654 / 180;
		$REBYDX = $RERTH / $DX;
		$ALATN1 = $ALATAN * $RADPD;
		$an = $H * sin($ALATN1);
		$COSLTN = cos($ALATN1);
		//
		//        MAKE SURE THAT INPUT LONGITUDES DO NOT PASS THROUGH
		//        THE CUT ZONE (FORBIDDEN TERRITORY) OF THE FLAT MAP
		//        AS MEASURED FROM THE VERTICAL (REFERENCE) LONGITUDE.
		//
		$ELON1L = $ELON1;
		if (($ELON1 - $ELONV) > 180) {
			$ELON1L = $ELON1 - 360;
		}
		if (($ELON1 - $ELONV) < -180) {
			$ELON1L = $ELON1 + 360;
		}
		$ELONL = $ELON;
		if (($ELON - $ELONV) > 180) {
			$ELONL = $ELON - 360;
		}
		if (($ELON - $ELONV) < -180) {
			$ELONL = $ELON + 360;
		}

		$ELONVR = $ELONV * $RADPD;

		//
		//        RADIUS TO LOWER LEFT HAND (LL) CORNER
		//
		$ALA1 = $Alat * $RADPD;
		//alert(ALA1);

		$RMLL = $REBYDX * pow($COSLTN, (1 - $an)) * pow((1 + $an), $an) * pow((cos($ALA1)) / (1 + $H * sin($ALA1)), $an) / $an;

		//
		//        USE LL POINT INFO TO LOCATE POLE POINT
		//

		$ELO1 = $ELON1L * $RADPD;
		$ARG = $an * ($ELO1 - $ELONVR);
		$POLEI = 1 - $H * $RMLL * sin($ARG);
		$POLEJ = 1 + $RMLL * cos($ARG);

		//
		//        RADIUS TO DESIRED POINT AND THE I J TOO
		//
		$ALA = $ALAT * $RADPD;
		$RM = $REBYDX * pow($COSLTN, (1 - $an)) * pow(1 + $an, $an) * pow((cos($ALA)) / (1 + $H * sin($ALA)), $an) / $an;

		$ELO = $ELONL * $RADPD;
		$ARG = $an * ($ELO - $ELONVR);

		$v2i = round($POLEI + $H * $RM * sin($ARG));
		$v2j = round($POLEJ - $RM * cos($ARG));

		$ximage = ((($v2i - $grid_imin) / ($grid_imax - $grid_imin) + ($image_maporiginx / $image_mapwidth)) * $image_mapwidth) * 2000;
		$yimage = (1 - (($v2j - $grid_jmin) / ($grid_jmax - $grid_jmin) + ($image_maporiginy / $image_mapheight) - 1) * $image_mapheight) * 2000;
	         
	    return array ( round($ximage) , round($yimage) );

	}
# ----------------------------------------------------------------------------------------------------
	public function GetDay ( $type_string )
	{
	/*
	Source Of Daya data URLs - note there is always 7 days worth, with today being day 1:
		Today =
			http://rasp.inn.leedsmet.ac.uk/UK12/FCST/
		Monday = 
			http://rasp.inn.leedsmet.ac.uk/Monday/FCST/
		Tuesday = 
			http://rasp.inn.leedsmet.ac.uk/Tuesday/FCST/
		Wednesday = 
			http://rasp.inn.leedsmet.ac.uk/Wednesday/FCST/
		Thursday = 
			http://rasp.inn.leedsmet.ac.uk/Thursday/FCST/
		Friday = 
			http://rasp.inn.leedsmet.ac.uk/Friday/FCST/
		Saturday = 
			http://rasp.inn.leedsmet.ac.uk/Saturday/FCST/
		Sunday = 
			http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/
	*/
		$output="UK12";
		switch ($type_string) {
			case ($type_string == 'UK12'):
				$output= "UK12";
				break;
			case ($type_string == 'UK4'):
				$output= "UK4";
				break;
			case ($type_string == 'MONDAY' || $type_string == 'monday'  ||$type_string == 'Monday'):
				$output= "Monday";
				break;
			case ($type_string == 'MON' || $type_string == 'mon'  ||$type_string == 'Mon'):
				$output= "Monday";
				break;
			case ($type_string == 'TUESDAY' || $type_string == 'tuesday'  ||$type_string == 'Tuesday'):
				$output= "Tuesday";
				break;
			case ($type_string == 'TUE' || $type_string == 'tue'  ||$type_string == 'Tue'):
				$output= "Tuesday";
				break;
			case ($type_string == 'WEDNESDAY' || $type_string == 'wednesday'  ||$type_string == 'Wednesday'):
				$output= "Wednesday";
				break;
			case ($type_string == 'WED' || $type_string == 'wed'  ||$type_string == 'Wed'):
				$output= "Wednesday";
				break;
			case ($type_string == 'THURSDAY' || $type_string == 'thursday'  ||$type_string == 'Thursday'):
				$output= "Thursday";
				break;
			case ($type_string == 'THU' || $type_string == 'thu'  ||$type_string == 'Thu'):
				$output= "Thursday";
				break;
			case ($type_string == 'FRIDAY' || $type_string == 'friday'  ||$type_string == 'Friday'):
				$output= "Friday";
				break;
			case ($type_string == 'FRI' || $type_string == 'fri'  ||$type_string == 'Fri'):
				$output= "Friday";
				break;
			case ($type_string == 'SATURDAY' || $type_string == 'saturday'  ||$type_string == 'Saturday'):
				$output= "Saturday";
				break;
			case ($type_string == 'SAT' || $type_string == 'sat'  ||$type_string == 'Sat'):
				$output= "Saturday";
				break;
			case ($type_string == 'SUNDAY' || $type_string == 'sunday'  ||$type_string == 'Sunday'):
				$output= "Sunday";
				break;
			case ($type_string == 'SUN' || $type_string == 'sun'  ||$type_string == 'Sun'):
				$output= "Sunday";
				break;
			case ($type_string == 'UK4%2b1' || $type_string == 'uk4%2b1' || $type_string == 'UK4+1' || $type_string == 'uk4+1'  ):
				$output= "UK4%2B1";
				break;
			case ($type_string == 'UK4%2b2' || $type_string == 'uk4%2b2' || $type_string == 'UK4+2' || $type_string == 'uk4+2'  ):
				$output= "UK4%2B2";
				break;
			case ($type_string == 'UK2' || $type_string == 'uk2' || $type_string == 'UK2' || $type_string == 'uk2'  ):
				$output= "UK2";
				break;
			case ($type_string == 'UK2%2b1' || $type_string == 'uk2%2b1' || $type_string == 'UK2+1' || $type_string == 'uk2+1'  ):
				$output= "UK2%2B1";
				break;
			default:
				echo "<p>The day name provided:'".$type_string."'. is not of the correct format.</br>";
				echo "Valid days are: <br>Monday, Tuesday, Wednesday, Thursday, Friday, Saturday or Sunday.";
				echo "<br>monday, tuesday, wednesday, thursday, friday, saturday or sunday.";
				echo "<br>Mon, Tue, Wed, Thu, Fri, Sat or Sun.";
				exit(1);
			}
			if ($this->bDebugMode) { echo "<p>Processed ok output='".$output."'"; }
			return $output;
	}

# ----------------------------------------------------------------------------------------------------
	public function GetFunctionType ( $type_string )
	{

	/*
	Data sources under each day are:

		Note: All files are numbered from 0600 to 1800 (in the URLs) in 30 min segments
		Note: Data file described here: http://www.drjack.info/twiki/bin/view/RASPop/DataFileDescription
		
		No Sfc. Wind
		No BL Avg. Wind
		No Wind at BL Top 
		No OD Cloudbase where ODpotential>0 
		
		Cambridge Sounding = 
			http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/sounding5.curr.0600lst.d2.png
	*/
		global $gGraphTitle, $gGraphYAxis, $gGraphLegend, $gCompressYAxis;

		$output="wstar"; // default
		switch ($type_string) {
			case ($type_string == 'wstar'):
				$output= "wstar";
				// Thermal Updraft Velocity (W*) [ft/min] = 
			//            http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/wstar.curr.0600lst.d2.data
					$gGraphTitle = "Thermal Updraft Velocity (W*) [ft/min]";
					$gGraphYAxis = "Thermal Updraft Velocity (W*) [ft/min]";
					$gGraphLegend = "Thermal Updraft Velocity (W*) [ft/min]";

				break;
			case ($type_string == 'hbl'):
				$output= "hbl";
				// Height of BL Top [ft] = 
			//             http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/hbl.curr.0600lst.d2.data
					$gGraphTitle = "Height of BL Top [ft]";
					$gGraphYAxis = "Altitude [ft]";
					$gGraphLegend = "Height of BL Top [ft]";
				break;
			case ($type_string == 'bsratio'):
				$output= "bsratio";
				// Buoyancy/Shear Ratio Truncated~C~ = 
			//         http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/bsratio.curr.0600lst.d2.data
					$gGraphTitle = "Buoyancy/Shear Ratio Truncated~C~";
					$gGraphYAxis = "Buoyancy/Shear Ratio Truncated~C~";
					$gGraphLegend = "Buoyancy/Shear Ratio Truncated~C~";
				break;
			case ($type_string == 'hwcrit'):
				$output= "hwcrit";
				//Height of Critical Updraft Strength (Hcrit) [ft] = 
			//http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/hwcrit.curr.0600lst.d2.data
					$gGraphTitle = "Ht Critical Updraft Strength (Hcrit) [ft]";
					$gGraphYAxis = "Altitude";
					$gGraphLegend = "Ht Critical Updraft Strength (Hcrit) [ft]";
				break;
			case ($type_string == 'dwcrit'):
				$output= "dwcrit";
				//Depth of Critical Updraft Strength (AGL Hcrit) [ft] = 
			//http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/dwcrit.curr.0600lst.d2.data
					$gGraphTitle = "Depth Critical Updraft Strength (AGL Hcrit) [ft]";
					$gGraphYAxis = "Altitude";
					$gGraphLegend = "Depth Critical Updraft Strength (AGL Hcrit) [ft]";
				break;
			case ($type_string == 'dbl'):
				$output= "dbl";
				//BL Depth [ft] =
			//http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/dbl.curr.0600lst.d2.data
					$gGraphTitle = "BL Depth [ft]";
					$gGraphYAxis = "Altitude [ft]";
					$gGraphLegend = "BL Depth [ft]";
				break;
			case ($type_string == 'bltopvariab'):
				$output= "bltopvariab";
				//BL Top Uncertainty/Variability (for +1degC) =
			//http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/bltopvariab.curr.0600lst.d2.data
					$gGraphTitle = "BL Top Uncertainty/Variability (for +1degC)";
					$gGraphYAxis = "Altitude [ft]";
					$gGraphLegend = "BL Top Uncertainty/Variability (for +1degC)";
				break;
			case ($type_string == 'sfcshf'):
				$output= "sfcshf";
				//Sfc. Heating [W/m~S~2~N~] =
			//http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/sfcshf.curr.0600lst.d2.data
					$gGraphTitle = "Sfc. Heating [W/m~S~2~N~]";
					$gGraphYAxis = "[W/m~S~2~N~]";
					$gGraphLegend = "Sfc. Heating [W/m~S~2~N~]";
				break;
			case ($type_string == 'sfcsunpct'):
				$output= "sfcsunpct";
				//Normalized Sfc. Solar Radiation [%]
			//http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/sfcsunpct.curr.0600lst.d2.data
					$gGraphTitle = "Normalized Sfc. Solar Radiation [%]";
					$gGraphYAxis = "Normalized Sfc. Solar Radiation [%]";
					$gGraphLegend = "Normalized Sfc. Solar Radiation [%]";
				break;
			case ($type_string == 'sfctemp'):
				$output= "sfctemp";
				//Surface Temperature [C]
			//http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/sfctemp.curr.0600lst.d2.data
					$gGraphTitle = "Surface Temp (2m AGL) [C]";
					$gGraphYAxis = "Surface Temp (2m AGL) [C]";
					$gGraphLegend = "Surface Temp (2m AGL) [C]";
				break;
			case ($type_string == 'sfcdewpt'):
				$output= "sfcdewpt";
				//Surface Dew Point Temperature [C]
			//http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/sfcdewpt.curr.0600lst.d2.data
					$gGraphTitle = "Surface Dew Point Temp (2m AGL) [C]";
					$gGraphYAxis = "Surface Dew Point Temp (2m AGL) [C]";
					$gGraphLegend = "Surface Dew Point Temp (2m AGL) [C]";
				break;
			case ($type_string == 'mslpress'):
				$output= "mslpress";
				//Mean Sea Level Pressure mb
			//http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/mslpress.curr.0600lst.d2.data
					$gGraphTitle = "Mean Sea Level Pressure mb";
					$gGraphYAxis = "Mean Sea Level Pressure mb";
					$gGraphLegend = "Mean Sea Level Pressure mb";
					$gCompressYAxis = 1;

					// may want to set the upper/lower y axis range for this?
				break;
			case ($type_string == 'zsfclcldif'):
				$output= "zsfclcldif";
			//http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/zsfclcldif.curr.0600lst.d2.data
					$gGraphTitle = "Cu Potential [ft]";
					$gGraphYAxis = "Altitude [ft]";
					$gGraphLegend = "Cu Potential [ft]";
				break;
			case ($type_string == 'blwindshear'):
				$output= "blwindshear";
			//http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/blwindshear.curr.0600lst.d2.data
					$gGraphTitle = "BL Vertical Wind Shear [kt]";
					$gGraphYAxis = "Wind Shear [kt]";
					$gGraphLegend = "BL Vertical Wind Shear [kt]";
				break;
			case ($type_string == 'wblmaxmin'):
				$output= "wblmaxmin";
			//http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/wblmaxmin.curr.0600lst.d2.data
					$gGraphTitle = "BL Max. Up/Down Motion [cm/s]";
					$gGraphYAxis = "BL Max. Up/Down Motion [cm/s]";
					$gGraphLegend = "BL Max. Up/Down Motion [cm/s]";
				break;
			case ($type_string == 'zsfclcl'):
				$output= "zsfclcl";
			//http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/zsfclcl.curr.0600lst.d2.data
					$gGraphTitle = "Cu Cloudbase (Sfc. LCL) [ft]";
					$gGraphYAxis = "Cu Cloudbase (Sfc. LCL) [ft]";
					$gGraphLegend = "Cu Cloudbase (Sfc. LCL) [ft]";
				break;
			case ($type_string == 'zblcldif'):
				$output= "zblcldif";
			//http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/zblcldif.curr.0600lst.d2.data
					$gGraphTitle = "Overcast Dev Potential [ft]";
					$gGraphYAxis = "Overcast Dev Potential [ft]";
					$gGraphLegend = "Overcast Dev Potential [ft]";
				break;
			case ($type_string == 'blcwbase'):
				$output= "blcwbase";
			//http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/blcwbase.curr.0600lst.d2.data
					$gGraphTitle = "BL Explicit Cloud Base [AGL] (CloudWater>1e-05) [ft AGL - max=18000";
					$gGraphYAxis = "Altitude [ft]";
					$gGraphLegend = "BL Explicit Cloud Base [AGL] (CloudWater>1e-05) [ft AGL - max=18000";
				break;
			case ($type_string == 'blcloudpct'):
				$output= "blcloudpct";
			//http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/blcloudpct.curr.0600lst.d2.data
					$gGraphTitle = "BL Cloud Cover [%]";
					$gGraphYAxis = "BL Cloud Cover [%]";
					$gGraphLegend = "BL Cloud Cover [%]";
				break;
			case ($type_string == 'rain1'):
				$output= "rain1";
			//http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/rain1.curr.0600lst.d2.data
					$gGraphTitle = "1 hr Accumulated Rain [mm]";
					$gGraphYAxis = "1 hr Accumulated Rain [mm]";
					$gGraphLegend = "1 hr Accumulated Rain [mm]";
				break;
			case ($type_string == 'cape'):
				$output= "cape";
			//http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/cape.curr.0600lst.d2.data
					$gGraphTitle = "CAPE [J/kg]";
					$gGraphYAxis = "CAPE [J/kg]";
					$gGraphLegend = "CAPE [J/kg] (Thunderstorm Development)";
				break;
			case ($type_string == 'stars'):
				$output= "stars";
			//http://rasp.inn.leedsmet.ac.uk/Sunday/FCST/stars.curr.0600lst.d2.data
					$gGraphTitle = "Stars (Rating)";
					$gGraphYAxis = "Stars (Rating)";
					$gGraphLegend = "Stars (Rating)";
				break;
			default:
				//echo "<p>No Function recognised for the type given='".$type_string."'. Defaults to ".$output."</p>";
			}
			return $output;
	}	
# ----------------------------------------------------------------------------------------------------
	public function SplitEntry ( $entry )
	{
		$temp = substr($entry,13);
		$slice = array_slice(str_split($temp,8),0);		
		$result = array_slice($slice,0,count($slice)-2);
		foreach ($result as &$value) {
			$value = trim($value);
		}
		return $result;
	}

#------------------------------------------------------------------------------

} // end class
?>

