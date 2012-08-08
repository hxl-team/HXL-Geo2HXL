A tool to convert geodata files to HXL format (see hxl.humanitarianresponse.info) for use as reference data. For the moment it is designed to work only administrative boundary data.

The tool is currently a simple script.  The intention is to ultimately build a user interface around the tool.

To use the script now:

1) Use ogr2ogr to convert your geodata to CSV with WKT geometry representations.  This is the command: ogr2ogr -f "CSV" output input -lco GEOMETRY=AS_WKT
This has been tested on shapefiles only, but should work on any geodata format that ogr2ogr accepts.

2) Configure the script for your specific translation.  See the "CONFIGURATION" section near the top of the geo2hxl.php script.

3) Run the PHP file.




