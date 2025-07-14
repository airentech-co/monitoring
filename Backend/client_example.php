<?php
/**
 * Client Example Script
 * 
 * This script demonstrates how monitoring clients can send usernames
 * to the server using different methods.
 */

// Server configuration
$server_url = "http://localhost:8924/html/../";

echo "<h2>Client Username Update Examples</h2>";

// Example 1: Update username via dedicated API
echo "<h3>Example 1: Update Username via API</h3>";
echo "<p>Use the dedicated username update API:</p>";
echo "<pre>";
echo "curl -X POST {$server_url}webapi.php \\\n";
echo "  -d \"action=update_username\" \\\n";
echo "  -d \"username=john_doe\"\n";
echo "</pre>";

// Example 2: Send username via tic event
echo "<h3>Example 2: Send Username via Tic Event</h3>";
echo "<p>Include username in regular tic events:</p>";
echo "<pre>";
echo "curl -X POST {$server_url}eventhandler.php \\\n";
echo "  -d \"Event=Tic\" \\\n";
echo "  -d \"Version=1.0\" \\\n";
echo "  -d \"MacAddress=AA:BB:CC:DD:EE:FF\" \\\n";
echo "  -d \"Username=john_doe\"\n";
echo "</pre>";

// Example 3: Send username via JSON event
echo "<h3>Example 3: Send Username via JSON Event</h3>";
echo "<p>Include username in JSON events:</p>";
echo "<pre>";
echo "curl -X POST {$server_url}eventhandler.php \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -d '{\n";
echo "    \"Event\": \"BrowserHistory\",\n";
echo "    \"Version\": \"1.0\",\n";
echo "    \"MacAddress\": \"AA:BB:CC:DD:EE:FF\",\n";
echo "    \"Username\": \"john_doe\",\n";
echo "    \"BrowserHistories\": [...]\n";
echo "  }'\n";
echo "</pre>";

// Example 4: PHP client code
echo "<h3>Example 4: PHP Client Code</h3>";
echo "<p>Here's how to implement username updates in your client:</p>";
echo "<pre>";
echo "// Method 1: Dedicated API\n";
echo "\$data = [\n";
echo "    'action' => 'update_username',\n";
echo "    'username' => \$current_username\n";
echo "];\n";
echo "\$ch = curl_init();\n";
echo "curl_setopt(\$ch, CURLOPT_URL, '{$server_url}webapi.php');\n";
echo "curl_setopt(\$ch, CURLOPT_POST, true);\n";
echo "curl_setopt(\$ch, CURLOPT_POSTFIELDS, http_build_query(\$data));\n";
echo "curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);\n";
echo "\$response = curl_exec(\$ch);\n";
echo "curl_close(\$ch);\n";
echo "\n";
echo "// Method 2: Tic Event\n";
echo "\$data = [\n";
echo "    'Event' => 'Tic',\n";
echo "    'Version' => '1.0',\n";
echo "    'MacAddress' => \$mac_address,\n";
echo "    'Username' => \$current_username\n";
echo "];\n";
echo "\$ch = curl_init();\n";
echo "curl_setopt(\$ch, CURLOPT_URL, '{$server_url}eventhandler.php');\n";
echo "curl_setopt(\$ch, CURLOPT_POST, true);\n";
echo "curl_setopt(\$ch, CURLOPT_POSTFIELDS, http_build_query(\$data));\n";
echo "curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);\n";
echo "\$response = curl_exec(\$ch);\n";
echo "curl_close(\$ch);\n";
echo "</pre>";

echo "<h3>Notes:</h3>";
echo "<ul>";
echo "<li>Usernames must be 2-50 characters long</li>";
echo "<li>Usernames must be unique across all monitors</li>";
echo "<li>The server automatically detects the client's IP address</li>";
echo "<li>Username updates are validated and sanitized</li>";
echo "<li>You can send usernames with any event type</li>";
echo "</ul>";
?> 