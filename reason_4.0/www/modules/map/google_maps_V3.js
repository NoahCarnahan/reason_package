var map = null;
var geocoder = null;
var bounds = null;
var infoWindow = null;

/*
 *	Initialize the map.
 */
function GLoad() {
	
	var standardTileSize = new google.maps.Size(256, 256);
	
	//Create the InfoWindow
	infoWindow = new google.maps.InfoWindow({
						maxWidth: 200
					});
					
	// Initial map options:
	var initMapOptions = {
		//center: new google.maps.LatLng(44.46201924941167, -93.15387010574341), //Center on Carleton
		center: new google.maps.LatLng(38.685527,-96.152435), //Center on the USA
		zoom: 4,
		mapTypeControl: true,
		streetViewControl: false,
		disableDoubleClickZoom: false,
		maxZoom: 19,
		mapTypeId: google.maps.MapTypeId.ROADMAP
	}
	
	map = new google.maps.Map(document.getElementById("map"), initMapOptions);
	geocoder = new google.maps.Geocoder();
	bounds = new google.maps.LatLngBounds();

	map.fitBounds(bounds);
}

/*
 * Add a marker to the map at the given address. Displays the given text when clicked on.
 * Note that this function uses google's geocoding instead of a reason geocode object.
 */
function showAddress(adr, displayText) {
    if (geocoder) { //would this ever be false?
    	geocoder.geocode(
    		{address: adr}, 
    		function(results, status) {
    			if (status == google.maps.GeocoderStatus.OK) {
    				displayMarker(map, bounds, infoWindow, results[0].geometry.location, displayText);
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
function extendBounds(lat, lng) {
	bounds.extend(google.maps.LatLng(lat, lng, true));
}

/*
 * Display a marker at the given latitude and longitude. The displayText
 * is shown when the marker is clicked on. icon and shadow are optional. icon and shadow
 * should be strings which are the names of javascript variables referring to google.map.MarkerImages.
 */
function showPoint(lat, lng, displayText, icon, shadow) {
    var point = new google.maps.LatLng(lat, lng, true);
    displayMarker(map, bounds, infoWindow, point, displayText, icon, shadow);
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
function convertListToMap(){
	$("#mapInfo").css("visibility", "hidden"); //We hide and remove because the removing is sometimes a bit slow.
	$("ul.mapCommands > li").each(function(){
		if($(this).attr("class") == "showPoint"){
			var text = $(this).find(".displayText").html();
			var lat = $(this).find(".latlon > .lat").text();
			var lon = $(this).find(".latlon > .lon").text();
			var icon = $(this).find(".icon").html();
			var shadow = $(this).find(".shadow").html();
			showPoint(lat, lon, text, icon, shadow);
		}
		//NOTE: Add other conditionals here to add support for other interactions with the map.
	});
	$("#mapInfo").remove();
}

$(document).ready(function(){
	//show map element
	$("#map").css("display","block");
	//load map
	GLoad();
	//display points
	convertListToMap();
});