A tool to convert geodata files to HXL format (see hxl.humanitarianresponse.info) for use as reference data. For the moment it is designed to work only administrative boundary data.

The tool is currently a simple script.  The intention is to ultimately build a user interface around the tool.

Note that this tool contains a function to truncate the precision of the WKT output from ogr2ogr.  This is 15 decimal places by default (for decimal degrees, that is smaller than most atoms). The degree of truncation can be set in the configuration variables.  

Note also that the URI patterns for the datacontainer include the optional organization reference to "unocha".  Eventually this should be made configurable so that reference data loaded from other organizations can be attributed to them.

To use the script now:

1) Use ogr2ogr to convert your geodata to CSV with WKT geometry representations.  This is the command: ogr2ogr -f "CSV" output input -lco GEOMETRY=AS_WKT
This has been tested on shapefiles only, but should work on any geodata format that ogr2ogr accepts.

2) Configure the script for your specific translation.  See the "CONFIGURATION" section near the top of the geo2hxl.php script.

3) Run the PHP file.




