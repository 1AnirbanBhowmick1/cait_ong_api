#!/bin/bash

# Quick test script to verify the .env file creation step from the workflow
# This simulates what the GitHub Actions workflow does

set -e

echo "🧪 Testing environment file creation step..."
echo ""

# Remove existing .env if present
if [ -f .env ]; then
    echo "⚠️  Removing existing .env file..."
    rm .env
fi

# Create .env file exactly as the workflow does
echo "📝 Creating .env file..."
cat > .env << EOF
APP_NAME=CaiTong
APP_ENV=testing
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=testing
DB_USERNAME=test
DB_PASSWORD=password
DB_SCHEMA=public

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=null
EOF

# Verify the file was created
if [ -f .env ]; then
    echo "✅ .env file created successfully!"
    echo ""
    echo "📄 File contents:"
    echo "----------------------------------------"
    cat .env
    echo "----------------------------------------"
    echo ""
    echo "✅ Test passed! The workflow step should work correctly."
else
    echo "❌ Failed to create .env file"
    exit 1
fi

