var maps = new Array();
var geocoder = null;
var bounds = new Array();
var infoWindows = new Array();

/*
 *	Initialize the map.
 */
function GLoad(mapId) {
	
	//Create the InfoWindow
	infoWindows[mapId] = new google.maps.InfoWindow({
						maxWidth: 200
					});
					
	// Initial map options:
	var initMapOptions = {
		center: new google.maps.LatLng(38.685527,-96.152435), //Center on the USA
		zoom: 4,
		mapTypeControl: true,
		streetViewControl: false,
		disableDoubleClickZoom: false,
		maxZoom: 19,
		mapTypeId: google.maps.MapTypeId.ROADMAP
	}

	maps[mapId] = new google.maps.Map($('.map[data-map-id="'+mapId+'"]')[0], initMapOptions);
	bounds[mapId] = new google.maps.LatLngBounds();
	maps[mapId].fitBounds(bounds[mapId]);
}

/*
 * Add a marker to the map at the given address. Displays the given text when clicked on.
 * Note that this function uses google's geocoding instead of a reason geocode object.
 */
function showAddress(mapId, adr, displayText) {
    if (geocoder) { //would this ever be false?
    	geocoder.geocode(
    		{address: adr}, 
    		function(results, status) {
    			if (status == google.maps.GeocoderStatus.OK) {
    				displayMarker(maps[mapId], bounds[mapId], infoWindows[mapId], results[0].geometry.location, displayText);
				} else {
					alert("Sorry " + adr + " not found");
				}
			});
    }
}

/*
 * This function extends the bounds to include the point designated by the given
 * latitude and longitude.
 */
function extendBounds(mapId, lat, lng) {
	bounds[mapId].extend(google.maps.LatLng(lat, lng, true));
}

/*
 * Display a marker at the given latitude and longitude. The displayText
 * is shown when the marker is clicked on. icon and shadow are optional. icon and shadow
 * should be strings which are the names of javascript variables referring to google.map.MarkerImages.
 */
function showPoint(mapId, lat, lng, displayText, icon, shadow) {
    var point = new google.maps.LatLng(lat, lng, true);
    displayMarker(maps[mapId], bounds[mapId], infoWindows[mapId], point, displayText, icon, shadow);
}

/*
 * This function displays a marker on the given map, at the given latlng, extending the given 
 * bounds, and displaying the given text in the given infoWindow when clicked.
 * icon and shadow are optional. icon and shadow should be strings which are the names of 
 * javascript variables referring to google.map.MarkerImages.
 */
function displayMarker(map, bounds, infowin, latlng, displayText, icon, shadow) {
	var marker = new google.maps.Marker({
									map: map, 
									position: latlng
								});
	if (typeof icon !== "undefined") {
        marker.setIcon(eval(icon));
    }
    if (typeof shadow !== "undefined") {
    	marker.setShadow(eval(shadow));
    }
    if (displayText != "") 
    {
        google.maps.event.addListener(marker, "click", function() {
														infowin.setContent(displayText);
														infowin.open(map, marker);
													});
    }
    bounds.extend(latlng);
}

/*
 * Convert the specially formatted list HTML to markers on the map.
 */
function convertListToMap(mapId){
	$(".mapInfo[data-map-id='"+mapId+"']").css("visibility", "hidden"); //We hide _and_ remove because the removing is sometimes a bit slow.
	$("ul.mapCommands[data-map-id='"+mapId+"'] > li").each(function(){
		if($(this).attr("class") == "showPoint"){
			var text = $(this).find(".displayText").html();
			var lat = $(this).find(".latlon > .lat").text();
			var lon = $(this).find(".latlon > .lon").text();
			var icon = $(this).find(".icon").html();
			var shadow = $(this).find(".shadow").html();
			showPoint(mapId, lat, lon, text, icon, shadow);
		}
		//NOTE: Add other conditionals here to add support for other interactions with the map.
	});
	$(".mapInfo[data-map-id='"+mapId+"']").remove();
}

$(document).ready(function(){
	//init geocoder
	geocoder = new google.maps.Geocoder();
	//show map element
	$(".map").each(function () {
		mapId = $(this).attr('data-map-id');
		$(".map[data-map-id='"+mapId+"']").css("display","block");
		//load map
		GLoad(mapId);
		//display points
		convertListToMap(mapId);	
	});
});