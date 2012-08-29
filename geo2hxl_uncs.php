<?php
//Geo2HXL_UNCS.php: Reads csv of global international boundaries and writes to HXL format.  Adapted from geo2hxl.php.  

//-------------------CONFIGURATION-----------------------------------------------------
//Delcare below which csv columns contain which data (first column = 0) and other needed info from user
//See http://sites.google.com/site/ochaimwiki/cod-fod-guidance/administrative-boundaries for guidance on these fields
//Also, configure the code that writes the prefixes at the beginning of the output file.  This is in the "add headers" section a bit further down.

//UNCS global 1:1m national boundaries dataset
//CSV header row: WKT,OBJECTID,ISO3_CODE,STATUS,CAPITAL,Terr_ID,Terr_Name,Color_Code,Shape_Leng,Shape_Area
$geom_element = 0 ; //which column contains the WKT geometry. First column = 0.
$level_n_pcode_element = 2 ; //which column contains the pcode for the level that is being converted
$level_n_minus_one_pcode_element = "ignored" ;  //which column contains the pcode for the admin unit one level above the level that is being converted. This is ignored if $n = 0 (See below).
$n = 0 ; //base admin level being processed. Set to 0 if you are processing the national boundary.
$featureName_element = 6 ; //which column contains the feature name
$featureRefName_element = 6 ; //which column contains the feature ref name 
//$country_code = "bfa" ; //ISO 3 letter code for the country, lower case
$precision = 7 ; //number of decimal places to which the WKT coordinates will be truncated. For Decimal Degrees, 7 yields approximately cm precision.  The default ogr2ogr output is 15 decimal places, about the radius of a hydrogen atom.
$file_to_process = "wrl_polbnda_int_1m_uncs.csv" ;
$output_file_name = "wrl_polbnda_int_1m_uncs.ttl" ;
//Metadata items
$dcdate = "2012-08-16T11:16:00.0Z" ; //the date the file is created
$validityStart = "2012-07-24" ;  //Beginning date for which this dataset is the valid one.  This value is applied to the data container (named graph) which holds the data.  
$validityEnd = "" ;  //Blank indicates that the dataset is currently valid.

//--------------FUNCTIONS--------------------------------------------------------------------------------------

/*function truncate($precision, $current_geom)  //used to truncate excessively long WKT output to 7 decimal places (about 1cm if using decimal degrees)
	{
	$output = "" ;
	$geom_length = strlen($current_geom) ;
	echo $geom_length ;
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
	}*/
	
function deaccent($x)  //used to clean refnames
	{
	$search = explode(",","ç,æ,œ,á,é,í,ó,ú,à,è,ì,ò,ù,ä,ë,ï,ö,ü,ÿ,â,ê,î,ô,û,å,e,i,ø,u");
	$replace = explode(",","c,ae,oe,a,e,i,o,u,a,e,i,o,u,a,e,i,o,u,y,a,e,i,o,u,a,e,i,o,u");
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
$atLocation_id = "atLocation" ;
$atLevel_id = "atLevel" ;
$pcode_id = "pcode" ;
$featureName_id = "featureName" ;
$featureRefName_id = "featureRefName" ;
$hasGeometry_id = "hasGeometry" ;
$geom_uri = "geom" ;
$hasSerialization_id = "hasSerialization" ;
$wktLiteral_id = "wktLiteral" ;
$Geometry_id = "Geometry" ;
$DataContainer_id = "DataContainer" ;
$Country_id = "Country" ;


//------------------------START PROCESSING--------------------------------------------

$csv_handle = fopen($file_to_process,"r") or exit ("Unable to open") ;
$output = fopen($output_file_name,"w") or exit ("Unable to create new file") ;

//$base_uri = $base_locations_uri . $country_code . "/" ;

//add headers
fwrite($output , "@prefix hxl: <http://hxl.humanitarianresponse.info/ns/#> .
@prefix geo: <http://www.opengis.net/ont/geosparql#> .
@prefix dc: <http://purl.org/dc/terms/> .\n") ;
//create DataContainer and associate metadata items
$time = gettimeofday(); //get time for timestamp which is used as datacontainer name (according to the HXL standard for URI patterns)
$timestamp = $time['sec'] . "." . $time['usec'] ;
fwrite ($output , $base_data_uri . "datacontainers" . "/" . "unocha/" . $timestamp . "> a " . $ns_uri . $DataContainer_id . " .\n") ;
fwrite ($output , $base_data_uri . "datacontainers" . "/" . "unocha/" . $timestamp . "> " . $dc_ns_uri . "date " . "\"" . $dcdate . "\"" . " .\n") ;
fwrite ($output , $base_data_uri . "datacontainers" . "/" . "unocha/" . $timestamp . "> " . $ns_uri . "validityStart " . "\"" . $validityStart . "\"" . " .\n") ;
if (strlen($validityEnd) > 3) //must have at least 4 chars to be a year
	{ fwrite ($output , $base_data_uri . "datacontainers" . "/" . "unocha/" . $timestamp . " " . $ns_uri . "validityEnd " . "\"" . $validityEnd . "\"" . " .\n") ;}

fgetcsv($csv_handle,0,",") ; //reads and discards the first line

while(!feof($csv_handle))
	{
	$oneup = fgetcsv($csv_handle,0,",") ;
	//test for blank line (usually last line at end)
	if (count($oneup)==1)
		{break;}
		
	$admunit_uri = $base_locations_uri . strtolower($oneup[$level_n_pcode_element]) . "/" . $oneup[$level_n_pcode_element] . ">" ;
	
	//create adminunit and its basic attributes
	fwrite($output , $admunit_uri . " a " . $ns_uri . $Country_id . " .\n") ;
	if ($n > 0)
		{fwrite($output , $admunit_uri . " " . $ns_uri . $atLocation_id . " " . $base_locations_uri . strtolower($oneup[$level_n_pcode_element]) . $oneup[$level_n_minus_one_pcode_element] . "> .\n") ;}
	fwrite($output , $admunit_uri . " " . $ns_uri . $atLevel_id . " " . $base_locations_uri . strtolower($oneup[$level_n_pcode_element]) . "/" . "adminlevel" . $n . "> .\n") ;
	fwrite($output , $admunit_uri . " " . $ns_uri . $pcode_id . " \"" . $oneup[$level_n_pcode_element] . "\" .\n") ;
	fwrite($output , $admunit_uri . " " . $ns_uri . $featureName_id . " \"" . $oneup[$featureName_element] . "\" .\n") ;
	fwrite($output , $admunit_uri . " " . $ns_uri . $featureRefName_id . " \"" . deaccent($oneup[$featureName_element]) . "\" .\n") ;
	fwrite($output , $base_locations_uri . strtolower($oneup[$level_n_pcode_element]) . "/" . $oneup[$level_n_pcode_element] . "/" . $geom_uri . ">" . " a " . $geo_ns_uri . $Geometry_id . " .\n") ;
	fwrite($output , $admunit_uri . " " . $geo_ns_uri . $hasGeometry_id . " " . $base_locations_uri . strtolower($oneup[$level_n_pcode_element]) . "/" . $oneup[$level_n_pcode_element] . "/" . $geom_uri . "> .\n") ;
	fwrite($output , $base_locations_uri . strtolower($oneup[$level_n_pcode_element]) . "/" . $oneup[$level_n_pcode_element] . "/" . $geom_uri . "> " . $geo_ns_uri . $hasSerialization_id . " " . "\"") ;
	$current_geom = $oneup[$geom_element] ;
	$geom_length = strlen($current_geom) ;
	echo $geom_length . " | " ;
	$char_counter = 99 ;
	$counting = false ;
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
			fwrite($output, $current_char) ;
			}
		elseif ($char_counter <= $precision + 1 and $char_counter > 0 )
			{
			fwrite($output, $current_char) ;
			}
		if ($counting)
			{$char_counter-- ;}
		}
	fwrite($output , "\"^^" . $geo_ns_uri . $wktLiteral_id . " .\n") ;
	}
	
	
 
 //close the files
 fclose($csv_handle) ;
 fclose($output) ;


?>