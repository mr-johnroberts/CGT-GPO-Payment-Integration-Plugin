// Event listener for 'message' event
window.addEventListener('message', receiveMessage, false);

// Function to handle received messages
function receiveMessage(event) {
    // Log received data and origin
    console.log('Received from GPO: ', event.data);
    console.log('Received from GPO: ', event.origin);
    
    // URL for callback endpoint
    var destination = "https://teubiva.com/wp-json/teubiva/v1/payment-callback/".concat(event.data);
    console.log(destination);
    
    // Check if the message origin is from the expected domain
    if (!event.origin.includes('emis.co.ao')) { 
        return; // Ignore messages from other origins
    }
    
    // Send data to your server's callback endpoint using fetch
    fetch(destination)
        .then(function (response) {
            return response.json();
        })
        .then(function (myJson) {
            // Process the response data
            var redirect_to;
            if (myJson.status == "ACCEPTED"){
                redirect_to = window.location.origin + "/checkout/order-received/";
            } else {
                redirect_to = window.location.origin + "/checkout/";
            }

           // Redirect the user to the appropriate URL
           window.location.replace(redirect_to);

           // Close the GPO web frame window
           closeGPOWebFrame();
       })
       .catch(function (error) {
           console.error('Error fetching data:', error);
       });
}