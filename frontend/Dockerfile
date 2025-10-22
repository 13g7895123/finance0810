# syntax=docker/dockerfile:1.4

# Development stage
FROM node:20-alpine as development

WORKDIR /app

# Install necessary dependencies for native bindings
RUN apk add --no-cache python3 make g++

# Copy dependency files first for better layer caching
COPY package*.json ./

# Install dependencies (this layer will be cached if package.json hasn't changed)
RUN npm ci --frozen-lockfile || npm install

# Copy application code (this layer changes frequently)
COPY . .

EXPOSE 3000

CMD ["npm", "run", "dev"]

# Production stage
FROM node:20-alpine as production

WORKDIR /app

# Install necessary dependencies for native bindings
RUN apk add --no-cache python3 make g++

# Copy dependency files first
COPY package*.json ./

# Install ALL dependencies first (needed for build)
RUN npm ci --frozen-lockfile || npm install

# Copy application code
COPY . .

# Set production environment and build
ENV NODE_ENV=production
RUN npm run build:prod

# Remove dev dependencies and clean cache
RUN npm prune --production && \
    npm cache clean --force && \
    rm -rf /root/.npm /tmp/*

# Remove build tools to reduce image size
RUN apk del python3 make g++

EXPOSE 3000

CMD ["node", ".output/server/index.mjs"]
