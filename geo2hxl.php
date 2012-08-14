<?php
//Geo2HXL.php: Reads csv of a single admin layer (converted to csv with WKT geom using ogr2ogr) to HXL triples.  

//CHANGE LOG
//Version 4: Adds in logic to truncate to desired precision of WKT
//Version 3: This version checks for whether or not the admin level being processed is level 0 (the national boundary).  It ignores the atLocation property in this case.
//Version 2: This version has revisions to map to latest version of URI patterns and the Geolocation Standard.  It adds the writing of prefixes and the DataContainer.

//THINGS IT DOESN'T DO:
//Doesn't parse the date stamp (used for the data container name) to also get the dc:date literal.  dc:date is specified in the configuration.
//Labels all data containers with the optional organization value set to "unocha".  A good future addition would be to make this configurable.
//Some metadata elements for the datacontainer are missing (reported by, for example).  

//-------------------CONFIGURATION-----------------------------------------------------
//Delcare below which csv columns contain which data (first column = 0) and other needed info from user
//See http://sites.google.com/site/ochaimwiki/cod-fod-guidance/administrative-boundaries for guidance on these fields
//Also, configure the code that writes the prefixes at the beginning of the output file.  This is in the "add headers" section a bit further down.

//Burkina Faso Admin 2 configuration
//CSV header row: WKT,CNTRY_NAME,CNTRY_CODE,ADM1_NAME,ADM1_CODE,ADM2_NAME,ADM2_CODE,Shape_Leng,Shape_Le_1,Shape_Area
$geom_element = 0 ; //which column contains the WKT geometry. First column is 0, Second column is 1, 0
$level_n_pcode_element = 6 ; //which column contains the pcode for the level that is being converted
$level_n_minus_one_pcode_element = 4 ;  //which column contains the pcode for the admin unit one level above the level that is being converted. This is ignored if $n = 0 (See below).
$n = 2 ; //base admin level being processed. Set to 0 if you are processing the national boundary, 1 for the first subnational boundary, etc.
$featureName_element = 5 ; //which column contains the feature name
$featureRefName_element = 5 ; //which column contains the feature ref name.  A REFNAME field can be included which contains a version of the name without accented characters or apostrophes (which cause problems in some applications).  Spaces and hyphens are allowed.  Refname is expressed in proper case.  For example, the administrative unit name "Sixt-Fer-à-Cheval" would become "Sixt-Fer-a-Cheval". Refname can be taken from the same field as the name if it meets all the criteria mentioned here. 
$country_code = "bfa" ; //ISO 3 letter code for the country, lower case
$precision = 7 ; //number of decimal places to which the WKT coordinates will be truncated. For Decimal Degrees, 7 yields approximately cm precision.  The default ogr2ogr output is 15 decimal places, about the radius of a hydrogen atom.
$file_to_process = "bfa_admbnda_adm2_1m_salb.csv" ;
$output_file_name = "bfa_admbnda_adm2_1m_salb.ttl" ;
//Metadata items
$dcdate = "2012-08-14T11:16:00.0Z" ; //the date the file is created
$validityStart = "2012-07-24" ;  //Beginning date for which this dataset is the valid one.  This value is applied to the data container (named graph) which holds the data.  
$validityEnd = "" ;  //Blank indicates that the dataset is currently valid.

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
$featureRefName_id = "featureRefname" ;
$hasGeometry_id = "hasGeometry" ;
$Polygon_id = "Polygon" ;
$geom_uri = "geom" ;
$hasSerialization_id = "hasSerialization" ;
$wktLiteral_id = "wktLiteral";
$Geometry_id = "Geometry" ;
$DataContainer_id = "DataContainer";


//------------------------START PROCESSING--------------------------------------------

$csv_handle = fopen($file_to_process,"r") or exit ("Unable to open") ;
$output = fopen($output_file_name,"w") or exit ("Unable to create new file") ;

$base_uri = $base_locations_uri . $country_code . "/" ;

//add headers
fwrite($output , "@prefix hxl: <http://hxl.humanitarianresponse.info/ns#> .
@prefix geo: <http://www.opengis.net/ont/geosparql#> .
@prefix dc: <http://www.w3.org/2001/XMLSchema#> .\n") ;
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
		
	$admunit_uri = $base_uri . $oneup[$level_n_pcode_element] . "/" . ">" ;
	
	//create adminunit and its basic attributes
	fwrite($output , $admunit_uri . " a " . $ns_uri . $AdminUnit_id . " .\n") ;
	if ($n > 0)
		{fwrite($output , $admunit_uri . " " . $ns_uri . $atLocation_id . " " . $base_uri . $oneup[$level_n_minus_one_pcode_element] . "> .\n") ;}
	fwrite($output , $admunit_uri . " " . $ns_uri . $atLevel_id . " " . $base_uri . "adminlevel" . $n . "> .\n") ;
	fwrite($output , $admunit_uri . " " . $ns_uri . $pcode_id . " \"" . $oneup[$level_n_pcode_element] . "\" .\n") ;
	fwrite($output , $admunit_uri . " " . $ns_uri . $featureName_id . " \"" . $oneup[$featureName_element] . "\" .\n") ;
	fwrite($output , $admunit_uri . " " . $ns_uri . $featureRefName_id . " \"" . $oneup[$featureName_element] . "\" .\n") ;
	fwrite($output , $base_uri . $oneup[$level_n_pcode_element] . "/" . $geom_uri . ">" . " a " . $geo_ns_uri . $Geometry_id . " .\n") ;
	fwrite($output , $admunit_uri . " " . $geo_ns_uri . $hasGeometry_id . " " . $base_uri . $oneup[$level_n_pcode_element] . "/" . $geom_uri . "> .\n") ;
	fwrite($output , $base_uri . $oneup[$level_n_pcode_element] . "/" . $geom_uri . "> " . $geo_ns_uri . $hasSerialization_id . " " . "\"" . truncate($precision,$oneup[$geom_element]) . "\"^^" . $geo_ns_uri . $wktLiteral_id . " .\n") ;
	
	
	/*create polygon and geometry
	fwrite($output , $base_uri . $oneup[$level_n_pcode_element] . "/" . $geom_uri . "> " . "a ") ;
	fwrite($output , $ns_uri . $Polygon_id . " .\n") ;
	
	fwrite($output , $base_uri . $oneup[$level_n_pcode_element] . "/" . $geom_uri . "> ") ;
	fwrite($output , $ns_uri . $asWKT_id . " ") ;
	fwrite($output , "\"" . $oneup[$geom_element] . "\"" . " .\n");*/
	}
 
 //close the files
 fclose($csv_handle) ;
 fclose($output) ;


?>