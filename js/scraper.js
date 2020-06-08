//alert("This alert box was called with the onload event");

function fetchEvents() {
			var xmlhttp = new XMLHttpRequest();
			xmlhttp.onreadystatechange = function() {
				if (this.readyState == 4 && this.status == 200) {
					document.getElementById("events").innerHTML = "";
					document.getElementById("events").innerHTML = this.responseText;
				}
			};
			xmlhttp.open("GET", "getEvents.php?url=" + document.getElementById('resultsPage').value, true);
			xmlhttp.send();
};