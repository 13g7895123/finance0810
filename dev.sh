#!/bin/bash

echo "Starting Finance CRM in Development Mode..."
echo "Frontend: npm run dev (Hot reload enabled)"
echo "Backend: Laravel with debug enabled"

# Stop any existing containers
docker-compose -f docker-compose.yml -f docker-compose.dev.yml down

# Build and start in development mode
docker-compose -f docker-compose.yml -f docker-compose.dev.yml up --build

echo "Development environment stopped."