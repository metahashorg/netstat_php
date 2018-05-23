<?php

	//if (!isset($_GET['key']) || $_GET['key'] != "fsjfhwhgjknae7gyhtuhrg8ewhg") die("sorry");
	header('Content-Type: text/html; charset=utf-8'); 
	setlocale(LC_ALL, 'en_US.UTF-8');

	function post_content ($url,$postdata) {
		$uagent = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322)";
		$ch = curl_init( $url );
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		//curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
		
		$content = curl_exec( $ch );
		$err     = curl_errno( $ch );
		$errmsg  = curl_error( $ch );
		$header  = curl_getinfo( $ch );
		curl_close( $ch );
		$header['errno']   = $err;
		$header['errmsg']  = $errmsg;
		$header['content'] = $content;
		return $header;
	}
	function get_content ($url) {
		$uagent = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322)";
		$ch = curl_init( $url );
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
		$content = curl_exec( $ch );
		curl_close( $ch );
		return $content;
	}
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<style type="text/css">
	    BODY { background-color: #151515 ; color: #01DF01; font-family: Monaco; font-size: 11px;}
</style>


    <script src="https://d3js.org/d3.v5.min.js"></script>
    <script src="https://d3js.org/d3-hexbin.v0.2.min.js"></script>

    <script>
 class Dot {
    constructor(config, map) {
      this.latitude = config.lat;
      this.longitude = config.long;
      this.type = config.type;
      if (map.findHexagon(config.lat, config.long)) {
        this.dotElement = map.findHexagon(config.lat, config.long).element;
      }
      this.render();
      this.isReleased = false;
      this.id = 'dot-id-' + Math.random().toString(36).substr(2, 16);
      this.offset = {
        xAxis: (map.zeroPointHexagon.width / map.hexagonSize / map.longitudesInHexagon),
        yAxis: (map.zeroPointHexagon.height / map.hexagonSize / map.latitudesInHexagon)
      };
    }

    get color() {
      let rgbColor = '';
      switch (this.type) {
        case 'verif':
          rgbColor = '255, 79, 248';
          break;
        case 'core':
          rgbColor = '255, 214, 0';
          break;
        case 'proxy':
          rgbColor = '0, 209, 255';
          break;
        default:
          rgbColor = '57, 201, 72';
          break;
      }
      return `rgb(${rgbColor})`
    }

    setDotToNewHexagon(hex) {
      this.isReleased = true;
      this.dotElement = hex.element;
      this.latitude = hex.lat;
      this.longitude = hex.long;
      this.dotElement.attr('id', this.id).style('fill', this.color);
    }

    render(released) {
      if (this.dotElement) {
        this.isReleased = released;
        this.dotElement.style('fill', this.color);
        this.dotElement.attr('id', this.id);
      }
    }

    getEmptyHexagonNearby(map) {
      let hexagonsNearby = [];
      hexagonsNearby.push(map.findHexagon(this.latitude + this.offset.yAxis, this.longitude + this.offset.xAxis * 2));
      hexagonsNearby.push(map.findHexagon(this.latitude, this.longitude + this.offset.xAxis * 3.5));
      hexagonsNearby.push(map.findHexagon(this.latitude - this.offset.yAxis * 2, this.longitude + this.offset.xAxis * 2));
      hexagonsNearby.push(map.findHexagon(this.latitude - this.offset.yAxis * 2, this.longitude - this.offset.xAxis));
      hexagonsNearby.push(map.findHexagon(this.latitude, this.longitude - this.offset.xAxis));
      hexagonsNearby.push(map.findHexagon(this.latitude + this.offset.yAxis, this.longitude - this.offset.xAxis));
      let hex = hexagonsNearby.find( (nearHex) => {
        if (!!nearHex.element) {
          return nearHex.element.attr('id') === 'regular';
        }
      });


      if (hexagonsNearby[0]) {
          return hex;
      }
      return null
    }
  }

  class Map {

    constructor(config) {
      this.config = config;
      this.elementToInject = d3.select(this.config.selector);
      this.margin = config.margin || {};
      this.margin.top = config.margin ? config.margin.top : 10;
      this.margin.left = config.margin ? config.margin.left : 10;
      this.margin.right = config.margin ? config.margin.right : 10;
      this.margin.bottom = config.margin ? config.margin.bottom : 10;
      this.width = this.elementToInject.node().parentNode.offsetWidth;
      this.height = this.elementToInject.node().parentNode.offsetHeight;
      this.hexagonSize = config.hexagonSize || 5;
      this.waterColor = this.config.waterColor || '#0E2545';
      this.earthColor = this.config.earthColor || '#2F7BC2';
      this.dots = [];
      this.net = {};
      this.definer();
      this.latitude = this.quadrantHeight / 139;
      this.longitude = this.quadrantWidth / 360;
      this.dotsGroups = [];
    }

    get initialLatitude() {
      return this.quadrantWidth / 2 * 0.959;
    }

    get initialLongitude() {
      return this.quadrantHeight / 2 * 1.19;
    }

    get marginX() {
      return this.margin.left + this.margin.right
    }

    get marginY() {
      return this.margin.top + this.margin.bottom
    }

    get quadrantWidth() {
      return this.width - this.marginX;
    }

    get quadrantHeight() {
      return this.height - this.marginY;
    }

    definer() {
      this.hexbin = d3.hexbin()
      .size([this.quadrantWidth, this.quadrantHeight])
      .radius(this.hexagonSize);

      this.renderCanvas();

      this.context = this.canvas.node().getContext("2d");
      this.points = [];
      this.hexagons = [];
    }

    renderCanvasStyles() {
      this.canvasStyles = document.createElement('style');
      this.canvasStyles.innerText = `.canvas-image { margin-top: ${this.marginY / 2}px; margin-left: ${this.marginX / 2}px; position: absolute }`;
      document.body.appendChild(this.canvasStyles);
    }

    renderCanvas() {
      this.elementToInject.select('canvas.canvas-image').remove();
      this.canvas = this.elementToInject.append("canvas")
      .attr("width", this.quadrantWidth)
      .attr("height", this.quadrantHeight)
      .classed('canvas-image', true);

      this.renderCanvasStyles();
    }

    renderSvg() {
      this.elementToInject.select('svg.hexagon-map').remove();
      this.svg = this.elementToInject.append("svg")
      .classed('hexagon-map', true)
      .attr("width", this.quadrantWidth)
      .attr("height", this.quadrantHeight)
      .attr('viewBox', `0, 0, ${this.quadrantWidth  + (this.hexagonSize * 2)}, ${this.quadrantHeight + (this.hexagonSize * 2.5)}`)
      .attr('transform', `translate(${this.marginX / 2}, ${this.marginY / 2})`);
    }

    definePointsAndHexagons(image) {
      this.context.drawImage(image, 0, 0, this.quadrantWidth, this.quadrantHeight);
      image = this.context.getImageData(0, 0, this.quadrantWidth, this.quadrantHeight);

      // Rescale the colors.
      for (let i = 0, n = this.quadrantWidth * this.quadrantHeight * 4, d = image.data; i < n; i += 4) {
        this.points.push([i / 4 % this.quadrantWidth, Math.floor(i / 4 / this.quadrantWidth), d[i]]);
      }

      this.color = d3.scaleLinear()
      .domain([d3.min(this.points, (d) => d[2]), d3.max(this.points, (d) => d[2])])
      .range([this.earthColor, this.waterColor]);

      this.hexagons = this.hexbin(this.points);
      this.hexagons.forEach((d) => {
        d.mean = d3.mean(d, (p) => p[2]);
      });
    }

    renderHexagons() {
      const t = (d) => {
        if (d.x < this.initialLatitude && this.initialLatitude < d.x + this.hexagonSize * 2
            && d.y < this.initialLongitude && this.initialLongitude < d.y + this.hexagonSize * 2 * 0.75
        ) {
          return true
        }
      };


      this.svg.select('g.hexagons').remove();
      this.hexagon = this.svg.append("g")
      .attr("class", "hexagons")
      .attr('transform', `translate(${this.hexagonSize}, ${this.hexagonSize})`)
      .selectAll("path")
      .data(this.hexagons)
      .enter().append("path")
      .attr('latitude', (d) => d.x)
      .attr('longitude', (d) => d.y)
      .attr("d", this.hexbin.hexagon(this.hexagonSize))
      .attr("transform", (d) => "translate(" + (d.x) + "," + (d.y) + ")")
      .attr('id', (d) => {
          if (t(d)) {
            return 'zeroPoint'
          }
        return 'regular'
      })
      .style("fill", (d) => {
        return this.color(d.mean)
      });
    }

    destroyImage() {
      this.canvas.remove()
    }


    render() {
      return new Promise((resolve, reject) => {
        this.renderBackgroundImage(this.config.imagePath, (image) => {
          try {
            this.definePointsAndHexagons(image);
            this.renderSvg();
            this.renderHexagons();
            this.destroyImage();
            this.defineNet();
            resolve();
          } catch(err) {
            reject(err)
          }

        })
      });
    }

    defineNet() {
      this.zeroPoint = d3.select('#zeroPoint');
      const size = this.zeroPoint.node().getBoundingClientRect();
      this.zeroPointHexagon = {};
      this.zeroPointHexagon.x = parseFloat(this.zeroPoint.attr('latitude'));
      this.zeroPointHexagon.y = parseFloat(this.zeroPoint.attr('longitude'));
      this.zeroPointHexagon.width = size.width;
      this.zeroPointHexagon.height = size.height;
      this.latitudesInHexagon = this.zeroPointHexagon.height / this.latitude;
      this.longitudesInHexagon = this.zeroPointHexagon.width / this.longitude;
    }

    renderBackgroundImage(path, callback) {
      const image = new Image;
      image.onload = function() { callback(image); };
      image.src = path;
    }

    findHexagon(lat, long) {
      let data;

      const y = (this.zeroPointHexagon.y - lat * this.latitude - this.zeroPointHexagon.height);
      const x = (this.zeroPointHexagon.x + long * this.longitude - this.zeroPointHexagon.width * 2);
      this.hexagons.forEach((hexagon) => {
        if (hexagon.y < y && y < hexagon.y + this.zeroPointHexagon.height
            && hexagon.x < x && x < hexagon.x + this.zeroPointHexagon.width
        ) {
          data = hexagon;
        }
      });
      if (data) {
        return {
          element: d3.select(`[latitude="${data.x}"][longitude="${data.y}"]`),
          lat: lat,
          long: long
        }
      }
      return {
        element: null,
        lat: lat,
        long: long
      };
    }

    setHex(mainDot, dots, index, mainDotCounter) {
      let nextDot = dots[index + 1];
      if (mainDot && nextDot) {
        let emptyHex = mainDot.getEmptyHexagonNearby(this);
        if (emptyHex && emptyHex.element) {
          nextDot.setDotToNewHexagon(emptyHex);
          this.setHex(mainDot, dots, index + 1, mainDotCounter);
        } else {
          this.setHex(dots[mainDotCounter + 1], dots, index, mainDotCounter + 1);
        }
      }
    };


    sortDots(arr) {
      const coreDots = arr.filter((dot) => dot.type === 'core');
      const verifDots = arr.filter((dot) => dot.type === 'verif');
      const proxyDots = arr.filter((dot) => dot.type === 'proxy');
      const torrentDots = arr.filter((dot) => dot.type === 'torrent');

      return coreDots.concat(verifDots.concat(proxyDots.concat(torrentDots)));
    }

    getDotsWithSameCoordinates() {
      let dotsGroups = d3.nest()
      .key( (d) => Math.round(d.latitude))
      .key( (d) => Math.round(d.longitude))
      .entries(this.dots.filter((dot) => !dot.isReleased));

      dotsGroups.forEach((dotsGroup) => {
        if (dotsGroup.values.length > 0) {
          dotsGroup.values.forEach((dotsGroup2) => {
            if (dotsGroup2.values.length > 0) {
              this.dotsGroups.push(dotsGroup2);
            }
          })
        }
      });
    }

    renderGroups() {
      this.getDotsWithSameCoordinates();
      this.dotsGroups.forEach((dotsGroup) => {
        dotsGroup.values[0].render();
        this.setHex(dotsGroup.values[0], this.sortDots(dotsGroup.values), 0, 0);
      });

      if (this.dots.filter((dot) => !dot.isReleased).length > 0) {
        this.getDotsWithSameCoordinates();
        this.dotsGroups.forEach((dotsGroup) => {
          dotsGroup.values[0].render();
          this.setHex(dotsGroup.values[0], this.sortDots(dotsGroup.values), 0, 0);
        });
      }
    }


    createDot(dot) {
      this.dots.push(new Dot(dot, this));
    }



  } 
    </script>

    <style>
      .parent {
          width: 1200px;
          height: 600px;
          margin: 0 auto;
      }
      .map-container {
          width: 100%;
          height: 100%;
      }
      body {
          margin: 0px;
      }
    </style>

</head>

<body>

<div class="parent">
    <div class="map-container"></div>
</div>



<?php

	
	$to_timestamp_ms=1000*time();
	$from_timestamp_ms=$to_timestamp_ms-5000;

	$STATS=array();
	$stat_item = array(
		"server"=>"",
		"group"=>"",
		"GEO_ip"=>"",
		"GEO_country"=>"",
		"GEO_city"=>"",
		"GEO_isp"=>"",
		"GEO_lat"=>"",
		"GEO_lon"=>"",
		"qps"=>0,
		"queue_size"=>0,
		"last_block_info_number"=>0,
		"last_block_info_hash"=>0,
		"last_block_info_type"=>0,
		"last_latency"=>0,
		"qps_inv"=>0,
		"qps_inv_sign"=>0,
		"qps_no_req"=>0,
		"qps_success"=>0,
		"qps_trash"=>0,
		"send_insuficent_funds"=>0,
		"send_invalid_transaction_value"=>0,
		"send_success"=>0,
		"send_verification_failed"=>0,
		"send_wallet_not_found"=>0
	);

  

  if (isset($_GET['net']) && $_GET['net'] == "test") {
    $net="net-test";
  } else if (isset($_GET['net']) && $_GET['net'] == "rocksdb") {
    $net="rocksdb";
  } else {
    $net="net-dev";
  }

	$url = "http://SEVER:PORT/get-statistic";
	$postdata = '{"params": {"network": "'.$net.'" , "from_timestamp_ms":'.$from_timestamp_ms.' , "to_timestamp_ms":'.$to_timestamp_ms.'}, "id": 1}';

	$result = post_content ($url,$postdata);
	$max_ts=0;
	if ($result['http_code'] == 200 && $result['errno'] == 0) {
		$json = json_decode($result['content'],true);
		// echo "<pre>";
		// print_r($json);
		// echo "</pre>";
		foreach ($json['result'] as $k_sec => $v_sec) {
			foreach ($v_sec['stat']['serversStat'] as $k_serv => $v_serv) {
				if ($v_sec['second'] > $max_ts) $max_ts = $v_sec['second'];
				$server = $v_serv['server'];
				$v_serv['server'] .= ("</td><td>".$v_serv['group']);
				if (!isset($STATS[$v_serv['server']][$v_sec['second']])) $STATS[$v_serv['server']][$v_sec['second']] = $stat_item;
				$STATS[$v_serv['server']][$v_sec['second']]['server'] = $server;
				$STATS[$v_serv['server']][$v_sec['second']]['group'] = $v_serv['group'];
				foreach($v_serv['statistic'] as $k_stat => $v_stat) {
					if ($v_stat['metric'] == 'count_requests') {
						$STATS[$v_serv['server']][$v_sec['second']]['qps'] = $v_stat['value'];
					} else if ($v_stat['metric'] == 'qps') {
						$STATS[$v_serv['server']][$v_sec['second']]['qps'] = $v_stat['value'];
					} else if ($v_stat['metric'] == 'queue_size') {
						$STATS[$v_serv['server']][$v_sec['second']]['queue_size'] = $v_stat['value'];
					} else if ($v_stat['metric'] == 'queue') {
						$STATS[$v_serv['server']][$v_sec['second']]['queue_size'] = $v_stat['value'];
					} else if ($v_stat['metric'] == 'qps_inv') {
						$STATS[$v_serv['server']][$v_sec['second']]['qps_inv'] = $v_stat['value'];
					} else if ($v_stat['metric'] == 'qps_inv_sign') {
						$STATS[$v_serv['server']][$v_sec['second']]['qps_inv_sign'] = $v_stat['value'];
					} else if ($v_stat['metric'] == 'qps_no_req') {
						$STATS[$v_serv['server']][$v_sec['second']]['qps_no_req'] = $v_stat['value'];
					} else if ($v_stat['metric'] == 'qps_success') {
						$STATS[$v_serv['server']][$v_sec['second']]['qps_success'] = $v_stat['value'];
					} else if ($v_stat['metric'] == 'qps_trash') {
						$STATS[$v_serv['server']][$v_sec['second']]['qps_trash'] = $v_stat['value'];
					} else if ($v_stat['metric'] == 'send_insuficent_funds') {
						$STATS[$v_serv['server']][$v_sec['second']]['send_insuficent_funds'] = $v_stat['value'];
					} else if ($v_stat['metric'] == 'send_invalid_transaction_value') {
						$STATS[$v_serv['server']][$v_sec['second']]['send_invalid_transaction_value'] = $v_stat['value'];
					} else if ($v_stat['metric'] == 'send_success') {
						$STATS[$v_serv['server']][$v_sec['second']]['send_success'] = $v_stat['value'];
					} else if ($v_stat['metric'] == 'send_verification_failed') {
						$STATS[$v_serv['server']][$v_sec['second']]['send_verification_failed'] = $v_stat['value'];
					} else if ($v_stat['metric'] == 'send_wallet_not_found') {
						$STATS[$v_serv['server']][$v_sec['second']]['send_wallet_not_found'] = $v_stat['value'];
					} else if ($v_stat['metric'] == 'ip') {
						$STATS[$v_serv['server']][$v_sec['second']]['GEO_ip'] = $v_stat['value'];
					} else if ($v_stat['metric'] == 'last_block_info') {
						$elems = preg_split("/[\s,:;]+/", $v_stat['value']);
						for ($i = 0 ; $i < count($elems) ; $i+=2) {
							if ($elems[$i] == "number") {
								$STATS[$v_serv['server']][$v_sec['second']]['last_block_info_number'] = $elems[$i+1];
							} else if ($elems[$i] == "hash") {
								$STATS[$v_serv['server']][$v_sec['second']]['last_block_info_hash'] = $elems[$i+1];
							} else if ($elems[$i] == "type") {
								$STATS[$v_serv['server']][$v_sec['second']]['last_block_info_type'] = $elems[$i+1];
							}
						}
						unset($elems);
					} else if ($v_stat['metric'] == 'geoip') {
						$elems = json_decode($v_stat['value'],true);
						//echo "<pre>";
						//print_r($elems);
						//echo "</pre>";
						$STATS[$v_serv['server']][$v_sec['second']]['GEO_country'] = $elems['country'];
						$STATS[$v_serv['server']][$v_sec['second']]['GEO_city'] = $elems['city'];
						$STATS[$v_serv['server']][$v_sec['second']]['GEO_isp'] = $elems['isp'];
						$STATS[$v_serv['server']][$v_sec['second']]['GEO_lat'] = $elems['lat'];
						$STATS[$v_serv['server']][$v_sec['second']]['GEO_lon'] = $elems['lon'];
						unset($elems);
					} else if ($v_stat['metric'] == 'last_latency') {
						$STATS[$v_serv['server']][$v_sec['second']]['last_latency'] = $v_stat['value'];
					}
				}
			}
		}
?>

<?php

		

		echo "<table>\n";
		echo "<tr>";
			echo "<th>node</th>";
			echo "<th>role</th>";
			echo "<th>ts</th>";
			echo "<th>qps</th>";
			echo "<th>queue</th>";
			echo "<th>height</th>";
			echo "<th>GEO_country</th>";
			//echo "<th>GEO_city</th>";
			//echo "<th>GEO_isp</th>";
			//echo "<th>GEO_lat</th>";
			//echo "<th>GEO_lon</th>";
			echo "<th>GEO_ip</th>";			
			echo "<th>last_latency</th>";
			echo "<th>400</th>";
			echo "<th>403</th>";
			echo "<th>horeq</th>";
			echo "<th>200</th>";
			echo "<th>400</th>";
			echo "<th>400</th>";
			echo "<th>400</th>";
			echo "<th>200</th>";
			echo "<th>403</th>";
			echo "<th>w_404</th>";
			echo "<th>last_block_info_hash</th>";
			echo "<th>last_block_info_type</th>";
		echo "</tr>\n";
		$cnt=0;
		$dot="";
		foreach($STATS as $k_serv => $v_serv) {
			$max_ts = $to_timestamp_ms/1000;
			while (!isset($v_serv[$max_ts]) && $max_ts > $from_timestamp_ms/1000) $max_ts--;
			if ($cnt > 0) $dot.=",";
			$dot.="{\n";
			$lat=$v_serv[$max_ts]['GEO_lat']/*+(rand(1,10)/5.0)*/;
			$lon=$v_serv[$max_ts]['GEO_lon']/*+(rand(1,10)/5.0)*/;

      //$lat=$v_serv[$max_ts]['GEO_lat'];
      //$lon=$v_serv[$max_ts]['GEO_lon'];

	        $dot.="lat: {$lat},\n";
	        $dot.="long: {$lon},\n";
	        if ($v_serv[$max_ts]['group'] == "core") {
	        	$dot.="size: 5,\n";
	        } else if ($v_serv[$max_ts]['group'] == "torrent") {
	        	$dot.="size: 5,\n";
	        } else if ($v_serv[$max_ts]['group'] == "verif") {
	        	$dot.="size: 5,\n";
	        } else if ($v_serv[$max_ts]['group'] == "proxy") {
	        	$dot.="size: 5,\n";
	        }  
	        
	        $dot.="type: '{$v_serv[$max_ts]['group']}'\n";
	     	$dot.="}\n";
	     	$cnt++;

			echo "<tr>";
			echo "<td>{$v_serv[$max_ts]['server']}</td>";
			echo "<td>{$v_serv[$max_ts]['group']}</td>";
			echo "<td>{$max_ts}</td>";
			echo "<td>{$v_serv[$max_ts]['qps']}</td>";
			echo "<td>{$v_serv[$max_ts]['queue_size']}</td>";
			echo "<td>{$v_serv[$max_ts]['last_block_info_number']}</td>";
			echo "<td>{$v_serv[$max_ts]['GEO_country']}</td>";
			//echo "<td>{$v_serv[$max_ts]['GEO_city']}</td>";
			//echo "<td>{$v_serv[$max_ts]['GEO_isp']}</td>";
			//echo "<td>{$v_serv[$max_ts]['GEO_lat']}</td>";
			//echo "<td>{$v_serv[$max_ts]['GEO_lon']}</td>";
			echo "<td>{$v_serv[$max_ts]['GEO_ip']}</td>";
			echo "<td>{$v_serv[$max_ts]['last_latency']}</td>";
			echo "<td>{$v_serv[$max_ts]['qps_inv']}</td>";
			echo "<td>{$v_serv[$max_ts]['qps_inv_sign']}</td>";
			echo "<td>{$v_serv[$max_ts]['qps_no_req']}</td>";
			echo "<td>{$v_serv[$max_ts]['qps_success']}</td>";
			echo "<td>{$v_serv[$max_ts]['qps_trash']}</td>";
			echo "<td>{$v_serv[$max_ts]['send_insuficent_funds']}</td>";
			echo "<td>{$v_serv[$max_ts]['send_invalid_transaction_value']}</td>";
			echo "<td>{$v_serv[$max_ts]['send_success']}</td>";
			echo "<td>{$v_serv[$max_ts]['send_verification_failed']}</td>";
			echo "<td>{$v_serv[$max_ts]['send_wallet_not_found']}</td>";
			echo "<td>{$v_serv[$max_ts]['last_block_info_hash']}</td>";
			echo "<td>{$v_serv[$max_ts]['last_block_info_type']}</td>";
			echo "</tr>\n";
		}
		echo "</table>\n";

	}


?>

<script>
    const dots = [
    <?php
      echo $dot;
    ?>
    ];

    const config = {
      selector: '.map-container',
      imagePath: './map-normal.png',
      hexagonSize: 4
    };

    const map = new Map(config);

    map.render().then(() => {
      dots.forEach((dot) => {
        map.createDot(dot);
      });
      map.renderGroups()
    })

</script>

</body>
</html>