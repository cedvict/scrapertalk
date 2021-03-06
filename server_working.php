<?php

			
// http://tiles.markware.net/bbox.json?bbox=a,b,c,d&debug=true
// http://tiles.markware.net/tile/16/19310/24634.json?debug=true
// http://tiles.markware.net/feature/111.json?debug=true



	$host        = "host=nc2";
	$port        = "port=5432";
	$dbname      = "dbname=planetosm";
	$credentials = "user=xxxxxx password=xxxxx";


	header('Access-Control-Allow-Origin: http://scrapertalk.com');
	header('Access-Control-Allow-Methods: GET, POST');
	header('Access-Control-Allow-Headers: X-Requested-With');
	header('X-Powered-By: Markware GIS');

	
	if ( !isset( $_GET['w'] ) 	)
	{
	
    	header('Error: Unsupported Request', false, 501);
		exit;
	
	}
	else
	{
		$service = strtolower($_GET['w']);
	}

	$debug=false;
	if ( isset( $_GET['debug'] ) 	)
	{
		if ( strtolower($_GET['debug']) == "true")
		{
			$debug = true;
			header('Markware GIS - Debug = True');
			echo "Querystring: " . $_SERVER['QUERY_STRING'] . "\n\n";	
			echo "Service: ". $service . "\n\n";
		}
	}



	// =============== RETURN ONE TILE ========================
	if ( $service == "tile" )	
	{	
				$zoom=$_GET['z'];
				$tile_y=$_GET['y']*1;
				$tile_x=$_GET['x']*1;
			
			
				//Tile numbers to lon./lat.[edit]
				$n = pow(2, $zoom);
				$topLon = $tile_x / $n * 360.0 - 180.0;
				$topLat = rad2deg(atan(sinh(pi() * (1 - 2 * $tile_y / $n))));
				$botLon = ($tile_x +1) / $n * 360.0 - 180.0;
				$botLat = rad2deg(atan(sinh(pi() * (1 - 2 * ( $tile_y +1 ) / $n))));
				$srid = "4326";
				$finalsrid = "4326";
			
				// Alternative Calcualtion of the Corners
				
				//list($N, $S) = Project($tile_y, $zoom);
				//list($W, $E) = ProjectL($tile_x, $zoom);
				//$topLon = $W;
				//$topLat = $N;
				//$botLon = $E;
				//$botLat = $S;
				//$srid = "4326";
				
			
			
				if ( $debug == true)
				{
					echo "tile_x " . $tile_x . ", tile_y " . $tile_y . "\n\n";
					echo $topLon . ", " . $topLat . "\n" . $botLon . ", " . $botLat . "\n\n";
					echo "North " . $N . ", South " . $S . ", East " . $E . ", West " . $W . "\n\n";
				}
			
			
			
			// Sould consider searchoing points for entrances
			
			GetBBoxData($topLon, $topLat, $botLon, $botLat, $srid, $finalsrid);
			
			exit;
	}



	// =============== RETURN BBOX ========================
	if ( $service == "bbox.json" )	
	{	

		echo "Querystring: " . $_SERVER['QUERY_STRING'] . "\n\n";	
		echo "Bounding Box Service";
		exit;
	
	}

	// =============== RETURN ONE FEATURE ========================
	if ( $service == "feature" )	
	{	
		// http://gis.stackexchange.com/questions/59487/assigning-a-nearest-polygon-to-a-point
		
		// Note - watch for multi polygons and polygons that realate to the same building
		
		
		echo "Feature Service";
		exit;
	
	}



	// Falls through to an error, invalid service requested

	header('Error: Unsupported Markware GIS Request', false, 501);
	exit;


		function GetBBoxData($topLon, $topLat, $botLon, $botLat, $srid, $finalsrid)
		{
		
					
					
					global $host, $port, $dbname, $credentials;
					
					
					$db = pg_pconnect( "$host $port $dbname $credentials"  );
					if(!$db)
					{
					  //echo "Error : Unable to open database\n";
					  
					  header('Error: Database Unavailable', false, 503);
					  exit;
					  	
					} else {
					  //echo "Opened database successfully\n";
					}
					
					
					$properties = 	"	osm_id,	
									\"name\",
									\"building\",
									\"height\",
									\"type\",
									\"tags\"->'min_height' AS minHeight,
									\"tags\"->'building:levels' AS levels,
									\"tags\"->'building:min_level' AS minLevel,
									\"tags\"->'building:material' AS material,
									\"tags\"->'building:color' AS color,
									\"tags\"->'roof:material' AS roofMaterial,
									\"tags\"->'roof:color' AS roofColor,
									\"tags\"->'roof:shape' AS roofShape,
									\"tags\"->'roof:height' AS roofHeight,
									\"tags\"->'building:shape'  AS shape
								";
					
					//					"unknown"  AS "wallColor",
					// relationId links the Relations
					//"addr:housenumber" AS house,
					
// Json Needs to be returned in the format of:
//    "type": "FeatureCollection",
//    "features": [{
//        "type": "Feature",
//        "id": 3698396,
//        "properties": {
//            "height": 54
//        },
//        "geometry": {
//            "type": "Polygon",
//            "coordinates": 
//                [
//                    [-73.97426, 40.74042],
//                    [-73.97424, 40.74045],
//                    [-73.97433, 40.74048],
//                    [-73.97435, 40.74045],
//                    [-73.97426, 40.74042]
//                ]
//            ]
//        }
//    },					


// NOT

//    "type": "FeatureCollection",
//    "features": [{
//        "type": "Feature",
//        "geometry": {
//            "type": "MultiPolygon",
//            "coordinates": [
//                [
//                    [
//                        [-8235019.6793318, 4973710.26745388],
//                         [-8235019.6793318, 4973619.1627252],
//                        [-8235019.6793318, 4973710.26745388]
//                    ]
//                ],
//         "properties": {
//            "osm_id": 278325855,
//            "height": "80.4"
//        }
//    },					

$sql = <<<EOF

WITH bbox AS 
  (SELECT ST_Transform(ST_MakeEnvelope($topLon, $topLat ,$botLon , $botLat , $srid), 900913) As geom)
  SELECT row_to_json(fc)
  FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features
  FROM (SELECT 'Feature' As type, osm_id As id, name as building_name,
    ( SELECT row_to_json(t) 
     FROM (SELECT $properties ) t )As properties,
     ST_AsGeoJSON(ST_Transform (lg.way ,$finalsrid) )::json As geometry 
  FROM planet_osm_polygon As lg, bbox
  WHERE ( "building" is NOT NULL OR "building:part" IS NOT NULL) AND lg.way && bbox.geom
 ) As f 
) fc;  


EOF;

//echo $sql;
//exit;


						$ret = pg_query($db, $sql);
						
						if(!$ret)
						{

					    	header('Error: Sql Issue in Select Statement', false, 503);
					    	echo pg_last_error($db) . "\n\n";

							echo $sql;
							exit;

						} 
						
						
						
						header('Content-Type: application/json');
						
						while($row = pg_fetch_row($ret))
						{
						  
							// The regular expression will remove all places in the string of the form ,"key":null including any whitespace between the leading comma 
							// and the start of the key. It will also match "key":null, afterwards to make sure that no null values were found at the beginning of a JSON object.
							// http://stackoverflow.com/questions/7741415/strip-null-values-of-json-object
							
						    echo preg_replace('/,\s*"[^"]+":null|"[^"]+":null,?/', '', $row[0]);
						    
						 
						  
						}
						
						pg_close($db);
		
		   
		}
		
		//=====================================================


			function Lat2Y($Lat){
			  $LimitY = ProjectF(85.0511);
			  $Y = ProjectF($Lat);
			  
			  $PY = ($LimitY - $Y) / (2 * $LimitY);
			  return($PY);
			}
			function ProjectF($Lat){
			  $Lat = deg2rad($Lat);
			  $Y = log(tan($Lat) + (1/cos($Lat)));
			  return($Y);
			}
			function Project($Y, $Zoom){
			  $LimitY = ProjectF(85.0511);
			  $RangeY = 2 * $LimitY;
			  
			  $Unit = 1 / pow(2, $Zoom);
			  $relY1 = $Y * $Unit;
			  $relY2 = $relY1 + $Unit;
			  
			  $relY1 = $LimitY - $RangeY * $relY1;
			  $relY2 = $LimitY - $RangeY * $relY2;
			    
			  $Lat1 = ProjectMercToLat($relY1);
			  $Lat2 = ProjectMercToLat($relY2);
			  return(array($Lat1, $Lat2));  
			}
			function ProjectMercToLat($MercY){
			  return(rad2deg(atan(sinh($MercY))));
			}
			function ProjectL($X, $Zoom){
			  $Unit = 360 / pow(2, $Zoom);
			  $Long1 = -180 + $X * $Unit;
			  return(array($Long1, $Long1 + $Unit));  
			}


// url is like http://tiles.markware.net/16/16820/24374.json
// RewriteRule ^(.*)$ http://tiles.markware.net/ [R=301,L]
// https://github.com/klokantech/tileserver-php/blob/master/.htaccess


//echo $_SERVER['QUERY_STRING'] . "\n\n";
//foreach (getallheaders() as $name => $value) {
//    echo "$name: $value\n";
//}

//.htaccess
//RewriteEngine on
//RewriteBase /
//# /1/2/3.json
//RewriteRule ^(tile)/([0-9]+)/([0-9]+)/([0-9]+).json$ /?w=$1&z=$2&y=$3&x=$4.json [QSA,L]

//RewriteRule ^(tile)/([0-9]+)/([0-9]+)/([0-9]+).json$ /?w=$1&z=$2&x=$3&y=$4.json [QSA,L]
//RewriteRule ^(feature)/([0-9]+).json$ /?w=$1&$2.json [QSA,L]
//RewriteRule ^(bbox.json)$ /w=$1 [QSA,L]

//RewriteRule ^([A-Za-z0-9-]+)/(tile)/([0-9]+)/([0-9]+)/([0-9]+).json$ /?k=$1&w=$2&z=$3&x=$4&y=$5.json [QSA,L]
//RewriteRule ^([A-Za-z0-9-]+)/(feature)/([0-9]+).json$ /?w=$2&k=$1&$2.json [QSA,L]
//RewriteRule ^([A-Za-z0-9-]+)/(bbox.json)$ /w=$2&k=$1 [QSA,L]



//Tile numbers to lon./lat.[edit]
//$n = pow(2, $zoom);
//$lon_deg = $xtile / $n * 360.0 - 180.0;
//$lat_deg = rad2deg(atan(sinh(pi() * (1 - 2 * $ytile / $n))));

//This returns the NW-corner of the square. Use the function with xtile+1 and/or ytile+1 to get the other corners. With xtile+0.5 & ytile+0.5 it will return the center of the tile.



// http://tiles.markware.net/tile/16/19300/24633.json

// relation type=building not dealt with yet


// http://skipperkongen.dk/2012/08/02/examples-of-querying-a-osm-postgresql-table-with-the-hstore-tags-column
// http://www.postgresonline.com/journal/archives/267-Creating-GeoJSON-Feature-Collections-with-JSON-and-PostGIS-functions.html
 
	//$topLon = -73.9929;
	//$topLat = 40.7536;
	//$botLon = -73.9792;
	//$botLat = 40.7450;
	//$srid = "4326";
	


?>












