<?php

  $start =$_POST['start'];
  $end =$_POST['end'];
  $directonsurl = "https://maps.googleapis.com/maps/api/directions/json?origin={$start}&destination={$end}&libraries=places&key=YOUR_API_KEY";


  // get the json response
  $resp_json = file_get_contents($directonsurl);
  //decode the response
    $resp = json_decode($resp_json, true);
    //getting overview_polyline from the json output
  $polymy= isset($resp['routes'][0]['overview_polyline']['points']) ? $resp['routes'][0]['overview_polyline']['points'] : "";

//fucntion to decode overview_polyline
  function decodePolylineToArray($encoded){
  $length = strlen($encoded);
  $index = 0;
  $points = array();
  $lat = 0;
  $lng = 0;

  while ($index < $length)
  {
    // Temporary variable to hold each ASCII byte.
    $b = 0;

    // The encoded polyline consists of a latitude value followed by a
    // longitude value.  They should always come in pairs.  Read the
    // latitude value first.
    $shift = 0;
    $result = 0;
    do
    {
      // The `ord(substr($encoded, $index++))` statement returns the ASCII
      //  code for the character at $index.  Subtract 63 to get the original
      // value. (63 was added to ensure proper ASCII characters are displayed
      // in the encoded polyline string, which is `human` readable)
      $b = ord(substr($encoded, $index++)) - 63;

      // AND the bits of the byte with 0x1f to get the original 5-bit `chunk.
      // Then left shift the bits by the required amount, which increases
      // by 5 bits each time.
      // OR the value into $results, which sums up the individual 5-bit chunks
      // into the original value.  Since the 5-bit chunks were reversed in
      // order during encoding, reading them in this way ensures proper
      // summation.
      $result |= ($b & 0x1f) << $shift;
      $shift += 5;
    }
    // Continue while the read byte is >= 0x20 since the last `chunk`
    // was not OR'd with 0x20 during the conversion process. (Signals the end)
    while ($b >= 0x20);

    // Check if negative, and convert. (All negative values have the last bit
    // set)
    $dlat = (($result & 1) ? ~($result >> 1) : ($result >> 1));

    // Compute actual latitude since value is offset from previous value.
    $lat += $dlat;

    // The next values will correspond to the longitude for this point.
    $shift = 0;
    $result = 0;
    do
    {
      $b = ord(substr($encoded, $index++)) - 63;
      $result |= ($b & 0x1f) << $shift;
      $shift += 5;
    }
    while ($b >= 0x20);

    $dlng = (($result & 1) ? ~($result >> 1) : ($result >> 1));
    $lng += $dlng;

    // The actual latitude and longitude values were multiplied by
    // 1e5 before encoding so that they could be converted to a 32-bit
    // integer representation. (With a decimal accuracy of 5 places)
    // Convert back to original values.
    $points[] = array($lat * 1e-5, $lng * 1e-5);
  }

  return $points;
}

  //array to store all lat long from overview_polyline
  $latlong = array();
  //reduced number of lat-long pairs
  $finallatlong= array();

  $latlong= decodePolylineToArray($polymy);
  //logic to reduce lat-long pairs
   $splittoless=1;
  $latlongcount=count($latlong);
  if($latlongcount >10){
    $splittoless= floor($latlongcount/8);

  }

  for($x = 0; $x < $latlongcount; $x+=$splittoless) {

     array_push($finallatlong, $latlong[$x]);
  }
  $countoffinallatlong=count($finallatlong);
  $allinonearray=array();
  $mywayjson="";

  class mywaypointsdata implements JsonSerializable
  {
      protected $lat1;
      protected $long1;
      protected $temp1;
      protected $place1;

      public function __construct(array $data)
      {
          $this->lat1 = $data['lat'];
          $this->long1 = $data['lng'];
          $this->temp1 = $data['temp'];
          $this->place1 = $data['place'];
      }

      public function getId()
      {
          return $this->lat1;
      }

      public function getName()
      {
          return $this->long1;
      }
      public function gettemp()
      {
          return $this->temp1;
      }

      public function getplace()
      {
          return $this->place1;
      }


      public function jsonSerialize()
      {
          return
          [
              'lat'   => $this->getId(),
              'lng' => $this->getName(),
              'temp'=>$this->gettemp(),
              'place'=>$this->getplace()
          ];
      }
  }

  $date = new DateTime();
    for($x =0; $x < $countoffinallatlong; $x++){
      $weatherurl= "http://api.openweathermap.org/data/2.5/weather?lat={$finallatlong[$x][0]}&lon={$finallatlong[$x][1]}&APPID=YOUR_API_KEY";
      $weather_json= file_get_contents($weatherurl);
      $weather_decode=json_decode($weather_json,true);
      $mywaypointsdata = new mywaypointsdata(array('lat' => round($finallatlong[$x][0],2), 'lng' => round($finallatlong[$x][1],2),'temp'=>round($weather_decode["main"]["temp"]-273.15,0),'place'=>$weather_decode["name"]));

      $mywayjson = json_encode($mywaypointsdata);

       array_push($allinonearray, $mywayjson);
  }

   header('Content-Type: application/json');
    echo json_encode($allinonearray);

?>
