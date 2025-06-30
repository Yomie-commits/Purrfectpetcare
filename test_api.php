<?php
// Simple API test script
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Test - Pet Care System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; }
        .info { background-color: #d1ecf1; border-color: #bee5eb; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🐾 Pet Care System - API Test</h1>
    
    <div class="test-section info">
        <h3>Database Connection Test</h3>
        <button onclick="testConnection()">Test Database Connection</button>
        <div id="connection-result"></div>
    </div>

    <div class="test-section info">
        <h3>Registration Test</h3>
        <button onclick="testRegistration()">Test User Registration</button>
        <div id="registration-result"></div>
    </div>

    <div class="test-section info">
        <h3>Login Test</h3>
        <button onclick="testLogin()">Test User Login</button>
        <div id="login-result"></div>
    </div>

    <div class="test-section info">
        <h3>Manual API Test</h3>
        <form id="api-test-form">
            <label>Action: <input type="text" id="action" value="test_connection" /></label><br><br>
            <label>Data (JSON): <textarea id="data" rows="4" cols="50">{"test": "data"}</textarea></label><br><br>
            <button type="submit">Test API</button>
        </form>
        <div id="manual-result"></div>
    </div>

    <script>
        async function testConnection() {
            const resultDiv = document.getElementById('connection-result');
            resultDiv.innerHTML = '<p>Testing...</p>';
            
            try {
                const formData = new FormData();
                formData.append('action', 'test_connection');
                
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="success">
                            <h4>✅ Connection Successful</h4>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="error">
                            <h4>❌ Connection Failed</h4>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="error">
                        <h4>❌ Network Error</h4>
                        <p>Error: ${error.message}</p>
                        <p>This usually means the API is returning HTML instead of JSON.</p>
                        <p>Check your database configuration in db_connect.php</p>
                    </div>
                `;
            }
        }

        async function testRegistration() {
            const resultDiv = document.getElementById('registration-result');
            resultDiv.innerHTML = '<p>Testing...</p>';
            
            try {
                const formData = new FormData();
                formData.append('action', 'register');
                formData.append('name', 'Test User');
                formData.append('email', 'test@example.com');
                formData.append('password', 'password123');
                formData.append('role', 'owner');
                
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="success">
                            <h4>✅ Registration Successful</h4>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="error">
                            <h4>❌ Registration Failed</h4>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="error">
                        <h4>❌ Network Error</h4>
                        <p>Error: ${error.message}</p>
                    </div>
                `;
            }
        }

        async function testLogin() {
            const resultDiv = document.getElementById('login-result');
            resultDiv.innerHTML = '<p>Testing...</p>';
            
            try {
                const formData = new FormData();
                formData.append('action', 'login');
                formData.append('email', 'john@example.com');
                formData.append('password', 'password');
                
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="success">
                            <h4>✅ Login Successful</h4>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="error">
                            <h4>❌ Login Failed</h4>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="error">
                        <h4>❌ Network Error</h4>
                        <p>Error: ${error.message}</p>
                    </div>
                `;
            }
        }

        document.getElementById('api-test-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const resultDiv = document.getElementById('manual-result');
            const action = document.getElementById('action').value;
            const dataText = document.getElementById('data').value;
            
            resultDiv.innerHTML = '<p>Testing...</p>';
            
            try {
                const formData = new FormData();
                formData.append('action', action);
                
                // Parse JSON data and add to form data
                const data = JSON.parse(dataText);
                for (const [key, value] of Object.entries(data)) {
                    formData.append(key, value);
                }
                
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const responseData = await response.json();
                
                resultDiv.innerHTML = `
                    <div class="success">
                        <h4>✅ API Response</h4>
                        <pre>${JSON.stringify(responseData, null, 2)}</pre>
                    </div>
                `;
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="error">
                        <h4>❌ Error</h4>
                        <p>Error: ${error.message}</p>
                    </div>
                `;
            }
        });
    </script>
</body>
</html> 