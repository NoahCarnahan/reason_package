$(document).ready(function() {
	var width = $('#countryRow input').outerWidth();

	$('#countryRow').after('<tr valign="top" id="mapRow">'+
	'<td align="right" class="words">Map:</td>'+
	'<td align="left">'+
		'<div id="mapDiv" style="width: '+width+'px; height: '+width+'px;"></div>'+
	'</td>'+
	'</tr>'+
	'<tr valign="top" id="mapButtonRow">'+
	'<td align="right">&nbsp;</td>'+
	'<td align="left">'+
		'<input id="kmlToLatLong" type="submit" value="Generate Lat/Long from KML" />'+
	'</td>'+
	'</tr>');
	
	initialize_map();
	$("input#kmlToLatLong").click(function(){
		kml = $("tr#kmlsnippetRow textarea").val()
		var bounds = new google.maps.LatLngBounds();
		$('coordinates',kml).each(function(){
			var coords = $(this).html();
			coords = coords.split(' ');
			for (i in coords)
			{
				var parts = coords[i].split(',')
				if (parts.length >= 2)
					bounds.extend(new google.maps.LatLng(parseFloat(parts[1]),parseFloat(parts[0])))
			}
		});

		var center = bounds.getCenter();
		
		marker.setVisible(true);
		marker.setPosition(center);
		map.panTo(center);
		updateLatLongFields(center);
		
		// We don't want our button actually sumbitting though
		return false;
	});
		
});

var map;
var marker;

function initialize_map()
{
	// The initial options for our map
	var initMapOptions = {
			center: new google.maps.LatLng(38.685527,-96.152435), //Center on the USA
			zoom: 3,
			mapTypeControl: true,
			disableDoubleClickZoom: false,
			mapTypeId: google.maps.MapTypeId.ROADMAP
		}
	map = new google.maps.Map(document.getElementById("mapDiv"), initMapOptions);
	
	//Create the marker icon
	var tinyIcon = new google.maps.MarkerImage(
		"//maps.gstatic.com/mapfiles/ridefinder-images/mm_20_blue.png",
		new google.maps.Size(12, 20),
		new google.maps.Point(0,0),
		new google.maps.Point(6, 20) 
		);
	var tinyIconShadow = new google.maps.MarkerImage(
		"//maps.gstatic.com/mapfiles/ridefinder-images/mm_20_shadow.png",
		new google.maps.Size(22, 20),
		new google.maps.Point(0,0),
		new google.maps.Point(6, 20) 
		);
	//Create the marker
	marker = new google.maps.Marker({draggable: true,
									 icon: tinyIcon,
									 shadow: tinyIconShadow,
									 position: google.maps.LatLng(38.685527,-96.152435),
									 map: map,
									 visible: false
									 });
									 
	//When map is clicked, move marker to the clicked position and update the lat lng fields in the form
	google.maps.event.addListener(map, "click", function(event){
		marker.setVisible(true);
		marker.setPosition(event.latLng);
		map.panTo(event.latLng);
		updateLatLongFields(event.latLng);
	});
	//When marker is dragged...
	google.maps.event.addListener(marker, "dragend", function(){
		latlng = marker.getPosition();
		map.panTo(latlng);
		updateLatLongFields(latlng);
	});
	//When the mode is changed to satellite, hide the buildings overlay
	google.maps.event.addListener(map, 'maptypeid_changed', function() {
		if (map.getMapTypeId() == google.maps.MapTypeId.SATELLITE || map.getMapTypeId() == google.maps.MapTypeId.HYBRID) 
		{
			map.overlayMapTypes.setAt(baseMapOverlay.indexInMap, null);
		} 
		else 
		{
			// Bring back baseMapOverlay
			map.overlayMapTypes.setAt(baseMapOverlay.indexInMap, baseMapOverlay);
		}
	});

	//If the form's lat and longitude are not empty, set an initial position for the marker and show it
	var lat = $('#latitudeRow input').val();
	var lng = $('#longitudeRow input').val();
	if (!(lat == '' && lng == '') && !(lat == '0' && lng == '0'))
	{
		latlng = new google.maps.LatLng(parseFloat(lat), parseFloat(lng));
		marker.setVisible(true);
		marker.setPosition(latlng);
		map.setCenter(latlng);
	}
	
	$('#mapDiv').css('cursor','default');
}

function updateLatLongFields(latlng)
{
	$('#latitudeRow input').val(latlng.lat());
	$('#longitudeRow input').val(latlng.lng());
}