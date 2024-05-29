<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebSocket Client</title>
</head>
<body>
    <h1>WebSocket Client</h1>
    <p id="message"></p>

    <!-- Input form for sending messages -->
    <form id="messageForm">
        <input type="text" id="messageInput" placeholder="Enter your message">
        <button type="submit">Send</button>
    </form>

    <script>
        // Create a new WebSocket connection
        const socket = new WebSocket('ws://localhost:9090');

        // Connection opened
        socket.addEventListener('open', function (event) {
            console.log('WebSocket is open now.');
        });

        // Listen for messages
        socket.addEventListener('message', function (event) {
            console.log('Message from server ', event.data);

            // Display the message inside the <p> tag
            const messageElement = document.getElementById('message');
            messageElement.textContent = event.data;
        });

        // Connection closed
        socket.addEventListener('close', function (event) {
            console.log('WebSocket is closed now.');
        });

        // Handle errors
        socket.addEventListener('error', function (event) {
            console.error('WebSocket error observed:', event);
        });

        // Handle form submission
        const form = document.getElementById('messageForm');
        form.addEventListener('submit', function (event) {
            event.preventDefault(); // Prevent the form from submitting the traditional way
            const input = document.getElementById('messageInput');
            const message = input.value;

            // Send the message through the WebSocket
            socket.send(message);

            // Clear the input field
            input.value = '';
        });
    </script>
</body>
</html>
