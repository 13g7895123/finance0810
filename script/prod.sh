#!/bin/bash

echo "Starting Finance CRM in Production Mode..."
echo "Frontend: Built static files served by Node.js"
echo "Backend: Laravel optimized for production"

# Stop any existing containers
docker-compose down

# Build and start in production mode
docker-compose up --build -d

echo "Production environment started in background."
echo "Access the application at: http://localhost:${FRONTEND_PORT:-3000}"