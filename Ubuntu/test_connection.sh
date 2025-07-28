#!/bin/bash

echo "=== Server Connection Test ==="

# Read settings from settings.ini
if [ -f "settings.ini" ]; then
    SERVER_IP=$(grep "^ip=" settings.ini | cut -d'=' -f2)
    SERVER_PORT=$(grep "^port=" settings.ini | cut -d'=' -f2)
else
    SERVER_IP="192.168.1.40"
    SERVER_PORT="8924"
fi

echo "Server IP: $SERVER_IP"
echo "Server Port: $SERVER_PORT"

echo ""
echo "=== Testing Basic Connectivity ==="
if nc -z -w5 $SERVER_IP $SERVER_PORT; then
    echo "✓ TCP connection successful"
else
    echo "✗ TCP connection failed"
fi

echo ""
echo "=== Testing eventhandler endpoint ==="
curl -v --connect-timeout 10 "http://$SERVER_IP:$SERVER_PORT/eventhandler" 2>&1

echo ""
echo "=== Testing webapi endpoint ==="
curl -v --connect-timeout 10 "http://$SERVER_IP:$SERVER_PORT/webapi" 2>&1

echo ""
echo "=== Testing webapi with file upload ==="
echo "test" > /tmp/test.txt
curl -v --connect-timeout 10 \
  -F "fileToUpload=@/tmp/test.txt" \
  "http://$SERVER_IP:$SERVER_PORT/webapi" 2>&1
rm /tmp/test.txt

echo ""
echo "=== Testing eventhandler with JSON Data ==="
curl -v --connect-timeout 10 \
  -H "Content-Type: application/json" \
  -d '{"Event":"Ping","Version":"1.0","MacAddress":"test"}' \
  "http://$SERVER_IP:$SERVER_PORT/eventhandler" 2>&1

echo ""
echo "=== Network Interface Info ==="
ip addr show | grep "inet " | grep -v "127.0.0.1"

echo ""
echo "=== Route to Server ==="
traceroute -n -w1 $SERVER_IP 2>/dev/null || echo "traceroute not available" 