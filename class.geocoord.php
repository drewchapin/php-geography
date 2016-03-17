<?php
/**
 * GeoCoord PHP Class
 *
 * This class represents a geographical location using latitude and longitude
 * coordinates.
 *
 * PHP version 5
 *
 *    Copyright (C) 2016  Drew Chapin <druciferre@gmail.com>
 *
 *    This code is free software: you can redistribute it and/or modify
 *    it under the terms of the GNU General Public License as published by
 *    the Free Software Foundation, either version 3 of the License, or
 *    (at your option) any later version.
 *
 *    This code is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *    GNU General Public License for more details.
 *
 *    You should have received a copy of the GNU General Public License
 *    along with this code.  If not, see <http://www.gnu.org/licenses/>.
 **/

class GeoCoord
{
	const EARTH_RADIUS = 6371000;
	public $lat;
	public $lng;
	function __construct()
	{
		$params = func_get_args();
		$num_params = func_num_args();
		switch( $num_params )
		{
			case 1:
				if( is_string($params[0]) )
				{
					list($this->lat,$this->lng) = explode(",",$params[0]);
				}
				else if( is_array($params[0]) )
				{
					if( isset($params[0]["lat"]) && isset($params[0]["lng"]) )
					{
						$this->lat = $params[0]["lat"];
						$this->lng = $params[0]["lng"];
					}
					else if( isset($params[0]["latitude"]) && isset($params[0]["longitude"]) )
					{
						$this->lat = $params[0]["latitude"];
						$this->lng = $params[0]["longitude"];
					}
					else if( isset($params[0][0]) && isset($params[0][1]) )
					{
						$this->lat = $params[0][0];
						$this->lng = $params[0][1];
					}
					//else
					//	throw new Exception("invalid argument supplied for " . get_class($this) . " constructor");
				}
				//else
				//	throw new Exception("invalid argument supplied for " . get_class($this) . " constructor");
				break;
			case 2:
				$this->lat = $params[0];
				$this->lng = $params[1];
				break;
			//default:
			//	throw new Exception(get_class($this) . " constructor does not take " . $num_params);
		}
	}
	public function __toString()
	{
		return $this->lat . "," . $this->lng;
	}
	public function distanceTo( $that )
	{
		return self::distance($this->lat,$this->lng,$that->lat,$that->lng);
	}
	public function bearingTo( $that )
	{
		// Algorithm from: https://trac.osgeo.org/openlayers/wiki/GreatCircleAlgorithms
		$x1 = deg2rad($this->lat);
		$y1 = deg2rad($this->lng);
		$x2 = deg2rad($that->lat);
		$y2 = deg2rad($that->lng);
		$a = cos($y2) * sin($x2 - $x1);
		$b = cos($y1) * sin($y2) - sin($y1) * cos($y2) * cos($x2 - $x1);
		$adjust = 0;
		if( $a == 0 && $b == 0 )
			$bearing = 0;
		else if( $b == 0 )
			$bearing = $a<0 ? 3*pi()/2 : pi()/2;
		else if( $b < 0 )
			$adjust = pi();
		else
			$adjust = $a<0 ? 2*pi() : 0;
		return rad2deg(atan($a/$b) + $adjust);
	}
	public function getWaypoint( $bearing /* degress */, $distance /* meters */ )
	{
		$x1 = deg2rad($this->lat);
		$y1 = deg2rad($this->lng);
		$bearing = deg2rad($bearing);
		$distance = $distance / self::EARTH_RADIUS;
		// Algorithm from: https://trac.osgeo.org/openlayers/wiki/GreatCircleAlgorithms
		// Doesn't work...
		//$c = $distance / self::EARTH_RADIUS; // convert arc distance to radians
		//$y2 = rad2deg(asin( sin($y1) * cos($c) + cos($y1) * sin($c) * cos($bearing) ));
		//$a = sin($c) * sin($bearing);
		//$b = cos($y1) * cos($c) - sin($y1) * sin($c) * cos($bearing);
		//if( $b == 0 )
		//	$x2 = $x1;
		//else
		//	$x2 = rad2deg($x1+atan($a/$b));
		// Algorithm from: http://stackoverflow.com/questions/7222382/get-lat-long-given-current-point-distance-and-bearing
		$x2 = asin( sin($x1) * cos($distance) + cos($x1) * sin($distance) * cos($bearing) );
		$y2 = $y1 + atan2( sin($bearing) * sin($distance) * cos($x1), cos($distance) - sin($x1) * sin($x2) );
		return new GeoCoord(rad2deg($x2),rad2deg($y2));
	}
	public function getCircle( $radius, $detail = 1 )
	{
		$points = array();
		for( $angle = 0; $angle <= 360; $angle+=$detail )
		{
			$tmp = $this->getWaypoint($angle,$radius);
			array_push($points,$tmp);
		}
		//array_push($points,$points[0]); // close the loop
		return $points;
	}
	public function getEncodedCircle( $radius, $detail = 10 )
	{
		return self::encodePolyline($this->getCircle($radius,$detail));
	}
	public static function distance( $lat1, $lng1, $lat2, $lng2 )
	{
		//$dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lng1 - $lng2));
		//$meters = rad2deg(acos($dist)) * 60 * 1853.155501;
		//return $meters;
		// Algorithm from: http://williams.best.vwh.net/avform.htm
		$lat1 = deg2rad($lat1);
		$lng1 = deg2rad($lng1);
		$lat2 = deg2rad($lat2);
		$lng2 = deg2rad($lng2);
		$dist = acos( sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos($lng1-$lng2) );
		return $dist * self::EARTH_RADIUS;

	}
	private static function encodeNumber( $num )
	{
		$enc_num = "";
		// left shift one bit (step 4)
		$tmp = $num << 1;
		// if original number is negative, invert value
		if( $num < 0 )
			$tmp = ~($tmp);
		// go through each 5 bit chunks in reverse order 
		while( $tmp > 0 )
		{
			// get the right most 5 bits
			$chunk = $tmp & 0x1F;
			// OR the chunk with 0x20 if it's not the last one (step 8)
			if( $tmp > 0x1F )
				$chunk = $chunk | 0x20;
			// add 63 (? char) to each chunk (step 10)
			$chunk += 63;
			// convert to ASCII
			$enc_num .= chr($chunk);
			// drop the right most 5 bits
			$tmp = $tmp >> 5;
		}
		return $enc_num;
	}
	public static function encodePolyline( array $points )
	{
		// Algorithm from: https://developers.google.com/maps/documentation/utilities/polylinealgorithm
		// make points relative
		$relative_points[] = $points[0];
		for( $i = 1; $i < count($points); $i++ )
		{
			$lat = $points[$i]->lat - $points[$i-1]->lat;
			$lng = $points[$i]->lng - $points[$i-1]->lng;
			$tmp = new GeoCoord($lat,$lng);
			array_push($relative_points,$tmp);
		}
		$enc_polyline = "";
		foreach( $relative_points as $point )
		{
			$enc_polyline .= self::encodeNumber(round($point->lat*1e5)); // step 2
			$enc_polyline .= self::encodeNumber(round($point->lng*1e5));
		}
		return $enc_polyline;
	}
};

?>
