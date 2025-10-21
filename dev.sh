#!/bin/bash

# Point 2: Quick Frontend Development Server Starter
# Simple wrapper for starting frontend dev server

cd "$(dirname "${BASH_SOURCE[0]}")/frontend"

# Ensure .env.development exists
if [ ! -f ".env.development" ]; then
    echo "ERROR: .env.development not found!"
    echo "Please create it first or use start-frontend-dev.sh for a guided setup."
    exit 1
fi

# Install dependencies if needed
if [ ! -d "node_modules" ]; then
    echo "Installing dependencies..."
    npm install
fi

# Start the dev server
echo "Starting frontend development server..."
echo "Server will be available at: http://localhost:3301"
echo ""

npm run dev
