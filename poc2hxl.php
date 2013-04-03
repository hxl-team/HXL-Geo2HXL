<?php
//POC2HXL.php: Reads csv of a Persons of Concern Locations (from UNHCR) to HXL triples.  

//CHANGE LOG
//Version 2 - 2012-11-20: better documentation, modifications for validOn property.  

//THINGS IT DOESN'T DO:
//Some metadata elements for the datacontainer are missing (reported by, for example).  

//-------------------CONFIGURATION-----------------------------------------------------
//Declare below which csv columns contain which data (first column = 0) and other needed info from user
//If necessary, configure the code that writes the prefixes at the beginning of the output file.  This is in the "add headers" section a bit further down.

//UNHCR POC List Configuration
//CSV header row: WKT,OBJECTID_1,OBJECTID,Id,Counrty,District,Site,Type,SubType,Type_Name,Long_,Lat,pcode,Cntry_pcod
$geom_element = 0 ; //which column contains the WKT geometry. First column is 0, Second column is 1, 0
$pcode_element = 3 ; //which column contains the pcode for POC. UNHCR has called this simply "ID" in the past.  We conncatenate it with UNHCR-POC-[ID]. 
$pcode_base "UNHCR-POC-" //Consult existing APL's in the HXL database to understand which pattern makes sense for your data.  APL pcodes should be globally unique.
$country_code_element = 13 ;  //which column contains the ISO3166 three letter code for the country in which the admin unit falls?  This is used for the URI.  
$pcode_of_admin_unit = 12 ; //which column contains the pcode of the admin unit (at the lowest available level) in which the POC falls.
$featureName_element = 6 ; //which column contains the feature name
$featureRefName_element = 6 ; //which column contains the feature ref name 
$precision = 7 ; //number of decimal places to which the WKT coordinates will be truncated. For Decimal Degrees, 7 yields approximately cm precision.  The default ogr2ogr output is 15 decimal places, about the radius of a hydrogen atom.
$file_to_process = "sahel_poc_identity.csv" ;
$output_file_name = "sahel_apl_unhcr.ttl" ;
$description_element = 9 ;  //for the UNHCR POCs, we use the "TypeName" field.
$description_language = "en" ; //two letter iso code for the language of the data in the description field.  Also used for the data container description.
//Metadata items
$dcdate = "2012-11-20T11:16:00.0Z" ; //the date the file is created
$validOn = "2012-08-24" ;  //Beginning date for which this dataset is the valid one.  This value is applied to the data container (named graph) which holds the data.  
$reportedBy = "UNHCR" ;  
$reported_by_for_uri = "unhcr" ;  //lowercase common acronym for the reporting organization.  
$datacontainer_description = "Loaded by UNOCHA from UNHCR Persons of Concern Locations data, Nov 2012" ;


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
fwrite ($output , $base_data_uri . "datacontainers" . "/" . $reported_by_for_uri . "/" . $timestamp . "> " . $ns_uri . "validOn " . "\"" . $validOn . "\"" . " .\n") ;
fwrite ($output , $base_data_uri . "datacontainers" . "/" . $reported_by_for_uri . "/" . $timestamp . "> " . $ns_uri . "reportedBy " . "\"" . $reportedBy . "\"" . " .\n") ;
fwrite ($output , $base_data_uri . "datacontainers" . "/" . $reported_by_for_uri . "/" . $timestamp . "> " . $ns_uri . "description " . "\"" . $datacontainer_description . "\"" . "@" . $description_language . " .\n") ;


fgetcsv($csv_handle,0,",") ; //reads and discards the first line

while(!feof($csv_handle))
	{
	$current = fgetcsv($csv_handle,0,",") ;
	//test for blank line (usually last line at end)
	if (count($current)==1)
		{break;}
	$pcode = $pcode_base . ceil($current[$pcode_element]) ;
	$base_uri = $base_locations_uri . strtolower($current[$country_code_element]) ;
	$apl_uri = $base_uri . "/" . $pcode ;
	
	//create apl and its basic attributes
	fwrite($output , $apl_uri . "> a " . $ns_uri . $apl_id . " .\n") ;
	fwrite($output , $apl_uri . "> " . $ns_uri . $atLocation_id . " " . $base_data_uri . "locations/admin/" . strtolower($current[$country_code_element]) . "/" . $current[$pcode_of_admin_unit] . "> .\n") ;
	fwrite($output , $apl_uri . "> " . $ns_uri . $pcode_id . " \"" . $pcode . "\" .\n") ;
	fwrite($output , $apl_uri . "> " . $ns_uri . $featureName_id . " \"" . utf8_encode($current[$featureName_element]) . "\" .\n") ;
	fwrite($output , $apl_uri . "> " . $ns_uri . $featureRefName_id . " \"" . deaccent($current[$featureRefName_element]) . "\" .\n") ;
	fwrite($output , $apl_uri . "> " . $ns_uri . $description_id . " \"" . $current[$description_element] . "\"@" . $description_language . " .\n") ;
	fwrite($output , $apl_uri . "/" . $geom_uri . ">" . " a " . $geo_ns_uri . $Geometry_id . " .\n") ;
	fwrite($output , $apl_uri . "> " . $geo_ns_uri . $hasGeometry_id . " " . $apl_uri . "/" . $geom_uri . "> .\n") ;
	fwrite($output , $apl_uri . "/" . $geom_uri . "> " . $geo_ns_uri . $hasSerialization_id . " " . "\"" . truncate($precision,$current[$geom_element]) . "\"^^" . $geo_ns_uri . $wktLiteral_id . " .\n") ;
	}
 
 //close the files
 fclose($csv_handle) ;
 fclose($output) ;


?>