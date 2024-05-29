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
        <button type="submit">Send</button>
    </form>

    <script>
        //check local storage have client-token = rand
        if (!localStorage.getItem('client-token')) {
            localStorage.setItem('client-token', Math.random().toString(36).substring(7));
        }

        const token = localStorage.getItem('client-token');
        const socket = new WebSocket('ws://localhost:9090');
        const channel = 'client-' + token;

        console.log(channel);

        socket.addEventListener('open', function(event) {
            console.log('WebSocket is open now.');
            // Subscribe to the client's channel
            socket.send(JSON.stringify({
                action: 'subscribe',
                channel: channel
            }));
        });

        socket.addEventListener('message', function(event) {
            console.log('message received');
            const data = JSON.parse(event.data);

            const template = `
                    <p><b>${data.channel}</b> : <i>${data.message}</i></p>
            `;
            document.getElementById('chat').innerHTML += template;
        
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
            event.preventDefault(); // Prevent the form from submitting the traditional way
            const input = document.getElementById('messageInput');
            const message = input.value;

            // Send the message through the WebSocket
            socket.send(JSON.stringify({
                action: 'publish',
                channel: channel,
                message: message
            }));

            // Clear the input field
            input.value = '';
        });

        
    </script>
</body>

</html>
