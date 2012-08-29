<?php
//POC2HXL.php: Reads csv of a Persons of Concern Locations (from UNHCR) to HXL triples.  

//CHANGE LOG

//THINGS IT DOESN'T DO:
//Some metadata elements for the datacontainer are missing (reported by, for example).  

//-------------------CONFIGURATION-----------------------------------------------------
//Delcare below which csv columns contain which data (first column = 0) and other needed info from user
//If necessary, configure the code that writes the prefixes at the beginning of the output file.  This is in the "add headers" section a bit further down.

//UNHCR POC List Configuration
//CSV header row: WKT,Id,Counrty,ISO3,District,Site,Type,SubType,Type_Name,Long,Lat,Update,CreateDate
$geom_element = 0 ; //which column contains the WKT geometry. First column is 0, Second column is 1, 0
$pcode_element = 1 ; //which column contains the pcode for the level that is being converted
$country_code_element = 3 ;  //which column contains the pcode for the admin unit one level above the level that is being converted. This is ignored if $n = 0 (See below).
$featureName_element = 5 ; //which column contains the feature name
$featureRefName_element = 5 ; //which column contains the feature ref name 
$precision = 7 ; //number of decimal places to which the WKT coordinates will be truncated. For Decimal Degrees, 7 yields approximately cm precision.  The default ogr2ogr output is 15 decimal places, about the radius of a hydrogen atom.
$file_to_process = "wrl_apl_unhcr.csv" ;
$output_file_name = "wrl_apl_unhcr.ttl" ;
$description_element = 8 ;
$description_language = "en" ; //two letter iso code for the language of the data in the description field
//Metadata items
$dcdate = "2012-08-15T11:16:00.0Z" ; //the date the file is created
$validityStart = "2012-07-24" ;  //Beginning date for which this dataset is the valid one.  This value is applied to the data container (named graph) which holds the data.  
$validityEnd = "" ;  //Blank indicates that the dataset is currently valid.
$reportedBy = "UNHCR" ;
$reported_by_for_uri = "unhcr" ;
$datacontainer_description = "Loaded by UNOCHA from UNHCR Persons of Concern Locations data, August 2012" ;


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
	$search = explode(",","ç,æ,œ,á,é,í,ó,ú,à,è,ì,ò,ù,ä,ë,ï,ö,ü,ÿ,â,ê,î,ô,û,å,e,i,ø,u");
	$replace = explode(",","c,ae,oe,a,e,i,o,u,a,e,i,o,u,a,e,i,o,u,y,a,e,i,o,u,a,e,i,o,u");
	$output = str_replace($search, $replace, $x);
	return $output ;
	}
//--------------DECLARE FIXED PARTS OF URIs--------------------------------------------------------------------

$base_data_uri = "<http://hxl.humanitarianresponse.info/data/" ;
$base_locations_uri = "<http://hxl.humanitarianresponse.info/data/locations/apl/" ;
$ns_uri = "hxl:" ;
$geo_ns_uri = "geo:" ;
$dc_ns_uri = "dc:" ;
$atLocation_id = "atLocation" ;
$pcode_id = "pcode" ;
$featureName_id = "featureName" ;
$featureRefName_id = "featureRefName" ;
$hasGeometry_id = "hasGeometry" ;
$geom_uri = "geom" ;
$hasSerialization_id = "hasSerialization" ;
$wktLiteral_id = "wktLiteral";
$Geometry_id = "Geometry" ;
$DataContainer_id = "DataContainer" ;
$apl_id = "APL" ;
$description_id = "description" ; 


//------------------------START PROCESSING--------------------------------------------

$csv_handle = fopen($file_to_process,"r") or exit ("Unable to open") ;
$output = fopen($output_file_name,"w") or exit ("Unable to create new file") ;

//add headers
fwrite($output , "@prefix hxl: <http://hxl.humanitarianresponse.info/ns/#> .
@prefix geo: <http://www.opengis.net/ont/geosparql#> .
@prefix dc: <http://purl.org/dc/terms/> .\n") ;
//create DataContainer and associate metadata items
$time = gettimeofday(); //get time for timestamp which is used as datacontainer name (according to the HXL standard for URI patterns)
$timestamp = $time['sec'] . "." . $time['usec'] ;
fwrite ($output , $base_data_uri . "datacontainers" . "/" . $reported_by_for_uri . "/" . $timestamp . "> a " . $ns_uri . $DataContainer_id . " .\n") ;
fwrite ($output , $base_data_uri . "datacontainers" . "/" . $reported_by_for_uri . "/" . $timestamp . "> " . $dc_ns_uri . "date " . "\"" . $dcdate . "\"" . " .\n") ;
fwrite ($output , $base_data_uri . "datacontainers" . "/" . $reported_by_for_uri . "/" . $timestamp . "> " . $ns_uri . "validityStart " . "\"" . $validityStart . "\"" . " .\n") ;
fwrite ($output , $base_data_uri . "datacontainers" . "/" . $reported_by_for_uri . "/" . $timestamp . "> " . $ns_uri . "reportedBy " . "\"" . $reportedBy . "\"" . " .\n") ;
fwrite ($output , $base_data_uri . "datacontainers" . "/" . $reported_by_for_uri . "/" . $timestamp . "> " . $ns_uri . "description " . "\"" . $datacontainer_description . "\"" . " .\n") ;
if (strlen($validityEnd) > 3) //must have at least 4 chars to be a year
	{ fwrite ($output , $base_data_uri . "datacontainers" . "/" . $reported_by_for_uri . "/" . $timestamp . " " . $ns_uri . "validityEnd " . "\"" . $validityEnd . "\"" . " .\n") ;}

fgetcsv($csv_handle,0,",") ; //reads and discards the first line

while(!feof($csv_handle))
	{
	$oneup = fgetcsv($csv_handle,0,",") ;
	//test for blank line (usually last line at end)
	if (count($oneup)==1)
		{break;}
	$pcode = "UNHCR-POC-" . $oneup[$pcode_element] ;
	$base_uri = $base_locations_uri . strtolower($oneup[$country_code_element]) ;
	$apl_uri = $base_uri . "/" . $pcode ;
	
	//create apl and its basic attributes
	fwrite($output , $apl_uri . "> a " . $ns_uri . $apl_id . " .\n") ;
	fwrite($output , $apl_uri . "> " . $ns_uri . $atLocation_id . " " . $base_data_uri . "locations/admin/" . strtolower($oneup[$country_code_element]) . "/" . $oneup[$country_code_element] . "> .\n") ;
	fwrite($output , $apl_uri . "> " . $ns_uri . $pcode_id . " \"" . $pcode . "\" .\n") ;
	fwrite($output , $apl_uri . "> " . $ns_uri . $featureName_id . " \"" . utf8_encode($oneup[$featureName_element]) . "\" .\n") ;
	fwrite($output , $apl_uri . "> " . $ns_uri . $featureRefName_id . " \"" . deaccent($oneup[$featureRefName_element]) . "\" .\n") ;
	fwrite($output , $apl_uri . "> " . $ns_uri . $description_id . " \"" . $oneup[$description_element] . "\"@" . $description_language . " .\n") ;
	fwrite($output , $apl_uri . "/" . $geom_uri . ">" . " a " . $geo_ns_uri . $Geometry_id . " .\n") ;
	fwrite($output , $apl_uri . "> " . $geo_ns_uri . $hasGeometry_id . " " . $apl_uri . "/" . $geom_uri . "> .\n") ;
	fwrite($output , $apl_uri . "/" . $geom_uri . "> " . $geo_ns_uri . $hasSerialization_id . " " . "\"" . truncate($precision,$oneup[$geom_element]) . "\"^^" . $geo_ns_uri . $wktLiteral_id . " .\n") ;
	}
 
 //close the files
 fclose($csv_handle) ;
 fclose($output) ;


?>