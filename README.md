Scripts to convert geodata files to HXL format (see http://hxl.humanitarianresponse.info) for use as reference data. 

For the moment it is designed to work with: 

1. administrative boundary data (geo2hxl) 
2. populated places data (geo2hxl)
3. Persons of Concern locations data from UNHCR (poc2hxl)
4. global 1:1million scale national boundaries from UN Cartographic Service (geo2hxl_uncs) 

Input is a CSV with a field containing WKT geometry and fields for the required attributes.
The tool is currently a simple script with a set of variables that need to be configured to make it work.  The intention is to ultimately build a user interface around the tool.

Note that this tool contains a function to truncate the precision of the WKT output from ogr2ogr which has 15 decimal places by (non-configurable) default. For decimal degrees, that is smaller than the radius of most atoms. The script truncates to 7 decimal places (around 1cm) by default but this can be configured.   

Note also that the URI patterns for the datacontainer include the optional organization reference to "unocha".  Eventually this should be made configurable so that reference data loaded from other organizations can be attributed to them.

To use the script now:

1. Use ogr2ogr to convert your geodata to CSV with WKT geometry representations.  This is the command: 
```ogr2ogr -f "CSV" output input -lco GEOMETRY=AS_WKT```
This has been tested on shapefiles only, but should work on any geodata format that ogr2ogr accepts.

2. Configure the script for your specific translation.  See the "CONFIGURATION" section near the top of the script.

3. Run the PHP file.