#!/bin/bash

# Point 2: Frontend Development Server Startup Script
# This script starts the Nuxt frontend development server with the correct .env.development configuration

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRONTEND_DIR="$SCRIPT_DIR/frontend"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  Frontend Development Server Startup  ${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Function to print colored messages
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if frontend directory exists
if [ ! -d "$FRONTEND_DIR" ]; then
    print_error "Frontend directory not found: $FRONTEND_DIR"
    exit 1
fi

cd "$FRONTEND_DIR"
print_info "Changed to frontend directory: $FRONTEND_DIR"
echo ""

# Check if .env.development exists
if [ ! -f ".env.development" ]; then
    print_error ".env.development file not found!"
    print_info "Please create .env.development file with the following content:"
    echo ""
    echo "NODE_ENV=development"
    echo "NUXT_PUBLIC_API_BASE_URL=http://localhost:9221/api"
    echo "NUXT_DEBUG=true"
    echo "NUXT_SKIP_AUTH=false"
    echo ""
    exit 1
fi

print_success "Found .env.development file"

# Display .env.development contents (excluding sensitive data)
echo ""
print_info "Development environment configuration:"
echo -e "${YELLOW}----------------------------------------${NC}"
grep -v "FIREBASE_API_KEY\|FIREBASE_APP_ID" .env.development | sed 's/^/  /'
echo -e "${YELLOW}----------------------------------------${NC}"
echo ""

# Check if package.json exists
if [ ! -f "package.json" ]; then
    print_error "package.json not found in frontend directory!"
    exit 1
fi

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    print_warning "node_modules directory not found. Installing dependencies..."
    echo ""

    # Check if npm is installed
    if ! command -v npm &> /dev/null; then
        print_error "npm is not installed. Please install Node.js and npm first."
        exit 1
    fi

    npm install
    print_success "Dependencies installed successfully"
    echo ""
fi

# Check Node.js version
NODE_VERSION=$(node -v)
print_info "Node.js version: $NODE_VERSION"

# Check npm version
NPM_VERSION=$(npm -v)
print_info "npm version: $NPM_VERSION"
echo ""

# Check if port 3301 is available
if lsof -Pi :3301 -sTCP:LISTEN -t >/dev/null 2>&1 ; then
    print_warning "Port 3301 is already in use!"
    print_info "Checking which process is using port 3301..."
    lsof -i :3301 | grep LISTEN || true
    echo ""
    read -p "Do you want to kill the existing process and continue? (y/N): " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        print_info "Killing process on port 3301..."
        lsof -ti:3301 | xargs kill -9 2>/dev/null || true
        sleep 2
        print_success "Port 3301 is now available"
    else
        print_error "Cannot start development server. Port 3301 is in use."
        exit 1
    fi
fi

# Ensure we're using the development environment
export NODE_ENV=development

# Print startup information
echo ""
print_info "Starting Nuxt development server..."
print_info "Server will be available at:"
echo -e "  ${GREEN}http://localhost:3301${NC}"
echo -e "  ${GREEN}http://0.0.0.0:3301${NC}"
echo ""
print_info "API Backend: $(grep NUXT_PUBLIC_API_BASE_URL .env.development | cut -d'=' -f2)"
echo ""
print_warning "Press Ctrl+C to stop the server"
echo ""
echo -e "${BLUE}========================================${NC}"
echo ""

# Start the development server
# Using the dev script from package.json which includes:
# cross-env NODE_ENV=development nuxt dev --port 3301 --host 0.0.0.0
npm run dev

# This line will only execute if the server is stopped
print_info "Development server stopped."
