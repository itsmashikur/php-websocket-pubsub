<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebSocket Client</title>
</head>

<body>

    <h1>WebSocket Client</h1>
    <div id="chat"></div>

    <!-- Input form for sending messages -->
    <form id="messageForm">
        <input type="text" id="messageInput" placeholder="Enter your message">
        <input type="text" id="channelInput" placeholder="Enter your channel">
        <button type="submit">Send</button>
    </form>

    <script>
        //check local storage have client-token = rand
        if (!localStorage.getItem('admin-token')) {
            localStorage.setItem('admin-token', Math.random().toString(36).substring(7));
        }

        const token = localStorage.getItem('admin-token');
        const socket = new WebSocket('ws://localhost:9090');
        const channel = 'admin-' + token;

        console.log(channel);

        socket.addEventListener('open', function(event) {
            console.log('WebSocket is open now.');
            // Subscribe to the admin's channel
            socket.send(JSON.stringify({
                action: 'subscribe',
                channel: channel
            }));
        });

        socket.addEventListener('message', function(event) {
            const data = JSON.parse(event.data);
            //set p tag
            const p = document.createElement('p');
            p.textContent = data.message;
            document.getElementById('chat').appendChild(p);
        });

        // Connection closed
        socket.addEventListener('close', function(event) {
            console.log('WebSocket is closed now.');
        });

        // Handle errors
        socket.addEventListener('error', function(event) {
            console.error('WebSocket error observed:', event);
        });

        // Handle form submission
        const form = document.getElementById('messageForm');

        form.addEventListener('submit', function(event) {

            event.preventDefault(); 

            const message = document.getElementById('messageInput').value;
            const destination = document.getElementById('channelInput').value;

            // Send the message through the WebSocket
            socket.send(JSON.stringify({
                action: 'publish',
                channel: channel,
                destination: destination,
                message: message
            }));

            // Clear the input field
            document.getElementById('messageInput').value = '';
            document.getElementById('channelInput').value = '';
        });

        
    </script>
</body>

</html>
