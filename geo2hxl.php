<?php
//Geo2HXL.php: Reads csv of a single admin layer (converted to csv with WKT geom using ogr2ogr) to HXL triples.  

//CHANGE LOG
//Version 3: This version checks for whether or not the admin level being processed is level 0 (the national boundary).  It ignores the atLocation property in this case.
//Version 2: This version has revisions to map to latest version of URI patterns and the Geolocation Standard.  It adds the writing of prefixes and the DataContainer.

//THINGS IT DOESN'T DO:
//     Doesn't parse the date stamp (used for the data container name) to also get the dc:date literal.  dc:date is specified in the configuration.


//-------------------CONFIGURATION-----------------------------------------------------
//Delcare below which csv columns contain which data (first column = 0) and other needed info from user
//See http://sites.google.com/site/ochaimwiki/cod-fod-guidance/administrative-boundaries for guidance on these fields
//Also, configure the code that writes the prefixes at the beginning of the output file.  This is in the "add headers" section a bit further down.

//Burkina Faso Admin 0 configuration
//CSV header row: WKT,CNTRY_NAME,CNTRY_CODE
$geom_element = 0 ; //which column contains the WKT geometry. First column = 0.
$level_n_pcode_element = 2 ; //which column contains the pcode for the level that is being converted
$level_n_minus_one_pcode_element = "ignored" ;  //which column contains the pcode for the admin unit one level above the level that is being converted. This is ignored if $n = 0 (See below).
$n = 0 ; //base admin level being processed. Set to 0 if you are processing the national boundary.
$featureName_element = 1 ; //which column contains the feature name
$featureRefName_element = 1 ; //which column contains the feature ref name 
$country_code = "bfa" ; //ISO 3 letter code for the country, lower case
$file_to_process = "bfa_admbnda_adm0_1m_salb.csv" ;
$output_file_name = "bfa_admbnda_adm0_1m_salb.ttl" ;
//Metadata items
$dcdate = "2012-08-08T11:16:00.0Z" ; //the date the file is created
$validityStart = "2012-07-24" ;  //Beginning date for which this dataset is the valid one.  This value is applied to the data container (named graph) which holds the data.  
$validityEnd = "" ;  //Blank indicates that the dataset is currently valid.



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
	fwrite($output , $base_uri . $oneup[$level_n_pcode_element] . "/" . $geom_uri . "> " . $geo_ns_uri . $hasSerialization_id . " " . "\"" . $oneup[$geom_element] . "\"^^" . $geo_ns_uri . $wktLiteral_id . " .\n") ;
	
	
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