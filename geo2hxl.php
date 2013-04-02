<?php
//Geo2HXL.php: Reads csv of a single admin layer (converted to csv with WKT geom using ogr2ogr) to HXL triples.  

//CHANGE LOG
//Version 13 02/04/2013: fix added to handle strangely formatted p-codes (e.g. "     3.000000")
//Version 12 21/11/2012: fixed declaration of Admin0 to be hxl:atLevel Admin0
//Version 11 20/11/2012: added logic so that the pcode for the Country does not need to be in the file for Admin 1 (is declared directly as a variable in the configuration)
//Version 10 16/11/2012: added a couple more characters to the deaccent function
//Version 9 15/11/2012: added logic to handle a populated place class that is not matched in the array (returns class = "unknown")
//Version 8 13/11/2012: added configuration variables for country name and pcode for admin 0 so that they don't have to be included in the csv as explicit field values.
//Version 7 13/11/2012: MAJOR REVISION - Adding logic to handle populated places and to reject rows with missing values.
//Version 6 07/11/2012: Modifications to the data container elements to reflect the ValidOn property.
//Version 5 17/8/2012: Adds declaration for rdf:type hxl:country .
//Version 4: Adds in logic to truncate to desired precision of WKT
//Version 3: This version checks for whether or not the admin level being processed is level 0 (the national boundary).  It ignores the atLocation property in this case.
//Version 2: This version has revisions to map to latest version of URI patterns and the Geolocation Standard.  It adds the writing of prefixes and the DataContainer.

//THINGS IT DOESN'T DO:
//Doesn't parse the date stamp (used for the data container name) to also get the dc:date literal.  dc:date is specified in the configuration.
//Labels all data containers with the optional organization value set to "unocha".  A good future addition would be to make this configurable.
//Some metadata elements for the datacontainer are missing (reported by, for example).  
//Although it declares a feature to be at it's AdminUnitLevel, no further definition is given to these levels (the title of the level or the country to which it belongs).  
//For the populated places classification, this script doesn't allow a delcaration of what a given class represents.  HXL has the ability to declare a "title" for a given class (ie: to indicate that "1stOrder" in HXL for a given country = "National Capital").  It would be good to add some logic to make these declartions.  The same is true for the admin levels, which can have titles, but this script doesn't handle declaring those.

//-------------------CONFIGURATION-----------------------------------------------------
//Delcare below which csv columns contain which data (first column = 0) and other needed info from user
//See http://sites.google.com/site/ochaimwiki/cod-fod-guidance/administrative-boundaries for guidance on these fields
//Also, configure the code that writes the prefixes at the beginning of the output file.  This is in the "add headers" section a bit further down.

//The sample configuration below is for BFA Admin 0
//For convenience, paste the CSV header row here:
//WKT,CNTRY_NAME,CNTRY_CODE

$countryName = "Pakistan" ; //Only used when processing Admin 0, otherwise ignored.
$countryPcode = "PAK" ; //Only used when processing Admin 0 and Admin 1, otherwise ignored.  This means that the pcode for the country itself does not need to be included in the Admin0 or Admin1 CSV input.  For lower admin levels (admin > 1), the pcode of the level immediately above must be included.

$country_iso3 = "pak" ; //ISO 3166 Alpha3 three letter code for the country, lower case.  This becomes part of the URI for the features generated by the script.
$geom_element = 0 ; //which column contains the WKT geometry. First column = 0.
$level_n_pcode_element = 2 ; //which column contains the pcode for the level that is being converted.  First column = 0. Ignored if $n = 0. See below.
$level_n_minus_one_pcode_element = 0 ;  //which column contains the pcode for the admin unit one level above the level that is being converted. This is ignored if $n = 0 or $n = 1 (See below).
$n = 1 ; //base admin level being processed. Set to 0 if you are processing the national boundary. Set to 999 if you are processing the populated places layer.  Otherwise set to the admin level you are processing: 1, 2, 3, etc.
$featureName_element = 1 ; //which column contains the feature name. Ignored if $n = 0.
$featureRefName_element = 1 ; //which column contains the feature ref name.  Note that the script will automatically change accented characters if found, so this can be the same column as the $featureName_element. Ignored if $n = 0.
$popPlaceClass_element = 15 ; //for populated places, which column contains the class number.  Note that these must be numbers with the lower numbers representing higher status in the hierarchy.  See http://hxl.humanitarianresponse.info for details.  Ignored if $n != 999.

//Populated Place Classes/Types/Categories Translation -------------------------------------------------------
//The list of elements below describes a translation between any classification of populated places in your dataset to the HXL system.  This information is primarily used for cartographic symbolization of the populated place data.  These settings are ignored if $n != 999.
//If your data doesn't have information for a given class, just leave it blank (keep the 'ignored').  Otherwise, put the value in your dataset that would indicate the status of a populated place equal to what is in the [ ].   It is recommended that your biggest/most important code be given 1st order, your smallest/least important code be given 10th order, with other ranks being distributed between.  Blank orders are acceptable.  
$pplClass['_1stOrder'] = 'ignored' ;
$pplClass['_2ndOrder'] = 'ignored' ;
$pplClass['_3rdOrder'] = 'ignored' ;
$pplClass['_4thOrder'] = 'ignored' ;
$pplClass['_5thOrder'] = 'ignored' ;
$pplClass['_6thOrder'] = 'ignored' ;
$pplClass['_7thOrder'] = 'ignored' ;
$pplClass['_8thOrder'] = 'ignored' ;
$pplClass['_9thOrder'] = 'ignored' ;
$pplClass['_10thOrder'] = 'ignored' ;

//Processing settings
$precision = 7 ; //number of decimal places to which the WKT coordinates will be truncated. For Decimal Degrees, 7 yields approximately cm precision and is the recommended value.  The default ogr2ogr output is 15 decimal places, about the radius of a hydrogen atom.
$file_to_process = "/Users/carsten/Desktop/Pakistan/Pak_adm1_pco_20110324.csv" ;
$output_file_name = "/Users/carsten/Desktop/Pakistan/Pak_adm1_pco_20110324.ttl" ;

//Metadata items
$dcdate = "2013-04-02T17:45:00.0Z" ; //the date the file is created.  Format must be ISO 8601 format (level of granularity below the day is optional).
$validon = "2011-03-24" ;  //Beginning date for which this dataset is the valid one (in ISO 8601 format, level of granularity below the day is optional). This value is applied to the data container (i.e. named graph) which holds the data.  The end of the period of validity for this dataset is the first later ValidOn for a given feature.

//--------------FUNCTIONS--------------------------------------------------------------------------------------
function truncate($precision, $current_geom)
	{
	$output = "" ;
	$geom_length = strlen($current_geom) ;
	$char_counter = 99;
	$counting = false;
	for ($i = 0; $i <= $geom_length-1; $i++)
		{
		$current_char = $current_geom[$i];
		if ($current_char == ".")
			{
			$char_counter = $precision + 1;
			$counting = true;
			}
		elseif (in_array ($current_char, array(" " , "," , ")")))
			{
			$char_counter = 99;
			$counting = false;
			}
		if (!$counting)
			{
			$output = $output . $current_char ;
			}
		elseif ($char_counter <= $precision + 1 and $char_counter > 0 )
			{
			$output = $output . $current_char ;
			}
		if ($counting)
			{$char_counter-- ;}
		}
	return $output ;
	}
	
function deaccent($x)  //used to clean refnames
	{
	$search = explode(",","�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,e,i,�,u,�,�");
	$replace = explode(",","c,ae,oe,a,e,i,o,u,a,e,i,o,u,a,e,i,o,u,y,a,e,i,o,u,a,e,i,o,u,O,A");
	$output = str_replace($search, $replace, $x);
	return $output ;
	}

//--------------DECLARE FIXED PARTS OF URIs--------------------------------------------------------------------

$base_data_uri = "<http://hxl.humanitarianresponse.info/data/" ;
$base_locations_uri = "<http://hxl.humanitarianresponse.info/data/locations/admin/" ;
$ns_uri = "hxl:" ;
$geo_ns_uri = "geo:" ;
$dc_ns_uri = "dc:" ;
$AdminUnit_id = "AdminUnit" ;
$PopulatedPlace_id = "PopulatedPlace" ;
$atLocation_id = "atLocation" ;
$atLevel_id = "atLevel" ;
$inClass_id = "inClass" ;
$pcode_id = "pcode" ;
$featureName_id = "featureName" ;
$featureRefName_id = "featureRefName" ;
$hasGeometry_id = "hasGeometry" ;
$geom_uri = "geom" ;
$hasSerialization_id = "hasSerialization" ;
$wktLiteral_id = "wktLiteral";
$Geometry_id = "Geometry" ;
$DataContainer_id = "DataContainer" ;
$Country_id = "Country" ;


//------------------------START PROCESSING--------------------------------------------

$csv_handle = fopen($file_to_process,"r") or exit ("Unable to open") ;
$output = fopen($output_file_name,"w") or exit ("Unable to create new file") ;

$base_uri = $base_locations_uri . $country_iso3 . "/" ;

//add headers
fwrite($output , "@prefix hxl: <http://hxl.humanitarianresponse.info/ns/#> .
@prefix geo: <http://www.opengis.net/ont/geosparql#> .
@prefix dc: <http://purl.org/dc/terms/> .\n") ;
//create DataContainer and associate metadata items
$time = gettimeofday(); //get time for timestamp which is used as datacontainer name (according to the HXL standard for URI patterns)
$timestamp = $time['sec'] . "." . $time['usec'] ;
fwrite ($output , $base_data_uri . "datacontainers" . "/" . "unocha/" . $timestamp . "> a " . $ns_uri . $DataContainer_id . " .\n") ;
fwrite ($output , $base_data_uri . "datacontainers" . "/" . "unocha/" . $timestamp . "> " . $dc_ns_uri . "date " . "\"" . $dcdate . "\"" . " .\n") ;
fwrite ($output , $base_data_uri . "datacontainers" . "/" . "unocha/" . $timestamp . "> " . $ns_uri . "validOn " . "\"" . $validon . "\"" . " .\n") ;
/*if (strlen($validityEnd) > 3) //must have at least 4 chars to be a year
	{ fwrite ($output , $base_data_uri . "datacontainers" . "/" . "unocha/" . $timestamp . " " . $ns_uri . "validityEnd " . "\"" . $validityEnd . "\"" . " .\n") ;}*/

fgetcsv($csv_handle,0,",") ; //reads and discards the first line
$csvline = 2 ; //set line counter for error reporting
while(!feof($csv_handle))
	{
	$current = fgetcsv($csv_handle,0,",") ;
	//test for blank line (usually last line at end), if so, exit the while loop
	if (count($current)==1)
		{break;}
	
	//check for missing data and write an error if found
	if ($n == 999) //handles populated places (not admin units)
		{$reject = "keep" ;
		$testarray = array($current[$level_n_minus_one_pcode_element],$current[$level_n_pcode_element],$current[$featureName_element],$current[$featureRefName_element],$current[$popPlaceClass_element],$current[$geom_element]) ;
		for ($i = 1; $i <= count($testarray); $i++)
			{if ((empty($testarray[$i-1]) &&  $testarray[$i-1] != 0) || ($testarray[$i-1] == ""))
				{$reject = "reject" ;}
			}
		}
	elseif ($n == 0) //handles national boundaries (admin 0)
		{$reject = 'keep' ;
		$testarray = array($countryPcode,$countryName,$current[$geom_element]) ;
		for ($i = 1; $i <= count($testarray); $i++)
			{if ((empty($testarray[$i-1]) &&  $testarray[$i-1] != 0) || ($testarray[$i-1] == ""))
				{$reject = "reject" ;}
			}
		}
	elseif ($n == 1) //handles admin 1 
		{$reject = 'keep' ;
		$testarray = array($countryPcode,$current[$level_n_pcode_element],$current[$featureName_element],$current[$featureRefName_element],$current[$geom_element]) ;
		for ($i = 1; $i <= count($testarray); $i++)
			{if ((empty($testarray[$i-1]) &&  $testarray[$i-1] != 0) || ($testarray[$i-1] == ""))
				{$reject = "reject" ;}
			}
		}
	
	elseif ($n > 1 && $n < 999) //handles sub-national boundaries (admin > 1)
		{$reject = 'keep' ;
		$testarray = array($current[$level_n_minus_one_pcode_element],$current[$level_n_pcode_element],$current[$featureName_element],$current[$featureRefName_element],$current[$geom_element]) ;
		for ($i = 1; $i <= count($testarray); $i++)
			{if ((empty($testarray[$i-1]) &&  $testarray[$i-1] != 0) || ($testarray[$i-1] == ""))
				{$reject = "reject" ;}
			}
		}
	else 
		{echo "Variable n is set to an illegal value which is > 999.  Exiting script.";
		fclose($csv_handle) ;
		fclose($output) ;
		exit() ;
		}
	if ($reject == "reject")
		{echo ("
		Rejected line " . $csvline . " due to missing data") ;
		}
	else //generate all the triples for the current line of the CSV
		//set up the base URI that is reused in most of the triples
		if ($n > 0){
			// remove blanks from pcode
			$current[$level_n_pcode_element] = trim($current[$level_n_pcode_element]);

			// remove "digits" from pcode (e.g. "3.000000" -> "3")
			$arr = explode(".", $current[$level_n_pcode_element]);
			$current[$level_n_pcode_element] = $arr[0];

			$admunit_uri = $base_uri . $current[$level_n_pcode_element] . ">" ; 
		} else {
			$admunit_uri = $base_uri . $countryPcode . ">" ; 
		}
		
		//create the feature and its basic attributes
		if ($n == 0) //handles admin 0 (national boundaries)
			{fwrite($output , $admunit_uri . " a " . $ns_uri . $Country_id . " .\n") ; 
			fwrite($output , $admunit_uri . " " . $ns_uri . $atLevel_id . " " . $base_uri . "adminlevel" . $n . "> .\n") ;
			fwrite($output , $admunit_uri . " " . $ns_uri . $pcode_id . " \"" . $countryPcode . "\" .\n") ;
			fwrite($output , $admunit_uri . " " . $ns_uri . $featureName_id . " \"" . $countryName . "\" .\n") ;
			fwrite($output , $admunit_uri . " " . $ns_uri . $featureRefName_id . " \"" . deaccent($countryName) . "\" .\n") ;
			fwrite($output , $base_uri . $countryPcode . "/" . $geom_uri . ">" . " a " . $geo_ns_uri . $Geometry_id . " .\n") ;
			fwrite($output , $admunit_uri . " " . $geo_ns_uri . $hasGeometry_id . " " . $base_uri . $countryPcode . "/" . $geom_uri . "> .\n") ;
			fwrite($output , $base_uri . $countryPcode . "/" . $geom_uri . "> " . $geo_ns_uri . $hasSerialization_id . " " . "\"" . truncate($precision,$current[$geom_element]) . "\"^^" . $geo_ns_uri . $wktLiteral_id . " .\n") ;
			}
		elseif ($n == 1) //handles sub-national admin boundaries 
			{fwrite($output , $admunit_uri . " a " . $ns_uri . $AdminUnit_id . " .\n") ; 
			fwrite($output , $admunit_uri . " " . $ns_uri . $atLevel_id . " " . $base_uri . "adminlevel" . $n . "> .\n") ;
			fwrite($output , $admunit_uri . " " . $ns_uri . $atLocation_id . " " . $base_uri . $countryPcode . "> .\n") ;
			fwrite($output , $admunit_uri . " " . $ns_uri . $pcode_id . " \"" . $current[$level_n_pcode_element] . "\" .\n") ;
			fwrite($output , $admunit_uri . " " . $ns_uri . $featureName_id . " \"" . $current[$featureName_element] . "\" .\n") ;
			fwrite($output , $admunit_uri . " " . $ns_uri . $featureRefName_id . " \"" . deaccent($current[$featureRefName_element]) . "\" .\n") ;
			fwrite($output , $base_uri . $current[$level_n_pcode_element] . "/" . $geom_uri . ">" . " a " . $geo_ns_uri . $Geometry_id . " .\n") ;
			fwrite($output , $admunit_uri . " " . $geo_ns_uri . $hasGeometry_id . " " . $base_uri . $current[$level_n_pcode_element] . "/" . $geom_uri . "> .\n") ;
			fwrite($output , $base_uri . $current[$level_n_pcode_element] . "/" . $geom_uri . "> " . $geo_ns_uri . $hasSerialization_id . " " . "\"" . truncate($precision,$current[$geom_element]) . "\"^^" . $geo_ns_uri . $wktLiteral_id . " .\n") ;
			}
		elseif ($n > 0 && $n < 999) //handles sub-national admin boundaries 
			{fwrite($output , $admunit_uri . " a " . $ns_uri . $AdminUnit_id . " .\n") ; 
			fwrite($output , $admunit_uri . " " . $ns_uri . $atLevel_id . " " . $base_uri . "adminlevel" . $n . "> .\n") ;
			fwrite($output , $admunit_uri . " " . $ns_uri . $atLocation_id . " " . $base_uri . $current[$level_n_minus_one_pcode_element] . "> .\n") ;
			fwrite($output , $admunit_uri . " " . $ns_uri . $pcode_id . " \"" . $current[$level_n_pcode_element] . "\" .\n") ;
			fwrite($output , $admunit_uri . " " . $ns_uri . $featureName_id . " \"" . $current[$featureName_element] . "\" .\n") ;
			fwrite($output , $admunit_uri . " " . $ns_uri . $featureRefName_id . " \"" . deaccent($current[$featureRefName_element]) . "\" .\n") ;
			fwrite($output , $base_uri . $current[$level_n_pcode_element] . "/" . $geom_uri . ">" . " a " . $geo_ns_uri . $Geometry_id . " .\n") ;
			fwrite($output , $admunit_uri . " " . $geo_ns_uri . $hasGeometry_id . " " . $base_uri . $current[$level_n_pcode_element] . "/" . $geom_uri . "> .\n") ;
			fwrite($output , $base_uri . $current[$level_n_pcode_element] . "/" . $geom_uri . "> " . $geo_ns_uri . $hasSerialization_id . " " . "\"" . truncate($precision,$current[$geom_element]) . "\"^^" . $geo_ns_uri . $wktLiteral_id . " .\n") ;
			}
		elseif ($n == 999) //handles populated places
			{fwrite($output , $admunit_uri . " a " . $ns_uri . $PopulatedPlace_id . " .\n") ; 
			fwrite($output , $admunit_uri . " " . $ns_uri . $atLocation_id . " " . $base_uri . $current[$level_n_minus_one_pcode_element] . "> .\n") ;
			//determine ppl class from pplClass array
			$class = array_search($current[$popPlaceClass_element],$pplClass) ;
			if ($class != FALSE)
				{fwrite($output , $admunit_uri . " " . $ns_uri . $inClass_id . " " . $base_uri . "pplclass/" . $class . "> .\n") ;
				}
			else
				{fwrite($output , $admunit_uri . " " . $ns_uri . $inClass_id . " " . $base_uri . "pplclass/" . "unknown" . "> .\n") ;
				}
			fwrite($output , $admunit_uri . " " . $ns_uri . $pcode_id . " \"" . $current[$level_n_pcode_element] . "\" .\n") ;
			fwrite($output , $admunit_uri . " " . $ns_uri . $featureName_id . " \"" . $current[$featureName_element] . "\" .\n") ;
			fwrite($output , $admunit_uri . " " . $ns_uri . $featureRefName_id . " \"" . deaccent($current[$featureRefName_element]) . "\" .\n") ;
			fwrite($output , $base_uri . $current[$level_n_pcode_element] . "/" . $geom_uri . ">" . " a " . $geo_ns_uri . $Geometry_id . " .\n") ;
			fwrite($output , $admunit_uri . " " . $geo_ns_uri . $hasGeometry_id . " " . $base_uri . $current[$level_n_pcode_element] . "/" . $geom_uri . "> .\n") ;
			fwrite($output , $base_uri . $current[$level_n_pcode_element] . "/" . $geom_uri . "> " . $geo_ns_uri . $hasSerialization_id . " " . "\"" . truncate($precision,$current[$geom_element]) . "\"^^" . $geo_ns_uri . $wktLiteral_id . " .\n") ;
			}
	$csvline ++;
	}
 
 //close the files
 fclose($csv_handle) ;
 fclose($output) ;

 echo 'Done. Output written to '.$output_file_name.'.';


?>