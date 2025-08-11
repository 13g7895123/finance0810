#!/bin/bash

echo "Starting Finance CRM in Development Mode..."
echo "Frontend: npm run dev (Hot reload enabled)"
echo "Backend: Laravel with debug enabled"
echo ""
echo "NOTE: This script will rebuild containers to ensure .env changes are loaded"

# Stop any existing containers and remove volumes to ensure clean state
echo "Stopping existing containers and cleaning up..."
docker-compose -f docker-compose.yml -f docker-compose.dev.yml down -v

# Remove any potentially cached environment variables
echo "Cleaning Docker system cache..."
docker system prune -f

# Rebuild and start in development mode with force-recreate to pick up .env changes
echo "Building and starting containers with fresh environment..."
docker-compose -f docker-compose.yml -f docker-compose.dev.yml up --build --force-recreate

echo "Development environment stopped."